<?php

// Test route for audio processing
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudioWebSocketController;
use App\Models\AudioRecording;

Route::get('/test-audio-processing/{id}', function($id) {
    $recording = AudioRecording::find($id);
    if (!$recording) {
        return response()->json(['error' => 'Recording not found'], 404);
    }
    
    try {
        $controller = new AudioWebSocketController();
        $method = new ReflectionMethod($controller, 'processCompleteAudio');
        $method->setAccessible(true);
        $method->invoke($controller, $recording);
        
        return response()->json([
            'success' => true,
            'recording' => $recording->fresh()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});
