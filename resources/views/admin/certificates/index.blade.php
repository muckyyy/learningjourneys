@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="hero blue">
        <div class="hero-content">
            <div class="pill light mb-3">
                <i class="bi bi-award"></i> Certificate Control Center
            </div>
            <h1 class="mb-3">Certificate Library</h1>
            <p class="mb-4">Review templates, institution access, and issuance trends in one place.</p>
        </div>
        <div class="hero-actions">
            @if(Route::has('admin.certificates.create'))
                <a href="{{ route('admin.certificates.create') }}" class="btn btn-light text-dark">
                    <i class="bi bi-plus-circle"></i> New certificate
                </a>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="metric-card h-100">
                <small>Total certificates</small>
                <div class="metric-value">{{ number_format($metrics['total']) }}</div>
                <div class="text-muted small">All definitions</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="metric-card h-100">
                <small>Enabled</small>
                <div class="metric-value">{{ number_format($metrics['enabled']) }}</div>
                <div class="text-muted small">Active templates</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="metric-card h-100">
                <small>Institutions</small>
                <div class="metric-value">{{ number_format($metrics['institutions']) }}</div>
                <div class="text-muted small">With certificate access</div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="metric-card h-100">
                <small>Issues recorded</small>
                <div class="metric-value">{{ number_format($metrics['issues']) }}</div>
                <div class="text-muted small">Total lifecycle events</div>
            </div>
        </div>
    </div>

    <div class="card filters-card border-0 mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="GET" class="d-flex align-items-center gap-3">
                <div class="flex-grow-1 d-flex align-items-center gap-2 border rounded-pill px-3 py-2 bg-light">
                    <i class="bi bi-search text-secondary"></i>
                    <input type="search" name="q" value="{{ $filters['q'] }}" class="form-control search-input" placeholder="Search certificates by name...">
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" name="only_enabled" value="1" id="filter-enabled"
                        {{ $filters['only_enabled'] ? 'checked' : '' }}
                        onchange="this.form.submit()">
                    <label class="form-check-label" for="filter-enabled">Show enabled only</label>
                </div>
                <button class="btn btn-primary rounded-pill" type="submit">
                    Apply
                </button>
                @if($filters['q'] || $filters['only_enabled'])
                    <a href="{{ route('admin.certificates.index') }}" class="btn btn-link text-decoration-none">Clear filters</a>
                @endif
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 certificate-table">
                    <thead>
                        <tr>
                            <th scope="col">Certificate</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-center">Validity</th>
                            <th scope="col">Institutions</th>
                            <th scope="col" class="text-center">Elements</th>
                            <th scope="col" class="text-center">Issues</th>
                            <th scope="col">Updated</th>
                            <th scope="col" class="text-center">Design</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($certificates as $certificate)
                            <tr>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $certificate->name }}</div>
                                    <div class="text-muted small">#{{ $certificate->id }} · {{ strtoupper($certificate->page_size) }} · {{ ucfirst($certificate->orientation) }} · {{ $certificate->page_width_mm }}mm × {{ $certificate->page_height_mm }}mm</div>
                                </td>
                                <td class="text-center">
                                    @if($certificate->enabled)
                                        <span class="status-pill enabled"><i class="bi bi-check-circle"></i> Enabled</span>
                                    @else
                                        <span class="status-pill disabled"><i class="bi bi-pause-circle"></i> Disabled</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($certificate->validity_days)
                                        <span class="badge rounded-pill text-bg-primary-subtle">{{ $certificate->validity_days }} days</span>
                                    @else
                                        <span class="text-muted small">No expiration</span>
                                    @endif
                                </td>
                                <td>
                                    @if($certificate->institutions->isEmpty())
                                        <span class="badge text-bg-light">Not assigned</span>
                                    @else
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($certificate->institutions as $institution)
                                                <span class="institution-chip">{{ $institution->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                    <div class="mt-2">
                                        <a href="{{ route('admin.certificates.institutions.edit', $certificate) }}" class="btn btn-link btn-sm px-0 text-decoration-none">Manage access</a>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light">{{ $certificate->elements_count }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light">{{ $certificate->issues_count }}</span>
                                </td>
                                <td>
                                    <div class="text-muted small">{{ optional($certificate->updated_at)->diffForHumans() ?? '—' }}</div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-column gap-2">
                                        <a href="{{ route('admin.certificates.edit', $certificate) }}" class="btn btn-sm btn-outline-secondary rounded-pill">
                                            <i class="bi bi-sliders"></i> Settings
                                        </a>
                                        <a href="{{ route('admin.certificates.designer', $certificate) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                            <i class="bi bi-vector-pen"></i> Designer
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="py-5">
                                        <i class="bi bi-journal-richtext fs-1 text-muted"></i>
                                        <p class="text-muted mt-3 mb-0">No certificates found. Adjust your filters or add a new definition.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($certificates->hasPages())
            <div class="card-footer border-0 bg-white px-4 py-3">
                {{ $certificates->links() }}
            </div>
        @endif
    </div>
</section>
@endsection
