<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$journey = App\Models\Journey::with('steps')->find(4);
if ($journey) {
    echo json_encode([
        'id' => $journey->id,
        'title' => $journey->title,
        'description' => $journey->description,
        'collection_id' => $journey->collection_id,
        'created_by' => $journey->created_by,
        'difficulty_level' => $journey->difficulty_level,
        'estimated_duration' => $journey->estimated_duration,
        'is_published' => $journey->is_published,
        'master_prompt' => $journey->master_prompt,
        'objectives' => $journey->objectives,
        'prerequisites' => $journey->prerequisites,
        'steps' => $journey->steps->map(function($step) {
            return [
                'title' => $step->title,
                'type' => $step->type,
                'content' => $step->content,
                'order' => $step->order,
                'objectives' => $step->objectives,
                'expected_duration' => $step->expected_duration,
                'is_required' => $step->is_required,
                'settings' => $step->settings
            ];
        })
    ], JSON_PRETTY_PRINT);
} else {
    echo 'Journey not found';
}
