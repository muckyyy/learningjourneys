<?php

namespace App\Http\Controllers;

use App\Models\LegalConsent;
use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegalConsentController extends Controller
{
    /**
     * Show a legal document (public view).
     */
    public function show(string $slug)
    {
        $document = LegalDocument::where('slug', $slug)->firstOrFail();

        return view('legal.show', compact('document'));
    }

    /**
     * Show the consent page with all pending documents.
     * Used after social login and when middleware detects missing consent.
     */
    public function accept()
    {
        $user = Auth::user();
        $pendingDocs = LegalConsent::pendingForUser($user);

        if ($pendingDocs->isEmpty()) {
            return redirect()->intended(config('app.url') . '/home');
        }

        return view('legal.accept', [
            'documents' => $pendingDocs,
        ]);
    }

    /**
     * Record the user's consent to all the pending documents.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $pendingDocs = LegalConsent::pendingForUser($user);

        // Validate that each pending document was explicitly accepted
        $rules = [];
        foreach ($pendingDocs as $doc) {
            $rules["consent_{$doc->id}"] = ['required', 'accepted'];
        }

        $request->validate($rules, [
            '*.accepted' => 'You must accept this document to continue.',
        ]);

        LegalConsent::recordAllRequired(
            $user,
            $request->ip(),
            $request->userAgent()
        );

        return redirect()->intended(config('app.url') . '/home')
            ->with('status', 'Thank you for accepting our legal documents.');
    }
}
