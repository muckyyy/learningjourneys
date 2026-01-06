@extends('layouts.app')

@push('styles')
<style>
.step-builder-shell {
    width: min(1200px, 100%);
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
}
.step-builder-hero {
    background: linear-gradient(135deg, #0f172a 10%, #2563eb 50%, #38bdf8);
    border-radius: 36px;
    padding: clamp(2rem, 5vw, 4rem);
    color: #fff;
    box-shadow: 0 35px 70px rgba(15, 23, 42, 0.35);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}
.step-builder-hero::after {
    content: "";
    position: absolute;
    inset: 15% auto auto 65%;
    width: 380px;
    height: 380px;
    background: radial-gradient(circle, rgba(255,255,255,0.28) 0%, transparent 60%);
    transform: translate(45%, -45%);
    pointer-events: none;
}
.hero-copy {
    position: relative;
    z-index: 1;
    flex: 1 1 360px;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    letter-spacing: 0.18em;
    text-transform: uppercase;
    font-size: 0.8rem;
}
.step-builder-hero h1 {
    font-size: clamp(2rem, 4.5vw, 3.1rem);
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
}
.step-builder-hero p {
    max-width: 540px;
    color: rgba(255,255,255,0.82);
    font-size: 1.05rem;
}
.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin-top: 1.5rem;
}
.hero-stat {
    background: rgba(15, 23, 42, 0.35);
    border-radius: 22px;
    padding: 0.85rem 1.4rem;
    min-width: 150px;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.18);
}
.hero-stat span {
    font-size: 0.75rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.75);
}
.hero-stat strong {
    display: block;
    font-size: 1.35rem;
    color: #fff;
}
.hero-actions {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    align-items: flex-start;
    position: relative;
    z-index: 1;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.75rem;
    font-weight: 600;
    box-shadow: 0 15px 35px rgba(0,0,0,0.2);
}
.step-builder-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
    gap: clamp(1rem, 2vw, 1.75rem);
    margin-top: 2.5rem;
}
.step-builder-form {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.form-card {
    background: rgba(255,255,255,0.96);
    border-radius: 28px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    padding: clamp(1.5rem, 3vw, 2rem);
    box-shadow: 0 25px 45px rgba(15, 23, 42, 0.08);
}
.form-card-header {
    display: flex;
    gap: 0.85rem;
    align-items: center;
    margin-bottom: 1.25rem;
}
.section-badge {
    width: 42px;
    height: 42px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #eef2ff;
    color: #4338ca;
    font-weight: 700;
}
.form-card h5 {
    margin: 0;
    font-weight: 700;
}
.form-card p {
    margin-bottom: 0;
    color: #6b7280;
}
.form-grid {
    display: grid;
    gap: 1.15rem;
}
.form-grid.two-col {
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
}
.form-control,
.form-select {
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.1);
    min-height: 54px;
}
.form-control:focus,
.form-select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.15);
}
textarea.form-control {
    min-height: 160px;
    border-radius: 22px;
}
.helper-text {
    font-size: 0.9rem;
    color: #6b7280;
}
.publish-toggle {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 18px;
}
.form-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 0.85rem;
    align-items: center;
}
.form-actions .btn {
    border-radius: 16px;
    padding: 0.85rem 1.5rem;
    font-weight: 600;
}
.builder-aside {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.info-card {
    background: rgba(15,23,42,0.92);
    color: #e2e8f0;
    border-radius: 28px;
    padding: 1.75rem;
    box-shadow: 0 25px 45px rgba(15, 23, 42, 0.35);
}
.info-card.light-card {
    background: #fff;
    color: #1f2937;
    border: 1px solid rgba(15,23,42,0.08);
    box-shadow: 0 15px 30px rgba(15, 23, 42, 0.08);
}
.info-card h6 {
    font-weight: 700;
    margin-bottom: 0.75rem;
}
.info-card ul {
    padding-left: 1.1rem;
    margin-bottom: 0;
}
.info-card li {
    margin-bottom: 0.35rem;
}
.quick-links {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}
.quick-links a {
    font-size: 0.85rem;
    border-radius: 999px;
    padding: 0.35rem 0.9rem;
}
.sticky-aside {
    position: sticky;
    top: 90px;
}
@media (max-width: 991.98px) {
    .step-builder-grid {
        grid-template-columns: 1fr;
    }
    .sticky-aside {
        position: static;
    }
    .hero-actions {
        width: 100%;
    }
    .hero-actions .btn {
        width: 100%;
        justify-content: center;
    }
}
@media (max-width: 575.98px) {
    .step-builder-shell {
        padding: 1.25rem;
    }
    .step-builder-hero {
        border-radius: 24px;
    }
}
</style>
@endpush

@section('content')
@php
    $stepsCount = $journey->steps->count();
    $journeyTitle = \Illuminate\Support\Str::limit($journey->title, 38);
@endphp

<div class="step-builder-shell">
    <div class="step-builder-hero">
        <div class="hero-copy">
            <div class="hero-pill"><i class="bi bi-shuffle"></i> Step Builder</div>
            <h1 class="fw-bold">Layer a New Segment</h1>
            <p>Create a premium touchpoint that keeps learners engaged, aligned to {{ $journeyTitle }}.</p>
            <div class="hero-meta">
                <div class="hero-stat">
                    <span>Total Steps</span>
                    <strong>{{ number_format($stepsCount) }}</strong>
                </div>
                <div class="hero-stat">
                    <span>Next Slot</span>
                    <strong>#{{ $nextOrder }}</strong>
                </div>
                <div class="hero-stat">
                    <span>Journey Mode</span>
                    <strong>{{ ucfirst($journey->difficulty_level ?? 'custom') }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            <a href="{{ route('journeys.steps.index', $journey) }}" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Back to Steps</a>
            <a href="{{ route('journeys.show', $journey) }}" class="btn btn-light text-dark"><i class="bi bi-eye"></i> View Journey</a>
        </div>
    </div>

    <div class="step-builder-grid">
        <form action="{{ route('journeys.steps.store', $journey) }}" method="POST" class="step-builder-form">
            @csrf

            <section class="form-card">
                <div class="form-card-header">
                    <div class="section-badge">01</div>
                    <div>
                        <h5>Step Identity</h5>
                        <p>Name the interaction, set order, and pick the experience type.</p>
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div>
                        <label for="title" class="form-label">Step Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" value="{{ old('title') }}" placeholder="Ex: Warm-up Reflection" required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="order" class="form-label">Step Order <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('order') is-invalid @enderror" id="order" name="order" value="{{ old('order', $nextOrder) }}" min="1" required>
                        @error('order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="type" class="form-label">Step Type <span class="text-danger">*</span></label>
                        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                            <option value="">Select Type</option>
                            <option value="text" {{ old('type', 'text') == 'text' ? 'selected' : '' }}>Text Content</option>
                            <option value="video" {{ old('type') == 'video' ? 'selected' : '' }}>Video</option>
                            <option value="quiz" {{ old('type') == 'quiz' ? 'selected' : '' }}>Quiz</option>
                            <option value="interactive" {{ old('type') == 'interactive' ? 'selected' : '' }}>Interactive</option>
                            <option value="assignment" {{ old('type') == 'assignment' ? 'selected' : '' }}>Assignment</option>
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                        <input type="number" class="form-control @error('time_limit') is-invalid @enderror" id="time_limit" name="time_limit" value="{{ old('time_limit') }}" min="1" placeholder="Optional">
                        <div class="helper-text mt-2">Cap reflection or quiz moments to keep mobile pacing.</div>
                        @error('time_limit')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="form-card">
                <div class="form-card-header">
                    <div class="section-badge">02</div>
                    <div>
                        <h5>Performance Gates</h5>
                        <p>Define success thresholds and retry behavior.</p>
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div>
                        <label for="ratepass" class="form-label">Pass Rating <span class="text-danger">*</span></label>
                        <select class="form-select @error('ratepass') is-invalid @enderror" id="ratepass" name="ratepass" required>
                            <option value="">Select Rating</option>
                            <option value="1" {{ old('ratepass') == '1' ? 'selected' : '' }}>1 - Basic</option>
                            <option value="2" {{ old('ratepass') == '2' ? 'selected' : '' }}>2 - Fair</option>
                            <option value="3" {{ old('ratepass') == '3' ? 'selected' : '' }}>3 - Good</option>
                            <option value="4" {{ old('ratepass') == '4' ? 'selected' : '' }}>4 - Very Good</option>
                            <option value="5" {{ old('ratepass') == '5' ? 'selected' : '' }}>5 - Excellent</option>
                        </select>
                        <div class="helper-text mt-2">Minimum AI score before advancing.</div>
                        @error('ratepass')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="maxattempts" class="form-label">Max Attempts <span class="text-danger">*</span></label>
                        <input type="number" class="form-control @error('maxattempts') is-invalid @enderror" id="maxattempts" name="maxattempts" value="{{ old('maxattempts', 3) }}" min="1" max="10" required>
                        <div class="helper-text mt-2">Give room for coaching, but keep the story moving.</div>
                        @error('maxattempts')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="publish-toggle mt-3">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1" {{ old('is_required', true) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_required">Required Step</label>
                    </div>
                    <p class="mb-0 helper-text">Required steps must be cleared before the learner can advance.</p>
                </div>
            </section>

            <section class="form-card">
                <div class="form-card-header">
                    <div class="section-badge">03</div>
                    <div>
                        <h5>Content & Prompts</h5>
                        <p>Craft the AI brief, expected output, and rating logic.</p>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="content" class="form-label">Step Content <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content" rows="8" placeholder="Prompt or HTML for this step" required>{{ old('content') }}</textarea>
                    <div class="helper-text mt-2" id="content-help"></div>
                    @error('content')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-4">
                    <label for="expected_output" class="form-label">Expected Output</label>
                    <textarea class="form-control @error('expected_output') is-invalid @enderror" id="expected_output" name="expected_output" rows="4" placeholder="Describe what the AI should deliver or how the learner should respond">{{ old('expected_output') }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 helper-text">
                        <span>Use defaults for instant tone alignment.</span>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" onclick="loadDefaultExpectedOutput(this)"><i class="bi bi-magic"></i> Match Step Type</button>
                    </div>
                    @error('expected_output')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="rating_prompt" class="form-label">Rating Prompt</label>
                    <textarea class="form-control @error('rating_prompt') is-invalid @enderror" id="rating_prompt" name="rating_prompt" rows="4" placeholder="Explain how the AI should grade this attempt">{{ old('rating_prompt', \App\Services\PromptDefaults::getDefaultRatePrompt()) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 helper-text">
                        <span>Controls AI scoring voice.</span>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" onclick="loadDefaultRatingPrompt(this)"><i class="bi bi-arrow-repeat"></i> Use Default</button>
                    </div>
                    @error('rating_prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            <div class="form-actions">
                <a href="{{ route('journeys.steps.index', $journey) }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i> Cancel</a>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> Create Step</button>
            </div>
        </form>

        <aside class="builder-aside sticky-aside">
            <div class="info-card">
                <h6 class="text-white">Journey Snapshot</h6>
                <p class="mb-2 text-white-75">{{ $journey->title }}</p>
                <ul class="mb-3">
                    <li>{{ $stepsCount }} steps live today</li>
                    <li>Next slot is <strong>#{{ $nextOrder }}</strong></li>
                    <li>Difficulty: {{ ucfirst($journey->difficulty_level ?? 'custom') }}</li>
                </ul>
                <div class="quick-links">
                    <a href="{{ route('journeys.steps.index', $journey) }}" class="btn btn-sm btn-outline-light"><i class="bi bi-list"></i> Manage</a>
                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-sm btn-outline-light"><i class="bi bi-eye"></i> Preview</a>
                </div>
            </div>
            <div class="info-card light-card">
                <h6>Prompt Shortcuts</h6>
                <p class="mb-2">Mix dynamic variables to keep tone personal:</p>
                <ul>
                    <li><code>{student_name}</code>, <code>{journey_title}</code></li>
                    <li><code>{current_step}</code>, <code>{next_step}</code></li>
                    <li><code>{previous_steps}</code>, <code>{institution_name}</code></li>
                </ul>
                <div class="helper-text mt-3">Pair with the default prompt buttons for fast resets.</div>
            </div>
            <div class="info-card light-card">
                <h6>Builder Tips</h6>
                <ul>
                    <li>Lead every step with a clear learner action.</li>
                    <li>Keep quizzes JSON-clean to avoid parsing issues.</li>
                    <li>Preview after saving to test AI pacing.</li>
                </ul>
            </div>
        </aside>
    </div>
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

        const defaults = window.promptDefaults?.expectedOutputs || {};
        if ((selectedType === 'text' || selectedType === 'video') && defaults[selectedType]) {
            expectedOutputTextarea.value = defaults[selectedType];
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
        if (defaults[selectedType]) {
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
