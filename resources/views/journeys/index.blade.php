@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-map"></i> Learning Journeys
                </h1>
                @can('create', App\Models\Journey::class)
                    <a href="{{ route('journeys.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create Journey
                    </a>
                @endcan
            </div>

            @if(Auth::user()->role === 'regular' && isset($activeAttempt) && $activeAttempt)
                <div class="alert alert-warning mb-4" role="alert">
                    <h5 class="alert-heading">
                        <i class="bi bi-exclamation-triangle"></i> Active Journey in Progress
                    </h5>
                    <p class="mb-2">
                        You currently have an active journey: <strong>{{ $activeAttempt->journey->title }}</strong>
                    </p>
                    <p class="mb-3">
                        You must complete or abandon your current journey before starting a new one.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-warning btn-sm">
                            <i class="bi bi-arrow-right-circle"></i> Continue Active Journey
                        </a>
                        <form action="{{ route('dashboard.journey.abandon', $activeAttempt) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm" 
                                    onclick="return confirm('Are you sure you want to abandon your current journey? Your progress will be lost.')">
                                <i class="bi bi-x-circle"></i> Abandon Current Journey
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            @if($journeys->count() > 0)
                <div class="row">
                    @foreach($journeys as $journey)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title">{{ $journey->title }}</h5>
                                        <span class="badge bg-{{ $journey->difficulty_level === 'beginner' ? 'success' : ($journey->difficulty_level === 'intermediate' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($journey->difficulty_level) }}
                                        </span>
                                    </div>
                                    
                                    <p class="card-text text-muted small">
                                        {{ Str::limit($journey->description, 100) }}
                                    </p>

                                    <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                                        <span>
                                            <i class="bi bi-clock"></i> {{ $journey->estimated_duration }} min
                                        </span>
                                        <span>
                                            <i class="bi bi-collection"></i> {{ $journey->collection->name }}
                                        </span>
                                    </div>

                                    @if($journey->tags)
                                        <div class="mb-3">
                                            @foreach(explode(',', $journey->tags) as $tag)
                                                <span class="badge bg-light text-dark me-1">{{ trim($tag) }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            By {{ $journey->creator->name }}
                                        </small>
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
                                
                                @if(!$journey->is_published)
                                    <div class="card-footer bg-warning bg-opacity-10">
                                        <small class="text-warning">
                                            <i class="bi bi-eye-slash"></i> Draft
                                        </small>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center">
                    {{ $journeys->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-map display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No journeys found</h3>
                    <p class="text-muted">Start creating learning journeys to see them here.</p>
                    @can('create', App\Models\Journey::class)
                        <a href="{{ route('journeys.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Create Your First Journey
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
