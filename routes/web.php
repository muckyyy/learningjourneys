<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\JourneyStepController;
use App\Http\Controllers\JourneyCollectionController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VoiceModeController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return auth()->check() ? redirect()->route('home') : view('welcome');
});

// Authentication Routes
Auth::routes(['verify' => true]);

// Preview chat route - available in all environments
Route::get('/preview-chat', [JourneyController::class, 'previewChat'])->middleware(['auth', 'verified'])->name('preview-chat');


// Debug route to test token creation
Route::get('/debug-token', function () {
    try {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }
        
        $token = $user->createToken('Debug Test Token - ' . now())->plainTextToken;
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $user->name
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
})->middleware(['auth', 'verified']);


// API Token Management Routes (should be accessible to all authenticated users)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/user/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/user/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/user/api-tokens/{token}', [App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
});

// API Test Route
Route::get('/api-test', function () {
    return view('api-test');
});

// Convenience redirect for api-tokens
Route::get('/api-tokens', function () {
    return redirect('/user/api-tokens');
});

// Protected Routes
Route::middleware(['auth', 'verified', 'profile.required'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Journey Management on Dashboard
    Route::post('/dashboard/journey/{journey}/start', [DashboardController::class, 'startJourney'])->name('dashboard.journey.start');
    Route::post('/dashboard/journey-attempt/{attempt}/complete', [DashboardController::class, 'completeJourney'])->name('dashboard.journey.complete');
    Route::post('/dashboard/journey-attempt/{attempt}/abandon', [DashboardController::class, 'abandonJourney'])->name('dashboard.journey.abandon');
    Route::post('/dashboard/journey-attempt/{attempt}/next-step', [DashboardController::class, 'nextStep'])->name('dashboard.journey.next-step');
    
    // Journey Routes - Specific routes must come before resource routes to avoid conflicts
    Route::post('journeys/voice/start', [VoiceModeController::class, 'start'])->name('journeys.voice.start');
    Route::get('journeys/voice/start', [VoiceModeController::class, 'start'])->name('journeys.voice.start.get');
    Route::get('journeys/aivoice/{jsrid}', [VoiceModeController::class, 'aivoice'])->name('journeys.aivoice');
    Route::post('journeys/voice/submit', [VoiceModeController::class, 'submitChat'])->name('journeys.voice.submit.get');
    Route::get('journeys/testdd', [VoiceModeController::class, 'testdata'])->name('journeys.voice.testdata');
    


    Route::post('journeys/{journey}/start', [JourneyController::class, 'start'])->name('journeys.start');
    Route::get('journeys/{attempt}/chat', [JourneyController::class, 'continue'])->name('journeys.chat');
    Route::get('journeys/{attempt}/voice', [JourneyController::class, 'continue'])->name('journeys.voice');
    
    Route::resource('journeys', JourneyController::class);
    
    // Journey Steps Routes
    Route::resource('journeys.steps', JourneyStepController::class)->names([
        'index' => 'journeys.steps.index',
        'create' => 'journeys.steps.create',
        'store' => 'journeys.steps.store',
        'show' => 'journeys.steps.show',
        'edit' => 'journeys.steps.edit',
        'update' => 'journeys.steps.update',
        'destroy' => 'journeys.steps.destroy',
    ]);
    Route::post('journeys/{journey}/steps/reorder', [JourneyStepController::class, 'reorder'])->name('journeys.steps.reorder');
    
    // AI Interaction Routes for Journey Steps
    Route::get('journeys/{journey}/steps/{step}/interact', [JourneyStepController::class, 'showInteraction'])->name('journeys.steps.interact');
    Route::post('journeys/{journey}/steps/{step}/interact', [JourneyStepController::class, 'interact'])->name('journeys.steps.process_interaction');
    Route::get('journeys/{journey}/steps/{step}/responses/{stepResponse}/debug', [JourneyStepController::class, 'debugInfo'])->name('journeys.steps.debug');
    
    // Journey Collection Routes
    Route::resource('collections', JourneyCollectionController::class);
    
    // Institution Routes (for Institution and Admin roles)
    Route::middleware(['role:institution,administrator'])->group(function () {
        Route::resource('institutions', InstitutionController::class);
        Route::get('editors', [UserController::class, 'editors'])->name('editors.index');
        Route::post('editors', [UserController::class, 'storeEditor'])->name('editors.store');
    });
    
    // User Management Routes (for Admin role)
    Route::middleware(['role:administrator'])->group(function () {
        Route::resource('users', UserController::class);
    });
    
    // Reports Routes
    Route::middleware(['permission:reports.view'])->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/journeys', [ReportController::class, 'journeys'])->name('reports.journeys');
        Route::get('reports/users', [ReportController::class, 'users'])->name('reports.users');
    });
    
    // Profile Fields Management Routes (Admin only)
    Route::middleware(['permission:user.manage'])->group(function () {
        Route::resource('profile-fields', App\Http\Controllers\ProfileFieldController::class);
    });
    
    // Profile Routes
    Route::get('profile', [App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    
    // (moved API endpoints out of this group to avoid profile.required redirects)
});

// Web-authenticated API endpoints (session auth, no profile gate), used by preview-chat
Route::middleware(['auth', 'verified'])->prefix('api')->group(function () {
    Route::get('user', function (Illuminate\Http\Request $request) {
        return response()->json($request->user());
    });
    Route::get('journeys-available', [JourneyController::class, 'apiAvailable']);
    Route::get('profile-fields', [App\Http\Controllers\ProfileFieldController::class, 'apiAll']);
    // Chat endpoints under web auth for preview-chat only - use different paths
    Route::post('chat/start-web', [\App\Http\Controllers\Api\ChatController::class, 'startChat']);
    Route::post('chat/submit-web', [\App\Http\Controllers\Api\ChatController::class, 'chatSubmit']);
    // Journey management endpoints under web auth
    Route::post('start-journey', [JourneyController::class, 'apiStartJourney']);
    Route::get('journey-attempts/{attemptId}/messages', [JourneyController::class, 'apiGetAttemptMessages']);
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->middleware(['auth', 'verified', 'profile.required'])->name('home');

// WebSocket Test Routes
Route::get('/websocket-test', function() {
    return view('websocket-test');
})->name('websocket.test');

Route::get('/websocket-test-integrated', function() {
    return view('websocket-test-integrated');
})->middleware(['auth', 'verified'])->name('websocket.test.integrated');

Route::post('/test-broadcast', function() {
    broadcast(new \App\Events\AudioChunkReceived('test-session-123', [
        'chunk_number' => rand(1, 10),
        'size' => rand(1000, 5000),
        'status' => 'recording',
        'is_final' => false,
        'timestamp' => now()->toISOString(),
        'test' => true
    ]));
    
    return response()->json(['success' => true, 'message' => 'Test broadcast sent']);
})->name('test.broadcast');

// Web-based Audio Routes (session auth, no API tokens needed)
Route::middleware(['auth', 'verified'])->prefix('audio')->group(function () {
    Route::post('/start-recording', [App\Http\Controllers\AudioWebSocketController::class, 'startRecording'])->name('audio.start');
    Route::post('/process-chunk', [App\Http\Controllers\AudioWebSocketController::class, 'processAudioChunk'])->name('audio.chunk');
    Route::post('/complete', [App\Http\Controllers\AudioWebSocketController::class, 'completeRecording'])->name('audio.complete');
    Route::get('/transcription/{sessionId}', [App\Http\Controllers\AudioWebSocketController::class, 'getTranscription'])->name('audio.transcription');
    Route::post('/transcribe', [App\Http\Controllers\AudioWebSocketController::class, 'transcribeAudio'])->name('audio.transcribe');
});
