@extends('layouts.app')

@section('content')
@php
    $fmtMins = function ($seconds) {
        if (!$seconds || $seconds <= 0) {
            return 'N/A';
        }

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;

        return $minutes > 0
            ? sprintf('%dm %02ds', $minutes, $remainingSeconds)
            : sprintf('%ds', $remainingSeconds);
    };
@endphp

<section class="container-fluid px-3 px-md-4 py-4 journey-detail-report">
    <div class="detail-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <p class="eyebrow mb-2">Journey Report</p>
                <h1 class="h3 mb-2">{{ $journey->title }}</h1>
                <p class="text-muted mb-0">
                    {{ $journey->collection->name ?? 'Uncategorized' }}
                    <span class="mx-1">|</span>
                    {{ match($journey->difficulty_level ?? null) {
                        'beginner' => 'Introductiory',
                        'intermediate' => 'Intermediate',
                        'advanced' => 'Advanced',
                        default => ucfirst((string) ($journey->difficulty_level ?? 'Custom')),
                    } }}
                    <span class="mx-1">|</span>
                    {{ $journey->is_published ? 'Published' : 'Draft' }}
                </p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <a href="{{ route('reports.journeys') }}" class="btn btn-light">Back to Journey Reports</a>
            </div>
        </div>
    </div>

    <div class="row g-2 mb-3 metrics-row">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Total Attempts</p>
                <h3>{{ number_format($stats['total_attempts'] ?? 0) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Completion Rate</p>
                <h3>{{ number_format($stats['completion_rate'] ?? 0, 2) }}%</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Average Score (Step Rating)</p>
                <h3>{{ number_format($stats['avg_score'] ?? 0, 2) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Avg Feedback Rating</p>
                <h3>{{ number_format($stats['avg_rating'] ?? 0, 2) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Feedback Entries</p>
                <h3>{{ number_format($stats['feedback_count'] ?? 0) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Average Completion Time</p>
                <h3>{{ $fmtMins($stats['avg_completion_seconds'] ?? 0) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Token Consumption</p>
                <h3>{{ number_format($stats['token_spend_total'] ?? 0) }} ledger · {{ number_format($stats['prompt_tokens_total'] ?? 0) }} prompt</h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Step Score Trend (Average and Attempts)</h2>
                    @if(!empty($stepScoreChart['labels']) && count($stepScoreChart['labels']) > 0)
                        <div class="chart-wrapper" style="position: relative; height: 360px;">
                            <canvas id="stepScoreTrendChart" aria-label="Step score trend chart" role="img"></canvas>
                        </div>
                    @else
                        <p class="text-muted mb-0">No step score data available yet.</p>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Recent Attempts</h2>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Status</th>
                                    <th class="text-end">Score</th>
                                    <th class="text-end">Rating</th>
                                    <th class="text-end">Started</th>
                                    <th class="text-end">Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentAttempts as $attempt)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $attempt->user->name ?? 'Unknown user' }}</div>
                                            <div class="small text-muted">{{ $attempt->user->email ?? '-' }}</div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $attempt->status === 'completed' ? 'bg-success' : 'bg-secondary' }}">
                                                {{ ucfirst(str_replace('_', ' ', $attempt->status)) }}
                                            </span>
                                        </td>
                                        <td class="text-end">{{ $attempt->score !== null ? number_format((float) $attempt->score, 2) : 'N/A' }}</td>
                                        <td class="text-end">{{ $attempt->rating !== null ? number_format((float) $attempt->rating, 2) : 'N/A' }}</td>
                                        <td class="text-end">{{ optional($attempt->started_at)->format('Y-m-d H:i') ?? 'N/A' }}</td>
                                        <td class="text-end">{{ optional($attempt->completed_at)->format('Y-m-d H:i') ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No attempts yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($recentAttempts->hasPages())
                    <div class="card-footer bg-white border-0 pt-0">{{ $recentAttempts->links() }}</div>
                @endif
            </div>

            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                        <h2 class="h5 mb-0">User Feedback Texts</h2>
                    </div>

                    <form method="GET" action="{{ route('reports.journeys.show', $journey) }}" class="mb-3 feedback-filters feedback-filters-toolbar">
                        <div class="feedback-filter-field feedback-filter-rating">
                            <label for="feedback_rating" class="visually-hidden">Rating</label>
                            <select id="feedback_rating" name="feedback_rating" class="form-select form-select-sm" aria-label="Filter by rating">
                                <option value="">All</option>
                                <option value="high" @selected(request('feedback_rating') === 'high')>High (>= 4)</option>
                                <option value="mid" @selected(request('feedback_rating') === 'mid')>Mid (2.01 - 3.99)</option>
                                <option value="low" @selected(request('feedback_rating') === 'low')>Low (<= 2)</option>
                                <option value="unrated" @selected(request('feedback_rating') === 'unrated')>Unrated</option>
                            </select>
                        </div>
                        <div class="feedback-filter-field feedback-filter-search">
                            <label for="feedback_search" class="visually-hidden">Keyword</label>
                            <input
                                id="feedback_search"
                                type="text"
                                name="feedback_search"
                                value="{{ request('feedback_search') }}"
                                class="form-control form-control-sm"
                                placeholder="Search feedback text"
                            >
                        </div>
                        <div class="feedback-filter-actions">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                            <a href="{{ route('reports.journeys.show', $journey) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
                        </div>
                    </form>

                    <div class="vstack gap-3">
                        @forelse($feedbackEntries as $feedback)
                            <article class="feedback-entry">
                                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                    <div>
                                        <div class="fw-semibold">{{ $feedback->user->name ?? 'Unknown user' }}</div>
                                        <div class="small text-muted">{{ optional($feedback->updated_at)->format('Y-m-d H:i') }}</div>
                                    </div>
                                    <span class="badge bg-info text-dark">
                                        Rating: {{ $feedback->rating !== null ? number_format((float) $feedback->rating, 2) : 'N/A' }}
                                    </span>
                                </div>
                                <p class="mb-0 text-break">{{ $feedback->feedback }}</p>
                            </article>
                        @empty
                            <p class="text-muted mb-0">No feedback text submitted yet for this journey.</p>
                        @endforelse
                    </div>
                </div>
                @if($feedbackEntries->hasPages())
                    <div class="card-footer bg-white border-0 pt-0">{{ $feedbackEntries->links() }}</div>
                @endif
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">12-Week Attempt Trend</h2>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Year-Week</th>
                                    <th class="text-end">Attempts</th>
                                    <th class="text-end">Completed</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($weeklyTrend as $week)
                                    <tr>
                                        <td>{{ $week->year_week }}</td>
                                        <td class="text-end">{{ number_format($week->attempts_total) }}</td>
                                        <td class="text-end">{{ number_format($week->completed_total) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No trend data available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h2 class="h5 mb-3">Status Breakdown</h2>
                    <div class="vstack gap-2">
                        @forelse(($stats['status_breakdown'] ?? collect()) as $status => $count)
                            <div class="status-row">
                                <span class="text-muted">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                <strong>{{ number_format($count) }}</strong>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">No status records yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h2 class="h5 mb-3">Top Learners (3+ step ratings)</h2>
                    <div class="vstack gap-2">
                        @forelse($topLearners as $learner)
                            <div class="top-learner-row">
                                <div>
                                    <div class="fw-semibold">{{ $learner->user->name ?? 'Unknown user' }}</div>
                                    <div class="small text-muted">{{ $learner->attempts_count }} attempts</div>
                                </div>
                                <span class="badge bg-primary">{{ number_format((float) $learner->avg_score, 2) }}</span>
                            </div>
                        @empty
                            <p class="text-muted small mb-0">Need more scored attempts to rank learners.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

@php
    $stepScoreChartPayload = $stepScoreChart ?? [
        'labels' => [],
        'average' => [],
        'attempt_1' => [],
        'attempt_2' => [],
        'attempt_3' => [],
    ];
@endphp

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const canvas = document.getElementById('stepScoreTrendChart');
            if (!canvas || typeof Chart === 'undefined') {
                return;
            }

            const chartData = @json($stepScoreChartPayload);

            const hasData = Array.isArray(chartData.labels) && chartData.labels.length > 0;
            if (!hasData) {
                return;
            }

            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: chartData.labels,
                    datasets: [
                        {
                            label: 'Average Score',
                            data: chartData.average,
                            borderColor: '#0d6efd',
                            backgroundColor: 'rgba(13, 110, 253, 0.15)',
                            borderWidth: 3,
                            tension: 0.25,
                            spanGaps: true,
                            pointRadius: 4,
                        },
                        {
                            label: '1st Attempt',
                            data: chartData.attempt_1,
                            borderColor: '#198754',
                            backgroundColor: 'rgba(25, 135, 84, 0.1)',
                            borderWidth: 2,
                            tension: 0.25,
                            spanGaps: true,
                            pointRadius: 3,
                        },
                        {
                            label: '2nd Attempt',
                            data: chartData.attempt_2,
                            borderColor: '#fd7e14',
                            backgroundColor: 'rgba(253, 126, 20, 0.1)',
                            borderWidth: 2,
                            tension: 0.25,
                            spanGaps: true,
                            pointRadius: 3,
                        },
                        {
                            label: '3rd Attempt',
                            data: chartData.attempt_3,
                            borderColor: '#dc3545',
                            backgroundColor: 'rgba(220, 53, 69, 0.1)',
                            borderWidth: 2,
                            tension: 0.25,
                            spanGaps: true,
                            pointRadius: 3,
                        }
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 5,
                            title: {
                                display: true,
                                text: 'Score',
                            },
                        },
                        x: {
                            ticks: {
                                maxRotation: 35,
                                minRotation: 20,
                            },
                        },
                    },
                },
            });
        })();
    </script>
@endpush
@endsection

@push('styles')
<style>
    .journey-detail-report {
        --jdr-ink: #122030;
        --jdr-highlight: #0d6efd;
        --jdr-bg: linear-gradient(135deg, #f5f9ff 0%, #eef4ff 45%, #fff7ec 100%);
    }

    .journey-detail-report .detail-hero {
        background: var(--jdr-bg);
        border: 1px solid #dde8fd;
        border-radius: 1rem;
        padding: 1.4rem;
    }

    .journey-detail-report .eyebrow {
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--jdr-highlight);
        font-size: .75rem;
        font-weight: 700;
    }

    .journey-detail-report .metric-card {
        background: #fff;
        border: 1px solid #e8edf7;
        border-radius: .75rem;
        padding: .6rem .75rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .journey-detail-report .metric-card p {
        margin: 0;
        color: #5b677b;
        font-size: .72rem;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .journey-detail-report .metric-card h3 {
        margin: .2rem 0 0;
        color: var(--jdr-ink);
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.2;
    }

    @media (min-width: 1200px) {
        .journey-detail-report .metrics-row .col-lg-3 {
            flex: 0 0 auto;
            width: 16.6667%;
        }
    }

    .journey-detail-report .status-row,
    .journey-detail-report .top-learner-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: .8rem;
        border: 1px solid #e8edf7;
        border-radius: .7rem;
        padding: .65rem .8rem;
        background: #fff;
    }

    .journey-detail-report .feedback-entry {
        border: 1px solid #e8edf7;
        border-radius: .7rem;
        padding: .75rem .85rem;
        background: #fff;
    }

    .journey-detail-report .feedback-filters {
        border: 1px solid #e8edf7;
        border-radius: .7rem;
        padding: .55rem;
        background: #fafcff;
    }

    .journey-detail-report .feedback-filters-toolbar {
        display: grid;
        grid-template-columns: 1fr;
        gap: .45rem;
    }

    .journey-detail-report .feedback-filter-actions {
        display: flex;
        gap: .45rem;
    }

    .journey-detail-report .feedback-filter-actions .btn {
        flex: 1;
    }

    @media (min-width: 992px) {
        .journey-detail-report .feedback-filters-toolbar {
            grid-template-columns: 220px 1fr auto;
            align-items: center;
            gap: .55rem;
        }

        .journey-detail-report .feedback-filter-actions .btn {
            flex: 0 0 auto;
            min-width: 88px;
        }
    }
</style>
@endpush
