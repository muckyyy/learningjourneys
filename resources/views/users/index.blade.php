@extends('layouts.app')

@section('content')
@php
    $totalUsers = method_exists($users, 'total') ? $users->total() : $users->count();
    $activeUsers = $users->where('is_active', true)->count();
    $adminCount = $users->where('role', 'administrator')->count();
    $metricCards = [
        [
            'label' => 'Total users',
            'value' => number_format($totalUsers),
            'description' => 'All roles combined',
            'icon' => 'bi-people',
            'accent' => 'accent-indigo',
        ],
        [
            'label' => 'Active',
            'value' => number_format($activeUsers),
            'description' => 'Enabled accounts',
            'icon' => 'bi-activity',
            'accent' => 'accent-teal',
        ],
        [
            'label' => 'Administrators',
            'value' => number_format($adminCount),
            'description' => 'Global control',
            'icon' => 'bi-shield-lock',
            'accent' => 'accent-amber',
        ],
        [
            'label' => 'Institutions linked',
            'value' => number_format($users->whereNotNull('institution_id')->count()),
            'description' => 'Users with org context',
            'icon' => 'bi-building',
            'accent' => 'accent-rose',
        ],
    ];
@endphp
<section class="shell certificate-admin">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="mb-1">Users</h1>
            <p class="text-muted mb-0">Manage admins, editors, and institutional seats from a unified list.</p>
        </div>
        @if(auth()->user()->role === 'administrator')
            <a href="{{ route('users.create') }}" class="btn btn-dark rounded-pill px-4">
                <i class="bi bi-plus-lg"></i> Create user
            </a>
        @endif
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

    @if($users->count() > 0)
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-modern align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Institution</th>
                                <th>Status</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($users as $user)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle">
                                                {{ strtoupper(substr($user->name, 0, 1)) }}
                                            </div>
                                            <div>
                                                <strong>{{ $user->name }}</strong>
                                                <p class="mb-0 text-muted small">{{ ucfirst($user->role) }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $user->email }}</td>
                                    <td>
                                        <span class="badge rounded-pill bg-{{ 
                                            $user->role === 'administrator' ? 'danger' : 
                                            ($user->role === 'institution' ? 'warning text-dark' : 
                                            ($user->role === 'editor' ? 'info' : 'secondary')) 
                                        }}">
                                            {{ ucfirst($user->role) }}
                                        </span>
                                    </td>
                                    <td>{{ $user->institution ? $user->institution->name : 'N/A' }}</td>
                                    <td>
                                        <span class="status-pill {{ $user->is_active ? 'bg-success text-white' : 'bg-secondary text-white' }}">
                                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            @if(auth()->user()->canImpersonate() && $user->canBeImpersonated())
                                                <form action="{{ route('impersonation.start', $user) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-dark" title="Impersonate user">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if(auth()->user()->role === 'administrator')
                                                <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                @if($user->id !== auth()->id())
                                                    <button type="button" class="btn btn-outline-danger" data-user-id="{{ $user->id }}" onclick="deleteUser(this.getAttribute('data-user-id'))">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                @endif
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
            {{ $users->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <div class="rounded-circle bg-light d-inline-flex align-items-center justify-content-center mb-3" style="width:96px;height:96px;">
                <i class="bi bi-people text-muted fs-2"></i>
            </div>
            <h3 class="fw-bold">No users yet</h3>
            <p class="text-muted mb-4">Invite your first admin, editor, or learner to unlock personalized journeys.</p>
            @if(auth()->user()->role === 'administrator')
                <a href="{{ route('users.create') }}" class="btn btn-dark rounded-pill px-4">
                    <i class="bi bi-plus-lg"></i> Create user
                </a>
            @endif
        </div>
    @endif
</section>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete user</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this user? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteUserForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function deleteUser(userId) {
    const form = document.getElementById('deleteUserForm');
    form.action = `/users/${userId}`;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>
@endsection
