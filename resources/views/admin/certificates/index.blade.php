@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Certificate Library</h1>
            <p class="text-muted mb-0">Review certificate definitions, institution access, and issuance activity.</p>
        </div>
        <form method="GET" class="d-flex align-items-center gap-2">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" name="only_enabled" value="1" id="filter-enabled"
                    {{ $filters['only_enabled'] ? 'checked' : '' }}
                    onchange="this.form.submit()">
                <label class="form-check-label" for="filter-enabled">Show enabled only</label>
            </div>
            @if($filters['only_enabled'])
                <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-secondary btn-sm">Clear</a>
            @endif
        </form>
    </div>

    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col">Certificate</th>
                            <th scope="col" class="text-center">Status</th>
                            <th scope="col" class="text-center">Validity</th>
                            <th scope="col">Institutions</th>
                            <th scope="col" class="text-center">Elements</th>
                            <th scope="col" class="text-center">Issues</th>
                            <th scope="col">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($certificates as $certificate)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $certificate->name }}</div>
                                    <div class="text-muted small">#{{ $certificate->id }}</div>
                                </td>
                                <td class="text-center">
                                    @if($certificate->enabled)
                                        <span class="badge bg-success-subtle text-success">Enabled</span>
                                    @else
                                        <span class="badge bg-secondary-subtle text-secondary">Disabled</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($certificate->validity_days)
                                        <span class="badge bg-primary-subtle text-primary">{{ $certificate->validity_days }} days</span>
                                    @else
                                        <span class="text-muted small">No expiration</span>
                                    @endif
                                </td>
                                <td>
                                    @if($certificate->institutions->isEmpty())
                                        <span class="badge bg-light text-dark">Not assigned</span>
                                    @else
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($certificate->institutions as $institution)
                                                <span class="badge bg-info-subtle text-info">{{ $institution->name }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $certificate->elements_count }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark">{{ $certificate->issues_count }}</span>
                                </td>
                                <td>
                                    <div class="text-muted small">{{ optional($certificate->updated_at)->diffForHumans() ?? '—' }}</div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">No certificates found yet.</div>
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
</div>
@endsection
