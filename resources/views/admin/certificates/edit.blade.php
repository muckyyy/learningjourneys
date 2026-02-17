@extends('layouts.app')

@section('content')
<section class="shell certificate-admin">
    <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
        <div>
            <span class="d-inline-flex align-items-center gap-2 text-muted text-uppercase small fw-semibold mb-2">
                <i class="bi bi-gear"></i> Certificate Studio
            </span>
            <h1 class="mb-2">{{ $certificate->name }}</h1>
            <p class="text-muted mb-0">Update availability, validity windows, and layout dimensions for this definition.</p>
        </div>
        <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left"></i> Back to certificates
        </a>
    </div>

    <div class="glass-form-card">
        <p class="form-section-title mb-1">Certificate details</p>
        <h2 class="h4 mb-4">Definition + layout</h2>

        <form method="POST" action="{{ route('admin.certificates.update', $certificate) }}" class="form-grid">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="form-label">Certificate name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" value="{{ old('name', $certificate->name) }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="row">
                <div>
                    <label for="validity_days" class="form-label">Validity window (days)</label>
                    <input type="number" min="1" max="3650" id="validity_days" name="validity_days" value="{{ old('validity_days', $certificate->validity_days) }}" class="form-control @error('validity_days') is-invalid @enderror" placeholder="Optional">
                    <div class="form-text">Leave empty if the certificate never expires.</div>
                    @error('validity_days')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex align-items-center">
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" {{ old('enabled', $certificate->enabled) ? 'checked' : '' }}>
                        <label class="form-check-label toggle-label" for="enabled">Enabled</label>
                        <div class="form-text mb-0">Disable to pause issuance for all institutions.</div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div>
                    <label class="form-label" for="page_size">Page size</label>
                    <select class="form-select" id="page_size" name="page_size">
                        @foreach(['A4' => 'A4 (210mm × 297mm)', 'LETTER' => 'Letter (216mm × 279mm)'] as $value => $label)
                            <option value="{{ $value }}" {{ old('page_size', strtoupper($certificate->page_size)) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="orientation">Orientation</label>
                    <select class="form-select" id="orientation" name="orientation">
                        <option value="portrait" {{ old('orientation', $certificate->orientation) === 'portrait' ? 'selected' : '' }}>Portrait</option>
                        <option value="landscape" {{ old('orientation', $certificate->orientation) === 'landscape' ? 'selected' : '' }}>Landscape</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Dimensions (mm)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="page_width_mm" name="page_width_mm" value="{{ old('page_width_mm', $certificate->page_width_mm) }}" min="100" max="2000">
                        <span class="input-group-text">×</span>
                        <input type="number" class="form-control" id="page_height_mm" name="page_height_mm" value="{{ old('page_height_mm', $certificate->page_height_mm) }}" min="100" max="2000">
                    </div>
                    <div class="form-text">Override the preset to support custom certificate boards.</div>
                </div>
            </div>

            <div class="actions-row">
                <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-x-lg"></i> Cancel
                </a>
                <button type="submit" class="btn btn-dark">
                    <i class="bi bi-save me-1"></i> Save changes
                </button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const presets = {
        'A4': { portrait: [210, 297], landscape: [297, 210] },
        'LETTER': { portrait: [216, 279], landscape: [279, 216] },
    };
    const pageSize = document.getElementById('page_size');
    const orientation = document.getElementById('orientation');
    const widthInput = document.getElementById('page_width_mm');
    const heightInput = document.getElementById('page_height_mm');

    function applyPreset(force = false) {
        const size = pageSize.value || 'A4';
        const dir = orientation.value || 'portrait';
        const preset = presets[size]?.[dir];
        if (!preset) return;
        if (!widthInput.value || force) widthInput.value = preset[0];
        if (!heightInput.value || force) heightInput.value = preset[1];
    }

    pageSize.addEventListener('change', () => applyPreset(true));
    orientation.addEventListener('change', () => applyPreset(true));
    applyPreset(false);
});
</script>
@endpush
