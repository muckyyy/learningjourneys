@extends('layouts.app')

@section('content')
<section class="shell">
<div class="row justify-content-center">
<div class="col-lg-8">

    <div class="glass-card">
        <div class="card-body p-4 p-lg-5">
            <h2 class="fw-bold mb-2">{{ $document->title }}</h2>
            <p class="text-muted mb-4">
                Version {{ $document->version }}
                @if ($document->published_at)
                    — Published {{ $document->published_at->format('F j, Y') }}
                @endif
            </p>

            <hr class="mb-4">

            <div class="legal-content">
                {!! $document->body !!}
            </div>
        </div>
    </div>

</div>
</div>
</section>
@endsection

@push('styles')
<style>
    .legal-content h1, .legal-content h2, .legal-content h3 {
        margin-top: 1.5rem;
        margin-bottom: 0.75rem;
    }
    .legal-content p {
        margin-bottom: 1rem;
        line-height: 1.7;
    }
    .legal-content ul, .legal-content ol {
        margin-bottom: 1rem;
        padding-left: 1.5rem;
    }
</style>
@endpush
