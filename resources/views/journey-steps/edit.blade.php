@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3">Edit Step: {{ $step->title }}</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('journeys.index') }}">Journeys</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('journeys.show', $journey) }}">{{ $journey->title }}</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('journeys.steps.index', $journey) }}">Steps</a></li>
                            <li class="breadcrumb-item active">Edit Step</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('journeys.steps.show', [$journey, $step]) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Step
                    </a>
                </div>
            </div>

            <form action="{{ route('journeys.steps.update', [$journey, $step]) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Step Content</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title *</label>
                                    <input type="text" class="form-control @error('title') is-invalid @enderror" 
                                           id="title" name="title" value="{{ old('title', $step->title) }}" required>
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="content" class="form-label">Content *</label>
                                    <textarea class="form-control @error('content') is-invalid @enderror" 
                                              id="content" name="content" rows="15" required>{{ old('content', $step->content) }}</textarea>
                                    <div class="form-text">Use HTML to format your content. Include videos, images, and interactive elements as needed.</div>
                                    @error('content')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="configuration" class="form-label">Configuration (JSON)</label>
                                    <textarea class="form-control @error('configuration') is-invalid @enderror" 
                                              id="configuration" name="configuration" rows="6" placeholder='{"key": "value"}'>{{ old('configuration', $step->config ? json_encode($step->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
                                    <div class="form-text">
                                        Add configuration options in JSON format. Examples:
                                        <br>Video: <code>{"video_url": "https://example.com/video.mp4", "autoplay": false}</code>
                                        <br>Quiz: <code>{"passing_score": 80, "randomize_questions": true}</code>
                                    </div>
                                    @error('configuration')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="expected_output" class="form-label">Expected Output</label>
                                    <textarea class="form-control @error('expected_output') is-invalid @enderror" 
                                              id="expected_output" name="expected_output" rows="4" 
                                              placeholder="Describe what the learner should produce or achieve in this step...">{{ old('expected_output', $step->expected_output) }}</textarea>
                                    <div class="form-text">Define what output or achievement is expected from the learner for this step.</div>
                                    @error('expected_output')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="rating_prompt" class="form-label">Rating Prompt</label>
                                    <textarea class="form-control @error('rating_prompt') is-invalid @enderror" 
                                              id="rating_prompt" name="rating_prompt" rows="4" 
                                              placeholder="Provide instructions for how this step should be rated or evaluated...">{{ old('rating_prompt', $step->rating_prompt) }}</textarea>
                                    <div class="form-text">Instructions for evaluating or rating the learner's performance on this step.</div>
                                    @error('rating_prompt')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Step Settings</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="type" class="form-label">Type *</label>
                                    <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" required>
                                        <option value="">Select type...</option>
                                        <option value="text" {{ old('type', $step->type) === 'text' ? 'selected' : '' }}>Text/Reading</option>
                                        <option value="video" {{ old('type', $step->type) === 'video' ? 'selected' : '' }}>Video</option>
                                        <option value="quiz" {{ old('type', $step->type) === 'quiz' ? 'selected' : '' }}>Quiz/Assessment</option>
                                        <option value="interactive" {{ old('type', $step->type) === 'interactive' ? 'selected' : '' }}>Interactive</option>
                                        <option value="document" {{ old('type', $step->type) === 'document' ? 'selected' : '' }}>Document</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="order" class="form-label">Follow up *</label>
                                    <input type="hidden" class="form-control @error('order') is-invalid @enderror" 
                                           id="order" name="order" value="{{ old('order', $step->order) }}" min="1" required>
                                        <input type="number" class="form-control @error('order') is-invalid @enderror" 
                                           id="maxfollowups" name="maxfollowups" value="{{ old('maxfollowups', $step->maxfollowups) }}" min="0" required>
                                    <div class="form-text">Steps will be displayed in this order.</div>
                                    @error('order')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="time_limit" class="form-label">Time Limit (minutes)</label>
                                    <input type="number" class="form-control @error('time_limit') is-invalid @enderror" 
                                           id="time_limit" name="time_limit" value="{{ old('time_limit', $step->time_limit) }}" min="1">
                                    <div class="form-text">Leave empty for no time limit.</div>
                                    @error('time_limit')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="ratepass" class="form-label">Pass Rating <span class="text-danger">*</span></label>
                                        <select class="form-select @error('ratepass') is-invalid @enderror" 
                                                id="ratepass" 
                                                name="ratepass" 
                                                required>
                                            <option value="">Select Rating</option>
                                            <option value="1" {{ old('ratepass', $step->ratepass) == '1' ? 'selected' : '' }}>1 - Basic</option>
                                            <option value="2" {{ old('ratepass', $step->ratepass) == '2' ? 'selected' : '' }}>2 - Fair</option>
                                            <option value="3" {{ old('ratepass', $step->ratepass) == '3' ? 'selected' : '' }}>3 - Good</option>
                                            <option value="4" {{ old('ratepass', $step->ratepass) == '4' ? 'selected' : '' }}>4 - Very Good</option>
                                            <option value="5" {{ old('ratepass', $step->ratepass) == '5' ? 'selected' : '' }}>5 - Excellent</option>
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
                                               value="{{ old('maxattempts', $step->maxattempts) }}" 
                                               min="1" 
                                               max="10"
                                               required>
                                        <div class="form-text">Maximum attempts allowed for this step</div>
                                        @error('maxattempts')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="is_required" name="is_required" 
                                           value="1" {{ old('is_required', $step->is_required) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_required">
                                        Required Step
                                    </label>
                                    <div class="form-text">Learners must complete required steps to progress.</div>
                                </div>

                                <hr>

                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Update Step
                                    </button>
                                    <a href="{{ route('journeys.steps.show', [$journey, $step]) }}" class="btn btn-outline-secondary">
                                        Cancel
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Card -->
                        <div class="card shadow-sm mt-3">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Current Step Info</h5>
                            </div>
                            <div class="card-body">
                                <dl class="row">
                                    <dt class="col-6">Current Order:</dt>
                                    <dd class="col-6">{{ $step->order }}</dd>
                                    
                                    <dt class="col-6">Current Type:</dt>
                                    <dd class="col-6">{{ ucfirst($step->type) }}</dd>
                                    
                                    <dt class="col-6">Created:</dt>
                                    <dd class="col-6">{{ $step->created_at->format('M d, Y') }}</dd>
                                    
                                    <dt class="col-6">Last Updated:</dt>
                                    <dd class="col-6">{{ $step->updated_at->diffForHumans() }}</dd>
                                </dl>
                            </div>
                        </div>

                        <!-- Delete Section -->
                        <div class="card border-danger shadow-sm mt-3">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">Danger Zone</h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">Once you delete this step, there is no going back. Please be certain.</p>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash"></i> Delete Step
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Delete Step</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the step "<strong>{{ $step->title }}</strong>"?</p>
                <p class="text-danger"><strong>This action cannot be undone.</strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('journeys.steps.destroy', [$journey, $step]) }}" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Step</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
#content {
    font-family: 'Courier New', monospace;
    font-size: 14px;
}

#configuration {
    font-family: 'Courier New', monospace;
    font-size: 13px;
}

.form-text code {
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
    padding: 0.25rem 0.5rem;
    font-size: 0.875em;
}
</style>
@endsection
