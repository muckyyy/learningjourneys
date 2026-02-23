@extends('layouts.app')

@section('content')
@php
    $stepsCount = $journey->steps->count();
    $journeyTitle = \Illuminate\Support\Str::limit($journey->title, 38);
    $configValue = $step->config;
    if (is_string($configValue)) {
        $decodedConfig = json_decode($configValue, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedConfig)) {
            $configValue = $decodedConfig;
        }
    }
    if (is_array($configValue)) {
        $configValue = json_encode($configValue, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
@endphp

<div class="shell" style="max-width: 960px;">
    <header class="mb-4 pb-3" style="border-bottom: 1px solid rgba(15,23,42,0.08);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <a href="{{ route('collections.journeys.show', [$collection, $journey]) }}" class="text-muted" style="font-size: 0.85rem; text-decoration: none;">
                <i class="bi bi-arrow-left"></i> Back to journey
            </a>
            <a href="{{ route('collections.journeys.show', [$collection, $journey]) }}" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="bi bi-eye"></i> View journey
            </a>
        </div>
        <h1 class="h3 fw-bold mb-1" style="color: var(--lj-ink); letter-spacing: -0.02em;">Edit step: {{ $step->title }}</h1>
        <p class="text-muted mb-3" style="max-width: 640px;">Update the step details while keeping the journey flow aligned to {{ $journeyTitle }}.</p>
        <div class="d-flex flex-wrap gap-3">
            <div class="px-3 py-2 rounded-4" style="background: #f8fafc;">
                <small class="d-block text-uppercase text-muted" style="font-size: 0.7rem;">Total steps</small>
                <span class="fw-semibold" style="font-size: 1.05rem;">{{ number_format($stepsCount) }}</span>
            </div>
            <div class="px-3 py-2 rounded-4" style="background: #f8fafc;">
                <small class="d-block text-uppercase text-muted" style="font-size: 0.7rem;">Current order</small>
                <span class="fw-semibold" style="font-size: 1.05rem;">#{{ $step->order }}</span>
            </div>
            <div class="px-3 py-2 rounded-4" style="background: #f8fafc;">
                <small class="d-block text-uppercase text-muted" style="font-size: 0.7rem;">Journey mode</small>
                <span class="fw-semibold" style="font-size: 1.05rem;">{{ ucfirst($journey->difficulty_level ?? 'custom') }}</span>
            </div>
        </div>
    </header>

    <form action="{{ route('journeys.steps.update', [$collection, $journey, $step]) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="glass-card">

            <section class="mb-4">
                <p class="form-section-title mb-1 text-uppercase text-muted">Step identity</p>
                <h5 class="fw-semibold mb-3" style="color: var(--lj-ink);">Name, order, and type</h5>
                <div class="form-grid two-col">
                    <div>
                        <label for="title" class="form-label">Step Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title', $step->title) }}" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="order" class="form-label">Step Order <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('order') is-invalid @enderror" id="order" name="order" value="{{ old('order', $step->order) }}" min="1" required>
                        @error('order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="type" class="form-label">Step Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="text" {{ old('type', $step->type) == 'text' ? 'selected' : '' }}>Text Content</option>
                            <option value="video" {{ old('type', $step->type) == 'video' ? 'selected' : '' }}>Video</option>
                            <option value="quiz" {{ old('type', $step->type) == 'quiz' ? 'selected' : '' }}>Quiz</option>
                            <option value="interactive" {{ old('type', $step->type) == 'interactive' ? 'selected' : '' }}>Interactive</option>
                            <option value="assignment" {{ old('type', $step->type) == 'assignment' ? 'selected' : '' }}>Assignment</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                        <input type="number" class="form-control @error('time_limit') is-invalid @enderror" id="time_limit" name="time_limit" value="{{ old('time_limit', $step->time_limit) }}" min="1" placeholder="Optional">
                        <div class="helper-text mt-2">Cap reflection or quiz moments to keep mobile pacing.</div>
                        @error('time_limit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="mb-4 pt-3" style="border-top: 1px solid rgba(15,23,42,0.08);">
                <p class="form-section-title mb-1 text-uppercase text-muted">Performance gates</p>
                <h5 class="fw-semibold mb-3" style="color: var(--lj-ink);">Pass, retry, and follow-up controls</h5>
                <div class="form-grid two-col">
                    <div>
                        <label for="ratepass" class="form-label">Pass Rating <span class="text-danger">*</span></label>
                        <select class="form-select @error('ratepass') is-invalid @enderror" id="ratepass" name="ratepass" required>
                            <option value="">Select Rating</option>
                            <option value="1" {{ old('ratepass', $step->ratepass) == '1' ? 'selected' : '' }}>1 - Basic</option>
                            <option value="2" {{ old('ratepass', $step->ratepass) == '2' ? 'selected' : '' }}>2 - Fair</option>
                            <option value="3" {{ old('ratepass', $step->ratepass) == '3' ? 'selected' : '' }}>3 - Good</option>
                            <option value="4" {{ old('ratepass', $step->ratepass) == '4' ? 'selected' : '' }}>4 - Very Good</option>
                            <option value="5" {{ old('ratepass', $step->ratepass) == '5' ? 'selected' : '' }}>5 - Excellent</option>
                        </select>
                        <div class="helper-text mt-2">Minimum AI score before advancing.</div>
                        @error('ratepass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="maxattempts" class="form-label">Max Attempts <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('maxattempts') is-invalid @enderror" id="maxattempts" name="maxattempts" value="{{ old('maxattempts', $step->maxattempts) }}" min="1" max="10" required>
                        <div class="helper-text mt-2">Give room for coaching, but keep the story moving.</div>
                        @error('maxattempts')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="maxfollowups" class="form-label">Max Follow-ups <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('maxfollowups') is-invalid @enderror" id="maxfollowups" name="maxfollowups" value="{{ old('maxfollowups', $step->maxfollowups) }}" min="0" max="10" required>
                        <div class="helper-text mt-2">Give room for coaching, but keep the story moving.</div>
                        @error('maxfollowups')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 p-3 mt-3 rounded-4" style="background: #f8fafc;">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1" {{ old('is_required', $step->is_required) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_required">Required Step</label>
                    </div>
                    <p class="mb-0 helper-text">Required steps must be cleared before the learner can advance.</p>
                </div>
            </section>

            <section class="pt-3" style="border-top: 1px solid rgba(15,23,42,0.08);">
                <p class="form-section-title mb-1 text-uppercase text-muted">Content & prompts</p>
                <h5 class="fw-semibold mb-3" style="color: var(--lj-ink);">Brief the AI and define grading</h5>
                <div class="mb-4">
                    <label for="content" class="form-label">Step Content <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content" rows="8" placeholder="Prompt or HTML for this step" required>{{ old('content', $step->content) }}</textarea>
                    <div class="helper-text mt-2" id="content-help"></div>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label for="expected_output" class="form-label">Expected Output</label>
                    <textarea class="form-control @error('expected_output') is-invalid @enderror" id="expected_output" name="expected_output" rows="4" placeholder="Describe what the AI should deliver or how the learner should respond">{{ old('expected_output', $step->expected_output) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 helper-text">
                        <span>Use defaults for instant tone alignment.</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="loadDefaultExpectedOutput(this)"><i class="bi bi-magic"></i> Match Step Type</button>
                    </div>
                    @error('expected_output')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label for="rating_prompt" class="form-label">Rating Prompt</label>
                    <textarea class="form-control @error('rating_prompt') is-invalid @enderror" id="rating_prompt" name="rating_prompt" rows="4" placeholder="Explain how the AI should grade this attempt">{{ old('rating_prompt', $step->rating_prompt) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 helper-text">
                        <span>Controls AI scoring voice.</span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" onclick="loadDefaultRatingPrompt(this)"><i class="bi bi-arrow-repeat"></i> Use Default</button>
                    </div>
                    @error('rating_prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="configuration" class="form-label">Configuration (JSON)</label>
                    <textarea class="form-control font-monospace @error('configuration') is-invalid @enderror" id="configuration" name="configuration" rows="6" placeholder='{"key": "value"}'>{{ old('configuration', $configValue ?? '') }}</textarea>
                    <div class="form-text">Use JSON for advanced settings like video URLs or quiz rules.</div>
                    @error('configuration')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="mt-3">
                        <div class="text-muted mb-2" style="font-size: 0.9rem;">AI response class reference (with icons):</div>
                        <div class="d-grid gap-2">
                            <div class="ainode-reflection">ainode-reflection</div>
                            <div class="ainode-teaching">ainode-teaching</div>
                            <div class="ainode-task">ainode-task</div>
                            <div class="ainode-retry-soft">ainode-retry-soft</div>
                            <div class="ainode-retry-urgent">ainode-retry-urgent</div>
                            <div class="ainode-followup">ainode-followup</div>
                        </div>
                    </div>
                </div>
            </section>

            <div class="d-flex justify-content-end gap-2 pt-3 mt-4" style="border-top: 1px solid rgba(15,23,42,0.06);">
                <a href="{{ route('collections.journeys.show', [$collection, $journey]) }}" class="btn btn-outline-secondary rounded-pill"><i class="bi bi-x-lg"></i> Cancel</a>
                <button type="submit" class="btn btn-primary rounded-pill"><i class="bi bi-check2-circle"></i> Save Changes</button>
            </div>
        </div>
    </form>
</div>

<script>
    window.promptDefaults = {
        expectedOutputs: {
            text: @json(\App\Services\PromptDefaults::getDefaultTextStepOutput()),
            video: @json(\App\Services\PromptDefaults::getDefaultVideoStepOutput())
        },
        ratingPrompt: @json(\App\Services\PromptDefaults::getDefaultRatePrompt())
    };
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeSelect = document.getElementById('type');
    const contentTextarea = document.getElementById('content');
    const contentHelp = document.getElementById('content-help');
    const expectedOutputTextarea = document.getElementById('expected_output');
    const ratingPromptTextarea = document.getElementById('rating_prompt');

    const helpTexts = {
        text: 'Enter the AI prompt or HTML copy that powers this moment.',
        video: 'Describe the video context and any analysis tasks for the learner.',
        quiz: 'Provide JSON or markdown instructions for quiz logic.',
        interactive: 'Outline the interactive flow or embed instructions.',
        assignment: 'Clarify deliverables, criteria, and submission guidelines.'
    };

    const contentExamples = {
        text: 'Prompt for ai in this particular step',
        video: '<p>Watch the clip below, then note two insights:</p>\n<ul>\n  <li>Insight prompt</li>\n  <li>Follow-up question</li>\n</ul>',
        quiz: '{\n  "questions": [{\n    "question": "What is 2 + 2?",\n    "type": "multiple_choice",\n    "options": ["3", "4", "5", "6"],\n    "correct": 1\n  }]\n}',
        interactive: '<div class="activity">\n  <h4>Interactive Activity</h4>\n  <p>Complete the following task:</p>\n</div>',
        assignment: '<h4>Assignment</h4>\n<p><strong>Objective:</strong> Summarize today\'s lesson in 200 words.</p>'
    };

    function updateTypeSpecificContent() {
        const selectedType = typeSelect.value;
        if (!selectedType) {
            contentHelp.innerHTML = '';
            return;
        }

        const help = helpTexts[selectedType] || 'Describe the experience for this step.';
        contentHelp.innerHTML = `<i class="bi bi-info-circle"></i> ${help}`;

        if (!contentTextarea.value.trim()) {
            contentTextarea.value = contentExamples[selectedType] || '';
        }

        if (!expectedOutputTextarea.value.trim()) {
            const defaults = window.promptDefaults?.expectedOutputs || {};
            if ((selectedType === 'text' || selectedType === 'video') && defaults[selectedType]) {
                expectedOutputTextarea.value = defaults[selectedType];
            }
        }
    }

    typeSelect.addEventListener('change', updateTypeSpecificContent);
    setTimeout(updateTypeSpecificContent, 100);

    window.loadDefaultRatingPrompt = function(button) {
        if (!window.promptDefaults?.ratingPrompt) {
            return;
        }
        ratingPromptTextarea.value = window.promptDefaults.ratingPrompt;
        pulseButton(button);
    };

    window.loadDefaultExpectedOutput = function(button) {
        const selectedType = typeSelect.value;
        const defaults = window.promptDefaults?.expectedOutputs || {};
        if (!expectedOutputTextarea.value.trim() && defaults[selectedType]) {
            expectedOutputTextarea.value = defaults[selectedType];
            pulseButton(button);
        }
    };

    function pulseButton(button) {
        if (!button) {
            return;
        }
        button.disabled = true;
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check2"></i> Loaded';
        setTimeout(() => {
            button.disabled = false;
            button.innerHTML = originalText;
        }, 1600);
    }
});
</script>
@endsection
