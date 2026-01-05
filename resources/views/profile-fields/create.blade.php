@extends('layouts.app')

@push('styles')
<style>
.profile-field-shell {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: clamp(1.5rem, 4vw, 4rem) clamp(1.5rem, 4vw, 3rem) 4rem;
}
.profile-field-hero {
    background: linear-gradient(135deg, #0f172a, #22d3ee);
    border-radius: 40px;
    padding: clamp(2rem, 4.5vw, 4rem);
    color: #fff;
    display: flex;
    flex-wrap: wrap;
    gap: 1.5rem;
    align-items: center;
    margin-bottom: 2.5rem;
    box-shadow: 0 30px 70px rgba(15, 23, 42, 0.4);
}
.hero-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1.4rem;
    border-radius: 999px;
    background: rgba(15, 23, 42, 0.35);
    letter-spacing: 0.16em;
    font-size: 0.78rem;
    text-transform: uppercase;
}
.profile-field-hero h1 {
    font-size: clamp(2rem, 4vw, 3.1rem);
    margin-bottom: 0.4rem;
}
.hero-actions {
    margin-left: auto;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}
.hero-actions .btn {
    border-radius: 999px;
    padding: 0.85rem 1.8rem;
    font-weight: 600;
}
.glass-form-card {
    border-radius: 34px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
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
.form-section {
    text-transform: uppercase;
    letter-spacing: 0.18em;
    font-size: 0.75rem;
    color: #94a3b8;
    margin-bottom: 0.75rem;
}
.switches {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
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
    .switches { flex-direction: column; }
}
</style>
@endpush

@section('content')
<section class="profile-field-shell">
    <div class="profile-field-hero">
        <div class="flex-grow-1">
            <div class="hero-pill"><i class="bi bi-plus-circle"></i> New profile field</div>
            <h1>Capture the exact learner metadata you need.</h1>
            <p class="mb-0">Launch a bespoke input in minutes, from keys to select menus, then surface it across onboarding journeys.</p>
        </div>
        <div class="hero-actions">
            <a href="{{ route('profile-fields.index') }}" class="btn btn-outline-light">
                <i class="bi bi-arrow-left"></i> Back to list
            </a>
        </div>
    </div>

    <div class="glass-form-card">
        <p class="form-section mb-1">Field blueprint</p>
        <h2 class="h4 mb-4">Identity + behavior</h2>
        <form action="{{ route('profile-fields.store') }}" method="POST" class="form-grid">
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Display name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="short_name" class="form-label">Short name (key) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('short_name') is-invalid @enderror" id="short_name" name="short_name" value="{{ old('short_name') }}" placeholder="e.g., phone_number" pattern="[a-z_]+" required>
                    <div class="form-text">Lowercase letters + underscores only</div>
                    @error('short_name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="input_type" class="form-label">Input type <span class="text-danger">*</span></label>
                    <select class="form-select @error('input_type') is-invalid @enderror" id="input_type" name="input_type" required>
                        <option value="">Select input type...</option>
                        @foreach($inputTypes as $value => $label)
                            <option value="{{ $value }}" {{ old('input_type') === $value ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    @error('input_type')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <div class="switches">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="required" name="required" value="1" {{ old('required') ? 'checked' : '' }}>
                            <label class="form-check-label" for="required">Required field</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>

            <div id="options-container" style="display: none;">
                <label for="options" class="form-label">Options (one per line)</label>
                <textarea class="form-control @error('options') is-invalid @enderror" id="options" name="options" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3">{{ old('options') }}</textarea>
                <div class="form-text">Each line becomes a selectable value.</div>
                @error('options')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Optional description for this field">{{ old('description') }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="actions-row">
                <a href="{{ route('profile-fields.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-circle"></i> Create field
                </button>
            </div>
        </form>
    </div>
</section>

<script>
document.getElementById('input_type').addEventListener('change', function() {
    const optionsContainer = document.getElementById('options-container');
    const value = this.value;
    const optionsField = document.getElementById('options');
    if (value === 'select' || value === 'select_multiple') {
        optionsContainer.style.display = 'block';
        optionsField.required = true;
    } else {
        optionsContainer.style.display = 'none';
        optionsField.required = false;
    }
});

document.getElementById('name').addEventListener('input', function() {
    const shortNameField = document.getElementById('short_name');
    if (shortNameField.value === '') {
        const shortName = this.value.toLowerCase()
            .replace(/[^a-z0-9\s]/g, '')
            .replace(/\s+/g, '_')
            .replace(/^_+|_+$/g, '');
        shortNameField.value = shortName;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('input_type').dispatchEvent(new Event('change'));
});
</script>
@endsection
