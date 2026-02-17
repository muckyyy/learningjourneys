@extends('layouts.app')

@section('content')
<section class="shell certificate-admin profile-fields-admin">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <span class="d-inline-flex align-items-center gap-2 text-muted text-uppercase small fw-semibold mb-2">
                <i class="bi bi-person-lines-fill"></i> Profile Fields
            </span>
            <h1 class="mb-2">{{ $profileField->name }}</h1>
            <p class="text-muted mb-0">Adjust naming, keys, or selectable values and propagate changes across every onboarding touchpoint.</p>
        </div>
        <a href="{{ route('profile-fields.index') }}" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left"></i> Back to profile fields
        </a>
    </div>

    <div class="glass-form-card">
        <p class="form-section-title mb-1">Field blueprint</p>
        <h2 class="h4 mb-4">Identity + behavior</h2>
        <form action="{{ route('profile-fields.update', $profileField) }}" method="POST" class="form-grid">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="name" class="form-label">Display name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $profileField->name) }}" required>
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-md-6">
                    <label for="short_name" class="form-label">Short name (key) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control @error('short_name') is-invalid @enderror" id="short_name" name="short_name" value="{{ old('short_name', $profileField->short_name) }}" placeholder="e.g., phone_number" pattern="[a-z_]+" required>
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
                            <option value="{{ $value }}" {{ old('input_type', $profileField->input_type) === $value ? 'selected' : '' }}>
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
                            <input class="form-check-input" type="checkbox" id="required" name="required" value="1" {{ old('required', $profileField->required) ? 'checked' : '' }}>
                            <label class="form-check-label" for="required">Required field</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $profileField->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                </div>
            </div>

            <div id="options-container" style="display: none;">
                <label for="options" class="form-label">Options (one per line)</label>
                <textarea class="form-control @error('options') is-invalid @enderror" id="options" name="options" rows="4" placeholder="Option 1&#10;Option 2&#10;Option 3">{{ old('options', $profileField->options ? implode("\n", $profileField->options) : '') }}</textarea>
                <div class="form-text">Each line becomes a selectable value.</div>
                @error('options')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div>
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" placeholder="Optional description for this field">{{ old('description', $profileField->description) }}</textarea>
                @error('description')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="actions-row">
                <a href="{{ route('profile-fields.index') }}" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-check-circle"></i> Update field
                </button>
            </div>
        </form>
    </div>
</section>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const inputType = document.getElementById('input_type');
    const optionsContainer = document.getElementById('options-container');
    const optionsField = document.getElementById('options');

    function toggleOptions() {
        if (!inputType || !optionsContainer || !optionsField) return;
        const useOptions = ['select', 'select_multiple'].includes(inputType.value);
        optionsContainer.style.display = useOptions ? 'block' : 'none';
        optionsField.required = useOptions;
    }

    if (inputType) {
        inputType.addEventListener('change', toggleOptions);
        toggleOptions();
    }
});
</script>
@endpush
@endsection
