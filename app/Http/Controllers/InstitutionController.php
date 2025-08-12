<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InstitutionController extends Controller
{
    public function __construct()
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
        
        if ($user->role === 'administrator') {
            $institutions = Institution::with(['users', 'journeyCollections'])->paginate(10);
        } else {
            // Institution users can only see their own institution
            $institutions = Institution::with(['users', 'journeyCollections'])
                ->where('id', $user->institution_id)
                ->paginate(10);
        }

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
        
        // Check if user can view this institution
        if ($user->role === 'institution' && $user->institution_id !== $institution->id) {
            abort(403);
        }

        $institution->load([
            'users' => function($query) {
                $query->select('id', 'name', 'email', 'role', 'institution_id', 'is_active');
            },
            'journeyCollections' => function($query) {
                $query->with('editor:id,name');
            }
        ]);

        // Get statistics
        $stats = [
            'total_users' => $institution->users()->count(),
            'active_users' => $institution->users()->where('is_active', true)->count(),
            'editors' => $institution->users()->where('role', 'editor')->count(),
            'collections' => $institution->journeyCollections()->count(),
            'active_collections' => $institution->journeyCollections()->where('is_active', true)->count(),
        ];

        return view('institutions.show', compact('institution', 'stats'));
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
        if ($institution->users()->count() > 0) {
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
}
