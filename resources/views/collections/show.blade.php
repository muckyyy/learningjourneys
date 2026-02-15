@extends('layouts.app')

@section('content')
@php
    $journeyCount = $collection->journeys->count();
    $publishedCount = $collection->journeys->where('is_published', true)->count();
    $draftCount = $journeyCount - $publishedCount;
    $totalAttempts = $collection->journeys->sum(fn($journey) => $journey->attempts()->count());
    $completedAttempts = $collection->journeys->sum(fn($journey) => $journey->attempts()->where('status', 'completed')->count());
    $completionRate = $totalAttempts > 0 ? round(($completedAttempts / max($totalAttempts, 1)) * 100, 1) : null;
@endphp

<div class="shell">

    <div class="collection-grid">
        <section class="collection-main">
            <div class="collection-panel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h5 class="mb-0">Collection Blueprint</h5>
                    <span class="accent-pill text-dark bg-light"><i class="bi bi-building"></i> {{ $collection->institution->name }}</span>
                </div>
                @php($editorNames = $collection->editors->pluck('name')->implode(', '))
                <p class="text-muted mb-0">Curated by {{ $editorNames ?: 'Unassigned editors' }} · {{ $journeyCount }} journeys crafted for {{ $collection->institution->name }} learners.</p>
                <div class="collection-meta-grid">
                    <div class="meta-pill">
                        <small>Institution</small>
                        <span>{{ $collection->institution->name }}</span>
                    </div>
                    <div class="meta-pill">
                        <small>Editors</small>
                        <span>{{ $editorNames ?: 'Pending assignment' }}</span>
                    </div>
                    <div class="meta-pill">
                        <small>Visibility</small>
                        <span>{{ $collection->is_active ? 'Available to builders' : 'Hidden from builders' }}</span>
                    </div>
                    <div class="meta-pill">
                        <small>Created</small>
                        <span>{{ $collection->created_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>

            <div class="collection-panel">
                <div class="journey-toolbar">
                    <div>
                        <h5 class="mb-1">Journeys in this collection</h5>
                        <p class="text-muted mb-0">Mix reflection, practice, and assessment moments for a cinematic learner arc.</p>
                    </div>
                    @can('create', App\Models\Journey::class)
                        <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-outline-primary rounded-pill">
                            <i class="bi bi-plus"></i> Add Journey
                        </a>
                    @endcan
                </div>

                @if($journeyCount > 0)
                    <div class="journey-grid">
                        @foreach($collection->journeys as $journey)
                            <div class="journey-card">
                                <div class="journey-card-header">
                                    <div>
                                        <h6 class="mb-1">{{ $journey->title }}</h6>
                                        <span class="badge rounded-pill bg-light text-dark fw-semibold">
                                            <i class="bi bi-activity"></i> {{ ucfirst($journey->difficulty_level ?? 'custom') }}
                                        </span>
                                    </div>
                                    <span class="status-chip {{ $journey->is_published ? 'published' : 'draft' }}">
                                        <i class="bi {{ $journey->is_published ? 'bi-check-circle' : 'bi-pencil' }}"></i>
                                        {{ $journey->is_published ? 'Published' : 'Draft' }}
                                    </span>
                                </div>
                                <p class="text-muted mb-0">{{ \Illuminate\Support\Str::limit($journey->description, 120) }}</p>
                                <div class="journey-meta">
                                    <span><i class="bi bi-clock"></i> {{ $journey->estimated_duration }} min</span>
                                    <span><i class="bi bi-person"></i> {{ $journey->creator->name }}</span>
                                </div>
                                <div class="journey-actions">
                                    <div class="d-flex flex-wrap gap-2">
                                        <span class="badge rounded-pill text-bg-light">{{ $journey->steps()->count() }} steps</span>
                                        <span class="badge rounded-pill text-bg-light">{{ $journey->attempts()->count() }} attempts</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('journeys.show', $journey) }}" class="btn btn-sm btn-outline-dark rounded-pill">View</a>
                                        @can('update', $journey)
                                            <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-sm btn-outline-secondary rounded-pill">Edit</a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state">
                        <i class="bi bi-map text-primary display-5"></i>
                        <h5 class="mt-3">No journeys yet</h5>
                        <p class="text-muted mb-3">Pair two or three signature experiences to set the tone for this institution.</p>
                        @can('create', App\Models\Journey::class)
                            <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-primary rounded-pill">
                                <i class="bi bi-plus-lg"></i> Create the first journey
                            </a>
                        @endcan
                    </div>
                @endif
            </div>
        </section>

        <aside class="collection-aside">
            <div class="sticky-stack">
                <div class="info-card">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Collection Snapshot</h6>
                        <span class="badge-soft text-dark"><i class="bi bi-eye"></i> Overview</span>
                    </div>
                    <ul class="info-list">
                        <li><span class="text-muted">Institution</span><strong>{{ $collection->institution->name }}</strong></li>
                        <li><span class="text-muted">Editors</span><strong>{{ $editorNames ?: 'Pending assignment' }}</strong></li>
                        <li><span class="text-muted">Audience</span><strong>{{ ucfirst($collection->audience ?? 'General') }}</strong></li>
                        <li><span class="text-muted">Status</span><strong>{{ $collection->is_active ? 'Active' : 'Inactive' }}</strong></li>
                    </ul>
                </div>

                <div class="info-card light">
                    <h6>Momentum</h6>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div>
                            <div class="h3 mb-0">{{ $totalAttempts }}</div>
                            <small class="text-muted">Learner attempts</small>
                        </div>
                        <span class="status-chip published">
                            <i class="bi bi-graph-up-arrow"></i>
                            {{ $completionRate !== null ? $completionRate.'%' : '—' }}
                        </span>
                    </div>
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: {{ $completionRate ?? 0 }}%;"></div>
                    </div>
                    <p class="text-muted small mt-3">Completion rate updates every two hours. Use this to prioritize the next sprint with your editors.</p>
                </div>

                <div class="info-card light">
                    <h6>Quick Actions</h6>
                    <div class="d-grid gap-2">
                        <a href="{{ route('journeys.index') }}" class="btn btn-outline-secondary rounded-pill w-100">
                            <i class="bi bi-grid"></i> Manage journeys
                        </a>
                        <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-outline-primary rounded-pill w-100">
                            <i class="bi bi-stars"></i> Launch AI brief
                        </a>
                    </div>
                    <p class="text-muted small mt-3">Pro tip: Alternate reflection and creation steps, then cap at four per arc for mobile attention.</p>
                </div>
            </div>
        </aside>
    </div>
</div>
@endsection
