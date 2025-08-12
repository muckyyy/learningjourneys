<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class InstitutionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any institutions.
     */
    public function viewAny(User $user)
    {
        return in_array($user->role, ['institution', 'administrator']);
    }

    /**
     * Determine whether the user can view the institution.
     */
    public function view(User $user, Institution $institution)
    {
        // Administrator can view any institution
        if ($user->role === 'administrator') {
            return true;
        }

        // Institution users can only view their own institution
        if ($user->role === 'institution' && $user->institution_id === $institution->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create institutions.
     */
    public function create(User $user)
    {
        return $user->role === 'administrator';
    }

    /**
     * Determine whether the user can update the institution.
     */
    public function update(User $user, Institution $institution)
    {
        // Administrator can update any institution
        if ($user->role === 'administrator') {
            return true;
        }

        // Institution users can update their own institution
        if ($user->role === 'institution' && $user->institution_id === $institution->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the institution.
     */
    public function delete(User $user, Institution $institution)
    {
        return $user->role === 'administrator';
    }
}
