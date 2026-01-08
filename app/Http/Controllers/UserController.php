<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\User;
use App\Services\MembershipService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private MembershipService $membershipService)
    {
        $this->middleware('auth');
        $this->middleware('role:administrator')->except(['editors', 'storeEditor']);
        $this->middleware('role:institution,administrator')->only(['editors', 'storeEditor']);
    }

    public function index()
    {
        $users = User::with(['institution', 'institutions'])->paginate(15);
        return view('users.index', compact('users'));
    }

    public function create()
    {
        $institutions = Institution::active()->get();
        $roles = UserRole::all();

        return view('users.create', compact('institutions', 'roles'));
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
                'active_institution_id' => null,
            ]);

            $this->applyRoleAssignment($user, $data['role'], $data['institution_id'] ?? null, $data['is_active']);
        });

        return redirect()->route('users.show', $user)
            ->with('success', 'User created successfully!');
    }

    public function show(User $user)
    {
        $user->load([
            'institution',
            'institutions' => fn ($query) => $query->withPivot(['role', 'is_active', 'activated_at', 'deactivated_at']),
            'journeyAttempts' => fn ($query) => $query->with('journey')->latest(),
        ]);

        $stats = [
            'total_attempts' => $user->journeyAttempts()->count(),
            'completed_journeys' => $user->journeyAttempts()->where('status', 'completed')->count(),
            'in_progress' => $user->journeyAttempts()->where('status', 'in_progress')->count(),
            'average_score' => $user->journeyAttempts()->where('status', 'completed')->avg('score') ?? 0,
        ];

        $institutions = Institution::active()->get();

        return view('users.show', compact('user', 'stats', 'institutions'));
    }

    public function edit(User $user)
    {
        $institutions = Institution::active()->get();
        $roles = UserRole::all();

        return view('users.edit', compact('user', 'institutions', 'roles'));
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

            $this->applyRoleAssignment($user, $data['role'], $data['institution_id'] ?? null, $data['is_active']);
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

    public function editors()
    {
        $authUser = Auth::user();

        $query = User::with('institution')
            ->whereHas('memberships', function ($builder) {
                $builder->where('role', UserRole::EDITOR)->where('is_active', true);
            });

        if ($authUser->hasRole(UserRole::INSTITUTION)) {
            $query->whereHas('memberships', function ($builder) use ($authUser) {
                $builder->where('institution_id', $authUser->active_institution_id);
            });
        }

        $editors = $query->paginate(15);
        $institutions = $authUser->isAdministrator()
            ? Institution::active()->get()
            : Institution::where('id', $authUser->active_institution_id)->get();

        return view('users.editors', compact('editors', 'institutions'));
    }

    public function storeEditor(Request $request)
    {
        $authUser = Auth::user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'institution_id' => 'required|exists:institutions,id',
        ]);

        if ($authUser->hasRole(UserRole::INSTITUTION) && (int) $data['institution_id'] !== (int) $authUser->active_institution_id) {
            abort(403, 'You can only create editors for your active institution.');
        }

        $institution = Institution::findOrFail($data['institution_id']);

        $editor = null;

        DB::transaction(function () use ($data, $institution, $authUser, &$editor) {
            $editor = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'active_institution_id' => $institution->id,
            ]);

            $this->membershipService->assign($editor, $institution, UserRole::EDITOR, true, $authUser);
        });

        return redirect()->route('editors.index')
            ->with('success', 'Editor created successfully!');
    }

    protected function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRule = $user ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed';

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'password' => $passwordRule,
            'role' => ['required', Rule::in(UserRole::all())],
            'institution_id' => 'nullable|exists:institutions,id',
            'is_active' => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        if (in_array($data['role'], UserRole::institutionScopedRoles(), true) && empty($data['institution_id'])) {
            throw ValidationException::withMessages([
                'institution_id' => 'Institution is required for this role.',
            ]);
        }

        return $data;
    }

    protected function applyRoleAssignment(User $user, string $role, ?int $institutionId, bool $isActive): void
    {
        if ($role === UserRole::ADMINISTRATOR) {
            $this->membershipService->syncAdministrator($user);
            return;
        }

        if (!$institutionId) {
            throw ValidationException::withMessages([
                'institution_id' => 'Institution is required for this role.',
            ]);
        }

        $institution = Institution::findOrFail($institutionId);

        $this->membershipService->assign(
            $user,
            $institution,
            $role,
            $isActive,
            Auth::user()
        );
    }
}
