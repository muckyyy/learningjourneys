@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="row g-4 align-items-center">
        <div class="col-lg-5">
            <div class="hero blue h-100">
                <p class="text-uppercase small mb-2" style="letter-spacing: 0.18em;">Welcome back</p>
                <h1 class="mb-3">Log in to keep your journeys in motion.</h1>
                <p class="text-white-50 mb-4">Pick up where you left off with saved attempts, token insights, and personalized learning flows built for fast-moving teams.</p>
                <div class="d-flex flex-wrap gap-2">
                    <span class="stat-pill">AI voice ready</span>
                    <span class="stat-pill">Secure by default</span>
                    <span class="stat-pill">Role aware</span>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="login-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <p class="text-uppercase small text-muted mb-1">Login</p>
                        <h5 class="mb-0">Access your The Thinking Course account</h5>
                    </div>
                    <a href="{{ route('register') }}" class="subtle-link text-secondary">Need an account?</a>
                </div>
                @if (session('error'))
                    <div class="alert alert-danger custom-alert" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if (session('status'))
                    <div class="alert alert-success custom-alert" role="alert">
                        {{ session('status') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="d-flex flex-column gap-3 mb-4">
                    @csrf

                    <div class="form-floating">
                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="name@example.com">
                        <label for="email">Email address</label>
                        @error('email')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="form-floating">
                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="********">
                        <label for="password">Password</label>
                        @error('password')
                            <span class="invalid-feedback" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <label class="remember-toggle mb-0">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                            <span class="text-muted">Keep me signed in</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="subtle-link" href="{{ route('password.request') }}">Forgot password?</a>
                        @endif
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-dark login-btn">Log in</button>
                        <small class="text-muted">Secured with email verification and device-aware monitoring.</small>
                    </div>
                </form>

                @php
                    $googleEnabled = config('services.google.enabled')
                        && config('services.google.client_id')
                        && config('services.google.client_secret')
                        && config('services.google.redirect');
                    $facebookEnabled = config('services.facebook.enabled')
                        && config('services.facebook.client_id')
                        && config('services.facebook.client_secret')
                        && config('services.facebook.redirect');
                    $linkedinEnabled = config('services.linkedin.enabled')
                        && config('services.linkedin.client_id')
                        && config('services.linkedin.client_secret')
                        && config('services.linkedin.redirect');
                    $appleEnabled = config('services.apple.enabled')
                        && config('services.apple.client_id')
                        && config('services.apple.client_secret')
                        && config('services.apple.redirect');
                    $microsoftEnabled = config('services.microsoft.enabled')
                        && config('services.microsoft.client_id')
                        && config('services.microsoft.client_secret')
                        && config('services.microsoft.redirect');
                    $socialEnabled = $googleEnabled || $facebookEnabled || $linkedinEnabled || $appleEnabled || $microsoftEnabled;
                @endphp

                @if ($socialEnabled)
                    <div class="divider-text mb-3">or use single sign-on</div>
                    <div class="social-grid">
                        @if ($googleEnabled)
                            <a href="{{ route('oauth.redirect', ['provider' => 'google']) }}" class="social-btn">
                                <span class="d-inline-flex" style="width: 18px; height: 18px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 533.5 544.3" width="18" height="18">
                                        <path fill="#4285f4" d="M533.5 278.4c0-17.4-1.6-34.1-4.7-50.3H272v95.2h147.3c-6.4 34.6-25.4 63.9-54.1 83.5v68.9h87.5c51.2-47.2 80.8-116.7 80.8-197.3z"/>
                                        <path fill="#34a853" d="M272 544.3c72.9 0 134.3-24.1 179-65.5l-87.5-68.9c-24.3 16.3-55.4 26-91.5 26-70.4 0-130.1-47.5-151.5-111.3H31.3v69.9c44.7 88.4 136.6 149.8 240.7 149.8z"/>
                                        <path fill="#fbbc04" d="M120.5 324.6c-10.8-32.3-10.8-67.6 0-99.9V154.8H31.3c-38 75.8-38 166.5 0 242.3z"/>
                                        <path fill="#ea4335" d="M272 107.7c39.7-.6 77.8 14.5 106.7 41.4l79.3-79.3C404.5 24.4 340.5-.6 272 0 167.9 0 76 61.4 31.3 149.8l89.2 69.9C141.9 155.2 201.6 107.7 272 107.7z"/>
                                    </svg>
                                </span>
                                Google
                            </a>
                        @endif
                        @if ($facebookEnabled)
                            <a href="{{ route('oauth.redirect', ['provider' => 'facebook']) }}" class="social-btn">
                                <span class="d-inline-flex" style="width: 18px; height: 18px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                        <path d="M22 12.07C22 6.517 17.523 2 12 2S2 6.517 2 12.07C2 17.095 5.657 21.245 10.438 22v-6.96H7.898v-2.97h2.54V9.845c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.196 2.238.196v2.47h-1.26c-1.243 0-1.63.776-1.63 1.572v1.886h2.773l-.443 2.97h-2.33V22C18.343 21.245 22 17.095 22 12.07"/>
                                    </svg>
                                </span>
                                Facebook
                            </a>
                        @endif
                        @if ($linkedinEnabled)
                            <a href="{{ route('oauth.redirect', ['provider' => 'linkedin']) }}" class="social-btn">
                                <span class="d-inline-flex" style="width: 18px; height: 18px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                                        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.352V9h3.414v1.561h.049c.476-.9 1.637-1.852 3.37-1.852 3.602 0 4.27 2.37 4.27 5.456zM5.337 7.433a2.062 2.062 0 1 1 0-4.125 2.062 2.062 0 0 1 0 4.125zm-1.777 13.02h3.554V9H3.56zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.228.792 24 1.771 24h20.451C23.2 24 24 23.228 24 22.271V1.729C24 .774 23.2 0 22.222 0"/>
                                    </svg>
                                </span>
                                LinkedIn
                            </a>
                        @endif
                        @if ($appleEnabled)
                            <a href="{{ route('oauth.redirect', ['provider' => 'apple']) }}" class="social-btn">
                                <span class="d-inline-flex" style="width: 18px; height: 18px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                        <path d="M16.365 1.43c.05.06.09.13.13.2-.55.35-1.04.81-1.41 1.35-.37.55-.62 1.18-.72 1.84-.01.06-.02.12-.02.18 0 .05 0 .1.01.16.08.01.16.02.25.02.55 0 1.13-.16 1.64-.46.51-.3.95-.74 1.27-1.27.29-.47.49-.99.61-1.53-.54-.05-1.09-.04-1.56.02-.07 0-.14.04-.2.09z"/>
                                        <path d="M21.74 17.82c-.1.22-.21.43-.32.63-.27.48-.58.93-.94 1.34-.33.38-.68.73-1.05 1.05-.43.37-.9.69-1.4.94-.43.21-.86.37-1.31.47-.43.1-.86.15-1.31.15-.47 0-.94-.06-1.4-.17-.43-.1-.85-.25-1.25-.43-.36-.16-.72-.35-1.06-.57-.27-.17-.53-.35-.78-.55-.25-.2-.49-.42-.72-.64-.43-.42-.82-.87-1.18-1.37-.34-.48-.64-.99-.9-1.52-.32-.63-.58-1.28-.77-1.95-.16-.58-.27-1.18-.33-1.79-.05-.54-.05-1.09 0-1.63.05-.63.17-1.26.34-1.88.19-.66.45-1.29.78-1.89.35-.64.78-1.23 1.27-1.77.31-.34.64-.66.99-.96.33-.29.69-.55 1.06-.78.38-.24.78-.44 1.2-.62.44-.18.88-.31 1.34-.4.5-.1 1-.14 1.51-.13.43 0 .86.04 1.29.12.48.09.96.23 1.42.41.45.18.88.41 1.3.67.44.27.85.58 1.24.92-.04.05-.07.1-.11.15-.33.43-.64.88-.92 1.35-.29.5-.53 1.02-.73 1.56-.24.64-.42 1.3-.53 1.98-.11.68-.14 1.36-.08 2.04.05.67.18 1.33.38 1.97.18.56.4 1.1.68 1.62.24.45.51.89.81 1.31.26.36.55.7.87 1.01.31.31.64.6 1 .85.28.19.58.36.89.51.26.12.53.21.81.28.25.06.5.09.76.09.17 0 .34-.02.52-.05-.02.14-.06.27-.11.4z"/>
                                    </svg>
                                </span>
                                Apple
                            </a>
                        @endif
                        @if ($microsoftEnabled)
                            <a href="{{ route('oauth.redirect', ['provider' => 'microsoft']) }}" class="social-btn">
                                <span class="d-inline-flex" style="width: 18px; height: 18px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 23 23" width="16" height="16" fill="currentColor">
                                        <path d="M1 1h10v10H1z"/>
                                        <path d="M12 1h10v10H12z"/>
                                        <path d="M1 12h10v10H1z"/>
                                        <path d="M12 12h10v10H12z"/>
                                    </svg>
                                </span>
                                Microsoft
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endsection
