<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\TokenBundle;
use App\Models\User;
use App\Models\UserTokenGrant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferralService
{
    public function __construct(private readonly TokenLedger $ledger)
    {
    }

    /**
     * Record a referral when a new user signs up via a referral link.
     */
    public function recordReferral(User $referrer, User $referred): ?Referral
    {
        if (! config('site.referal_enabled')) {
            return null;
        }

        // Referrals are only available to regular users
        if ($referrer->role !== \App\Enums\UserRole::REGULAR) {
            return null;
        }

        // Don't allow self-referral
        if ($referrer->id === $referred->id) {
            return null;
        }

        // Don't create duplicate referral
        if (Referral::where('referred_id', $referred->id)->exists()) {
            return null;
        }

        $referral = Referral::create([
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
        ]);

        Log::info('Referral recorded', [
            'referrer_id' => $referrer->id,
            'referred_id' => $referred->id,
        ]);

        return $referral;
    }

    /**
     * Mark a referred user as having made their first payment
     * and check if the referrer qualifies for a reward.
     */
    public function markPaid(User $user): void
    {
        if (! config('site.referal_enabled')) {
            return;
        }

        $referral = Referral::where('referred_id', $user->id)
            ->where('has_paid', false)
            ->first();

        if (! $referral) {
            return;
        }

        $referral->update([
            'has_paid' => true,
            'first_payment_at' => now(),
        ]);

        Log::info('Referral marked as paid', [
            'referral_id' => $referral->id,
            'referrer_id' => $referral->referrer_id,
            'referred_id' => $referral->referred_id,
        ]);

        $this->checkAndGrantReward($referral->referrer_id);
    }

    /**
     * Check if a referrer has hit the frequency threshold and grant reward.
     */
    private function checkAndGrantReward(int $referrerId): void
    {
        $frequency = (int) config('site.referal_frequency', 10);
        $bundleId  = (int) config('site.referal_token_bundle', 0);

        if ($frequency <= 0 || $bundleId <= 0) {
            return;
        }

        // Count paid but unrewarded referrals for this referrer
        $unrewarded = Referral::where('referrer_id', $referrerId)
            ->where('has_paid', true)
            ->where('rewarded', false)
            ->count();

        if ($unrewarded < $frequency) {
            return;
        }

        // Grant reward in a transaction
        DB::transaction(function () use ($referrerId, $frequency, $bundleId) {
            // Lock rows to prevent race conditions
            $toReward = Referral::where('referrer_id', $referrerId)
                ->where('has_paid', true)
                ->where('rewarded', false)
                ->orderBy('first_payment_at')
                ->limit($frequency)
                ->lockForUpdate()
                ->get();

            if ($toReward->count() < $frequency) {
                return;
            }

            $bundle = TokenBundle::find($bundleId);
            if (! $bundle) {
                Log::warning('Referral token bundle not found', ['bundle_id' => $bundleId]);
                return;
            }

            $referrer = User::find($referrerId);
            if (! $referrer) {
                return;
            }

            // Mark these referrals as rewarded
            Referral::whereIn('id', $toReward->pluck('id'))->update(['rewarded' => true]);

            // Grant the token bundle
            $this->ledger->grant($referrer, $bundle->token_amount, [
                'token_bundle_id' => $bundle->id,
                'source' => UserTokenGrant::SOURCE_PROMO,
                'expires_after_days' => $bundle->expires_after_days,
                'description' => sprintf('Referral reward – %d paid referrals (%s)', $frequency, $bundle->name),
                'notes' => 'Auto-granted by referral program',
            ]);

            Log::info('Referral reward granted', [
                'referrer_id' => $referrerId,
                'bundle_id' => $bundle->id,
                'tokens' => $bundle->token_amount,
                'referrals_rewarded' => $toReward->count(),
            ]);
        });
    }

    /**
     * Get referral stats for a user's profile dashboard.
     * Only regular users participate in the referral programme.
     */
    public function getStats(User $user): array
    {
        if ($user->role !== \App\Enums\UserRole::REGULAR) {
            return ['enabled' => false];
        }

        $frequency = (int) config('site.referal_frequency', 10);

        $totalReferred = Referral::where('referrer_id', $user->id)->count();
        $paidReferred  = Referral::where('referrer_id', $user->id)->where('has_paid', true)->count();
        $rewarded      = Referral::where('referrer_id', $user->id)->where('rewarded', true)->count();

        // Progress toward next reward = paid-but-unrewarded count
        $unrewarded = $paidReferred - $rewarded;
        $progressTowardNext = $frequency > 0 ? $unrewarded % $frequency : 0;
        $rewardsEarned = $frequency > 0 ? intdiv($rewarded, $frequency) : 0;

        return [
            'enabled'              => (bool) config('site.referal_enabled'),
            'frequency'            => $frequency,
            'total_referred'       => $totalReferred,
            'paid_referred'        => $paidReferred,
            'progress'             => $progressTowardNext,
            'rewards_earned'       => $rewardsEarned,
            'referral_code'        => $user->referral_id,
            'referral_link'        => url('/register?ref=' . $user->referral_id),
            'recent_referrals'     => Referral::where('referrer_id', $user->id)
                ->with('referred:id,name,created_at')
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }
}
