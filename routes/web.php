<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\JourneyStepController;
use App\Http\Controllers\JourneyCollectionController;
use App\Http\Controllers\InstitutionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReportController;
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
Auth::routes();

// Protected Routes
Route::middleware(['auth', 'profile.required'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    
    // Journey Management on Dashboard
    Route::post('/dashboard/journey/{journey}/start', [DashboardController::class, 'startJourney'])->name('dashboard.journey.start');
    Route::post('/dashboard/journey-attempt/{attempt}/complete', [DashboardController::class, 'completeJourney'])->name('dashboard.journey.complete');
    Route::post('/dashboard/journey-attempt/{attempt}/abandon', [DashboardController::class, 'abandonJourney'])->name('dashboard.journey.abandon');
    Route::post('/dashboard/journey-attempt/{attempt}/next-step', [DashboardController::class, 'nextStep'])->name('dashboard.journey.next-step');
    
    // Journey Routes
    Route::resource('journeys', JourneyController::class);
    Route::post('journeys/{journey}/start', [JourneyController::class, 'start'])->name('journeys.start');
    Route::get('journeys/{attempt}/continue', [JourneyController::class, 'continue'])->name('journeys.continue');
    
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
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->middleware(['auth', 'profile.required'])->name('home');
