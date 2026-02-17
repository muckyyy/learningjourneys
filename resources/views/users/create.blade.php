@extends('layouts.app')

@section('content')
<section class="shell certificate-admin">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <span class="d-inline-flex align-items-center gap-2 text-muted text-uppercase small fw-semibold mb-2">
                <i class="bi bi-person-plus"></i> New user
            </span>
            <h1 class="mb-2">Create user</h1>
            <p class="text-muted mb-0">Provision admins, editors, or learners with institution-aware controls.</p>
        </div>
        <a href="{{ route('users.index') }}" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left"></i> Back to roster
        </a>
    </div>

    <div class="glass-form-card">
        <p class="form-section-title mb-1">Identity & permissions</p>
        <h2 class="h4 mb-4">Core details</h2>

        <form action="{{ route('users.store') }}" method="POST" class="form-grid">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Full name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required>
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="password_confirmation" class="form-label">Confirm password <span class="text-danger">*</span></label>
                    <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                        <option value="">Select role</option>
                        <option value="regular" {{ old('role') == 'regular' ? 'selected' : '' }}>Regular user</option>
                        <option value="editor" {{ old('role') == 'editor' ? 'selected' : '' }}>Editor</option>
                        <option value="institution" {{ old('role') == 'institution' ? 'selected' : '' }}>Institution</option>
                        <option value="administrator" {{ old('role') == 'administrator' ? 'selected' : '' }}>Administrator</option>
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
                            <option value="{{ $institution->id }}" {{ old('institution_id') == $institution->id ? 'selected' : '' }}>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">Required for Editor and Institution roles.</div>
                </div>
            </div>

            <div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active user</label>
                </div>
                <div class="form-text">Active users can log in and access journeys immediately.</div>
            </div>

            <div class="actions-row">
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-lg"></i> Create user
                </button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const roleSelect = document.getElementById('role');
    const institutionField = document.getElementById('institution_id');
    const institutionLabel = document.querySelector('label[for="institution_id"]');

    if (!roleSelect || !institutionField || !institutionLabel) {
        return;
    }

    const toggleInstitutionRequirement = () => {
        const roleValue = roleSelect.value;
        const requiresInstitution = roleValue === 'editor' || roleValue === 'institution';

        institutionField.required = requiresInstitution;
        institutionLabel.innerHTML = requiresInstitution
            ? 'Institution <span class="text-danger">*</span>'
            : 'Institution';
    };

    roleSelect.addEventListener('change', toggleInstitutionRequirement);
    toggleInstitutionRequirement();
});
</script>
@endpush
