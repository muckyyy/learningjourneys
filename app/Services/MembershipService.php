<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class MembershipService
{
    public function __construct(private PermissionRegistrar $registrar)
    {
    }

    public function ensureInstitutionRoles(Institution $institution): void
    {
        $guard = config('auth.defaults.guard', 'web');

        $this->registrar->setPermissionsTeamId($institution->id);

        foreach (UserRole::institutionScopedRoles() as $roleName) {
            $role = Role::findOrCreate($roleName, $guard);
            $role->syncPermissions(UserRole::permissions($roleName));
        }

        $this->restoreTeamContext();
    }

    public function assign(User $user, Institution $institution, string $role, bool $isActive = true, ?User $actor = null): void
    {
        $this->ensureInstitutionRoles($institution);

        $now = now();

        DB::table('institution_user')->updateOrInsert(
            [
                'institution_id' => $institution->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $role,
                'is_active' => $isActive,
                'activated_at' => $isActive ? $now : null,
                'deactivated_at' => $isActive ? null : $now,
                'assigned_by' => $actor?->id,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        if ($isActive && !$user->active_institution_id) {
            $user->update(['active_institution_id' => $institution->id]);
        }

        $this->registrar->setPermissionsTeamId($institution->id);
        $user->syncRoles([$role]);
        $this->restoreTeamContext();
    }

    public function syncAdministrator(User $user): void
    {
        $this->registrar->setPermissionsTeamId(null);
        $user->syncRoles([UserRole::ADMINISTRATOR]);
        $this->restoreTeamContext();
    }

    public function detach(User $user, Institution $institution): void
    {
        DB::table('institution_user')
            ->where('institution_id', $institution->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($user->active_institution_id === $institution->id) {
            $fallbackInstitutionId = $this->resolveFallbackInstitutionId($institution->id);
            $user->update(['active_institution_id' => $fallbackInstitutionId]);
        }
    }

    protected function restoreTeamContext(): void
    {
        $teamId = optional(Auth::user())->active_institution_id;
        $this->registrar->setPermissionsTeamId($teamId);
    }

    protected function resolveFallbackInstitutionId(int $removedInstitutionId): ?int
    {
        $configuredId = $this->configuredDefaultInstitutionId($removedInstitutionId);

        if ($configuredId) {
            return $configuredId;
        }

        return Institution::query()
            ->where('is_active', true)
            ->where('id', '!=', $removedInstitutionId)
            ->orderBy('id')
            ->value('id');
    }

    protected function configuredDefaultInstitutionId(int $removedInstitutionId): ?int
    {
        $configuredId = config('institutions.default_id') ?? config('institution.default_id');

        if (! $configuredId || (int) $configuredId === $removedInstitutionId) {
            return null;
        }

        return Institution::query()
            ->whereKey($configuredId)
            ->where('is_active', true)
            ->value('id');
    }
}
