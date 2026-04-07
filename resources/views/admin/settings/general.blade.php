@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-xl-10 col-xxl-9">

    {{-- Header --}}
    <div class="mb-4">
        <a href="{{ route('admin.settings.index') }}" class="text-muted text-decoration-none small">
            <i class="bi bi-arrow-left"></i> Back to Settings
        </a>
        <h2 class="fw-bold mt-2 mb-1">General Config</h2>
        <p class="text-muted mb-0">Core platform settings. Changes take effect immediately.</p>
    </div>

    <form action="{{ route('admin.settings.general.update') }}" method="POST">
        @csrf

        {{-- Sign-up section --}}
        <div class="glass-card mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-1"><i class="bi bi-person-plus"></i> Sign-up</h5>
                <p class="text-muted small mb-4">Control whether new users can register and which token bundle they receive on sign-up.</p>

                <div class="row g-4">
                    {{-- Signup enabled toggle --}}
                    <div class="col-md-6">
                        <label class="form-label fw-medium">Registration</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="signup_enabled" name="signup_enabled" value="1" {{ old('signup_enabled', $signup_enabled) ? 'checked' : '' }}>
                            <label class="form-check-label" for="signup_enabled">Allow new user registration</label>
                        </div>
                        <div class="form-text">When disabled, only administrators can create accounts.</div>
                        @error('signup_enabled')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Signup token bundle --}}
                    <div class="col-md-6">
                        <label for="signup_token_bundle" class="form-label fw-medium">Welcome Token Bundle</label>
                        <select class="form-select @error('signup_token_bundle') is-invalid @enderror" id="signup_token_bundle" name="signup_token_bundle">
                            <option value="0" {{ old('signup_token_bundle', $signup_token_bundle) == 0 ? 'selected' : '' }}>None — no tokens on sign-up</option>
                            @foreach ($bundles as $bundle)
                                <option value="{{ $bundle->id }}" {{ old('signup_token_bundle', $signup_token_bundle) == $bundle->id ? 'selected' : '' }}>
                                    {{ $bundle->name }} ({{ number_format($bundle->token_amount) }} tokens)
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Automatically grant this token bundle to every new user.</div>
                        @error('signup_token_bundle')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Referral programme section --}}
        <div class="glass-card mb-4">
            <div class="card-body p-4">
                <h5 class="fw-semibold mb-1"><i class="bi bi-people"></i> Referral Programme</h5>
                <p class="text-muted small mb-4">Reward users who bring paying customers. After a set number of referred users make their first purchase, the referrer automatically receives a token bundle.</p>

                <div class="row g-4">
                    {{-- Referral enabled toggle --}}
                    <div class="col-md-4">
                        <label class="form-label fw-medium">Programme Status</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" id="referal_enabled" name="referal_enabled" value="1" {{ old('referal_enabled', $referal_enabled) ? 'checked' : '' }}>
                            <label class="form-check-label" for="referal_enabled">Enable referral programme</label>
                        </div>
                        <div class="form-text">Users will see their referral link on their profile.</div>
                        @error('referal_enabled')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Frequency --}}
                    <div class="col-md-4">
                        <label for="referal_frequency" class="form-label fw-medium">Paid Referrals Required</label>
                        <input type="number" class="form-control @error('referal_frequency') is-invalid @enderror" id="referal_frequency" name="referal_frequency" min="1" value="{{ old('referal_frequency', $referal_frequency) }}">
                        <div class="form-text">Number of referred paying users needed to trigger a reward.</div>
                        @error('referal_frequency')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    {{-- Referral token bundle --}}
                    <div class="col-md-4">
                        <label for="referal_token_bundle" class="form-label fw-medium">Reward Token Bundle</label>
                        <select class="form-select @error('referal_token_bundle') is-invalid @enderror" id="referal_token_bundle" name="referal_token_bundle">
                            <option value="0" {{ old('referal_token_bundle', $referal_token_bundle) == 0 ? 'selected' : '' }}>None — no reward</option>
                            @foreach ($bundles as $bundle)
                                <option value="{{ $bundle->id }}" {{ old('referal_token_bundle', $referal_token_bundle) == $bundle->id ? 'selected' : '' }}>
                                    {{ $bundle->name }} ({{ number_format($bundle->token_amount) }} tokens)
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text">Token bundle granted to the referrer when the threshold is reached.</div>
                        @error('referal_token_bundle')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary rounded-pill px-4">
                <i class="bi bi-check-lg"></i> Save Changes
            </button>
        </div>
    </form>

</div>
</div>
</section>
@endsection

@push('scripts')
<script>
// If a checkbox is unchecked, we still need to send a value
document.querySelector('form').addEventListener('submit', function () {
    ['signup_enabled', 'referal_enabled'].forEach(function (name) {
        const cb = document.getElementById(name);
        if (!cb.checked) {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = name;
            hidden.value = '0';
            cb.closest('form').appendChild(hidden);
        }
    });
});
</script>
@endpush
