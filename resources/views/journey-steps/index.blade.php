@extends('layouts.app')

@push('styles')
<style>
.journey-steps-shell {
    width: min(1200px, 100%);
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
}
.journey-steps-hero {
    background: linear-gradient(135deg, #0f172a 10%, #2563eb 50%, #38bdf8);
    border-radius: 36px;
    padding: clamp(2rem, 5vw, 4rem);
    color: #fff;
    box-shadow: 0 35px 70px rgba(15, 23, 42, 0.35);
    position: relative;
    overflow: hidden;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
}
.journey-steps-hero::after {
    content: "";
    position: absolute;
    inset: 18% auto auto 62%;
    width: 420px;
    height: 420px;
    background: radial-gradient(circle, rgba(255,255,255,0.32) 0%, transparent 60%);
    transform: translate(40%, -40%);
    pointer-events: none;
}
.hero-copy {
    position: relative;
    z-index: 1;
    flex: 1 1 360px;
}
.hero-pill {
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
.journey-steps-hero h1 {
    font-size: clamp(2rem, 4.5vw, 3.1rem);
    margin-top: 0.75rem;
    margin-bottom: 0.5rem;
}
.journey-steps-hero p {
    max-width: 560px;
    color: rgba(255,255,255,0.82);
    font-size: 1.05rem;
}
.hero-meta {
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
.journey-steps-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(260px, 320px);
    gap: clamp(1rem, 2vw, 1.75rem);
    margin-top: 2.5rem;
}
.steps-card {
    background: rgba(255,255,255,0.96);
    border-radius: 28px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    padding: clamp(1.5rem, 3vw, 2rem);
    box-shadow: 0 25px 45px rgba(15, 23, 42, 0.08);
}
.steps-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.steps-card-header h5 {
    margin-bottom: 0.25rem;
}
.step-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
.step-row {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 24px;
    padding: 1.1rem 1.25rem;
    display: flex;
    gap: 1rem;
    align-items: flex-start;
    background: #fff;
    box-shadow: 0 15px 30px rgba(15, 23, 42, 0.05);
}
.drag-handle {
    width: 44px;
    height: 44px;
    border-radius: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f1f5f9;
    flex-shrink: 0;
    cursor: grab;
}
.step-order-pill {
    width: 48px;
    height: 48px;
    border-radius: 18px;
    background: #2563eb;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    flex-shrink: 0;
}
.step-body {
    flex: 1;
}
.step-body h6 {
    margin-bottom: 0.35rem;
}
.badge-stack {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    margin-bottom: 0.5rem;
}
.badge-soft {
    border-radius: 999px;
    padding: 0.25rem 0.8rem;
    font-size: 0.8rem;
    background: #eef2ff;
    color: #4338ca;
}
.badge-soft.video { background: #fef2f2; color: #b91c1c; }
.badge-soft.quiz { background: #fffbeb; color: #92400e; }
.badge-soft.interactive { background: #ecfeff; color: #0f766e; }
.badge-soft.assignment { background: #fefce8; color: #854d0e; }
.badge-neutral {
    border-radius: 999px;
    padding: 0.25rem 0.75rem;
    font-size: 0.75rem;
    background: #f1f5f9;
    color: #475569;
}
.step-actions {
    display: flex;
    gap: 0.45rem;
    flex-shrink: 0;
}
.step-actions .btn {
    border-radius: 14px;
}
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
    border-radius: 28px;
    border: 1px dashed rgba(15, 23, 42, 0.15);
    background: #f8fafc;
}
.builder-aside {
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
    margin: 0;
    padding-left: 1.1rem;
}
.sticky-aside {
    position: sticky;
    top: 90px;
}
@media (max-width: 991.98px) {
    .journey-steps-grid {
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
        justify-content: center;
    }
    .step-row {
        flex-direction: column;
    }
    .step-actions {
        width: 100%;
        justify-content: flex-end;
    }
}
@media (max-width: 575.98px) {
    .journey-steps-shell {
        padding: 1.25rem;
    }
    .journey-steps-hero {
        border-radius: 24px;
    }
}
</style>
@endpush

@section('content')
@php
    $stepsCount = $steps->count();
    $requiredCount = $steps->where('is_required', true)->count();
    $avgDuration = $stepsCount ? round($steps->avg('time_limit')) : null;
    $journeyTitle = \Illuminate\Support\Str::limit($journey->title, 42);
@endphp

<div class="journey-steps-shell">
    <div class="journey-steps-hero">
        <div class="hero-copy">
            <div class="hero-pill"><i class="bi bi-diagram-3"></i> Sequence</div>
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
