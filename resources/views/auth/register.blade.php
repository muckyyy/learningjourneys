@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="row g-4 align-items-center">
        <div class="col-lg-5">
            <div class="hero blue h-100">
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
            <div class="card">
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
