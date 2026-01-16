<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate Preview</title>
    <style>
        @page {
            margin: 0;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: #fff;
        }
        .canvas-wrapper {
            position: relative;
            margin: 0;
        }
        .certificate-canvas {
            position: relative;
            border: 1px solid rgba(15, 23, 42, 0.15);
            border-radius: 4mm;
            overflow: hidden;
            background: #fff;
        }
        .certificate-canvas::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: linear-gradient(0deg, rgba(148,163,184,0.15) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148,163,184,0.15) 1px, transparent 1px);
            background-size: 10mm 10mm;
            pointer-events: none;
        }
        .element {
            position: absolute;
            border-radius: 2mm;
            border: 0.3mm solid rgba(37, 99, 235, 0.2);
            background: rgba(255,255,255,0.92);
            padding: 2mm;
            box-sizing: border-box;
            overflow: hidden;
        }
        .element-label {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 1mm;
        }
        .element-text {
            font-size: 12px;
            line-height: 1.4;
            color: #1e293b;
        }
        .element-image {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 1mm;
        }
        .placeholder-image {
            width: 100%;
            height: 100%;
            border-radius: 1mm;
            background-image: linear-gradient(135deg, rgba(148,163,184,0.35) 25%, transparent 25%, transparent 50%, rgba(148,163,184,0.35) 50%, rgba(148,163,184,0.35) 75%, transparent 75%, transparent);
            background-size: 8mm 8mm;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #334155;
        }
    </style>
</head>
<body>
@php
    $pxToMm = fn ($value) => round(($value ?? 0) / 3.7795275591, 4);
    $looksLikeImage = fn (?string $key) => (bool) ($key && preg_match('/photo|image|signature|logo|seal/i', $key));
    $placeholderText = function (?string $key, ?string $fallback = null) {
        if ($fallback) {
            return $fallback;
        }
        $map = [
            '/name/i' => 'Jane Student',
            '/date/i' => 'Jan 08, 2026',
            '/course|program/i' => 'Advanced Certificate Program',
            '/issuer|instructor|coach/i' => 'Dr. Alex Mentor',
            '/grade|score/i' => 'Score: 95/100',
        ];
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
    <div class="canvas-wrapper" style="width: {{ $page_dimensions['width_mm'] }}mm;">
        <div class="certificate-canvas" style="width: {{ $page_dimensions['width_mm'] }}mm; height: {{ $page_dimensions['height_mm'] }}mm;">
            @foreach($elements as $element)
                @php
                    $left = $pxToMm($element['x'] ?? 0);
                    $top = $pxToMm($element['y'] ?? 0);
                    $width = $pxToMm($element['width'] ?? 240);
                    $height = $pxToMm($element['height'] ?? 80);
                    $isImage = $element['assetUrl'] || ($element['type'] === 'variable' && $looksLikeImage($element['variable'] ?? null));
                    $textValue = $element['type'] === 'variable'
                        ? $placeholderText($element['variable'] ?? null, $element['content'] ?? null)
                        : ($element['content'] ?? 'Sample text block');
                @endphp
                <div class="element" style="left: {{ $left }}mm; top: {{ $top }}mm; width: {{ $width }}mm; height: {{ $height }}mm;">
                    <div class="element-label">{{ $element['label'] ?? ' ' }}</div>
                    @if($element['assetUrl'])
                        <img src="{{ $element['assetUrl'] }}" class="element-image" alt="{{ $element['label'] ?? 'Image asset' }}">
                    @elseif($isImage)
                        <div class="placeholder-image">{{ $placeholderImageLabel($element['variable'] ?? null) }}</div>
                    @else
                        <div class="element-text">{{ $textValue }}</div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>
