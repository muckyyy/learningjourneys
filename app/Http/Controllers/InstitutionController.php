<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InstitutionController extends Controller
{
    public function __construct(private MembershipService $membershipService)
    {
        $this->middleware('auth');
        $this->middleware('role:institution,administrator');
    }

    /**
     * Display a listing of institutions.
     */
    public function index()
    {
        $user = Auth::user();

        $query = Institution::with(['members', 'journeyCollections']);

        if (!$user->isAdministrator()) {
            $query->whereHas('members', function ($builder) use ($user) {
                $builder->where('user_id', $user->id)->where('is_active', true);
            });
        }

        $institutions = $query->paginate(10);

        return view('institutions.index', compact('institutions'));
    }

    /**
     * Show the form for creating a new institution.
     */
    public function create()
    {
        $this->authorize('create', Institution::class);
        return view('institutions.create');
    }

    /**
     * Store a newly created institution in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Institution::class);

        $request->validate([
            'name' => 'required|string|max:255|unique:institutions',
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'contact_email' => 'required|email|unique:institutions',
            'contact_phone' => 'nullable|string',
            'website' => 'nullable|url',
            'is_active' => 'boolean',
        ]);

        $institution = Institution::create([
            'name' => $request->name,
            'description' => $request->description,
            'address' => $request->address,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'website' => $request->website,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('institutions.show', $institution)
            ->with('success', 'Institution created successfully!');
    }

    /**
     * Display the specified institution.
     */
    public function show(Institution $institution)
    {
        $user = Auth::user();

        if (!$user->isAdministrator() && !$user->hasMembership($institution->id)) {
            abort(403);
        }

        $institution->load([
            'members' => fn ($query) => $query->withPivot(['role', 'is_active', 'activated_at', 'deactivated_at']),
            'journeyCollections' => fn ($query) => $query->with(['editors:id,name']),
        ]);

        // Get statistics
        $stats = [
            'total_users' => $institution->members()->count(),
            'active_users' => $institution->members()->wherePivot('is_active', true)->count(),
            'editors' => $institution->members()->wherePivot('role', UserRole::EDITOR)->count(),
            'collections' => $institution->journeyCollections()->count(),
            'active_collections' => $institution->journeyCollections()->where('is_active', true)->count(),
        ];

        $availableRoles = UserRole::institutionScopedRoles();

        return view('institutions.show', compact('institution', 'stats', 'availableRoles'));
    }

    /**
     * Show the form for editing the specified institution.
     */
    public function edit(Institution $institution)
    {
        $this->authorize('update', $institution);
        return view('institutions.edit', compact('institution'));
    }

    /**
     * Update the specified institution in storage.
     */
    public function update(Request $request, Institution $institution)
    {
        $this->authorize('update', $institution);

        $request->validate([
            'name' => 'required|string|max:255|unique:institutions,name,' . $institution->id,
            'description' => 'nullable|string',
            'address' => 'nullable|string',
            'contact_email' => 'required|email|unique:institutions,contact_email,' . $institution->id,
            'contact_phone' => 'nullable|string',
            'website' => 'nullable|url',
            'is_active' => 'boolean',
        ]);

        $institution->update([
            'name' => $request->name,
            'description' => $request->description,
            'address' => $request->address,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'website' => $request->website,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('institutions.show', $institution)
            ->with('success', 'Institution updated successfully!');
    }

    /**
     * Remove the specified institution from storage.
     */
    public function destroy(Institution $institution)
    {
        $this->authorize('delete', $institution);

        // Check if institution has users or collections
        if ($institution->members()->count() > 0) {
            return redirect()->route('institutions.index')
                ->with('error', 'Cannot delete institution with existing users.');
        }

        if ($institution->journeyCollections()->count() > 0) {
            return redirect()->route('institutions.index')
                ->with('error', 'Cannot delete institution with existing journey collections.');
        }

        $institution->delete();

        return redirect()->route('institutions.index')
            ->with('success', 'Institution deleted successfully!');
    }

    public function addMember(Request $request, Institution $institution)
    {
        $this->authorize('update', $institution);

        $data = $request->validate([
            'email' => 'required|email',
            'role' => ['required', Rule::in(UserRole::institutionScopedRoles())],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            return back()->withErrors(['email' => 'User not found.'])->withInput();
        }

        $this->membershipService->assign($user, $institution, $data['role'], true, Auth::user());

        return back()->with('success', 'User added to institution.');
    }

    public function updateMember(Request $request, Institution $institution, User $user)
    {
        $this->authorize('update', $institution);

        $data = $request->validate([
            'role' => ['required', Rule::in(UserRole::institutionScopedRoles())],
            'is_active' => 'boolean',
        ]);

        $this->membershipService->assign($user, $institution, $data['role'], $request->boolean('is_active', true), Auth::user());

        return back()->with('success', 'Membership updated.');
    }

    public function removeMember(Institution $institution, User $user)
    {
        $this->authorize('update', $institution);

        if (!$user->hasMembership($institution->id, false)) {
            abort(404);
        }

        $this->membershipService->detach($user, $institution);

        return back()->with('success', 'Member removed.');
    }
}
