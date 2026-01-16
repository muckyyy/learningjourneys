@extends('layouts.app')

@section('content')
@php
    $roleColors = [
        'administrator' => 'danger',
        'institution' => 'warning',
        'editor' => 'info',
        'regular' => 'secondary',
    ];
@endphp

<div class="shell">
    <div class="hero cyan">
        <div class="flex-grow-1">
            <div class="pill light mb-3"><i class="bi bi-building"></i> Institution Overview</div>
            <div class="d-flex align-items-center flex-wrap gap-3 mt-3">
                <h1 class="mb-0">{{ $institution->name }}</h1>
                <span class="status-chip {{ $institution->is_active ? 'status-active' : 'status-inactive' }}">
                    {{ $institution->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <p class="mt-3 mb-0 text-white-50">
                {{ $institution->description ?? 'No description provided yet. Give editors and members context about this institution.' }}
            </p>
            <div class="hero-meta">
                <div>
                    <span>Created</span>
                    <h6>{{ $institution->created_at->format('M d, Y') }}</h6>
                    <small>{{ $institution->created_at->diffForHumans() }}</small>
                </div>
                <div>
                    <span>Members</span>
                    <h6>{{ $stats['total_users'] }}</h6>
                    <small>{{ $stats['active_users'] }} active</small>
                </div>
                <div>
                    <span>Collections</span>
                    <h6>{{ $stats['collections'] }}</h6>
                    <small>{{ $stats['active_collections'] }} publishing</small>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            <a href="{{ route('institutions.index') }}" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> Back to list
            </a>
            @can('update', $institution)
                <a href="{{ route('institutions.edit', $institution) }}" class="btn btn-light text-dark">
                    <i class="bi bi-pencil"></i> Edit institution
                </a>
            @endcan
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <span>Total Members</span>
            <h3>{{ $stats['total_users'] }}</h3>
            <p class="text-muted mb-0">Across all roles</p>
        </div>
        <div class="stat-card">
            <span>Active Members</span>
            <h3>{{ $stats['active_users'] }}</h3>
            <p class="text-muted mb-0">Currently enabled</p>
        </div>
        <div class="stat-card">
            <span>Editors</span>
            <h3>{{ $stats['editors'] }}</h3>
            <p class="text-muted mb-0">Assigned to collections</p>
        </div>
        <div class="stat-card">
            <span>Collections</span>
            <h3>{{ $stats['collections'] }}</h3>
            <p class="text-muted mb-0">{{ $stats['active_collections'] }} active</p>
        </div>
    </div>

    <div class="content-grid">
        <div class="primary-column">
            <div class="glass-card members-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between flex-wrap align-items-center mb-4">
                        <div>
                            <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.18em;">Roster</p>
                            <h4 class="section-heading mb-0">Members ({{ $institution->members->count() }})</h4>
                        </div>
                        @can('update', $institution)
                            <span class="text-muted small">Invite existing accounts</span>
                        @endcan
                    </div>

                    @can('update', $institution)
                        <form action="{{ route('institutions.members.store', $institution) }}" method="POST" class="row g-3 mb-4 members-form">
                            @csrf
                            <div class="col-md-6">
                                <label for="member-email" class="form-label">User Email</label>
                                <input type="email" id="member-email" name="email" class="form-control" placeholder="user@example.com" value="{{ old('email') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label for="member-role" class="form-label">Role</label>
                                <select id="member-role" name="role" class="form-select" required>
                                    @foreach($availableRoles as $roleOption)
                                        <option value="{{ $roleOption }}" @selected(old('role') === $roleOption)>
                                            {{ ucfirst(str_replace('_', ' ', $roleOption)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-dark w-100">
                                    <i class="bi bi-person-plus"></i> Add
                                </button>
                            </div>
                        </form>
                    @endcan

                    @if($institution->members->count() > 0)
                        <div class="members-table">
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            @can('update', $institution)
                                                <th class="text-end">Actions</th>
                                            @endcan
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($institution->members as $member)
                                            <tr>
                                                <td>{{ $member->name }}</td>
                                                <td>{{ $member->email }}</td>
                                                <td>
                                                    @php $role = $member->pivot->role; @endphp
                                                    <span class="badge role-badge bg-{{ $roleColors[$role] ?? 'secondary' }}">
                                                        {{ \App\Enums\UserRole::label($role) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    @if($member->pivot->is_active)
                                                        <span class="badge bg-success">Active</span>
                                                    @else
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    @endif
                                                </td>
                                                <td>{{ optional($member->pivot->created_at)->format('M d, Y') ?? '—' }}</td>
                                                @can('update', $institution)
                                                    <td class="text-end">
                                                        <form action="{{ route('institutions.members.update', [$institution, $member]) }}" method="POST" class="d-flex flex-wrap gap-2 justify-content-end">
                                                            @csrf
                                                            @method('PATCH')
                                                            <select name="role" class="form-select form-select-sm w-auto">
                                                                @foreach($availableRoles as $roleOption)
                                                                    <option value="{{ $roleOption }}" @selected($member->pivot->role === $roleOption)>
                                                                        {{ ucfirst(str_replace('_', ' ', $roleOption)) }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                            <div class="form-check form-switch m-0">
                                                                <input type="hidden" name="is_active" value="0">
                                                                <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" @checked($member->pivot->is_active)>
                                                                <label class="form-check-label small">Active</label>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-outline-dark">Save</button>
                                                        </form>
                                                        <form action="{{ route('institutions.members.destroy', [$institution, $member]) }}" method="POST" class="mt-2" onsubmit="return confirm('Remove this member from the institution?');">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                                                        </form>
                                                    </td>
                                                @endcan
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <div class="empty-state">
                            <i class="bi bi-people"></i>
                            <p class="mb-0">No members yet</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="glass-card mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.18em;">Collections</p>
                            <h4 class="mb-0">Journey Collections ({{ $institution->journeyCollections->count() }})</h4>
                        </div>
                        @can('create', App\Models\JourneyCollection::class)
                            <a href="{{ route('collections.create') }}" class="btn btn-outline-dark rounded-pill">
                                <i class="bi bi-plus-lg"></i> New Collection
                            </a>
                        @endcan
                    </div>

                    @if($institution->journeyCollections->count() > 0)
                        <div class="collections-grid">
                            @foreach($institution->journeyCollections->take(6) as $collection)
                                <div class="collection-card">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="mb-0">{{ $collection->name }}</h5>
                                        <span class="badge {{ $collection->is_active ? 'bg-success' : 'bg-secondary' }}">
                                            {{ $collection->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-3">{{ Str::limit($collection->description, 100) }}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Editors: {{ $collection->editors->pluck('name')->implode(', ') ?: 'Pending' }}</small>
                                        <a href="{{ route('collections.show', $collection) }}" class="btn btn-sm btn-outline-primary">View</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if($institution->journeyCollections->count() > 6)
                            <div class="text-center mt-3">
                                <a href="{{ route('collections.index') }}" class="btn btn-outline-primary">View all collections</a>
                            </div>
                        @endif
                    @else
                        <div class="empty-state">
                            <i class="bi bi-collection"></i>
                            <p class="mb-0">No collections yet</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="secondary-column">
            <div class="glass-card contact-card">
                <div class="card-body">
                    <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.18em;">Key Contacts</p>
                    <h4 class="mb-4">Institution Details</h4>
                    <ul class="contact-list">
                        <li class="contact-item">
                            <div class="contact-icon"><i class="bi bi-envelope"></i></div>
                            <div class="contact-meta">
                                <span>Email</span>
                                <h6><a href="mailto:{{ $institution->contact_email }}">{{ $institution->contact_email }}</a></h6>
                            </div>
                        </li>
                        @if($institution->contact_phone)
                            <li class="contact-item">
                                <div class="contact-icon"><i class="bi bi-telephone"></i></div>
                                <div class="contact-meta">
                                    <span>Phone</span>
                                    <h6>{{ $institution->contact_phone }}</h6>
                                </div>
                            </li>
                        @endif
                        @if($institution->website)
                            <li class="contact-item">
                                <div class="contact-icon"><i class="bi bi-globe"></i></div>
                                <div class="contact-meta">
                                    <span>Website</span>
                                    <h6><a href="{{ $institution->website }}" target="_blank">{{ Str::limit($institution->website, 32) }}</a></h6>
                                </div>
                            </li>
                        @endif
                        @if($institution->address)
                            <li class="contact-item">
                                <div class="contact-icon"><i class="bi bi-geo-alt"></i></div>
                                <div class="contact-meta">
                                    <span>Address</span>
                                    <h6>{{ $institution->address }}</h6>
                                </div>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            <div class="glass-card">
                <div class="card-body">
                    <p class="text-uppercase small text-muted mb-1" style="letter-spacing: 0.18em;">Snapshot</p>
                    <h4 class="mb-4">Operational notes</h4>
                    <ul class="list-unstyled mb-0">
                        <li class="mb-3 d-flex align-items-start gap-2">
                            <i class="bi bi-check2-circle text-success"></i>
                            <div>
                                <strong>{{ $stats['active_users'] }}</strong> members currently active.
                            </div>
                        </li>
                        <li class="mb-3 d-flex align-items-start gap-2">
                            <i class="bi bi-people text-primary"></i>
                            <div>
                                {{ $stats['editors'] }} editors assigned across collections.
                            </div>
                        </li>
                        <li class="d-flex align-items-start gap-2">
                            <i class="bi bi-bar-chart text-warning"></i>
                            <div>
                                {{ $stats['collections'] }} total collections with {{ $stats['active_collections'] }} live.
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
