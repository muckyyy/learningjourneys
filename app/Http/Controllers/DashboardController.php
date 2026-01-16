<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\InsufficientTokensException;
use App\Models\Institution;
use App\Models\Journey;
use App\Models\JourneyAttempt;
use App\Models\JourneyCollection;
use App\Models\User;
use App\Services\TokenLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(private TokenLedger $tokenLedger)
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     */
    public function index()
    {
        $user = Auth::user();
        
        // Get active journey attempt (only one allowed at a time)
        $activeAttempt = JourneyAttempt::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->with(['journey', 'journey.steps'])
            ->first();

        $tokenSnapshot = null;

        if ($user->role === 'regular') {
            $data = $this->getRegularUserData($user, $activeAttempt);
            $tokenSnapshot = $this->tokenLedger->balance($user);
        } elseif ($user->role === 'editor') {
            $data = $this->getEditorData($user);
        } else { // institution or administrator
            $data = $this->getAdminData($user);
        }

        return view('dashboard', compact('user', 'data', 'activeAttempt', 'tokenSnapshot'));
    }

    /**
     * Start a new journey attempt.
     */
    public function startJourney(Request $request, Journey $journey)
    {
        $user = Auth::user();

        // Check if user already has an active journey
        $activeAttempt = JourneyAttempt::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        if ($activeAttempt) {
            return redirect()->route('dashboard')->with('error', 
                'You already have an active journey. Please complete or abandon it before starting a new one.');
        }

        try {
            DB::transaction(function () use ($user, $journey) {
                $attempt = JourneyAttempt::create([
                    'user_id' => $user->id,
                    'journey_id' => $journey->id,
                    'journey_type' => 'attempt',
                    'mode' => 'chat',
                    'status' => 'in_progress',
                    'started_at' => now(),
                    'current_step' => 1,
                    'progress_data' => ['current_step' => 1],
                ]);

                $this->tokenLedger->spendForJourney($user, $journey, $attempt);
            });
        } catch (InsufficientTokensException $e) {
            return redirect()->route('tokens.index')->with('error', 'You need more tokens to start this journey.');
        }

        return redirect()->route('dashboard')->with('success', 
            'Journey started successfully! You can now interact with the steps below.');
    }

    /**
     * Complete the current journey attempt.
     */
    public function completeJourney(JourneyAttempt $attempt)
    {
        $this->authorize('update', $attempt);

        $attempt->update([
            'status' => 'completed',
            'completed_at' => now(),
            'score' => $this->calculateJourneyScore($attempt),
        ]);

        return redirect()->route('dashboard')->with('success', 
            'Congratulations! You have completed the journey.');
    }

    /**
     * Abandon the current journey attempt.
     */
    public function abandonJourney(JourneyAttempt $attempt)
    {
        $this->authorize('update', $attempt);

        $attempt->update([
            'status' => 'abandoned',
            'completed_at' => now(),
        ]);

        return redirect()->route('home')->with('info', 
            'Journey has been abandoned. You can start a new journey now.');
    }

    /**
     * Advance to the next step in the journey.
     */
    public function nextStep(JourneyAttempt $attempt)
    {
        $this->authorize('update', $attempt);

        $totalSteps = $attempt->journey->steps()->count();
        $currentStep = $attempt->current_step;

        if ($currentStep >= $totalSteps) {
            return response()->json([
                'success' => false,
                'message' => 'You are already on the last step of this journey.'
            ]);
        }

        $attempt->update([
            'current_step' => $currentStep + 1
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Advanced to next step successfully.'
        ]);
    }

    /**
     * Get data for regular users.
     */
    private function getRegularUserData($user, $activeAttempt)
    {
        $completedAttempts = JourneyAttempt::where('user_id', $user->id)
            ->completed()
            ->count();

        $inProgressAttempts = $activeAttempt ? 1 : 0;

        $availableJourneys = Journey::where('is_published', true)->count();

        $recentAttempts = JourneyAttempt::where('user_id', $user->id)
            ->with('journey')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return [
            'available_journeys' => $availableJourneys,
            'completed_journeys' => $completedAttempts,
            'in_progress_journeys' => $inProgressAttempts,
            'recent_attempts' => $recentAttempts,
        ];
    }

    /**
     * Get data for editors.
     */
    private function getEditorData($user)
    {
        $managedCollections = JourneyCollection::query()
            ->whereHas('editors', fn ($q) => $q->where('users.id', $user->id))
            ->count();

        $authoredJourneys = Journey::query()
            ->where('created_by', $user->id);

        $totalJourneys = $authoredJourneys->count();

        $publishedJourneys = (clone $authoredJourneys)
            ->where('is_published', true)
            ->count();

        $totalAttempts = JourneyAttempt::whereHas('journey', function ($query) use ($user) {
            $query->where('created_by', $user->id);
        })->count();

        return [
            'managed_collections' => $managedCollections,
            'total_journeys' => $totalJourneys,
            'published_journeys' => $publishedJourneys,
            'total_attempts' => $totalAttempts,
            // Add default values for any other keys that might be expected
            'total_collections' => $managedCollections,
            'total_editors' => 1, // Just themselves
            'total_users' => User::count(), // Can see total users
            'total_institutions' => Institution::count(),
        ];
    }

    /**
     * Get data for admins.
     */
    private function getAdminData($user)
    {
        if ($user->role === UserRole::INSTITUTION) {
            // Institution admin sees only their institution's data
            $totalCollections = JourneyCollection::where('institution_id', $user->institution_id)->count();
            $totalEditors = User::whereHas('memberships', function ($query) use ($user) {
                    $query->where('institution_id', $user->institution_id)
                        ->where('role', UserRole::EDITOR)
                        ->where('is_active', true);
                })->count();
            $totalJourneys = Journey::whereHas('collection', function($query) use ($user) {
                $query->where('institution_id', $user->institution_id);
            })->count();
            $totalUsers = User::where('institution_id', $user->institution_id)->count();
            $totalAttempts = JourneyAttempt::whereHas('journey.collection', function($query) use ($user) {
                $query->where('institution_id', $user->institution_id);
            })->count();
            $totalInstitutions = 1; // Just their own
            
            // Recent activity for institution
            $recentActivity = [
                'new_users_today' => User::where('institution_id', $user->institution_id)
                    ->whereDate('created_at', today())
                    ->count(),
                'new_journeys_today' => Journey::whereHas('collection', function($query) use ($user) {
                    $query->where('institution_id', $user->institution_id);
                })->whereDate('created_at', today())->count(),
                'attempts_today' => JourneyAttempt::whereHas('journey.collection', function($query) use ($user) {
                    $query->where('institution_id', $user->institution_id);
                })->whereDate('created_at', today())->count(),
            ];
        } else {
            // Super admin sees all data
            $totalCollections = JourneyCollection::count();
            $totalEditors = User::withRole(UserRole::EDITOR)->count();
            $totalJourneys = Journey::count();
            $totalUsers = User::count();
            $totalAttempts = JourneyAttempt::count();
            $totalInstitutions = Institution::count();
            
            // Recent activity for all
            $recentActivity = [
                'new_users_today' => User::whereDate('created_at', today())->count(),
                'new_journeys_today' => Journey::whereDate('created_at', today())->count(),
                'attempts_today' => JourneyAttempt::whereDate('created_at', today())->count(),
            ];
        }

        return [
            'total_collections' => $totalCollections,
            'total_editors' => $totalEditors,
            'total_journeys' => $totalJourneys,
            'total_users' => $totalUsers,
            'total_attempts' => $totalAttempts,
            'total_institutions' => $totalInstitutions,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Calculate the score for a journey attempt.
     */
    private function calculateJourneyScore(JourneyAttempt $attempt)
    {
        // Simple scoring: percentage of steps completed
        $totalSteps = $attempt->journey->steps()->count();
        $completedSteps = $attempt->stepResponses()->count();
        
        if ($totalSteps === 0) {
            return 0;
        }
        
        return ($completedSteps / $totalSteps) * 100;
    }
}
