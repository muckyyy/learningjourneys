<?php

namespace App\Http\Controllers;

use App\Models\TokenBundle;
use App\Models\TokenPurchase;
use App\Models\UserTokenGrant;
use App\Services\InvoiceService;
use App\Services\PayrexxService;
use App\Services\TokenLedger;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrexxController extends Controller
{
    public function __construct(
        private readonly PayrexxService $payrexx,
        private readonly TokenLedger $ledger,
        private readonly ReferralService $referralService,
        private readonly InvoiceService $invoiceService,
    ) {
        $this->middleware(['auth', 'verified'])->except('webhook');
    }

    /**
     * Initiate a Payrexx payment for a token bundle.
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'bundle_id' => ['required', 'exists:token_bundles,id'],
        ]);

        $bundle = TokenBundle::active()->findOrFail($data['bundle_id']);

        try {
            $result = $this->payrexx->createGateway($request->user(), $bundle);

            return redirect()->away($result['payment_url']);
        } catch (\Payrexx\PayrexxException $e) {
            Log::error('Payrexx gateway creation failed', ['error' => $e->getMessage()]);

            return redirect()->route('tokens.index')
                ->with('error', 'Payment could not be initiated. Please try again.');
        }
    }

    /**
     * Handle successful payment redirect from Payrexx.
     */
    public function success(Request $request, TokenPurchase $purchase)
    {
        abort_unless($purchase->user_id === auth()->id(), 403);

        // Verify with Payrexx API that payment is actually confirmed
        if ($purchase->status === TokenPurchase::STATUS_PENDING) {
            $gatewayId = $purchase->metadata['gateway_id'] ?? null;

            if ($gatewayId && $this->payrexx->isConfirmed($gatewayId)) {
                $this->completePurchase($purchase);
            }
        }

        if ($purchase->status === TokenPurchase::STATUS_COMPLETED) {
            return redirect()->route('tokens.index')
                ->with('status', 'Payment successful! Tokens have been added to your account.');
        }

        // Payment not yet confirmed — might arrive via webhook
        return redirect()->route('tokens.index')
            ->with('status', 'Payment is being processed. Tokens will be added shortly.');
    }

    /**
     * Handle failed payment redirect from Payrexx.
     */
    public function failed(Request $request, TokenPurchase $purchase)
    {
        abort_unless($purchase->user_id === auth()->id(), 403);

        if ($purchase->status === TokenPurchase::STATUS_PENDING) {
            $purchase->update(['status' => TokenPurchase::STATUS_FAILED]);
        }

        return redirect()->route('tokens.index')
            ->with('error', 'Payment failed. No tokens were charged.');
    }

    /**
     * Handle cancelled payment redirect from Payrexx.
     */
    public function cancel(Request $request, TokenPurchase $purchase)
    {
        abort_unless($purchase->user_id === auth()->id(), 403);

        if ($purchase->status === TokenPurchase::STATUS_PENDING) {
            $purchase->update(['status' => TokenPurchase::STATUS_FAILED]);
        }

        return redirect()->route('tokens.index')
            ->with('error', 'Payment was cancelled.');
    }

    /**
     * Handle Payrexx webhook notifications.
     */
    public function webhook(Request $request)
    {
        $transaction = $request->input('transaction');

        if (!$transaction || empty($transaction['referenceId'])) {
            return response()->json(['status' => 'ignored'], 200);
        }

        // referenceId format: "purchase-{id}"
        $referenceId = $transaction['referenceId'];
        if (!str_starts_with($referenceId, 'purchase-')) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $purchaseId = (int) str_replace('purchase-', '', $referenceId);
        $purchase = TokenPurchase::find($purchaseId);

        if (!$purchase) {
            Log::warning('Payrexx webhook: purchase not found', ['reference' => $referenceId]);
            return response()->json(['status' => 'not_found'], 200);
        }

        $status = $transaction['status'] ?? '';

        if ($status === 'confirmed' && $purchase->status === TokenPurchase::STATUS_PENDING) {
            $this->completePurchase($purchase);
            Log::info('Payrexx webhook: purchase completed', ['purchase_id' => $purchase->id]);
        } elseif (in_array($status, ['failed', 'declined', 'error', 'expired'])) {
            $purchase->update(['status' => TokenPurchase::STATUS_FAILED]);
            Log::info('Payrexx webhook: purchase failed', ['purchase_id' => $purchase->id, 'status' => $status]);
        }

        return response()->json(['status' => 'ok'], 200);
    }

    /**
     * Complete a purchase: grant tokens and update status.
     */
    private function completePurchase(TokenPurchase $purchase): void
    {
        $user = $purchase->user;
        $bundle = $purchase->bundle;

        DB::transaction(function () use ($purchase, $user, $bundle) {
            $this->ledger->grant($user, $purchase->tokens, [
                'token_bundle_id' => $bundle?->id,
                'token_purchase_id' => $purchase->id,
                'source' => UserTokenGrant::SOURCE_PURCHASE,
                'description' => sprintf('Purchase via Payrexx (%s)', $bundle?->name ?? 'Token Bundle'),
            ]);

            $purchase->update([
                'status' => TokenPurchase::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            $this->referralService->markPaid($user);
        });

        // Generate and store invoice on S3
        $this->invoiceService->ensureStored($purchase);
    }
}
