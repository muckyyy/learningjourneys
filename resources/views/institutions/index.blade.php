@extends('layouts.app')

@push('styles')
<style>
.institutions-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
}
.institutions-hero {
    background: linear-gradient(130deg, #0f172a, #2563eb 70%);
    border-radius: 40px;
    padding: clamp(2rem, 5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: flex-start;
    box-shadow: 0 30px 70px rgba(37, 99, 235, 0.35);
    margin-bottom: 2.5rem;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.3);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.institutions-hero h1 {
    font-size: clamp(2rem, 4vw, 3rem);
    margin-bottom: 0.5rem;
}
.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 1.5rem;
}
.meta-card {
    background: rgba(15, 23, 42, 0.25);
    border-radius: 22px;
    padding: 0.9rem 1.4rem;
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
    font-size: 1.4rem;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.9rem 1.8rem;
    font-weight: 600;
}
.institutions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
}
.institution-card {
    border-radius: 30px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    padding: 2rem;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    height: 100%;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.institution-card h3 {
    margin-bottom: 0.3rem;
}
.institution-card .status-pill {
    padding: 0.2rem 0.85rem;
    border-radius: 999px;
    font-size: 0.75rem;
    text-transform: uppercase;
}
.institution-description {
    color: #475569;
    line-height: 1.5;
}
.institution-meta {
    display: grid;
    gap: 0.6rem;
    font-size: 0.9rem;
    color: #475569;
}
.institution-meta i {
    color: #2563eb;
}
.institution-stats {
    display: flex;
    justify-content: space-between;
    gap: 0.75rem;
    text-align: center;
}
.institution-stats div {
    flex: 1;
    padding: 0.8rem;
    border-radius: 18px;
    background: #f8fafc;
}
.institution-stats span {
    display: block;
    font-size: 0.75rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #94a3b8;
}
.institution-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    margin-top: auto;
}
.institution-actions small {
    color: #94a3b8;
}
.institution-actions .btn {
    border-radius: 999px;
    padding: 0.4rem 1.4rem;
}
.pagination-shell {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}
.empty-state {
    border-radius: 36px;
    border: 1px dashed rgba(37, 99, 235, 0.4);
    padding: 4rem 2rem;
    text-align: center;
    background: #f8fbff;
}
.empty-state i {
    color: #cbd5f5;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
    .institution-stats { flex-direction: column; }
}
</style>
@endpush

@section('content')
@php
    $totalInstitutions = method_exists($institutions, 'total') ? $institutions->total() : $institutions->count();
    $visibleActive = $institutions->where('is_active', true)->count();
@endphp
<section class="institutions-shell">
    <div class="institutions-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-building"></i> Institutions</div>
            <h1>Coordinate every partner organization with ease.</h1>
            <p class="mb-0">Give editors, admins, and finance teams a single glass dashboard for institution data, contacts, and collections.</p>
            <div class="hero-meta">
                <div class="meta-card">
                    <span>Total records</span>
                    <strong>{{ number_format($totalInstitutions) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Visible now</span>
                    <strong>{{ number_format($institutions->count()) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Active (view)</span>
                    <strong>{{ number_format($visibleActive) }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            @can('create', App\Models\Institution::class)
                <a href="{{ route('institutions.create') }}" class="btn btn-light text-dark">
                    <i class="bi bi-plus-lg"></i> Create institution
                </a>
            @endcan
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light">
                <i class="bi bi-speedometer"></i> Back to dashboard
            </a>
        </div>
    </div>

    @if($institutions->count() > 0)
        <div class="institutions-grid">
            @foreach($institutions as $institution)
                <article class="institution-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="mb-2 text-uppercase text-muted small">{{ $institution->slug ?? 'Institution' }}</p>
                            <h3 class="mb-0">{{ $institution->name }}</h3>
                        </div>
                        <span class="status-pill {{ $institution->is_active ? 'bg-success text-white' : 'bg-secondary text-white' }}">
                            {{ $institution->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    @if($institution->description)
                        <p class="institution-description mb-0">
                            {{ Str::limit($institution->description, 150) }}
                        </p>
                    @endif

                    <div class="institution-meta">
                        @if($institution->address)
                            <div><i class="bi bi-geo-alt"></i> {{ $institution->address }}</div>
                        @endif
                        <div><i class="bi bi-envelope"></i> {{ $institution->contact_email }}</div>
                        @if($institution->contact_phone)
                            <div><i class="bi bi-telephone"></i> {{ $institution->contact_phone }}</div>
                        @endif
                        @if($institution->website)
                            <div>
                                <i class="bi bi-globe"></i>
                                <a href="{{ $institution->website }}" target="_blank" rel="noopener" class="text-decoration-none">Visit site</a>
                            </div>
                        @endif
                    </div>

                    <div class="institution-stats">
                        <div>
                            <span>Users</span>
                            <strong>{{ $institution->users->count() }}</strong>
                        </div>
                        <div>
                            <span>Collections</span>
                            <strong>{{ $institution->journeyCollections->count() }}</strong>
                        </div>
                        <div>
                            <span>Editors</span>
                            <strong>{{ $institution->users->where('role', 'editor')->count() }}</strong>
                        </div>
                    </div>

                    <div class="institution-actions">
                        <small>Created {{ $institution->created_at->diffForHumans() }}</small>
                        <div class="d-flex gap-2">
                            <a href="{{ route('institutions.show', $institution) }}" class="btn btn-outline-dark btn-sm">
                                View
                            </a>
                            @can('update', $institution)
                                <a href="{{ route('institutions.edit', $institution) }}" class="btn btn-outline-secondary btn-sm">
                                    Edit
                                </a>
                            @endcan
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="pagination-shell">
            {{ $institutions->links() }}
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-building display-4"></i>
            <h3 class="mt-3">No institutions yet</h3>
            <p class="text-muted mb-4">Spin up a new institution to group editors, manage tokens, and tailor collections.</p>
            @can('create', App\Models\Institution::class)
                <a href="{{ route('institutions.create') }}" class="btn btn-dark rounded-pill px-4">
                    <i class="bi bi-plus-lg"></i> Create your first institution
                </a>
            @endcan
        </div>
    @endif
</section>
@endsection
