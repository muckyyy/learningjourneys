<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\JourneyCollection;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JourneyCollectionPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any collections.
     */
    public function viewAny(User $user)
    {
        return true;
    }

    /**
     * Determine whether the user can view the collection.
     */
    public function view(User $user, JourneyCollection $collection)
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if (is_null($collection->institution_id)) {
            return true;
        }

        return $user->active_institution_id === $collection->institution_id;
    }

    /**
     * Determine whether the user can create collections.
     */
    public function create(User $user)
    {
        return $user->isAdministrator()
            || $user->hasRole(UserRole::INSTITUTION)
            || $user->hasRole(UserRole::EDITOR);
    }

    /**
     * Determine whether the user can update the collection.
     */
    public function update(User $user, JourneyCollection $collection)
    {
        if ($user->isAdministrator()) {
            return true;
        }

        if ($user->hasRole(UserRole::INSTITUTION) && $user->active_institution_id === $collection->institution_id) {
            return true;
        }

        if ($user->hasRole(UserRole::EDITOR)) {
            return $collection->editors()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the collection.
     */
    public function delete(User $user, JourneyCollection $collection)
    {
        return $this->update($user, $collection);
    }
}
