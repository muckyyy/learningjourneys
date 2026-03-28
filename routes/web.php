<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImpersonationController;
use App\Http\Controllers\JourneyCollectionController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\JourneyStepController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TokenPurchaseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VoiceModeController;
use App\Http\Controllers\Auth\SocialLoginController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\PayrexxController;
use App\Http\Controllers\Admin\CertificateController as AdminCertificateController;
use App\Http\Controllers\Admin\CertificateDesignerController;
use App\Http\Controllers\Admin\TokenBundleController as AdminTokenBundleController;
use App\Http\Controllers\Admin\TokenGrantController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\TokenManagementController;
use App\Http\Controllers\Admin\TokenReportController;
use App\Http\Controllers\Admin\LegalDocumentController as AdminLegalDocumentController;
use App\Http\Controllers\LegalConsentController;
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

// Public certificate verification
Route::get('certificates/verify/{qrCode?}', [CertificateVerificationController::class, 'show'])->name('certificates.verify');
Route::post('certificates/verify', [CertificateVerificationController::class, 'lookup'])->name('certificates.verify.lookup');

// Contact form
Route::post('/contact', [ContactController::class, 'send'])->middleware('throttle:5,1')->name('contact.send');

// Authentication Routes
Auth::routes(['verify' => true, 'register' => config('site.signup_enabled')]);

Route::middleware('guest')->prefix('auth')->name('oauth.')->group(function () {
    Route::get('{provider}/redirect', [SocialLoginController::class, 'redirect'])
        ->whereIn('provider', ['google', 'facebook', 'linkedin', 'apple', 'microsoft'])
        ->name('redirect');
    Route::get('{provider}/callback', [SocialLoginController::class, 'callback'])
        ->whereIn('provider', ['google', 'facebook', 'linkedin', 'apple', 'microsoft'])
        ->name('callback');
});

// Legal consent routes (authenticated users only, no consent middleware to avoid loops)
Route::middleware(['auth', 'verified'])->prefix('legal')->group(function () {
    Route::get('accept', [LegalConsentController::class, 'accept'])->name('legal.consent.accept');
    Route::post('accept', [LegalConsentController::class, 'store'])->name('legal.consent.store');
});

// Public legal document view (must be after /legal/accept to avoid slug catching "accept")
Route::get('legal/{slug}', [LegalConsentController::class, 'show'])->name('legal.show');

// Journeys index - accessible to guests as well
Route::get('journeys', [JourneyController::class, 'index'])->name('journeys.index');

// Preview chat route - available in all environments
Route::get('/preview-chat', [JourneyController::class, 'previewChat'])->middleware(['auth', 'verified'])->name('preview-chat');

// Start journey (AJAX from journeys index / dashboard)
Route::post('/api/start-journey', [JourneyController::class, 'apiStartJourney'])->middleware(['auth', 'verified'])->name('api.journey.start');

// API Token Management Routes (should be accessible to all authenticated users)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/user/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'index'])->name('api-tokens.index');
    Route::post('/user/api-tokens', [App\Http\Controllers\ApiTokenController::class, 'store'])->name('api-tokens.store');
    Route::delete('/user/api-tokens/{token}', [App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('api-tokens.destroy');
});

// Protected Routes
Route::middleware(['auth', 'verified', 'legal.consent', 'profile.required'])->group(function () {
    // Token management for end users
    Route::get('tokens', [TokenPurchaseController::class, 'index'])->name('tokens.index');
    Route::post('tokens/purchase', [TokenPurchaseController::class, 'store'])->name('tokens.purchase');
    Route::get('tokens/balance', [TokenPurchaseController::class, 'balance'])->name('tokens.balance');
    Route::get('tokens/purchases/{purchase}/invoice', [TokenPurchaseController::class, 'downloadInvoice'])->name('tokens.purchase.invoice');

    // Payrexx payment routes
    Route::post('payrexx/checkout', [PayrexxController::class, 'checkout'])->name('payrexx.checkout');
    Route::get('payrexx/success/{purchase}', [PayrexxController::class, 'success'])->name('payrexx.success');
    Route::get('payrexx/failed/{purchase}', [PayrexxController::class, 'failed'])->name('payrexx.failed');
    Route::get('payrexx/cancel/{purchase}', [PayrexxController::class, 'cancel'])->name('payrexx.cancel');

    Route::post('impersonate/{user}', [ImpersonationController::class, 'store'])->name('impersonation.start');
    Route::delete('impersonate', [ImpersonationController::class, 'destroy'])->name('impersonation.leave');
    
    // Journey Management on Dashboard
 
    Route::post('/dashboard/journey/{journey}/start', [DashboardController::class, 'startJourney'])->name('dashboard.journey.start');
    Route::post('/dashboard/journey-attempt/{attempt}/complete', [DashboardController::class, 'completeJourney'])->name('dashboard.journey.complete');
    Route::post('/dashboard/journey-attempt/{attempt}/abandon', [DashboardController::class, 'abandonJourney'])->name('dashboard.journey.abandon');
    Route::post('/dashboard/journey-attempt/{attempt}/next-step', [DashboardController::class, 'nextStep'])->name('dashboard.journey.next-step');
    
    // Journey Routes - Specific routes must come before resource routes to avoid conflicts
    Route::middleware('throttle:voice')->group(function () {
        Route::post('journeys/voice/start', [VoiceModeController::class, 'start'])->name('journeys.voice.start');
        Route::get('journeys/voice/start', [VoiceModeController::class, 'start'])->name('journeys.voice.start.get');
        Route::post('journeys/voice/submit', [VoiceModeController::class, 'submitChat'])->name('journeys.voice.submit.get');
        Route::post('journeys/voice/feedback', [VoiceModeController::class, 'submitFeedback'])->name('journeys.voice.feedback');
        Route::post('journeys/voice/reset', [VoiceModeController::class, 'resetAttempt'])->name('journeys.voice.reset');
        Route::post('journeys/voice/rollback', [VoiceModeController::class, 'rollbackToResponse'])->name('journeys.voice.rollback');
    });
    Route::get('journeys/aivoice/{jsrid}', [VoiceModeController::class, 'aivoice'])->name('journeys.aivoice');
    Route::get('journeys/uservoice/{jsrid}', [VoiceModeController::class, 'uservoice'])->name('journeys.uservoice');
    Route::get('journeys/prompt/{id}/{steporder?}', [VoiceModeController::class, 'getprompt'])
    ->whereNumber('id')
    ->whereNumber('steporder')
    ->name('journeys.voice.getprompt');
    


    Route::post('journeys/{journey}/start', [JourneyController::class, 'start'])->name('journeys.start');
    Route::get('journeys/{attempt}/chat', [JourneyController::class, 'continue'])->name('journeys.chat');
    Route::get('journeys/{attempt}/voice', [JourneyController::class, 'continue'])->name('journeys.voice');

    Route::get('certificates/{certificateIssue}/download', [CertificateVerificationController::class, 'download'])->name('certificates.download');
    
    Route::resource('journeys', JourneyController::class)->only(['show']);

    Route::middleware(['role:administrator'])->scopeBindings()->group(function () {
        Route::get('collections/{collection}/journeys/create', [JourneyController::class, 'create'])->name('journeys.create');
        Route::post('collections/{collection}/journeys', [JourneyController::class, 'store'])->name('journeys.store');
        Route::get('collections/{collection}/journeys/{journey}', [JourneyController::class, 'showManaged'])->name('collections.journeys.show');
        Route::get('collections/{collection}/journeys/{journey}/edit', [JourneyController::class, 'edit'])->name('journeys.edit');
        Route::match(['put', 'patch'], 'collections/{collection}/journeys/{journey}', [JourneyController::class, 'update'])->name('journeys.update');
        Route::delete('collections/{collection}/journeys/{journey}', [JourneyController::class, 'destroy'])->name('journeys.destroy');
        Route::get('collections/{collection}/journeys/{journey}/backup', [JourneyController::class, 'backup'])->name('journeys.backup');
        Route::post('collections/{collection}/journeys/restore', [JourneyController::class, 'restore'])->name('journeys.restore');

        // Journey Steps Routes nested under collections
        Route::resource('collections.journeys.steps', JourneyStepController::class)
            ->except(['index'])
            ->names([
                'create' => 'journeys.steps.create',
                'store' => 'journeys.steps.store',
                'show' => 'journeys.steps.show',
                'edit' => 'journeys.steps.edit',
                'update' => 'journeys.steps.update',
                'destroy' => 'journeys.steps.destroy',
            ]);
        Route::post('collections/{collection}/journeys/{journey}/steps/reorder', [JourneyStepController::class, 'reorder'])->name('journeys.steps.reorder');
    });

    // AI Interaction Routes for Journey Steps
    Route::get('journeys/{journey}/steps/{step}/interact', [JourneyStepController::class, 'showInteraction'])->name('journeys.steps.interact');
    Route::post('journeys/{journey}/steps/{step}/interact', [JourneyStepController::class, 'interact'])->name('journeys.steps.process_interaction');
    Route::get('journeys/{journey}/steps/{step}/responses/{stepResponse}/debug', [JourneyStepController::class, 'debugInfo'])->name('journeys.steps.debug');
    
    // Journey Collection Routes
    Route::resource('collections', JourneyCollectionController::class);
    Route::post('collections/{collection}/reorder', [JourneyCollectionController::class, 'reorderJourneys'])->name('collections.reorder');
    

    
    // User Management Routes (for Admin role)
    Route::middleware(['role:administrator'])->group(function () {
        Route::resource('users', UserController::class);

        // Admin Settings
        Route::get('admin/settings', [AdminSettingsController::class, 'index'])->name('admin.settings.index');
        Route::get('admin/settings/prompts', [AdminSettingsController::class, 'prompts'])->name('admin.settings.prompts');
        Route::post('admin/settings/prompts', [AdminSettingsController::class, 'updatePrompts'])->name('admin.settings.prompts.update');
        Route::delete('admin/settings/prompts/reset', [AdminSettingsController::class, 'resetPrompt'])->name('admin.settings.prompts.reset');
        Route::get('admin/settings/general', [AdminSettingsController::class, 'general'])->name('admin.settings.general');
        Route::post('admin/settings/general', [AdminSettingsController::class, 'updateGeneral'])->name('admin.settings.general.update');

        // Admin Legal Document Management
        Route::prefix('admin/legal')->name('admin.legal.')->group(function () {
            Route::get('/', [AdminLegalDocumentController::class, 'index'])->name('index');
            Route::get('create', [AdminLegalDocumentController::class, 'create'])->name('create');
            Route::post('/', [AdminLegalDocumentController::class, 'store'])->name('store');
            Route::get('{legal}/edit', [AdminLegalDocumentController::class, 'edit'])->name('edit');
            Route::put('{legal}', [AdminLegalDocumentController::class, 'update'])->name('update');
            Route::post('{legal}/publish', [AdminLegalDocumentController::class, 'publish'])->name('publish');
            Route::delete('{legal}', [AdminLegalDocumentController::class, 'destroy'])->name('destroy');
            Route::get('{legal}/consents', [AdminLegalDocumentController::class, 'consents'])->name('consents');
        });

        Route::prefix('admin/token-management')->name('admin.token-management.')->group(function () {
            Route::get('/', [TokenManagementController::class, 'index'])->name('index');
            Route::post('bundles', [TokenManagementController::class, 'storeBundle'])->name('bundles.store');
            Route::put('bundles/{bundle}', [TokenManagementController::class, 'updateBundle'])->name('bundles.update');
            Route::delete('bundles/{bundle}', [TokenManagementController::class, 'deleteBundle'])->name('bundles.destroy');
            Route::post('grant', [TokenManagementController::class, 'grantTokens'])->name('grant');
        });

        Route::prefix('admin/tokens')->name('admin.tokens.')->group(function () {
            Route::get('bundles', [AdminTokenBundleController::class, 'index'])->name('bundles.index');
            Route::post('bundles', [AdminTokenBundleController::class, 'store'])->name('bundles.store');
            Route::put('bundles/{bundle}', [AdminTokenBundleController::class, 'update'])->name('bundles.update');
            Route::delete('bundles/{bundle}', [AdminTokenBundleController::class, 'destroy'])->name('bundles.destroy');

            Route::get('users/{user}', [TokenGrantController::class, 'show'])->name('users.show');
            Route::post('users/{user}/grant', [TokenGrantController::class, 'store'])->name('users.grant');

            Route::get('reports/summary', [TokenReportController::class, 'index'])->name('reports.summary');
        });
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

    Route::middleware(['permission:certificate.manage'])->prefix('admin')->name('admin.')->group(function () {
        Route::get('certificates', [AdminCertificateController::class, 'index'])->name('certificates.index');
        Route::get('certificates/create', [AdminCertificateController::class, 'create'])->name('certificates.create');
        Route::post('certificates', [AdminCertificateController::class, 'store'])->name('certificates.store');
        Route::get('certificates/{certificate}/edit', [AdminCertificateController::class, 'edit'])->name('certificates.edit');
        Route::match(['put', 'patch'], 'certificates/{certificate}', [AdminCertificateController::class, 'update'])->name('certificates.update');
        Route::get('certificates/{certificate}/designer', [CertificateDesignerController::class, 'show'])->name('certificates.designer');
        Route::get('certificates/{certificate}/designer/preview', [CertificateDesignerController::class, 'preview'])->name('certificates.designer.preview');
        Route::post('certificates/{certificate}/designer/layout', [CertificateDesignerController::class, 'saveLayout'])->name('certificates.designer.save');
        Route::match(['get', 'post'], 'certificates/{certificate}/designer/assets', [CertificateDesignerController::class, 'uploadAsset'])->name('certificates.designer.asset');
    });
    
    // Profile Routes
    Route::get('profile', [App\Http\Controllers\ProfileController::class, 'show'])->name('profile.show');
    Route::get('profile/edit', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::get('profile/password', [App\Http\Controllers\ProfileController::class, 'passwordEdit'])->name('profile.password.edit');
    Route::put('profile/password', [App\Http\Controllers\ProfileController::class, 'passwordUpdate'])->name('profile.password.update');

    // Personal Report
    Route::get('my-report', [App\Http\Controllers\UserReportController::class, 'index'])->name('users.report');
    Route::get('my-report/attempts/{attempt}', [App\Http\Controllers\UserReportController::class, 'attemptReport'])->name('users.attempt-report');
    
    // (moved API endpoints out of this group to avoid profile.required redirects)
});

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->middleware(['auth', 'verified', 'profile.required'])->name('home');

// Payrexx webhook (no auth, no CSRF — verified via Payrexx reference ID)
Route::post('payrexx/webhook', [PayrexxController::class, 'webhook'])->name('payrexx.webhook');

// Web-based Audio Routes (session auth, no API tokens needed)
Route::middleware(['auth', 'verified'])->prefix('audio')->group(function () {
    Route::post('/start-recording', [App\Http\Controllers\AudioWebSocketController::class, 'startRecording'])->name('audio.start');
    Route::post('/process-chunk', [App\Http\Controllers\AudioWebSocketController::class, 'processAudioChunk'])->name('audio.chunk');
    Route::post('/complete', [App\Http\Controllers\AudioWebSocketController::class, 'completeRecording'])->name('audio.complete');
    Route::get('/transcription/{sessionId}', [App\Http\Controllers\AudioWebSocketController::class, 'getTranscription'])->name('audio.transcription');
    Route::post('/transcribe', [App\Http\Controllers\AudioWebSocketController::class, 'transcribeAudio'])->name('audio.transcribe');
});
