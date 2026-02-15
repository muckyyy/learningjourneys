@extends('layouts.app')

@section('content')
@php
    $firstName = \Illuminate\Support\Str::of($user->name)->before(' ');
    $firstName = $firstName->isNotEmpty() ? $firstName : $user->name;
@endphp

<section class="shell">

    {{-- ── Token balance row ─────────────────────────────────── --}}
    <div class="stat-grid mb-4">
        <div class="stat-card">
            <span>Tokens available</span>
            <h3>{{ number_format($balance['total']) }}</h3>
        </div>
        <div class="stat-card">
            <span>Expiring soon</span>
            <h3>{{ number_format($balance['expiring_soon']) }}</h3>
        </div>
        <div class="stat-card">
            <span>Active grants</span>
            <h3>{{ $activeGrants->count() }}</h3>
        </div>
        <div class="stat-card">
            <span>Transactions</span>
            <h3>{{ $transactions->count() }}</h3>
        </div>
    </div>

    {{-- ── Journey stats row ─────────────────────────────────── --}}
    <div class="stat-grid mb-4">
        <div class="stat-card">
            <span>Total attempts</span>
            <h3>{{ number_format($totalAttempts) }}</h3>
        </div>
        <div class="stat-card">
            <span>Completed</span>
            <h3>{{ number_format($completed) }}</h3>
        </div>
        <div class="stat-card">
            <span>In progress</span>
            <h3>{{ number_format($inProgress) }}</h3>
        </div>
        <div class="stat-card">
            <span>Completion rate</span>
            <h3>{{ $completionRate }}%</h3>
        </div>
    </div>

    <div class="row g-4">

        {{-- ── Journey attempts ──────────────────────────────── --}}
        <div class="col-lg-7">
            <div class="full-width-card mt-0">
                <h4>Journeys</h4>
                <h3 class="h4 mb-3">Your recent attempts</h3>

                @if($attempts->count())
                    <div class="table-responsive">
                        <table class="table table-modern align-middle">
                            <thead>
                                <tr>
                                    <th>Journey</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Score</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($attempts->take(15) as $attempt)
                                    <tr>
                                        <td class="fw-semibold">{{ $attempt->journey->title ?? '—' }}</td>
                                        <td class="text-center">
                                            @if($attempt->status === 'completed')
                                                <span class="badge bg-success">Completed</span>
                                            @elseif($attempt->status === 'in_progress')
                                                <span class="badge bg-warning text-dark">In progress</span>
                                            @else
                                                <span class="badge bg-secondary">{{ ucfirst($attempt->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($attempt->score !== null)
                                                <strong>{{ round($attempt->score, 1) }}%</strong>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td class="text-muted small">{{ $attempt->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-journal-x" style="font-size:2rem;"></i>
                        <p class="mt-2 mb-0">You haven't attempted any journeys yet.</p>
                        <a href="{{ route('journeys.index') }}" class="btn btn-primary btn-sm mt-3">Browse journeys</a>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Token activity ────────────────────────────────── --}}
        <div class="col-lg-5">

            {{-- Active grants --}}
            <div class="full-width-card mt-0 mb-4">
                <h4>Token grants</h4>
                <h3 class="h4 mb-3">Active balances</h3>

                @if($activeGrants->count())
                    <div class="grant-list">
                        @foreach($activeGrants as $grant)
                            <div class="grant-row">
                                <div>
                                    <strong>{{ number_format($grant->tokens_remaining) }}</strong>
                                    <span class="text-muted small">/ {{ number_format($grant->tokens_total) }} tokens</span>
                                </div>
                                <div class="text-end small text-muted">
                                    @if($grant->expires_at)
                                        Expires {{ $grant->expires_at->diffForHumans() }}
                                    @else
                                        No expiry
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No active token grants.</p>
                @endif
            </div>

            {{-- Transaction history --}}
            <div class="full-width-card mt-0">
                <h4>Transactions</h4>
                <h3 class="h4 mb-3">Recent activity</h3>

                @if($transactions->count())
                    <div class="transaction-list">
                        @foreach($transactions as $tx)
                            <div class="transaction-row">
                                <div class="d-flex align-items-center gap-2">
                                    @if($tx->type === 'credit')
                                        <span class="badge bg-success">+{{ $tx->amount }}</span>
                                    @else
                                        <span class="badge bg-danger">-{{ $tx->amount }}</span>
                                    @endif
                                    <span class="small">{{ $tx->description }}</span>
                                </div>
                                <span class="text-muted small">{{ $tx->occurred_at ? $tx->occurred_at->diffForHumans() : $tx->created_at->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-muted mb-0">No transactions yet.</p>
                @endif
            </div>

        </div>
    </div>

</section>
@endsection
