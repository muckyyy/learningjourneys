@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">{{ $institution->name }}</h1>
                <div>
                    <a href="{{ route('institutions.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Institutions
                    </a>
                    @can('update', $institution)
                        <a href="{{ route('institutions.edit', $institution) }}" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                    @endcan
                </div>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <!-- Institution Info -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    @if($institution->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-secondary">Inactive</span>
                                    @endif
                                </div>
                                <small class="text-muted">
                                    Created {{ $institution->created_at->diffForHumans() }}
                                </small>
                            </div>

                            @if($institution->description)
                                <p class="lead">{{ $institution->description }}</p>
                            @endif

                            <div class="row">
                                @if($institution->address)
                                    <div class="col-md-6 mb-3">
                                        <strong><i class="bi bi-geo-alt"></i> Address:</strong>
                                        <div class="text-muted">{{ $institution->address }}</div>
                                    </div>
                                @endif

                                <div class="col-md-6 mb-3">
                                    <strong><i class="bi bi-envelope"></i> Contact Email:</strong>
                                    <div class="text-muted">
                                        <a href="mailto:{{ $institution->contact_email }}">{{ $institution->contact_email }}</a>
                                    </div>
                                </div>

                                @if($institution->contact_phone)
                                    <div class="col-md-6 mb-3">
                                        <strong><i class="bi bi-telephone"></i> Phone:</strong>
                                        <div class="text-muted">{{ $institution->contact_phone }}</div>
                                    </div>
                                @endif

                                @if($institution->website)
                                    <div class="col-md-6 mb-3">
                                        <strong><i class="bi bi-globe"></i> Website:</strong>
                                        <div class="text-muted">
                                            <a href="{{ $institution->website }}" target="_blank">{{ $institution->website }}</a>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Users -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-people"></i> Users ({{ $institution->users->count() }})
                            </h5>
                            @if(auth()->user()->role === 'administrator' || (auth()->user()->role === 'institution' && auth()->user()->institution_id === $institution->id))
                                <a href="{{ route('editors.index') }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Manage Users
                                </a>
                            @endif
                        </div>
                        <div class="card-body">
                            @if($institution->users->count() > 0)
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Role</th>
                                                <th>Status</th>
                                                <th>Joined</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($institution->users->take(10) as $user)
                                                <tr>
                                                    <td>{{ $user->name }}</td>
                                                    <td>{{ $user->email }}</td>
                                                    <td>
                                                        <span class="badge bg-{{ 
                                                            $user->role === 'administrator' ? 'danger' : 
                                                            ($user->role === 'institution' ? 'warning' : 
                                                            ($user->role === 'editor' ? 'info' : 'secondary')) 
                                                        }}">
                                                            {{ ucfirst($user->role) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        @if($user->is_active)
                                                            <span class="badge bg-success">Active</span>
                                                        @else
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                @if($institution->users->count() > 10)
                                    <div class="text-center mt-3">
                                        <small class="text-muted">Showing 10 of {{ $institution->users->count() }} users</small>
                                    </div>
                                @endif
                            @else
                                <div class="text-center py-3">
                                    <i class="bi bi-people text-muted"></i>
                                    <p class="text-muted mb-0">No users yet</p>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Journey Collections -->
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-collection"></i> Journey Collections ({{ $institution->journeyCollections->count() }})
                            </h5>
                            @can('create', App\Models\JourneyCollection::class)
                                <a href="{{ route('collections.create') }}" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Create Collection
                                </a>
                            @endcan
                        </div>
                        <div class="card-body">
                            @if($institution->journeyCollections->count() > 0)
                                <div class="row">
                                    @foreach($institution->journeyCollections->take(6) as $collection)
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title">{{ $collection->name }}</h6>
                                                        @if($collection->is_active)
                                                            <span class="badge bg-success">Active</span>
                                                        @else
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        @endif
                                                    </div>
                                                    <p class="card-text text-muted small">
                                                        {{ Str::limit($collection->description, 80) }}
                                                    </p>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            Editor: {{ $collection->editor->name }}
                                                        </small>
                                                        <a href="{{ route('collections.show', $collection) }}" class="btn btn-outline-primary btn-sm">
                                                            View
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if($institution->journeyCollections->count() > 6)
                                    <div class="text-center mt-3">
                                        <a href="{{ route('collections.index') }}" class="btn btn-outline-primary">
                                            View All Collections
                                        </a>
                                    </div>
                                @endif
                            @else
                                <div class="text-center py-3">
                                    <i class="bi bi-collection text-muted"></i>
                                    <p class="text-muted mb-0">No collections yet</p>
                                    @can('create', App\Models\JourneyCollection::class)
                                        <a href="{{ route('collections.create') }}" class="btn btn-sm btn-primary">
                                            Create First Collection
                                        </a>
                                    @endcan
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <!-- Statistics -->
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bar-chart"></i> Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-primary mb-0">{{ $stats['total_users'] }}</h4>
                                        <small class="text-muted">Total Users</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-success mb-0">{{ $stats['active_users'] }}</h4>
                                    <small class="text-muted">Active Users</small>
                                </div>
                            </div>

                            <div class="row text-center mb-3">
                                <div class="col-6">
                                    <div class="border-end">
                                        <h4 class="text-info mb-0">{{ $stats['editors'] }}</h4>
                                        <small class="text-muted">Editors</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <h4 class="text-warning mb-0">{{ $stats['collections'] }}</h4>
                                    <small class="text-muted">Collections</small>
                                </div>
                            </div>

                            <div class="text-center">
                                <h4 class="text-secondary mb-0">{{ $stats['active_collections'] }}</h4>
                                <small class="text-muted">Active Collections</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
