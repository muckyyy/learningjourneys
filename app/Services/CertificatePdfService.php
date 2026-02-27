<?php

namespace App\Services;

use App\Enums\CertificateElementType;
use App\Enums\CertificateVariable;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Data\QRMatrix;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CertificatePdfService
{
    private const DEFAULT_FONT = 'Arial';
    private const CORE_FONTS = ['Arial', 'Courier', 'Times', 'Symbol', 'ZapfDingbats'];
    private const VALID_ALIGNMENTS = ['left', 'center', 'right'];
    private const ALIGNMENT_PDF_MAP = ['left' => 'L', 'center' => 'C', 'right' => 'R'];
    private const TEXT_SETTINGS_DEFAULTS = [
        'color'     => '#0f172a',
        'size'      => 18,
        'bold'      => false,
        'italic'    => false,
        'underline' => false,
        'font'      => self::DEFAULT_FONT,
        'align'     => 'left',
    ];

    private array $registeredPdfFonts = [];

    /**
     * Generate the certificate PDF for a given CertificateIssue, upload it to S3,
     * and return the S3 path.
     */
    public function generateAndUpload(CertificateIssue $issue): string
    {
        $certificate = $issue->certificate;
        $payload     = $issue->payload ?? [];
        $variables   = $payload['variables'] ?? [];

        [$diskName, $assetDisk] = $this->resolveAssetDisk();

        // Build elements from the certificate template
        $elements = $this->buildElementPayload($certificate, $assetDisk, $diskName);

        // Resolve page metrics
        $page = $this->resolvePageMetrics($certificate);

        // Render the PDF binary, substituting real variable values
        // QR code is generated lazily inside drawElementOnPdf using the element's color
        $pdfBinary = $this->renderPdf($page, $elements, $assetDisk, $diskName, $variables, $issue);

        // Determine the S3 storage path
        $s3Path = $this->buildS3Path($issue);

        // Upload to S3
        $this->uploadToS3($s3Path, $pdfBinary);

        Log::info('Certificate PDF generated and uploaded', [
            'certificate_issue_id' => $issue->id,
            's3_path'              => $s3Path,
        ]);

        return $s3Path;
    }

    /**
     * Build the S3 storage path: certificate_issues/{collection_id}/{certificate_issue_id}
     */
    public function buildS3Path(CertificateIssue $issue): string
    {
        $collectionId = $issue->collection_id ?? 0;

        return sprintf('certificate_issues/%s/%s.pdf', $collectionId, $issue->id);
    }

    /**
     * Build the public verification URL for a certificate.
     */
    public static function buildVerificationUrl(string $qrCode): string
    {
        $base = rtrim(config('app.url'), '/');

        return $base . '/certificates/verify/' . urlencode($qrCode);
    }

    // ─── PDF Rendering ───────────────────────────────────────────────

    protected function renderPdf(
        array $page,
        $elements,
        FilesystemAdapter $disk,
        string $diskName,
        array $variables,
        CertificateIssue $issue
    ): string {
        $this->ensureFontPathDefined();
        $this->registeredPdfFonts = [];

        $orientationFlag = $page['orientation'] === 'landscape' ? 'L' : 'P';
        $pdf = new CertificateFpdf($orientationFlag, 'mm', [$page['width_mm'], $page['height_mm']]);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        $pdf->AddPage();

        $cleanup = [];

        try {
            foreach ($elements as $element) {
                $this->drawElementOnPdf($pdf, $element, $disk, $diskName, $cleanup, $variables, $issue);
            }

            // Add AI report on a second page if available
            $this->renderReportPage($pdf, $issue, $page);

            return $pdf->Output('S');
        } finally {
            foreach ($cleanup as $file) {
                if ($file && file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Render the AI report on a new page (portrait A4) if available.
     */
    protected function renderReportPage(\FPDF $pdf, CertificateIssue $issue, array $page): void
    {
        // Reload to get freshest ai_report (it's generated right before PDF)
        $issue->refresh();

        $report = $issue->ai_report;
        if (! $report || trim($report) === '') {
            return;
        }

        // Strip HTML tags, decode entities for clean PDF text
        $report = html_entity_decode(strip_tags($report), ENT_QUOTES, 'UTF-8');

        // Add a portrait A4 page for the report
        $pdf->AddPage('P', [210, 297]);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 20);

        // Title
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetTextColor(15, 23, 42); // slate-900
        $pdf->SetXY(15, 20);
        $pdf->Cell(180, 10, 'Certificate Report', 0, 1, 'C');

        // Divider line
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, 33, 195, 33);

        // Recipient and date info
        $payload = $issue->payload ?? [];
        $userName = $payload['variables']['profile.full_name'] ?? ($issue->user->name ?? '');
        $issuedDate = $payload['variables']['certificate.issued_date'] ?? ($issue->issued_at ? $issue->issued_at->format('F j, Y') : '');

        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(100, 116, 139); // slate-500
        $pdf->SetXY(15, 36);
        $pdf->Cell(180, 5, mb_convert_encoding("Issued to: {$userName}  |  Date: {$issuedDate}", 'ISO-8859-1', 'UTF-8'), 0, 1, 'C');

        // Report body
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(30, 41, 59); // slate-800
        $pdf->SetXY(15, 48);

        $lineHeight = 5.5;
        $cellWidth  = 180;

        // Split report into paragraphs and render each
        $paragraphs = preg_split('/\n{2,}/', trim($report));
        foreach ($paragraphs as $i => $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            // Handle single newlines as line breaks within a paragraph
            $paragraph = str_replace("\n", ' ', $paragraph);

            $pdf->MultiCell($cellWidth, $lineHeight, mb_convert_encoding($paragraph, 'ISO-8859-1', 'UTF-8'), 0, 'L');
            $pdf->Ln(3); // space between paragraphs
        }
    }

    protected function drawElementOnPdf(
        \FPDF $pdf,
        array $element,
        FilesystemAdapter $disk,
        string $diskName,
        array &$cleanup,
        array $variables,
        CertificateIssue $issue
    ): void {
        $x      = $this->pxToMm($element['x'] ?? 0);
        $y      = $this->pxToMm($element['y'] ?? 0);
        $width  = max($this->pxToMm($element['width'] ?? 240), 2);
        $height = max($this->pxToMm($element['height'] ?? 80), 6);

        $isImageElement = $this->isImageElement($element);
        $variableKey    = $element['variable'] ?? null;

        // Handle QR_IMAGE variable → generate QR code image using element's color
        // QR codes must be square; use the smaller dimension
        if ($variableKey === CertificateVariable::QR_IMAGE) {
            $textSettings = $this->normalizeTextSettings($element['textSettings'] ?? null);
            $qrPath = $this->generateQrCodeImage($issue, $textSettings['color']);
            if ($qrPath) {
                $qrSize = min($width, $height);
                $pdf->Image($qrPath, $x, $y, $qrSize, $qrSize);
                $cleanup[] = $qrPath;
            }
            return;
        }

        if ($isImageElement) {
            $assetInfo = $this->prepareImageForPdf($disk, $diskName, $element['assetPath'] ?? null);

            if ($assetInfo) {
                $pdf->Image($assetInfo['path'], $x, $y, $width, $height);
                if ($assetInfo['cleanup']) {
                    $cleanup[] = $assetInfo['cleanup'];
                }
            }

            return;
        }

        // Text / variable element
        $textSettings   = $this->normalizeTextSettings($element['textSettings'] ?? null);
        $contentPadding = 2;
        $contentWidth   = max($width - ($contentPadding * 2), 2);
        $lineHeight     = max(4, $textSettings['size'] * 0.6);

        $pdf->SetXY($x + $contentPadding, $y + $contentPadding);
        $this->applyPdfTextStyles($pdf, $textSettings);

        $pdfAlign = self::ALIGNMENT_PDF_MAP[$textSettings['align']] ?? 'L';
        $text     = $this->resolveElementText($element, $variables);

        $pdf->MultiCell($contentWidth, $lineHeight, mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8'), 0, $pdfAlign);
    }

    // ─── Element / Variable Resolution ───────────────────────────────

    protected function resolveElementText(array $element, array $variables): string
    {
        $type    = $element['type'] ?? null;
        $content = $element['content'] ?? null;

        // For variable elements, look up the actual value from the payload
        if ($type === CertificateElementType::VARIABLE) {
            $variableKey = $element['variable'] ?? null;

            if ($variableKey && array_key_exists($variableKey, $variables)) {
                $value = $variables[$variableKey];
                if (is_string($value) && trim($value) !== '') {
                    return $value;
                }
                if (is_numeric($value)) {
                    return (string) $value;
                }
            }

            // Fallback to content/label
            if (is_string($content) && trim($content) !== '') {
                return $content;
            }

            return $element['label'] ?? 'Variable';
        }

        // Static text
        if (is_string($content) && trim($content) !== '') {
            return $content;
        }

        return $element['label'] ?? 'Text';
    }

    // ─── QR Code Generation ──────────────────────────────────────────

    /**
     * Generate a QR code PNG image using the element's color.
     *
     * @param CertificateIssue $issue
     * @param string $hexColor  Hex color from the element's textSettings (e.g. '#0f172a')
     * @return string|null  Path to the temp PNG file
     */
    protected function generateQrCodeImage(CertificateIssue $issue, string $hexColor = '#000000'): ?string
    {
        $qrCode = $issue->qr_code;
        if (! $qrCode) {
            return null;
        }

        $verificationUrl = self::buildVerificationUrl($qrCode);

        [$r, $g, $b] = $this->hexToRgb($hexColor);

        $options = new QROptions([
            'version'             => QRCode::VERSION_AUTO,
            'eccLevel'            => EccLevel::M,
            'outputType'          => QROutputInterface::GDIMAGE_PNG,
            'scale'               => 10,
            'quietzoneSize'       => 2,
            'imageTransparent'    => false,
            'bgColor'             => [255, 255, 255],
            'moduleValues'        => [
                // dark modules (use element color)
                QRMatrix::M_DARKMODULE     => [$r, $g, $b],
                QRMatrix::M_DATA_DARK      => [$r, $g, $b],
                QRMatrix::M_FINDER_DARK    => [$r, $g, $b],
                QRMatrix::M_FINDER_DOT     => [$r, $g, $b],
                QRMatrix::M_ALIGNMENT_DARK => [$r, $g, $b],
                QRMatrix::M_TIMING_DARK    => [$r, $g, $b],
                QRMatrix::M_FORMAT_DARK    => [$r, $g, $b],
                QRMatrix::M_VERSION_DARK   => [$r, $g, $b],
                QRMatrix::M_LOGO_DARK      => [$r, $g, $b],
                // light modules (white)
                QRMatrix::M_NULL              => [255, 255, 255],
                QRMatrix::M_DARKMODULE_LIGHT  => [255, 255, 255],
                QRMatrix::M_DATA              => [255, 255, 255],
                QRMatrix::M_FINDER            => [255, 255, 255],
                QRMatrix::M_FINDER_DOT_LIGHT  => [255, 255, 255],
                QRMatrix::M_SEPARATOR         => [255, 255, 255],
                QRMatrix::M_ALIGNMENT         => [255, 255, 255],
                QRMatrix::M_TIMING            => [255, 255, 255],
                QRMatrix::M_FORMAT            => [255, 255, 255],
                QRMatrix::M_VERSION           => [255, 255, 255],
                QRMatrix::M_QUIETZONE         => [255, 255, 255],
                QRMatrix::M_LOGO              => [255, 255, 255],
            ],
        ]);

        $dataUri = (new QRCode($options))->render($verificationUrl);

        // render() returns a data URI like "data:image/png;base64,..."
        $base64 = preg_replace('#^data:image/png;base64,#', '', $dataUri);
        $pngData = base64_decode($base64);

        $tmp = tempnam(sys_get_temp_dir(), 'certqr_') . '.png';
        file_put_contents($tmp, $pngData);

        return $tmp;
    }

    // ─── S3 Upload ───────────────────────────────────────────────────

    protected function uploadToS3(string $path, string $pdfBinary): void
    {
        $disk = Storage::disk('s3');
        $disk->put($path, $pdfBinary, 'private');
    }

    // ─── Shared Helpers (mirrored from CertificateDesignerController) ─

    protected function buildElementPayload(Certificate $certificate, FilesystemAdapter $disk, string $diskName)
    {
        return $certificate->elements()->orderBy('sorting')->get()->map(function ($element) use ($disk, $diskName) {
            return [
                'id'           => $element->id,
                'label'        => $element->name,
                'type'         => $element->type,
                'content'      => $element->text_content,
                'variable'     => $element->variable_key,
                'assetPath'    => $element->asset_path,
                'assetUrl'     => null, // not needed for PDF generation
                'x'            => (float) $element->position_x,
                'y'            => (float) $element->position_y,
                'width'        => (float) ($element->width ?? 240),
                'height'       => (float) ($element->height ?? 80),
                'sorting'      => $element->sorting ?? 0,
                'textSettings' => $this->normalizeTextSettings(Arr::get($element->meta ?? [], 'text')),
            ];
        })->values()->toArray();
    }

    protected function resolveAssetDisk(): array
    {
        $diskName = config('certificates.asset_disk', config('filesystems.default'));

        return [$diskName, Storage::disk($diskName)];
    }

    protected function resolvePageMetrics(Certificate $certificate): array
    {
        $widthMm     = (float) ($certificate->page_width_mm ?? 210);
        $heightMm    = (float) ($certificate->page_height_mm ?? 297);
        $orientation = strtolower($certificate->orientation ?? 'portrait');

        if ($orientation === 'landscape' && $widthMm < $heightMm) {
            [$widthMm, $heightMm] = [$heightMm, $widthMm];
        } elseif ($orientation === 'portrait' && $heightMm < $widthMm) {
            [$widthMm, $heightMm] = [$heightMm, $widthMm];
        }

        return [
            'width_mm'    => $widthMm,
            'height_mm'   => $heightMm,
            'orientation' => in_array($orientation, ['landscape', 'portrait'], true) ? $orientation : 'portrait',
        ];
    }

    protected function normalizeTextSettings($settings): array
    {
        $defaults = self::TEXT_SETTINGS_DEFAULTS;

        if (! is_array($settings) || empty($settings)) {
            return $defaults;
        }

        return [
            'color'     => $this->sanitizeHexColor($settings['color'] ?? null) ?? $defaults['color'],
            'size'      => $this->clamp((float) ($settings['size'] ?? $defaults['size']), 6, 120),
            'bold'      => (bool) ($settings['bold'] ?? $defaults['bold']),
            'italic'    => (bool) ($settings['italic'] ?? $defaults['italic']),
            'underline' => (bool) ($settings['underline'] ?? $defaults['underline']),
            'font'      => $this->sanitizeFontSelection($settings['font'] ?? null) ?? $defaults['font'],
            'align'     => in_array($settings['align'] ?? null, self::VALID_ALIGNMENTS, true)
                ? $settings['align']
                : $defaults['align'],
        ];
    }

    protected function applyPdfTextStyles(\FPDF $pdf, array $settings): void
    {
        [$r, $g, $b] = $this->hexToRgb($settings['color']);
        $pdf->SetTextColor($r, $g, $b);

        $font             = $this->sanitizeFontSelection($settings['font'] ?? null) ?? self::DEFAULT_FONT;
        $supportsVariants = in_array($font, self::CORE_FONTS, true);
        $style            = $this->compileFontStyle($settings, $supportsVariants);

        $this->ensurePdfFontAvailable($pdf, $font);
        $pdf->SetFont($font, $style, $settings['size']);
    }

    protected function compileFontStyle(array $settings, bool $supportsWeightVariants = true): string
    {
        $style = '';
        if ($supportsWeightVariants && ! empty($settings['bold'])) {
            $style .= 'B';
        }
        if ($supportsWeightVariants && ! empty($settings['italic'])) {
            $style .= 'I';
        }
        if (! empty($settings['underline'])) {
            $style .= 'U';
        }

        return $style;
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

        if (! is_file($fontPath)) {
            Log::warning("Font definition {$fontFile} not found, falling back to Arial.");
            return;
        }

        $pdf->AddFont($font, '', $fontFile);
        $this->registeredPdfFonts[$font] = true;
    }

    protected function ensureFontPathDefined(): void
    {
        if (defined('FPDF_FONTPATH')) {
            return;
        }

        $fontPath = $this->fontStoragePath();

        if (! is_dir($fontPath) && ! @mkdir($fontPath, 0775, true) && ! is_dir($fontPath)) {
            throw new \RuntimeException("Unable to create certificate font directory at {$fontPath}.");
        }

        define('FPDF_FONTPATH', $fontPath . DIRECTORY_SEPARATOR);
    }

    protected function fontStoragePath(): string
    {
        $configuredPath = config('certificates.fonts.path', storage_path('app/fonts'));

        return rtrim($configuredPath, DIRECTORY_SEPARATOR);
    }

    protected function sanitizeFontSelection(?string $font): ?string
    {
        if (! is_string($font) || trim($font) === '') {
            return null;
        }

        $font = trim($font);

        // Accept core fonts directly
        if (in_array($font, self::CORE_FONTS, true)) {
            return $font;
        }

        // Accept custom fonts that have a definition file
        $fontFile = $this->fontStoragePath() . DIRECTORY_SEPARATOR . $font . '.php';

        return is_file($fontFile) ? $font : null;
    }

    protected function prepareImageForPdf(FilesystemAdapter $disk, string $diskName, ?string $path): ?array
    {
        if (! $path) {
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
        } catch (\Throwable $e) {
            Log::warning('Failed to fetch certificate image from disk', ['path' => $path, 'error' => $e->getMessage()]);
            return null;
        }

        $tmp       = tempnam(sys_get_temp_dir(), 'certimg_');
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension) {
            $newTmp = $tmp . '.' . $extension;
            @rename($tmp, $newTmp);
            $tmp = $newTmp;
        }

        file_put_contents($tmp, $contents);

        return ['path' => $tmp, 'cleanup' => $tmp];
    }

    protected function isImageElement(array $element): bool
    {
        $type = strtolower((string) ($element['type'] ?? ''));

        return $type === 'image' || $this->looksLikeImage($element['variable'] ?? null);
    }

    protected function looksLikeImage(?string $key): bool
    {
        return (bool) ($key && preg_match('/photo|image|signature|logo|seal/i', $key));
    }

    protected function sanitizeHexColor(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = strtolower(trim($value));
        if (! preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $trimmed)) {
            return null;
        }

        if (strlen($trimmed) === 4) {
            $trimmed = sprintf('#%1$s%1$s%2$s%2$s%3$s%3$s', $trimmed[1], $trimmed[2], $trimmed[3]);
        }

        return $trimmed;
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

    protected function pxToMm(float $pixels): float
    {
        return $pixels / 3.7795275591;
    }

    protected function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }
}

/**
 * Custom FPDF subclass that skips the legacy magic_quotes check.
 */
class CertificateFpdf extends \FPDF
{
    protected function _dochecks()
    {
        if (ini_get('mbstring.func_overload') & 2) {
            $this->Error('mbstring overloading must be disabled');
        }
    }
}
