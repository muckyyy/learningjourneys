<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use App\Models\JourneyCollection;
use App\Models\JourneyAttempt;
use App\Models\JourneyStep;
use App\Models\User;
use App\Models\ProfileField;
use App\Services\PromptDefaults;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class JourneyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->except(['apiStartJourney', 'apiGetAttemptMessages']);
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
            $journeys = $query->with(['collection', 'creator', 'steps'])->paginate(12);
        } elseif ($user->role === 'editor') {
            // Editors see their own journeys and published ones
            $journeys = $query->where(function($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere('is_published', true);
            })->with(['collection', 'creator', 'steps'])->paginate(12);
        } else {
            // Institution and admin users see all journeys
            $journeys = Journey::with(['collection', 'creator', 'steps'])->paginate(12);
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
            'recordtime' => 'nullable|integer|min:15|max:300',
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
            'recordtime' => $request->recordtime,
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
            'recordtime' => 'nullable|integer|min:15|max:300',
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
            'recordtime' => $request->recordtime,
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
            return redirect()->route('journeys.' . $existingAttempt->type, $existingAttempt);
        }

        // Create new attempt
        $attempt = JourneyAttempt::create([
            'user_id' => $user->id,
            'journey_id' => $journey->id,
            'status' => 'in_progress',
            'started_at' => now(),
            'progress_data' => ['current_step' => 1],
        ]);

        return redirect()->route('journeys.' . $attempt->type, $attempt);
    }

    // --- Server-side formatting to match resources/js/utili.js ---
    private function hasHtmlTagsPhp(?string $str): bool
    {
        if ($str === null) return false;
        return preg_match('/<\/?[a-z][\s\S]*>/i', $str) === 1;
    }

    private function safeParseConfigPhp($config): array
    {
        if (!$config) return [];
        if (is_array($config)) return $config;

        if (is_string($config)) {
            // Try JSON first
            $decoded = json_decode($config, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

            // Normalize to JSON-ish
            $s = trim($config);
            $s = str_replace("'", '"', $s);
            $s = preg_replace('/([{,]\s*)(\d+)\s*:/', '$1"$2":', $s);
            $s = preg_replace('/,(\s*[}\]])/', '$1', $s);
            $decoded = json_decode($s, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;

            // Lenient fallback: split k:v pairs
            $s = preg_replace('/^{|}$/', '', $s);
            $obj = [];
            foreach (array_filter(array_map('trim', explode(',', $s))) as $pair) {
                [$k, $v] = array_pad(explode(':', $pair, 2), 2, null);
                if ($k !== null && $v !== null) {
                    $key = trim($k, " \t\n\r\0\x0B\"");
                    $val = trim($v, " \t\n\r\0\x0B\"");
                    $obj[$key] = $val;
                }
            }
            return $obj;
        }

        return [];
    }

    private function formatStreamingContentPhp($content, $cfgMap): string
    {
        $content = (string) $content;

        // Preserve HTML (e.g., <p>, <video>, <iframe>) as-is
        if ($this->hasHtmlTagsPhp($content)) {
            return $content;
        }

        $map = $this->safeParseConfigPhp($cfgMap);
        $paragraphs = preg_split("/\r?\n\r?\n+/", $content) ?: [$content];
        $out = [];

        foreach ($paragraphs as $i => $p) {
            $lines = preg_split("/\r?\n/", $p) ?: [$p];
            $escapedLines = array_map(function ($s) {
                return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }, $lines);
            $inner = implode('<br>', $escapedLines);

            $cls = $map[$i] ?? ($map[(string) $i] ?? '');
            $classAttr = $cls ? ' class="' . htmlspecialchars((string) $cls, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '';

            $out[] = "<p{$classAttr}>{$inner}</p>";
        }

        return implode("\n", $out);
    }
    // --- End matching formatter ---

    /**
     * Continue a journey attempt.
     */
    public function continue(JourneyAttempt $attempt)
    {
        $this->authorize('view', $attempt);

        $attempt->load(['journey.steps' => function($query) {
            $query->orderBy('order');
        }, 'stepResponses' => function($query) {
            $query->orderBy('created_at');
        }]);
        $journey = $attempt->journey;
        // Use the current_step field directly instead of progress_data
        $currentStepNumber = $attempt->current_step ?? 1;
        $currentStep = $attempt->journey->steps->where('order', $currentStepNumber)->first();

        // Check if there are any interactions (step responses) and load the last one
        $lastResponseText = null;
        $lastResponseAudio = null;
        
        if ($attempt->stepResponses->isNotEmpty()) {
            $lastResponse = $attempt->stepResponses->last();
            $lastResponseText = $lastResponse->ai_response;
            
            // Check for audio file in storage - try MP3 first, then fallback to WAV
            if ($lastResponse->id) {
                $mp3Path = "ai_audios/{$attempt->id}/{$lastResponse->id}/ai_audio.mp3";
                $wavPath = "ai_audios/{$attempt->id}/{$lastResponse->id}/ai_vaw.wav";
                
                if (Storage::disk('local')->exists($mp3Path)) {
                    $lastResponseAudio = $lastResponse->id;
                } elseif (Storage::disk('local')->exists($wavPath)) {
                    $lastResponseAudio = $lastResponse->id;
                }
            }
        }

        // Format existing messages for the view
        $existingMessages = [];
        foreach ($attempt->stepResponses as $response) {
            $step = JourneyStep::find($response->journey_step_id);
            // Add AI response
            
            // $config = json_decode($step->config,true);
            $config = is_array($step->config) ? $step->config : json_decode($step->config, true);
            // $classes = json_encode($config['paragraphclassesinit']);
            $classes = isset($config['paragraphclassesinit']) ? json_encode($config['paragraphclassesinit']) : null;

            if ($response->ai_response) {
                $existingMessages[] = [
                    // format content like utili.js using response_config
                    'content' => $this->formatStreamingContentPhp($response->ai_response, $classes ?? null),
                    'type' => 'ai',
                    'jsrid' => $response->getKey(),
                ];
            }
            if ($response->user_input) {
                $existingMessages[] = [
                    'content' => $response->user_input,
                    'type' => 'user',
                    'jsrid' => $response->getKey(),
                
                ];
            }
        }
        $responsesCount = count($existingMessages);
        $progress = number_format(($attempt->current_step - 1) / $attempt->journey->steps->count() * 100, 2);
        if ($attempt->status != 'in_progress') {
            $progress = 100;
        }
        //dd($attempt);
        //dd($lastResponseText,$lastResponseAudio);
        if ($attempt->mode == 'chat') {
            // Additional logic for chat mode can be added here
            return view('journeys.chat', compact('attempt', 'currentStep', 'existingMessages', 'responsesCount', 'progress'));
        }
        else{
            return view('journeys.voice', compact('attempt', 'currentStep', 'existingMessages', 'lastResponseText', 'lastResponseAudio','progress','journey'));
        }
        
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
            'current_step', 'previous_step', 'previous_steps', 'next_step','expected_output'
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
        $currentStep = null;
        $attemptCount = 0;

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
            
            // Get current step information
            if ($existingAttempt->current_step) {
                $currentStep = $journey->steps()->where('order', $existingAttempt->current_step)->first();
                $currentStepId = $currentStep?->id;
            }
            
            // Count attempts for this journey by this user
            $attemptCount = JourneyAttempt::where('journey_id', $journey->id)
                ->where('user_id', auth()->id())
                ->count();
            
            // Saved variables
            $attemptVariables = $existingAttempt->progress_data['variables'] ?? [];
            // Remove excluded variables if present
            foreach ($excludedVars as $ex) {
                unset($attemptVariables[$ex]);
            }

            // Existing messages
            foreach ($existingAttempt->stepResponses as $response) {
                // Get step information for this response
                $stepInfo = $journey->steps()->find($response->journey_step_id);
                $stepOrder = $stepInfo ? $stepInfo->order : 'Unknown';
                $stepTitle = $stepInfo ? $stepInfo->title : 'Step';
                $stepMaxAttempts = $stepInfo ? $stepInfo->maxattempts : 3;
                
                // Count attempts for this specific step
                $stepAttemptCount = \App\Models\JourneyStepResponse::where('journey_attempt_id', $existingAttempt->id)
                    ->where('journey_step_id', $response->journey_step_id)
                    ->where('id', '<=', $response->id) // Count up to this response
                    ->count();
                
                // Add step info before user input
                if ($response->user_input) {
                    $existingMessages[] = [
                        'type' => 'step_info',
                        'step_order' => $stepOrder,
                        'step_title' => $stepTitle,
                        'total_steps' => $journey->steps()->count(),
                        'step_attempt_count' => $stepAttemptCount,
                        'step_max_attempts' => $stepMaxAttempts,
                        'rating' => $response->step_rate, // Use step_rate column instead of ai_rating
                        'timestamp' => $response->created_at->format('Y-m-d H:i:s')
                    ];
                    
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
                        'timestamp' => $response->created_at->format('Y-m-d H:i:s'),
                        'rating' => $response->step_rate,
                        'action' => $response->step_action
                    ];
                    
                    // Add feedback info if we have rating and action
                    if ($response->step_rate && $response->step_action) {
                        $existingMessages[] = [
                            'type' => 'feedback_info',
                            'rating' => $response->step_rate,
                            'action' => $response->step_action,
                            'step_attempt_count' => $stepAttemptCount,
                            'step_max_attempts' => $stepMaxAttempts,
                            'timestamp' => $response->created_at->format('Y-m-d H:i:s')
                        ];
                    }
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
            'currentStepId',
            'currentStep',
            'attemptCount'
        ));
    }

    /**
     * API: Start a new journey attempt
     */
    public function apiStartJourney(Request $request)
    {
        $request->validate([
            'journey_id' => 'required|integer|exists:journeys,id',
            'user_id' => 'required|integer|exists:users,id',
            'type' => 'required|in:chat,voice'
        ]);

        $user = $request->user();
        $journeyId = $request->journey_id;
        $userId = $request->user_id;
        $type = $request->type;

        // Check if the requesting user has permission to start journey for this user
        if ($user->id !== $userId && !in_array($user->role, ['admin', 'administrator', 'institution'])) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to start journey for this user'
            ], 403);
        }

        try {
            $journey = Journey::findOrFail($journeyId);
            $targetUser = User::findOrFail($userId);

            // Check if user already has an active attempt for this journey
            $existingAttempt = JourneyAttempt::where('user_id', $userId)
                ->where('journey_id', $journeyId)
                ->where('status', 'in_progress')
                ->first();

            if ($existingAttempt) {
                return response()->json([
                    'success' => false,
                    'error' => 'User already has an active attempt for this journey',
                    'existing_attempt_id' => $existingAttempt->id
                ], 422);
            }

            // Get user's profile field values for populating progress_data
            $profileFields = ProfileField::where('is_active', true)->get();
            $progressData = [];
            
            foreach ($profileFields as $field) {
                $value = $field->getValueForUser($userId);
                if ($value !== null) {
                    $progressData[$field->short_name] = $value;
                }
            }

            // Add basic metadata to progress_data
            $progressData['current_step'] = 1;
            $progressData['started_at'] = now()->toISOString();
            $progressData['mode'] = $type; // Store the mode in progress_data for reference
            $progressData['student_firstname'] = $targetUser->firstname ?? $targetUser->name;
            $progressData['student_lastname'] = $targetUser->lastname ?? '';
            $progressData['student_email'] = $targetUser->email;
            $progressData['institution_name'] = $targetUser->institution->name ?? '';
            $progressData['journey_title'] = $journey->title;
            $progressData['journey_description'] = $journey->description;

            // Create new journey attempt
            $attempt = JourneyAttempt::create([
                'user_id' => $userId,
                'journey_id' => $journeyId,
                'journey_type' => 'attempt', // Use 'attempt' for regular journeys
                'mode' => $type, // Use the mode field for 'chat' or 'voice'
                'status' => 'in_progress',
                'started_at' => now(),
                'current_step' => 1,
                'progress_data' => $progressData
            ]);

            return response()->json([
                'success' => true,
                'journey_attempt_id' => $attempt->id,
                'redirect_url' => route('journeys.' . $attempt->type, $attempt)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to start journey: ' . $e->getMessage(), [
                'journey_id' => $journeyId,
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to start journey: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get existing messages for a journey attempt
     */
    public function apiGetAttemptMessages(Request $request, $attemptId)
    {
        try {
            $user = $request->user();
            
            // Find the attempt and verify ownership
            $attempt = JourneyAttempt::where('id', $attemptId)
                ->where('user_id', $user->id)
                ->with(['stepResponses' => function($query) {
                    $query->orderBy('created_at');
                }])
                ->first();

            if (!$attempt) {
                return response()->json([
                    'success' => false,
                    'error' => 'Journey attempt not found or access denied'
                ], 404);
            }

            // Format messages from step responses
            $messages = [];
            
            foreach ($attempt->stepResponses as $response) {
                // Add user message
                if ($response->user_input) {
                    $messages[] = [
                        'content' => $response->user_input,
                        'type' => 'user',
                        'timestamp' => $response->created_at
                    ];
                }
                
                // Add AI response
                if ($response->ai_response) {
                    $messages[] = [
                        'content' => $response->ai_response,
                        'type' => 'ai',
                        'timestamp' => $response->created_at
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'messages' => $messages
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get attempt messages: ' . $e->getMessage(), [
                'attempt_id' => $attemptId,
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to load messages'
            ], 500);
        }
    }
}
