@extends('layouts.app')

@section('content')
@php
    $fieldsCollection = collect($profileFields);
    $totalFields = $fieldsCollection->count();
    $activeFields = $fieldsCollection->where('is_active', true)->count();
    $requiredFields = $fieldsCollection->where('required', true)->count();
    $optionalFields = max($totalFields - $requiredFields, 0);
    $metricCards = [
        [
            'label' => 'Total fields',
            'value' => number_format($totalFields),
            'description' => 'Available definitions',
            'icon' => 'bi-person-lines-fill',
            'accent' => 'accent-indigo',
        ],
        [
            'label' => 'Active today',
            'value' => number_format($activeFields),
            'description' => 'Visible to journeys',
            'icon' => 'bi-lightning-charge',
            'accent' => 'accent-teal',
        ],
        [
            'label' => 'Required data',
            'value' => number_format($requiredFields),
            'description' => 'Must be collected',
            'icon' => 'bi-exclamation-diamond',
            'accent' => 'accent-amber',
        ],
        [
            'label' => 'Optional',
            'value' => number_format($optionalFields),
            'description' => 'Enrichment prompts',
            'icon' => 'bi-layers',
            'accent' => 'accent-rose',
        ],
    ];
@endphp

<section class="shell certificate-admin profile-fields-admin">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <span class="d-inline-flex align-items-center gap-2 text-muted text-uppercase small fw-semibold mb-2">
                <i class="bi bi-person-lines-fill"></i> Profile Fields
            </span>
            <h1 class="mb-1">Profile fields</h1>
            <p class="text-muted mb-0">Design consistent learner metadata once and reuse it across every onboarding journey.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('profile-fields.create') }}" class="btn btn-dark rounded-pill px-4">
                <i class="bi bi-plus-lg"></i> Add field
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

    @if($fieldsCollection->count() > 0)
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
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
                                        <span class="badge {{ $field->required ? 'bg-warning text-dark' : 'bg-secondary' }}">
                                            {{ $field->required ? 'Required' : 'Optional' }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-pill {{ $field->is_active ? 'active' : 'inactive' }}">
                                            {{ $field->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ route('profile-fields.edit', $field) }}" class="btn btn-outline-secondary" title="Edit profile field">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form action="{{ route('profile-fields.destroy', $field) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Are you sure you want to delete this profile field?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger" title="Delete profile field">
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
            </div>
        </div>
    @else
        <div class="text-center py-5">
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:96px;height:96px;">
                <i class="bi bi-person-lines-fill text-muted fs-2"></i>
            </div>
            <h3 class="fw-bold">No profile fields yet</h3>
            <p class="text-muted mb-4">Craft the data blueprint learners complete in onboarding. Start with a required field or two.</p>
            <a href="{{ route('profile-fields.create') }}" class="btn btn-dark rounded-pill px-4">
                <i class="bi bi-plus-lg"></i> Add first field
            </a>
        </div>
    @endif
</section>
@endsection
