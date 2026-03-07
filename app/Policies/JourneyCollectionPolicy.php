<?php

namespace App\Policies;

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
        return true;
    }

    /**
     * Determine whether the user can create collections.
     */
    public function create(User $user)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can update the collection.
     */
    public function update(User $user, JourneyCollection $collection)
    {
        return $user->isAdministrator();
    }

    /**
     * Determine whether the user can delete the collection.
     */
    public function delete(User $user, JourneyCollection $collection)
    {
        return $user->isAdministrator();
    }
}
