@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-xl-10 col-xxl-9">

    {{-- Header --}}
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <p class="section-title mb-1">Legal Documents</p>
            <h2 class="fw-bold mb-1">{{ $document->exists ? 'Edit: ' . $document->title : 'New Legal Document' }}</h2>
        </div>
        <a href="{{ route('admin.legal.index') }}" class="btn btn-outline-secondary rounded-pill">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="glass-card">
        <div class="card-body p-4">
            <form method="POST"
                  action="{{ $document->exists ? route('admin.legal.update', $document) : route('admin.legal.store') }}">
                @csrf
                @if ($document->exists)
                    @method('PUT')
                @endif

                {{-- Document Type --}}
                @if (!$document->exists)
                    <div class="mb-4">
                        <label for="type" class="form-label fw-semibold">Document Type <span class="text-danger">*</span></label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                            <option value="">Select type…</option>
                            @foreach ($types as $key => $label)
                                <option value="{{ $key }}" {{ old('type', $document->type) === $key ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @else
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Document Type</label>
                        <p class="mb-0"><span class="badge bg-primary">{{ $document->type_label }}</span> — Version {{ $document->version }}</p>
                    </div>
                @endif

                {{-- Title --}}
                <div class="mb-4">
                    <label for="title" class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror"
                           value="{{ old('title', $document->title) }}" required maxlength="255"
                           placeholder="e.g. Terms of Service">
                    @error('title')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                {{-- Body (Rich Text) --}}
                <div class="mb-4">
                    <label for="body" class="form-label fw-semibold">Content <span class="text-danger">*</span></label>
                    <textarea name="body" id="body" class="form-control @error('body') is-invalid @enderror"
                              rows="20" required>{{ old('body', $document->body) }}</textarea>
                    @error('body')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">You can use HTML for formatting. Content will be displayed as-is to users.</div>
                </div>

                {{-- Required --}}
                <div class="mb-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_required" id="is_required" value="1"
                               {{ old('is_required', $document->is_required ?? true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_required">
                            Require users to accept this document
                        </label>
                    </div>
                    <div class="form-text">When enabled, users must accept this document during registration and when it's updated.</div>
                </div>

                {{-- Submit --}}
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i>
                        {{ $document->exists ? 'Save Changes' : 'Create Document' }}
                    </button>
                    <a href="{{ route('admin.legal.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

</div>
</div>
</section>
@endsection
