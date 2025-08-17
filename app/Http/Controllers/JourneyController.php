<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use App\Models\JourneyCollection;
use App\Models\JourneyAttempt;
use App\Models\JourneyStep;
use App\Services\PromptDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class JourneyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of journeys.
     */
    public function index()
    {
        $user = Auth::user();
        $query = Journey::with(['collection', 'creator'])
            ->where('is_published', true);

        // Get active journey attempt for the user
        $activeAttempt = null;
        if ($user->role === 'regular') {
            $activeAttempt = JourneyAttempt::where('user_id', $user->id)
                ->where('status', 'in_progress')
                ->with('journey')
                ->first();
        }

        // Filter based on user role
        if ($user->role === 'regular') {
            // Regular users see all published journeys
            $journeys = $query->paginate(12);
        } elseif ($user->role === 'editor') {
            // Editors see their own journeys and published ones
            $journeys = $query->where(function($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('is_published', true);
            })->paginate(12);
        } else {
            // Institution and admin users see all journeys
            $journeys = Journey::with(['collection', 'creator'])->paginate(12);
        }

        return view('journeys.index', compact('journeys', 'activeAttempt'));
    }

    /**
     * Show the form for creating a new journey.
     */
    public function create()
    {
        $this->authorize('create', Journey::class);
        
        $user = Auth::user();
        $collections = $user->role === 'administrator' 
            ? JourneyCollection::all() 
            : JourneyCollection::where('editor_id', $user->id)->get();

        // Provide default prompts for new journey creation
        $defaultPrompts = [
            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
        ];

        return view('journeys.create', compact('collections', 'defaultPrompts'));
    }

    /**
     * Store a newly created journey in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Journey::class);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'master_prompt' => 'nullable|string',
            'report_prompt' => 'nullable|string',
            'journey_collection_id' => 'required|exists:journey_collections,id',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'estimated_duration' => 'required|integer|min:1',
            'tags' => 'nullable|string',
            'is_published' => 'boolean',
        ]);

        $journey = Journey::create([
            'title' => $request->title,
            'description' => $request->description,
            'master_prompt' => $request->master_prompt ?: PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => $request->report_prompt ?: PromptDefaults::getDefaultReportPrompt(),
            'journey_collection_id' => $request->journey_collection_id,
            'difficulty_level' => $request->difficulty_level,
            'estimated_duration' => $request->estimated_duration,
            'is_published' => $request->boolean('is_published'),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('journeys.show', $journey)
            ->with('success', 'Journey created successfully!');
    }

    /**
     * Display the specified journey.
     */
    public function show(Journey $journey)
    {
        $journey->load(['collection', 'creator', 'steps' => function($query) {
            $query->orderBy('order');
        }]);

        $userAttempt = null;
        $activeAttempt = null;
        $previewAttempts = null;
        
        if (Auth::check()) {
            $userAttempt = JourneyAttempt::where('user_id', Auth::id())
                ->where('journey_id', $journey->id)
                ->whereIn('status', ['in_progress', 'completed'])
                ->first();
                
            // Get any active attempt (different journey)
            if (Auth::user()->role === 'regular') {
                $activeAttempt = JourneyAttempt::where('user_id', Auth::id())
                    ->where('status', 'in_progress')
                    ->where('journey_id', '!=', $journey->id)
                    ->with('journey')
                    ->first();
            }
            
            // Get preview attempts for privileged users (editors, institution, admin)
            if (in_array(Auth::user()->role, ['editor', 'institution', 'admin', 'administrator'])) {
                $previewAttempts = JourneyAttempt::where('journey_id', $journey->id)
                    ->where('journey_type', 'preview')
                    ->with(['user', 'stepResponses' => function($query) {
                        $query->orderBy('created_at', 'desc')->limit(1);
                    }])
                    ->orderBy('created_at', 'desc')
                    ->limit(10)
                    ->get();
            }
        }

        return view('journeys.show', compact('journey', 'userAttempt', 'activeAttempt', 'previewAttempts'));
    }

    /**
     * Show the form for editing the specified journey.
     */
    public function edit(Journey $journey)
    {
        $this->authorize('update', $journey);

        $user = Auth::user();
        $collections = $user->role === 'administrator' 
            ? JourneyCollection::all() 
            : JourneyCollection::where('editor_id', $user->id)->get();

        // Provide default prompts for reference
        $defaultPrompts = [
            'master_prompt' => PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => PromptDefaults::getDefaultReportPrompt(),
        ];

        return view('journeys.edit', compact('journey', 'collections', 'defaultPrompts'));
    }

    /**
     * Update the specified journey in storage.
     */
    public function update(Request $request, Journey $journey)
    {
        $this->authorize('update', $journey);

        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'master_prompt' => 'nullable|string',
            'report_prompt' => 'nullable|string',
            'journey_collection_id' => 'required|exists:journey_collections,id',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'estimated_duration' => 'required|integer|min:1',
            'tags' => 'nullable|string',
            'is_published' => 'boolean',
        ]);

        $journey->update([
            'title' => $request->title,
            'description' => $request->description,
            'master_prompt' => $request->master_prompt ?: PromptDefaults::getDefaultMasterPrompt(),
            'report_prompt' => $request->report_prompt ?: PromptDefaults::getDefaultReportPrompt(),
            'journey_collection_id' => $request->journey_collection_id,
            'difficulty_level' => $request->difficulty_level,
            'estimated_duration' => $request->estimated_duration,
            'is_published' => $request->boolean('is_published'),
        ]);

        return redirect()->route('journeys.show', $journey)
            ->with('success', 'Journey updated successfully!');
    }

    /**
     * Remove the specified journey from storage.
     */
    public function destroy(Journey $journey)
    {
        $this->authorize('delete', $journey);

        $journey->delete();

        return redirect()->route('journeys.index')
            ->with('success', 'Journey deleted successfully!');
    }

    /**
     * Start a journey attempt.
     */
    public function start(Journey $journey)
    {
        $user = Auth::user();
        
        // Check if user already has an active attempt
        $existingAttempt = JourneyAttempt::where('user_id', $user->id)
            ->where('journey_id', $journey->id)
            ->whereIn('status', ['in_progress', 'completed'])
            ->first();

        if ($existingAttempt) {
            return redirect()->route('journeys.continue', $existingAttempt);
        }

        // Create new attempt
        $attempt = JourneyAttempt::create([
            'user_id' => $user->id,
            'journey_id' => $journey->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'progress_data' => json_encode(['current_step' => 1]),
        ]);

        return redirect()->route('journeys.continue', $attempt);
    }

    /**
     * Continue a journey attempt.
     */
    public function continue(JourneyAttempt $attempt)
    {
        $this->authorize('view', $attempt);

        $attempt->load(['journey.steps' => function($query) {
            $query->orderBy('order');
        }]);

        $progressData = json_decode($attempt->progress_data, true);
        $currentStepNumber = $progressData['current_step'] ?? 1;
        $currentStep = $attempt->journey->steps->where('order', $currentStepNumber)->first();

        if (!$currentStep) {
            // Journey completed
            $attempt->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            
            return view('journeys.completed', compact('attempt'));
        }

        return view('journeys.step', compact('attempt', 'currentStep'));
    }

    /**
     * API: Return journeys available to the authenticated user (for preview-chat selector)
     */
    public function apiAvailable(Request $request)
    {
        $user = $request->user();
        $query = Journey::query();
        if ($user->role === 'regular') {
            $query->where('is_published', true);
        } elseif ($user->role === 'editor') {
            $query->where(function($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('is_published', true);
            });
        }
        // Admins/institutions see all
        $journeys = $query->orderBy('title')->get(['id', 'title', 'description']);
        return $journeys;
    }

    /**
     * Display the preview chat interface
     */
    public function previewChat(Request $request)
    {
        $journeyId = $request->get('journey_id');
        $attemptId = $request->get('attempt_id');

        // Variables that are derived by API and should not appear in preview-chat
        $excludedVars = [
            'journey_description', 'student_email', 'institution_name', 'journey_title',
            'current_step', 'previous_step', 'previous_steps', 'next_step'
        ];

        $journey = null;
        $existingAttempt = null;
        $existingMessages = [];
        $availableJourneys = collect();
        $profileFields = collect();
        $userProfileDefaults = [];
        $attemptVariables = [];
        $masterVariables = [];
        $currentStepId = null;

        // Build available journeys (reuse apiAvailable logic)
        $user = $request->user();
        $query = Journey::query();
        if ($user->role === 'regular') {
            $query->where('is_published', true);
        } elseif ($user->role === 'editor') {
            $query->where(function($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('is_published', true);
            });
        }
        $availableJourneys = $query->orderBy('title')->get(['id', 'title', 'description', 'master_prompt']);

        // Load active profile fields and user defaults
        $profileFields = \App\Models\ProfileField::where('is_active', true)->orderBy('sort_order')->get()
            ->reject(function($field) use ($excludedVars) {
                return in_array($field->short_name, $excludedVars, true);
            })->values();
        foreach ($profileFields as $field) {
            $userProfileDefaults[$field->short_name] = $field->getValueForUser($user->id);
        }

        // Load journey if provided
        if ($journeyId) {
            $journey = Journey::findOrFail($journeyId);
        }

        // Load existing attempt if provided
        if ($attemptId) {
            $existingAttempt = JourneyAttempt::with(['journey', 'stepResponses' => function($query) {
                $query->orderBy('created_at', 'asc');
            }])->findOrFail($attemptId);

            // Override journey with the one from the attempt
            $journey = $existingAttempt->journey;
            // Saved variables
            $attemptVariables = $existingAttempt->progress_data['variables'] ?? [];
            // Remove excluded variables if present
            foreach ($excludedVars as $ex) {
                unset($attemptVariables[$ex]);
            }

            // Existing messages
            foreach ($existingAttempt->stepResponses as $response) {
                if ($response->user_input) {
                    $existingMessages[] = [
                        'type' => 'user',
                        'content' => $response->user_input,
                        'timestamp' => $response->created_at->format('Y-m-d H:i:s')
                    ];
                }
                if ($response->ai_response) {
                    $existingMessages[] = [
                        'type' => 'ai',
                        'content' => $response->ai_response,
                        'timestamp' => $response->created_at->format('Y-m-d H:i:s')
                    ];
                }
                $currentStepId = $response->journey_step_id; // last seen step
            }
        }

        // Extract variables from master prompt of the selected journey
        if ($journey && $journey->master_prompt) {
            if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $journey->master_prompt, $matches)) {
                $masterVariables = array_values(array_filter(array_unique($matches[1]), function($v) use ($excludedVars) {
                    return !in_array($v, $excludedVars, true);
                }));
            }
        }

        // Ensure user defaults do not include excluded vars (in case of any legacy data)
        foreach ($excludedVars as $ex) {
            unset($userProfileDefaults[$ex]);
        }

        return view('preview-chat', compact(
            'journey',
            'existingAttempt',
            'existingMessages',
            'availableJourneys',
            'profileFields',
            'userProfileDefaults',
            'attemptVariables',
            'masterVariables',
            'currentStepId'
        ));
    }
}
