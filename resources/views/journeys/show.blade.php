@extends('layouts.app')

@section('content')
@php
    $stepsCount = $journey->steps->count();
    $lastUpdated = optional($journey->updated_at)->format('M j, Y');
@endphp

<div class="shell" style="max-width: 980px;">
    <header class="mb-4 pb-3" style="border-bottom: 1px solid rgba(15,23,42,0.08);">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
            <a href="{{ route('collections.show', $journey->collection) }}" class="text-muted" style="font-size: 0.85rem; text-decoration: none;">
                <i class="bi bi-arrow-left"></i> Back to {{ $journey->collection->name }}
            </a>
            <div class="d-flex flex-wrap gap-2">
                @can('update', $journey)
                    <a href="{{ route('journeys.edit', [$journey->collection, $journey]) }}" class="btn btn-sm btn-outline-secondary rounded-pill">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                @endcan
            </div>
        </div>
        <div class="d-flex align-items-center gap-2 mb-2">
            <h1 class="h3 fw-bold mb-0" style="color: var(--lj-ink); letter-spacing: -0.02em;">{{ $journey->title }}</h1>
            <span class="badge rounded-pill {{ $journey->is_published ? 'bg-success' : 'bg-secondary' }}">{{ $journey->is_published ? 'Published' : 'Draft' }}</span>
        </div>
        <p class="text-muted mb-0" style="max-width: 720px;">{{ $journey->description }}</p>
        <div class="d-flex flex-wrap gap-3 mt-3" style="font-size: 0.86rem; color: var(--lj-muted);">
            <span><i class="bi bi-clock me-1"></i>{{ $journey->estimated_duration }} min</span>
            <span><i class="bi bi-layers me-1"></i>{{ $stepsCount }} {{ Str::plural('step', $stepsCount) }}</span>
            <span><i class="bi bi-activity me-1"></i>{{ ucfirst($journey->difficulty_level ?? 'Custom') }}</span>
            <span><i class="bi bi-coin me-1"></i>{{ $journey->token_cost > 0 ? $journey->token_cost . ' tokens' : 'Free' }}</span>
            <span><i class="bi bi-calendar3 me-1"></i>Updated {{ $lastUpdated }}</span>
        </div>
    </header>

    <div class="glass-card">
        <section class="mb-4">
            <h5 class="fw-semibold mb-3" style="color: var(--lj-ink);">Journey details</h5>
            <div class="row g-3 text-muted">
                <div class="col-md-6"><strong>Collection:</strong> {{ $journey->collection->name }}</div>
                <div class="col-md-6"><strong>Institution:</strong> {{ $journey->collection->institution->name }}</div>
                <div class="col-md-6"><strong>Created by:</strong> {{ $journey->creator->name }}</div>
                <div class="col-md-6"><strong>Created:</strong> {{ $journey->created_at->format('M d, Y') }}</div>
            </div>
        </section>

        <section class="pt-3" style="border-top: 1px solid rgba(15,23,42,0.08);">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-semibold mb-0" style="color: var(--lj-ink);">Steps</h5>
                @can('update', $journey)
                    <a href="{{ route('journeys.steps.create', [$journey->collection, $journey]) }}" class="btn btn-sm btn-primary rounded-pill">
                        <i class="bi bi-plus-lg"></i> Add Step
                    </a>
                @endcan
            </div>

            @if($stepsCount > 0)
                <div id="journey-steps-sortable" class="list-group list-group-flush">
                    @foreach($journey->steps as $step)
                        <div class="list-group-item d-flex justify-content-between align-items-center" data-step-id="{{ $step->id }}">
                            <div class="d-flex align-items-center gap-3">
                                @can('update', $journey)
                                    <span class="text-muted" style="cursor: grab;">
                                        <i class="bi bi-grip-vertical"></i>
                                    </span>
                                @endcan
                                <div>
                                    {{ $step->title }}
                                    <div class="text-muted" style="font-size: 0.85rem;">{{ $step->type }}</div>
                                </div>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @can('update', $journey)
                                    <a href="{{ route('journeys.steps.edit', [$journey->collection, $journey, $step]) }}" class="btn btn-sm btn-outline-secondary rounded-pill">
                                        Edit
                                    </a>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger rounded-pill"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteStepModal"
                                        data-delete-url="{{ route('journeys.steps.destroy', [$journey->collection, $journey, $step]) }}"
                                        data-step-title="{{ $step->title }}">
                                        Delete
                                    </button>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4">
                    <i class="bi bi-list-ol display-6 text-muted"></i>
                    <h6 class="mt-3 text-muted">No steps yet</h6>
                    <p class="text-muted">Add steps to activate this journey.</p>
                    @can('update', $journey)
                        <a href="{{ route('journeys.steps.create', [$journey->collection, $journey]) }}" class="btn btn-primary rounded-pill">
                            <i class="bi bi-plus-lg"></i> Add the first step
                        </a>
                    @endcan
                </div>
            @endif
        </section>
    </div>
</div>

@can('update', $journey)
    <div class="modal fade" id="deleteStepModal" tabindex="-1" aria-labelledby="deleteStepModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteStepModalLabel">Delete Step</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the step "<span id="deleteStepTitle"></span>"?</p>
                    <p class="text-danger"><strong>This action cannot be undone.</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteStepForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete Step</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endcan
@endsection

@can('update', $journey)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('journey-steps-sortable');
    if (!container || typeof Sortable === 'undefined') return;

    Sortable.create(container, {
        handle: '.bi-grip-vertical',
        animation: 200,
        ghostClass: 'bg-light',
        onEnd: function () {
            const items = container.querySelectorAll('.list-group-item');
            const steps = [];

            items.forEach(function (item, index) {
                const order = index + 1;
                steps.push({ id: parseInt(item.dataset.stepId), order: order });
                const orderEl = item.querySelector('.step-order-num');
                if (orderEl) orderEl.textContent = order;
            });

            fetch("{{ route('journeys.steps.reorder', [$journey->collection, $journey]) }}", {
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

    const deleteStepModal = document.getElementById('deleteStepModal');
    if (deleteStepModal) {
        deleteStepModal.addEventListener('show.bs.modal', function (event) {
            const trigger = event.relatedTarget;
            if (!trigger) return;
            const deleteUrl = trigger.getAttribute('data-delete-url');
            const stepTitle = trigger.getAttribute('data-step-title') || 'this step';
            const form = document.getElementById('deleteStepForm');
            const titleEl = document.getElementById('deleteStepTitle');
            if (form && deleteUrl) form.action = deleteUrl;
            if (titleEl) titleEl.textContent = stepTitle;
        });
    }
});
</script>
@endpush
@endcan
