<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchCertificateIssuedPayload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        protected int $certificateId,
        protected int $collectionId
    ) {
    }

    public function handle(): void
    {
        Log::info('Dispatching certificate issued payload', [
            'certificate_id' => $this->certificateId,
            'collection_id' => $this->collectionId,
        ]);

        // Hook for downstream integrations (webhooks, notifications, etc.)
    }
}
