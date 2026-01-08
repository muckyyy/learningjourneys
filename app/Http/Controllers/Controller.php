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

        $user = auth()->user();

        if ($role === UserRole::ADMINISTRATOR) {
            return $user->hasGlobalRole(UserRole::ADMINISTRATOR);
        }

        return $user->hasRole($role);
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

        if ($user->isAdministrator()) {
            return $query;
        }

        if ($user->hasRole(UserRole::INSTITUTION)) {
            if ($user->active_institution_id) {
                return $query->whereHas('collection', function ($q) use ($user) {
                    $q->where('institution_id', $user->active_institution_id);
                });
            }

            return $query->whereNull('id');
        }

        if ($user->hasRole(UserRole::EDITOR)) {
            return $query->whereHas('collection.editors', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }

        // Regular users can only see published content inside their active institution
        if (method_exists($query->getModel(), 'scopePublished')) {
            $query = $query->published();
        }

        if ($user->active_institution_id) {
            return $query->whereHas('collection', function ($q) use ($user) {
                $q->whereNull('institution_id')
                    ->orWhere('institution_id', $user->active_institution_id);
            });
        }

        return $query;
    }
}
