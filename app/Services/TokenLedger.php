<?php

namespace App\Services;

use App\Exceptions\InsufficientTokensException;
use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\TokenTransaction;
use App\Models\User;
use App\Models\UserTokenGrant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TokenLedger
{
    public function balance(User $user): array
    {
        $grants = $this->activeGrantsQuery($user)->get();
        $total = (int) $grants->sum('tokens_remaining');
        $expiringSoon = (int) $grants->filter(fn ($grant) => $grant->expiresSoon())->sum('tokens_remaining');

        return [
            'total' => $total,
            'expiring_soon' => $expiringSoon,
            'grants' => $grants,
        ];
    }

    public function grant(User $user, int $tokens, array $options = []): UserTokenGrant
    {
        return DB::transaction(function () use ($user, $tokens, $options) {
            $expiresAt = $options['expires_at'] ?? Carbon::now()->addDays($options['expires_after_days'] ?? config('tokens.default_expiration_days', 365));

            $grant = UserTokenGrant::create([
                'user_id' => $user->id,
                'token_bundle_id' => $options['token_bundle_id'] ?? null,
                'token_purchase_id' => $options['token_purchase_id'] ?? null,
                'source' => $options['source'] ?? UserTokenGrant::SOURCE_PURCHASE,
                'tokens_total' => $tokens,
                'tokens_used' => 0,
                'tokens_remaining' => $tokens,
                'expires_at' => $expiresAt,
                'granted_at' => $options['granted_at'] ?? Carbon::now(),
                'granted_by' => $options['granted_by'] ?? null,
                'notes' => $options['notes'] ?? null,
                'metadata' => $options['metadata'] ?? null,
            ]);

            $balanceAfter = $this->activeGrantsQuery($user)->sum('tokens_remaining');

            TokenTransaction::create([
                'user_id' => $user->id,
                'user_token_grant_id' => $grant->id,
                'token_purchase_id' => $options['token_purchase_id'] ?? null,
                'type' => TokenTransaction::TYPE_CREDIT,
                'amount' => $tokens,
                'balance_after' => $balanceAfter,
                'description' => $options['description'] ?? 'Token grant',
                'metadata' => $options['transaction_metadata'] ?? null,
                'occurred_at' => Carbon::now(),
            ]);

            return $grant;
        });
    }

    public function spendForJourney(User $user, Journey $journey, ?JourneyAttempt $attempt = null): array
    {
        $cost = (int) ($journey->token_cost ?? 0);
        if ($cost <= 0) {
            return ['amount' => 0, 'breakdown' => collect()];
        }

        return $this->spend($user, $cost, sprintf('Journey start: %s', $journey->title), [
            'journey' => $journey,
            'attempt' => $attempt,
        ]);
    }

    public function spend(User $user, int $amount, string $description, array $context = []): array
    {
        if ($amount <= 0) {
            return ['amount' => 0, 'breakdown' => collect()];
        }

        return DB::transaction(function () use ($user, $amount, $description, $context) {
            $grants = $this->activeGrantsQuery($user, lock: true)->orderBy('expires_at')->orderBy('granted_at')->orderBy('id')->get();
            $available = (int) $grants->sum('tokens_remaining');

            if ($available < $amount) {
                throw new InsufficientTokensException($amount, $available);
            }

            $remaining = $amount;
            $breakdown = collect();
            $currentBalance = $available;

            foreach ($grants as $grant) {
                if ($remaining <= 0) {
                    break;
                }

                $usable = min($grant->tokens_remaining, $remaining);
                if ($usable <= 0) {
                    continue;
                }

                $grant->tokens_used += $usable;
                $grant->tokens_remaining -= $usable;
                $grant->save();

                $currentBalance -= $usable;

                $transaction = TokenTransaction::create([
                    'user_id' => $user->id,
                    'user_token_grant_id' => $grant->id,
                    'token_purchase_id' => $grant->token_purchase_id,
                    'journey_id' => $context['journey']->id ?? null,
                    'journey_attempt_id' => $context['attempt']->id ?? null,
                    'type' => TokenTransaction::TYPE_DEBIT,
                    'amount' => $usable,
                    'balance_after' => $currentBalance,
                    'description' => $description,
                    'metadata' => [
                        'journey_title' => $context['journey']->title ?? null,
                    ],
                    'occurred_at' => Carbon::now(),
                ]);

                $breakdown->push($transaction);
                $remaining -= $usable;
            }

            return [
                'amount' => $amount,
                'breakdown' => $breakdown,
            ];
        });
    }

    public function activeGrantsQuery(User $user, bool $lock = false)
    {
        $query = UserTokenGrant::where('user_id', $user->id)
            ->where('tokens_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now());
            });

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query;
    }
}
