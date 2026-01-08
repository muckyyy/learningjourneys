@extends('layouts.app')

@push('styles')
<style>
    .certificate-edit .hero-card {
        background: linear-gradient(135deg, rgba(14, 165, 233, 0.9), rgba(37, 99, 235, 0.8));
        color: #fff;
        border: none;
    }
    .certificate-edit .form-shell {
        border-radius: 1.5rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
        background: #fff;
    }
    .certificate-edit label {
        font-weight: 600;
        color: #0f172a;
    }
    .certificate-edit .helper-text {
        font-size: 0.9rem;
        color: #64748b;
    }
</style>
@endpush

@section('content')
<div class="container py-4 certificate-edit">
    <div class="card hero-card rounded-4 p-4 p-lg-5 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="d-inline-flex align-items-center gap-2 mb-3 text-white-75">
                    <i class="bi bi-gear"></i> Edit Certificate Settings
                </span>
                <h1 class="h2 fw-semibold mb-2">{{ $certificate->name }}</h1>
                <p class="mb-0 text-white-75">Update core attributes such as availability, validity window, and page dimensions.</p>
            </div>
            <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-light rounded-pill">
                <i class="bi bi-arrow-left"></i> Back to library
            </a>
        </div>
    </div>

    <div class="form-shell p-4 p-lg-5">
        <form method="POST" action="{{ route('admin.certificates.update', $certificate) }}" class="d-flex flex-column gap-4">
            @csrf
            @method('PUT')

            <div>
                <label for="name" class="form-label">Certificate name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $certificate->name) }}" class="form-control form-control-lg" required>
                @error('name')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <label for="validity_days" class="form-label">Validity window (days)</label>
                    <input type="number" min="1" max="3650" id="validity_days" name="validity_days" value="{{ old('validity_days', $certificate->validity_days) }}" class="form-control" placeholder="Optional">
                    <p class="helper-text mt-2">Leave empty if the certificate never expires.</p>
                    @error('validity_days')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
                <div class="col-12 col-lg-6 d-flex align-items-center">
                    <div class="form-check form-switch fs-5">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" {{ old('enabled', $certificate->enabled) ? 'checked' : '' }}>
                        <label class="form-check-label ms-2" for="enabled">Enabled</label>
                        <p class="helper-text mt-2 mb-0">Disable to pause issuance for all institutions.</p>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="page_size">Page size</label>
                    <select class="form-select" id="page_size" name="page_size">
                        @foreach(['A4' => 'A4 (210mm × 297mm)', 'LETTER' => 'Letter (216mm × 279mm)'] as $value => $label)
                            <option value="{{ $value }}" {{ old('page_size', strtoupper($certificate->page_size)) === $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="orientation">Orientation</label>
                    <select class="form-select" id="orientation" name="orientation">
                        <option value="portrait" {{ old('orientation', $certificate->orientation) === 'portrait' ? 'selected' : '' }}>Portrait</option>
                        <option value="landscape" {{ old('orientation', $certificate->orientation) === 'landscape' ? 'selected' : '' }}>Landscape</option>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Dimensions (mm)</label>
                    <div class="input-group">
                        <input type="number" class="form-control" id="page_width_mm" name="page_width_mm" value="{{ old('page_width_mm', $certificate->page_width_mm) }}" min="100" max="2000">
                        <span class="input-group-text">×</span>
                        <input type="number" class="form-control" id="page_height_mm" name="page_height_mm" value="{{ old('page_height_mm', $certificate->page_height_mm) }}" min="100" max="2000">
                    </div>
                    <p class="helper-text mt-2">Override the preset to support custom certificate boards.</p>
                </div>
            </div>

            <div class="d-flex flex-column flex-md-row gap-3 justify-content-end">
                <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                <button type="submit" class="btn btn-primary rounded-pill px-4">
                    <i class="bi bi-save me-1"></i> Save changes
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
