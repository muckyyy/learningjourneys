@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">{{ $collection->name }}</h1>
                <div>
                    <a href="{{ route('collections.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Collections
                    </a>
                    @can('update', $collection)
                        <a href="{{ route('collections.edit', $collection) }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    @endcan
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Collection Info -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    @if($collection->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </div>
                                <small class="text-muted">
                                    Updated {{ $collection->updated_at->diffForHumans() }}
                                </small>
                            </div>

                            <p class="lead">{{ $collection->description }}</p>

                            <div class="row text-muted small">
                                <div class="col-md-6">
                                    <strong>Institution:</strong> {{ $collection->institution->name }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Editor:</strong> {{ $collection->editor->name }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Journeys:</strong> {{ $collection->journeys->count() }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Created:</strong> {{ $collection->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Journeys in Collection -->
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-map"></i> Journeys ({{ $collection->journeys->count() }})
                            </h5>
                            @can('create', App\Models\Journey::class)
                                <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Add Journey
                                </a>
                            @endcan
                        </div>
                        <div class="card-body">
                            @if($collection->journeys->count() > 0)
                                <div class="row">
                                    @foreach($collection->journeys as $journey)
                                        <div class="col-md-6 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title">{{ $journey->title }}</h6>
                                                        <span class="badge bg-{{ $journey->difficulty_level === 'beginner' ? 'success' : ($journey->difficulty_level === 'intermediate' ? 'warning' : 'danger') }}">
                                                            {{ ucfirst($journey->difficulty_level) }}
                                                        </span>
                                                    </div>
                                                    
                                                    <p class="card-text text-muted small">
                                                        {{ Str::limit($journey->description, 80) }}
                                                    </p>

                                                    <div class="d-flex justify-content-between align-items-center text-muted small mb-2">
                                                        <span>
                                                            <i class="bi bi-clock"></i> {{ $journey->estimated_duration }} min
                                                        </span>
                                                        <span>
                                                            <i class="bi bi-person"></i> {{ $journey->creator->name }}
                                                        </span>
                                                    </div>

                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            @if($journey->is_published)
                                                                <span class="badge bg-success">Published</span>
                                                            @else
                                                                <span class="badge bg-warning">Draft</span>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-primary btn-sm">
                                                                View
                                                            </a>
                                                            @can('update', $journey)
                                                                <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-outline-secondary btn-sm">
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
                            @else
                                <div class="text-center py-5">
                                    <i class="bi bi-map display-4 text-muted"></i>
                                    <h5 class="mt-3 text-muted">No Journeys Yet</h5>
                                    <p class="text-muted">This collection doesn't have any journeys yet.</p>
                                    @can('create', App\Models\Journey::class)
                                        <a href="{{ route('journeys.create', ['collection' => $collection->id]) }}" class="btn btn-primary">
                                            <i class="bi bi-plus-lg"></i> Create First Journey
                                        </a>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Collection Statistics -->
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart"></i> Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-primary mb-0">{{ $collection->journeys->count() }}</h4>
                                        <small class="text-muted">Total Journeys</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success mb-0">{{ $collection->journeys->where('is_published', true)->count() }}</h4>
                                    <small class="text-muted">Published</small>
                                </div>
                            </div>

                            @php
                                $totalAttempts = $collection->journeys->sum(function($journey) {
                                    return $journey->attempts()->count();
                                });
                                $completedAttempts = $collection->journeys->sum(function($journey) {
                                    return $journey->attempts()->where('status', 'completed')->count();
                                });
                            @endphp

                            <hr>

                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-info mb-0">{{ $totalAttempts }}</h4>
                                        <small class="text-muted">Total Attempts</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-warning mb-0">{{ $completedAttempts }}</h4>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>

                            @if($totalAttempts > 0)
                                @php
                                    $completionRate = round(($completedAttempts / $totalAttempts) * 100, 1);
                                @endphp
                                <div class="mt-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small>Completion Rate</small>
                                        <small>{{ $completionRate }}%</small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: {{ $completionRate }}%"></div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
