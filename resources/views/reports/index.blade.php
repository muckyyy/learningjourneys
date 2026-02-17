@extends('layouts.app')

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

<section class="shell">
    <div class="reports-grid">
        <div class="card">
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
            <div class="card">
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
