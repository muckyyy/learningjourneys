<?php

namespace App\Services;

use App\Models\TokenPurchase;
use Illuminate\Support\Facades\Storage;
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;

class InvoiceService
{
    private const S3_FOLDER = 'invoices';

    public function buildInvoice(TokenPurchase $purchase): Invoice
    {
        $user = $purchase->user;

        $customer = new Buyer([
            'name'          => $user->name,
            'custom_fields' => [
                'email' => $user->email,
            ],
        ]);

        $item = (new InvoiceItem())
            ->title($purchase->bundle->name ?? 'Token Bundle')
            ->description(sprintf('%d tokens', $purchase->tokens))
            ->pricePerUnit($purchase->amount_cents / 100)
            ->quantity(1);

        return Invoice::make()
            ->buyer($customer)
            ->serialNumberFormat('INV-{SEQUENCE}')
            ->sequence($purchase->id)
            ->date($purchase->purchased_at ?? $purchase->created_at)
            ->currencyCode($purchase->currency ?? 'CHF')
            ->currencySymbol($purchase->currency ?? 'CHF')
            ->addItem($item)
            ->filename("invoice-{$purchase->id}")
            ->logo(public_path('logo/logo.png'));
    }

    private function s3Path(TokenPurchase $purchase): string
    {
        return self::S3_FOLDER . "/invoice-{$purchase->id}.pdf";
    }

    private function ensureStoredOnS3(TokenPurchase $purchase): string
    {
        $path = $this->s3Path($purchase);
        $disk = Storage::disk('s3');

        if (!$disk->exists($path)) {
            $invoice = $this->buildInvoice($purchase);
            $pdf = $invoice->render()->output;
            $disk->put($path, $pdf);
        }

        return $path;
    }

    /**
     * Generate and store the invoice PDF on S3. Called on purchase completion.
     */
    public function ensureStored(TokenPurchase $purchase): string
    {
        return $this->ensureStoredOnS3($purchase);
    }

    public function download(TokenPurchase $purchase)
    {
        $path = $this->ensureStoredOnS3($purchase);

        return Storage::disk('s3')->download($path, "invoice-{$purchase->id}.pdf");
    }

    public function stream(TokenPurchase $purchase)
    {
        $path = $this->ensureStoredOnS3($purchase);

        return Storage::disk('s3')->response($path, "invoice-{$purchase->id}.pdf", [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function temporaryUrl(TokenPurchase $purchase, int $minutes = 5): string
    {
        $path = $this->ensureStoredOnS3($purchase);

        return Storage::disk('s3')->temporaryUrl($path, now()->addMinutes($minutes));
    }
}
