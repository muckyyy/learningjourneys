@extends('layouts.app')

@section('content')
@php
    $user = Auth::user();
    $hasCompleted = isset($profileFields) ? $user->hasCompletedRequiredProfileFields() : true;
    $missingFields = isset($profileFields) ? $user->getMissingRequiredProfileFields() : [];
    $completedJourneys = App\Models\JourneyAttempt::where('user_id', $user->id)->where('status', 'completed')->count();
    $inProgressJourneys = App\Models\JourneyAttempt::where('user_id', $user->id)->where('status', 'in_progress')->count();
    $totalAttempts = App\Models\JourneyAttempt::where('user_id', $user->id)->count();
    $avgScore = App\Models\JourneyAttempt::where('user_id', $user->id)->where('status', 'completed')->avg('score');
    $recentAttempts = App\Models\JourneyAttempt::where('user_id', $user->id)->with('journey')->orderByDesc('created_at')->take(5)->get();
@endphp

<section class="shell">


    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
                <div>
                    <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Account</p>
                    <h4 class="mb-0">Profile details</h4>
                </div>
            </div>
            <div class="profile-info-grid">
                <div class="info-pill">
                    <p class="text-muted mb-1">Name</p>
                    <p class="fw-semibold mb-0">{{ $user->name }}</p>
                </div>
                <div class="info-pill">
                    <p class="text-muted mb-1">Email</p>
                    <p class="fw-semibold mb-0">{{ $user->email }}</p>
                </div>
                <div class="info-pill">
                    <p class="text-muted mb-1">Role</p>
                    <p class="fw-semibold mb-0">{{ $user->role_label }}</p>
                </div>
                @if($user->institution)
                    <div class="info-pill">
                        <p class="text-muted mb-1">Institution</p>
                        <p class="fw-semibold mb-0">{{ $user->institution->name }}</p>
                    </div>
                @endif
                <div class="info-pill">
                    <p class="text-muted mb-1">Last login</p>
                    <p class="fw-semibold mb-0">{{ $user->updated_at->diffForHumans() }}</p>
                </div>
            </div>
            @if(isset($profileFields) && $profileFields->count())
                <div class="mt-4">
                    <p class="text-uppercase small text-muted mb-2" style="letter-spacing: 0.2em;">Custom fields</p>
                    <div class="custom-field-grid">
                        @foreach($profileFields as $field)
                            @php
                                $value = $user->getProfileValue($field->short_name);
                            @endphp
                            <div class="custom-field">
                                <p class="text-muted small mb-1">{{ $field->name }}</p>
                                @if($value !== null && $value !== '')
                                    @if($field->input_type === 'select_multiple' && is_array($value))
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($value as $option)
                                                <span class="badge bg-secondary">{{ $option }}</span>
                                            @endforeach
                                        </div>
                                    @elseif($field->input_type === 'textarea')
                                        <div class="border rounded p-2 bg-white">{{ $value }}</div>
                                    @else
                                        <p class="fw-semibold mb-0">{{ $value }}</p>
                                    @endif
                                @else
                                    <p class="text-muted fst-italic mb-0">Not filled</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if($user->role === 'regular')
        <div class="glass-card">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
                    <div>
                        <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Progress</p>
                        <h4 class="mb-0"><i class="bi bi-graph-up"></i> Learning statistics</h4>
                    </div>
                </div>
                <div class="stat-grid">
                    <div class="stat-tile">
                        <p class="text-uppercase small mb-1">Completed</p>
                        <h3 class="mb-0">{{ $completedJourneys }}</h3>
                    </div>
                    <div class="stat-tile">
                        <p class="text-uppercase small mb-1">In progress</p>
                        <h3 class="mb-0">{{ $inProgressJourneys }}</h3>
                    </div>
                    <div class="stat-tile">
                        <p class="text-uppercase small mb-1">Total attempts</p>
                        <h3 class="mb-0">{{ $totalAttempts }}</h3>
                    </div>
                    <div class="stat-tile">
                        <p class="text-uppercase small mb-1">Average score</p>
                        <h3 class="mb-0">{{ $avgScore ? number_format($avgScore, 1) . '%' : 'N/A' }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="card recent-table">
            <div class="card-body">
                <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
                    <div>
                        <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">History</p>
                        <h4 class="mb-0"><i class="bi bi-clock-history"></i> Recent journey attempts</h4>
                    </div>
                </div>
                @if($recentAttempts->count())
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
                                @foreach($recentAttempts as $attempt)
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
                                                <a href="{{ route('dashboard') }}" class="btn btn-sm btn-dark rounded-pill">Continue</a>
                                            @else
                                                <a href="{{ route('journeys.show', $attempt->journey) }}" class="btn btn-sm btn-outline-dark rounded-pill">View</a>
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

    <div class="glass-card">
        <div class="card-body">
            <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
                <div>
                    <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Actions</p>
                    <h4 class="mb-0"><i class="bi bi-gear"></i> Account controls</h4>
                </div>
            </div>
            <div class="account-actions">
                <a href="{{ route('profile.edit') }}" class="btn btn-outline-dark"><i class="bi bi-pencil"></i> Edit profile</a>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="btn btn-outline-secondary"><i class="bi bi-lock"></i> Change password</a>
                @endif
                <a href="{{ route('dashboard') }}" class="btn btn-outline-primary"><i class="bi bi-house"></i> Dashboard</a>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to logout?')"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection
