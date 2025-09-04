@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3">Add Step</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('journeys.index') }}">Journeys</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('journeys.show', $journey) }}">{{ $journey->title }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('journeys.steps.index', $journey) }}">Steps</a></li>
                            <li class="breadcrumb-item active">Add Step</li>
                        </ol>
                    </nav>
                </div>
                <a href="{{ route('journeys.steps.index', $journey) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Steps
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="{{ route('journeys.steps.store', $journey) }}" method="POST">
                                @csrf

                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="title" class="form-label">Step Title <span class="text-danger">*</span></label>
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
                                        <label for="order" class="form-label">Step Order <span class="text-danger">*</span></label>
                                        <input type="number" 
                                               class="form-control @error('order') is-invalid @enderror" 
                                               id="order" 
                                               name="order" 
                                               value="{{ old('order', $nextOrder) }}" 
                                               min="1" 
                                               required>
                                        @error('order')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="type" class="form-label">Step Type <span class="text-danger">*</span></label>
                                        <select class="form-select @error('type') is-invalid @enderror" 
                                                id="type" 
                                                name="type" 
                                                required>
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

                                    <div class="col-md-6 mb-3">
                                        <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                        <input type="number" 
                                               class="form-control @error('time_limit') is-invalid @enderror" 
                                               id="time_limit" 
                                               name="time_limit" 
                                               value="{{ old('time_limit') }}" 
                                               min="1">
                                        <div class="form-text">Optional: Set a time limit for this step</div>
                                        @error('time_limit')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="ratepass" class="form-label">Pass Rating <span class="text-danger">*</span></label>
                                        <select class="form-select @error('ratepass') is-invalid @enderror" 
                                                id="ratepass" 
                                                name="ratepass" 
                                                required>
                                            <option value="">Select Rating</option>
                                            <option value="1" {{ old('ratepass') == '1' ? 'selected' : '' }}>1 - Basic</option>
                                            <option value="2" {{ old('ratepass') == '2' ? 'selected' : '' }}>2 - Fair</option>
                                            <option value="3" {{ old('ratepass') == '3' ? 'selected' : '' }}>3 - Good</option>
                                            <option value="4" {{ old('ratepass') == '4' ? 'selected' : '' }}>4 - Very Good</option>
                                            <option value="5" {{ old('ratepass') == '5' ? 'selected' : '' }}>5 - Excellent</option>
                                        </select>
                                        <div class="form-text">Minimum rating required to pass this step</div>
                                        @error('ratepass')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="maxattempts" class="form-label">Max Attempts <span class="text-danger">*</span></label>
                                        <input type="number" 
                                               class="form-control @error('maxattempts') is-invalid @enderror" 
                                               id="maxattempts" 
                                               name="maxattempts" 
                                               value="{{ old('maxattempts', 3) }}" 
                                               min="1" 
                                               max="10"
                                               required>
                                        <div class="form-text">Maximum attempts allowed for this step</div>
                                        @error('maxattempts')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                                    <textarea class="form-control @error('content') is-invalid @enderror" 
                                              id="content" 
                                              name="content" 
                                              rows="10" 
                                              required>{{ old('content') }}</textarea>
                                    <div class="form-text">
                                        <div id="content-help">
                                            <!-- Dynamic help text based on selected type -->
                                        </div>
                                    </div>
                                    @error('content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="expected_output" class="form-label">Expected Output</label>
                                    <textarea class="form-control @error('expected_output') is-invalid @enderror" 
                                              id="expected_output" name="expected_output" rows="4" 
                                              placeholder="Describe what the ai assistant should produce or achieve in this step...">{{ old('expected_output') }}</textarea>
                                    <div class="form-text">Define what output or achievement is expected from the learner for this step.</div>
                                    @error('expected_output')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="rating_prompt" class="form-label">Rating Prompt</label>
                                    <textarea class="form-control @error('rating_prompt') is-invalid @enderror" 
                                              id="rating_prompt" name="rating_prompt" rows="4" 
                                              placeholder="Provide instructions for how this step should be rated or evaluated...">{{ old('rating_prompt', \App\Services\PromptDefaults::getDefaultRatePrompt()) }}</textarea>
                                    <div class="form-text">Instructions for evaluating or rating the learner's performance on this step.</div>
                                    @error('rating_prompt')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <!-- Type-specific configuration sections -->
                                <div id="video-config" class="type-config" style="display: none;">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title">Video Configuration</h6>
                                            <div class="mb-3">
                                                <label for="video_url" class="form-label">Video URL</label>
                                                <input type="url" class="form-control" id="video_url" name="configuration[video_url]" 
                                                       placeholder="https://youtube.com/watch?v=...">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="video_autoplay" 
                                                       name="configuration[autoplay]" value="1">
                                                <label class="form-check-label" for="video_autoplay">
                                                    Auto-play video
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="quiz-config" class="type-config" style="display: none;">
                                    <div class="card bg-light mb-3">
                                        <div class="card-body">
                                            <h6 class="card-title">Quiz Configuration</h6>
                                            <div class="mb-3">
                                                <label for="quiz_passing_score" class="form-label">Passing Score (%)</label>
                                                <input type="number" class="form-control" id="quiz_passing_score" 
                                                       name="configuration[passing_score]" value="70" min="0" max="100">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="quiz_randomize" 
                                                       name="configuration[randomize_questions]" value="1">
                                                <label class="form-check-label" for="quiz_randomize">
                                                    Randomize question order
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="is_required" 
                                               name="is_required" 
                                               value="1" 
                                               {{ old('is_required', true) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_required">
                                            Required Step
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Required steps must be completed before users can proceed to the next step.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('journeys.steps.index', $journey) }}" class="btn btn-secondary">
                                        <i class="bi bi-x-lg"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Create Step
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

<!-- Add default values as script variables -->
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
    const typeConfigs = document.querySelectorAll('.type-config');

    const helpTexts = {
        text: 'Enter the main content for this step. You can use HTML formatting.',
        video: 'Provide instructions or description for the video content. The video URL can be configured below.',
        quiz: 'Create quiz questions in JSON format or provide instructions. Example structure will be shown.',
        interactive: 'Define interactive elements or activities for this step.',
        assignment: 'Describe the assignment requirements and submission guidelines.'
    };

    const contentExamples = {
        text: 'Prompt for ai in this particular step',
        video: '<p>Watch the video below to understand the key concepts:</p>\n<p><strong>Key points to focus on:</strong></p>\n<ul>\n  <li>Point 1</li>\n  <li>Point 2</li>\n</ul>',
        quiz: '{\n  "questions": [\n    {\n      "question": "What is 2 + 2?",\n      "type": "multiple_choice",\n      "options": ["3", "4", "5", "6"],\n      "correct": 1\n    }\n  ]\n}',
        interactive: '<div class="activity">\n  <h4>Interactive Activity</h4>\n  <p>Complete the following task:</p>\n  <div id="interactive-element">\n    <!-- Interactive content here -->\n  </div>\n</div>',
        assignment: '<h4>Assignment: Create a Project</h4>\n<p><strong>Objective:</strong> Create a simple project demonstrating the concepts learned.</p>\n<p><strong>Requirements:</strong></p>\n<ul>\n  <li>Requirement 1</li>\n  <li>Requirement 2</li>\n</ul>\n<p><strong>Submission:</strong> Upload your completed project files.</p>'
    };

    function updateTypeSpecificContent() {
        const selectedType = typeSelect.value;
        console.log('Selected type:', selectedType);
        
        // Hide all type configs
        typeConfigs.forEach(config => config.style.display = 'none');
        
        if (selectedType) {
            // Show help text
            contentHelp.innerHTML = `<i class="bi bi-info-circle"></i> ${helpTexts[selectedType]}`;
            
            // Show relevant config
            const config = document.getElementById(`${selectedType}-config`);
            if (config) {
                config.style.display = 'block';
            }
            
            // Update content with example if empty
            if (!contentTextarea.value.trim()) {
                contentTextarea.value = contentExamples[selectedType] || '';
            }

            // Update Expected Output field with defaults for text and video types
            if (selectedType === 'text' || selectedType === 'video') {
                console.log('Updating Expected Output for type:', selectedType);
                if (window.promptDefaults && window.promptDefaults.expectedOutputs[selectedType]) {
                    expectedOutputTextarea.value = window.promptDefaults.expectedOutputs[selectedType];
                    console.log('Updated Expected Output with', selectedType, 'default');
                }
            }
        } else {
            contentHelp.innerHTML = '';
        }
    }

    typeSelect.addEventListener('change', updateTypeSpecificContent);
    
    // Initialize on page load - force update even if "text" is pre-selected
    setTimeout(() => {
        updateTypeSpecificContent();
    }, 100);
});
</script>
@endsection
