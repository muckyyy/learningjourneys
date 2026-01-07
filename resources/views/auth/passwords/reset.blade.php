@extends('layouts.app')

@push('styles')
<style>
.reset-shell {
    width: min(520px, 100%);
    margin: 0 auto;
    padding: clamp(1.5rem, 6vw, 4rem) clamp(1rem, 6vw, 2rem) 4rem;
}
.reset-hero {
    background: linear-gradient(135deg, #0f172a, #6366f1 70%);
    border-radius: 32px;
    color: #fff;
    padding: clamp(1.75rem, 5vw, 3rem);
    text-align: center;
    box-shadow: 0 25px 60px rgba(99, 102, 241, 0.35);
    margin-bottom: 1.75rem;
}
.reset-hero h1 {
    font-size: clamp(1.8rem, 4vw, 2.4rem);
    margin-bottom: 0.5rem;
}
.reset-card {
    border-radius: 28px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
}
.reset-card .card-body {
    padding: clamp(1.75rem, 4vw, 2.75rem);
}
.form-label {
    font-weight: 600;
    color: #0f172a;
}
.form-control {
    border-radius: 16px;
    padding: 0.85rem 1rem;
}
.reset-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-top: 1.5rem;
}
.reset-actions .btn {
    border-radius: 999px;
    padding: 0.9rem 1.5rem;
    font-weight: 600;
}
.helper-links a {
    color: #6b7280;
    text-decoration: none;
    font-weight: 600;
}
.helper-links a:hover {
    color: #0f172a;
}
</style>
@endpush

@section('content')
<section class="reset-shell">
    <div class="reset-hero">
        <h1>Reset your password</h1>
        <p class="mb-0">Set a new password to get back into your The Thinking Course account.</p>
    </div>

    <div class="reset-card">
        <div class="card-body">
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ $email ?? old('email') }}" required autocomplete="email" autofocus>
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">New password</label>
                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password">
                    @error('password')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password-confirm" class="form-label">Confirm password</label>
                    <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password">
                </div>

                <div class="reset-actions">
                    <button type="submit" class="btn btn-dark w-100">Reset password</button>
                    <div class="helper-links text-center">
                        <a href="{{ route('login') }}">Back to sign in</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
