@extends('layouts.app')

@section('content')
@php
    $defaultPromptCount = collect($defaultPrompts ?? [])->filter()->count();
@endphp

<div class="shell">

    {{-- Header --}}
    <header class="mb-4 pb-3" style="border-bottom: 1px solid rgba(15,23,42,0.08);">
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="{{ route('collections.show', $collection) }}" class="text-muted" style="font-size: 0.85rem; text-decoration: none;">
                <i class="bi bi-arrow-left"></i> Back to {{ $collection->name }}
            </a>
        </div>
        <h2 class="fw-bold mb-1" style="color: var(--lj-ink); letter-spacing: -0.02em;">Create Journey</h2>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">Craft a new experience directly inside {{ $collection->name }}.</p>
    </header>

    <form action="{{ route('journeys.store', $collection) }}" method="POST">
        @csrf

        <div class="glass-card">

            {{-- Section 1: Journey Overview --}}
            <section class="mb-4">
                <h5 class="fw-semibold mb-3" style="font-size: 1.05rem; color: var(--lj-ink);">Journey Overview</h5>

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label for="title" class="form-label fw-medium">Journey Title <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control @error('title') is-invalid @enderror"
                               id="title"
                               name="title"
                               value="{{ old('title') }}"
                               placeholder="Ex: Socratic Reasoning Sprint"
                               maxlength="255"
                               data-char-limit="255"
                               data-char-target="createTitleCounter"
                               required>
                        <div class="d-flex justify-content-end mt-1">
                            <small id="createTitleCounter" class="text-muted fw-semibold">0/255</small>
                        </div>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-4">
                        <label for="difficulty_level" class="form-label fw-medium">Difficulty <span class="text-danger">*</span></label>
                        <select class="form-select @error('difficulty_level') is-invalid @enderror"
                                id="difficulty_level"
                                name="difficulty_level"
                                required>
                            <option value="">Choose level</option>
                            <option value="beginner" {{ old('difficulty_level') == 'beginner' ? 'selected' : '' }}>Introductory</option>
                            <option value="intermediate" {{ old('difficulty_level') == 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                            <option value="advanced" {{ old('difficulty_level') == 'advanced' ? 'selected' : '' }}>Advanced</option>
                        </select>
                        @error('difficulty_level')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="short_description" class="form-label fw-medium">Short Description <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('short_description') is-invalid @enderror"
                              id="short_description"
                              name="short_description"
                              rows="2"
                              placeholder="1-2 sentence teaser that appears in previews"
                              maxlength="255"
                              data-char-limit="255"
                              data-char-target="createShortDescriptionCounter"
                              required>{{ old('short_description') }}</textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">Shows on cards, emails, and search.</small>
                        <small id="createShortDescriptionCounter" class="text-muted fw-semibold">0/255</small>
                    </div>
                    @error('short_description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-0">
                    <label for="description" class="form-label fw-medium">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control @error('description') is-invalid @enderror"
                              id="description"
                              name="description"
                              rows="4"
                              placeholder="Explain the promise of this journey in a few lines"
                              required>{{ old('description') }}</textarea>
                    <small class="text-muted d-block mt-1">Displayed on the hero card and used to train the AI introduction.</small>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            {{-- Section 2: Structure & Timing --}}
            <section class="mb-4 pt-4" style="border-top: 1px solid rgba(15,23,42,0.06);">
                <h5 class="fw-semibold mb-3" style="font-size: 1.05rem; color: var(--lj-ink);">Structure & Timing</h5>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Collection</label>
                        <div class="p-3 rounded-3" style="background: #f8fafc;">
                            <div class="fw-semibold" style="font-size: 0.95rem;">{{ $collection->name }}</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="estimated_duration" class="form-label fw-medium">Estimated Duration (minutes) <span class="text-danger">*</span></label>
                        <input type="number"
                               class="form-control @error('estimated_duration') is-invalid @enderror"
                               id="estimated_duration"
                               name="estimated_duration"
                               value="{{ old('estimated_duration') }}"
                               min="1"
                               placeholder="Ex: 25"
                               required>
                        @error('estimated_duration')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="recordtime" class="form-label fw-medium">Record Time (seconds)</label>
                        <input type="number"
                               class="form-control @error('recordtime') is-invalid @enderror"
                               id="recordtime"
                               name="recordtime"
                               value="0"
                               min="0"
                               placeholder="Optional voice max">
                        <small class="text-muted d-block mt-1">Max duration students can speak during voice prompts.</small>
                        @error('recordtime')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="token_cost" class="form-label fw-medium">Token Cost <span class="text-danger">*</span></label>
                        <div class="input-group @error('token_cost') has-validation @enderror">
                            <span class="input-group-text"><i class="bi bi-coin"></i></span>
                            <input type="number"
                                   class="form-control @error('token_cost') is-invalid @enderror"
                                   id="token_cost"
                                   name="token_cost"
                                   value="{{ old('token_cost', 0) }}"
                                   min="0"
                                   required>
                        </div>
                        <small class="text-muted d-block mt-1">0 keeps it free. Tokens expire after 12 months.</small>
                        @error('token_cost')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- Section 3: AI Prompts --}}
            <section class="mb-4 pt-4" style="border-top: 1px solid rgba(15,23,42,0.06);">
                <h5 class="fw-semibold mb-3" style="font-size: 1.05rem; color: var(--lj-ink);">AI Prompts</h5>

                <div class="mb-4">
                    <label for="master_prompt" class="form-label fw-medium">Master Prompt</label>
                    <textarea class="form-control @error('master_prompt') is-invalid @enderror"
                              id="master_prompt"
                              name="master_prompt"
                              rows="8"
                              placeholder="Guide the AI session tone, pacing, and transitions">{{ old('master_prompt', $defaultPrompts['master_prompt']) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <small class="text-muted">This fuels every learner interaction.</small>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#masterPromptHelp">
                            <i class="bi bi-question-circle"></i> Variables
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="useDefaultMasterPrompt()">Use Default</button>
                        <span class="badge bg-success">Default loaded</span>
                    </div>
                    @error('master_prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <label for="report_prompt" class="form-label fw-medium">Report Prompt</label>
                    <textarea class="form-control @error('report_prompt') is-invalid @enderror"
                              id="report_prompt"
                              name="report_prompt"
                              rows="6"
                              placeholder="Explain how the AI should summarize performance">{{ old('report_prompt', $defaultPrompts['report_prompt']) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <small class="text-muted">Generates analytics at the end of every path.</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#reportPromptHelp">
                            <i class="bi bi-journal-text"></i> Reference
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="useDefaultReportPrompt()">Use Default</button>
                        <span class="badge bg-success">Default loaded</span>
                    </div>
                    @error('report_prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            {{-- Section 4: Visibility --}}
            <section class="pt-4" style="border-top: 1px solid rgba(15,23,42,0.06);">
                <h5 class="fw-semibold mb-3" style="font-size: 1.05rem; color: var(--lj-ink);">Visibility</h5>

                <div class="d-flex align-items-start gap-3 p-3 rounded-3" style="background: rgba(var(--lj-brand-rgb), 0.04);">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_published">Publish immediately</label>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Published journeys are visible to all users. Leave it off to keep the draft private.</small>
            </section>

        </div>{{-- /glass-card --}}

        {{-- Actions --}}
        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-x-lg"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-check2-circle"></i> Create Journey
            </button>
        </div>
    </form>
</div>

<!-- Master Prompt Help Modal -->
<div class="modal fade" id="masterPromptHelp" tabindex="-1" aria-labelledby="masterPromptHelpLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="masterPromptHelpLabel">Master Prompt - Available Variables</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>You can use the following variables in your master prompt:</p>
                
                <h6>Student Information:</h6>
                <ul>
                    <li><code>{student_name}</code> - Student's full name</li>
                    <li><code>{student_email}</code> - Student's email address</li>
                    <li><code>{profile_&lt;short_name&gt;}</code> - Custom profile fields (e.g. <code>{profile_major}</code>, <code>{profile_year}</code>)</li>
                </ul>

                <h6>Journey Context:</h6>
                <ul>
                    <li><code>{journey_title}</code> - Title of the current learning journey</li>
                    <li><code>{journey_description}</code> - Description of this learning journey</li>
                    <li><code>{current_step}</code> - Current step details (title, description, content)</li>
                    <li><code>{previous_steps}</code> - List of previously completed steps</li>
                    <li><code>{next_step}</code> - Next step in the journey</li>
                </ul>

                <h6>Learning history:</h6>
                <ul>
                    <li><code>{previous_journey}</code> - Collection of student responses for last completed attempt</li>
                    <li><code>{journey_path&lt;journey_id&gt;}</code> - Summary of previous journey with given ID (e.g. <code>{journey_path1}</code>)</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Report Prompt Help Modal -->
<div class="modal fade" id="reportPromptHelp" tabindex="-1" aria-labelledby="reportPromptHelpLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="reportPromptHelpLabel">Report Prompt - Available Variables</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>The report prompt is used to generate comprehensive learning assessments. Available variables:</p>
                
                <h6>Student Assessment Variables:</h6>
                <ul>
                    <li><code>{student_firstname}</code> - Student's first name</li>
                    <li><code>{student_lastname}</code> - Student's last name</li>
                    <li><code>{journey_title}</code> - Journey title</li>
                    <li><code>{journey_description}</code> - Journey description</li>
                </ul>

                <h6>Learning Data:</h6>
                <ul>
                    <li><code>{student_responses}</code> - All student responses during the journey</li>
                    <li><code>{ai_responses}</code> - All AI tutor interactions</li>
                    <li><code>{completion_status}</code> - Whether journey was completed</li>
                    <li><code>{time_spent}</code> - Total learning time</li>
                </ul>

                <h6>Report Sections to Include:</h6>
                <ul>
                    <li>Subject/Topic Covered</li>
                    <li>Participation Level Assessment</li>
                    <li>Comprehension and Understanding</li>
                    <li>Skill Development</li>
                    <li>Communication and Expression</li>
                    <li>Areas for Improvement</li>
                    <li>Overall Performance Rating</li>
                </ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
window.__defaultPrompts = @json($defaultPrompts);

(function() {
    const limitedFields = document.querySelectorAll('[data-char-limit]');
    limitedFields.forEach((field) => {
        const limit = parseInt(field.dataset.charLimit, 10);
        const counter = document.getElementById(field.dataset.charTarget || '');
        if (!limit || !counter) {
            return;
        }

        const updateCounter = () => {
            const length = field.value ? field.value.length : 0;
            counter.textContent = `${length}/${limit}`;
            counter.classList.toggle('text-danger', length >= limit);
        };

        field.addEventListener('input', updateCounter);
        updateCounter();
    });
})();

function useDefaultMasterPrompt() {
    document.getElementById('master_prompt').value = window.__defaultPrompts.master_prompt;
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Default Loaded!';
    button.classList.add('text-success');
    setTimeout(() => { button.textContent = originalText; button.classList.remove('text-success'); }, 2000);
}

function useDefaultReportPrompt() {
    document.getElementById('report_prompt').value = window.__defaultPrompts.report_prompt;
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Default Loaded!';
    button.classList.add('text-success');
    setTimeout(() => { button.textContent = originalText; button.classList.remove('text-success'); }, 2000);
}
</script>
@endsection
