@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-plus-lg"></i> Create Journey
                </h1>
                <a href="{{ route('journeys.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Journeys
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="{{ route('journeys.store') }}" method="POST">
                                @csrf

                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="title" class="form-label">Journey Title <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control @error('title') is-invalid @enderror" 
                                               id="title" 
                                               name="title" 
                                               value="{{ old('title') }}" 
                                               required>
                                        @error('title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-4 mb-3">
                                        <label for="difficulty_level" class="form-label">Difficulty Level <span class="text-danger">*</span></label>
                                        <select class="form-select @error('difficulty_level') is-invalid @enderror" 
                                                id="difficulty_level" 
                                                name="difficulty_level" 
                                                required>
                                            <option value="">Select Difficulty</option>
                                            <option value="beginner" {{ old('difficulty_level') == 'beginner' ? 'selected' : '' }}>Beginner</option>
                                            <option value="intermediate" {{ old('difficulty_level') == 'intermediate' ? 'selected' : '' }}>Intermediate</option>
                                            <option value="advanced" {{ old('difficulty_level') == 'advanced' ? 'selected' : '' }}>Advanced</option>
                                        </select>
                                        @error('difficulty_level')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" 
                                              name="description" 
                                              rows="4" 
                                              required>{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="master_prompt" class="form-label">
                                        Master Prompt 
                                        <button type="button" class="btn btn-sm btn-outline-info ms-2" data-bs-toggle="modal" data-bs-target="#masterPromptHelp">
                                            <i class="bi bi-question-circle"></i> Help
                                        </button>
                                    </label>
                                    <textarea class="form-control @error('master_prompt') is-invalid @enderror" 
                                              id="master_prompt" 
                                              name="master_prompt" 
                                              rows="8" 
                                              placeholder="Enter the master prompt for this journey...">{{ old('master_prompt', $defaultPrompts['master_prompt']) }}</textarea>
                                    <div class="form-text">
                                        This prompt guides AI-powered learning features. 
                                        <button type="button" class="btn btn-link btn-sm p-0" onclick="useDefaultMasterPrompt()">Use Default Prompt</button>
                                        <span class="badge bg-success ms-2">Default Loaded</span>
                                    </div>
                                    @error('master_prompt')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="report_prompt" class="form-label">
                                        Report Prompt
                                        <button type="button" class="btn btn-sm btn-outline-info ms-2" data-bs-toggle="modal" data-bs-target="#reportPromptHelp">
                                            <i class="bi bi-question-circle"></i> Help
                                        </button>
                                    </label>
                                    <textarea class="form-control @error('report_prompt') is-invalid @enderror" 
                                              id="report_prompt" 
                                              name="report_prompt" 
                                              rows="6" 
                                              placeholder="Enter the report generation prompt for this journey...">{{ old('report_prompt', $defaultPrompts['report_prompt']) }}</textarea>
                                    <div class="form-text">
                                        This prompt generates progress reports and analytics.
                                        <button type="button" class="btn btn-link btn-sm p-0" onclick="useDefaultReportPrompt()">Use Default Prompt</button>
                                        <span class="badge bg-success ms-2">Default Loaded</span>
                                    </div>
                                    @error('report_prompt')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="journey_collection_id" class="form-label">Collection <span class="text-danger">*</span></label>
                                        <select class="form-select @error('journey_collection_id') is-invalid @enderror" 
                                                id="journey_collection_id" 
                                                name="journey_collection_id" 
                                                required>
                                            <option value="">Select Collection</option>
                                            @foreach($collections as $collection)
                                                <option value="{{ $collection->id }}" 
                                                        {{ old('journey_collection_id') == $collection->id ? 'selected' : '' }}>
                                                    {{ $collection->name }} ({{ $collection->institution->name }})
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('journey_collection_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="estimated_duration" class="form-label">Estimated Duration (minutes) <span class="text-danger">*</span></label>
                                        <input type="number" 
                                               class="form-control @error('estimated_duration') is-invalid @enderror" 
                                               id="estimated_duration" 
                                               name="estimated_duration" 
                                               value="{{ old('estimated_duration') }}" 
                                               min="1" 
                                               required>
                                        @error('estimated_duration')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="recordtime" class="form-label">Record Time (seconds)</label>
                                        <input type="number" 
                                               class="form-control @error('recordtime') is-invalid @enderror" 
                                               id="recordtime" 
                                               name="recordtime" 
                                               value="{{ old('recordtime') }}" 
                                               min="0" 
                                               placeholder="Enter recording time in seconds">
                                        <div class="form-text">
                                            Maximum recording duration for voice interactions in seconds.
                                        </div>
                                        @error('recordtime')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <!-- Empty column for layout balance -->
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="is_published" 
                                               name="is_published" 
                                               value="1" 
                                               {{ old('is_published') ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_published">
                                            Publish immediately
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Published journeys are visible to all users. Unpublished journeys are only visible to you and administrators.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('journeys.index') }}" class="btn btn-secondary">
                                        <i class="bi bi-x-lg"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Create Journey
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
