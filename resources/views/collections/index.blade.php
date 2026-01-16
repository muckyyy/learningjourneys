@extends('layouts.app')

@section('content')
@php
    $totalCollections = method_exists($collections, 'total') ? $collections->total() : $collections->count();
    $visibleCollections = $collections->count();
    $activeCollections = $collections->where('is_active', true)->count();
    $journeyCount = $collections->sum(fn($collection) => $collection->journeys->count());
@endphp
<section class="shell">
    <div class="hero cyan">
        <div class="flex-grow-1">
            <div class="pill light mb-3"><i class="bi bi-collection"></i> Collections</div>
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
                        <div><i class="bi bi-people"></i> {{ $collection->editors->pluck('name')->implode(', ') ?: 'Pending editors' }}</div>
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
