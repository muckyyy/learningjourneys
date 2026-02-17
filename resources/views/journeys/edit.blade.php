@extends('layouts.app')

@section('content')
@php
    $steps = $journey->steps->sortBy('order');
    $stepsCount = $steps->count();
    $lastUpdated = optional($journey->updated_at)->format('M j, Y');
    $statusLabel = $journey->is_published ? 'Live' : 'Draft';
@endphp

<div class="shell" style="max-width: 780px;">

    {{-- Header --}}
    <header class="mb-4 pb-3" style="border-bottom: 1px solid rgba(15,23,42,0.08);">
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="{{ route('journeys.show', $journey) }}" class="text-muted" style="font-size: 0.85rem; text-decoration: none;">
                <i class="bi bi-arrow-left"></i> Back to journey
            </a>
        </div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h2 class="fw-bold mb-0" style="color: var(--lj-ink); letter-spacing: -0.02em;">Edit {{ $journey->title }}</h2>
            <span class="badge rounded-pill {{ $journey->is_published ? 'bg-success' : 'bg-secondary' }}">{{ $statusLabel }}</span>
        </div>
        <p class="text-muted mb-0" style="font-size: 0.9rem;">Last updated {{ $lastUpdated }} · {{ $stepsCount }} {{ Str::plural('step', $stepsCount) }}</p>
    </header>

    <form action="{{ route('journeys.update', $journey) }}" method="POST">
        @csrf
        @method('PUT')

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
                               value="{{ old('title', $journey->title) }}"
                               placeholder="Ex: Socratic Reasoning Sprint"
                               maxlength="255"
                               data-char-limit="255"
                               data-char-target="editTitleCounter"
                               required>
                        <div class="d-flex justify-content-end mt-1">
                            <small id="editTitleCounter" class="text-muted fw-semibold">0/255</small>
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
                            <option value="beginner" {{ old('difficulty_level', $journey->difficulty_level) == 'beginner' ? 'selected' : '' }}>Beginner</option>
                            <option value="intermediate" {{ old('difficulty_level', $journey->difficulty_level) == 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                            <option value="advanced" {{ old('difficulty_level', $journey->difficulty_level) == 'advanced' ? 'selected' : '' }}>Advanced</option>
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
                              data-char-target="editShortDescriptionCounter"
                              required>{{ old('short_description', $journey->short_description) }}</textarea>
                    <div class="d-flex justify-content-between mt-1">
                        <small class="text-muted">Shows on cards, emails, and search.</small>
                        <small id="editShortDescriptionCounter" class="text-muted fw-semibold">0/255</small>
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
                              placeholder="Explain the promise of this journey"
                              required>{{ old('description', $journey->description) }}</textarea>
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
                        <label for="journey_collection_id" class="form-label fw-medium">Collection <span class="text-danger">*</span></label>
                        <select class="form-select @error('journey_collection_id') is-invalid @enderror"
                                id="journey_collection_id"
                                name="journey_collection_id"
                                required>
                            <option value="">Select collection</option>
                            @foreach($collections as $collection)
                                <option value="{{ $collection->id }}" {{ old('journey_collection_id', $journey->journey_collection_id) == $collection->id ? 'selected' : '' }}>
                                    {{ $collection->name }} ({{ $collection->institution->name }})
                                </option>
                            @endforeach
                        </select>
                        @error('journey_collection_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="estimated_duration" class="form-label fw-medium">Estimated Duration (minutes) <span class="text-danger">*</span></label>
                        <input type="number"
                               class="form-control @error('estimated_duration') is-invalid @enderror"
                               id="estimated_duration"
                               name="estimated_duration"
                               value="{{ old('estimated_duration', $journey->estimated_duration) }}"
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
                               value="{{ old('recordtime', $journey->recordtime) }}"
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
                                   value="{{ old('token_cost', $journey->token_cost) }}"
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
                              placeholder="Guide the AI session tone, pacing, and transitions">{{ old('master_prompt', $journey->master_prompt) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <small class="text-muted">This fuels every learner interaction.</small>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#masterPromptHelp">
                            <i class="bi bi-question-circle"></i> Variables
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="useDefaultMasterPrompt()">Use Default</button>
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
                              placeholder="Explain how the AI should summarize performance">{{ old('report_prompt', $journey->report_prompt) }}</textarea>
                    <div class="d-flex flex-wrap align-items-center gap-2 mt-2">
                        <small class="text-muted">Generates analytics at the end of every path.</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill" data-bs-toggle="modal" data-bs-target="#reportPromptHelp">
                            <i class="bi bi-journal-text"></i> Reference
                        </button>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" onclick="useDefaultReportPrompt()">Use Default</button>
                    </div>
                    @error('report_prompt')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </section>

            {{-- Section 4: Visibility & Safety --}}
            <section class="pt-4" style="border-top: 1px solid rgba(15,23,42,0.06);">
                <h5 class="fw-semibold mb-3" style="font-size: 1.05rem; color: var(--lj-ink);">Visibility & Safety</h5>

                <div class="d-flex align-items-start gap-3 p-3 rounded-3 mb-3" style="background: rgba(var(--lj-brand-rgb), 0.04);">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="is_published" name="is_published" value="1" {{ old('is_published', $journey->is_published) ? 'checked' : '' }}>
                        <label class="form-check-label fw-semibold" for="is_published">Published</label>
                    </div>
                </div>
                <small class="text-muted d-block mb-3">Keep unpublished while iterating. Flip it live when the experience feels premium.</small>

                @can('delete', $journey)
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pt-3" style="border-top: 1px solid rgba(15,23,42,0.06);">
                        <small class="text-muted">Need to sunset this journey? Delete removes attempts forever.</small>
                        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash"></i> Delete Journey
                        </button>
                    </div>
                @endcan
            </section>

        </div>{{-- /glass-card --}}

        {{-- Actions --}}
        <div class="d-flex justify-content-between align-items-center mt-4">
            <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-secondary rounded-pill px-4">
                <i class="bi bi-x-lg"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-check2-circle"></i> Update Journey
            </button>
        </div>
    </form>

    {{-- Steps Section --}}
    <div class="glass-card mt-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
            <div>
                <h5 class="fw-semibold mb-1" style="font-size: 1.05rem; color: var(--lj-ink);">Journey Steps ({{ $stepsCount }})</h5>
                <small class="text-muted">Drag steps to reorder. Changes save automatically.</small>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-primary btn-sm rounded-pill"><i class="bi bi-plus-lg"></i> Add Step</a>
            </div>
        </div>
        @if($stepsCount)
            <div id="steps-sortable">
                @foreach($steps as $step)
                    <div class="step-sort-item d-flex align-items-center justify-content-between py-2 px-2 rounded-3 {{ !$loop->last ? 'mb-1' : '' }}" data-id="{{ $step->id }}" style="{{ $loop->iteration % 2 === 0 ? 'background: rgba(15,23,42,0.02);' : '' }}">
                        <div class="d-flex align-items-center gap-3">
                            <span class="sort-handle d-flex align-items-center text-muted" style="cursor: grab; padding: 4px;"><i class="bi bi-grip-vertical"></i></span>
                            <span class="step-order-num d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 28px; height: 28px; font-size: 0.75rem; background: rgba(var(--lj-brand-rgb), 0.10); color: var(--lj-brand-dark);">{{ $step->order }}</span>
                            <div>
                                <span class="fw-medium" style="color: var(--lj-ink);">{{ $step->title }}</span>
                                <small class="text-muted d-block">{{ ucfirst($step->type) }}</small>
                            </div>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="{{ route('journeys.steps.show', [$journey, $step]) }}" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size: 0.75rem;"><i class="bi bi-eye"></i></a>
                            <a href="{{ route('journeys.steps.edit', [$journey, $step]) }}" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size: 0.75rem;"><i class="bi bi-pencil"></i></a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-4">
                <i class="bi bi-list-ol text-muted d-block mb-2" style="font-size: 2rem;"></i>
                <p class="text-muted mb-3">No steps added yet. Layer interactive tasks and reflections to engage learners.</p>
                <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-primary btn-sm rounded-pill"><i class="bi bi-plus-lg"></i> Add First Step</a>
            </div>
        @endif
    </div>
</div>

<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Journey</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this journey?</p>
                <p class="text-danger"><strong>{{ $journey->title }}</strong></p>
                <p class="text-muted">This action cannot be undone. All attempts, responses, and analytics will be removed.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('journeys.destroy', $journey) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Journey</button>
                </form>
            </div>
        </div>
    </div>
</div>

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
}
</script>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('steps-sortable');
    if (!container || typeof window.Sortable === 'undefined') return;

    Sortable.create(container, {
        handle: '.sort-handle',
        animation: 200,
        ghostClass: 'sortable-ghost',
        onEnd: function () {
            const items = container.querySelectorAll('.step-sort-item');
            const steps = [];

            items.forEach(function (item, index) {
                const order = index + 1;
                steps.push({ id: parseInt(item.dataset.id), order: order });

                // Update the visible order number
                const orderEl = item.querySelector('.step-order-num');
                if (orderEl) orderEl.textContent = order;

                // Reset alternating row backgrounds
                item.style.background = (index % 2 === 1) ? 'rgba(15,23,42,0.02)' : '';
            });

            fetch("{{ route('journeys.steps.reorder', $journey) }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ steps: steps })
            });
        }
    });
});
</script>
@endpush
@endsection
