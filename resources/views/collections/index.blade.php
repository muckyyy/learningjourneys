@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-collection"></i> Journey Collections
                </h1>
                @can('create', App\Models\JourneyCollection::class)
                    <a href="{{ route('collections.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create Collection
                    </a>
                @endcan
            </div>

            @if($collections->count() > 0)
                <div class="row">
                    @foreach($collections as $collection)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title">{{ $collection->name }}</h5>
                                        @if($collection->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </div>
                                    
                                    <p class="card-text text-muted">
                                        {{ Str::limit($collection->description, 120) }}
                                    </p>

                                    <div class="text-muted small mb-3">
                                        <div class="mb-1">
                                            <i class="bi bi-building"></i> {{ $collection->institution->name }}
                                        </div>
                                        <div class="mb-1">
                                            <i class="bi bi-person"></i> {{ $collection->editor->name }}
                                        </div>
                                        <div>
                                            <i class="bi bi-map"></i> {{ $collection->journeys->count() }} {{ Str::plural('journey', $collection->journeys->count()) }}
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Updated {{ $collection->updated_at->diffForHumans() }}
                                        </small>
                                        <div>
                                            <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-primary btn-sm">
                                                View
                                            </a>
                                            @can('update', $collection)
                                                <a href="{{ route('collections.edit', $collection) }}" class="btn btn-outline-secondary btn-sm">
                                                    Edit
                                                </a>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center">
                    {{ $collections->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-collection display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No collections found</h3>
                    <p class="text-muted">Collections help organize journeys by institution and editor.</p>
                    @can('create', App\Models\JourneyCollection::class)
                        <a href="{{ route('collections.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Create Your First Collection
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
