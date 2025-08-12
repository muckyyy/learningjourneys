<?php

namespace App\Policies;

use App\Models\JourneyAttempt;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class JourneyAttemptPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the attempt.
     */
    public function view(User $user, JourneyAttempt $attempt)
    {
        // User can view their own attempts
        if ($user->id === $attempt->user_id) {
            return true;
        }

        // Journey creator can view attempts on their journeys
        if ($user->id === $attempt->journey->created_by) {
            return true;
        }

        // Institution users can view attempts on journeys from their institution
        if ($user->role === 'institution' && $user->institution_id === $attempt->journey->collection->institution_id) {
            return true;
        }

        // Administrator can view any attempt
        if ($user->role === 'administrator') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can update the attempt.
     */
    public function update(User $user, JourneyAttempt $attempt)
    {
        // Only the user who owns the attempt can update it
        return $user->id === $attempt->user_id;
    }
}
