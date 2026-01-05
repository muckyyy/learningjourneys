@extends('layouts.app')

@push('styles')
<style>
.profile-edit-shell {
    width: min(1100px, 100%);
    max-width: 100%;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1rem, 4vw, 3rem) 4rem;
    box-sizing: border-box;
    overflow-x: hidden;
}
.edit-hero {
    background: linear-gradient(135deg, #0f172a, #a855f7 70%);
    border-radius: 36px;
    color: #fff;
    padding: clamp(2rem, 6vw, 4rem);
    display: flex;
    flex-wrap: wrap;
    gap: 1.75rem;
    align-items: center;
    margin-bottom: 2.5rem;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.35);
}
.hero-pill {
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    padding: 0.5rem 1.35rem;
    text-transform: uppercase;
    letter-spacing: 0.14em;
    font-size: 0.78rem;
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
}
.edit-hero h1 {
    font-size: clamp(2rem, 4vw, 2.75rem);
    margin-bottom: 0.35rem;
}
.hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.8rem 1.75rem;
    font-weight: 600;
}
.glass-card {
    border-radius: 32px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    margin-bottom: 2rem;
}
.glass-card .card-body {
    padding: clamp(1.75rem, 4vw, 2.75rem);
}
.form-section + .form-section {
    margin-top: 2.5rem;
}
.section-label {
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.75rem;
    color: #94a3b8;
}
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 1.25rem;
}
.field-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.25rem;
}
.field-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 22px;
    padding: 1.25rem;
    background: #f8fafc;
}
.field-card textarea,
.field-card select,
.field-card input {
    background: #fff;
}
.form-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 0.75rem;
    margin-top: 2rem;
}
.form-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.75rem;
    font-weight: 600;
}
.alert-banner {
    border-radius: 18px;
    padding: 1rem 1.25rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
}
.alert-banner.success {
    background: rgba(16, 185, 129, 0.15);
    color: #065f46;
    border: 1px solid rgba(16, 185, 129, 0.35);
}
.alert-banner.warning {
    background: rgba(251, 191, 36, 0.18);
    color: #92400e;
    border: 1px solid rgba(251, 191, 36, 0.35);
}
.empty-state {
    text-align: center;
    padding: 2rem 1rem;
}
.empty-state i {
    font-size: 3rem;
    color: #cbd5f5;
}
@media (max-width: 575.98px) {
    .hero-actions .btn,
    .form-actions .btn {
        width: 100%;
    }
}

@media (max-width: 640px) {
    .form-grid,
    .field-grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
@endpush

@section('content')
<section class="profile-edit-shell">
    <div class="edit-hero">
        <div class="flex-grow-1">
            <div class="hero-pill mb-3"><i class="bi bi-pencil"></i> Edit profile</div>
            <h1>Refine your profile details</h1>
            <p class="mb-0">Update custom fields, keep your information accurate, and help institutions tailor every journey.</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('profile.show') }}" class="btn btn-light text-dark"><i class="bi bi-arrow-left"></i> Back to profile</a>
            <a href="{{ route('dashboard') }}" class="btn btn-outline-light"><i class="bi bi-speedometer"></i> Dashboard</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-banner success">
            <i class="bi bi-check-circle fs-4"></i>
            <div>
                <strong>{{ session('success') }}</strong>
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert-banner warning">
            <i class="bi bi-exclamation-triangle fs-4"></i>
            <div>
                <strong>Required fields missing</strong>
                <p class="mb-0">{{ session('warning') }}</p>
            </div>
        </div>
    @endif

    <div class="glass-card">
        <div class="card-body">
            <form action="{{ route('profile.update') }}" method="POST">
                @csrf
                @method('PUT')

                <div class="form-section">
                    <p class="section-label mb-1">Basic</p>
                    <h4 class="mb-3">Profile snapshot</h4>
                    <div class="form-grid">
                        <div>
                            <label class="form-label">Name</label>
                            <input type="text" class="form-control" value="{{ $user->name }}" readonly>
                            <div class="form-text">Contact an administrator to change your name.</div>
                        </div>
                        <div>
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="{{ $user->email }}" readonly>
                            <div class="form-text">Contact an administrator to change your email.</div>
                        </div>
                    </div>
                </div>

                @if($profileFields->count() > 0)
                    <div class="form-section">
                        <p class="section-label mb-1">Custom</p>
                        <h4 class="mb-3">Additional information</h4>
                        <div class="field-grid">
                            @foreach($profileFields as $field)
                                @php
                                    $currentValue = $user->getProfileValue($field->short_name);
                                    $fieldName = 'profile_' . $field->short_name;
                                @endphp
                                <div class="field-card">
                                    <label for="{{ $fieldName }}" class="form-label fw-semibold">
                                        {{ $field->name }}
                                        @if($field->required)
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    @if($field->input_type === 'text')
                                        <input type="text" class="form-control @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" value="{{ old($fieldName, $currentValue) }}" {{ $field->required ? 'required' : '' }}>
                                    @elseif($field->input_type === 'number')
                                        <input type="number" class="form-control @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" value="{{ old($fieldName, $currentValue) }}" {{ $field->required ? 'required' : '' }}>
                                    @elseif($field->input_type === 'textarea')
                                        <textarea class="form-control @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" rows="3" {{ $field->required ? 'required' : '' }}>{{ old($fieldName, $currentValue) }}</textarea>
                                    @elseif($field->input_type === 'select')
                                        <select class="form-select @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}" {{ $field->required ? 'required' : '' }}>
                                            @if(!$field->required)
                                                <option value="">-- Select an option --</option>
                                            @endif
                                            @foreach($field->options as $option)
                                                <option value="{{ $option }}" {{ old($fieldName, $currentValue) === $option ? 'selected' : '' }}>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @elseif($field->input_type === 'select_multiple')
                                        <select class="form-select @error($fieldName) is-invalid @enderror" id="{{ $fieldName }}" name="{{ $fieldName }}[]" multiple {{ $field->required ? 'required' : '' }}>
                                            @foreach($field->options as $option)
                                                <option value="{{ $option }}" {{ in_array($option, old($fieldName, $currentValue ?: [])) ? 'selected' : '' }}>{{ $option }}</option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">Hold Ctrl/Cmd to select multiple options.</div>
                                    @endif

                                    @if($field->description)
                                        <div class="form-text">{{ $field->description }}</div>
                                    @endif

                                    @error($fieldName)
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                @else
                    <div class="empty-state">
                        <i class="bi bi-info-circle"></i>
                        <h5 class="mt-3">No additional fields</h5>
                        <p class="text-muted mb-0">Administrators have not configured extra profile questions yet.</p>
                    </div>
                @endif

                @if($profileFields->count() > 0)
                    <div class="form-actions">
                        <a href="{{ route('profile.show') }}" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-dark"><i class="bi bi-check-circle"></i> Update profile</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</section>
@endsection
