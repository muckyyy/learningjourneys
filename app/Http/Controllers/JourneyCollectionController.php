<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\JourneyCollection;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class JourneyCollectionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:editor,institution,administrator')->except(['index', 'show']);
    }

    public function index()
    {
        $user = Auth::user();
        $query = JourneyCollection::with(['institution', 'editors']);

        if ($user->isAdministrator()) {
            $collections = $query->paginate(12);
        } elseif ($user->hasRole(UserRole::INSTITUTION)) {
            $collections = $query
                ->where('institution_id', $user->active_institution_id)
                ->paginate(12);
        } elseif ($user->hasRole(UserRole::EDITOR)) {
            $collections = $query
                ->whereHas('editors', fn ($q) => $q->where('users.id', $user->id))
                ->paginate(12);
        } else {
            $collections = $query->active()->where(function ($builder) use ($user) {
                $builder->whereNull('institution_id');

                if ($user->active_institution_id) {
                    $builder->orWhere('institution_id', $user->active_institution_id);
                }
            })->paginate(12);
        }

        return view('collections.index', compact('collections'));
    }

    public function create()
    {
        $user = Auth::user();
        $institutions = $this->institutionsForUser($user);

        if ($institutions->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('error', 'You do not have any active institution memberships. Contact support for assistance.');
        }

        $editorGroups = $this->editorGroups($institutions->pluck('id')->all());

        return view('collections.create', [
            'institutions' => $institutions,
            'editorGroups' => $editorGroups,
            'selectedEditors' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'institution_id' => 'required|exists:institutions,id',
            'editor_ids' => 'array|min:1',
            'editor_ids.*' => 'integer|exists:users,id',
            'editor_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $this->assertInstitutionAccess($user, (int) $validated['institution_id']);

        $editorIds = $this->resolveEditorIds($request);
        $this->assertEditorMembership($editorIds, (int) $validated['institution_id']);

        $collection = JourneyCollection::create([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'institution_id' => $validated['institution_id'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncEditors($collection, $editorIds, $user->id);

        return redirect()->route('collections.show', $collection)
            ->with('success', 'Journey collection created successfully!');
    }

    public function show(JourneyCollection $collection)
    {
        $this->authorize('view', $collection);

        $collection->load([
            'institution',
            'editors:id,name,email',
            'journeys' => fn ($query) => $query->where('is_published', true)->with('creator'),
        ]);

        return view('collections.show', compact('collection'));
    }

    public function edit(JourneyCollection $collection)
    {
        $this->authorize('update', $collection);

        $user = Auth::user();
        $institutions = $this->institutionsForUser($user);

        if ($institutions->isEmpty()) {
            return redirect()->route('collections.index')
                ->with('error', 'You do not have access to manage institutions.');
        }

        $currentEditors = $collection->editors()->get();
        $editorGroups = $this->editorGroups($institutions->pluck('id')->all());

        return view('collections.edit', [
            'collection' => $collection->load('editors'),
            'institutions' => $institutions,
            'editorGroups' => $editorGroups,
            'selectedEditors' => $currentEditors,
        ]);
    }

    public function update(Request $request, JourneyCollection $collection)
    {
        $this->authorize('update', $collection);

        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'institution_id' => 'required|exists:institutions,id',
            'editor_ids' => 'array|min:1',
            'editor_ids.*' => 'integer|exists:users,id',
            'editor_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $this->assertInstitutionAccess($user, (int) $validated['institution_id']);

        $editorIds = $this->resolveEditorIds($request);
        $this->assertEditorMembership($editorIds, (int) $validated['institution_id']);

        $collection->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
            'institution_id' => $validated['institution_id'],
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->syncEditors($collection, $editorIds, $user->id);

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

        $collection->editors()->detach();
        $collection->delete();

        return redirect()->route('collections.index')
            ->with('success', 'Journey collection deleted successfully!');
    }

    private function institutionsForUser(User $user)
    {
        if ($user->isAdministrator()) {
            return Institution::active()->get();
        }

        $institutionIds = $user->memberships()
            ->where('is_active', true)
            ->pluck('institution_id')
            ->all();

        return Institution::whereIn('id', $institutionIds)->active()->get();
    }

    private function editorGroups(array $institutionIds)
    {
        return Institution::whereIn('id', $institutionIds)
            ->with(['members' => function ($query) {
                $query->wherePivot('role', UserRole::EDITOR)
                    ->wherePivot('is_active', true);
            }])->get();
    }

    private function assertInstitutionAccess(User $user, int $institutionId): void
    {
        if ($user->isAdministrator()) {
            return;
        }

        if (!$user->hasMembership($institutionId)) {
            abort(403, 'You do not have permission to manage this institution.');
        }
    }

    private function resolveEditorIds(Request $request): array
    {
        $editorIds = $request->input('editor_ids', []);

        if (empty($editorIds) && $request->filled('editor_id')) {
            $editorIds = [$request->integer('editor_id')];
        }

        $editorIds = array_values(array_unique(array_filter($editorIds)));

        if (empty($editorIds)) {
            throw ValidationException::withMessages([
                'editor_ids' => 'At least one editor is required.',
            ]);
        }

        return $editorIds;
    }

    private function assertEditorMembership(array $editorIds, int $institutionId): void
    {
        $count = User::whereIn('id', $editorIds)
            ->whereHas('memberships', function ($query) use ($institutionId) {
                $query->where('institution_id', $institutionId)
                    ->where('is_active', true)
                    ->whereIn('role', [UserRole::EDITOR, UserRole::INSTITUTION]);
            })
            ->count();

        if ($count !== count($editorIds)) {
            throw ValidationException::withMessages([
                'editor_ids' => 'All editors must be active members of the selected institution.',
            ]);
        }
    }

    private function syncEditors(JourneyCollection $collection, array $editorIds, ?int $actorId): void
    {
        $payload = collect($editorIds)->mapWithKeys(function ($editorId) use ($actorId) {
            return [
                $editorId => [
                    'role' => 'editor',
                    'assigned_by' => $actorId,
                ],
            ];
        });

        $collection->editors()->sync($payload->all());
    }
}
