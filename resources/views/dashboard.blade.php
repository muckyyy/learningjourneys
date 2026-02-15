@extends('layouts.app')

@section('content')
@php
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ');
    $firstName = $firstName->isNotEmpty() ? $firstName : $user->name;
    $roleTaglines = [
        'regular' => 'Stay on track with curated journeys, token insights, and saved attempts.',
        'editor' => 'Curate collections, publish journeys, and keep engagement high.',
        'institution' => 'Monitor cohorts, align editors, and keep every learner moving.',
        'administrator' => 'Oversee institutions, users, and reporting all in one place.',
    ];
    $heroTagline = $roleTaglines[$user->role] ?? 'Guide every learning journey with confidence.';
@endphp

<section class="shell">
    @if($user->role === 'regular')
        @if($activeAttempt)
            <div class="active-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <p class="text-uppercase small text-warning mb-1 letter-spacing-default">Active journey</p>
                        <h4>{{ $activeAttempt->journey->title }}</h4>
                        <p class="mb-0 text-muted">Complete or abandon this run before starting something new.</p>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('journeys.' . $activeAttempt->type, $activeAttempt) }}" class="btn btn-warning">
                            <i class="bi bi-arrow-right-circle"></i> Continue
                        </a>
                        <form action="{{ route('dashboard.journey.abandon', $activeAttempt) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-outline-dark"
                                onclick="return confirm('Abandon this journey and lose current progress?')">
                                <i class="bi bi-x-circle"></i> Abandon
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @else
            @php
                $availableJourneys = App\Models\Journey::where('is_published', true)
                    ->with('collection')
                    ->orderBy('created_at', 'desc')
                    ->take(3)
                    ->get();
            @endphp
            <div class="glass-card mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <p class="text-uppercase small text-muted mb-1 letter-spacing-default">Recommendations</p>
                            <h4 class="mb-0">Start a new journey</h4>
                        </div>
                        <a href="{{ route('journeys.index') }}" class="btn btn-outline-dark rounded-pill">
                            <i class="bi bi-collection"></i> Browse all
                        </a>
                    </div>
                    @if($availableJourneys->count())
                        <div class="journey-grid">
                            @foreach($availableJourneys as $journey)
                                <div class="journey-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge-pill bg-light text-dark">{{ ucfirst($journey->difficulty_level) }}</span>
                                        <small class="text-muted">{{ $journey->estimated_duration }} min</small>
                                    </div>
                                    <h5 class="mb-2">{{ $journey->title }}</h5>
                                    <p class="text-muted mb-3">{{ \Illuminate\Support\Str::limit($journey->description, 96) }}</p>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-dark btn-sm"
                                            onclick="window.JourneyStartModal.showStartJourneyModal({{ $journey->id }}, '{{ addslashes($journey->title) }}', 'voice', {{ (int) $journey->token_cost }})">
                                            <i class="bi bi-mic"></i> Start Voice {{ $journey->token_cost > 0 ? '(' . $journey->token_cost . ' tokens)' : '(Free)' }}
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">No journeys available at the moment.</p>
                    @endif
                </div>
            </div>
        @endif

        @if(!$activeAttempt)
            <div class="stat-grid mt-4">
                <div class="stat-card">
                    <span>Available</span>
                    <h3>{{ $data['available_journeys'] }}</h3>
                    <p class="text-muted mb-0">Ready-to-start journeys</p>
                </div>
                <div class="stat-card">
                    <span>Completed</span>
                    <h3>{{ $data['completed_journeys'] }}</h3>
                    <p class="text-muted mb-0">All-time completions</p>
                </div>
                <div class="stat-card">
                    <span>In progress</span>
                    <h3>{{ $data['in_progress_journeys'] }}</h3>
                    <p class="text-muted mb-0">Currently running</p>
                </div>
                <div class="stat-card">
                    <span>Total attempts</span>
                    <h3>{{ $data['completed_journeys'] + $data['in_progress_journeys'] }}</h3>
                    <p class="text-muted mb-0">Historical attempts</p>
                </div>
            </div>

            <div class="glass-card recent-table">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap align-items-center mb-3">
                        <div>
                            <p class="text-uppercase small text-muted mb-1 letter-spacing-default">Timeline</p>
                            <h4 class="mb-0">Recent attempts</h4>
                        </div>
                    </div>
                    @if($data['recent_attempts']->count())
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Journey</th>
                                        <th>Status</th>
                                        <th>Started</th>
                                        <th>Score</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($data['recent_attempts'] as $attempt)
                                        <tr>
                                            <td class="fw-semibold" data-label="Journey">{{ $attempt->journey->title }}</td>
                                            <td data-label="Status">
                                                @if($attempt->status === 'completed')
                                                    <span class="badge bg-success rounded-pill">Completed</span>
                                                @elseif($attempt->status === 'in_progress')
                                                    <span class="badge bg-warning rounded-pill">In progress</span>
                                                @else
                                                    <span class="badge bg-secondary rounded-pill">{{ ucfirst($attempt->status) }}</span>
                                                @endif
                                            </td>
                                            <td data-label="Started">{{ $attempt->started_at ? $attempt->started_at->format('M d, Y') : 'Not started' }}</td>
                                            <td data-label="Score">{{ $attempt->score ? number_format($attempt->score, 1) . '%' : '—' }}</td>
                                            <td class="text-end" data-label="Action">
                                                @if($attempt->status === 'in_progress')
                                                    <a href="{{ route('journeys.' . $attempt->type, $attempt) }}" class="btn btn-sm btn-dark">Continue</a>
                                                @else
                                                    <a href="{{ route('journeys.show', $attempt->journey) }}" class="btn btn-sm btn-outline-dark">View</a>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted mb-0">No journey attempts yet. <a href="{{ route('journeys.index') }}">Start your first journey!</a></p>
                    @endif
                </div>
            </div>
        @endif
    @else
        @php
            $statBlocks = [];
            if($user->role === 'editor') {
                $statBlocks = [
                    ['label' => 'Managed collections', 'value' => $data['managed_collections']],
                    ['label' => 'Total journeys', 'value' => $data['total_journeys']],
                    ['label' => 'Published', 'value' => $data['published_journeys']],
                    ['label' => 'Total attempts', 'value' => $data['total_attempts']],
                ];
            } elseif($user->role === 'institution') {
                $statBlocks = [
                    ['label' => 'Collections', 'value' => $data['total_collections']],
                    ['label' => 'Editors', 'value' => $data['total_editors']],
                    ['label' => 'Journeys', 'value' => $data['total_journeys']],
                    ['label' => 'Users', 'value' => $data['total_users']],
                ];
            } else {
                $statBlocks = [
                    ['label' => 'Institutions', 'value' => $data['total_institutions']],
                    ['label' => 'Total users', 'value' => $data['total_users']],
                    ['label' => 'Journeys', 'value' => $data['total_journeys']],
                    ['label' => 'Attempts', 'value' => $data['total_attempts']],
                ];
            }
        @endphp

        <div class="stat-grid">
            @foreach($statBlocks as $block)
                <div class="stat-card">
                    <span>{{ $block['label'] }}</span>
                    <h3>{{ number_format($block['value']) }}</h3>
                </div>
            @endforeach
        </div>

        @if(isset($data['recent_activity']))
            <div class="glass-card">
                <div class="card-body">
                    <p class="text-uppercase small text-muted mb-1 letter-spacing-default">Today</p>
                    <h4 class="mb-3">Activity snapshot</h4>
                    <div class="today-grid">
                        <div class="today-tile">
                            <h4 class="text-primary">{{ $data['recent_activity']['new_users_today'] }}</h4>
                            <p class="text-muted mb-0">New users</p>
                        </div>
                        <div class="today-tile">
                            <h4 class="text-success">{{ $data['recent_activity']['new_journeys_today'] }}</h4>
                            <p class="text-muted mb-0">New journeys</p>
                        </div>
                        <div class="today-tile">
                            <h4 class="text-warning">{{ $data['recent_activity']['attempts_today'] }}</h4>
                            <p class="text-muted mb-0">Journey attempts</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif

    <div class="glass-card">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                <div>
                    <p class="text-uppercase small text-muted mb-1 letter-spacing-default">Shortcuts</p>
                    <h4 class="mb-0">Quick actions</h4>
                </div>
            </div>
            <div class="quick-actions">
                @if($user->canPerform('journey.view'))
                    <a href="{{ route('journeys.index') }}" class="action-chip"><i class="bi bi-map"></i> Browse journeys</a>
                @endif
                @if($user->canPerform('journey.create'))
                    <a href="{{ route('journeys.create') }}" class="action-chip"><i class="bi bi-plus-circle"></i> Create journey</a>
                @endif
                @if($user->canPerform('journey_collection.create'))
                    <a href="{{ route('collections.create') }}" class="action-chip"><i class="bi bi-collection"></i> New collection</a>
                @endif
                @if($user->canPerform('reports.view'))
                    <a href="{{ route('reports.index') }}" class="action-chip"><i class="bi bi-graph-up"></i> Reports</a>
                @endif
                @if($user->canPerform('user.manage'))
                    <a href="{{ route('users.create') }}" class="action-chip"><i class="bi bi-person-plus"></i> Add user</a>
                @endif
                <a href="{{ route('profile.show') }}" class="action-chip"><i class="bi bi-person-circle"></i> My profile</a>
            </div>
        </div>
    </div>
</section>

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
