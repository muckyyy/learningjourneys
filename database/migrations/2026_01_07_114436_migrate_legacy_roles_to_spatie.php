<?php

use App\Enums\UserRole;
use App\Models\JourneyCollection;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $hasLegacyUserColumns = Schema::hasColumn('users', 'role')
            && Schema::hasColumn('users', 'is_active');

        $hasLegacyEditorColumn = Schema::hasColumn('journey_collections', 'editor_id');

        $guard = config('auth.defaults.guard', 'web');
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $rolePermissions = [
            UserRole::REGULAR => UserRole::permissions(UserRole::REGULAR),
            UserRole::ADMINISTRATOR => UserRole::permissions(UserRole::ADMINISTRATOR),
        ];

        $allPermissions = collect($rolePermissions)
            ->flatten()
            ->unique()
            ->values();

        foreach ($allPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        // Create global roles (no teams)
        $registrar->setPermissionsTeamId(null);
        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, $guard);
            $role->syncPermissions($permissions);
        }

        $now = now();

        if ($hasLegacyUserColumns) {
            User::chunkById(200, function ($users) use ($registrar, $guard, $now) {
                foreach ($users as $user) {
                    $registrar->setPermissionsTeamId(null);

                    if ($user->role === 'administrator') {
                        $user->syncRoles([UserRole::ADMINISTRATOR]);
                    } else {
                        $user->syncRoles([UserRole::REGULAR]);
                    }
                }
            });
        }

        $registrar->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left blank. Data cannot be reverted reliably once migrated.
    }
};
