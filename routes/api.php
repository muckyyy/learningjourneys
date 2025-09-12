
<?php


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\ProfileFieldController;
use App\Http\Controllers\AudioWebSocketController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Journey and Profile API Routes
|--------------------------------------------------------------------------
|
| Routes for fetching journeys and profile fields
|
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/journeys-available', [JourneyController::class, 'apiAvailable'])->name('api.journeys.available');
    Route::get('/profile-fields', [ProfileFieldController::class, 'apiAll'])->name('api.profile-fields.all');
    Route::post('/start-journey', [JourneyController::class, 'apiStartJourney'])->name('api.journey.start');
    Route::get('/journey-attempts/{attemptId}/messages', [JourneyController::class, 'apiGetAttemptMessages'])->name('api.journey.attempt.messages');
});

/*
|--------------------------------------------------------------------------
| Chat API Routes
|--------------------------------------------------------------------------
|
| Routes for AI chat functionality in learning journeys
|
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/chat/start', [ChatController::class, 'startChat'])->name('api.chat.start');
    Route::post('/chat/submit', [ChatController::class, 'chatSubmit'])->name('api.chat.submit');
    Route::get('/chat/prompt/{journeyAttemptId}/{type}', [ChatController::class, 'getCurrentPrompt'])->name('api.chat.prompt');
});

/*
|--------------------------------------------------------------------------
| Audio API Routes
|--------------------------------------------------------------------------
|
| Routes for audio recording and transcription
|
*/
Route::middleware('auth:sanctum')->group(function () {
    // Simple audio transcription - upload complete file
    Route::post('/audio/transcribe', [AudioWebSocketController::class, 'transcribeAudio'])->name('api.audio.transcribe');
    
    // Legacy chunked audio routes (kept for backward compatibility)
    Route::post('/audio/start-recording', [AudioWebSocketController::class, 'startRecording'])->name('api.audio.start');
    Route::post('/audio/process-chunk', [AudioWebSocketController::class, 'processAudioChunk'])->name('api.audio.chunk');
    Route::post('/audio/complete', [AudioWebSocketController::class, 'completeRecording'])->name('api.audio.complete');
    Route::get('/audio/transcription/{sessionId}', [AudioWebSocketController::class, 'getTranscription'])->name('api.audio.transcription');
});
