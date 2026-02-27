<?php

namespace App\Jobs;

use App\Models\CertificateIssue;
use App\Models\JourneyAttempt;
use App\Models\JourneyCollection;
use App\Services\AIInteractionService;
use App\Services\CertificatePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class IssueCollectionCertificate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(protected int $certificateIssueId)
    {
        $this->afterCommit = true;
    }

    public function handle(CertificatePdfService $pdfService, AIInteractionService $aiService): void
    {
        $issue = CertificateIssue::with(['certificate', 'user'])->find($this->certificateIssueId);

        if (! $issue) {
            Log::warning('IssueCollectionCertificate: CertificateIssue not found', [
                'certificate_issue_id' => $this->certificateIssueId,
            ]);
            return;
        }

        if (! $issue->certificate) {
            Log::warning('IssueCollectionCertificate: Certificate template not found', [
                'certificate_issue_id' => $issue->id,
                'certificate_id'       => $issue->certificate_id,
            ]);
            return;
        }

        Log::info('IssueCollectionCertificate: Processing', [
            'certificate_issue_id' => $issue->id,
            'certificate_id'       => $issue->certificate_id,
            'user_id'              => $issue->user_id,
            'collection_id'        => $issue->collection_id,
        ]);

        // Step 1: Generate the AI report using the collection's certificate_prompt
        $this->generateAiReport($issue, $aiService);

        // Step 2: Generate the PDF and upload to S3
        try {
            $s3Path = $pdfService->generateAndUpload($issue);

            Log::info('IssueCollectionCertificate: PDF uploaded successfully', [
                'certificate_issue_id' => $issue->id,
                's3_path'              => $s3Path,
            ]);
        } catch (\Throwable $e) {
            Log::error('IssueCollectionCertificate: PDF generation failed', [
                'certificate_issue_id' => $issue->id,
                'error'                => $e->getMessage(),
                'trace'                => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate an AI report for the certificate using the collection's certificate_prompt
     * and all interactions from the last completed attempt of each journey.
     */
    protected function generateAiReport(CertificateIssue $issue, AIInteractionService $aiService): void
    {
        if (! $issue->collection_id) {
            Log::info('IssueCollectionCertificate: No collection_id, skipping AI report');
            return;
        }

        $collection = JourneyCollection::with(['journeys' => function ($q) {
            $q->where('is_published', true)->orderBy('id');
        }])->find($issue->collection_id);

        if (! $collection) {
            Log::warning('IssueCollectionCertificate: Collection not found', [
                'collection_id' => $issue->collection_id,
            ]);
            return;
        }

        $certificatePrompt = $collection->certificate_prompt;
        if (! $certificatePrompt || trim($certificatePrompt) === '') {
            Log::info('IssueCollectionCertificate: No certificate_prompt defined, skipping AI report', [
                'collection_id' => $collection->id,
            ]);
            return;
        }

        // Gather interactions from the last completed attempt of each journey
        $interactionsText = $this->gatherCollectionInteractions($collection, $issue->user_id);

        if (empty($interactionsText)) {
            Log::info('IssueCollectionCertificate: No interactions found, skipping AI report');
            return;
        }

        // Find any attempt ID for prompt logging (use the first one we can find)
        $anyAttemptId = $this->findAnyAttemptId($collection, $issue->user_id);

        $messages = [
            [
                'role'    => 'system',
                'content' => $certificatePrompt,
            ],
            [
                'role'    => 'user',
                'content' => $interactionsText,
            ],
        ];

        try {
            Log::info('IssueCollectionCertificate: Generating AI report', [
                'certificate_issue_id' => $issue->id,
                'collection_id'        => $collection->id,
                'interaction_length'   => strlen($interactionsText),
            ]);

            $response = $aiService->executeChatRequest($messages, [
                'journey_attempt_id' => $anyAttemptId,
                'ai_model'           => config('openai.default_model', 'gpt-4'),
                'max_tokens'         => 4000,
            ]);

            $reportContent = $response->choices[0]->message->content ?? null;

            if ($reportContent && is_string($reportContent)) {
                $issue->ai_report = trim($reportContent);
                $issue->save();

                Log::info('IssueCollectionCertificate: AI report saved', [
                    'certificate_issue_id' => $issue->id,
                    'report_length'        => strlen($issue->ai_report),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('IssueCollectionCertificate: AI report generation failed', [
                'certificate_issue_id' => $issue->id,
                'error'                => $e->getMessage(),
            ]);
            // Don't rethrow — PDF generation should still proceed even if report fails
        }
    }

    /**
     * Gather all interactions from the last completed attempt of each journey in the collection.
     */
    protected function gatherCollectionInteractions(JourneyCollection $collection, int $userId): string
    {
        $parts = [];

        foreach ($collection->journeys as $journey) {
            // Find the last completed/awaiting_feedback attempt for this user and journey
            $attempt = JourneyAttempt::where('user_id', $userId)
                ->where('journey_id', $journey->id)
                ->whereIn('status', ['completed', 'awaiting_feedback'])
                ->where(function ($q) {
                    $q->whereNull('journey_type')->orWhere('journey_type', '!=', 'preview');
                })
                ->latest('completed_at')
                ->first();

            if (! $attempt) {
                continue;
            }

            // Load the interactions for this attempt, ordered chronologically
            $responses = $attempt->stepResponses()
                ->orderBy('id')
                ->get(['user_input', 'ai_response']);

            if ($responses->isEmpty()) {
                continue;
            }

            $parts[] = "=== Journey: {$journey->title} ===";
            if ($journey->description) {
                $parts[] = "Description: {$journey->description}";
                $parts[] = '';
            }

            foreach ($responses as $response) {
                if ($response->user_input) {
                    $parts[] = "Student: {$response->user_input}";
                }
                if ($response->ai_response) {
                    $parts[] = "AI: {$response->ai_response}";
                }
            }

            $parts[] = ''; // blank line separator
        }

        return implode("\n", $parts);
    }

    /**
     * Find any valid attempt ID for this user in the collection (needed for prompt logging).
     */
    protected function findAnyAttemptId(JourneyCollection $collection, int $userId): ?int
    {
        $journeyIds = $collection->journeys->pluck('id');

        return JourneyAttempt::where('user_id', $userId)
            ->whereIn('journey_id', $journeyIds)
            ->whereIn('status', ['completed', 'awaiting_feedback'])
            ->value('id');
    }
}
