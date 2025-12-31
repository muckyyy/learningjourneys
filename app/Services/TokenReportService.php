<?php

namespace App\Services;

use App\Models\TokenBundle;
use App\Models\TokenPurchase;
use App\Models\TokenTransaction;
use App\Models\UserTokenGrant;
use Illuminate\Support\Carbon;

class TokenReportService
{
    public function summary(): array
    {
        $now = Carbon::now();
        $window = $now->copy()->subDays(30);

        $credits = (int) TokenTransaction::where('type', TokenTransaction::TYPE_CREDIT)->sum('amount');
        $debits = (int) TokenTransaction::where('type', TokenTransaction::TYPE_DEBIT)->sum('amount');
        $activeTokens = (int) UserTokenGrant::where('tokens_remaining', '>', 0)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->sum('tokens_remaining');
        $expiredGrants = (int) UserTokenGrant::whereNotNull('expires_at')->where('expires_at', '<=', $now)->count();

        $purchasesLast30 = TokenPurchase::where('created_at', '>=', $window)->count();
        $revenueLast30 = (int) TokenPurchase::where('created_at', '>=', $window)
            ->where('status', TokenPurchase::STATUS_COMPLETED)
            ->sum('amount_cents');

        $topBundles = TokenBundle::withCount(['purchases as completed_purchases_count' => function ($query) {
                $query->where('status', TokenPurchase::STATUS_COMPLETED);
            }])
            ->orderByDesc('completed_purchases_count')
            ->take(5)
            ->get(['id', 'name', 'token_amount', 'price_cents'])
            ->map(fn ($bundle) => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'token_amount' => $bundle->token_amount,
                'price_cents' => $bundle->price_cents,
                'completed_purchases' => $bundle->completed_purchases_count,
            ]);

        return [
            'total_tokens_granted' => $credits,
            'total_tokens_spent' => $debits,
            'active_tokens' => $activeTokens,
            'expired_grants' => $expiredGrants,
            'purchases_last_30_days' => $purchasesLast30,
            'revenue_last_30_days_cents' => $revenueLast30,
            'top_bundles' => $topBundles,
        ];
    }
}
