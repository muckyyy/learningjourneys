@extends('layouts.app')

@section('content')
@php
    $totalInstitutions = method_exists($institutions, 'total') ? $institutions->total() : $institutions->count();
@endphp
<div class="shell" style="max-width: 900px;">

    {{-- Header --}}
    <header class="mb-4 pb-3" style="border-bottom: 1px solid rgba(15,23,42,0.08);">
        <h2 class="fw-bold mb-1" style="color: var(--lj-ink); letter-spacing: -0.02em;">Institutions</h2>
        <div class="d-flex justify-content-between align-items-center">
            <p class="text-muted mb-0" style="font-size: 0.9rem;">{{ $totalInstitutions }} {{ Str::plural('institution', $totalInstitutions) }} on the platform.</p>
            @can('create', App\Models\Institution::class)
                <a href="{{ route('institutions.create') }}" class="btn btn-sm btn-outline-primary rounded-pill">
                    <i class="bi bi-plus"></i> New Institution
                </a>
            @endcan
        </div>
    </header>

    @if($institutions->count() > 0)
        <div class="glass-card">
            <div class="d-flex flex-column">
                @foreach($institutions as $institution)
                    <div class="d-flex align-items-start gap-3 py-3" style="{{ !$loop->last ? 'border-bottom: 1px solid rgba(15,23,42,0.06);' : '' }}">
                        {{-- Icon --}}
                        <div class="flex-shrink-0 d-flex align-items-center justify-content-center rounded-3"
                             style="width: 40px; height: 40px; background: var(--lj-brand-muted); color: var(--lj-brand-dark); font-size: 1.1rem;">
                            <i class="bi bi-building"></i>
                        </div>

                        {{-- Content --}}
                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <a href="{{ route('institutions.show', $institution) }}" class="fw-semibold text-decoration-none" style="color: var(--lj-ink); font-size: 1rem;">
                                    {{ $institution->name }}
                                </a>
                                <span class="badge rounded-pill {{ $institution->is_active ? 'bg-success' : 'bg-secondary' }}" style="font-size: 0.7rem;">
                                    {{ $institution->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            @if($institution->description)
                                <p class="text-muted mb-1 mt-1" style="font-size: 0.88rem; line-height: 1.5;">
                                    {{ Str::limit($institution->description, 120) }}
                                </p>
                            @endif
                            <div class="d-flex flex-wrap gap-3 mt-1" style="font-size: 0.8rem; color: var(--lj-muted);">
                                <span><i class="bi bi-people me-1"></i>{{ $institution->users->count() }} users</span>
                                <span><i class="bi bi-collection me-1"></i>{{ $institution->journeyCollections->count() }} collections</span>
                                <span><i class="bi bi-pencil-square me-1"></i>{{ $institution->users->where('role', 'editor')->count() }} editors</span>
                                @if($institution->contact_email)
                                    <span><i class="bi bi-envelope me-1"></i>{{ $institution->contact_email }}</span>
                                @endif
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex-shrink-0 d-flex gap-2 align-items-center">
                            <a href="{{ route('institutions.show', $institution) }}" class="btn btn-sm btn-outline-dark rounded-pill" style="font-size: 0.8rem;">View</a>
                            @can('update', $institution)
                                <a href="{{ route('institutions.edit', $institution) }}" class="btn btn-sm btn-outline-secondary rounded-pill" style="font-size: 0.8rem;">Edit</a>
                            @endcan
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $institutions->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-building text-muted" style="font-size: 2.5rem;"></i>
            <h6 class="mt-3 fw-semibold">No institutions yet</h6>
            <p class="text-muted mb-3" style="font-size: 0.9rem;">Create an institution to group editors, manage tokens, and tailor collections.</p>
            @can('create', App\Models\Institution::class)
                <a href="{{ route('institutions.create') }}" class="btn btn-primary rounded-pill">
                    <i class="bi bi-plus-lg"></i> Create your first institution
                </a>
            @endcan
        </div>
    @endif
</div>
@endsection
