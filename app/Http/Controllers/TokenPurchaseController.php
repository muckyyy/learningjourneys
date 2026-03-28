<?php

namespace App\Http\Controllers;

use App\Exceptions\VirtualVendorDisabledException;
use App\Models\TokenBundle;
use App\Models\TokenPurchase;
use App\Services\InvoiceService;
use App\Services\TokenLedger;
use App\Services\TokenPurchaseService;
use Illuminate\Http\Request;

class TokenPurchaseController extends Controller
{
    public function __construct(private TokenLedger $ledger, private TokenPurchaseService $purchaseService)
    {
        $this->middleware(['auth', 'verified']);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $bundles = TokenBundle::active()->orderBy('token_amount')->get();
        $balance = $this->ledger->balance($user);
        $transactions = $user->tokenTransactions()->with(['journey', 'purchase'])->latest()->limit(15)->get();
        $purchases = $user->tokenPurchases()
            ->with('bundle')
            ->where('status', TokenPurchase::STATUS_COMPLETED)
            ->orderByDesc('purchased_at')
            ->get();

        return view('tokens.index', [
            'bundles' => $bundles,
            'balance' => $balance,
            'transactions' => $transactions,
            'purchases' => $purchases,
            'virtualVendorEnabled' => config('tokens.virtual_vendor.enabled'),
            'payrexxEnabled' => config('payrexx.enabled'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bundle_id' => ['required', 'exists:token_bundles,id'],
        ]);

        $bundle = TokenBundle::active()->findOrFail($data['bundle_id']);

        try {
            $this->purchaseService->createVirtualPurchase($request->user(), $bundle);
        } catch (VirtualVendorDisabledException $e) {
            return redirect()->route('tokens.index')->with('error', $e->getMessage());
        }

        return redirect()->route('tokens.index')->with('status', 'Tokens added to your account.');
    }

    public function balance(Request $request)
    {
        return response()->json($this->ledger->balance($request->user()));
    }

    public function downloadInvoice(TokenPurchase $purchase, InvoiceService $invoiceService)
    {
        abort_unless($purchase->user_id === auth()->id(), 403);
        abort_unless($purchase->status === TokenPurchase::STATUS_COMPLETED, 404);

        return redirect($invoiceService->temporaryUrl($purchase));
    }
}
