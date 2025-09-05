<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Test data
$journeyId = 1; // You may need to adjust this
$userId = 1; // You may need to adjust this
$type = 'chat';

echo "Testing start journey API functionality...\n\n";

try {
    // Check if journey exists
    $journey = App\Models\Journey::find($journeyId);
    if (!$journey) {
        echo "❌ Journey with ID $journeyId not found\n";
        // Let's check what journeys exist
        $journeys = App\Models\Journey::all(['id', 'title', 'is_published']);
        echo "Available journeys:\n";
        foreach ($journeys as $j) {
            echo "  - ID {$j->id}: {$j->title} (published: " . ($j->is_published ? 'yes' : 'no') . ")\n";
        }
        exit(1);
    }
    echo "✅ Journey found: {$journey->title}\n";

    // Check if user exists
    $user = App\Models\User::find($userId);
    if (!$user) {
        echo "❌ User with ID $userId not found\n";
        exit(1);
    }
    echo "✅ User found: {$user->name}\n";

    // Check if user already has an active attempt
    $existingAttempt = App\Models\JourneyAttempt::where('user_id', $userId)
        ->where('journey_id', $journeyId)
        ->where('status', 'in_progress')
        ->first();

    if ($existingAttempt) {
        echo "⚠️  User already has an active attempt for this journey (ID: {$existingAttempt->id})\n";
    } else {
        echo "✅ No existing active attempt found\n";
    }

    // Get profile fields
    $profileFields = App\Models\ProfileField::where('is_active', true)->get();
    echo "✅ Found " . $profileFields->count() . " active profile fields\n";

    // Test the profile data population
    $progressData = [];
    foreach ($profileFields as $field) {
        $value = $field->getValueForUser($userId);
        if ($value !== null) {
            $progressData[$field->short_name] = $value;
            echo "  - {$field->short_name}: $value\n";
        }
    }

    // Add basic metadata
    $progressData['current_step'] = 1;
    $progressData['started_at'] = now()->toISOString();
    $progressData['mode'] = $type;
    $progressData['student_firstname'] = $user->firstname ?? $user->name;
    $progressData['student_lastname'] = $user->lastname ?? '';
    $progressData['student_email'] = $user->email;
    $progressData['institution_name'] = $user->institution->name ?? '';
    $progressData['journey_title'] = $journey->title;
    $progressData['journey_description'] = $journey->description;

    echo "\n✅ Progress data prepared:\n";
    foreach ($progressData as $key => $value) {
        echo "  - $key: $value\n";
    }

    if (!$existingAttempt) {
        echo "\n🚀 Creating new journey attempt...\n";
        
        $attempt = App\Models\JourneyAttempt::create([
            'user_id' => $userId,
            'journey_id' => $journeyId,
            'journey_type' => 'attempt',
            'mode' => $type,
            'status' => 'in_progress',
            'started_at' => now(),
            'current_step' => 1,
            'progress_data' => $progressData
        ]);

        echo "✅ Journey attempt created successfully!\n";
        echo "  - Attempt ID: {$attempt->id}\n";
        echo "  - Status: {$attempt->status}\n";
        echo "  - Journey Type: {$attempt->journey_type}\n";
        echo "  - Current Step: {$attempt->current_step}\n";
        
        // The redirect URL would be:
        $redirectUrl = "/journeys/{$attempt->id}/continue";
        echo "  - Redirect URL: $redirectUrl\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
