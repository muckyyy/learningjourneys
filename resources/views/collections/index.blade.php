@extends('layouts.app')

@section('content')
@php
    $totalCollections = method_exists($collections, 'total') ? $collections->total() : $collections->count();
    $visibleCollections = $collections->count();
    $activeCollections = $collections->where('is_active', true)->count();
    $journeyCount = $collections->sum(fn($collection) => $collection->journeys->count());
@endphp
<section class="shell">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Collections</h1>
        @can('create', App\Models\JourneyCollection::class)
            <a href="{{ route('collections.create') }}" class="btn btn-dark rounded-pill px-4">
                <i class="bi bi-plus-lg"></i> Create collection
            </a>
        @endcan
    </div>

    @if($collections->count() > 0)
        <div class="collections-grid">
            @foreach($collections as $collection)
                @php
                    $jCount = $collection->journeys->count();
                    $editorNames = $collection->editors->pluck('name')->implode(', ');
                @endphp
                <article class="collection-card">
                    <div class="collection-card-header">
                        <span class="collection-card-institution">{{ $collection->institution->name ?? 'No institution' }}</span>
                        <span class="status-pill {{ $collection->is_active ? 'active' : 'inactive' }}">
                            {{ $collection->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <h3 class="collection-card-title">{{ $collection->name }}</h3>

                    @if($collection->description)
                        <p class="collection-card-desc">{{ Str::limit($collection->description, 120) }}</p>
                    @endif

                    <div class="collection-meta">
                        <div class="collection-meta-item">
                            <i class="bi bi-map"></i>
                            <span>{{ $jCount }} {{ Str::plural('journey', $jCount) }}</span>
                        </div>
                        <div class="collection-meta-item">
                            <i class="bi bi-pencil-square"></i>
                            <span>{{ $editorNames ?: 'No editors' }}</span>
                        </div>
                        <div class="collection-meta-item">
                            <i class="bi bi-clock-history"></i>
                            <span>{{ $collection->updated_at->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="collection-actions">
                        <small>Created {{ $collection->created_at->format('M d, Y') }}</small>
                        <div class="d-flex gap-2">
                            <a href="{{ route('collections.show', $collection) }}" class="btn btn-dark btn-sm">
                                <i class="bi bi-arrow-right"></i> View
                            </a>
                            @can('update', $collection)
                                <a href="{{ route('collections.edit', $collection) }}" class="btn btn-outline-secondary btn-sm">
                                    <i class="bi bi-pencil"></i> Edit
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
        <div class="text-center py-5">
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:96px;height:96px;">
                <i class="bi bi-collection text-muted fs-2"></i>
            </div>
            <h3 class="fw-bold">No collections yet</h3>
            <p class="text-muted mb-4">Collections bundle journeys per persona and institution. Spin up your first one to launch faster.</p>
            @can('create', App\Models\JourneyCollection::class)
                <a href="{{ route('collections.create') }}" class="btn btn-dark rounded-pill px-4">
                    <i class="bi bi-plus-lg"></i> Create collection
                </a>
            @endcan
        </div>
    @endif
</section>
@endsection
