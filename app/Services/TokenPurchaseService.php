<?php

namespace App\Services;

use App\Exceptions\VirtualVendorDisabledException;
use App\Models\TokenBundle;
use App\Models\TokenPurchase;
use App\Models\User;
use App\Models\UserTokenGrant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TokenPurchaseService
{
    public function __construct(private readonly TokenLedger $ledger)
    {
    }

    public function createVirtualPurchase(User $user, TokenBundle $bundle): TokenPurchase
    {
        if (!config('tokens.virtual_vendor.enabled')) {
            throw new VirtualVendorDisabledException();
        }

        return DB::transaction(function () use ($user, $bundle) {
            $purchase = TokenPurchase::create([
                'user_id' => $user->id,
                'token_bundle_id' => $bundle->id,
                'payment_provider' => 'virtual_vendor',
                'status' => TokenPurchase::STATUS_PENDING,
                'amount_cents' => $bundle->price_cents,
                'currency' => $bundle->currency,
                'tokens' => $bundle->token_amount,
                'metadata' => ['bundle_name' => $bundle->name],
                'purchased_at' => Carbon::now(),
            ]);

            $grant = $this->ledger->grant($user, $bundle->token_amount, [
                'token_bundle_id' => $bundle->id,
                'token_purchase_id' => $purchase->id,
                'source' => UserTokenGrant::SOURCE_PURCHASE,
                'description' => sprintf('Purchase via Virtual Vendor (%s)', $bundle->name),
            ]);

            $purchase->update([
                'status' => TokenPurchase::STATUS_COMPLETED,
                'completed_at' => Carbon::now(),
            ]);

            return $purchase->fresh(['bundle']);
        });
    }
}
