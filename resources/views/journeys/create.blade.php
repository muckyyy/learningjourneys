@extends('layouts.app')

@section('content')
@php
    $collectionCount = $collections->count();
    $defaultPromptCount = collect($defaultPrompts ?? [])->filter()->count();
@endphp

<div class="shell">
    <div class="hero blue">
        <div class="hero-content">
            <div class="pill light mb-3"><i class="bi bi-wand"></i> Builder</div>
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
                               maxlength="255"
                               data-char-limit="255"
                               data-char-target="createTitleCounter"
                               required>
                        <div class="d-flex justify-content-end mt-2 helper-text">
                            <span id="createTitleCounter" class="char-counter fw-semibold text-muted">0/255</span>
                        </div>
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
                    <label for="short_description" class="form-label">Short Description <span class="text-danger">*</span></label>
                    <textarea class="form-control glass-input @error('short_description') is-invalid @enderror"
                              id="short_description"
                              name="short_description"
                              rows="3"
                              placeholder="Write the 1-2 sentence teaser that appears in previews"
                              maxlength="255"
                              data-char-limit="255"
                              data-char-target="createShortDescriptionCounter"
                              required>{{ old('short_description') }}</textarea>
                    <div class="helper-text mt-2 d-flex justify-content-between flex-wrap gap-2">
                        <span>Keep it punchy—this line shows on cards, emails, and search.</span>
                        <span id="createShortDescriptionCounter" class="char-counter fw-semibold text-muted">0/255</span>
                    </div>
                    @error('short_description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
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
                               value="0"
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
