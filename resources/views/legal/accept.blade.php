@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-lg-8">

    <div class="glass-card">
        <div class="card-body p-4 p-lg-5">
            <h2 class="fw-bold mb-2">Please Review &amp; Accept</h2>
            <p class="text-muted mb-4">We've updated our legal documents. Please review and accept them to continue using the platform.</p>

            @if ($errors->any())
                <div class="alert alert-danger mb-4">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('legal.consent.store') }}">
                @csrf

                @foreach ($documents as $document)
                    <div class="border rounded-3 p-4 mb-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h5 class="fw-semibold mb-1">{{ $document->title }}</h5>
                                <small class="text-muted">Version {{ $document->version }} — {{ $document->type_label }}</small>
                            </div>
                            <a href="{{ route('legal.show', $document->slug) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Read Full Document
                            </a>
                        </div>

                        <div class="legal-preview bg-light rounded p-3 mb-3" style="max-height: 200px; overflow-y: auto; font-size: .875rem;">
                            {!! Str::limit(strip_tags($document->body), 500) !!}
                        </div>

                        <div class="form-check">
                            <input class="form-check-input @error('consent_' . $document->id) is-invalid @enderror"
                                   type="checkbox" name="consent_{{ $document->id }}" id="consent_{{ $document->id }}" value="1">
                            <label class="form-check-label" for="consent_{{ $document->id }}">
                                I have read and agree to the <strong>{{ $document->title }}</strong>
                                @if ($document->is_required)
                                    <span class="text-danger">*</span>
                                @endif
                            </label>
                            @error('consent_' . $document->id)
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                @endforeach

                <button type="submit" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-check-lg me-1"></i> Accept &amp; Continue
                </button>
            </form>
        </div>
    </div>

</div>
</div>
</section>
@endsection
