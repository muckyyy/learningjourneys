@extends('layouts.app')

@section('content')
@php
    $journeyCount = $collection->journeys->count();
    $publishedCount = $collection->journeys->where('is_published', true)->count();
    $draftCount = $journeyCount - $publishedCount;
    $editorNames = $collection->editors->pluck('name')->implode(', ');
@endphp

<div class="shell" style="max-width: 900px;">

    {{-- Collection header --}}
    <header class="mb-4 pb-3" style="border-bottom: 1px solid rgba(15,23,42,0.08);">
        <div class="d-flex align-items-center gap-2 mb-2">
            <a href="{{ route('collections.index') }}" class="text-muted" style="font-size: 0.85rem; text-decoration: none;">
                <i class="bi bi-arrow-left"></i> Collections
            </a>
        </div>
        <h2 class="fw-bold mb-1" style="color: var(--lj-ink); letter-spacing: -0.02em;">{{ $collection->name }}</h2>
        @if($collection->description)
            <p class="text-muted mb-2" style="font-size: 0.95rem; line-height: 1.55;">{{ $collection->description }}</p>
        @endif
        <div class="d-flex flex-wrap align-items-center gap-3 mt-2" style="font-size: 0.88rem; color: var(--lj-muted);">
            <span><i class="bi bi-building me-1"></i>{{ $collection->institution->name }}</span>
            <span><i class="bi bi-people me-1"></i>{{ $editorNames ?: 'Unassigned' }}</span>
            <span><i class="bi bi-journal-text me-1"></i>{{ $journeyCount }} {{ Str::plural('journey', $journeyCount) }}</span>
            <span class="badge rounded-pill {{ $collection->is_active ? 'bg-success' : 'bg-secondary' }}" style="font-size: 0.75rem;">
                {{ $collection->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </header>

    <div class="glass-card">

    {{-- Toolbar --}}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-semibold mb-0" style="font-size: 1.05rem;">Journeys</h5>
        @can('create', App\Models\Journey::class)
            <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                <i class="bi bi-plus"></i> Add Journey
            </a>
        @endcan
    </div>

    {{-- Journey list --}}
    @if($journeyCount > 0)
        <div id="journey-sortable" class="d-flex flex-column gap-0">
            @foreach($collection->journeys as $index => $journey)
                <div class="d-flex align-items-start gap-3 py-3 journey-sort-item" data-id="{{ $journey->id }}" style="border-bottom: 1px solid rgba(15,23,42,0.06);">
                    {{-- Drag handle --}}
                    @can('update', $collection)
                        <div class="flex-shrink-0 d-flex align-items-center sort-handle" style="cursor: grab; padding: 4px; color: var(--lj-muted); font-size: 1rem;" title="Drag to reorder">
                            <i class="bi bi-grip-vertical"></i>
                        </div>
                    @endcan

                    {{-- Number --}}
                    <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-circle journey-sort-number"
                         style="width: 32px; height: 32px; background: var(--lj-brand-muted); color: var(--lj-brand-dark); font-weight: 700; font-size: 0.82rem;">
                        {{ $index + 1 }}
                    </div>

                    {{-- Content --}}
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <a href="{{ route('journeys.show', $journey) }}" class="fw-semibold text-decoration-none" style="color: var(--lj-ink); font-size: 1rem;">
                                {{ $journey->title }}
                            </a>
                            <span class="badge rounded-pill {{ $journey->is_published ? 'bg-success' : 'bg-warning text-dark' }}" style="font-size: 0.7rem;">
                                {{ $journey->is_published ? 'Published' : 'Draft' }}
                            </span>
                        </div>
                        @if($journey->description)
                            <p class="text-muted mb-1 mt-1" style="font-size: 0.88rem; line-height: 1.5;">
                                {{ \Illuminate\Support\Str::limit($journey->description, 140) }}
                            </p>
                        @endif
                        <div class="d-flex flex-wrap gap-3 mt-1" style="font-size: 0.8rem; color: var(--lj-muted);">
                            <span><i class="bi bi-clock me-1"></i>{{ $journey->estimated_duration }} min</span>
                            <span><i class="bi bi-layers me-1"></i>{{ $journey->steps()->count() }} steps</span>
                            <span><i class="bi bi-activity me-1"></i>{{ ucfirst($journey->difficulty_level ?? 'Custom') }}</span>
                            <span><i class="bi bi-person me-1"></i>{{ $journey->creator->name }}</span>
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex-shrink-0 d-flex gap-2 align-items-center">
                        <a href="{{ route('journeys.show', $journey) }}" class="btn btn-sm btn-outline-dark rounded-pill" style="font-size: 0.8rem;">View</a>
                        @can('update', $journey)
                            <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size: 0.8rem;">Edit</a>
                        @endcan
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-map text-muted" style="font-size: 2.5rem;"></i>
            <h6 class="mt-3 fw-semibold">No journeys yet</h6>
            <p class="text-muted mb-3" style="font-size: 0.9rem;">Add a journey to get this collection started.</p>
            @can('create', App\Models\Journey::class)
                <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-primary rounded-pill">
                    <i class="bi bi-plus-lg"></i> Create the first journey
                </a>
            @endcan
        </div>
    @endif

    </div>{{-- /glass-card --}}

</div>
@endsection

@can('update', $collection)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('journey-sortable');
    if (!container || typeof Sortable === 'undefined') return;

    Sortable.create(container, {
        handle: '.sort-handle',
        animation: 200,
        ghostClass: 'bg-light',
        onEnd: function () {
            // Renumber visible indices
            container.querySelectorAll('.journey-sort-number').forEach(function (el, i) {
                el.textContent = i + 1;
            });

            // Build payload
            const journeys = [];
            container.querySelectorAll('.journey-sort-item').forEach(function (el, i) {
                journeys.push({ id: parseInt(el.dataset.id), sort: i });
            });

            // Send to server
            fetch('{{ route('collections.reorder', $collection) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ journeys: journeys }),
            }).catch(function (err) {
                console.error('Reorder failed', err);
            });
        }
    });
});
</script>
@endpush
@endcan
