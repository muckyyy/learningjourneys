@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-plus-circle"></i> Add New Profile Field</h2>
                <a href="{{ route('profile-fields.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to List
                </a>
            </div>

            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Profile Field Details</h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('profile-fields.store') }}" method="POST">
                                @csrf
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="name" class="form-label">Display Name *</label>
                                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                                   id="name" name="name" value="{{ old('name') }}" required>
                                            @error('name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="short_name" class="form-label">Short Name (Key) *</label>
                                            <input type="text" class="form-control @error('short_name') is-invalid @enderror" 
                                                   id="short_name" name="short_name" value="{{ old('short_name') }}" 
                                                   placeholder="e.g., phone_number" pattern="[a-z_]+" required>
                                            <div class="form-text">Only lowercase letters and underscores allowed</div>
                                            @error('short_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="input_type" class="form-label">Input Type *</label>
                                            <select class="form-select @error('input_type') is-invalid @enderror" 
                                                    id="input_type" name="input_type" required>
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
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="required" 
                                                       name="required" value="1" {{ old('required') ? 'checked' : '' }}>
                                                <label class="form-check-label" for="required">
                                                    Required Field
                                                </label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="is_active" 
                                                       name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_active">
                                                    Active
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3" id="options-container" style="display: none;">
                                    <label for="options" class="form-label">Options (one per line)</label>
                                    <textarea class="form-control @error('options') is-invalid @enderror" 
                                              id="options" name="options" rows="5" 
                                              placeholder="Option 1&#10;Option 2&#10;Option 3">{{ old('options') }}</textarea>
                                    <div class="form-text">Enter each option on a new line</div>
                                    @error('options')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control @error('description') is-invalid @enderror" 
                                              id="description" name="description" rows="3" 
                                              placeholder="Optional description for this field">{{ old('description') }}</textarea>
                                    @error('description')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('profile-fields.index') }}" class="btn btn-secondary">Cancel</a>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check-circle"></i> Create Field
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('input_type').addEventListener('change', function() {
    const optionsContainer = document.getElementById('options-container');
    const value = this.value;
    
    if (value === 'select' || value === 'select_multiple') {
        optionsContainer.style.display = 'block';
        document.getElementById('options').required = true;
    } else {
        optionsContainer.style.display = 'none';
        document.getElementById('options').required = false;
    }
});

// Auto-generate short_name from name
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

// Trigger on page load to show options if needed
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('input_type').dispatchEvent(new Event('change'));
});
</script>
@endsection
