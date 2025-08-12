<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:administrator')->except(['editors', 'storeEditor']);
        $this->middleware('role:institution,administrator')->only(['editors', 'storeEditor']);
    }

    /**
     * Display a listing of users.
     */
    public function index()
    {
        $users = User::with('institution')->paginate(15);
        return view('users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $institutions = Institution::where('is_active', true)->get();
        return view('users.create', compact('institutions'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => ['required', Rule::in(['regular', 'editor', 'institution', 'administrator'])],
            'institution_id' => 'nullable|exists:institutions,id',
            'is_active' => 'boolean',
        ]);

        // Validate institution requirement for certain roles
        if (in_array($request->role, ['editor', 'institution']) && !$request->institution_id) {
            return back()->withErrors(['institution_id' => 'Institution is required for this role.']);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'institution_id' => $request->institution_id,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('users.show', $user)
            ->with('success', 'User created successfully!');
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        $user->load(['institution', 'journeyAttempts' => function($query) {
            $query->with('journey')->latest();
        }]);

        // Get user statistics
        $stats = [
            'total_attempts' => $user->journeyAttempts()->count(),
            'completed_journeys' => $user->journeyAttempts()->where('status', 'completed')->count(),
            'in_progress' => $user->journeyAttempts()->where('status', 'in_progress')->count(),
            'average_score' => $user->journeyAttempts()->where('status', 'completed')->avg('score') ?? 0,
        ];

        return view('users.show', compact('user', 'stats'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        $institutions = Institution::where('is_active', true)->get();
        return view('users.edit', compact('user', 'institutions'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => ['required', Rule::in(['regular', 'editor', 'institution', 'administrator'])],
            'institution_id' => 'nullable|exists:institutions,id',
            'is_active' => 'boolean',
        ]);

        // Validate institution requirement for certain roles
        if (in_array($request->role, ['editor', 'institution']) && !$request->institution_id) {
            return back()->withErrors(['institution_id' => 'Institution is required for this role.']);
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'institution_id' => $request->institution_id,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return redirect()->route('users.show', $user)
            ->with('success', 'User updated successfully!');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        // Prevent deleting current user
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Check if user has journey attempts
        if ($user->journeyAttempts()->count() > 0) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete user with existing journey attempts.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully!');
    }

    /**
     * Display a listing of editors.
     */
    public function editors()
    {
        $user = Auth::user();
        $query = User::where('role', 'editor')->with('institution');

        if ($user->role === 'institution') {
            // Institution users can only see editors from their institution
            $query->where('institution_id', $user->institution_id);
        }

        $editors = $query->paginate(15);
        $institutions = $user->role === 'administrator' 
            ? Institution::where('is_active', true)->get()
            : Institution::where('id', $user->institution_id)->get();

        return view('users.editors', compact('editors', 'institutions'));
    }

    /**
     * Store a newly created editor.
     */
    public function storeEditor(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'institution_id' => 'required|exists:institutions,id',
        ]);

        // Validate institution access
        if ($user->role === 'institution' && $request->institution_id != $user->institution_id) {
            abort(403, 'You can only create editors for your institution.');
        }

        $editor = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'editor',
            'institution_id' => $request->institution_id,
            'is_active' => true,
        ]);

        return redirect()->route('editors.index')
            ->with('success', 'Editor created successfully!');
    }
}
