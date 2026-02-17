@extends('layouts.app')

@section('content')
@php
    $hasFilters = filled($filters['q']) || $filters['only_enabled'];
    $metricCards = [
        [
            'label' => 'Total certificates',
            'value' => number_format($metrics['total']),
            'description' => 'All definitions',
            'icon' => 'bi-award',
            'accent' => 'accent-indigo',
        ],
        [
            'label' => 'Enabled',
            'value' => number_format($metrics['enabled']),
            'description' => 'Active templates',
            'icon' => 'bi-lightning-charge',
            'accent' => 'accent-amber',
        ],
        [
            'label' => 'Institutions',
            'value' => number_format($metrics['institutions']),
            'description' => 'With certificate access',
            'icon' => 'bi-building',
            'accent' => 'accent-teal',
        ],
        [
            'label' => 'Issues recorded',
            'value' => number_format($metrics['issues']),
            'description' => 'Total lifecycle events',
            'icon' => 'bi-activity',
            'accent' => 'accent-rose',
        ],
    ];
@endphp
<section class="shell certificates-shell certificate-admin">

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="mb-1">Certificates</h1>
            <p class="text-muted mb-0">{{ number_format($metrics['enabled']) }} active definitions across {{ number_format($metrics['institutions']) }} institutions.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.certificates.create') }}" class="btn btn-dark rounded-pill px-4">
                <i class="bi bi-plus-lg"></i> New certificate
            </a>
        </div>
    </div>

    <div class="metrics-grid mb-4">
        @foreach($metricCards as $card)
            <article class="metric-card {{ $card['accent'] }}">
                <div class="metric-card-icon">
                    <i class="bi {{ $card['icon'] }}"></i>
                </div>
                <small>{{ $card['label'] }}</small>
                <div class="metric-value">{{ $card['value'] }}</div>
                <p class="text-muted small mb-0">{{ $card['description'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" class="d-flex flex-column flex-lg-row gap-3 align-items-lg-center">
                <div class="flex-grow-1 d-flex align-items-center gap-2 border rounded-pill px-3 py-2 bg-light">
                    <i class="bi bi-search text-secondary"></i>
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control search-input" placeholder="Search certificates by name...">
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" name="only_enabled" value="1" id="filter-enabled"
                            {{ $filters['only_enabled'] ? 'checked' : '' }}
                            onchange="this.form.submit()">
                        <label class="form-check-label" for="filter-enabled">Show enabled only</label>
                    </div>
                    <button class="btn btn-primary rounded-pill px-4" type="submit">
                        Apply
                    </button>
                    @if($hasFilters)
                        <a href="{{ route('admin.certificates.index') }}" class="btn btn-link text-decoration-none">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if($certificates->count() > 0)
        <div class="collections-grid certificate-grid mb-4">
            @foreach($certificates as $certificate)
                <article class="collection-card certificate-card h-100">
                    <div class="collection-card-header">
                        <span class="collection-card-institution">#{{ $certificate->id }} · {{ strtoupper($certificate->page_size) }} · {{ ucfirst($certificate->orientation) }}</span>
                        <span class="status-pill {{ $certificate->enabled ? 'active' : 'inactive' }}">
                            {{ $certificate->enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>

                    <h3 class="collection-card-title mb-1">{{ $certificate->name }}</h3>
                    <p class="text-muted small mb-3">{{ $certificate->page_width_mm }}mm × {{ $certificate->page_height_mm }}mm</p>

                    <div class="collection-meta">
                        <div class="collection-meta-item">
                            <i class="bi bi-clock-history"></i>
                            <span>{{ $certificate->validity_days ? $certificate->validity_days . ' day validity' : 'No expiration' }}</span>
                        </div>
                        <div class="collection-meta-item">
                            <i class="bi bi-diagram-3"></i>
                            <span>{{ $certificate->institutions->count() }} institutions</span>
                        </div>
                        <div class="collection-meta-item">
                            <i class="bi bi-layers"></i>
                            <span>{{ $certificate->elements_count }} elements</span>
                        </div>
                        <div class="collection-meta-item">
                            <i class="bi bi-activity"></i>
                            <span>{{ $certificate->issues_count }} issues</span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Institutions</span>
                            <a href="{{ route('admin.certificates.institutions.edit', $certificate) }}" class="btn btn-link btn-sm px-0 text-decoration-none">Manage access</a>
                        </div>
                        @if($certificate->institutions->isEmpty())
                            <span class="badge text-bg-light">Not assigned</span>
                        @else
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($certificate->institutions as $institution)
                                    <span class="institution-chip">{{ $institution->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="collection-actions mt-4">
                        <small>Updated {{ optional($certificate->updated_at)->diffForHumans() ?? '—' }}</small>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.certificates.edit', $certificate) }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-sliders"></i> Settings
                            </a>
                            <a href="{{ route('admin.certificates.designer', $certificate) }}" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-vector-pen"></i> Designer
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($certificates->hasPages())
            <div class="pagination-shell">
                {{ $certificates->links() }}
            </div>
        @endif
    @else
        <div class="text-center py-5">
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:96px;height:96px;">
                <i class="bi bi-award text-muted fs-2"></i>
            </div>
            <h3 class="fw-bold">No certificates match your filters</h3>
            <p class="text-muted mb-4">Certificate definitions orchestrate issuances across journeys. Create one to get started.</p>
            <a href="{{ route('admin.certificates.create') }}" class="btn btn-dark rounded-pill px-4">
                <i class="bi bi-plus-lg"></i> New certificate
            </a>
        </div>
    @endif

</section>
@endsection
