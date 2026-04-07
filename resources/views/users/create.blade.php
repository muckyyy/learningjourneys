@extends('layouts.app')

@section('content')
<section class="shell certificate-admin">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <span class="d-inline-flex align-items-center gap-2 text-muted text-uppercase small fw-semibold mb-2">
                <i class="bi bi-person-plus"></i> New user
            </span>
            <h1 class="mb-2">Create user</h1>
            <p class="text-muted mb-0">Set name and email, then send a secure setup link.</p>
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

            <div class="alert alert-info mb-0" role="alert">
                <i class="bi bi-envelope-check me-1"></i>
                After creation, the user will receive a secure email link to set their own password.
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                    <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                        <option value="">Select role</option>
                        <option value="regular" {{ old('role') == 'regular' ? 'selected' : '' }}>Regular user</option>
                        <option value="administrator" {{ old('role') == 'administrator' ? 'selected' : '' }}>Administrator</option>
                    </select>
                    @error('role')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
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
</script>
@endpush
