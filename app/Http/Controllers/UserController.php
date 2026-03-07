<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:administrator');
    }

    public function index()
    {
        $users = User::paginate(15);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $roles = UserRole::all();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $data = $this->validateUser($request);

        $user = null;

        DB::transaction(function () use ($data, &$user) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $this->applyRoleAssignment($user, $data['role'], $data['is_active']);
        });

        return redirect()->route('users.show', $user)
            ->with('success', 'User created successfully!');
    }

    public function show(User $user)
    {
        $user->load([
            'journeyAttempts' => fn ($query) => $query->with('journey')->latest(),
        ]);

        $stats = [
            'total_attempts' => $user->journeyAttempts()->count(),
            'completed_journeys' => $user->journeyAttempts()->where('status', 'completed')->count(),
            'in_progress' => $user->journeyAttempts()->where('status', 'in_progress')->count(),
            'average_score' => $user->journeyAttempts()->where('status', 'completed')->avg('score') ?? 0,
        ];

        return view('users.show', compact('user', 'stats'));
    }

    public function edit(User $user)
    {
        $roles = UserRole::all();
        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateUser($request, $user);

        DB::transaction(function () use ($data, $user) {
            $update = [
                'name' => $data['name'],
                'email' => $data['email'],
            ];

            if (!empty($data['password'])) {
                $update['password'] = Hash::make($data['password']);
            }

            $user->update($update);

            $this->applyRoleAssignment($user, $data['role'], $data['is_active']);
        });

        return redirect()->route('users.show', $user)
            ->with('success', 'User updated successfully!');
    }

    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        if ($user->journeyAttempts()->count() > 0) {
            return redirect()->route('users.index')
                ->with('error', 'Cannot delete user with existing journey attempts.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully!');
    }

    protected function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRule = $user ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed';

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => $passwordRule,
            'role' => ['required', Rule::in(UserRole::all())],
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }

    protected function applyRoleAssignment(User $user, string $role, bool $isActive): void
    {
        $registrar = app(PermissionRegistrar::class);

        if ($role === UserRole::ADMINISTRATOR) {
            $registrar->setPermissionsTeamId(null);
            $user->syncRoles([UserRole::ADMINISTRATOR]);
        } else {
            $registrar->setPermissionsTeamId(null);
            $user->syncRoles([UserRole::REGULAR]);
        }
    }
}
