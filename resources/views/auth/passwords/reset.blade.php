@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="hero blue">
        <h1>Reset your password</h1>
        <p class="mb-0">Set a new password to get back into your The Thinking Course account.</p>
    </div>

    <div class="card">
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
