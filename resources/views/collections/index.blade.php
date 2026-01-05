@extends('layouts.app')

@push('styles')
<style>
.collections-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.collections-hero {
    background: linear-gradient(135deg, #0f172a, #22d3ee 70%);
    border-radius: 40px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(34, 211, 238, 0.35);
    margin-bottom: 2.5rem;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.collections-hero h1 {
    font-size: clamp(2rem, 4.4vw, 3.1rem);
    margin-bottom: 0.35rem;
}
.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin-top: 1.5rem;
}
.meta-card {
    background: rgba(15, 23, 42, 0.25);
    border-radius: 22px;
    padding: 0.85rem 1.4rem;
    min-width: 150px;
}
.meta-card span {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.7);
}
.meta-card strong {
    display: block;
    font-size: 1.4rem;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.9rem 1.8rem;
    font-weight: 600;
}
.collections-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}
.collection-card {
    border-radius: 30px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    padding: 2rem;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}
.collection-card .status-pill {
    padding: 0.2rem 0.9rem;
    border-radius: 999px;
    font-size: 0.75rem;
    text-transform: uppercase;
}
.collection-meta {
    display: grid;
    gap: 0.6rem;
    font-size: 0.9rem;
    color: #475569;
}
.collection-meta i {
    color: #0f172a;
}
.collection-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: auto;
}
.collection-actions small {
    color: #94a3b8;
}
.collection-actions .btn {
    border-radius: 999px;
    padding: 0.4rem 1.4rem;
}
.pagination-shell {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}
.empty-state {
    border-radius: 36px;
    border: 1px dashed rgba(148, 163, 184, 0.6);
    background: #f0fdff;
    padding: 4rem 2rem;
    text-align: center;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
}
</style>
@endpush

@section('content')
@php
    $totalCollections = method_exists($collections, 'total') ? $collections->total() : $collections->count();
    $visibleCollections = $collections->count();
    $activeCollections = $collections->where('is_active', true)->count();
    $journeyCount = $collections->sum(fn($collection) => $collection->journeys->count());
@endphp
<section class="collections-shell">
    <div class="collections-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-collection"></i> Collections</div>
            <h1>Organize journeys like product suites.</h1>
            <p class="mb-0">Curate journeys per institution, pair editors to launch timelines, and keep status clarity in one glass layer.</p>
            <div class="hero-meta">
                <div class="meta-card">
                    <span>Total catalog</span>
                    <strong>{{ number_format($totalCollections) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Active here</span>
                    <strong>{{ number_format($activeCollections) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Journeys linked</span>
                    <strong>{{ number_format($journeyCount) }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            @can('create', App\Models\JourneyCollection::class)
                <a href="{{ route('collections.create') }}" class="btn btn-light text-dark">
                    <i class="bi bi-plus-lg"></i> Create collection
                </a>
            @endcan
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light">
                <i class="bi bi-speedometer"></i> Dashboard
            </a>
        </div>
    </div>

    @if($collections->count() > 0)
        <div class="collections-grid">
            @foreach($collections as $collection)
                <article class="collection-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-uppercase text-muted small mb-1">{{ $collection->institution->name }}</p>
                            <h3 class="mb-0">{{ $collection->name }}</h3>
                        </div>
                        <span class="status-pill {{ $collection->is_active ? 'bg-success text-white' : 'bg-secondary text-white' }}">
                            {{ $collection->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="mb-0 text-muted">{{ Str::limit($collection->description, 140) }}</p>
                    <div class="collection-meta">
                        <div><i class="bi bi-person"></i> {{ $collection->editor->name }}</div>
                        <div>
                            <i class="bi bi-map"></i>
                            {{ $collection->journeys->count() }} {{ Str::plural('journey', $collection->journeys->count()) }}
                        </div>
                        <div><i class="bi bi-clock-history"></i> Updated {{ $collection->updated_at->diffForHumans() }}</div>
                    </div>
                    <div class="collection-actions">
                        <small>Created {{ $collection->created_at->format('M d, Y') }}</small>
                        <div class="d-flex gap-2">
                            <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-dark btn-sm">
                                View
                            </a>
                            @can('update', $collection)
                                <a href="{{ route('collections.edit', $collection) }}" class="btn btn-outline-secondary btn-sm">
                                    Edit
                                </a>
                            @endcan
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pagination-shell">
            {{ $collections->links() }}
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-collection display-4 text-muted"></i>
            <h3 class="mt-3">No collections yet</h3>
            <p class="text-muted">Collections bundle journeys per persona and institution. Spin up your first one to launch faster.</p>
            @can('create', App\Models\JourneyCollection::class)
                <a href="{{ route('collections.create') }}" class="btn btn-dark rounded-pill px-4">
                    <i class="bi bi-plus-lg"></i> Create collection
                </a>
            @endcan
        </div>
    @endif
</section>
@endsection
