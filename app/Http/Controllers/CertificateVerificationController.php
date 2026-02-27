<?php

namespace App\Http\Controllers;

use App\Models\CertificateIssue;
use App\Services\CertificatePdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CertificateVerificationController extends Controller
{
    /**
     * Show the verification page.
     * If a QR code is provided, look up and display the certificate.
     * Otherwise, show a search form.
     */
    public function show(?string $qrCode = null)
    {
        if (!$qrCode) {
            return view('certificates.verify', ['issue' => null, 'searched' => false]);
        }

        $issue = CertificateIssue::with(['certificate', 'user', 'collection'])
            ->where('qr_code', $qrCode)
            ->first();

        return view('certificates.verify', [
            'issue'    => $issue,
            'searched' => true,
            'qrCode'   => $qrCode,
        ]);
    }

    /**
     * Handle the form POST when a user manually enters a QR code.
     */
    public function lookup(Request $request)
    {
        $request->validate([
            'qr_code' => 'required|string|max:255',
        ]);

        return redirect()->route('certificates.verify', ['qrCode' => $request->input('qr_code')]);
    }

    /**
     * Download the certificate PDF from S3 for the authenticated user.
     */
    public function download(CertificateIssue $certificateIssue)
    {
        // Ensure the authenticated user owns this certificate
        if ($certificateIssue->user_id !== Auth::id()) {
            abort(403, 'You do not have access to this certificate.');
        }

        $pdfService = app(CertificatePdfService::class);
        $s3Path = $pdfService->buildS3Path($certificateIssue);

        $disk = Storage::disk('s3');
        if (!$disk->exists($s3Path)) {
            abort(404, 'Certificate PDF not found. It may still be generating.');
        }

        $userName = optional($certificateIssue->user)->name ?? 'Certificate';
        $filename = str_replace(' ', '_', $userName) . '_Certificate_' . $certificateIssue->id . '.pdf';

        return $disk->download($s3Path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
