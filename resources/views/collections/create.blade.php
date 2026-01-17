@extends('layouts.app')

@section('content')
<section class="shell">
    <div class="glass-form-card">
        <p class="form-section-title mb-1">Collection details</p>
        <h2 class="h4 mb-4">Identity + ownership</h2>
        <form action="{{ route('collections.store') }}" method="POST" class="form-grid">
            @csrf

            <div>
                <label for="name" class="form-label">Collection name <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="certificate_prompt" class="form-label">Certificate prompt</label>
                <textarea class="form-control @error('certificate_prompt') is-invalid @enderror" id="certificate_prompt" name="certificate_prompt" rows="4" placeholder="Optional guidance for certificate text or AI instructions">{{ old('certificate_prompt', $defaultCertificatePrompt) }}</textarea>
                <div class="form-text">Optional instructions that accompany certificates issued from this collection.</div>
                @error('certificate_prompt')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="certificate_id" class="form-label">Certificate template</label>
                <select class="form-select @error('certificate_id') is-invalid @enderror" id="certificate_id" name="certificate_id">
                    <option value="">No certificate attached</option>
                    @foreach($certificates as $certificate)
                        <option value="{{ $certificate->id }}" {{ old('certificate_id') == $certificate->id ? 'selected' : '' }}>
                            {{ $certificate->name }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text">Choose which certificate design issues automatically for this collection's journeys.</div>
                @error('certificate_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-3">
                <div class="col-md-12">
                    <label for="institution_id" class="form-label">Institution <span class="text-danger">*</span></label>
                    <select class="form-select @error('institution_id') is-invalid @enderror" id="institution_id" name="institution_id" required>
                        <option value="">Select institution</option>
                        @foreach($institutions as $institution)
                            <option value="{{ $institution->id }}" {{ old('institution_id') == $institution->id ? 'selected' : '' }}>
                                {{ $institution->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('institution_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
               
            </div>

            <div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                    <label class="form-check-label" for="is_active">Active collection</label>
                </div>
                <div class="form-text">Active collections are visible to editors and learners.</div>
            </div>

            <div class="actions-row">
                <a href="{{ route('collections.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-lg"></i> Create collection
                </button>
            </div>
        </form>
    </div>
</section>
@endsection
