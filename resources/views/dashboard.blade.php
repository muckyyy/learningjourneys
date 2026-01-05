@extends('layouts.app')

@push('styles')
<style>
.dash-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
}
.dash-hero {
    background: linear-gradient(135deg, #0f172a, #1d4ed8 70%);
    border-radius: 32px;
    color: #fff;
    padding: clamp(2rem, 6vw, 3.75rem);
    margin-bottom: 2.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.35);
}
.dash-hero h1 {
    font-size: clamp(2rem, 4vw, 2.75rem);
    font-weight: 700;
}
.dash-hero p {
    color: rgba(255, 255, 255, 0.75);
    max-width: 520px;
}
.hero-meta {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    min-width: 260px;
}
.hero-pill {
    border-radius: 24px;
    padding: 0.65rem 1.5rem;
    background: rgba(255, 255, 255, 0.14);
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 600;
}
.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.hero-actions .btn {
    border-radius: 999px;
    font-weight: 600;
    padding: 0.8rem 1.8rem;
}
.glass-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 28px;
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    margin-bottom: 2rem;
}
.glass-card .card-body {
    padding: 2rem;
}
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.stat-card {
    border-radius: 24px;
    padding: 1.5rem;
    background: rgba(15, 23, 42, 0.03);
    border: 1px solid rgba(15, 23, 42, 0.04);
}
.stat-card h3 {
    font-size: 2.1rem;
    margin-bottom: 0.25rem;
    font-weight: 700;
}
.stat-card span {
    text-transform: uppercase;
    letter-spacing: 0.1em;
    font-size: 0.75rem;
    color: #6b7280;
}
.active-card {
    border-radius: 28px;
    border: 1px solid rgba(234, 179, 8, 0.4);
    background: #fffbeb;
    padding: 1.75rem;
    margin-bottom: 2rem;
    box-shadow: 0 20px 50px rgba(234, 179, 8, 0.25);
}
.active-card h4 {
    font-weight: 700;
    margin-bottom: 1rem;
}
.journey-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
}
.journey-card {
    border-radius: 24px;
    border: 1px solid rgba(15, 23, 42, 0.06);
    padding: 1.5rem;
    background: #fff;
    box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
    height: 100%;
}
.journey-card h5 {
    font-weight: 600;
}
.badge-pill {
    border-radius: 999px;
    padding: 0.35rem 0.9rem;
    font-size: 0.78rem;
}
.recent-table {
    overflow-x: auto;
}
.recent-table table {
    border: none;
    table-layout: fixed;
    width: 100%;
}
.recent-table thead th {
    border: none;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #6b7280;
}
.recent-table th,
.recent-table td {
    word-break: break-word;
    white-space: normal;
}
.recent-table tr {
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
}
@media (max-width: 640px) {
    .recent-table thead {
        display: none;
    }
    .recent-table table,
    .recent-table tbody,
    .recent-table tr,
    .recent-table td {
        display: block;
        width: 100%;
    }
    .recent-table tr {
        margin-bottom: 1rem;
        padding: 1rem 1.1rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 18px;
        background: #fff;
    }
    .recent-table td {
        padding: 0.35rem 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
    }
    .recent-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        font-size: 0.7rem;
    }
    .recent-table td.text-end {
        justify-content: flex-end;
    }
}
.recent-table tbody td {
    border-top: 1px solid rgba(15, 23, 42, 0.06);
    vertical-align: middle;
}
.quick-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.8rem;
}
.action-chip {
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.15);
    padding: 0.65rem 1.2rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    font-weight: 600;
    text-decoration: none;
    color: #0f172a;
}
.action-chip:hover {
    border-color: #0f172a;
    color: #0f172a;
}
.today-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}
.today-tile {
    border-radius: 22px;
    background: rgba(15, 23, 42, 0.04);
    padding: 1.25rem;
    text-align: center;
}
.today-tile h4 {
    font-weight: 700;
    margin-bottom: 0.15rem;
}
@media (max-width: 767.98px) {
    .glass-card .card-body {
        padding: 1.5rem;
    }
    .hero-actions .btn {
        width: 100%;
    }
}
</style>
@endpush

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

<section class="dash-shell">
    <div class="dash-hero">
        <div class="flex-grow-1">
            <div class="hero-pill text-uppercase small mb-2" style="letter-spacing: 0.18em;">
                <i class="bi bi-stars"></i> Dashboard
            </div>
            <h1 class="mb-3">Welcome back, {{ $firstName }}.</h1>
            <p class="mb-4">{{ $heroTagline }}</p>
            <div class="hero-actions">
                @if($user->role === 'regular')
                    <a href="{{ route('journeys.index') }}" class="btn btn-light text-dark">
                        <i class="bi bi-compass"></i> Explore Journeys
                    </a>
                    @if(isset($tokenSnapshot))
                        <a href="{{ route('tokens.index') }}" class="btn btn-outline-light">
                            <i class="bi bi-coin"></i> Manage Tokens
                        </a>
                    @endif
                @else
                    <a href="{{ route('journeys.index') }}" class="btn btn-light text-dark">
                        <i class="bi bi-map"></i> View Journeys
                    </a>
                    <a href="{{ route('reports.index') }}" class="btn btn-outline-light">
                        <i class="bi bi-graph-up"></i> View Reports
                    </a>
                @endif
            </div>
        </div>
        <div class="hero-meta">
            @if(isset($tokenSnapshot))
                <div>
                    <span class="hero-pill"><i class="bi bi-coin"></i> {{ number_format($tokenSnapshot['total']) }} tokens</span>
                    <p class="mt-2 mb-0 small text-white-50">
                        {{ $tokenSnapshot['expiring_soon'] > 0 ? $tokenSnapshot['expiring_soon'] . ' expiring soon' : 'All tokens fresh' }}
                    </p>
                </div>
            @endif
            <div>
                <span class="hero-pill"><i class="bi bi-person-badge"></i> {{ ucfirst($user->role) }}</span>
                <p class="mt-2 mb-0 small text-white-50">{{ $user->email }}</p>
            </div>
        </div>
    </div>

    @if($user->role === 'regular')
        @if($activeAttempt)
            <div class="active-card">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <p class="text-uppercase small text-warning mb-1" style="letter-spacing: 0.2em;">Active journey</p>
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
            <div class="glass-card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                        <div>
                            <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Recommendations</p>
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
            <div class="stat-grid">
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
                            <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Timeline</p>
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
                    <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Today</p>
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
                    <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Shortcuts</p>
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
