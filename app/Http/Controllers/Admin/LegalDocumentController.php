<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LegalDocumentController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'verified', 'role:administrator']);
    }

    /**
     * List all legal documents.
     */
    public function index()
    {
        $documents = LegalDocument::orderByDesc('updated_at')->get()->groupBy('type');

        return view('admin.legal.index', [
            'documents' => $documents,
            'types'     => LegalDocument::TYPES,
        ]);
    }

    /**
     * Show form to create a new legal document.
     */
    public function create()
    {
        return view('admin.legal.form', [
            'document' => new LegalDocument(),
            'types'    => LegalDocument::TYPES,
        ]);
    }

    /**
     * Store a new legal document.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type'        => ['required', Rule::in(array_keys(LegalDocument::TYPES))],
            'title'       => ['required', 'string', 'max:255'],
            'body'        => ['required', 'string'],
            'is_required' => ['boolean'],
        ]);

        // Auto-calculate version: next version for this type
        $latestVersion = LegalDocument::ofType($data['type'])->max('version') ?? 0;

        $document = LegalDocument::create([
            'type'        => $data['type'],
            'title'       => $data['title'],
            'slug'        => Str::slug($data['title']) . '-v' . ($latestVersion + 1),
            'body'        => $data['body'],
            'version'     => $latestVersion + 1,
            'is_required' => $data['is_required'] ?? true,
            'is_active'   => false,
        ]);

        return redirect()->route('admin.legal.index')
            ->with('status', '"' . $document->title . '" created as version ' . $document->version . '.');
    }

    /**
     * Show form to edit an existing document.
     */
    public function edit(LegalDocument $legal)
    {
        return view('admin.legal.form', [
            'document' => $legal,
            'types'    => LegalDocument::TYPES,
        ]);
    }

    /**
     * Update an existing document.
     */
    public function update(Request $request, LegalDocument $legal)
    {
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'body'        => ['required', 'string'],
            'is_required' => ['boolean'],
        ]);

        $bodyChanged = $legal->body !== $data['body'];

        // If the body changed, create a NEW version record so existing consents
        // don't carry over — users will be asked to re-consent to the new version.
        if ($bodyChanged) {
            $newVersion = LegalDocument::ofType($legal->type)->max('version') + 1;

            $newDoc = LegalDocument::create([
                'type'        => $legal->type,
                'title'       => $data['title'],
                'slug'        => Str::slug($data['title']) . '-v' . $newVersion,
                'body'        => $data['body'],
                'version'     => $newVersion,
                'is_required' => $data['is_required'] ?? true,
                'is_active'   => false,
            ]);

            return redirect()->route('admin.legal.index')
                ->with('status', '"' . $newDoc->title . '" saved as new version ' . $newDoc->version . '. Please publish it to make it active — users will be asked to re-consent.');
        }

        // Title-only or is_required change — safe to update in place
        $legal->update([
            'title'       => $data['title'],
            'is_required' => $data['is_required'] ?? true,
        ]);

        return redirect()->route('admin.legal.index')
            ->with('status', '"' . $legal->title . '" updated.');
    }

    /**
     * Publish (activate) a document version.
     */
    public function publish(LegalDocument $legal)
    {
        $legal->publish();

        return redirect()->route('admin.legal.index')
            ->with('status', '"' . $legal->title . '" v' . $legal->version . ' is now the active version.');
    }

    /**
     * Delete a document (only drafts).
     */
    public function destroy(LegalDocument $legal)
    {
        if ($legal->is_active) {
            return back()->with('error', 'Cannot delete an active document. Publish a replacement first.');
        }

        $title = $legal->title;
        $legal->delete();

        return redirect()->route('admin.legal.index')
            ->with('status', '"' . $title . '" deleted.');
    }

    /**
     * Show consent records for a specific document.
     */
    public function consents(LegalDocument $legal)
    {
        $consents = $legal->consents()
            ->with('user')
            ->orderByDesc('created_at')
            ->paginate(50);

        return view('admin.legal.consents', [
            'document' => $legal,
            'consents' => $consents,
        ]);
    }
}
