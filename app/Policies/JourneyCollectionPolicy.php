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
        if ($user->role === 'regular') {
            if (is_null($collection->institution_id)) {
                return true;
            }

            return $user->institution_id && (int) $user->institution_id === (int) $collection->institution_id;
        }

        return true; // Elevated roles can view any collection
    }

    /**
     * Determine whether the user can create collections.
     */
    public function create(User $user)
    {
        return in_array($user->role, ['editor', 'institution', 'administrator']);
    }

    /**
     * Determine whether the user can update the collection.
     */
    public function update(User $user, JourneyCollection $collection)
    {
        // Editor can update their own collections
        if ($user->role === 'editor' && $user->id === $collection->editor_id) {
            return true;
        }

        // Institution users can update collections in their institution
        if ($user->role === 'institution' && $user->institution_id === $collection->institution_id) {
            return true;
        }

        // Administrator can update any collection
        if ($user->role === 'administrator') {
            return true;
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
