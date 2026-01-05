@extends('layouts.app')

@push('styles')
<style>
.auth-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
}
.auth-card {
    border-radius: 32px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.12);
    background: #fff;
    padding: clamp(2rem, 5vw, 3.5rem);
}
.auth-hero {
    background: linear-gradient(135deg, #0f172a, #1d4ed8 65%);
    border-radius: 32px;
    color: #fff;
    padding: clamp(2rem, 5vw, 3.5rem);
    box-shadow: 0 20px 40px rgba(15, 23, 42, 0.3);
}
.auth-hero h1 { font-weight: 700; }
.form-floating > label { color: #6c738a; }
.form-floating .form-control {
    border-radius: 18px;
    border: 1px solid rgba(15, 23, 42, 0.15);
    padding: 1.1rem 1rem;
}
.form-floating .form-control:focus {
    border-color: #0f172a;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}
.auth-btn {
    border-radius: 18px;
    padding: 0.95rem 2.5rem;
    font-weight: 600;
}
.hint-link { text-decoration: none; font-weight: 500; }
.hint-link:hover { text-decoration: underline; }
.recaptcha-shell {
    border-radius: 18px;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.03);
}
</style>
@endpush

@section('content')
<section class="auth-shell">
    <div class="row g-4 align-items-center">
        <div class="col-lg-5">
            <div class="auth-hero h-100">
                <p class="text-uppercase small mb-2" style="letter-spacing: 0.18em;">Create account</p>
                <h1 class="mb-3">Start your journey with the same modern toolkit.</h1>
                <p class="text-white-50 mb-4">Unlock curated collections, token tracking, and Alpine-powered dashboards. Join editors, learners, and institutions building daily momentum.</p>
                <ul class="list-unstyled text-white-50 mb-0 d-flex flex-column gap-2">
                    <li><i class="bi bi-check-circle me-2"></i>Personalized learning cards & smart filters</li>
                    <li><i class="bi bi-check-circle me-2"></i>Role-aware navigation & token ledger</li>
                    <li><i class="bi bi-check-circle me-2"></i>Voice-ready journeys and rich analytics</li>
                </ul>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="auth-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <p class="text-uppercase small text-muted mb-1">Register</p>
                    </div>
                    <a href="{{ route('login') }}" class="hint-link text-secondary">Already have an account?</a>
                </div>

                <form method="POST" action="{{ route('register') }}" class="d-flex flex-column gap-3">
                    @csrf

                    <div class="form-floating">
                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="Jane Doe">
                        <label for="name">Full name</label>
                        @error('name')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="form-floating">
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="name@example.com">
                        <label for="email">Work email</label>
                        @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="form-floating">
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="********">
                        <label for="password">Create password</label>
                        @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="form-floating">
                        <input id="password-confirm" type="password" class="form-control" name="password_confirmation" required autocomplete="new-password" placeholder="********">
                        <label for="password-confirm">Confirm password</label>
                    </div>

                    @if(config('services.recaptcha.enabled') && config('services.recaptcha.site_key'))
                        <div class="recaptcha-shell">
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                            @error('g-recaptcha-response')
                                <span class="text-danger small d-block mt-2">{{ $message }}</span>
                            @enderror
                        </div>
                    @endif

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-dark auth-btn">Create your account</button>
                        <small class="text-muted">By continuing you agree to our terms and privacy policy.</small>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
    @if(config('services.recaptcha.enabled') && config('services.recaptcha.site_key'))
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @endif
@endpush
