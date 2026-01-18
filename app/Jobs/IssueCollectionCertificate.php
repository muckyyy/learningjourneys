<?php

namespace App\Jobs;

use App\Models\CertificateIssue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IssueCollectionCertificate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(protected int $certificateIssueId)
    {
    }

    public function handle(): void
    {
        $issue = CertificateIssue::with(['certificate', 'user'])->find($this->certificateIssueId);

        if (! $issue) {
            return;
        }

        Log::info('Processing certificate issue job', [
            'certificate_issue_id' => $issue->id,
            'certificate_id' => $issue->certificate_id,
            'user_id' => $issue->user_id,
        ]);
    }
}
