@extends('layouts.app')

@section('content')
<section class="shell">

    @if(session('success'))
        <div class="alert-banner success">
            <i class="bi bi-check-circle fs-4"></i>
            <div>
                <strong>{{ session('success') }}</strong>
            </div>
        </div>
    @endif

    <div class="glass-card">
        <form action="{{ route('profile.password.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-section">
                <p class="text-uppercase small text-muted mb-1 letter-spacing-wide">Security</p>
                <h4 class="mb-3">Change password</h4>
                <div class="form-grid">
                    <div>
                        <label for="password" class="form-label fw-semibold">New password</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" required minlength="8" autocomplete="new-password">
                        @error('password')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="form-label fw-semibold">Confirm password</label>
                        <input type="password" class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                        @error('password_confirmation')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-dark"><i class="bi bi-lock"></i> Update password</button>
            </div>
        </form>
    </div>
</section>
@endsection
