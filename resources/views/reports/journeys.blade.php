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

<section class="container-fluid px-3 px-md-4 py-4 journey-reports">
    <div class="hero-card mb-4">
        <div class="d-flex flex-column flex-lg-row align-items-lg-end justify-content-between gap-3">
            <div>
                <p class="eyebrow mb-2">Admin Reports</p>
                <h1 class="h3 mb-2">Journey Performance Dashboard</h1>
                <p class="text-muted mb-0">Track completion, token usage, and learning quality across all journeys.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('reports.index') }}" class="btn btn-light">Back to Reports Home</a>
                <a href="{{ route('reports.users') }}" class="btn btn-outline-light">User Reports</a>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('reports.journeys') }}" class="card border-0 shadow-sm mb-4">
        <div class="card-body row g-3 align-items-end">
            <div class="col-12 col-md-3">
                <label for="difficulty" class="form-label">Difficulty</label>
                <select id="difficulty" name="difficulty" class="form-select">
                    <option value="">All</option>
                    <option value="beginner" @selected(request('difficulty') === 'beginner')>Beginner</option>
                    <option value="intermediate" @selected(request('difficulty') === 'intermediate')>Intermediate</option>
                    <option value="advanced" @selected(request('difficulty') === 'advanced')>Advanced</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label for="status" class="form-label">Publication</label>
                <select id="status" name="status" class="form-select">
                    <option value="">All</option>
                    <option value="published" @selected(request('status') === 'published')>Published</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label for="sort" class="form-label">Sort by</label>
                <select id="sort" name="sort" class="form-select">
                    <option value="most_attempted" @selected(($sort ?? 'most_attempted') === 'most_attempted')>Most attempted</option>
                    <option value="top_rated" @selected(($sort ?? '') === 'top_rated')>Top rated</option>
                    <option value="most_tokens" @selected(($sort ?? '') === 'most_tokens')>Highest token spend</option>
                    <option value="fastest_completion" @selected(($sort ?? '') === 'fastest_completion')>Fastest completion</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Apply</button>
                <a href="{{ route('reports.journeys') }}" class="btn btn-outline-secondary w-100">Reset</a>
            </div>
        </div>
    </form>

    <div class="row g-2 mb-3 metrics-row">
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Total Journeys</p>
                <h3>{{ number_format($summary['total_journeys'] ?? 0) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Total Attempts</p>
                <h3>{{ number_format($summary['total_attempts'] ?? 0) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Completion Rate</p>
                <h3>{{ number_format($summary['completion_rate'] ?? 0, 2) }}%</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Average Score (Step Rating)</p>
                <h3>{{ number_format($summary['avg_score'] ?? 0, 2) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Average Completion Time</p>
                <h3>{{ $fmtMins($summary['avg_completion_seconds'] ?? 0) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Token Spend (Ledger)</p>
                <h3>{{ number_format($summary['token_spend_total'] ?? 0) }}</h3>
            </div>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <div class="metric-card">
                <p>Prompt Tokens (AI Usage)</p>
                <h3>{{ number_format($summary['prompt_tokens_total'] ?? 0) }}</h3>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-9">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Journey</th>
                                    <th class="text-end">Attempts</th>
                                    <th class="text-end">Completion</th>
                                    <th class="text-end">Avg Score</th>
                                    <th class="text-end">Avg Feedback</th>
                                    <th class="text-end">Avg Time</th>
                                    <th class="text-end">Token Spend</th>
                                    <th class="text-end">Prompt Tokens</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($journeys as $journey)
                                    @php
                                        $completionRate = $journey->attempts_count > 0
                                            ? ($journey->completed_attempts_count / $journey->attempts_count) * 100
                                            : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $journey->title }}</div>
                                            <div class="small text-muted">
                                                {{ $journey->collection->name ?? 'Uncategorized' }}
                                                <span class="mx-1">|</span>
                                                {{ ucfirst($journey->difficulty_level) }}
                                            </div>
                                        </td>
                                        <td class="text-end">{{ number_format($journey->attempts_count) }}</td>
                                        <td class="text-end">{{ number_format($completionRate, 1) }}%</td>
                                        <td class="text-end">{{ number_format((float) ($journey->avg_score ?? 0), 2) }}</td>
                                        <td class="text-end">{{ $journey->avg_rating !== null ? number_format((float) $journey->avg_rating, 2) : 'N/A' }}</td>
                                        <td class="text-end">{{ $fmtMins((int) ($journey->avg_completion_seconds ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format((int) ($journey->token_spend_total ?? 0)) }}</td>
                                        <td class="text-end">{{ number_format((int) ($journey->prompt_tokens_total ?? 0)) }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('reports.journeys.show', $journey) }}" class="btn btn-sm btn-outline-primary">View report</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center py-5 text-muted">No journeys match your filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($journeys->hasPages())
                    <div class="card-footer bg-white border-0 pt-0">
                        {{ $journeys->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-12 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h5 mb-3">Top Rated Journeys</h2>
                    <div class="vstack gap-2">
                        @forelse(($summary['top_rated'] ?? collect()) as $top)
                            <a href="{{ route('reports.journeys.show', $top) }}" class="top-rated-item text-decoration-none">
                                <div class="fw-semibold text-dark">{{ $top->title }}</div>
                                <div class="small text-muted">Score {{ number_format((float) ($top->avg_score ?? 0), 2) }} · {{ $top->attempts_count }} attempts</div>
                            </a>
                        @empty
                            <p class="text-muted small mb-0">Not enough data yet. Top-rated requires at least 3 step ratings.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('styles')
<style>
    .journey-reports {
        --jr-accent: #0b5ed7;
        --jr-ink: #10213a;
        --jr-bg: linear-gradient(140deg, #e8f1ff 0%, #f8fbff 50%, #fff8ee 100%);
    }

    .journey-reports .hero-card {
        background: var(--jr-bg);
        border: 1px solid #dce8ff;
        border-radius: 1rem;
        padding: 1.5rem;
    }

    .journey-reports .eyebrow {
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--jr-accent);
        font-size: .75rem;
        font-weight: 700;
    }

    .journey-reports .metric-card {
        background: #fff;
        border: 1px solid #ebeff7;
        border-radius: .75rem;
        padding: .6rem .75rem;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .journey-reports .metric-card p {
        margin: 0;
        color: #5a6578;
        font-size: .72rem;
        line-height: 1.2;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .journey-reports .metric-card h3 {
        margin: .2rem 0 0;
        color: var(--jr-ink);
        font-size: 1.05rem;
        font-weight: 700;
        line-height: 1.2;
    }

    @media (min-width: 1200px) {
        .journey-reports .metrics-row .col-lg-3 {
            flex: 0 0 auto;
            width: 14.2857%;
        }
    }

    .journey-reports .top-rated-item {
        display: block;
        padding: .7rem;
        border: 1px solid #e5e9f2;
        border-radius: .7rem;
        transition: all .18s ease;
    }

    .journey-reports .top-rated-item:hover {
        border-color: #c9d7f7;
        background: #f7faff;
        transform: translateY(-1px);
    }
</style>
@endpush
