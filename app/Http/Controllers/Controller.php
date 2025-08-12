<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Check if the current user has the required permission.
     */
    protected function checkPermission(string $permission): bool
    {
        if (!auth()->check()) {
            return false;
        }

        return auth()->user()->canPerform($permission);
    }

    /**
     * Abort if user doesn't have the required permission.
     */
    protected function requirePermission(string $permission): void
    {
        if (!$this->checkPermission($permission)) {
            abort(403, 'You do not have permission to perform this action.');
        }
    }

    /**
     * Check if user has a specific role.
     */
    protected function hasRole(string $role): bool
    {
        if (!auth()->check()) {
            return false;
        }

        return auth()->user()->hasRole($role);
    }

    /**
     * Require user to have a specific role.
     */
    protected function requireRole(string $role): void
    {
        if (!$this->hasRole($role)) {
            abort(403, 'You must have ' . UserRole::label($role) . ' role to access this resource.');
        }
    }

    /**
     * Get the current user's institution (if any).
     */
    protected function getCurrentInstitution()
    {
        if (!auth()->check()) {
            return null;
        }

        return auth()->user()->institution;
    }

    /**
     * Filter query based on user's access level.
     */
    protected function filterByUserAccess($query, Request $request = null)
    {
        $user = auth()->user();
        
        if (!$user) {
            return $query->whereNull('id'); // Return empty result
        }

        switch ($user->role) {
            case UserRole::REGULAR:
                // Regular users can only see published content
                if (method_exists($query->getModel(), 'scopePublished')) {
                    $query = $query->published();
                }
                break;

            case UserRole::EDITOR:
                // Editors can see content in their assigned collections
                if ($user->institution_id) {
                    $query = $query->whereHas('collection', function ($q) use ($user) {
                        $q->where('institution_id', $user->institution_id)
                          ->where('editor_id', $user->id);
                    });
                }
                break;

            case UserRole::INSTITUTION:
                // Institution users can see content in their institution
                if ($user->institution_id) {
                    $query = $query->whereHas('collection', function ($q) use ($user) {
                        $q->where('institution_id', $user->institution_id);
                    });
                }
                break;

            case UserRole::ADMINISTRATOR:
                // Administrators can see everything
                break;

            default:
                $query = $query->whereNull('id'); // Return empty result for unknown roles
                break;
        }

        return $query;
    }
}
