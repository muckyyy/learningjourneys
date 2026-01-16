@extends('layouts.app')

@section('content')
@php
    $stepsCount = $steps->count();
    $requiredCount = $steps->where('is_required', true)->count();
    $avgDuration = $stepsCount ? round($steps->avg('time_limit')) : null;
    $journeyTitle = \Illuminate\Support\Str::limit($journey->title, 42);
@endphp

<div class="shell">
    <div class="hero cyan">
        <div class="hero-content">
            <div class="pill light mb-3"><i class="bi bi-diagram-3"></i> Sequence</div>
            <h1 class="fw-bold">Curate the {{ $journeyTitle }} Flow</h1>
            <p>Keep every interaction intentional. Reorder, refine, or duplicate segments so this journey feels as polished as our flagship experiences.</p>
            <div class="hero-meta">
                <div class="hero-stat">
                    <span>Total Steps</span>
                    <strong>{{ number_format($stepsCount) }}</strong>
                </div>
                <div class="hero-stat">
                    <span>Required</span>
                    <strong>{{ number_format($requiredCount) }}</strong>
                </div>
                <div class="hero-stat">
                    <span>Avg Duration</span>
                    <strong>{{ $avgDuration ? $avgDuration . ' min' : '—' }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-light"><i class="bi bi-arrow-left"></i> Back to Journey</a>
            <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-light text-dark"><i class="bi bi-plus-lg"></i> Add Step</a>
        </div>
    </div>

    <div class="journey-steps-grid">
        <section class="steps-card">
            <div class="steps-card-header">
                <div>
                    <h5 class="mb-1">Live Steps</h5>
                    <p class="text-muted mb-0">Drag to reorder. Changes save instantly.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-primary rounded-pill"><i class="bi bi-plus-lg"></i> Add Step</a>
                    <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-outline-secondary rounded-pill d-none d-md-inline-flex"><i class="bi bi-lightning"></i> Quick Add</a>
                </div>
            </div>

            @if($stepsCount)
                <div id="steps-container" class="step-list">
                    @foreach($steps as $step)
                        <div class="step-row" data-step-id="{{ $step->id }}">
                            <div class="drag-handle">
                                <i class="bi bi-grip-vertical"></i>
                            </div>
                            <div class="step-order-pill">{{ $step->order }}</div>
                            <div class="step-body">
                                <h6 class="fw-semibold mb-1">{{ $step->title }}</h6>
                                <div class="badge-stack">
                                    <span class="badge-soft {{ $step->type }}">{{ ucfirst($step->type) }}</span>
                                    @if($step->is_required)
                                        <span class="badge-neutral"><i class="bi bi-lock"></i> Required</span>
                                    @endif
                                    @if($step->time_limit)
                                        <span class="badge-neutral"><i class="bi bi-stopwatch"></i> {{ $step->time_limit }} min</span>
                                    @endif
                                    <span class="badge-neutral"><i class="bi bi-check2"></i> Rate {{ $step->ratepass }}/5</span>
                                    <span class="badge-neutral"><i class="bi bi-arrow-repeat"></i> {{ $step->maxattempts }} attempts</span>
                                </div>
                                <p class="text-muted mb-0">{{ \Illuminate\Support\Str::limit(strip_tags($step->content), 140) }}</p>
                            </div>
                            <div class="step-actions">
                                <a href="{{ route('journeys.steps.show', [$journey, $step]) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-eye"></i></a>
                                <a href="{{ route('journeys.steps.edit', [$journey, $step]) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
                                <button type="button" class="btn btn-outline-danger btn-sm" data-step-id="{{ $step->id }}" data-step-title="{{ $step->title }}" onclick="deleteStep(this.dataset.stepId, this.dataset.stepTitle)"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-4 p-3 rounded-3 bg-light d-flex align-items-center gap-2">
                    <i class="bi bi-info-circle text-primary"></i>
                    <small class="text-muted">Hold the grip icon to reorder. Auto-save keeps parity across devices.</small>
                </div>
            @else
                <div class="empty-state">
                    <i class="bi bi-columns-gap display-5 text-muted"></i>
                    <h4 class="mt-3">No Steps Yet</h4>
                    <p class="text-muted">Start layering reflections, practice, and feedback loops to bring this journey alive.</p>
                    <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-primary rounded-pill"><i class="bi bi-plus-lg"></i> Add First Step</a>
                </div>
            @endif
        </section>

        <aside class="builder-aside sticky-aside">
            <div class="info-card">
                <h6 class="text-white">Journey Snapshot</h6>
                <p class="text-white-75 mb-2">{{ $journey->title }}</p>
                <ul class="mb-3">
                    <li>{{ $stepsCount }} total steps</li>
                    <li>{{ $requiredCount }} locked steps</li>
                    <li>{{ ucfirst($journey->difficulty_level ?? 'custom') }} difficulty</li>
                </ul>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-sm btn-outline-light rounded-pill"><i class="bi bi-eye"></i> Preview</a>
                    <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-sm btn-light text-dark rounded-pill"><i class="bi bi-plus"></i> Add Step</a>
                </div>
            </div>
            <div class="info-card light-card">
                <h6>Sequencing Tips</h6>
                <ul>
                    <li>Alternate reflection and action to keep energy high.</li>
                    <li>Cap voice steps at 3–4 min for mobile attention.</li>
                    <li>Use quiz JSON to unlock branching moments.</li>
                </ul>
            </div>
            <div class="info-card light-card">
                <h6>Prompt Shortcuts</h6>
                <p class="mb-2">Use variables for instant personalization:</p>
                <ul>
                    <li><code>{current_step}</code> or <code>{next_step}</code></li>
                    <li><code>{journey_title}</code> + <code>{journey_description}</code></li>
                    <li><code>{student_name}</code> + <code>{institution_name}</code></li>
                </ul>
                <small class="text-muted">Combine with the Step Builder defaults for consistent tone.</small>
            </div>
        </aside>
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

@push('scripts')
<script>
(function() {
    function deleteStep(stepId, title) {
        document.getElementById('stepTitle').textContent = title;
        document.getElementById('deleteStepForm').action = `{{ route('journeys.steps.index', $journey) }}/${stepId}`;
        new bootstrap.Modal(document.getElementById('deleteStepModal')).show();
    }
    window.deleteStep = deleteStep;

    function initSortable() {
        const container = document.getElementById('steps-container');
        if (!container) {
            return;
        }
        if (typeof Sortable === 'undefined') {
            console.error('SortableJS was not bundled; ensure resources/js/app.js requires it.');
            return;
        }

        new Sortable(container, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: function() {
                const steps = [];
                container.querySelectorAll('.step-row').forEach((item, index) => {
                    steps.push({ id: parseInt(item.dataset.stepId, 10), order: index + 1 });
                });

                container.querySelectorAll('.step-order-pill').forEach((pill, index) => {
                    pill.textContent = index + 1;
                });

                fetch(`{{ route('journeys.steps.reorder', $journey) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ steps })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initSortable, { once: true });
    } else {
        initSortable();
    }
})();
</script>
@endpush
@endsection
