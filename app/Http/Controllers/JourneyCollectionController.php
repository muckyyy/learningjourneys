<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Certificate;
use App\Models\JourneyCollection;
use App\Models\Journey;
use App\Models\User;
use App\Services\PromptBuilderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JourneyCollectionController extends Controller
{
    protected PromptBuilderService $promptBuilderService;

    public function __construct(PromptBuilderService $promptBuilderService)
    {
        $this->middleware('auth');
        $this->middleware('role:administrator')->except(['index', 'show']);
        $this->promptBuilderService = $promptBuilderService;
    }

    public function index()
    {
        $user = Auth::user();
        $query = JourneyCollection::query();

        if ($user->isAdministrator()) {
            $collections = $query->paginate(12);
        } else {
            $collections = $query->active()->paginate(12);
        }

        return view('collections.index', compact('collections'));
    }

    public function create()
    {
        return view('collections.create', [
            'certificates' => $this->availableCertificates(),
            'defaultCertificatePrompt' => $this->promptBuilderService->getDefaultCollectionCertificatePrompt(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'is_active' => 'boolean',
            'certificate_prompt' => 'nullable|string',
            'certificate_id' => 'nullable|exists:certificates,id',
        ]);

        $collection = JourneyCollection::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'is_active' => $request->boolean('is_active', true),
            'certificate_prompt' => $validated['certificate_prompt'] ?? null,
            'certificate_id' => $validated['certificate_id'] ?? null,
        ]);

        return redirect()->route('collections.show', $collection)
            ->with('success', 'Journey collection created successfully!');
    }

    public function show(JourneyCollection $collection)
    {
        $this->authorize('view', $collection);

        $collection->load([
            'journeys' => fn ($query) => $query->orderBy('sort')->with('creator'),
        ]);

        return view('collections.show', compact('collection'));
    }

    /**
     * Reorder journeys within a collection via AJAX.
     */
    public function reorderJourneys(Request $request, JourneyCollection $collection)
    {
        $this->authorize('update', $collection);

        $request->validate([
            'journeys' => 'required|array',
            'journeys.*.id' => 'required|exists:journeys,id',
            'journeys.*.sort' => 'required|integer|min:0',
        ]);

        foreach ($request->journeys as $item) {
            Journey::where('id', $item['id'])
                ->where('journey_collection_id', $collection->id)
                ->update(['sort' => $item['sort']]);
        }

        return response()->json(['success' => true]);
    }

    public function edit(JourneyCollection $collection)
    {
        $this->authorize('update', $collection);

        return view('collections.edit', [
            'collection' => $collection,
            'certificates' => $this->availableCertificates(),
            'defaultCertificatePrompt' => $this->promptBuilderService->getDefaultCollectionCertificatePrompt(),
        ]);
    }

    public function update(Request $request, JourneyCollection $collection)
    {
        $this->authorize('update', $collection);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'is_active' => 'boolean',
            'certificate_prompt' => 'nullable|string',
            'certificate_id' => 'nullable|exists:certificates,id',
        ]);

        $collection->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'is_active' => $request->boolean('is_active'),
            'certificate_prompt' => $validated['certificate_prompt'] ?? null,
            'certificate_id' => $validated['certificate_id'] ?? null,
        ]);

        return redirect()->route('collections.show', $collection)
            ->with('success', 'Journey collection updated successfully!');
    }

    public function destroy(JourneyCollection $collection)
    {
        $this->authorize('delete', $collection);

        if ($collection->journeys()->count() > 0) {
            return redirect()->route('collections.index')
                ->with('error', 'Cannot delete collection with existing journeys.');
        }

        $collection->delete();

        return redirect()->route('collections.index')
            ->with('success', 'Journey collection deleted successfully!');
    }

    private function availableCertificates()
    {
        return Certificate::query()
            ->where('enabled', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
