<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:administrator')->only('store');
    }

    public function store(Request $request, User $user)
    {
        $actor = $request->user();

        if (!$actor->canImpersonate() || !$user->canBeImpersonated()) {
            abort(403, 'Impersonation not allowed.');
        }

        $actor->impersonate($user);

        return redirect()->route('dashboard')
            ->with('success', 'You are now impersonating ' . $user->name . '.');
    }

    public function destroy(Request $request)
    {
        $request->user()->leaveImpersonation();

        return redirect()->route('dashboard')
            ->with('success', 'Returned to your account.');
    }
}
