@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-xl-10 col-xxl-9">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="section-title mb-1">Administration</p>
            <h2 class="fw-bold mb-1">Legal Documents</h2>
            <p class="text-muted mb-0">Manage Terms of Service, Privacy Policy, and Cookie Policy documents.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.settings.index') }}" class="btn btn-outline-secondary rounded-pill">
                <i class="bi bi-arrow-left"></i> Settings
            </a>
            <a href="{{ route('admin.legal.create') }}" class="btn btn-primary rounded-pill">
                <i class="bi bi-plus-lg"></i> New Document
            </a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @forelse ($types as $typeKey => $typeLabel)
        <div class="glass-card mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-3">
                    <i class="bi bi-file-earmark-text me-2"></i>{{ $typeLabel }}
                </h5>

                @if (isset($documents[$typeKey]) && $documents[$typeKey]->count())
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th class="text-center">Version</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Required</th>
                                    <th class="text-center">Consents</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($documents[$typeKey]->sortByDesc('version') as $doc)
                                    <tr>
                                        <td>
                                            <strong>{{ $doc->title }}</strong>
                                            @if ($doc->published_at)
                                                <br><small class="text-muted">Published {{ $doc->published_at->diffForHumans() }}</small>
                                            @else
                                                <br><small class="text-muted">Created {{ $doc->created_at->diffForHumans() }}</small>
                                            @endif
                                        </td>
                                        <td class="text-center">v{{ $doc->version }}</td>
                                        <td class="text-center">
                                            @if ($doc->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Draft</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if ($doc->is_required)
                                                <i class="bi bi-check-circle text-success"></i>
                                            @else
                                                <i class="bi bi-dash-circle text-muted"></i>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <a href="{{ route('admin.legal.consents', $doc) }}" class="text-decoration-none">
                                                {{ $doc->consents_count ?? $doc->consents()->count() }}
                                            </a>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('legal.show', $doc->slug) }}" class="btn btn-outline-secondary" title="Preview" target="_blank">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.legal.edit', $doc) }}" class="btn btn-outline-primary" title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                @if (!$doc->is_active)
                                                    <form method="POST" action="{{ route('admin.legal.publish', $doc) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-success" title="Publish" onclick="return confirm('Publish v{{ $doc->version }}? This will deactivate the current version.')">
                                                            <i class="bi bi-rocket-takeoff"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.legal.destroy', $doc) }}" class="d-inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-outline-danger" title="Delete" onclick="return confirm('Delete this draft?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">
                        No {{ strtolower($typeLabel) }} documents yet.
                        <a href="{{ route('admin.legal.create') }}">Create one</a>.
                    </p>
                @endif
            </div>
        </div>
    @empty
        <p class="text-muted">No document types defined.</p>
    @endforelse

</div>
</div>
</section>
@endsection
