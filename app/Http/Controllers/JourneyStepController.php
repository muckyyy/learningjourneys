<?php

namespace App\Http\Controllers;

use App\Models\Journey;
use App\Models\JourneyStep;
use App\Models\JourneyStepResponse;
use App\Models\JourneyAttempt;
use App\Services\AIInteractionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JourneyStepController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of steps for a journey.
     */
    public function index(Journey $journey)
    {
        $this->authorize('view', $journey);
        
        $steps = $journey->steps()->orderBy('order')->get();
        
        return view('journey-steps.index', compact('journey', 'steps'));
    }

    /**
     * Show the form for creating a new step.
     */
    public function create(Journey $journey)
    {
        $this->authorize('update', $journey);
        
        $nextOrder = $journey->steps()->max('order') + 1;
        
        return view('journey-steps.create', compact('journey', 'nextOrder'));
    }

    /**
     * Store a newly created step in storage.
     */
    public function store(Request $request, Journey $journey)
    {
        $this->authorize('update', $journey);

        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:text,video,quiz,interactive,assignment',
            'content' => 'required|string',
            'order' => 'required|integer|min:1',
            'ratepass' => 'required|integer|min:1|max:5',
            'maxattempts' => 'required|integer|min:1|max:10',
            'is_required' => 'boolean',
            'time_limit' => 'nullable|integer|min:1',
        ]);

        // Adjust order of existing steps if necessary
        if ($request->order <= $journey->steps()->max('order')) {
            $journey->steps()
                ->where('order', '>=', $request->order)
                ->increment('order');
        }

        $step = $journey->steps()->create([
            'title' => $request->title,
            'type' => $request->type,
            'content' => $request->content,
            'order' => $request->order,
            'ratepass' => $request->ratepass,
            'maxattempts' => $request->maxattempts,
            'is_required' => $request->boolean('is_required', true),
            'time_limit' => $request->time_limit,
            'configuration' => json_encode($request->configuration ?? []),
        ]);

        return redirect()->route('journeys.steps.index', $journey)
            ->with('success', 'Journey step created successfully!');
    }

    /**
     * Display the specified step.
     */
    public function show(Journey $journey, JourneyStep $step)
    {
        $this->authorize('view', $journey);
        
        if ($step->journey_id !== $journey->id) {
            abort(404);
        }

        return view('journey-steps.show', compact('journey', 'step'));
    }

    /**
     * Show the form for editing the specified step.
     */
    public function edit(Journey $journey, JourneyStep $step)
    {
        $this->authorize('update', $journey);
        
        if ($step->journey_id !== $journey->id) {
            abort(404);
        }

        return view('journey-steps.edit', compact('journey', 'step'));
    }

    /**
     * Update the specified step in storage.
     */
    public function update(Request $request, Journey $journey, JourneyStep $step)
    {
        $this->authorize('update', $journey);
        
        if ($step->journey_id !== $journey->id) {
            abort(404);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'type' => 'required|in:text,video,quiz,interactive,assignment',
            'content' => 'required|string',
            'order' => 'required|integer|min:1',
            'ratepass' => 'required|integer|min:1|max:5',
            'maxattempts' => 'required|integer|min:1|max:10',
            'is_required' => 'boolean',
            'time_limit' => 'nullable|integer|min:1',
        ]);

        // Handle order changes
        if ($request->order != $step->order) {
            $oldOrder = $step->order;
            $newOrder = $request->order;
            
            if ($newOrder > $oldOrder) {
                // Moving down: decrement orders between old and new
                $journey->steps()
                    ->where('order', '>', $oldOrder)
                    ->where('order', '<=', $newOrder)
                    ->where('id', '!=', $step->id)
                    ->decrement('order');
            } else {
                // Moving up: increment orders between new and old
                $journey->steps()
                    ->where('order', '>=', $newOrder)
                    ->where('order', '<', $oldOrder)
                    ->where('id', '!=', $step->id)
                    ->increment('order');
            }
        }

        $step->update([
            'title' => $request->title,
            'type' => $request->type,
            'content' => $request->content,
            'order' => $request->order,
            'ratepass' => $request->ratepass,
            'maxattempts' => $request->maxattempts,
            'is_required' => $request->boolean('is_required'),
            'time_limit' => $request->time_limit,
            'configuration' => json_encode($request->configuration ?? []),
        ]);

        return redirect()->route('journeys.steps.index', $journey)
            ->with('success', 'Journey step updated successfully!');
    }

    /**
     * Remove the specified step from storage.
     */
    public function destroy(Journey $journey, JourneyStep $step)
    {
        $this->authorize('update', $journey);
        
        if ($step->journey_id !== $journey->id) {
            abort(404);
        }

        $deletedOrder = $step->order;
        
        $step->delete();

        // Adjust order of remaining steps
        $journey->steps()
            ->where('order', '>', $deletedOrder)
            ->decrement('order');

        return redirect()->route('journeys.steps.index', $journey)
            ->with('success', 'Journey step deleted successfully!');
    }

    /**
     * Reorder steps via AJAX.
     */
    public function reorder(Request $request, Journey $journey)
    {
        $this->authorize('update', $journey);

        $request->validate([
            'steps' => 'required|array',
            'steps.*.id' => 'required|exists:journey_steps,id',
            'steps.*.order' => 'required|integer|min:1',
        ]);

        foreach ($request->steps as $stepData) {
            $step = JourneyStep::findOrFail($stepData['id']);
            if ($step->journey_id === $journey->id) {
                $step->update(['order' => $stepData['order']]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * Handle AI interaction for a journey step
     */
    public function interact(Request $request, Journey $journey, JourneyStep $step)
    {
        $this->authorize('view', $journey);

        $request->validate([
            'user_input' => 'required|string|max:10000',
            'interaction_type' => 'in:text,voice,rating',
            'attempt_id' => 'nullable|exists:journey_attempts,id',
        ]);

        $user = Auth::user();
        
        // Get journey attempt - use provided attempt_id or find/create one
        if ($request->attempt_id) {
            $attempt = JourneyAttempt::findOrFail($request->attempt_id);
            $this->authorize('update', $attempt);
        } else {
            // Get or create journey attempt for non-dashboard interactions
            $attempt = JourneyAttempt::firstOrCreate([
                'journey_id' => $journey->id,
                'user_id' => $user->id,
                'status' => 'in_progress',
            ], [
                'started_at' => now(),
                'current_step' => 1,
            ]);
        }

        // Create step response record
        $stepResponse = JourneyStepResponse::create([
            'journey_attempt_id' => $attempt->id,
            'journey_step_id' => $step->id,
            'user_input' => $request->user_input,
            'submitted_at' => now(),
        ]);

        // Process AI interaction
        $aiService = app(AIInteractionService::class);
        $result = $aiService->processStepInteraction(
            $step,
            $user,
            $request->user_input,
            $stepResponse,
            [
                'interaction_type' => $request->interaction_type ?? 'text',
                'ai_model' => 'gpt-3.5-turbo',
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]
        );

        // Update step response with AI response
        if ($result['success']) {
            $stepResponse->update([
                'ai_response' => $result['ai_response'],
                'interaction_type' => $request->interaction_type ?? 'text',
            ]);
            
            // Update journey attempt progress if using dashboard
            if ($request->attempt_id && $attempt->current_step == $step->order) {
                $totalSteps = $attempt->journey->steps()->count();
                if ($step->order < $totalSteps) {
                    $attempt->update(['current_step' => $step->order + 1]);
                }
            }
        }

        // Handle different response types based on where the request came from
        if ($request->expectsJson()) {
            // API/AJAX request
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'ai_response' => $result['ai_response'],
                    'tokens_used' => $result['tokens_used'],
                    'processing_time' => $result['processing_time'],
                    'debug_id' => $result['debug_id'],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => $result['error'],
                    'debug_id' => $result['debug_id'],
                ], 500);
            }
        } else {
            // Form submission - redirect back
            if ($result['success']) {
                return redirect()->route('dashboard')->with('success', 
                    'Your response has been processed successfully!');
            } else {
                return redirect()->route('dashboard')->with('error', 
                    'There was an error processing your response. Please try again.');
            }
        }
    }

    /**
     * Get debug information for a step response
     */
    public function debugInfo(Journey $journey, JourneyStep $step, JourneyStepResponse $stepResponse)
    {
        $this->authorize('view', $journey);

        $aiService = app(AIInteractionService::class);
        $debugInfo = $aiService->getDebugInfo($stepResponse);

        return response()->json($debugInfo);
    }

    /**
     * Show step interaction interface
     */
    public function showInteraction(Journey $journey, JourneyStep $step)
    {
        $this->authorize('view', $journey);

        $user = Auth::user();
        
        // Get user's latest attempt for this journey
        $attempt = JourneyAttempt::where('journey_id', $journey->id)
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        $previousResponses = [];
        if ($attempt) {
            $previousResponses = JourneyStepResponse::where('journey_attempt_id', $attempt->id)
                ->where('journey_step_id', $step->id)
                ->with('debugEntries')
                ->latest()
                ->get();
        }

        return view('journey-steps.interact', compact(
            'journey', 
            'step', 
            'attempt', 
            'previousResponses'
        ));
    }
}
