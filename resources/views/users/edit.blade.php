@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-pencil"></i> Edit User: {{ $user->name }}
                </h1>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Users
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <form action="{{ route('users.update', $user) }}" method="POST">
                                @csrf
                                @method('PUT')

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" 
                                               class="form-control @error('name') is-invalid @enderror" 
                                               id="name" 
                                               name="name" 
                                               value="{{ old('name', $user->name) }}" 
                                               required>
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" 
                                               class="form-control @error('email') is-invalid @enderror" 
                                               id="email" 
                                               name="email" 
                                               value="{{ old('email', $user->email) }}" 
                                               required>
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Password</label>
                                        <input type="password" 
                                               class="form-control @error('password') is-invalid @enderror" 
                                               id="password" 
                                               name="password">
                                        <div class="form-text">Leave blank to keep current password</div>
                                        @error('password')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="password_confirmation" class="form-label">Confirm Password</label>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password_confirmation" 
                                               name="password_confirmation">
                                        <div class="form-text">Required only if changing password</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                        <select class="form-select @error('role') is-invalid @enderror" 
                                                id="role" 
                                                name="role" 
                                                required>
                                            <option value="">Select Role</option>
                                            <option value="regular" {{ old('role', $user->role) == 'regular' ? 'selected' : '' }}>Regular User</option>
                                            <option value="editor" {{ old('role', $user->role) == 'editor' ? 'selected' : '' }}>Editor</option>
                                            <option value="institution" {{ old('role', $user->role) == 'institution' ? 'selected' : '' }}>Institution</option>
                                            <option value="administrator" {{ old('role', $user->role) == 'administrator' ? 'selected' : '' }}>Administrator</option>
                                        </select>
                                        @error('role')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label for="institution_id" class="form-label">Institution</label>
                                        <select class="form-select @error('institution_id') is-invalid @enderror" 
                                                id="institution_id" 
                                                name="institution_id">
                                            <option value="">Select Institution (Optional)</option>
                                            @foreach($institutions as $institution)
                                                <option value="{{ $institution->id }}" 
                                                        {{ old('institution_id', $user->institution_id) == $institution->id ? 'selected' : '' }}>
                                                    {{ $institution->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('institution_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <div class="form-text">Required for Editor and Institution roles</div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="is_active" 
                                               name="is_active" 
                                               value="1" 
                                               {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="is_active">
                                            Active
                                        </label>
                                    </div>
                                    <div class="form-text">
                                        Active users can log in and use the system.
                                    </div>
                                </div>

                                <!-- User Information -->
                                <div class="card bg-light mb-3">
                                    <div class="card-body">
                                        <h6 class="card-title">User Information</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    <strong>Created:</strong> {{ $user->created_at->format('F j, Y g:i A') }}
                                                </small>
                                            </div>
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    <strong>Last Updated:</strong> {{ $user->updated_at->format('F j, Y g:i A') }}
                                                </small>
                                            </div>
                                        </div>
                                        @if($user->email_verified_at)
                                            <div class="mt-2">
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle"></i> Email Verified
                                                </span>
                                            </div>
                                        @else
                                            <div class="mt-2">
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-exclamation-triangle"></i> Email Not Verified
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                        <i class="bi bi-x-lg"></i> Cancel
                                    </a>
                                    @if($user->id !== Auth::id())
                                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                                            <i class="bi bi-trash"></i> Delete User
                                        </button>
                                    @endif
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-lg"></i> Update User
                                    </button>
                                </div>
                            </form>

                            @if($user->id !== Auth::id())
                                <!-- Delete Form (hidden) -->
                                <form id="deleteForm" action="{{ route('users.destroy', $user) }}" method="POST" style="display: none;">
                                    @csrf
                                    @method('DELETE')
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide institution requirement based on role
document.getElementById('role').addEventListener('change', function() {
    const institutionField = document.getElementById('institution_id');
    const institutionLabel = document.querySelector('label[for="institution_id"]');
    const roleValue = this.value;
    
    if (roleValue === 'editor' || roleValue === 'institution') {
        institutionField.required = true;
        institutionLabel.innerHTML = 'Institution <span class="text-danger">*</span>';
    } else {
        institutionField.required = false;
        institutionLabel.innerHTML = 'Institution';
    }
});

// Initialize institution requirement on page load
document.addEventListener('DOMContentLoaded', function() {
    const roleField = document.getElementById('role');
    const event = new Event('change');
    roleField.dispatchEvent(event);
});

// Confirm delete function
function confirmDelete() {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@endsection
