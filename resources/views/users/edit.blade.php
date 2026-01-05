@extends('layouts.app')

@push('styles')
<style>
.user-edit-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.user-edit-hero {
    background: linear-gradient(135deg, #0f172a, #6366f1 70%);
    border-radius: 38px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(99, 102, 241, 0.35);
    margin-bottom: 2.5rem;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.35rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.3);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.user-edit-hero h1 {
    font-size: clamp(2rem, 4.2vw, 3rem);
    margin-bottom: 0.35rem;
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
.glass-form-card {
    border-radius: 34px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    padding: clamp(1.75rem, 4vw, 3rem);
}
.form-grid {
    display: grid;
    gap: 1.5rem;
}
.form-control,
.form-select,
textarea {
    border-radius: 18px;
    padding: 0.95rem 1.1rem;
}
.form-section-title {
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.75rem;
    color: #94a3b8;
}
.actions-row {
    display: flex;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.actions-row .btn {
    border-radius: 999px;
    padding: 0.7rem 1.6rem;
}
.user-meta-card {
    border-radius: 26px;
    background: #f8fafc;
    border: 1px dashed rgba(15, 23, 42, 0.1);
    padding: 1.5rem;
    display: grid;
    gap: 0.75rem;
}
.user-meta-card small {
    color: #475569;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
    .actions-row { flex-direction: column; }
    .actions-row .btn { width: 100%; }
}
</style>
@endpush

@section('content')
<section class="user-edit-shell">
    <div class="user-edit-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-pencil"></i> Edit user</div>
            <h1>Tune {{ $user->name }}'s access story in seconds.</h1>
            <p class="mb-0">Refresh credentials, switch roles, or update institutions—all while keeping compliance in lockstep.</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('users.index') }}" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> Back to roster
            </a>
        </div>
    </div>

    <div class="glass-form-card">
        <p class="form-section-title mb-1">Identity & permissions</p>
        <h2 class="h4 mb-4">Core details</h2>
        <form action="{{ route('users.update', $user) }}" method="POST" class="form-grid">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email', $user->email) }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password">
                    <div class="form-text">Leave blank to keep the existing password.</div>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">Confirm password</label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation">
                    <div class="form-text">Only required when setting a new password.</div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                        <option value="">Select role</option>
                        <option value="regular" {{ old('role', $user->role) == 'regular' ? 'selected' : '' }}>Regular user</option>
                        <option value="editor" {{ old('role', $user->role) == 'editor' ? 'selected' : '' }}>Editor</option>
                        <option value="institution" {{ old('role', $user->role) == 'institution' ? 'selected' : '' }}>Institution</option>
                        <option value="administrator" {{ old('role', $user->role) == 'administrator' ? 'selected' : '' }}>Administrator</option>
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="institution_id" class="form-label">Institution</label>
                    <select class="form-select @error('institution_id') is-invalid @enderror" id="institution_id" name="institution_id">
                        <option value="">Select institution (optional)</option>
                        @foreach($institutions as $institution)
                            <option value="{{ $institution->id }}" {{ old('institution_id', $user->institution_id) == $institution->id ? 'selected' : '' }}>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Required whenever the role is Editor or Institution.</div>
                </div>
            </div>

            <div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active user</label>
                </div>
                <div class="form-text">Active users can log in and access journeys immediately.</div>
            </div>

            <div class="user-meta-card">
                <div class="d-flex flex-wrap gap-4">
                    <small><strong>Created:</strong> {{ $user->created_at->format('F j, Y g:i A') }}</small>
                    <small><strong>Updated:</strong> {{ $user->updated_at->format('F j, Y g:i A') }}</small>
                </div>
                @if($user->email_verified_at)
                    <span class="badge bg-success align-self-start">
                        <i class="bi bi-check-circle"></i> Email verified
                    </span>
                @else
                    <span class="badge bg-warning text-dark align-self-start">
                        <i class="bi bi-exclamation-triangle"></i> Email not verified
                    </span>
                @endif
            </div>

            <div class="actions-row">
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                @if($user->id !== Auth::id())
                    <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                        <i class="bi bi-trash"></i> Delete user
                    </button>
                @endif
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-lg"></i> Update user
                </button>
            </div>
        </form>
    </div>
</section>

@if($user->id !== Auth::id())
    <form id="deleteForm" action="{{ route('users.destroy', $user) }}" method="POST" class="d-none">
        @csrf
        @method('DELETE')
    </form>
@endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleSelect = document.getElementById('role');
    const institutionField = document.getElementById('institution_id');
    const institutionLabel = document.querySelector('label[for="institution_id"]');

    const toggleInstitutionRequirement = () => {
        const roleValue = roleSelect.value;
        const needsInstitution = roleValue === 'editor' || roleValue === 'institution';
        institutionField.required = needsInstitution;
        institutionLabel.innerHTML = needsInstitution
            ? 'Institution <span class="text-danger">*</span>'
            : 'Institution';
    };

    roleSelect.addEventListener('change', toggleInstitutionRequirement);
    toggleInstitutionRequirement();
});

function confirmDelete() {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        document.getElementById('deleteForm').submit();
    }
}
</script>
@endpush
