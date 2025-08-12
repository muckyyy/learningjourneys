@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-building"></i> Institutions
                </h1>
                @can('create', App\Models\Institution::class)
                    <a href="{{ route('institutions.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create Institution
                    </a>
                @endcan
            </div>

            @if($institutions->count() > 0)
                <div class="row">
                    @foreach($institutions as $institution)
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title">{{ $institution->name }}</h5>
                                        @if($institution->is_active)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </div>
                                    
                                    @if($institution->description)
                                        <p class="card-text text-muted">
                                            {{ Str::limit($institution->description, 120) }}
                                        </p>
                                    @endif

                                    <div class="text-muted small mb-3">
                                        @if($institution->address)
                                            <div class="mb-1">
                                                <i class="bi bi-geo-alt"></i> {{ $institution->address }}
                                            </div>
                                        @endif
                                        <div class="mb-1">
                                            <i class="bi bi-envelope"></i> {{ $institution->contact_email }}
                                        </div>
                                        @if($institution->contact_phone)
                                            <div class="mb-1">
                                                <i class="bi bi-telephone"></i> {{ $institution->contact_phone }}
                                            </div>
                                        @endif
                                        @if($institution->website)
                                            <div class="mb-1">
                                                <i class="bi bi-globe"></i> 
                                                <a href="{{ $institution->website }}" target="_blank" class="text-decoration-none">
                                                    Website
                                                </a>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="row text-center mb-3">
                                        <div class="col-4">
                                            <div class="text-muted small">Users</div>
                                            <div class="fw-bold">{{ $institution->users->count() }}</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Collections</div>
                                            <div class="fw-bold">{{ $institution->journeyCollections->count() }}</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted small">Editors</div>
                                            <div class="fw-bold">{{ $institution->users->where('role', 'editor')->count() }}</div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            Created {{ $institution->created_at->diffForHumans() }}
                                        </small>
                                        <div>
                                            <a href="{{ route('institutions.show', $institution) }}" class="btn btn-outline-primary btn-sm">
                                                View
                                            </a>
                                            @can('update', $institution)
                                                <a href="{{ route('institutions.edit', $institution) }}" class="btn btn-outline-secondary btn-sm">
                                                    Edit
                                                </a>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center">
                    {{ $institutions->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-building display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No institutions found</h3>
                    <p class="text-muted">Institutions manage editors and journey collections.</p>
                    @can('create', App\Models\Institution::class)
                        <a href="{{ route('institutions.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Create Your First Institution
                        </a>
                    @endcan
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
