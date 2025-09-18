@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3">Journey Steps</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('journeys.index') }}">Journeys</a></li>
                            <li class="breadcrumb-item"><a href="{{ route('journeys.show', $journey) }}">{{ $journey->title }}</a></li>
                            <li class="breadcrumb-item active">Steps</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Journey
                    </a>
                    <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Step
                    </a>
                </div>
            </div>

            @if($steps->count() > 0)
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ol"></i> Steps ({{ $steps->count() }})
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="steps-container">
                            @foreach($steps as $step)
                                <div class="step-item card mb-3" data-step-id="{{ $step->id }}">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="d-flex align-items-start">
                                                <div class="drag-handle me-3 text-muted" style="cursor: move;">
                                                    <i class="bi bi-grip-vertical"></i>
                                                </div>
                                                <div class="step-badge me-3">
                                                    <span class="badge bg-primary fs-6">{{ $step->order }}</span>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">{{ $step->title }}</h6>
                                                    <div class="d-flex gap-2 mb-2">
                                                        <span class="badge bg-{{ 
                                                            $step->type === 'text' ? 'secondary' : 
                                                            ($step->type === 'video' ? 'danger' : 
                                                            ($step->type === 'quiz' ? 'warning' : 
                                                            ($step->type === 'interactive' ? 'info' : 'success'))) 
                                                        }}">
                                                            {{ ucfirst($step->type) }}
                                                        </span>
                                                        @if($step->is_required)
                                                            <span class="badge bg-outline-dark">Required</span>
                                                        @endif
                                                        @if($step->time_limit)
                                                            <span class="badge bg-outline-warning">{{ $step->time_limit }} min</span>
                                                        @endif
                                                    </div>
                                                    <p class="text-muted mb-0">
                                                        {{ Str::limit(strip_tags($step->content), 100) }}
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="btn-group">
                                                <a href="{{ route('journeys.steps.show', [$journey, $step]) }}" 
                                                   class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('journeys.steps.edit', [$journey, $step]) }}" 
                                                   class="btn btn-outline-secondary btn-sm">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-sm"
                                                        data-step-id="{{ $step->id }}"
                                                        data-step-title="{{ $step->title }}"
                                                        onclick="deleteStep(this.getAttribute('data-step-id'), this.getAttribute('data-step-title'))">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4 p-3 bg-light rounded">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                Drag and drop steps to reorder them. Changes are saved automatically.
                            </small>
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-list-ol display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No Steps Added Yet</h3>
                    <p class="text-muted">Create interactive steps to build an engaging learning journey.</p>
                    <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Add Your First Step
                    </a>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete Step Modal -->
<div class="modal fade" id="deleteStepModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Step</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this step?</p>
                <p class="text-danger" id="stepTitle"></p>
                <p class="text-muted">This action cannot be undone and will affect the step order.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteStepForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete Step</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- SortableJS is loaded via compiled app.js in layout -->
<script>
// Verify SortableJS is available from compiled assets
if (typeof Sortable === 'undefined') {
    console.error('SortableJS is not available. Make sure app.js is loaded in the layout.');
}

function deleteStep(stepId, title) {
    document.getElementById('stepTitle').textContent = title;
    document.getElementById('deleteStepForm').action = 
        `{{ route('journeys.steps.index', $journey) }}/${stepId}`;
    new bootstrap.Modal(document.getElementById('deleteStepModal')).show();
}

// Initialize sortable for step reordering
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('steps-container');
    if (container) {
        new Sortable(container, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: function(evt) {
                const steps = [];
                container.querySelectorAll('.step-item').forEach((item, index) => {
                    steps.push({
                        id: parseInt(item.dataset.stepId),
                        order: index + 1
                    });
                });

                // Update step numbers visually
                container.querySelectorAll('.step-badge .badge').forEach((badge, index) => {
                    badge.textContent = index + 1;
                });

                // Send AJAX request to update order
                fetch(`{{ route('journeys.steps.reorder', $journey) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ steps: steps })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        // Reload page if update failed
                        location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    location.reload();
                });
            }
        });
    }
});
</script>
@endsection
