@extends('layouts.app')

@push('styles')
<style>
.editors-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.editors-hero {
    background: linear-gradient(135deg, #0f172a, #a855f7 70%);
    border-radius: 40px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(168, 85, 247, 0.35);
    margin-bottom: 2.5rem;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.2);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.editors-hero h1 {
    font-size: clamp(2rem, 4.4vw, 3.1rem);
    margin-bottom: 0.35rem;
}
.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    margin-top: 1.5rem;
}
.meta-card {
    background: rgba(15, 23, 42, 0.25);
    border-radius: 22px;
    padding: 0.85rem 1.4rem;
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
.editors-table-card {
    border-radius: 34px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
}
.editors-table-card .card-body {
    padding: clamp(1.5rem, 4vw, 2.5rem);
}
.table-modern thead th {
    border: none;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    font-size: 0.72rem;
    color: #94a3b8;
}
.table-modern tbody td {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    vertical-align: middle;
}
.avatar-circle {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: #a855f7;
    color: #fff;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.95rem;
}
.status-pill {
    border-radius: 999px;
    padding: 0.25rem 0.9rem;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
}
.pagination-shell {
    margin-top: 2rem;
    display: flex;
    justify-content: center;
}
.empty-state {
    border-radius: 36px;
    border: 1px dashed rgba(148, 163, 184, 0.6);
    background: #f8fbff;
    padding: 4rem 2rem;
    text-align: center;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
    .table-modern tbody td { font-size: 0.85rem; }
}
</style>
@endpush

@section('content')
@php
    $totalEditors = method_exists($editors, 'total') ? $editors->total() : $editors->count();
    $activeEditors = $editors->where('is_active', true)->count();
    $avgCollections = $editors->count() > 0 ? round($editors->sum(fn($editor) => $editor->journeyCollections()->count()) / $editors->count(), 1) : 0;
@endphp
<section class="editors-shell">
    <div class="editors-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-pencil-square"></i> Editors</div>
            <h1>Give every curator the same glass cockpit.</h1>
            <p class="mb-0">Editors launch collections, coordinate assets, and fuel journeys. Track their momentum and add new collaborators instantly.</p>
            <div class="hero-meta">
                <div class="meta-card">
                    <span>Total editors</span>
                    <strong>{{ number_format($totalEditors) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Active now</span>
                    <strong>{{ number_format($activeEditors) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Avg collections</span>
                    <strong>{{ $avgCollections }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            <button type="button" class="btn btn-light text-dark" data-bs-toggle="modal" data-bs-target="#createEditorModal">
                <i class="bi bi-plus-lg"></i> Create editor
            </button>
            <a href="{{ route('users.index') }}" class="btn btn-outline-light">
                <i class="bi bi-people"></i> Users
            </a>
        </div>
    </div>

    @if($editors->count() > 0)
        <div class="editors-table-card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-modern align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Institution</th>
                                <th>Collections</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($editors as $editor)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle">
                                                {{ strtoupper(substr($editor->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <strong>{{ $editor->name }}</strong>
                                                <p class="mb-0 text-muted small">{{ $editor->institution->name }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $editor->email }}</td>
                                    <td>{{ $editor->institution->name }}</td>
                                    <td>
                                        <span class="badge rounded-pill bg-info text-dark">{{ $editor->journeyCollections()->count() }}</span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $editor->is_active ? 'bg-success text-white' : 'bg-secondary text-white' }}">
                                            {{ $editor->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ $editor->created_at->format('M d, Y') }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if(auth()->user()->role === 'administrator')
                                                <a href="{{ route('users.show', $editor) }}" class="btn btn-outline-dark">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('users.edit', $editor) }}" class="btn btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="pagination-shell">
            {{ $editors->links() }}
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-pencil-square display-3 text-muted"></i>
            <h3 class="mt-3">No editors yet</h3>
            <p class="text-muted">Editors shape collections. Invite your first collaborator to start publishing journeys.</p>
            <button type="button" class="btn btn-dark rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#createEditorModal">
                <i class="bi bi-plus-lg"></i> Create editor
            </button>
        </div>
    @endif
</section>

<!-- Create Editor Modal -->
<div class="modal fade" id="createEditorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ route('editors.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Create editor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_name" class="form-label">Full name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="modal_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="modal_email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="modal_email" name="email" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="modal_password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="modal_password" name="password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="modal_password_confirmation" class="form-label">Confirm password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="modal_password_confirmation" name="password_confirmation" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_institution_id" class="form-label">Institution <span class="text-danger">*</span></label>
                        <select class="form-select" id="modal_institution_id" name="institution_id" required>
                            <option value="">Select institution</option>
                            @foreach($institutions as $institution)
                                <option value="{{ $institution->id }}">{{ $institution->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-dark">Create editor</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
