@extends('layouts.app')

@push('styles')
<style>
    .collection-shell {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1.5rem clamp(1rem, 3vw, 2rem) 3rem;
        color: #0f172a;
    }
    .collection-hero {
        background: radial-gradient(circle at 10% 20%, rgba(59,130,246,.25), rgba(15,23,42,.9));
        border-radius: 32px;
        padding: clamp(1.75rem, 3vw, 2.75rem);
        color: #fff;
        position: relative;
        overflow: hidden;
        box-shadow: 0 25px 60px rgba(15, 23, 42, 0.25);
    }
    .collection-hero::after {
        content: "";
        position: absolute;
        inset: 0;
        background: radial-gradient(circle at 80% -10%, rgba(236,72,153,.4), transparent 45%);
        pointer-events: none;
    }
    .hero-content {
        position: relative;
        z-index: 2;
    }
    .hero-top {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.25rem;
    }
    .ghost-link {
        text-transform: uppercase;
        letter-spacing: 0.2em;
        font-size: 0.78rem;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
    }
    .hero-title {
        font-size: clamp(1.75rem, 4vw, 2.75rem);
        font-weight: 600;
        margin: 0;
    }
    .accent-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        font-size: 0.9rem;
        background: rgba(255,255,255,0.18);
        backdrop-filter: blur(8px);
    }
    .collection-stats {
        margin-top: 1.5rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px,1fr));
        gap: 1rem;
    }
    .collection-stat {
        background: rgba(15,23,42,0.35);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 16px;
        padding: 1rem;
    }
    .collection-stat small {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-size: 0.75rem;
        color: rgba(255,255,255,0.7);
    }
    .collection-stat h3 {
        margin: 0.5rem 0 0;
        font-size: 1.75rem;
    }
    .collection-grid {
        margin-top: 2.5rem;
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(280px, 320px);
        gap: 1.75rem;
    }
    .collection-main {
        display: flex;
        flex-direction: column;
        gap: 1.75rem;
    }
    .collection-panel, .info-card {
        background: #fff;
        border-radius: 28px;
        border: 1px solid rgba(15,23,42,0.08);
        padding: clamp(1.5rem, 2.5vw, 2rem);
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
    }
    .collection-panel h5 {
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .collection-meta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
        gap: 1rem;
        margin-top: 1rem;
    }
    .meta-pill {
        border: 1px solid rgba(15,23,42,0.08);
        border-radius: 18px;
        padding: 0.85rem 1rem;
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }
    .meta-pill small {
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        font-weight: 600;
    }
    .journey-toolbar {
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    .journey-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.25rem;
    }
    .journey-card {
        border: 1px solid rgba(15,23,42,0.08);
        border-radius: 20px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        gap: 0.85rem;
        min-height: 220px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .journey-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 15px 30px rgba(15, 23, 42, 0.12);
    }
    .journey-card-header {
        display: flex;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .journey-meta {
        display: flex;
        justify-content: space-between;
        font-size: 0.92rem;
        color: #475569;
    }
    .journey-actions {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.75rem;
    }
    .status-chip {
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        font-size: 0.82rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border: 1px solid transparent;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }
    .status-chip.published {
        background: rgba(16,185,129,0.12);
        color: #0f5132;
        border-color: rgba(16,185,129,0.35);
    }
    .status-chip.draft {
        background: rgba(234,179,8,0.12);
        color: #854d0e;
        border-color: rgba(234,179,8,0.35);
    }
    .collection-aside {
        position: relative;
    }
    .sticky-stack {
        position: sticky;
        top: 88px;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }
    .info-card.light {
        background: #f8fafc;
        border-color: rgba(100,116,139,0.15);
    }
    .info-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
        color: #475569;
        font-size: 0.95rem;
    }
    .info-list li {
        display: flex;
        gap: 0.5rem;
        align-items: baseline;
    }
    .empty-state {
        text-align: center;
        padding: 3rem 1.5rem;
        border-radius: 20px;
        background: rgba(248,250,252,0.9);
        border: 1px dashed rgba(99,102,241,0.35);
    }
    .badge-soft {
        border-radius: 999px;
        padding: 0.35rem 0.75rem;
        background: rgba(255,255,255,0.2);
        color: inherit;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }
    @media (max-width: 991.98px) {
        .collection-shell {
            padding-bottom: 5rem;
        }
        .collection-grid {
            grid-template-columns: 1fr;
        }
        .sticky-stack {
            position: static;
        }
        .journey-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@section('content')
@php
    $journeyCount = $collection->journeys->count();
    $publishedCount = $collection->journeys->where('is_published', true)->count();
    $draftCount = $journeyCount - $publishedCount;
    $totalAttempts = $collection->journeys->sum(fn($journey) => $journey->attempts()->count());
    $completedAttempts = $collection->journeys->sum(fn($journey) => $journey->attempts()->where('status', 'completed')->count());
    $completionRate = $totalAttempts > 0 ? round(($completedAttempts / max($totalAttempts, 1)) * 100, 1) : null;
@endphp

<div class="collection-shell">
    <div class="collection-hero">
        <div class="hero-content">
            <div class="hero-top">
                <div>
                    <a href="{{ route('collections.index') }}" class="ghost-link d-inline-flex align-items-center gap-2">
                        <i class="bi bi-arrow-left"></i> Collections
                    </a>
                    <div class="d-flex align-items-center flex-wrap gap-2 mt-3">
                        <h1 class="hero-title mb-0">{{ $collection->name }}</h1>
                        <span class="accent-pill">
                            <i class="bi bi-toggle-{{ $collection->is_active ? 'on' : 'off' }}"></i>
                            {{ $collection->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @can('update', $collection)
                        <a href="{{ route('collections.edit', $collection) }}" class="btn btn-light text-dark rounded-pill">
                            <i class="bi bi-pencil"></i> Edit Collection
                        </a>
                    @endcan
                    @can('create', App\Models\Journey::class)
                        <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-primary rounded-pill">
                            <i class="bi bi-plus-lg"></i> New Journey
                        </a>
                    @endcan
                </div>
            </div>
            <p class="lead text-white-75 mb-0" style="max-width: 680px;">
                {{ $collection->description ?: 'No description yet. Use this space to inspire editors and institutions.' }}
            </p>
            <div class="collection-stats">
                <div class="collection-stat">
                    <small>Journeys</small>
                    <h3>{{ $journeyCount }}</h3>
                    <span class="badge-soft"><i class="bi bi-check2-circle"></i> {{ $publishedCount }} published</span>
                </div>
                <div class="collection-stat">
                    <small>Drafts</small>
                    <h3>{{ $draftCount }}</h3>
                    <span class="badge-soft"><i class="bi bi-lightning-charge"></i> {{ max($draftCount, 0) }} in progress</span>
                </div>
                <div class="collection-stat">
                    <small>Updated</small>
                    <h3>{{ $collection->updated_at->diffForHumans() }}</h3>
                    <span class="badge-soft"><i class="bi bi-calendar3"></i> Created {{ $collection->created_at->format('M d, Y') }}</span>
                </div>
                <div class="collection-stat">
                    <small>Completion</small>
                    <h3>{{ $completionRate !== null ? $completionRate.'%' : '—' }}</h3>
                    <span class="badge-soft"><i class="bi bi-graph-up"></i> {{ $completedAttempts }} of {{ $totalAttempts }} attempts</span>
                </div>
            </div>
        </div>
    </div>

    <div class="collection-grid">
        <section class="collection-main">
            <div class="collection-panel">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h5 class="mb-0">Collection Blueprint</h5>
                    <span class="accent-pill text-dark bg-light"><i class="bi bi-building"></i> {{ $collection->institution->name }}</span>
                </div>
                <p class="text-muted mb-0">Curated by {{ $collection->editor->name }} · {{ $journeyCount }} journeys crafted for {{ $collection->institution->name }} learners.</p>
                <div class="collection-meta-grid">
                    <div class="meta-pill">
                        <small>Institution</small>
                        <span>{{ $collection->institution->name }}</span>
                    </div>
                    <div class="meta-pill">
                        <small>Lead Editor</small>
                        <span>{{ $collection->editor->name }}</span>
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
                        <li><span class="text-muted">Lead Editor</span><strong>{{ $collection->editor->name }}</strong></li>
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
