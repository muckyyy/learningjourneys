@extends('layouts.app')

@push('styles')
<style>
.token-admin-shell {
    width: min(1200px, 100%);
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
}
.token-admin-hero {
    background: linear-gradient(135deg, #0f172a, #0ea5e9 70%);
    border-radius: 36px;
    color: #fff;
    padding: clamp(2rem, 5vw, 4rem);
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(14, 165, 233, 0.35);
    margin-bottom: 2.5rem;
}
.token-admin-hero h1 {
    font-size: clamp(2rem, 4.5vw, 3rem);
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.5rem;
}
.meta-card {
    background: rgba(255, 255, 255, 0.16);
    border-radius: 20px;
    padding: 0.9rem 1.25rem;
    min-width: 150px;
}
.meta-card span {
    display: block;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: rgba(255, 255, 255, 0.7);
}
.meta-card strong {
    display: block;
    font-size: 1.3rem;
    color: #fff;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.8rem;
    font-weight: 600;
}
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}
.stat-card {
    border-radius: 24px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    padding: 1.5rem;
    box-shadow: 0 18px 50px rgba(15, 23, 42, 0.1);
}
.stat-card span {
    text-transform: uppercase;
    letter-spacing: 0.15em;
    font-size: 0.72rem;
    color: #94a3b8;
}
.stat-card h3 {
    font-size: 2rem;
    margin: 0.25rem 0 0;
}
.glass-card {
    border-radius: 32px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
}
.glass-card .card-body,
.glass-card .card-header {
    padding: clamp(1.5rem, 4vw, 2.5rem);
}
.section-title {
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.75rem;
    color: #94a3b8;
}
.form-control,
.form-select {
    border-radius: 16px;
    padding: 0.85rem 1rem;
}
.table-modern {
    width: 100%;
    table-layout: fixed;
}
.table-modern thead th {
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.74rem;
    color: #94a3b8;
}
.table-modern tbody td {
    vertical-align: middle;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
}
.list-modern .list-group-item {
    border: none;
    border-bottom: 1px solid rgba(15, 23, 42, 0.06);
    padding-left: 0;
    padding-right: 0;
}
.list-modern .list-group-item:last-child {
    border-bottom: none;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
}
</style>
@endpush

@section('content')
<section class="token-admin-shell">
    <div class="token-admin-hero">
        <div class="flex-grow-1">
            <div class="hero-pill mb-3"><i class="bi bi-coin"></i> Token admin</div>
            <h1>Design bundles and fuel the ecosystem</h1>
            <p class="mb-0">Virtual vendor is {{ config('tokens.virtual_vendor.enabled') ? 'enabled' : 'disabled' }} · Currency {{ config('tokens.default_currency', 'USD') }}.</p>
            <div class="hero-meta">
                <div class="meta-card">
                    <span>Bundles</span>
                    <strong>{{ number_format($bundles->count()) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Active tokens</span>
                    <strong>{{ number_format($summary['active_tokens'] ?? 0) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Revenue 30d</span>
                    <strong>{{ config('tokens.default_currency', 'USD') }} {{ number_format(($summary['revenue_last_30_days_cents'] ?? 0) / 100, 2) }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            <a href="{{ route('tokens.index') }}" class="btn btn-light text-dark"><i class="bi bi-person-badge"></i> Learner wallets</a>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light"><i class="bi bi-speedometer"></i> Dashboard</a>
        </div>
    </div>

    <div class="stat-grid">
        <div class="stat-card">
            <span>Granted</span>
            <h3>{{ number_format($summary['total_tokens_granted'] ?? 0) }}</h3>
        </div>
        <div class="stat-card">
            <span>Spent</span>
            <h3>{{ number_format($summary['total_tokens_spent'] ?? 0) }}</h3>
        </div>
        <div class="stat-card">
            <span>In circulation</span>
            <h3>{{ number_format($summary['active_tokens'] ?? 0) }}</h3>
        </div>
        <div class="stat-card">
            <span>Revenue 30d</span>
            <h3>{{ config('tokens.default_currency', 'USD') }} {{ number_format(($summary['revenue_last_30_days_cents'] ?? 0) / 100, 2) }}</h3>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="glass-card h-100">
                <div class="card-header border-0">
                    <p class="section-title mb-1">Bundles</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="bi bi-boxes"></i> Create bundle</h4>
                    </div>
                </div>
                <div class="card-body">
                    @if ($errors->bundle->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->bundle->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.token-management.bundles.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label">Bundle Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Slug (optional)</label>
                            <input type="text" name="slug" class="form-control" value="{{ old('slug') }}" placeholder="auto-generated if blank">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tokens</label>
                            <input type="number" name="token_amount" class="form-control" min="1" value="{{ old('token_amount') }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Price ({{ config('tokens.default_currency', 'USD') }})</label>
                            <input type="number" name="price" class="form-control" min="0" step="0.01" value="{{ old('price') }}" placeholder="e.g. 19.99" required>
                            <small class="text-muted">Enter the learner price; we'll convert to cents automatically.</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Currency</label>
                            <input type="text" name="currency" class="form-control" value="{{ old('currency', config('tokens.default_currency', 'USD')) }}" maxlength="3" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expires After (days)</label>
                            <input type="number" name="expires_after_days" class="form-control" min="1" value="{{ old('expires_after_days', config('tokens.default_expiration_days', 365)) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            @php
                                $bundleActiveOld = old('is_active', '__default__');
                                $bundleActiveChecked = $bundleActiveOld === '__default__' ? true : (bool) $bundleActiveOld;
                            @endphp
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="create-bundle-active" name="is_active" value="1" {{ $bundleActiveChecked ? 'checked' : '' }}>
                                <label class="form-check-label" for="create-bundle-active">Active</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Visible to users when selecting a bundle.">{{ old('description') }}</textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-dark rounded-pill px-4">
                                <i class="bi bi-plus"></i> Save bundle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="glass-card h-100">
                <div class="card-header border-0">
                    <p class="section-title mb-1">Learners</p>
                    <h4 class="mb-0"><i class="bi bi-gift"></i> Manual token grant</h4>
                </div>
                <div class="card-body">
                    @if ($errors->grant->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->grant->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <form action="{{ route('admin.token-management.grant') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Learner Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="user@example.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tokens</label>
                            <input type="number" name="tokens" class="form-control" min="1" value="{{ old('tokens') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expires After (days)</label>
                            <input type="number" name="expires_after_days" class="form-control" min="1" value="{{ old('expires_after_days') }}" placeholder="Default">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Internal reason or context (optional)">{{ old('notes') }}</textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-outline-dark rounded-pill px-4">
                                <i class="bi bi-send"></i> Grant tokens
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="glass-card">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <p class="section-title mb-1">Inventory</p>
                        <h4 class="mb-0"><i class="bi bi-collection"></i> Existing bundles</h4>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Tokens</th>
                                <th>Price</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($bundles as $bundle)
                                <tr>
                                    <td>
                                        <strong>{{ $bundle->name }}</strong>
                                        <br>
                                        <small class="text-muted">{{ $bundle->slug }}</small>
                                    </td>
                                    <td>{{ number_format($bundle->token_amount) }}</td>
                                    <td>{{ $bundle->currency }} {{ number_format($bundle->price_cents / 100, 2) }}</td>
                                    <td>{{ $bundle->expires_after_days }} days</td>
                                    <td>
                                        <span class="badge rounded-pill {{ $bundle->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $bundle->is_active ? 'Active' : 'Hidden' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-dark rounded-pill" data-bs-toggle="modal" data-bs-target="#editBundleModal{{ $bundle->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('admin.token-management.bundles.destroy', $bundle) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this bundle?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger rounded-pill" type="submit">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editBundleModal{{ $bundle->id }}" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Bundle: {{ $bundle->name }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <form action="{{ route('admin.token-management.bundles.update', $bundle) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <div class="modal-body">
                                                    <div class="row g-3">
                                                        <div class="col-md-6">
                                                            <label class="form-label">Name</label>
                                                            <input type="text" name="name" class="form-control" value="{{ $bundle->name }}" required>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <label class="form-label">Slug</label>
                                                            <input type="text" name="slug" class="form-control" value="{{ $bundle->slug }}" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Tokens</label>
                                                            <input type="number" name="token_amount" class="form-control" value="{{ $bundle->token_amount }}" min="1" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Price ({{ $bundle->currency }})</label>
                                                            <input type="number" name="price" class="form-control" value="{{ number_format($bundle->price_cents / 100, 2, '.', '') }}" min="0" step="0.01" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Currency</label>
                                                            <input type="text" name="currency" class="form-control" value="{{ $bundle->currency }}" maxlength="3" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Expires After (days)</label>
                                                            <input type="number" name="expires_after_days" class="form-control" value="{{ $bundle->expires_after_days }}" min="1" required>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <label class="form-label">Status</label>
                                                            <div class="form-check form-switch mt-2">
                                                                <input class="form-check-input" type="checkbox" name="is_active" id="bundle-active-{{ $bundle->id }}" {{ $bundle->is_active ? 'checked' : '' }}>
                                                                <label class="form-check-label" for="bundle-active-{{ $bundle->id }}">Active</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-12">
                                                            <label class="form-label">Description</label>
                                                            <textarea name="description" class="form-control" rows="2">{{ $bundle->description }}</textarea>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-outline-secondary rounded-pill" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-dark rounded-pill">Update bundle</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-muted text-center py-4">No bundles created yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="glass-card h-100">
                <div class="card-header border-0 d-flex justify-content-between align-items-center">
                    <div>
                        <p class="section-title mb-1">Activity</p>
                        <h4 class="mb-0"><i class="bi bi-receipt"></i> Recent purchases</h4>
                    </div>
                </div>
                <div class="card-body">
                    @if($recentPurchases->isEmpty())
                        <p class="text-muted mb-0">No purchases yet.</p>
                    @else
                        <div class="list-group list-modern">
                            @foreach($recentPurchases as $purchase)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong>{{ $purchase->user->name ?? 'User' }}</strong>
                                            <p class="mb-0 text-muted small">
                                                {{ $purchase->bundle->name ?? 'Bundle' }} · {{ $purchase->tokens }} tokens
                                            </p>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge rounded-pill {{ $purchase->status === 'completed' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($purchase->status) }}</span>
                                            <p class="mb-0 text-muted small">{{ $purchase->created_at->format('M d') }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
