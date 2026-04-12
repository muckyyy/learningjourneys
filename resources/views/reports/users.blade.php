@extends('layouts.app')

@section('content')
<section class="container-fluid px-3 px-md-4 py-4 user-report-page">

    {{-- ── Header ──────────────────────────────────────────────────── --}}
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
        <div>
            <p class="text-uppercase text-success fw-semibold small mb-1">Admin Reports</p>
            <h1 class="h3 mb-1">User Reports</h1>
            <p class="text-muted mb-0">All users, activity, and capability data.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('reports.trends') }}" class="btn btn-outline-warning d-flex align-items-center gap-2">
                <i class="bi bi-graph-up-arrow"></i> View Trends
            </a>
            <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Back to Reports Home</a>
        </div>
    </div>

    {{-- ── Table filter ────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('reports.users') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="role" class="form-label">Role</label>
                <select id="role" name="role" class="form-select">
                    <option value="">All</option>
                    <option value="administrator" @selected(request('role') === 'administrator')>Administrator</option>
                    <option value="regular" @selected(request('role') === 'regular')>Regular</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                </select>
            </div>
            <div class="col-12 col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
                <a href="{{ route('reports.users') }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </div>
    </form>

    {{-- ── Most active + Most capable ──────────────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Most Active Users</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th class="text-end">Attempts</th>
                                    <th class="text-end">Completion</th>
                                    <th class="text-end">Avg Step Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($topActiveUsers ?? collect()) as $user)
                                    @php
                                        $completionRate = $user->journey_attempts_count > 0
                                            ? ($user->completed_attempts_count / $user->journey_attempts_count) * 100
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $user->name }}</div>
                                            <div class="small text-muted">{{ $user->email }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format($user->journey_attempts_count) }}</td>
                                        <td class="text-end">{{ number_format($completionRate, 2) }}%</td>
                                        <td class="text-end">{{ $user->avg_step_rate !== null ? number_format((float) $user->avg_step_rate, 2) : 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No active users data available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Most Capable Users</h2>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th class="text-end">Attempts</th>
                                    <th class="text-end">Completion</th>
                                    <th class="text-end">Avg Step Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($topCapableUsers ?? collect()) as $user)
                                    @php
                                        $completionRate = $user->journey_attempts_count > 0
                                            ? ($user->completed_attempts_count / $user->journey_attempts_count) * 100
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $user->name }}</div>
                                            <div class="small text-muted">{{ $user->email }}</div>
                                        </td>
                                        <td class="text-end">{{ number_format($user->journey_attempts_count) }}</td>
                                        <td class="text-end">{{ number_format($completionRate, 2) }}%</td>
                                        <td class="text-end">{{ $user->avg_step_rate !== null ? number_format((float) $user->avg_step_rate, 2) : 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">No capability data yet (requires at least 3 rated steps).</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── All users table ──────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th class="text-end">Attempts</th>
                            <th class="text-end">Completed</th>
                            <th class="text-end">Completion Rate</th>
                            <th class="text-end">Avg Step Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($users as $user)
                            @php
                                $completionRate = $user->journey_attempts_count > 0
                                    ? ($user->completed_attempts_count / $user->journey_attempts_count) * 100
                                    : 0;
                            @endphp
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <div class="small text-muted">{{ $user->email }}</div>
                                </td>
                                <td>{{ ucfirst($user->role) }}</td>
                                <td class="text-end">{{ number_format($user->journey_attempts_count) }}</td>
                                <td class="text-end">{{ number_format($user->completed_attempts_count) }}</td>
                                <td class="text-end">{{ number_format($completionRate, 2) }}%</td>
                                <td class="text-end">{{ $user->avg_step_rate !== null ? number_format((float) $user->avg_step_rate, 2) : 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">No users found for selected filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($users->hasPages())
            <div class="card-footer bg-white border-0 pt-0">
                {{ $users->links() }}
            </div>
        @endif
    </div>

</section>
@endsection

@push('styles')
<style>
    .user-report-page .card { border-radius: .85rem; }
</style>
@endpush
