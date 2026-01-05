@extends('layouts.app')

@push('styles')
<style>
.users-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.users-hero {
    background: linear-gradient(135deg, #0f172a, #1d4ed8 70%);
    border-radius: 40px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(30, 64, 175, 0.35);
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
.users-hero h1 {
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
.users-table-card {
    border-radius: 34px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
}
.users-table-card .card-body {
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
    background: #1d4ed8;
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
    $totalUsers = method_exists($users, 'total') ? $users->total() : $users->count();
    $activeUsers = $users->where('is_active', true)->count();
    $adminCount = $users->where('role', 'administrator')->count();
@endphp
<section class="users-shell">
    <div class="users-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-people"></i> Users</div>
            <h1>Keep every persona in sync with your launch plans.</h1>
            <p class="mb-0">Admins, editors, institutions, and learners share the same glass dashboard. Manage access, activity, and memberships here.</p>
            <div class="hero-meta">
                <div class="meta-card">
                    <span>Total users</span>
                    <strong>{{ number_format($totalUsers) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Active in view</span>
                    <strong>{{ number_format($activeUsers) }}</strong>
                </div>
                <div class="meta-card">
                    <span>Admins</span>
                    <strong>{{ number_format($adminCount) }}</strong>
                </div>
            </div>
        </div>
        <div class="hero-actions">
            @if(auth()->user()->role === 'administrator')
                <a href="{{ route('users.create') }}" class="btn btn-light text-dark">
                    <i class="bi bi-plus-lg"></i> Create user
                </a>
            @endif
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light">
                <i class="bi bi-speedometer"></i> Dashboard
            </a>
        </div>
    </div>

    @if($users->count() > 0)
        <div class="users-table-card">
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
                                            <a href="{{ route('users.show', $user) }}" class="btn btn-outline-dark">
                                                <i class="bi bi-eye"></i>
                                            </a>
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
        <div class="empty-state">
            <i class="bi bi-people display-3 text-muted"></i>
            <h3 class="mt-3">No users yet</h3>
            <p class="text-muted">Invite your first admin, editor, or learner to unlock personalized journeys.</p>
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
