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
use Illuminate\Validation\Rule;

class CertificateDesignerController extends Controller
{
    public function show(Certificate $certificate)
    {
        [$diskName, $assetDisk] = $this->resolveAssetDisk();
        $elements = $this->buildElementPayload($certificate, $assetDisk, $diskName);

        $variableOptions = collect(CertificateVariable::all())->mapWithKeys(function ($variable) {
            return [$variable => CertificateVariable::label($variable)];
        });

        return view('admin.certificates.designer', [
            'certificate' => $certificate,
            'variables' => $variableOptions,
            'elements' => $elements,
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
        $validated = $request->validate([
            'elements' => ['required', 'array'],
            'elements.*.label' => ['required', 'string', 'max:255'],
            'elements.*.type' => ['required', Rule::in(CertificateElementType::all())],
            'elements.*.content' => ['nullable', 'string'],
            'elements.*.variable' => ['nullable', Rule::in(CertificateVariable::all())],
            'elements.*.assetPath' => ['nullable', 'string', 'max:1024'],
            'elements.*.x' => ['required', 'numeric'],
            'elements.*.y' => ['required', 'numeric'],
            'elements.*.width' => ['nullable', 'numeric', 'min:10'],
            'elements.*.height' => ['nullable', 'numeric', 'min:10'],
            'elements.*.sorting' => ['nullable', 'integer'],
        ]);

        DB::transaction(function () use ($certificate, $validated) {
            $certificate->elements()->delete();

            foreach ($validated['elements'] as $index => $element) {
                $certificate->elements()->create([
                    'name' => $element['label'],
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
                    'meta' => null,
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

        $pdf->SetDrawColor(37, 99, 235);
        $pdf->SetLineWidth(0.2);
        $pdf->Rect($x, $y, $width, $height);

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

        $pdf->SetXY($x + 1, $y + 1);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell($width - 2, 4, utf8_decode($element['label'] ?? 'Element'), 0, 2);

        $contentTop = $pdf->GetY();
        $contentHeight = max(($y + $height) - $contentTop - 1, 4);
        $contentWidth = max($width - 2, 2);

        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell($contentWidth, 4.5, utf8_decode($this->resolveElementText($element)), 0, 'L');
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
