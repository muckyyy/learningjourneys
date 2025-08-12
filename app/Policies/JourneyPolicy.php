<?php

namespace App\Policies;

use App\Models\Journey;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JourneyPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any journeys.
     */
    public function viewAny(User $user)
    {
        return true; // All authenticated users can view journeys
    }

    /**
     * Determine whether the user can view the journey.
     */
    public function view(User $user, Journey $journey)
    {
        // Published journeys can be viewed by anyone
        if ($journey->is_published) {
            return true;
        }

        // Unpublished journeys can only be viewed by creator, institution users, or admins
        return $user->id === $journey->created_by 
            || $user->role === 'administrator'
            || ($user->role === 'institution' && $journey->collection->institution_id === $user->institution_id);
    }

    /**
     * Determine whether the user can create journeys.
     */
    public function create(User $user)
    {
        return in_array($user->role, ['editor', 'institution', 'administrator']);
    }

    /**
     * Determine whether the user can update the journey.
     */
    public function update(User $user, Journey $journey)
    {
        // Creator can always update
        if ($user->id === $journey->created_by) {
            return true;
        }

        // Administrator can update any journey
        if ($user->role === 'administrator') {
            return true;
        }

        // Institution users can update journeys in their institution
        if ($user->role === 'institution' && $journey->collection->institution_id === $user->institution_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the journey.
     */
    public function delete(User $user, Journey $journey)
    {
        return $this->update($user, $journey);
    }
}
