<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CertificateElementType;
use App\Enums\CertificateVariable;
use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateElement;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CertificateDesignerController extends Controller
{
    private const DEFAULT_FONT = 'Arial';
    private const CORE_FONTS = ['Arial', 'Courier', 'Times', 'Symbol', 'ZapfDingbats'];
    private const VALID_ALIGNMENTS = ['left', 'center', 'right'];
    private const ALIGNMENT_PDF_MAP = ['left' => 'L', 'center' => 'C', 'right' => 'R'];
    private const TEXT_SETTINGS_DEFAULTS = [
        'color' => '#0f172a',
        'size' => 18,
        'bold' => false,
        'italic' => false,
        'underline' => false,
        'font' => self::DEFAULT_FONT,
        'align' => 'left',
    ];

    private ?array $availableFontsCache = null;
    private array $registeredPdfFonts = [];
    public function show(Certificate $certificate)
    {
        [$diskName, $assetDisk] = $this->resolveAssetDisk();
        $elements = $this->buildElementPayload($certificate, $assetDisk, $diskName);

        $variableOptions = collect(CertificateVariable::all())->mapWithKeys(function ($variable) {
            return [$variable => CertificateVariable::label($variable)];
        });
        $fontOptions = $this->availableFonts();
        $fontList = array_keys($fontOptions);

        return view('admin.certificates.designer', [
            'certificate' => $certificate,
            'variables' => $variableOptions,
            'elements' => $elements,
            'fontOptions' => $fontOptions,
            'fontValues' => $fontList,
            'defaultFont' => $this->defaultFont(),
            'page_dimensions' => [
                'width_mm' => $certificate->page_width_mm,
                'height_mm' => $certificate->page_height_mm,
            ],
        ]);
    }

    public function preview(Certificate $certificate)
    {
        [$diskName, $assetDisk] = $this->resolveAssetDisk();
        $elements = $this->buildElementPayload($certificate, $assetDisk, $diskName);

        $page = $this->resolvePageMetrics($certificate);
        $pdfBinary = $this->renderPdfPreview($page, $elements, $assetDisk, $diskName);

        $filename = sprintf('certificate-preview-%d.pdf', $certificate->id);

        return response($pdfBinary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    public function saveLayout(Request $request, Certificate $certificate)
    {
        $validator = Validator::make($request->all(), [
            'elements' => ['required', 'array'],
            'elements.*.label' => ['nullable', 'string', 'max:255'],
            'elements.*.type' => ['required', Rule::in(CertificateElementType::all())],
            'elements.*.content' => ['nullable', 'string'],
            'elements.*.variable' => ['nullable', Rule::in(CertificateVariable::all())],
            'elements.*.assetPath' => ['nullable', 'string', 'max:1024'],
            'elements.*.x' => ['required', 'numeric'],
            'elements.*.y' => ['required', 'numeric'],
            'elements.*.width' => ['nullable', 'numeric', 'min:10'],
            'elements.*.height' => ['nullable', 'numeric', 'min:10'],
            'elements.*.sorting' => ['nullable', 'integer'],
            'elements.*.textSettings' => ['nullable', 'array'],
            'elements.*.textSettings.color' => ['nullable', 'regex:/^#(?:[0-9a-fA-F]{3}){1,2}$/'],
            'elements.*.textSettings.size' => ['nullable', 'numeric', 'min:6', 'max:120'],
            'elements.*.textSettings.bold' => ['nullable', 'boolean'],
            'elements.*.textSettings.italic' => ['nullable', 'boolean'],
            'elements.*.textSettings.underline' => ['nullable', 'boolean'],
            'elements.*.textSettings.font' => ['nullable', 'string', Rule::in($this->fontValueList())],
            'elements.*.textSettings.align' => ['nullable', 'string', Rule::in(self::VALID_ALIGNMENTS)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Unable to save layout. Please review the validation errors.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($certificate, $validated) {
            $certificate->elements()->delete();

            foreach ($validated['elements'] as $index => $element) {
                $meta = [];
                if ($this->isTextualElementType($element['type'] ?? null)) {
                    $textMeta = $this->prepareTextSettingsMeta(Arr::get($element, 'textSettings'));
                    if ($textMeta) {
                        $meta['text'] = $textMeta;
                    }
                }

                $certificate->elements()->create([
                    'name' => $element['label'] ?? ' ',
                    'type' => $element['type'],
                    'text_content' => Arr::get($element, 'content'),
                    'variable_key' => Arr::get($element, 'variable'),
                    'asset_path' => Arr::get($element, 'assetPath'),
                    'sorting' => $element['sorting'] ?? $index,
                    'position_x' => $element['x'],
                    'position_y' => $element['y'],
                    'width' => Arr::get($element, 'width'),
                    'height' => Arr::get($element, 'height'),
                    'fpdf_settings' => null,
                    'meta' => $meta ?: null,
                ]);
            }
        });

        return response()->json([
            'message' => 'Certificate layout saved successfully.',
        ]);
    }

    public function uploadAsset(Request $request, Certificate $certificate)
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'disk' => config('certificates.asset_disk'),
                'max_upload_kb' => config('certificates.designer.max_upload_size'),
            ]);
        }

        $maxKilobytes = (int) config('certificates.designer.max_upload_size', 5120);
        $allowedMimes = implode(',', config('certificates.designer.allowed_mimes'));

        $data = $request->validate([
            'asset' => ['required', 'file', 'mimes:' . $allowedMimes, 'max:' . $maxKilobytes],
        ]);

        $diskName = config('certificates.asset_disk', config('filesystems.default'));
        $disk = Storage::disk($diskName);
        $prefix = trim(config('certificates.asset_prefix', 'certificateassets'), '/');
        $file = $data['asset'];

        $directory = trim(sprintf('%s/%d', $prefix, $certificate->id), '/');
        if ($directory === '') {
            $directory = (string) $certificate->id;
        }

        $filename = $file->hashName();
        $stored = $disk->putFileAs($directory, $file, $filename);

        if ($stored === false) {
            return response()->json([
                'message' => 'Unable to store asset at this time.',
            ], 500);
        }

        $storedPath = ltrim($directory . '/' . $filename, '/');

        return response()->json([
            'path' => $storedPath,
            'url' => $this->resolveAssetUrl($disk, $diskName, $storedPath),
        ]);
    }

    protected function buildElementPayload(Certificate $certificate, FilesystemAdapter $disk, string $diskName)
    {
        return $certificate->elements()->orderBy('sorting')->get()->map(function (CertificateElement $element) use ($disk, $diskName) {
            return [
                'id' => $element->id,
                'label' => $element->name,
                'type' => $element->type,
                'content' => $element->text_content,
                'variable' => $element->variable_key,
                'assetPath' => $element->asset_path,
                'assetUrl' => $this->resolveAssetUrl($disk, $diskName, $element->asset_path),
                'x' => (float) $element->position_x,
                'y' => (float) $element->position_y,
                'width' => (float) ($element->width ?? 240),
                'height' => (float) ($element->height ?? 80),
                'sorting' => $element->sorting ?? 0,
                'textSettings' => $this->normalizeTextSettings(Arr::get($element->meta ?? [], 'text')),
            ];
        })->values();
    }

    protected function resolveAssetDisk(): array
    {
        $diskName = config('certificates.asset_disk', config('filesystems.default'));
        return [$diskName, Storage::disk($diskName)];
    }

    protected function resolveAssetUrl(FilesystemAdapter $disk, string $diskName, ?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $ttlSeconds = (int) config('certificates.asset_url_ttl', 3600);
        $driver = config("filesystems.disks.{$diskName}.driver");

        if ($driver === 's3' && method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($path, now()->addSeconds(max($ttlSeconds, 60)));
            } catch (\Throwable $exception) {
                // fall through to standard URL generation
            }
        }

        try {
            return $disk->url($path);
        } catch (\Throwable $exception) {
            return $path;
        }
    }

    protected function renderPdfPreview(array $page, $elements, FilesystemAdapter $disk, string $diskName): string
    {
        $this->ensureFontPathDefined();
        $this->registeredPdfFonts = [];
        $orientationFlag = $page['orientation'] === 'landscape' ? 'L' : 'P';
        $pdf = new PreviewFpdf($orientationFlag, 'mm', [$page['width_mm'], $page['height_mm']]);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $cleanup = [];

        try {
            foreach ($elements as $element) {
                $this->drawElementOnPdf($pdf, $element, $disk, $diskName, $cleanup);
            }

            return $pdf->Output('S');
        } finally {
            foreach ($cleanup as $file) {
                if ($file && file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    protected function drawElementOnPdf(\FPDF $pdf, array $element, FilesystemAdapter $disk, string $diskName, array &$cleanup): void
    {
        $x = $this->pxToMm($element['x'] ?? 0);
        $y = $this->pxToMm($element['y'] ?? 0);
        $width = max($this->pxToMm($element['width'] ?? 240), 2);
        $height = max($this->pxToMm($element['height'] ?? 80), 6);
        $isImageElement = $this->isImageElement($element);

        if ($isImageElement) {
            $assetInfo = $this->prepareImageForPdf($disk, $diskName, $element['assetPath'] ?? null);

            if ($assetInfo) {
                $pdf->Image($assetInfo['path'], $x, $y, $width, $height);
                if ($assetInfo['cleanup']) {
                    $cleanup[] = $assetInfo['cleanup'];
                }
            } else {
                $this->drawPlaceholderImage($pdf, $x, $y, $width, $height);
            }

            return;
        }

        $textSettings = $this->normalizeTextSettings($element['textSettings'] ?? null);
        $contentPadding = 2;
        $contentWidth = max($width - ($contentPadding * 2), 2);
        $lineHeight = max(4, $textSettings['size'] * 0.6);

        $pdf->SetXY($x + $contentPadding, $y + $contentPadding);
        $this->applyPdfTextStyles($pdf, $textSettings);
        $pdfAlign = self::ALIGNMENT_PDF_MAP[$textSettings['align']] ?? 'L';
        $pdf->MultiCell($contentWidth, $lineHeight, utf8_decode($this->resolveElementText($element)), 0, $pdfAlign);
    }

    protected function prepareTextSettingsMeta(?array $settings): ?array
    {
        if (!is_array($settings)) {
            return null;
        }

        $meta = [];

        $color = $this->sanitizeHexColor($settings['color'] ?? null);
        if ($color) {
            $meta['color'] = $color;
        }

        if (array_key_exists('size', $settings)) {
            $size = (float) $settings['size'];
            if ($size >= 6 && $size <= 120) {
                $meta['size'] = $size;
            }
        }

        foreach (['bold', 'italic', 'underline'] as $flag) {
            if (array_key_exists($flag, $settings)) {
                $meta[$flag] = (bool) $settings[$flag];
            }
        }

        $font = $this->sanitizeFontSelection($settings['font'] ?? null);
        if ($font) {
            $meta['font'] = $font;
        }

        if (array_key_exists('align', $settings) && in_array($settings['align'], self::VALID_ALIGNMENTS, true)) {
            $meta['align'] = $settings['align'];
        }

        return $meta ?: null;
    }

    protected function normalizeTextSettings($settings): array
    {
        $defaults = self::TEXT_SETTINGS_DEFAULTS;
        $defaults['font'] = $this->defaultFont();

        if (!is_array($settings) || empty($settings)) {
            return $defaults;
        }

        return [
            'color' => $this->sanitizeHexColor($settings['color'] ?? null) ?? $defaults['color'],
            'size' => $this->clamp((float) ($settings['size'] ?? $defaults['size']), 6, 120),
            'bold' => (bool) ($settings['bold'] ?? $defaults['bold']),
            'italic' => (bool) ($settings['italic'] ?? $defaults['italic']),
            'underline' => (bool) ($settings['underline'] ?? $defaults['underline']),
            'font' => $this->sanitizeFontSelection($settings['font'] ?? null) ?? $defaults['font'],
            'align' => in_array($settings['align'] ?? null, self::VALID_ALIGNMENTS, true)
                ? $settings['align']
                : $defaults['align'],
        ];
    }

    protected function sanitizeHexColor(?string $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = strtolower(trim($value));
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $trimmed)) {
            return null;
        }

        if (strlen($trimmed) === 4) {
            $trimmed = sprintf('#%1$s%1$s%2$s%2$s%3$s%3$s', $trimmed[1], $trimmed[2], $trimmed[3]);
        }

        return $trimmed;
    }

    protected function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    protected function ensureFontPathDefined(): void
    {
        if (defined('FPDF_FONTPATH')) {
            return;
        }

        $fontPath = $this->fontStoragePath();

        if (!is_dir($fontPath) && !@mkdir($fontPath, 0775, true) && !is_dir($fontPath)) {
            throw new \RuntimeException("Unable to create certificate font directory at {$fontPath}.");
        }

        define('FPDF_FONTPATH', $fontPath . DIRECTORY_SEPARATOR);
    }

    protected function isTextualElementType(?string $type): bool
    {
        $type = strtolower((string) $type);
        return in_array($type, [CertificateElementType::TEXT, CertificateElementType::VARIABLE], true);
    }

    protected function applyPdfTextStyles(\FPDF $pdf, array $settings): void
    {
        [$r, $g, $b] = $this->hexToRgb($settings['color']);
        $pdf->SetTextColor($r, $g, $b);
        $font = $this->sanitizeFontSelection($settings['font'] ?? null) ?? $this->defaultFont();
        $supportsVariants = $this->fontSupportsVariants($font);
        $style = $this->compileFontStyle($settings, $supportsVariants);
        $this->ensurePdfFontAvailable($pdf, $font);
        $pdf->SetFont($font, $style, $settings['size']);
    }

    protected function compileFontStyle(array $settings, bool $supportsWeightVariants = true): string
    {
        $style = '';
        if ($supportsWeightVariants && !empty($settings['bold'])) {
            $style .= 'B';
        }
        if ($supportsWeightVariants && !empty($settings['italic'])) {
            $style .= 'I';
        }
        if (!empty($settings['underline'])) {
            $style .= 'U';
        }

        return $style ?: '';
    }

    protected function ensurePdfFontAvailable(\FPDF $pdf, string $font): void
    {
        if (in_array($font, self::CORE_FONTS, true)) {
            return;
        }

        if (isset($this->registeredPdfFonts[$font])) {
            return;
        }

        $fontFile = $font . '.php';
        $fontPath = $this->fontStoragePath() . DIRECTORY_SEPARATOR . $fontFile;

        if (!is_file($fontPath)) {
            throw new \RuntimeException("Font definition {$fontFile} not found in configured font path.");
        }

        $pdf->AddFont($font, '', $fontFile);
        $this->registeredPdfFonts[$font] = true;
    }

    protected function fontStoragePath(): string
    {
        $configuredPath = config('certificates.fonts.path', storage_path('app/fonts'));
        return rtrim($configuredPath, DIRECTORY_SEPARATOR);
    }

    protected function availableFonts(): array
    {
        if ($this->availableFontsCache !== null) {
            return $this->availableFontsCache;
        }

        $fonts = [
            'Arial' => 'Arial',
            'Courier' => 'Courier',
            'Times' => 'Times',
        ];

        $fontPath = $this->fontStoragePath();
        if (is_dir($fontPath)) {
            $pattern = $fontPath . DIRECTORY_SEPARATOR . '*.php';
            $files = glob($pattern) ?: [];
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                if (!$name) {
                    continue;
                }
                $fonts[$name] = $this->humanReadableFontLabel($name);
            }
        }

        uasort($fonts, fn($a, $b) => strcasecmp($a, $b));
        return $this->availableFontsCache = $fonts;
    }

    protected function fontValueList(): array
    {
        return array_keys($this->availableFonts());
    }

    protected function defaultFont(): string
    {
        $fonts = $this->fontValueList();
        return $fonts[0] ?? self::DEFAULT_FONT;
    }

    protected function sanitizeFontSelection(?string $font): ?string
    {
        if (!is_string($font) || trim($font) === '') {
            return null;
        }

        $font = trim($font);
        return in_array($font, $this->fontValueList(), true) ? $font : null;
    }

    protected function fontSupportsVariants(string $font): bool
    {
        return in_array($font, self::CORE_FONTS, true);
    }

    protected function humanReadableFontLabel(string $value): string
    {
        $label = str_replace(['_', '-'], ' ', $value);
        $label = preg_replace('/\s+/', ' ', $label) ?: $value;
        return ucwords($label);
    }

    protected function hexToRgb(string $color): array
    {
        $color = ltrim($color, '#');
        if (strlen($color) === 3) {
            $color = sprintf('%1$s%1$s%2$s%2$s%3$s%3$s', $color[0], $color[1], $color[2]);
        }

        $int = hexdec($color);
        return [
            ($int >> 16) & 255,
            ($int >> 8) & 255,
            $int & 255,
        ];
    }

    protected function prepareImageForPdf(FilesystemAdapter $disk, string $diskName, ?string $path): ?array
    {
        if (!$path) {
            return null;
        }

        if (method_exists($disk, 'path')) {
            $localPath = $disk->path($path);
            if (is_string($localPath) && is_file($localPath)) {
                return ['path' => $localPath, 'cleanup' => null];
            }
        }

        try {
            $contents = $disk->get($path);
        } catch (\Throwable $exception) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'certimg_');
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension) {
            $newTmp = $tmp . '.' . $extension;
            @rename($tmp, $newTmp);
            $tmp = $newTmp;
        }

        file_put_contents($tmp, $contents);

        return ['path' => $tmp, 'cleanup' => $tmp];
    }

    protected function drawPlaceholderImage(\FPDF $pdf, float $x, float $y, float $width, float $height): void
    {
        $pdf->SetFillColor(226, 232, 240);
        $pdf->Rect($x, $y, $width, $height, 'F');
        $pdf->SetDrawColor(148, 163, 184);
        $pdf->SetLineWidth(0.1);
        $pdf->Line($x, $y, $x + $width, $y + $height);
        $pdf->Line($x + $width, $y, $x, $y + $height);
        $pdf->SetLineWidth(0.2);
        $pdf->SetDrawColor(37, 99, 235);
    }

    protected function resolveElementText(array $element): string
    {
        $content = $element['content'] ?? null;

        if (($element['type'] ?? null) === 'variable') {
            return $this->placeholderText($element['variable'] ?? null, $content);
        }

        if (is_string($content) && trim($content) !== '') {
            return $content;
        }

        return match ($element['type'] ?? null) {
            'text' => 'Sample text block',
            'image' => 'Image placeholder',
            default => 'Placeholder content',
        };
    }

    protected function looksLikeImage(?string $key): bool
    {
        return (bool) ($key && preg_match('/photo|image|signature|logo|seal/i', $key));
    }

    protected function isImageElement(array $element): bool
    {
        $type = strtolower((string) ($element['type'] ?? ''));
        return $type === 'image' || $this->looksLikeImage($element['variable'] ?? null);
    }

    protected function placeholderText(?string $key, ?string $fallback = null): string
    {
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
    }

    protected function pxToMm(float $pixels): float
    {
        return $pixels / 3.7795275591;
    }

    protected function resolvePageMetrics(Certificate $certificate): array
    {
        $widthMm = (float) ($certificate->page_width_mm ?? 210);
        $heightMm = (float) ($certificate->page_height_mm ?? 297);
        $orientation = strtolower($certificate->orientation ?? 'portrait');

        if ($orientation === 'landscape' && $widthMm < $heightMm) {
            [$widthMm, $heightMm] = [$heightMm, $widthMm];
        } elseif ($orientation === 'portrait' && $heightMm < $widthMm) {
            [$widthMm, $heightMm] = [$heightMm, $widthMm];
        }

        return [
            'width_mm' => $widthMm,
            'height_mm' => $heightMm,
            'orientation' => in_array($orientation, ['landscape', 'portrait'], true) ? $orientation : 'portrait',
        ];
    }

}

class PreviewFpdf extends \FPDF
{
    protected function _dochecks()
    {
        if (ini_get('mbstring.func_overload') & 2) {
            $this->Error('mbstring overloading must be disabled');
        }

        // Runtime magic quotes do not exist on PHP 8+, so skip the parent call.
    }
}
