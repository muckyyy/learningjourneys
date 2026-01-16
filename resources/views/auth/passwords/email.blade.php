@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="hero blue">
        <h1 class="mb-2">Forgot your password?</h1>
        <p class="mb-0">Enter your email and we'll send a secure link to reset it.</p>
    </div>

    <div class="card">
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
