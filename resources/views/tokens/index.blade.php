@extends('layouts.app')

@push('styles')
<style>
.tokens-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
}
.tokens-hero {
    background: linear-gradient(130deg, #0f172a, #0ea5e9 80%);
    border-radius: 36px;
    color: #fff;
    padding: clamp(2rem, 6vw, 4rem);
    display: flex;
    flex-wrap: wrap;
    gap: 2rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(14, 165, 233, 0.25);
    margin-bottom: 2.75rem;
}
.tokens-hero h1 {
    font-size: clamp(2.4rem, 5vw, 3.4rem);
    margin-bottom: 0.35rem;
}
.tokens-hero p {
    color: rgba(255, 255, 255, 0.78);
    margin-bottom: 0.25rem;
}
.hero-metrics {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
    min-width: 220px;
}
.hero-pill {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    padding: 0.55rem 1.35rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 0.78rem;
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
.glass-panel {
    border-radius: 32px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    margin-bottom: 2rem;
}
.glass-panel .panel-body {
    padding: clamp(1.75rem, 4vw, 2.5rem);
}
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}
.bundle-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
}
.bundle-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 24px;
    padding: 1.5rem;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.bundle-card h6 {
    font-weight: 700;
}
.bundle-amount {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
}
.bundle-meta {
    color: #64748b;
    font-size: 0.9rem;
}
.transactions-table table {
    margin: 0;
}
.transactions-table thead th {
    border: none;
    font-size: 0.78rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #94a3b8;
}
.transactions-table tbody td {
    border-top: 1px solid rgba(15, 23, 42, 0.06);
    vertical-align: middle;
}
.status-chip {
    border-radius: 12px;
    padding: 0.25rem 0.7rem;
    font-size: 0.75rem;
    font-weight: 600;
}
.vendor-alert {
    border-radius: 18px;
    padding: 0.75rem 1rem;
    background: rgba(251, 191, 36, 0.18);
    color: #92400e;
    font-weight: 600;
    margin-bottom: 1rem;
}
@media (max-width: 991.98px) {
    .glass-panel .panel-body {
        padding: 1.5rem;
    }
}
@media (max-width: 575.98px) {
    .hero-actions .btn {
        width: 100%;
    }
}
</style>
@endpush

@section('content')
<section class="tokens-shell">
    <div class="tokens-hero">
        <div class="flex-grow-1">
            <div class="hero-pill mb-3"><i class="bi bi-coin"></i> Tokens</div>
            <h1>{{ number_format($balance['total']) }} tokens</h1>
            <p>{{ $balance['expiring_soon'] > 0 ? $balance['expiring_soon'] . ' expiring soon' : 'All tokens active for now' }}</p>
            <div class="hero-actions">
                <a href="{{ route('dashboard') }}" class="btn btn-light text-dark">
                    <i class="bi bi-speedometer"></i> Back to dashboard
                </a>
                <a href="{{ route('journeys.index') }}" class="btn btn-outline-light">
                    <i class="bi bi-compass"></i> Explore journeys
                </a>
            </div>
        </div>
        <div class="hero-metrics text-white-50">
            <div>
                <small class="text-uppercase">Virtual vendor</small>
                <p class="fs-5 mb-0 text-white">{{ $virtualVendorEnabled ? 'Enabled' : 'Disabled' }}</p>
            </div>
            <div>
                <small class="text-uppercase">Account</small>
                <p class="mb-0">{{ auth()->user()->email }}</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-panel h-100">
                <div class="panel-body">
                    <div class="panel-header">
                        <div>
                            <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Bundles</p>
                            <h4 class="mb-0"><i class="bi bi-bag me-2"></i>Token bundles</h4>
                        </div>
                        @unless($virtualVendorEnabled)
                            <span class="vendor-alert"><i class="bi bi-exclamation-triangle"></i> Purchases disabled</span>
                        @endunless
                    </div>
                    @if($bundles->isEmpty())
                        <p class="text-muted mb-0">No bundles configured yet.</p>
                    @else
                        <div class="bundle-grid">
                            @foreach($bundles as $bundle)
                                <div class="bundle-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <h6 class="mb-0">{{ $bundle->name }}</h6>
                                        <span class="status-chip bg-{{ $bundle->is_active ? 'success' : 'secondary' }} text-white">{{ $bundle->is_active ? 'Active' : 'Hidden' }}</span>
                                    </div>
                                    <p class="bundle-amount">{{ number_format($bundle->token_amount) }}</p>
                                    <p class="bundle-meta mb-2">Tokens · expires {{ $bundle->expires_after_days }} days after purchase</p>
                                    <p class="fw-semibold mb-3">{{ $bundle->currency }} {{ number_format($bundle->price_cents / 100, 2) }}</p>
                                    <form action="{{ route('tokens.purchase') }}" method="POST" class="mt-auto">
                                        @csrf
                                        <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                                        <button type="submit" class="btn btn-dark w-100 rounded-pill"
                                            {{ !$virtualVendorEnabled || !$bundle->is_active ? 'disabled' : '' }}>
                                            <i class="bi bi-credit-card"></i> Purchase
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="glass-panel h-100">
                <div class="panel-body">
                    <div class="panel-header">
                        <div>
                            <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">History</p>
                            <h4 class="mb-0"><i class="bi bi-journal-text me-2"></i>Recent transactions</h4>
                        </div>
                    </div>
                    @if($transactions->isEmpty())
                        <p class="text-muted mb-0">No transactions yet.</p>
                    @else
                        <div class="transactions-table table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Details</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transactions as $transaction)
                                        <tr>
                                            <td>
                                                <span class="badge rounded-pill {{ $transaction->type === 'credit' ? 'bg-success' : 'bg-danger' }}">
                                                    {{ ucfirst($transaction->type) }}
                                                </span>
                                            </td>
                                            <td class="fw-semibold">{{ $transaction->type === 'debit' ? '-' : '+' }}{{ $transaction->amount }}</td>
                                            <td class="small">
                                                {{ $transaction->description ?? '—' }}
                                                @if($transaction->journey)
                                                    <br><span class="text-muted">{{ $transaction->journey->title }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $transaction->occurred_at?->format('M d, Y') ?? $transaction->created_at->format('M d, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
