@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-people"></i> Users
                </h1>
                @if(auth()->user()->role === 'administrator')
                    <a href="{{ route('users.create') }}" class="btn btn-primary">
                        <i class="bi bi-plus-lg"></i> Create User
                    </a>
                @endif
            </div>

            @if($users->count() > 0)
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Institution</th>
                                        <th>Status</th>
                                        <th>Joined</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($users as $user)
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-circle me-2">
                                                        {{ strtoupper(substr($user->name, 0, 1)) }}
                                                    </div>
                                                    {{ $user->name }}
                                                </div>
                                            </td>
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
                                                {{ $user->institution ? $user->institution->name : 'N/A' }}
                                            </td>
                                            <td>
                                                @if($user->is_active)
                                                    <span class="badge bg-success">Active</span>
                                                @else
                                                    <span class="badge bg-secondary">Inactive</span>
                                                @endif
                                            </td>
                                            <td>{{ $user->created_at->format('M d, Y') }}</td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="{{ route('users.show', $user) }}" class="btn btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @if(auth()->user()->role === 'administrator')
                                                        <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        @if($user->id !== auth()->id())
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    data-user-id="{{ $user->id }}" 
                                                                    onclick="deleteUser(this.getAttribute('data-user-id'))">
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

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $users->links() }}
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-people display-1 text-muted"></i>
                    <h3 class="mt-3 text-muted">No users found</h3>
                    <p class="text-muted">Start by creating user accounts for your learning journey platform.</p>
                    @if(auth()->user()->role === 'administrator')
                        <a href="{{ route('users.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-lg"></i> Create Your First User
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Delete User Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this user? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteUserForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background-color: #6c757d;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: bold;
}
</style>

<script>
function deleteUser(userId) {
    const form = document.getElementById('deleteUserForm');
    form.action = `/users/${userId}`;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>
@endsection
