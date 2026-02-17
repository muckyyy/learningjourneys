@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="glass-form-card">
        <p class="form-section-title mb-1">Institution details</p>
        <h2 class="h4 mb-4">Profile + contact</h2>
        <form action="{{ route('institutions.update', $institution) }}" method="POST" class="form-grid">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="form-label">Institution name <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $institution->name) }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $institution->description) }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control @error('address') is-invalid @enderror" id="address" name="address" rows="2">{{ old('address', $institution->address) }}</textarea>
                @error('address')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="contact_email" class="form-label">Contact email <span class="text-danger">*</span></label>
                    <input type="email" class="form-control @error('contact_email') is-invalid @enderror" id="contact_email" name="contact_email" value="{{ old('contact_email', $institution->contact_email) }}" required>
                    @error('contact_email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="contact_phone" class="form-label">Contact phone</label>
                    <input type="text" class="form-control @error('contact_phone') is-invalid @enderror" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $institution->contact_phone) }}">
                    @error('contact_phone')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div>
                <label for="website" class="form-label">Website</label>
                <input type="url" class="form-control @error('website') is-invalid @enderror" id="website" name="website" value="{{ old('website', $institution->website) }}" placeholder="https://example.com">
                @error('website')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $institution->is_active) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active institution</label>
                </div>
                <div class="form-text">Inactive institutions lose access to collections, editors, and token flows.</div>
            </div>

            <div class="actions-row">
                <a href="{{ route('institutions.show', $institution) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-lg"></i> Save changes
                </button>
            </div>
        </form>
    </div>
</section>
@endsection
