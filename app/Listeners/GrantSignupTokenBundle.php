<?php

namespace App\Listeners;

use App\Models\TokenBundle;
use App\Models\UserTokenGrant;
use App\Services\TokenLedger;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

class GrantSignupTokenBundle
{
    public function __construct(private TokenLedger $ledger)
    {
    }

    /**
     * Grant the configured signup token bundle to a newly verified user.
     */
    public function handle(Verified $event): void
    {
        $user = $event->user;
        $bundleId = (int) config('site.signup_token_bundle');

        if ($bundleId <= 0) {
            return;
        }

        // Don't grant if the user already received this signup bundle
        $alreadyGranted = $user->tokenGrants()
            ->where('token_bundle_id', $bundleId)
            ->where('source', UserTokenGrant::SOURCE_PROMO)
            ->exists();

        if ($alreadyGranted) {
            return;
        }

        // Find the bundle regardless of its active status
        $bundle = TokenBundle::find($bundleId);

        if (! $bundle) {
            Log::warning('Signup token bundle not found', ['bundle_id' => $bundleId]);
            return;
        }

        $this->ledger->grant($user, $bundle->token_amount, [
            'token_bundle_id' => $bundle->id,
            'source' => UserTokenGrant::SOURCE_PROMO,
            'expires_after_days' => $bundle->expires_after_days,
            'description' => 'Welcome tokens – signup bundle: ' . $bundle->name,
            'notes' => 'Auto-granted on email verification',
        ]);

        Log::info('Signup token bundle granted', [
            'user_id' => $user->id,
            'bundle_id' => $bundle->id,
            'tokens' => $bundle->token_amount,
        ]);
    }

    /**
     * Grant the signup bundle to an OAuth user who is verified at creation time.
     * Call this statically from SocialLoginController or similar.
     */
    public static function grantForNewUser($user): void
    {
        $bundleId = (int) config('site.signup_token_bundle');

        if ($bundleId <= 0) {
            return;
        }

        $bundle = TokenBundle::find($bundleId);

        if (! $bundle) {
            Log::warning('Signup token bundle not found', ['bundle_id' => $bundleId]);
            return;
        }

        app(TokenLedger::class)->grant($user, $bundle->token_amount, [
            'token_bundle_id' => $bundle->id,
            'source' => UserTokenGrant::SOURCE_PROMO,
            'expires_after_days' => $bundle->expires_after_days,
            'description' => 'Welcome tokens – signup bundle: ' . $bundle->name,
            'notes' => 'Auto-granted on OAuth registration',
        ]);

        Log::info('Signup token bundle granted (OAuth)', [
            'user_id' => $user->id,
            'bundle_id' => $bundle->id,
            'tokens' => $bundle->token_amount,
        ]);
    }
}
