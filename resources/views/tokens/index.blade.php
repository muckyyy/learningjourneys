@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="hero cyan mb-4">
        <div>
            <p class="text-uppercase small mb-1" style="letter-spacing: 0.18em;">Your balance</p>
            <h1 class="mb-1">{{ number_format($balance['total']) }} <span style="font-weight: 400; font-size: 0.5em;">tokens available</span></h1>
            @if($balance['expiring_soon'] > 0)
                <p class="text-white-50 mb-0"><i class="bi bi-clock me-1"></i>{{ number_format($balance['expiring_soon']) }} expiring soon</p>
            @endif
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
                        @unless($virtualVendorEnabled || $payrexxEnabled)
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
                                    <div class="d-flex flex-column gap-2 mt-auto">
                                        @if($payrexxEnabled)
                                            <form action="{{ route('payrexx.checkout') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                                                <button type="submit" class="btn btn-primary w-100 rounded-pill"
                                                    {{ !$bundle->is_active ? 'disabled' : '' }}>
                                                    <i class="bi bi-credit-card"></i> Purchase
                                                </button>
                                            </form>
                                        @endif
                                        @if($virtualVendorEnabled)
                                            <form action="{{ route('tokens.purchase') }}" method="POST">
                                                @csrf
                                                <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                                                <button type="submit" class="btn btn-outline-secondary w-100 rounded-pill"
                                                    {{ !$bundle->is_active ? 'disabled' : '' }}>
                                                    <i class="bi bi-bag"></i> Test Purchase
                                                </button>
                                            </form>
                                        @endif
                                    </div>
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
                                        <th></th>
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
                                                
                                                @if($transaction->journey)
                                                    <br><span class="text-muted">{{ $transaction->journey->title }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $transaction->occurred_at?->format('M d, Y') ?? $transaction->created_at->format('M d, Y') }}</td>
                                            <td>
                                                @if($transaction->type === 'credit' && $transaction->purchase && $transaction->purchase->status === 'completed')
                                                    <a href="{{ route('tokens.purchase.invoice', $transaction->purchase) }}"
                                                       class="btn btn-sm btn-outline-primary rounded-pill"
                                                       title="View invoice" target="_blank">
                                                        <i class="bi bi-receipt"></i>
                                                    </a>
                                                @endif
                                            </td>
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

    {{-- Purchases & Invoices --}}
    @if($purchases->count())
        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="glass-panel">
                    <div class="panel-body">
                        <div class="panel-header">
                            <div>
                                <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.2em;">Invoices</p>
                                <h4 class="mb-0"><i class="bi bi-receipt me-2"></i>Purchases &amp; Invoices</h4>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Bundle</th>
                                        <th>Tokens</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center">Invoice</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($purchases as $purchase)
                                        <tr>
                                            <td class="small text-muted">{{ ($purchase->purchased_at ?? $purchase->created_at)->format('d.m.Y') }}</td>
                                            <td class="fw-semibold">{{ $purchase->bundle->name ?? 'Token Bundle' }}</td>
                                            <td>{{ number_format($purchase->tokens) }}</td>
                                            <td class="text-end">{{ $purchase->currency ?? 'CHF' }} {{ number_format($purchase->amount_cents / 100, 2, '.', "'") }}</td>
                                            <td class="text-center">
                                                <a href="{{ route('tokens.purchase.invoice', $purchase) }}"
                                                   class="btn btn-sm btn-outline-primary rounded-pill"
                                                   title="View invoice" target="_blank">
                                                    <i class="bi bi-receipt"></i> Invoice
                                                </a>
                                            </td>
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
</section>
@endsection
