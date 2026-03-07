<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\InsufficientTokensException;
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
        } else {
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
     * Get data for admins.
     */
    private function getAdminData($user)
    {
        $totalCollections = JourneyCollection::count();
        $totalJourneys = Journey::count();
        $totalUsers = User::count();
        $totalAttempts = JourneyAttempt::count();
        
        $recentActivity = [
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'new_journeys_today' => Journey::whereDate('created_at', today())->count(),
            'attempts_today' => JourneyAttempt::whereDate('created_at', today())->count(),
        ];

        return [
            'total_collections' => $totalCollections,
            'total_journeys' => $totalJourneys,
            'total_users' => $totalUsers,
            'total_attempts' => $totalAttempts,
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
