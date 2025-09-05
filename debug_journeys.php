<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== DEBUGGING JOURNEY BUTTONS ===\n\n";

// Check users
echo "Users in system:\n";
$users = App\Models\User::all(['id', 'name', 'email', 'role']);
foreach ($users as $user) {
    echo "ID: {$user->id} | Name: {$user->name} | Email: {$user->email} | Role: {$user->role}\n";
}
echo "\n";

// Check journeys
$journeys = App\Models\Journey::with(['steps', 'creator'])->get();
echo "Total journeys: " . $journeys->count() . "\n\n";

foreach ($journeys as $journey) {
    echo "Journey ID: {$journey->id}\n";
    echo "Title: {$journey->title}\n";
    echo "Published: " . ($journey->is_published ? 'Yes' : 'No') . "\n";
    echo "Steps count: " . $journey->steps->count() . "\n";
    echo "Created by: User ID {$journey->created_by} (" . ($journey->creator->name ?? 'Unknown') . ")\n";
    echo "User role: " . ($journey->creator->role ?? 'Unknown') . "\n";
    echo "---\n";
}

// Check current active attempts
echo "\nActive Journey Attempts:\n";
$activeAttempts = App\Models\JourneyAttempt::where('status', 'in_progress')->with(['journey', 'user'])->get();
foreach ($activeAttempts as $attempt) {
    echo "User: {$attempt->user->name} | Journey: {$attempt->journey->title} | Status: {$attempt->status}\n";
}

echo "\nConditions for buttons to show:\n";
echo "1. User cannot update journey (@can('update', \$journey) should be false)\n";
echo "2. Journey is published\n"; 
echo "3. Journey has steps > 0\n";
echo "4. No active attempt for this journey OR active attempt is for different journey\n";
