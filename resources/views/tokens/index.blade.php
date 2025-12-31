@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="token-hero card shadow-sm border-0 mb-4" style="background: linear-gradient(135deg, #0f1624, #263b5e); color:#fff;">
                <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                    <div>
                        <p class="text-uppercase small mb-1 opacity-75">Current Balance</p>
                        <h1 class="display-4 mb-0">{{ number_format($balance['total']) }} tokens</h1>
                        <p class="mb-0 opacity-75">{{ $balance['expiring_soon'] > 0 ? $balance['expiring_soon'] . ' expiring soon' : 'All tokens active for now' }}</p>
                    </div>
                    <div class="text-md-end mt-4 mt-md-0">
                        <p class="mb-1">Virtual Vendor: <strong>{{ $virtualVendorEnabled ? 'Enabled' : 'Disabled' }}</strong></p>
                        <a href="{{ route('dashboard') }}" class="btn btn-outline-light">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card shadow-sm mb-4 h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-bag"></i> Token Bundles</h5>
                    @unless($virtualVendorEnabled)
                        <span class="badge bg-warning text-dark">Purchases disabled</span>
                    @endunless
                </div>
                <div class="card-body">
                    @if($bundles->isEmpty())
                        <p class="text-muted mb-0">No bundles configured yet.</p>
                    @else
                        <div class="row g-3">
                            @foreach($bundles as $bundle)
                                <div class="col-md-6">
                                    <div class="border rounded p-3 h-100 d-flex flex-column justify-content-between">
                                        <div>
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h6 class="mb-0">{{ $bundle->name }}</h6>
                                                <span class="badge bg-{{ $bundle->is_active ? 'success' : 'secondary' }}">{{ $bundle->is_active ? 'Active' : 'Hidden' }}</span>
                                            </div>
                                            <p class="display-6 fw-bold mb-1">{{ number_format($bundle->token_amount) }}</p>
                                            <p class="text-muted small mb-2">tokens &middot; expires {{ $bundle->expires_after_days }} days after purchase</p>
                                            <p class="mb-0 fw-semibold">{{ $bundle->currency }} {{ number_format($bundle->price_cents / 100, 2) }}</p>
                                        </div>
                                        <form action="{{ route('tokens.purchase') }}" method="POST" class="mt-3">
                                            @csrf
                                            <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                                            <button type="submit" class="btn btn-primary w-100" {{ !$virtualVendorEnabled || !$bundle->is_active ? 'disabled' : '' }}>
                                                <i class="bi bi-credit-card"></i> Purchase
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-journal-text"></i> Recent Transactions</h5>
                </div>
                <div class="card-body p-0">
                    @if($transactions->isEmpty())
                        <p class="text-muted p-3 mb-0">No transactions yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
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
                                                <span class="badge {{ $transaction->type === 'credit' ? 'bg-success' : 'bg-danger' }}">
                                                    {{ ucfirst($transaction->type) }}
                                                </span>
                                            </td>
                                            <td>{{ $transaction->type === 'debit' ? '-' : '+' }}{{ $transaction->amount }}</td>
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
</div>
@endsection
