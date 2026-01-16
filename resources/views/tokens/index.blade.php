@extends('layouts.app')

@section('content')
<section class="shell">
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
                                <div class="card">
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
