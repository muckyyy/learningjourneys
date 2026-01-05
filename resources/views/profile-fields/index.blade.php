@extends('layouts.app')

@push('styles')
<style>
.profile-fields-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.profile-fields-hero {
    background: linear-gradient(135deg, #0f172a, #1d4ed8 45%, #22d3ee);
    border-radius: 40px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.75rem;
    align-items: flex-start;
    box-shadow: 0 35px 80px rgba(15, 23, 42, 0.35);
    margin-bottom: 2.5rem;
}
.profile-fields-hero .hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.55rem 1.45rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.35);
    font-size: 0.78rem;
    letter-spacing: 0.18em;
    text-transform: uppercase;
}
.profile-fields-hero h1 {
    font-size: clamp(2rem, 4vw, 3.1rem);
    margin-bottom: 0.4rem;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.9rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.9rem 1.8rem;
    font-weight: 600;
    box-shadow: 0 15px 25px rgba(15, 23, 42, 0.25);
}
.stats-grid {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}
.stat-card {
    background: rgba(15, 23, 42, 0.35);
    border-radius: 1.5rem;
    padding: 1rem 1.25rem;
}
.stat-card span {
    font-size: 0.78rem;
    letter-spacing: 0.15em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.7);
}
.stat-card strong {
    display: block;
    font-size: 2rem;
    line-height: 1.1;
}
.profile-fields-card {
    border-radius: 36px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.1);
    padding: clamp(1.5rem, 3vw, 2.75rem);
}
.table-modern th {
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-size: 0.75rem;
    color: #94a3b8;
    border-bottom-width: 1px;
}
.table-modern td {
    vertical-align: middle;
    border-color: rgba(15, 23, 42, 0.05);
}
.table-modern tr:hover {
    background: rgba(15, 23, 42, 0.02);
}
.badge-chip {
    border-radius: 999px;
    padding: 0.35rem 0.85rem;
    font-weight: 600;
    font-size: 0.78rem;
}
.code-chip {
    background: rgba(15, 23, 42, 0.06);
    border-radius: 8px;
    padding: 0.25rem 0.5rem;
    font-size: 0.85rem;
}
.table-actions {
    display: flex;
    gap: 0.4rem;
}
.table-actions .btn {
    border-radius: 999px;
    padding: 0.35rem 0.75rem;
}
.empty-state {
    text-align: center;
    padding: 3rem 1rem;
}
.empty-icon {
    width: 72px;
    height: 72px;
    border-radius: 20px;
    background: rgba(15, 23, 42, 0.05);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #0f172a;
    font-size: 2rem;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
    .table-actions { flex-direction: column; }
}
</style>
@endpush

@section('content')
@php
    $fieldsCollection = collect($profileFields);
    $totalFields = $fieldsCollection->count();
    $activeFields = $fieldsCollection->where('is_active', true)->count();
    $requiredFields = $fieldsCollection->where('required', true)->count();
@endphp

<section class="profile-fields-shell">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="profile-fields-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-person-lines-fill"></i> Profile fields</div>
            <h1>Design the metadata your learners share.</h1>
            <p class="mb-4">Build consistent onboarding by defining the exact fields your team must capture, then keep them fresh with living data.</p>

            <div class="stats-grid">
                <div class="stat-card">
                    <span>Total fields</span>
                    <strong>{{ number_format($totalFields) }}</strong>
                </div>
                <div class="stat-card">
                    <span>Active</span>
                    <strong>{{ number_format($activeFields) }}</strong>
                </div>
                <div class="stat-card">
                    <span>Required</span>
                    <strong>{{ number_format($requiredFields) }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            <a href="{{ route('profile-fields.create') }}" class="btn btn-light text-dark">
                <i class="bi bi-plus-circle"></i> Add new field
            </a>
            <small class="text-white-50">Every field automatically syncs into onboarding journeys.</small>
        </div>
    </div>

    <div class="profile-fields-card">
        @if($fieldsCollection->count() > 0)
            <div class="table-responsive">
                <table class="table table-modern align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Field</th>
                            <th scope="col">Key</th>
                            <th scope="col">Input</th>
                            <th scope="col">Required</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($fieldsCollection as $field)
                            <tr>
                                <td class="fw-semibold">{{ $field->sort_order }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $field->name }}</div>
                                    @if($field->description)
                                        <div class="text-muted small">{{ $field->description }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="code-chip">{{ $field->short_name }}</span>
                                </td>
                                <td>
                                    <span class="badge-chip bg-light text-dark">
                                        {{ ucfirst(str_replace('_', ' ', $field->input_type)) }}
                                    </span>
                                    @if(in_array($field->input_type, ['select', 'select_multiple']) && $field->options)
                                        <div class="text-muted small mt-1">
                                            Options: {{ implode(', ', $field->options) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if($field->required)
                                        <span class="badge bg-warning text-dark">Required</span>
                                    @else
                                        <span class="badge bg-secondary">Optional</span>
                                    @endif
                                </td>
                                <td>
                                    @if($field->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-danger">Inactive</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="table-actions">
                                        <a href="{{ route('profile-fields.edit', $field) }}" class="btn btn-outline-dark btn-sm">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form action="{{ route('profile-fields.destroy', $field) }}" method="POST" class="d-inline"
                                              onsubmit="return confirm('Are you sure you want to delete this profile field?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="empty-state">
                <div class="empty-icon mb-3">
                    <i class="bi bi-person-lines-fill"></i>
                </div>
                <h4 class="mb-2">No profile fields yet</h4>
                <p class="text-muted mb-3">Craft the data blueprint learners complete in onboarding. Start with a required field or two.</p>
                <a href="{{ route('profile-fields.create') }}" class="btn btn-dark rounded-pill">
                    <i class="bi bi-plus-circle"></i> Add first field
                </a>
            </div>
        @endif
    </div>
</section>
@endsection
