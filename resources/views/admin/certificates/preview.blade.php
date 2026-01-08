@extends('layouts.app')

@push('styles')
<style>
    .certificate-hero {
        background: radial-gradient(circle at top, rgba(37, 99, 235, 0.18), rgba(15, 23, 42, 0.9));
        color: #fff;
        border: none;
    }
    .preview-shell {
        border-radius: 1.5rem;
        border: 1px solid rgba(15, 23, 42, 0.08);
        background: #f8fafc;
        padding: 2rem;
        box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
    }
    .preview-stage {
        display: flex;
        justify-content: center;
        overflow: auto;
    }
    .preview-canvas {
        position: relative;
        background: repeating-linear-gradient(0deg, rgba(148, 163, 184, 0.2) 0, rgba(148, 163, 184, 0.2) 1px, transparent 1px, transparent 40px),
            repeating-linear-gradient(90deg, rgba(148, 163, 184, 0.2) 0, rgba(148, 163, 184, 0.2) 1px, transparent 1px, transparent 40px);
        background-color: #fff;
        border-radius: 1rem;
        border: 1px solid rgba(15, 23, 42, 0.1);
        box-shadow: 0 20px 45px rgba(15, 23, 42, 0.1);
    }
    .preview-element {
        position: absolute;
        border-radius: 0.75rem;
        padding: 0.75rem;
        border: 1px solid rgba(37, 99, 235, 0.12);
        background: rgba(255, 255, 255, 0.9);
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        overflow: hidden;
    }
    .preview-element .label {
        font-weight: 600;
        font-size: 0.85rem;
        color: #0f172a;
    }
    .preview-element .preview-text {
        font-size: 0.95rem;
        color: #1e293b;
        line-height: 1.3;
    }
    .preview-element img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 0.65rem;
    }
    .placeholder-image {
        background-image: linear-gradient(135deg, rgba(148, 163, 184, 0.35) 25%, transparent 25%, transparent 50%, rgba(148, 163, 184, 0.35) 50%, rgba(148, 163, 184, 0.35) 75%, transparent 75%, transparent);
        background-size: 28px 28px;
        color: #334155;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 0.65rem;
    }
    .preview-meta {
        color: #475569;
        font-size: 0.95rem;
    }
</style>
@endpush

@section('content')
@php
    $mmToPx = function (float $value) {
        return $value * 3.7795275591;
    };
    $canvasWidth = $mmToPx($page_dimensions['width_mm'] ?? 210);
    $canvasHeight = $mmToPx($page_dimensions['height_mm'] ?? 297);

    $looksLikeImage = function (?string $key): bool {
        return (bool) ($key && preg_match('/photo|image|signature|logo|seal/i', $key));
    };

    $placeholderText = function (?string $key, ?string $fallback = null): string {
        $map = [
            '/name/i' => 'Jane Student',
            '/date/i' => 'Jan 08, 2026',
            '/course|program/i' => 'Advanced Certificate Program',
            '/issuer|instructor|coach/i' => 'Dr. Alex Mentor',
            '/grade|score/i' => 'Score: 95/100',
        ];
        if ($fallback) {
            return $fallback;
        }
        foreach ($map as $pattern => $value) {
            if ($key && preg_match($pattern, $key)) {
                return $value;
            }
        }
        return 'Dynamic text preview';
    };

    $placeholderImageLabel = function (?string $key): string {
        if (!$key) {
            return 'Variable image';
        }
        $label = preg_replace('/[_-]+/', ' ', $key);
        return ucwords($label ?? 'Variable image');
    };
@endphp
<div class="container py-4">
    <div class="card certificate-hero rounded-4 p-4 p-lg-5 mb-4 shadow-sm">
        <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
            <div>
                <span class="hero-pill mb-3 d-inline-flex align-items-center gap-2">
                    <i class="bi bi-eye"></i> Certificate Preview
                </span>
                <h1 class="h2 fw-semibold mb-2">{{ $certificate->name }}</h1>
                <p class="mb-0 text-white-50">
                    {{ strtoupper($certificate->page_size) }} · {{ ucfirst($certificate->orientation) }} ·
                    {{ $page_dimensions['width_mm'] }}mm × {{ $page_dimensions['height_mm'] }}mm
                </p>
            </div>
            <a href="{{ route('admin.certificates.designer', $certificate) }}" class="btn btn-light text-primary fw-semibold rounded-pill">
                <i class="bi bi-pencil"></i> Back to designer
            </a>
        </div>
    </div>

    <div class="preview-shell">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <div class="preview-meta">
                Showing layout at 100% scale · Canvas width {{ number_format($canvasWidth, 0) }}px
            </div>
            <span class="badge text-bg-light">Preview only</span>
        </div>
        <div class="preview-stage">
            <div class="preview-canvas" style="width: {{ $canvasWidth }}px; height: {{ $canvasHeight }}px;">
                @forelse($elements as $element)
                    @php
                        $left = round($element['x'] ?? 0, 2);
                        $top = round($element['y'] ?? 0, 2);
                        $width = round($element['width'] ?? 240, 2);
                        $height = round($element['height'] ?? 80, 2);
                        $isImage = $element['assetUrl'] || ($element['type'] === 'variable' && $looksLikeImage($element['variable'] ?? null));
                        $textValue = $element['type'] === 'variable'
                            ? $placeholderText($element['variable'] ?? null, $element['content'] ?? null)
                            : ($element['content'] ?? 'Sample text block');
                    @endphp
                    <div class="preview-element" style="left: {{ $left }}px; top: {{ $top }}px; width: {{ $width }}px; height: {{ $height }}px;">
                        <span class="label">{{ $element['label'] ?? 'Element' }}</span>
                        @if($element['assetUrl'])
                            <img src="{{ $element['assetUrl'] }}" alt="{{ $element['label'] ?? 'Image asset' }}">
                        @elseif($isImage)
                            <div class="placeholder-image">{{ $placeholderImageLabel($element['variable'] ?? null) }}</div>
                        @else
                            <div class="preview-text">{{ $textValue }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-card-text fs-3 d-block mb-2"></i>
                        No layout elements defined yet.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
