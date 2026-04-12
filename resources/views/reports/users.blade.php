@extends('layouts.app')

@section('content')
@php
    $tc = $trendCharts ?? [];
@endphp
<section class="container-fluid px-3 px-md-4 py-4 user-report-page">

    {{-- ── Header ──────────────────────────────────────────────────── --}}
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
        <div>
            <p class="text-uppercase text-primary fw-semibold small mb-1">Admin Reports</p>
            <h1 class="h3 mb-1">User Reports</h1>
            <p class="text-muted mb-0">Weekly KPI trends · last 26 weeks · cached 10 min</p>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Back to Reports Home</a>
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

    {{-- ── Row 1: Active users + New registrations ─────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">Weekly Active Users</h2>
                    <p class="small text-muted mb-3">Users who started at least one journey attempt per week</p>
                    <div style="position:relative;height:220px;"><canvas id="chartActiveUsers"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">New Registrations</h2>
                    <p class="small text-muted mb-3">New user accounts per week</p>
                    <div style="position:relative;height:220px;"><canvas id="chartNewUsers"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 2: Completion rate + Avg step score ──────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">Completion Rate Trend</h2>
                    <p class="small text-muted mb-3">% of all journey attempts completed per week</p>
                    <div style="position:relative;height:220px;"><canvas id="chartCompletion"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">Avg Step Score Trend</h2>
                    <p class="small text-muted mb-3">Average step rating (1-5) across all scored responses per week</p>
                    <div style="position:relative;height:220px;"><canvas id="chartStepScore"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 3: Avg rating + Attempts per user ────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">Avg Feedback Rating Trend</h2>
                    <p class="small text-muted mb-3">Mean end-of-journey feedback rating (1-5) per week</p>
                    <div style="position:relative;height:220px;"><canvas id="chartRating"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">Attempts per Active User</h2>
                    <p class="small text-muted mb-3">Average number of journey attempts per distinct active user per week</p>
                    <div style="position:relative;height:220px;"><canvas id="chartAttemptsPerUser"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Row 4: Token spend + Cohort retention ────────────────────── --}}
    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">Token Spend per User</h2>
                    <p class="small text-muted mb-3">Average ledger tokens debited per active user per week</p>
                    <div style="position:relative;height:220px;"><canvas id="chartTokens"></canvas></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-muted fw-semibold mb-1">First-Attempt Cohort Retention</h2>
                    <p class="small text-muted mb-3">% of signup cohort who attempted a journey at week 1 and week 4</p>
                    @if(!empty($tc['cohortLabels']))
                        <div style="position:relative;height:220px;"><canvas id="chartCohort"></canvas></div>
                    @else
                        <p class="text-muted">Not enough cohort data yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ── Top leaderboard ──────────────────────────────────────────── --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h2 class="h5 mb-1">Top Users Leaderboard</h2>
            <p class="small text-muted mb-3">Best average step score · min 3 rated responses</p>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th class="text-end">Avg Score</th>
                            <th class="text-end">Rated Responses</th>
                            <th class="text-end">Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($tc['leaderboard'] ?? collect()) as $i => $row)
                            <tr>
                                <td class="text-muted fw-semibold">{{ $i + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $row->name }}</div>
                                    <div class="small text-muted">{{ $row->email }}</div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-primary">{{ number_format((float)$row->avg_score, 2) }}</span>
                                </td>
                                <td class="text-end">{{ number_format($row->rated_count) }}</td>
                                <td class="text-end">
                                    <a href="{{ route('reports.users') }}?role=&status=" class="btn btn-sm btn-outline-secondary">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-3 text-muted">No leaderboard data yet (requires ≥3 rated responses).</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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

@php
    $chartTc = $trendCharts ?? [
        'labels'          => [],
        'activeUsers'     => [],
        'newUsers'        => [],
        'completionRate'  => [],
        'avgStepScore'    => [],
        'avgRating'       => [],
        'attemptsPerUser' => [],
        'tokenPerUser'    => [],
        'cohortLabels'    => [],
        'cohortWeek0'     => [],
        'cohortWeek1'     => [],
        'cohortWeek4'     => [],
    ];
@endphp

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
(function () {
    const d = @json($chartTc);

    const sharedOpts = (yLabel, suggestedMax) => ({
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: { legend: { display: false } },
        scales: {
            x: { ticks: { maxRotation: 45, minRotation: 30, font: { size: 10 } } },
            y: { beginAtZero: true, suggestedMax: suggestedMax ?? undefined,
                 title: { display: !!yLabel, text: yLabel } }
        }
    });

    const line = (id, label, data, color, yLabel, max) => {
        const el = document.getElementById(id);
        if (!el) return;
        new Chart(el, {
            type: 'line',
            data: {
                labels: d.labels,
                datasets: [{ label, data, borderColor: color,
                    backgroundColor: color + '22', borderWidth: 2,
                    tension: 0.3, spanGaps: true, pointRadius: 3 }]
            },
            options: sharedOpts(yLabel, max)
        });
    };

    line('chartActiveUsers',     'Active users',           d.activeUsers,     '#0d6efd', 'Users');
    line('chartNewUsers',        'New registrations',      d.newUsers,        '#198754', 'Users');
    line('chartCompletion',      'Completion rate (%)',    d.completionRate,  '#fd7e14', '%', 100);
    line('chartStepScore',       'Avg step score',         d.avgStepScore,    '#6f42c1', 'Score (1-5)', 5);
    line('chartRating',          'Avg feedback rating',    d.avgRating,       '#20c997', 'Rating (1-5)', 5);
    line('chartAttemptsPerUser', 'Attempts / active user', d.attemptsPerUser, '#0dcaf0', 'Attempts');
    line('chartTokens',          'Tokens / user',          d.tokenPerUser,    '#dc3545', 'Tokens');

    // Cohort retention – grouped bar
    const cohortEl = document.getElementById('chartCohort');
    if (cohortEl && d.cohortLabels && d.cohortLabels.length > 0) {
        new Chart(cohortEl, {
            type: 'bar',
            data: {
                labels: d.cohortLabels,
                datasets: [
                    { label: 'Cohort size', data: d.cohortWeek0, backgroundColor: '#0d6efd44', borderColor: '#0d6efd', borderWidth: 1 },
                    { label: 'Week 1 retention (%)', data: d.cohortWeek1, backgroundColor: '#19875488', borderColor: '#198754', borderWidth: 1 },
                    { label: 'Week 4 retention (%)', data: d.cohortWeek4, backgroundColor: '#fd7e1488', borderColor: '#fd7e14', borderWidth: 1 },
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', labels: { font: { size: 11 } } } },
                scales: {
                    x: { ticks: { maxRotation: 45, minRotation: 30, font: { size: 10 } } },
                    y: { beginAtZero: true,
                         title: { display: true, text: 'Users / %' } }
                }
            }
        });
    }
})();
</script>
@endpush

@push('styles')
<style>
    .user-report-page .card { border-radius: .85rem; }
</style>
@endpush
