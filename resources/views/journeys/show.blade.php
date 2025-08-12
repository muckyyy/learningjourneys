@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">{{ $journey->title }}</h1>
                <div>
                    <a href="{{ route('journeys.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Journeys
                    </a>
                    @can('update', $journey)
                        <a href="{{ route('journeys.edit', $journey) }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <a href="/preview-chat?journey_id={{ $journey->id }}" class="btn btn-outline-info ms-2">
                            <i class="bi bi-eye"></i> Preview
                        </a>
                    @endcan
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <span class="badge bg-{{ $journey->difficulty_level === 'beginner' ? 'success' : ($journey->difficulty_level === 'intermediate' ? 'warning' : 'danger') }} me-2">
                                        {{ ucfirst($journey->difficulty_level) }}
                                    </span>
                                    @if(!$journey->is_published)
                                        <span class="badge bg-warning">Draft</span>
                                    @endif
                                </div>
                                <div class="text-muted">
                                    <i class="bi bi-clock"></i> {{ $journey->estimated_duration }} minutes
                                </div>
                            </div>

                            <p class="lead">{{ $journey->description }}</p>

                            <div class="row text-muted small">
                                <div class="col-md-6">
                                    <strong>Collection:</strong> {{ $journey->collection->name }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Created by:</strong> {{ $journey->creator->name }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Institution:</strong> {{ $journey->collection->institution->name }}
                                </div>
                                <div class="col-md-6">
                                    <strong>Created:</strong> {{ $journey->created_at->format('M d, Y') }}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($journey->steps->count() > 0)
                        <div class="card shadow-sm">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-list-ol"></i> Journey Steps ({{ $journey->steps->count() }})
                                </h5>
                                @can('update', $journey)
                                    <a href="{{ route('journeys.steps.index', $journey) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-gear"></i> Manage Steps
                                    </a>
                                @endcan
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    @foreach($journey->steps as $step)
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>Step {{ $step->order }}:</strong> {{ $step->title }}
                                                <br>
                                                <small class="text-muted">{{ $step->type }}</small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">{{ $step->order }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="card shadow-sm">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-list-ol display-4 text-muted"></i>
                                <h5 class="mt-3 text-muted">No Steps Added Yet</h5>
                                <p class="text-muted">This journey doesn't have any steps yet. Add some steps to make it interactive!</p>
                                @can('update', $journey)
                                    <a href="{{ route('journeys.steps.create', $journey) }}" class="btn btn-primary">
                                        <i class="bi bi-plus-lg"></i> Add Steps
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-md-4">
                    @if(auth()->check())
                        <div class="card shadow-sm mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-play-circle"></i> Your Progress
                                </h5>
                            </div>
                            <div class="card-body">
                                @if($userAttempt)
                                    @if($userAttempt->status === 'completed')
                                        <div class="alert alert-success">
                                            <i class="bi bi-check-circle"></i> You completed this journey!
                                            <br>
                                            <small>Completed on {{ $userAttempt->completed_at->format('M d, Y') }}</small>
                                            @if($userAttempt->score)
                                                <br>
                                                <small>Score: {{ $userAttempt->score }}%</small>
                                            @endif
                                        </div>
                                        <a href="{{ route('journeys.continue', $userAttempt) }}" class="btn btn-outline-primary w-100">
                                            <i class="bi bi-eye"></i> Review Journey
                                        </a>
                                    @else
                                        <div class="alert alert-info">
                                            <i class="bi bi-play-circle"></i> Journey in progress
                                            <br>
                                            <small>Started {{ $userAttempt->started_at->diffForHumans() }}</small>
                                        </div>
                                        <a href="{{ route('journeys.continue', $userAttempt) }}" class="btn btn-primary w-100">
                                            <i class="bi bi-play"></i> Continue Journey
                                        </a>
                                    @endif
                                @else
                                    @if($journey->steps->count() > 0 && $journey->is_published)
                                        @if(isset($activeAttempt) && $activeAttempt)
                                            <div class="alert alert-warning">
                                                <h6 class="alert-heading">
                                                    <i class="bi bi-exclamation-triangle"></i> Active Journey in Progress
                                                </h6>
                                                <p class="mb-2">
                                                    You currently have an active journey: <strong>{{ $activeAttempt->journey->title }}</strong>
                                                </p>
                                                <p class="mb-3">
                                                    You must complete or abandon your current journey before starting this one.
                                                </p>
                                                <div class="d-flex gap-2">
                                                    <a href="{{ route('dashboard') }}" class="btn btn-warning btn-sm">
                                                        <i class="bi bi-arrow-right-circle"></i> Go to Active Journey
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
                                        @else
                                            <p class="text-muted">Ready to start this learning journey?</p>
                                            <form action="{{ route('dashboard.journey.start', $journey) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="bi bi-play-fill"></i> Start Journey
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <div class="alert alert-warning">
                                            @if(!$journey->is_published)
                                                <i class="bi bi-eye-slash"></i> This journey is not published yet.
                                            @else
                                                <i class="bi bi-exclamation-triangle"></i> This journey has no steps yet.
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="card shadow-sm mb-4">
                            <div class="card-body text-center">
                                <h5>Ready to start learning?</h5>
                                <p class="text-muted">Sign in to track your progress and take this journey.</p>
                                <a href="{{ route('login') }}" class="btn btn-primary w-100">
                                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- Journey Statistics -->
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
                                        <h4 class="text-primary mb-0">{{ $journey->attempts()->count() }}</h4>
                                        <small class="text-muted">Attempts</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success mb-0">{{ $journey->attempts()->where('status', 'completed')->count() }}</h4>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                            @php
                                $totalAttempts = $journey->attempts()->count();
                                $completedAttempts = $journey->attempts()->where('status', 'completed')->count();
                                $completionRate = $totalAttempts > 0 ? round(($completedAttempts / $totalAttempts) * 100, 1) : 0;
                            @endphp
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small>Completion Rate</small>
                                    <small>{{ $completionRate }}%</small>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-success" style="width: {{ $completionRate }}%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
