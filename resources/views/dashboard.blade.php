@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-12">
        <h1 class="h3 mb-4">Dashboard</h1>
    </div>
</div>

@if($user->role === 'regular')
    {{-- Active Journey Section --}}
    @if($activeAttempt)
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-play-circle"></i> Active Journey: {{ $activeAttempt->journey->title }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p class="card-text">{{ $activeAttempt->journey->description }}</p>
                                <div class="d-flex gap-2 mb-3">
                                    <span class="badge bg-info">Step {{ $activeAttempt->current_step }} of {{ $activeAttempt->journey->steps->count() }}</span>
                                    <span class="badge bg-secondary">{{ ucfirst($activeAttempt->journey->difficulty_level) }}</span>
                                    <span class="badge bg-warning">{{ $activeAttempt->journey->estimated_duration }} min</span>
                                </div>
                                
                                {{-- Progress Bar --}}
                                @php
                                    $progress = $activeAttempt->journey->steps->count() > 0 
                                        ? ($activeAttempt->current_step / $activeAttempt->journey->steps->count()) * 100 
                                        : 0;
                                @endphp
                                <div class="progress mb-3">
                                    <div class="progress-bar" role="progressbar" style="width: {{ $progress }}%" 
                                         aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100">
                                        {{ number_format($progress, 0) }}%
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid gap-2">
                                    <form action="{{ route('dashboard.journey.complete', $activeAttempt) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-success w-100" 
                                                onclick="return confirm('Are you sure you want to complete this journey?')">
                                            <i class="bi bi-check-circle"></i> Complete Journey
                                        </button>
                                    </form>
                                    <form action="{{ route('dashboard.journey.abandon', $activeAttempt) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-danger w-100" 
                                                onclick="return confirm('Are you sure you want to abandon this journey? Your progress will be lost.')">
                                            <i class="bi bi-x-circle"></i> Abandon Journey
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        {{-- Current Step Interaction --}}
                        @if($activeAttempt->journey->steps->count() > 0)
                            @php
                                $currentStep = $activeAttempt->journey->steps->where('order', $activeAttempt->current_step)->first();
                            @endphp
                            @if($currentStep)
                                <hr>
                                <div class="row">
                                    <div class="col-12">
                                        <h6 class="text-primary">Current Step: {{ $currentStep->title }}</h6>
                                        <div class="step-content bg-light p-3 rounded mb-3">
                                            {!! $currentStep->content !!}
                                        </div>
                                        
                                        {{-- AI Interaction Form --}}
                                        <form action="{{ route('journeys.steps.process_interaction', [$activeAttempt->journey, $currentStep]) }}" method="POST">
                                            @csrf
                                            <input type="hidden" name="attempt_id" value="{{ $activeAttempt->id }}">
                                            <div class="mb-3">
                                                <label for="user_input" class="form-label">Your Response:</label>
                                                <textarea class="form-control" id="user_input" name="user_input" rows="4" 
                                                          placeholder="Enter your response or ask questions about this step..."></textarea>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-send"></i> Submit Response
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="nextStep()">
                                                    <i class="bi bi-skip-forward"></i> Skip to Next Step
                                                </button>
                                            </div>
                                        </form>
                                        
                                        {{-- Previous Responses for Current Step --}}
                                        @php
                                            $stepResponses = $activeAttempt->stepResponses()
                                                ->where('journey_step_id', $currentStep->id)
                                                ->orderBy('created_at', 'desc')
                                                ->take(3)
                                                ->get();
                                        @endphp
                                        @if($stepResponses->count() > 0)
                                            <div class="mt-4">
                                                <h6>Recent Interactions:</h6>
                                                @foreach($stepResponses as $response)
                                                    <div class="card mb-2">
                                                        <div class="card-body p-3">
                                                            @if($response->user_input)
                                                                <div class="mb-2">
                                                                    <strong>You:</strong> {{ $response->user_input }}
                                                                </div>
                                                            @endif
                                                            @if($response->ai_response)
                                                                <div class="text-muted">
                                                                    <strong>AI:</strong> {{ $response->ai_response }}
                                                                </div>
                                                            @endif
                                                            <small class="text-muted">{{ $response->created_at->diffForHumans() }}</small>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- No Active Journey - Show Available Journeys --}}
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-compass"></i> Start a New Journey
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">You don't have any active journey. Choose from our available learning journeys to get started!</p>
                        @php
                            $availableJourneys = App\Models\Journey::where('is_published', true)
                                ->with('collection')
                                ->orderBy('created_at', 'desc')
                                ->take(3)
                                ->get();
                        @endphp
                        @if($availableJourneys->count() > 0)
                            <div class="row">
                                @foreach($availableJourneys as $journey)
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <h6 class="card-title">{{ $journey->title }}</h6>
                                                <p class="card-text small">{{ Str::limit($journey->description, 100) }}</p>
                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                    <span class="badge bg-{{ $journey->difficulty_level === 'beginner' ? 'success' : ($journey->difficulty_level === 'intermediate' ? 'warning' : 'danger') }}">
                                                        {{ ucfirst($journey->difficulty_level) }}
                                                    </span>
                                                    <small class="text-muted">{{ $journey->estimated_duration }} min</small>
                                                </div>
                                                <form action="{{ route('dashboard.journey.start', $journey) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-sm w-100">
                                                        <i class="bi bi-play"></i> Start Journey
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="text-center mt-3">
                                <a href="{{ route('journeys.index') }}" class="btn btn-outline-primary">
                                    <i class="bi bi-collection"></i> Browse All Journeys
                                </a>
                            </div>
                        @else
                            <p class="text-muted">No journeys available at the moment.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Available Journeys</h5>
                            <h2>{{ $data['available_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-map fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Completed</h5>
                            <h2>{{ $data['completed_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">In Progress</h5>
                            <h2>{{ $data['in_progress_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Total Attempts</h5>
                            <h2>{{ $data['completed_journeys'] + $data['in_progress_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-play-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Recent Journey Attempts</h5>
                </div>
                <div class="card-body">
                    @if($data['recent_attempts']->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Journey</th>
                                        <th>Status</th>
                                        <th>Started</th>
                                        <th>Score</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['recent_attempts'] as $attempt)
                                        <tr>
                                            <td>{{ $attempt->journey->title }}</td>
                                            <td>
                                                @if($attempt->status === 'completed')
                                                    <span class="badge bg-success">Completed</span>
                                                @elseif($attempt->status === 'in_progress')
                                                    <span class="badge bg-warning">In Progress</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ ucfirst($attempt->status) }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $attempt->started_at ? $attempt->started_at->format('M d, Y') : 'Not started' }}</td>
                                            <td>{{ $attempt->score ? number_format($attempt->score, 1) . '%' : '-' }}</td>
                                            <td>
                                                @if($attempt->status === 'in_progress')
                                                    <a href="{{ route('journeys.continue', $attempt) }}" class="btn btn-sm btn-primary">Continue</a>
                                                @else
                                                    <a href="{{ route('journeys.show', $attempt->journey) }}" class="btn btn-sm btn-outline-primary">View</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">No journey attempts yet. <a href="{{ route('journeys.index') }}">Start your first journey!</a></p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@elseif($user->role === 'editor')
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Managed Collections</h5>
                            <h2>{{ $data['managed_collections'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-collection fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Total Journeys</h5>
                            <h2>{{ $data['total_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-map fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Published</h5>
                            <h2>{{ $data['published_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Total Attempts</h5>
                            <h2>{{ $data['total_attempts'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-play-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@elseif($user->role === 'institution')
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Collections</h5>
                            <h2>{{ $data['total_collections'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-collection fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Editors</h5>
                            <h2>{{ $data['total_editors'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Journeys</h5>
                            <h2>{{ $data['total_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-map fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Users</h5>
                            <h2>{{ $data['total_users'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@elseif($user->role === 'administrator')
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Institutions</h5>
                            <h2>{{ $data['total_institutions'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-building fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Total Users</h5>
                            <h2>{{ $data['total_users'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Total Journeys</h5>
                            <h2>{{ $data['total_journeys'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-map fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">Total Attempts</h5>
                            <h2>{{ $data['total_attempts'] }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-play-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Today's Activity</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-primary">{{ $data['recent_activity']['new_users_today'] }}</h4>
                                <p class="text-muted">New Users</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-success">{{ $data['recent_activity']['new_journeys_today'] }}</h4>
                                <p class="text-muted">New Journeys</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-warning">{{ $data['recent_activity']['attempts_today'] }}</h4>
                                <p class="text-muted">Journey Attempts</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    @if($user->canPerform('journey.view'))
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('journeys.index') }}" class="btn btn-outline-primary w-100">
                                <i class="bi bi-map"></i> Browse Journeys
                            </a>
                        </div>
                    @endif

                    @if($user->canPerform('journey.create'))
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('journeys.create') }}" class="btn btn-outline-success w-100">
                                <i class="bi bi-plus-circle"></i> Create Journey
                            </a>
                        </div>
                    @endif

                    @if($user->canPerform('journey_collection.create'))
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('collections.create') }}" class="btn btn-outline-info w-100">
                                <i class="bi bi-collection"></i> Create Collection
                            </a>
                        </div>
                    @endif

                    @if($user->canPerform('reports.view'))
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('reports.index') }}" class="btn btn-outline-warning w-100">
                                <i class="bi bi-graph-up"></i> View Reports
                            </a>
                        </div>
                    @endif

                    @if($user->canPerform('user.manage'))
                        <div class="col-md-4 mb-3">
                            <a href="{{ route('users.create') }}" class="btn btn-outline-secondary w-100">
                                <i class="bi bi-person-plus"></i> Add User
                            </a>
                        </div>
                    @endif

                    <div class="col-md-4 mb-3">
                        <a href="{{ route('profile.show') }}" class="btn btn-outline-dark w-100">
                            <i class="bi bi-person-circle"></i> My Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($user->role === 'regular' && $activeAttempt)
<script>
function nextStep() {
    if (confirm('Are you sure you want to skip to the next step without submitting a response?')) {
        fetch(`{{ route('dashboard.journey.next-step', ':attempt') }}`.replace(':attempt', {{ $activeAttempt->id ?? 'null' }}), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'An error occurred while advancing to the next step.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while advancing to the next step.');
        });
    }
}
</script>
@endif
@endsection
