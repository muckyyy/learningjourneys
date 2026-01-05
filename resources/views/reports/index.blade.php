@extends('layouts.app')

@push('styles')
<style>
.reports-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.reports-hero {
    background: linear-gradient(135deg, #0f172a, #0ea5e9 50%, #6366f1);
    border-radius: 40px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.75rem;
    box-shadow: 0 35px 80px rgba(14, 165, 233, 0.35);
    margin-bottom: 2.5rem;
}
.reports-hero .hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.5rem 1.4rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.35);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.reports-hero h1 {
    font-size: clamp(2rem, 4vw, 3.2rem);
    margin-bottom: 0.4rem;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.6rem;
    font-weight: 600;
    box-shadow: 0 15px 30px rgba(15, 23, 42, 0.35);
}
.hero-stat-grid {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
}
.hero-stat {
    background: rgba(15, 23, 42, 0.35);
    border-radius: 1.75rem;
    padding: 1rem 1.25rem;
}
.hero-stat span {
    display: block;
    font-size: 0.78rem;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.7);
}
.hero-stat strong {
    display: block;
    font-size: 2rem;
    line-height: 1.1;
}
.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.75rem;
    margin-bottom: 2rem;
}
.reports-card {
    border-radius: 34px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    padding: clamp(1.5rem, 3vw, 2.5rem);
}
.reports-card h3 {
    font-size: 1rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 0.5rem;
}
.reports-card h4 {
    font-size: 1.4rem;
    margin-bottom: 1.2rem;
}
.link-stack .btn {
    border-radius: 18px;
    padding: 0.85rem 1.2rem;
    font-weight: 600;
    justify-content: flex-start;
}
.popular-list {
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.popular-item {
    border-radius: 20px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    padding: 0.9rem 1.1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}
.popular-item h6 {
    margin-bottom: 0.15rem;
}
.full-width-card {
    border-radius: 34px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    padding: clamp(1.5rem, 3vw, 2.5rem);
    margin-bottom: 2rem;
}
.full-width-card h4 {
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.85rem;
    color: #94a3b8;
    margin-bottom: 0.4rem;
}
.table-modern thead th {
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.75rem;
    color: #94a3b8;
    border-bottom-width: 1px;
}
.table-modern tbody td {
    vertical-align: middle;
    border-color: rgba(15, 23, 42, 0.05);
}
.roles-list {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.roles-list .role-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: rgba(15, 23, 42, 0.03);
    border-radius: 16px;
    padding: 0.6rem 0.95rem;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
}
</style>
@endpush

@section('content')
@php
    $totalUsers = $stats['total_users'] ?? null;
    $totalJourneys = $stats['total_journeys'] ?? null;
    $totalAttempts = $stats['total_attempts'] ?? null;
    $completionRate = $stats['average_completion_rate'] ?? null;
    $popularJourneys = $stats['popular_journeys'] ?? collect();
    $recentActivity = $stats['recent_activity'] ?? collect();
    $userRoles = $stats['user_roles'] ?? [];
@endphp

<section class="reports-shell">
    <div class="reports-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-graph-up"></i> Intelligence</div>
            <h1>See the pulse of every journey.</h1>
            <p class="mb-3">Track adoption, completion rates, and high-performing journeys from a single glass dashboard.</p>
            <div class="hero-stat-grid">
                @if($totalUsers)
                    <div class="hero-stat">
                        <span>Total users</span>
                        <strong>{{ number_format($totalUsers) }}</strong>
                    </div>
                @endif
                @if($totalJourneys)
                    <div class="hero-stat">
                        <span>Journeys</span>
                        <strong>{{ number_format($totalJourneys) }}</strong>
                    </div>
                @endif
                @if($totalAttempts)
                    <div class="hero-stat">
                        <span>Attempts</span>
                        <strong>{{ number_format($totalAttempts) }}</strong>
                    </div>
                @endif
                @if(!is_null($completionRate))
                    <div class="hero-stat">
                        <span>Completion rate</span>
                        <strong>{{ $completionRate }}%</strong>
                    </div>
                @endif
            </div>
        </div>
        <div class="hero-actions">
            <a href="{{ route('reports.journeys') }}" class="btn btn-light text-dark">
                <i class="bi bi-map"></i> Journey deep dive
            </a>
            <a href="{{ route('reports.users') }}" class="btn btn-outline-light">
                <i class="bi bi-people"></i> User drilldown
            </a>
        </div>
    </div>

    <div class="reports-grid">
        <div class="reports-card">
            <h3>Navigation</h3>
            <h4>Jump into detailed reporting.</h4>
            <div class="link-stack d-grid gap-2">
                <a href="{{ route('reports.journeys') }}" class="btn btn-outline-primary d-flex align-items-center gap-2">
                    <i class="bi bi-map"></i> Journey reports
                </a>
                <a href="{{ route('reports.users') }}" class="btn btn-outline-success d-flex align-items-center gap-2">
                    <i class="bi bi-people"></i> User reports
                </a>
            </div>
        </div>

        @if($popularJourneys && $popularJourneys->count() > 0)
            <div class="reports-card">
                <h3>Highlights</h3>
                <h4>Popular journeys right now.</h4>
                <div class="popular-list">
                    @foreach($popularJourneys as $journey)
                        <div class="popular-item">
                            <div>
                                <h6 class="mb-0">{{ $journey->title }}</h6>
                                <small class="text-muted">{{ $journey->collection->name ?? 'Uncategorized' }}</small>
                            </div>
                            <span class="badge bg-primary rounded-pill">{{ $journey->attempts_count }} attempts</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    @if($recentActivity && $recentActivity->count() > 0)
        <div class="full-width-card">
            <h4>Recent activity</h4>
            <h3 class="h4 mb-3">Live journey attempts</h3>
            <div class="table-responsive">
                <table class="table table-modern align-middle">
                    <thead>
                        <tr>
                            <th scope="col">User</th>
                            <th scope="col">Journey</th>
                            <th scope="col">Status</th>
                            <th scope="col">Started</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentActivity as $attempt)
                            <tr>
                                <td class="fw-semibold">{{ $attempt->user->name }}</td>
                                <td>{{ $attempt->journey->title }}</td>
                                <td>
                                    <span class="badge {{ $attempt->status === 'completed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ ucfirst($attempt->status) }}
                                    </span>
                                </td>
                                <td>{{ $attempt->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if(!empty($userRoles))
        <div class="full-width-card">
            <h4>User composition</h4>
            <h3 class="h4 mb-3">Roles distribution</h3>
            <div class="roles-list">
                @foreach($userRoles as $role => $count)
                    <div class="role-row">
                        <div class="badge bg-{{ 
                            $role === 'administrator' ? 'danger' : 
                            ($role === 'institution' ? 'warning text-dark' : 
                            ($role === 'editor' ? 'info text-dark' : 'secondary')) 
                        }}">
                            {{ ucfirst($role) }}
                        </div>
                        <strong>{{ number_format($count) }}</strong>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
@endsection
