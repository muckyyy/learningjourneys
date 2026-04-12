@extends('layouts.app')

@section('content')
@php
    $tc = $trendCharts ?? [];
@endphp

<section class="container-fluid px-3 px-md-4 py-4 trends-report-page">

    {{-- ── Header ──────────────────────────────────────────────────── --}}
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
        <div>
            <p class="text-uppercase text-warning fw-semibold small mb-1">Admin Reports</p>
            <h1 class="h3 mb-1">Trends</h1>
            <p class="text-muted mb-0">Weekly KPI trends · last 26 weeks · cached 10 min</p>
        </div>
        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary">Back to Reports Home</a>
    </div>

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
                    <p class="small text-muted mb-3">Average step rating (1–5) across all scored responses per week</p>
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
                    <p class="small text-muted mb-3">Mean end-of-journey feedback rating (1–5) per week</p>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">No leaderboard data yet (requires ≥3 rated responses).</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
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
    .trends-report-page .card { border-radius: .85rem; }
</style>
@endpush
