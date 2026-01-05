@extends('layouts.app')

@push('styles')
<style>
.profile-shell {
    width: min(1200px, 100%);
    max-width: 100%;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
    overflow-x: hidden;
}
.profile-hero {
    background: linear-gradient(130deg, #0f172a, #8b5cf6 70%);
    border-radius: 36px;
    color: #fff;
    padding: clamp(2rem, 6vw, 4rem);
    margin-bottom: 2.5rem;
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.35);
}
.profile-hero h1 {
    font-size: clamp(2.2rem, 4vw, 3rem);
    margin-bottom: 0.35rem;
}
.hero-pill {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    padding: 0.55rem 1.4rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}
.hero-meta {
    min-width: 220px;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    color: rgba(255, 255, 255, 0.82);
}
.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.8rem;
    font-weight: 600;
}
.glass-card {
    border-radius: 32px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    margin-bottom: 2rem;
}
.glass-card .card-body {
    padding: clamp(1.75rem, 4vw, 2.5rem);
}
.profile-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
}
.info-pill {
    border-radius: 20px;
    background: #f8fafc;
    padding: 1rem 1.25rem;
}
.status-chip {
    border-radius: 999px;
    padding: 0.35rem 0.9rem;
    font-size: 0.8rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
}
.custom-field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1rem;
}
.custom-field {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    padding: 1rem 1.25rem;
    background: #f8fafc;
}
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
}
.stat-tile {
    border-radius: 24px;
    padding: 1.5rem;
    background: #0f172a;
    color: #fff;
}
.stat-tile:nth-child(2) { background: #eab308; color: #0f172a; }
.stat-tile:nth-child(3) { background: #0284c7; }
.stat-tile:nth-child(4) { background: #8b5cf6; }
.recent-table table {
    margin: 0;
    table-layout: fixed;
    width: 100%;
}
.recent-table thead th {
    border: none;
    font-size: 0.78rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #94a3b8;
}
.recent-table td,
.recent-table th {
    word-break: break-word;
    white-space: normal;
}
.recent-table {
    overflow-x: auto;
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
.account-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.account-actions .btn {
    border-radius: 999px;
    min-width: 220px;
}
@media (max-width: 575.98px) {
    .hero-actions .btn,
    .account-actions .btn {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .profile-info-grid,
    .custom-field-grid,
    .stat-grid {
        grid-template-columns: minmax(0, 1fr);
    }
    .account-actions {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
@endpush

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

<section class="profile-shell">
    <div class="profile-hero">
        <div class="flex-grow-1">
            <div class="hero-pill mb-3"><i class="bi bi-person"></i> Profile</div>
            <h1>Hi {{ \Illuminate\Support\Str::of($user->name)->before(' ')->isNotEmpty() ? \Illuminate\Support\Str::of($user->name)->before(' ') : $user->name }}, this is your space.</h1>
            <p class="mb-3">Review your account details, keep profile fields current, and monitor learning progress from one cohesive view.</p>
            <div class="hero-actions d-flex flex-wrap gap-2">
                <a href="{{ route('profile.edit') }}" class="btn btn-light text-dark rounded-pill"><i class="bi bi-pencil"></i> Edit profile</a>
                <a href="{{ route('dashboard') }}" class="btn btn-outline-light rounded-pill"><i class="bi bi-speedometer"></i> Dashboard</a>
            </div>
        </div>
        <div class="hero-meta">
            <div>
                <small class="text-uppercase">Role</small>
                <p class="fs-5 mb-0">{{ $user->role_label }}</p>
            </div>
            <div>
                <small class="text-uppercase">Member since</small>
                <p class="mb-0">{{ $user->created_at->format('F j, Y') }}</p>
            </div>
            <div>
                <small class="text-uppercase">Status</small>
                @if(isset($profileFields) && $profileFields->count())
                    <span class="status-chip bg-{{ $hasCompleted ? 'success' : 'warning' }} text-white">{{ $hasCompleted ? 'Complete' : 'Missing ' . count($missingFields) }}</span>
                @else
                    <span class="status-chip bg-success text-white">Complete</span>
                @endif
            </div>
        </div>
    </div>

    <div class="glass-card">
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

        <div class="glass-card recent-table">
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
