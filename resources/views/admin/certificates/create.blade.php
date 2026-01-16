@extends('layouts.app')

@section('content')
<div class="container py-4 certificate-create">
    <div class="card hero-card rounded-4 p-4 p-lg-5 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="d-inline-flex align-items-center gap-2 mb-3 text-white-75">
                    <i class="bi bi-award"></i> New Certificate
                </span>
                <h1 class="h2 fw-semibold mb-2">Design a certificate definition</h1>
                <p class="mb-0 text-white-75">Set a name, enablement state, and validity window. Additional layout settings can be configured later.</p>
            </div>
            <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-light rounded-pill">
                <i class="bi bi-arrow-left"></i> Back to library
            </a>
        </div>
    </div>

    <div class="form-shell p-4 p-lg-5">
        <form method="POST" action="{{ route('admin.certificates.store') }}" class="d-flex flex-column gap-4">
            @csrf
            <div>
                <label for="name" class="form-label">Certificate name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="form-control form-control-lg" placeholder="e.g. STEM Completion Award" required>
                <p class="helper-text mt-2">Use a descriptive title that will be visible on the certificate and in admin lists.</p>
                @error('name')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <label for="validity_days" class="form-label">Validity window (days)</label>
                    <input type="number" min="1" max="3650" id="validity_days" name="validity_days" value="{{ old('validity_days') }}" class="form-control" placeholder="Optional">
                    <p class="helper-text mt-2">Leave empty if the certificate should never expire. Maximum 10 years (3650 days).</p>
                    @error('validity_days')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-lg-6 d-flex align-items-center">
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" {{ old('enabled', true) ? 'checked' : '' }}>
                        <label class="form-check-label toggle-label" for="enabled">Enable certificate immediately</label>
                        <p class="helper-text mt-2 mb-0">When enabled, authorized institutions can start issuing this certificate.</p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="page_size">Page size</label>
                    <select class="form-select" id="page_size" name="page_size">
                        @foreach(['A4' => 'A4 (210mm × 297mm)', 'LETTER' => 'Letter (216mm × 279mm)'] as $value => $label)
                            <option value="{{ $value }}" {{ old('page_size', 'A4') === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="orientation">Orientation</label>
                    <select class="form-select" id="orientation" name="orientation">
                        <option value="portrait" {{ old('orientation', 'portrait') === 'portrait' ? 'selected' : '' }}>Portrait</option>
                        <option value="landscape" {{ old('orientation', 'portrait') === 'landscape' ? 'selected' : '' }}>Landscape</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Dimensions (mm)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="page_width_mm" name="page_width_mm" value="{{ old('page_width_mm') }}" placeholder="Width" min="100" max="2000">
                        <span class="input-group-text">×</span>
                        <input type="number" class="form-control" id="page_height_mm" name="page_height_mm" value="{{ old('page_height_mm') }}" placeholder="Height" min="100" max="2000">
                    </div>
                    <p class="helper-text mt-2">Defaults to the selected size but you can override it for custom layouts.</p>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row gap-3 justify-content-end">
                <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-plus-circle me-1"></i> Create certificate
                </button>
            </div>
        </form>
    </div>
</div>
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
