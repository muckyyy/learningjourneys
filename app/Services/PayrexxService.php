<?php

namespace App\Services;

use App\Models\TokenBundle;
use App\Models\TokenPurchase;
use App\Models\User;
use Illuminate\Support\Carbon;
use Payrexx\Models\Request\Gateway;
use Payrexx\Payrexx;
use Payrexx\PayrexxException;

class PayrexxService
{
    private Payrexx $client;

    public function __construct()
    {
        $this->client = new Payrexx(
            config('payrexx.instance'),
            config('payrexx.api_secret'),
        );
    }

    /**
     * Create a Payrexx payment gateway for a token bundle purchase.
     *
     * @return array{purchase: TokenPurchase, payment_url: string}
     */
    public function createGateway(User $user, TokenBundle $bundle): array
    {
        // Create a pending purchase record first
        $purchase = TokenPurchase::create([
            'user_id' => $user->id,
            'token_bundle_id' => $bundle->id,
            'payment_provider' => 'payrexx',
            'status' => TokenPurchase::STATUS_PENDING,
            'amount_cents' => $bundle->price_cents,
            'currency' => $bundle->currency ?? config('payrexx.currency', 'CHF'),
            'tokens' => $bundle->token_amount,
            'metadata' => ['bundle_name' => $bundle->name],
            'purchased_at' => Carbon::now(),
        ]);

        $gateway = new Gateway();
        $gateway->setAmount($bundle->price_cents);
        $gateway->setCurrency($purchase->currency);
        $gateway->setSuccessRedirectUrl(route('payrexx.success', ['purchase' => $purchase->id]));
        $gateway->setFailedRedirectUrl(route('payrexx.failed', ['purchase' => $purchase->id]));
        $gateway->setCancelRedirectUrl(route('payrexx.cancel', ['purchase' => $purchase->id]));
        $gateway->setReferenceId("purchase-{$purchase->id}");
        $gateway->setPurpose("Token Bundle: {$bundle->name}");
        $gateway->setValidity(config('payrexx.gateway_validity_minutes', 30));

        $gateway->addField('email', $user->email);
        $gateway->addField('forename', $user->name);

        try {
            $response = $this->client->create($gateway);

            $purchase->update([
                'provider_reference' => $response->getId(),
                'metadata' => array_merge($purchase->metadata ?? [], [
                    'gateway_id' => $response->getId(),
                    'gateway_hash' => $response->getHash(),
                ]),
            ]);

            return [
                'purchase' => $purchase->fresh(),
                'payment_url' => $response->getLink(),
            ];
        } catch (PayrexxException $e) {
            $purchase->update([
                'status' => TokenPurchase::STATUS_FAILED,
                'metadata' => array_merge($purchase->metadata ?? [], [
                    'error' => $e->getMessage(),
                ]),
            ]);

            throw $e;
        }
    }

    /**
     * Retrieve a gateway from Payrexx to check its status.
     */
    public function getGateway(int $gatewayId): \Payrexx\Models\Response\Gateway
    {
        $gateway = new Gateway();
        $gateway->setId($gatewayId);

        return $this->client->getOne($gateway);
    }

    /**
     * Check if a gateway payment was confirmed.
     */
    public function isConfirmed(int $gatewayId): bool
    {
        try {
            $response = $this->getGateway($gatewayId);

            return $response->getStatus() === 'confirmed';
        } catch (PayrexxException) {
            return false;
        }
    }
}
