@extends('layouts.app')

@section('content')
@php
    $fieldsCollection = collect($profileFields);
    $totalFields = $fieldsCollection->count();
    $activeFields = $fieldsCollection->where('is_active', true)->count();
    $requiredFields = $fieldsCollection->where('required', true)->count();
@endphp

<section class="shell">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="hero cyan">
        <div class="hero-content">
            <div class="pill light mb-3"><i class="bi bi-person-lines-fill"></i> Profile fields</div>
            <h1>Design the metadata your learners share.</h1>
            <p class="mb-4">Build consistent onboarding by defining the exact fields your team must capture, then keep them fresh with living data.</p>

            <div class="stats-grid">
                <div class="card">
                    <span>Total fields</span>
                    <strong>{{ number_format($totalFields) }}</strong>
                </div>
                <div class="card">
                    <span>Active</span>
                    <strong>{{ number_format($activeFields) }}</strong>
                </div>
                <div class="card">
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
