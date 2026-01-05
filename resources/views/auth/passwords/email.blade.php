@extends('layouts.app')

@push('styles')
<style>
.reset-request-shell {
    width: min(520px, 100%);
    margin: 0 auto;
    padding: clamp(1.5rem, 6vw, 4rem) clamp(1rem, 6vw, 2rem) 4rem;
}
.reset-request-hero {
    background: linear-gradient(135deg, #0f172a, #0ea5e9 70%);
    border-radius: 32px;
    color: #fff;
    padding: clamp(1.75rem, 5vw, 3rem);
    text-align: center;
    box-shadow: 0 25px 60px rgba(14, 165, 233, 0.35);
    margin-bottom: 1.75rem;
}
.reset-request-card {
    border-radius: 28px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
}
.reset-request-card .card-body {
    padding: clamp(1.75rem, 4vw, 2.75rem);
}
.form-label { font-weight: 600; color: #0f172a; }
.form-control { border-radius: 16px; padding: 0.85rem 1rem; }
.reset-request-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.5rem;
    font-weight: 600;
}
</style>
@endpush

@section('content')
<section class="reset-request-shell">
    <div class="reset-request-hero">
        <h1 class="mb-2">Forgot your password?</h1>
        <p class="mb-0">Enter your email and we’ll send a secure link to reset it.</p>
    </div>

    <div class="reset-request-card">
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success mb-4" role="alert">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="mb-4">
                    <label for="email" class="form-label">Email address</label>
                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                    @error('email')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="reset-request-actions d-flex flex-column gap-3">
                    <button type="submit" class="btn btn-dark w-100">Send reset link</button>
                    <a href="{{ route('login') }}" class="btn btn-outline-secondary w-100">Back to sign in</a>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
