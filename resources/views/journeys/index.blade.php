@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-map"></i> Learning Journeys
                </h1>
                @can('create', App\Models\Journey::class)
                    <a href="{{ route('journeys.create') }}" class="btn btn-primary w-100 w-md-auto">
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
                        <a href="{{ route('journeys.' . $activeAttempt->type, $activeAttempt) }}" class="btn btn-warning btn-sm">
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
                <div class="row g-3 g-md-4">
                    @foreach($journeys as $journey)
                        <div class="col-12 col-sm-6 col-lg-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title">{{ $journey->title }}</h5>
                                        <span class="badge bg-{{ $journey->difficulty_level === 'beginner' ? 'success' : ($journey->difficulty_level === 'intermediate' ? 'warning' : 'danger') }}">
                                            {{ ucfirst($journey->difficulty_level) }}
                                        </span>
                                    </div>
                                    
                                    <p class="card-text text-muted small mb-2">
                                        {{ Str::limit($journey->description, 100) }}
                                    </p>

                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center text-muted small mb-3 gap-1">
                                        <span><i class="bi bi-clock"></i> {{ $journey->estimated_duration }} min</span>
                                        <span><i class="bi bi-collection"></i> {{ $journey->collection->name }}</span>
                                    </div>

                                    @if($journey->tags)
                                        <div class="mb-3 d-flex flex-wrap gap-1">
                                            @foreach(explode(',', $journey->tags) as $tag)
                                                <span class="badge bg-light text-dark">{{ trim($tag) }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-grid d-md-flex gap-2">
                                             @can('update', $journey)
                                                <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-secondary btn-sm w-100 w-md-auto">
                                                    View
                                                </a>
                                                <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-outline-secondary btn-sm w-100 w-md-auto">
                                                    Edit
                                                </a>
                                            @else
                                                @if($journey->is_published && $journey->steps->count() > 0 && !$activeAttempt)
                                                    <button type="button" class="btn btn-success btn-sm w-100 w-md-auto" 
                                                            onclick="window.JourneyStartModal.showStartJourneyModal({{ $journey->id }}, '{{ addslashes($journey->title) }}', 'voice')">
                                                        <i class="bi bi-mic"></i> Start Voice
                                                    </button>
                                                @elseif($activeAttempt && $activeAttempt->journey_id === $journey->id)
                                                    <a href="{{ route('journeys.' . $activeAttempt->type, $activeAttempt) }}" class="btn btn-warning btn-sm w-100 w-md-auto">
                                                        <i class="bi bi-arrow-right-circle"></i> Continue
                                                    </a>
                                                @elseif($activeAttempt)
                                                    <div class="text-muted small">
                                                        <i class="bi bi-info-circle"></i> Complete your active journey first
                                                    </div>
                                                @else
                                                    <a href="{{ route('journeys.show', $journey) }}" class="btn btn-outline-secondary btn-sm w-100 w-md-auto">
                                                        View Details
                                                    </a>
                                                @endif
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
                <div class="d-flex justify-content-center mt-2">
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

<!-- Start Journey Confirmation Modal -->
<div class="modal fade" id="startJourneyModal" tabindex="-1" aria-labelledby="startJourneyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="startJourneyModalLabel">Start Learning Journey</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to start <strong id="journeyTypeText">chat</strong> journey for:</p>
                <h6 id="journeyTitleText">Journey Title</h6>
                <p class="text-muted">This will create a new learning session and you can track your progress.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmStartJourney">
                    <span class="spinner-border spinner-border-sm d-none" id="startJourneySpinner" role="status" aria-hidden="true"></span>
                    <span id="startJourneyText">Yes, Start Journey</span>
                </button>
            </div>
        </div>
    </div>
</div>

@endsection
