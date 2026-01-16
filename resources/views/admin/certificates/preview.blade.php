@extends('layouts.app')

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
                        <span class="label">{{ $element['label'] ?? ' ' }}</span>
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
