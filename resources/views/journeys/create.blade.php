@extends('layouts.app')

@push('styles')
<style>
.journey-create-shell {
    width: min(1200px, 100%);
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
}
.journey-create-hero {
    background: linear-gradient(135deg, #0f172a 10%, #2563eb 45%, #38bdf8);
    border-radius: 36px;
    padding: clamp(2rem, 5vw, 4rem);
    color: #fff;
    box-shadow: 0 35px 70px rgba(15, 23, 42, 0.35);
    overflow: hidden;
    position: relative;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}
.journey-create-hero::after {
    content: "";
    position: absolute;
    inset: 20% auto auto 65%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(255,255,255,0.35) 0%, transparent 60%);
    pointer-events: none;
    transform: translate(50%, -50%);
}
.hero-copy {
    position: relative;
    z-index: 1;
    flex: 1 1 360px;
}
.journey-create-hero .hero-pill {
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
.journey-create-hero h1 {
    font-size: clamp(2rem, 4.8vw, 3.2rem);
    margin-top: 0.65rem;
    margin-bottom: 0.5rem;
}
.journey-create-hero p {
    max-width: 540px;
    color: rgba(255, 255, 255, 0.82);
    font-size: 1.05rem;
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
.create-hero-meta {
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
.journey-create-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
    gap: clamp(1rem, 2vw, 1.75rem);
    margin-top: 2.5rem;
}
.journey-create-form {
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
.glass-input {
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: inset 0 1px 2px rgba(15,23,42,0.05);
    min-height: 54px;
}
.glass-input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 0.25rem rgba(37, 99, 235, 0.15);
}
textarea.glass-input {
    min-height: 160px;
    resize: vertical;
}
.helper-text {
    font-size: 0.9rem;
    color: #6b7280;
}
.token-input-group {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    overflow: hidden;
}
.token-input-group .input-group-text {
    border: none;
    background: transparent;
    color: #475569;
}
.token-input-group .form-control {
    border: none;
    border-left: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 0;
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
    justify-content: flex-end;
    gap: 0.85rem;
}
.form-actions .btn {
    border-radius: 16px;
    padding: 0.85rem 1.75rem;
    font-weight: 600;
}
.create-aside {
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
.sticky-aside {
    position: sticky;
    top: 90px;
}
@media (max-width: 991.98px) {
    .journey-create-grid {
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
    }
}
@media (max-width: 575.98px) {
    .journey-create-shell {
        padding: 1.25rem;
    }
    .journey-create-hero {
        border-radius: 24px;
    }
}
</style>
@endpush

@section('content')
@php
    $collectionCount = $collections->count();
    $defaultPromptCount = collect($defaultPrompts ?? [])->filter()->count();
@endphp

<div class="journey-create-shell">
    <div class="journey-create-hero">
        <div class="hero-copy">
            <div class="hero-pill"><i class="bi bi-wand"></i> Builder</div>
            <h1 class="fw-bold">Design a Premium Journey</h1>
            <p>Craft an immersive pathway with the same polish as our library pages. Keep structure, tone, and timing intentional so students feel guided at every tap.</p>
            <div class="create-hero-meta">
                <div class="hero-stat">
                    <span>Collections Ready</span>
                    <strong>{{ number_format($collectionCount) }}</strong>
                </div>
                <div class="hero-stat">
                    <span>Default Prompts</span>
                    <strong>{{ number_format($defaultPromptCount) }}</strong>
                </div>
                <div class="hero-stat">
                    <span>Publish Control</span>
                    <strong>Instant</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            <a href="{{ route('journeys.index') }}" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Journeys Home</a>
            <button type="button" class="btn btn-light text-dark" data-bs-toggle="modal" data-bs-target="#masterPromptHelp">
                <i class="bi bi-magic"></i> Prompt Glossary
            </button>
        </div>
    </div>

    <div class="journey-create-grid">
        <form action="{{ route('journeys.store') }}" method="POST" class="journey-create-form">
            @csrf

            <section class="form-card">
                <div class="form-card-header">
                    <div class="section-badge">01</div>
                    <div>
                        <h5>Journey Overview</h5>
                        <p>Name the experience and outline what learners should expect.</p>
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div>
                        <label for="title" class="form-label">Journey Title <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control form-control-lg glass-input @error('title') is-invalid @enderror"
                               id="title"
                               name="title"
                               value="{{ old('title') }}"
                               placeholder="Ex: Socratic Reasoning Sprint"
                               required>
                        @error('title')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="difficulty_level" class="form-label">Difficulty Level <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg glass-input @error('difficulty_level') is-invalid @enderror"
                                id="difficulty_level"
                                name="difficulty_level"
                                required>
                            <option value="">Choose level</option>
                            <option value="beginner" {{ old('difficulty_level') == 'beginner' ? 'selected' : '' }}>Beginner</option>
                            <option value="intermediate" {{ old('difficulty_level') == 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                            <option value="advanced" {{ old('difficulty_level') == 'advanced' ? 'selected' : '' }}>Advanced</option>
                        </select>
                        @error('difficulty_level')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="mt-3">
                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control glass-input @error('description') is-invalid @enderror"
                              id="description"
                              name="description"
                              rows="4"
                              placeholder="Explain the promise of this journey in a few lines"
                              required>{{ old('description') }}</textarea>
                    <div class="helper-text mt-2">This shows on the hero card and trains the AI introduction.</div>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            <section class="form-card">
                <div class="form-card-header">
                    <div class="section-badge">02</div>
                    <div>
                        <h5>Structure & Timing</h5>
                        <p>Connect to the right collection and define pacing details.</p>
                    </div>
                </div>
                <div class="form-grid two-col">
                    <div>
                        <label for="journey_collection_id" class="form-label">Collection <span class="text-danger">*</span></label>
                        <select class="form-select form-select-lg glass-input @error('journey_collection_id') is-invalid @enderror"
                                id="journey_collection_id"
                                name="journey_collection_id"
                                required>
                            <option value="">Select collection</option>
                            @foreach($collections as $collection)
                                <option value="{{ $collection->id }}" {{ old('journey_collection_id') == $collection->id ? 'selected' : '' }}>
                                    {{ $collection->name }} ({{ $collection->institution->name }})
                                </option>
                            @endforeach
                        </select>
                        @error('journey_collection_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="estimated_duration" class="form-label">Estimated Duration (minutes) <span class="text-danger">*</span></label>
                        <input type="number"
                               class="form-control form-control-lg glass-input @error('estimated_duration') is-invalid @enderror"
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
                    <div>
                        <label for="recordtime" class="form-label">Record Time (seconds)</label>
                        <input type="number"
                               class="form-control form-control-lg glass-input @error('recordtime') is-invalid @enderror"
                               id="recordtime"
                               name="recordtime"
                               value="{{ old('recordtime') }}"
                               min="0"
                               placeholder="Optional voice max">
                        <div class="helper-text mt-2">Set how long students can speak during voice prompts.</div>
                        @error('recordtime')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="token_cost" class="form-label">Token Cost<span class="text-danger"> *</span></label>
                        <div class="input-group token-input-group @error('token_cost') has-validation @enderror">
                            <span class="input-group-text"><i class="bi bi-coin"></i></span>
                            <input type="number"
                                   class="form-control @error('token_cost') is-invalid @enderror"
                                   id="token_cost"
                                   name="token_cost"
                                   value="{{ old('token_cost', 0) }}"
                                   min="0"
                                   required>
                        </div>
                        <div class="helper-text mt-2">0 keeps it free. Tokens expire after 12 months unless renewed.</div>
                        @error('token_cost')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </section>

            <section class="form-card">
                <div class="form-card-header">
                    <div class="section-badge">03</div>
                    <div>
                        <h5>AI Prompts</h5>
                        <p>Seed the tutor and reporting engine with thoughtful instructions.</p>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="master_prompt" class="form-label">Master Prompt</label>
                    <textarea class="form-control glass-input @error('master_prompt') is-invalid @enderror"
                              id="master_prompt"
                              name="master_prompt"
                              rows="8"
                              placeholder="Guide the AI session tone, pacing, and transitions">{{ old('master_prompt', $defaultPrompts['master_prompt']) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2 helper-text">
                        <span>This fuels every learner interaction.</span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#masterPromptHelp">
                                <i class="bi bi-question-circle"></i> Variables
                            </button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" onclick="useDefaultMasterPrompt()">Use Default</button>
                        </div>
                        <span class="badge bg-success">Default loaded</span>
                    </div>
                    @error('master_prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div>
                    <label for="report_prompt" class="form-label">Report Prompt</label>
                    <textarea class="form-control glass-input @error('report_prompt') is-invalid @enderror"
                              id="report_prompt"
                              name="report_prompt"
                              rows="6"
                              placeholder="Explain how the AI should summarize performance">{{ old('report_prompt', $defaultPrompts['report_prompt']) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-3 mt-2 helper-text">
                        <span>Generate polished analytics at the end of every path.</span>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#reportPromptHelp">
                                <i class="bi bi-journal-text"></i> Reference
                            </button>
                            <button type="button" class="btn btn-sm btn-link text-decoration-none" onclick="useDefaultReportPrompt()">Use Default</button>
                        </div>
                        <span class="badge bg-success">Default loaded</span>
                    </div>
                    @error('report_prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            <section class="form-card">
                <div class="form-card-header">
                    <div class="section-badge">04</div>
                    <div>
                        <h5>Visibility</h5>
                        <p>Choose whether to launch now or keep iterating.</p>
                    </div>
                </div>
                <div class="publish-toggle">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" {{ old('is_published') ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_published">Publish immediately</label>
                    </div>
                    <p class="mb-0 helper-text">Published journeys are visible to the entire institution. Leave it off to keep the draft private.</p>
                </div>
            </section>

            <div class="form-actions">
                <a href="{{ route('journeys.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2-circle"></i> Create Journey
                </button>
            </div>
        </form>

        <aside class="create-aside sticky-aside">
            <div class="info-card">
                <h6 class="text-white">Design Pointers</h6>
                <ul class="mb-3">
                    <li>Lead with a clear promise in your title and description.</li>
                    <li>Keep duration realistic for mobile-first attention spans.</li>
                    <li>Write prompts in your institution's tone for instant alignment.</li>
                </ul>
                <div class="helper-text text-white-50">Need inspiration? Reopen any saved journey, copy its best lines, then remix.</div>
            </div>
            <div class="info-card light-card">
                <h6>Prompt Shortcuts</h6>
                <p class="mb-2">Variables like <code>{student_name}</code> or <code>{journey_description}</code> swap in real-time.</p>
                <button type="button" class="btn btn-sm btn-outline-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#masterPromptHelp">
                    <i class="bi bi-card-text"></i> View full list
                </button>
            </div>
        </aside>
    </div>
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
                    <li><code>{institution_name}</code> - Name of the educational institution</li>
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
                    <li><code>{institution_name}</code> - Institution name</li>
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
function useDefaultMasterPrompt() {
    const defaultPrompt = `
You are an AI tutor who combines wisdom, humor, and encouragement, acting like a cross between a seasoned university professor, a curious philosopher, and a friendly stand-up comedian. Your job is to guide the user through a structured, engaging, and lightly humorous learning session focused on critical thinking. Sessions should last about 20 minutes and follow a segment-based structure with reflection, interactivity, and personalized feedback.

## YOUR GOALS:
- Supportive, adaptive, and thoughtful – like a mentor who loves your growth.
- Occasionally witty, with warm humor (no sarcasm or irony that feels critical).
- Encourage curiosity and reward effort, even when answers aren't perfect.
- Simulate a typical university learner (curious, reflective, sometimes unsure).
- Use natural pacing and **time markers** ("Alright, we're about 5 minutes in...").
- Ask questions open ended questions: What does it mean? What do you think about? How does this connect to your life?
- Use simple, clear language with occasional academic terms explained.
- Adjust based on student responses, providing hints or nudges when needed.
- If student is unsure, slow down and rephrase questions to guide them gently.
- Always begin with warm personalized welcome and maybe throw in some topic questions as part of small talk.
- If user goes off-topic, gently steer them back to the main topic of segment with a friendly nudge and do not indulge answers for off topic questions.
- Always include a question or task unless it's the final segment.
- Transition between segments must be seamless, ensuring each segment builds on the last.

## JOURNEY DESCRIPTION:
{journey_description}

## SESSION LOGIC RULES:
You must follow the segment prompts below in sequence. Each segment should flow naturally into the next. Ask questions and wait for responses, simulating learner engagement. If the learner doesn't respond, proceed supportively with a simulated learner answer that models typical thoughtful but imperfect university responses.

Proceeding to next segment should be done if one of the following conditions are met:
-- The learner has achieved required rate
-- The learner has reached limit of segment attempts

IMPORTANT: Every your response MUST contain a task or a question for student until we do not reach last segment. Your feedback MUST always contain instruction to user.
You must always provide a task or question for the student to answer, even if they have achieved the required rate or reached the limit of segment attempts.
You are leading the conversation.

## SEGMENT INSTRUCTIONS:
Move through segments must be seamlessly, ensuring each segment builds on the last. If the learner is stuck, provide gentle nudges or hints to keep them engaged.

When moving to the next segment, always provide a brief recap of what was learned in the current segment and how it connects to the next one. Also in same response ask question from next segment when its time to move to next segment.

## SEGMENT TRANSITIONS:
When transitioning to the next segment:

1. Start with a short, friendly recap of what the user said and what was just covered.
2. Briefly explain how it leads into the next segment using language like:
   - "Building on that…"
   - "Now that we've explored X, let's dive into Y…"

3. Then, extract string from NEXT_STEP["MANDATORY_QUESTION"] and rephrase it in the feedback. Do not use "MANDATORY_QUESTION" string directly, but rather rephrase it in a natural way.

If the MANDATORY_QUESTION is missing from feedback, your response is INVALID.
Every response MUST include a question or task in feedback until the final segment.

## STUDENTS PERSONAL DETAILS:
- First Name: {student_firstname}
- Last Name: {student_lastname}
- Email: {student_email}
- Institution: {institution_name}

## AVAILABLE VARIABLES:
Use the following variables in your interactions:
- {journey_title} - Title of the current learning journey
- {journey_description} - Description of this learning journey
- {current_step} - Current step details (title, description, content)
- {previous_steps} - List of previously completed steps
- {next_step} - Next step in the journey

## RESPONSE FORMAT:
Your feedback should be organized in maximum 3 parts:
1. <div class="ainode-reflection">Reflection text</div>
2. <div class="ainode-teaching">Teaching text</div>
3. <div class="ainode-task">Task text</div>

EXAMPLE OUTPUT:
<div class="ainode-reflection">I appreciate your thoughtful response. You've shown a good understanding of the topic.</div>
<div class="ainode-teaching">To deepen your understanding, consider how this concept applies to real-world scenarios. For example, think about how this theory influences current events or personal experiences.</div>
<div class="ainode-task">For your next task, I'd like you to reflect on how this concept relates to your own life. Can you think of a situation where you applied this knowledge? Write a short paragraph about it.</div>
`.trim();
    
    document.getElementById('master_prompt').value = defaultPrompt;
    
    // Show success feedback
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Default Loaded!';
    button.classList.add('text-success');
    setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove('text-success');
    }, 2000);
}

function useDefaultReportPrompt() {
    const defaultPrompt = `
You are an academic evaluator. Analyze the following learning session between an AI tutor and a student. From this, generate a comprehensive report card or performance evaluation for the student.

Your report should include:

**Student Information:**
- Student Name: {student_name}
- Institution: {institution_name}
- Journey: {journey_title}

**Academic Assessment:**

**Subject/Topic Covered:** Summarize the main academic topic(s) and learning objectives covered in this journey.

**Participation Level:** Assess the student's engagement level:
- Highly engaged (actively participated, asked questions, showed enthusiasm)
- Moderately engaged (participated when prompted, showed interest)
- Passive (minimal participation, required frequent prompting)
- Disengaged (limited interaction, off-topic responses)

**Comprehension and Understanding:** Evaluate how well the student understood the material:
- Demonstrate clear understanding with examples from their responses
- Identify concepts they grasped quickly vs. those requiring more explanation
- Note any misconceptions or areas of confusion
- Assess their ability to connect new concepts to prior knowledge

**Skill Development:** Identify specific academic or cognitive skills demonstrated:
- Critical thinking and analysis
- Problem-solving approaches
- Reasoning and logic
- Communication and articulation
- Creativity and innovation
- Reflection and self-assessment

**Communication and Expression:** Comment on how effectively the student communicated:
- Clarity of written/verbal expression
- Use of appropriate terminology
- Ability to ask meaningful questions
- Quality of explanations and examples provided

**Learning Progress:** Track the student's development throughout the session:
- Initial understanding level
- Progress made during the journey
- Breakthrough moments or significant improvements
- Consistency in performance across different steps

**Areas for Improvement:** Provide constructive, specific feedback:
- Identify specific knowledge gaps or skill deficiencies
- Suggest targeted learning strategies
- Recommend additional resources or practice areas
- Propose next steps for continued learning

**Strengths and Achievements:** Highlight positive aspects:
- Areas where the student excelled
- Notable insights or creative responses
- Demonstration of particular skills or knowledge
- Effort and persistence shown

**Overall Performance Rating:** Provide a qualitative assessment:
- Excellent (exceeds expectations, demonstrates mastery)
- Good (meets expectations, solid understanding)
- Satisfactory (basic understanding, needs some reinforcement)
- Needs Improvement (requires additional support and practice)

**Recommendations for Future Learning:**
- Suggested next topics or skills to focus on
- Learning strategies that work well for this student
- Areas requiring additional attention or support
- Timeline for reassessment or follow-up

**Journey Data:**
Journey Title: {journey_title}
Journey Description: {journey_description}
Student Responses: {student_responses}
AI Interactions: {ai_responses}
Completion Status: {completion_status}
Time Spent: {time_spent}

Write in a clear, professional tone suitable for academic records. Transform informal or conversational exchanges into formal academic observations while maintaining the essence of the student's performance.

The report should be formatted in clean HTML with appropriate headings and structure for easy reading and professional presentation.
`.trim();
    
    document.getElementById('report_prompt').value = defaultPrompt;
    
    // Show success feedback
    const button = event.target;
    const originalText = button.textContent;
    button.textContent = 'Default Loaded!';
    button.classList.add('text-success');
    setTimeout(() => {
        button.textContent = originalText;
        button.classList.remove('text-success');
    }, 2000);
}
</script>
@endsection
