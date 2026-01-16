@extends('layouts.app')

@section('content')
@php
    $totalInstitutions = method_exists($institutions, 'total') ? $institutions->total() : $institutions->count();
    $visibleActive = $institutions->where('is_active', true)->count();
@endphp
<section class="shell">
    <div class="hero cyan">
        <div class="hero-content">
            <div class="pill light mb-3"><i class="bi bi-building"></i> Institutions</div>
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
