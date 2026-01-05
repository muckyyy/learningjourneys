@extends('layouts.app')

@push('styles')
<style>
.institution-create-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.institution-create-hero {
    background: linear-gradient(130deg, #0f172a, #0ea5e9 70%);
    border-radius: 38px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    box-shadow: 0 30px 70px rgba(14, 165, 233, 0.35);
    margin-bottom: 2.5rem;
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    padding: 0.55rem 1.4rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.3);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.institution-create-hero h1 {
    font-size: clamp(2rem, 4vw, 3rem);
    margin-bottom: 0.35rem;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.85rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.9rem 1.8rem;
    font-weight: 600;
}
.glass-form-card {
    border-radius: 34px;
    border: 1px solid rgba(15, 23, 42, 0.08);
    background: #fff;
    box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    padding: clamp(1.75rem, 4vw, 3rem);
}
.form-grid {
    display: grid;
    gap: 1.5rem;
}
.form-control,
.form-select,
textarea {
    border-radius: 18px;
    padding: 0.95rem 1.1rem;
}
.form-section-title {
    text-transform: uppercase;
    letter-spacing: 0.2em;
    font-size: 0.75rem;
    color: #94a3b8;
}
.actions-row {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}
.actions-row .btn {
    border-radius: 999px;
    padding: 0.7rem 1.6rem;
}
@media (max-width: 575.98px) {
    .hero-actions { width: 100%; }
    .hero-actions .btn { width: 100%; }
    .actions-row { flex-direction: column; }
    .actions-row .btn { width: 100%; }
}
</style>
@endpush

@section('content')
<section class="institution-create-shell">
    <div class="institution-create-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-plus-lg"></i> New institution</div>
            <h1>Give a partner org the same polished experience.</h1>
            <p class="mb-0">Complete the details to unlock editor management, collections, and token oversight for this institution.</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('institutions.index') }}" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> Back to roster
            </a>
        </div>
    </div>

    <div class="glass-form-card">
        <p class="form-section-title mb-1">Institution details</p>
        <h2 class="h4 mb-4">Profile + contact</h2>
        <form action="{{ route('institutions.store') }}" method="POST" class="form-grid">
            @csrf

            <div>
                <label for="name" class="form-label">Institution name <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address') }}</textarea>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="contact_email" class="form-label">Contact email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" value="{{ old('contact_email') }}" required>
                    @error('contact_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="contact_phone" class="form-label">Contact phone</label>
                    <input type="text" class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" name="contact_phone" value="{{ old('contact_phone') }}">
                    @error('contact_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div>
                <label for="website" class="form-label">Website</label>
                <input type="url" class="form-control @error('website') is-invalid @enderror" id="website" name="website" value="{{ old('website') }}" placeholder="https://example.com">
                @error('website')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active institution</label>
                </div>
                <div class="form-text">Active institutions can launch collections and invite editors immediately.</div>
            </div>

            <div class="actions-row">
                <a href="{{ route('institutions.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-lg"></i> Create institution
                </button>
            </div>
        </form>
    </div>
</section>
@endsection
