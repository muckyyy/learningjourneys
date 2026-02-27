<?php

namespace App\Http\Controllers;

use App\Models\CertificateIssue;
use Illuminate\Http\Request;

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
}
