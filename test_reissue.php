<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\JourneyCollection;
use App\Models\JourneyAttempt;
use App\Services\CertificateIssueService;
use App\Enums\CertificateVariable;

// Find collection 1 with its certificate and institution
$collection = JourneyCollection::with(['certificate', 'institution', 'journeys'])->find(1);

if (!$collection) {
    echo "Collection 1 not found\n";
    exit(1);
}

echo "Collection: {$collection->name}\n";
echo "Certificate: " . ($collection->certificate ? $collection->certificate->name : 'NONE') . "\n";
echo "Institution: " . ($collection->institution ? $collection->institution->name : 'NONE') . "\n";

// Find the user who completed journeys in this collection
$journeyIds = $collection->journeys->pluck('id');
$attempt = JourneyAttempt::whereIn('journey_id', $journeyIds)
    ->whereIn('status', ['completed', 'awaiting_feedback'])
    ->first();

if (!$attempt) {
    echo "No completed attempts found\n";
    exit(1);
}

$user = App\Models\User::find($attempt->user_id);
echo "User: {$user->name} (ID: {$user->id})\n";

// Issue the certificate
$service = new CertificateIssueService();

$overrides = [
    'variables' => [
        CertificateVariable::COLLECTION_NAME => $collection->name,
        CertificateVariable::JOURNEY_COUNT => $journeyIds->count(),
    ],
];

try {
    $issue = $service->issue(
        $collection->certificate,
        $user,
        $overrides,
        $collection->institution,
        $collection->id
    );
    echo "CertificateIssue created: ID={$issue->id}, QR={$issue->qr_code}\n";
} catch (\Throwable $e) {
    echo "ERROR: {$e->getMessage()}\n";
}
