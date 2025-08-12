@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">
                <i class="bi bi-graph-up"></i> Reports Dashboard
            </h1>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                @if(isset($stats['total_users']))
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $stats['total_users'] }}</h4>
                                        <p class="card-text">Total Users</p>
                                    </div>
                                    <i class="bi bi-people fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(isset($stats['total_journeys']))
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $stats['total_journeys'] }}</h4>
                                        <p class="card-text">Total Journeys</p>
                                    </div>
                                    <i class="bi bi-map fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(isset($stats['total_attempts']))
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $stats['total_attempts'] }}</h4>
                                        <p class="card-text">Total Attempts</p>
                                    </div>
                                    <i class="bi bi-play-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if(isset($stats['average_completion_rate']))
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title">{{ $stats['average_completion_rate'] }}%</h4>
                                        <p class="card-text">Completion Rate</p>
                                    </div>
                                    <i class="bi bi-check-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Quick Links -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart"></i> Detailed Reports
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="{{ route('reports.journeys') }}" class="btn btn-outline-primary">
                                    <i class="bi bi-map"></i> Journey Reports
                                </a>
                                <a href="{{ route('reports.users') }}" class="btn btn-outline-success">
                                    <i class="bi bi-people"></i> User Reports
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                @if(isset($stats['popular_journeys']) && $stats['popular_journeys']->count() > 0)
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-star"></i> Popular Journeys
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group list-group-flush">
                                    @foreach($stats['popular_journeys'] as $journey)
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1">{{ $journey->title }}</h6>
                                                <small class="text-muted">{{ $journey->collection->name ?? 'N/A' }}</small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill">
                                                {{ $journey->attempts_count }} attempts
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if(isset($stats['recent_activity']) && $stats['recent_activity']->count() > 0)
                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-clock-history"></i> Recent Activity
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>User</th>
                                                <th>Journey</th>
                                                <th>Status</th>
                                                <th>Started</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($stats['recent_activity'] as $attempt)
                                                <tr>
                                                    <td>{{ $attempt->user->name }}</td>
                                                    <td>{{ $attempt->journey->title }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ $attempt->status === 'completed' ? 'success' : 'warning' }}">
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
                        </div>
                    </div>
                </div>
            @endif

            @if(isset($stats['user_roles']))
                <!-- User Roles Distribution -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-pie-chart"></i> User Roles Distribution
                                </h5>
                            </div>
                            <div class="card-body">
                                @foreach($stats['user_roles'] as $role => $count)
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-{{ 
                                            $role === 'administrator' ? 'danger' : 
                                            ($role === 'institution' ? 'warning' : 
                                            ($role === 'editor' ? 'info' : 'secondary')) 
                                        }}">
                                            {{ ucfirst($role) }}
                                        </span>
                                        <span class="fw-bold">{{ $count }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
