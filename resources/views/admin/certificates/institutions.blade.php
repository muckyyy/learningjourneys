@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-3">
        <a href="{{ route('admin.certificates.index') }}" class="btn btn-link text-decoration-none px-0">
            <i class="bi bi-arrow-left"></i> Back to certificate library
        </a>
        @if(session('status'))
            <div class="alert alert-success mb-0 rounded-pill px-4 py-2">
                {{ session('status') }}
            </div>
        @endif
    </div>

    <div class="assignments-shell mb-4">
        <div class="hero">
            <span class="badge text-bg-light text-dark mb-2">Institution access</span>
            <h1 class="h3 mb-2">{{ $certificate->name }}</h1>
            <p class="mb-0 text-white-50">
                Manage which institutions can issue this certificate template. Changes apply immediately.
            </p>
        </div>
        <div class="body">
            <div class="certificate-meta mb-4">
                <div class="d-flex flex-wrap gap-4">
                    <div>
                        <div class="text-muted text-uppercase small mb-1">Identifier</div>
                        <div class="fw-semibold">#{{ $certificate->id }}</div>
                    </div>
                    <div>
                        <div class="text-muted text-uppercase small mb-1">Size & orientation</div>
                        <div class="fw-semibold">{{ strtoupper($certificate->page_size) }} · {{ ucfirst($certificate->orientation) }} · {{ $certificate->page_width_mm }}mm × {{ $certificate->page_height_mm }}mm</div>
                    </div>
                    <div>
                        <div class="text-muted text-uppercase small mb-1">Status</div>
                        <span class="badge {{ $certificate->enabled ? 'text-bg-success-subtle' : 'text-bg-secondary-subtle' }}">
                            {{ $certificate->enabled ? 'Enabled' : 'Disabled' }}
                        </span>
                    </div>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.certificates.institutions.update', $certificate) }}">
                @csrf
                @method('PUT')

                <div class="assignment-list mb-4 bg-white">
                    @forelse($institutions as $institution)
                        @php
                            $checked = in_array($institution->id, $assigned, true);
                        @endphp
                        <label class="assignment-row" for="institution-{{ $institution->id }}">
                            <div class="flex-grow-1">
                                <div class="title">{{ $institution->name }}</div>
                                <div class="meta">{{ $institution->contact_email ?? 'No contact email' }}</div>
                            </div>
                            <span class="status-pill {{ $institution->is_active ? 'status-active' : 'status-inactive' }}">
                                {{ $institution->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="institutions[]" value="{{ $institution->id }}" id="institution-{{ $institution->id }}" {{ $checked ? 'checked' : '' }}>
                            </div>
                        </label>
                    @empty
                        <div class="p-5 text-center text-muted">
                            No institutions have been created yet.
                        </div>
                    @endforelse
                </div>

                @error('institutions')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                @error('institutions.*')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <p class="mb-0 text-muted small">
                        Checked institutions will gain immediate access. Unchecking removes their ability to issue certificates.
                    </p>
                    <div class="d-flex gap-2">
                        <a href="{{ route('admin.certificates.index') }}" class="btn btn-outline-secondary rounded-pill">Cancel</a>
                        <button type="submit" class="btn btn-primary rounded-pill">
                            <i class="bi bi-save me-1"></i> Save assignments
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
