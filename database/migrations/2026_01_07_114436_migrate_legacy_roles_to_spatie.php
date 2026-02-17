<?php

use App\Enums\UserRole;
use App\Models\Institution;
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
            && Schema::hasColumn('users', 'institution_id')
            && Schema::hasColumn('users', 'is_active');

        $hasLegacyEditorColumn = Schema::hasColumn('journey_collections', 'editor_id');

        $guard = config('auth.defaults.guard', 'web');
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $rolePermissions = [
            UserRole::REGULAR => UserRole::permissions(UserRole::REGULAR),
            UserRole::EDITOR => UserRole::permissions(UserRole::EDITOR),
            UserRole::INSTITUTION => UserRole::permissions(UserRole::INSTITUTION),
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

        // Prepare institution-scoped roles
        foreach (Institution::cursor() as $institution) {
            $registrar->setPermissionsTeamId($institution->id);

            foreach ([UserRole::REGULAR, UserRole::EDITOR, UserRole::INSTITUTION] as $roleName) {
                $role = Role::findOrCreate($roleName, $guard);
                $role->syncPermissions($rolePermissions[$roleName] ?? []);
            }
        }

        // Prepare global administrator role (team-less)
        $registrar->setPermissionsTeamId(null);
        $administratorRole = Role::findOrCreate(UserRole::ADMINISTRATOR, $guard);
        $administratorRole->syncPermissions($rolePermissions[UserRole::ADMINISTRATOR] ?? []);

        $now = now();

        if ($hasLegacyUserColumns) {
            User::chunkById(200, function ($users) use ($registrar, $rolePermissions, $guard, $now) {
                foreach ($users as $user) {
                    if ($user->role === UserRole::ADMINISTRATOR) {
                        $registrar->setPermissionsTeamId(null);
                        $user->syncRoles([UserRole::ADMINISTRATOR]);
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['active_institution_id' => null]);
                        continue;
                    }

                    if (!$user->institution_id) {
                        DB::table('users')
                            ->where('id', $user->id)
                            ->update(['active_institution_id' => null]);
                        continue;
                    }

                    $membershipRole = match ($user->role) {
                        UserRole::EDITOR => UserRole::EDITOR,
                        UserRole::INSTITUTION => UserRole::INSTITUTION,
                        default => UserRole::REGULAR,
                    };

                    $isActive = (bool) $user->is_active;

                    DB::table('institution_user')->updateOrInsert(
                        [
                            'institution_id' => $user->institution_id,
                            'user_id' => $user->id,
                        ],
                        [
                            'role' => $membershipRole,
                            'is_active' => $isActive,
                            'activated_at' => $isActive ? $now : null,
                            'deactivated_at' => $isActive ? null : $now,
                            'assigned_by' => null,
                            'updated_at' => $now,
                            'created_at' => $now,
                        ]
                    );

                    DB::table('users')
                        ->where('id', $user->id)
                        ->update([
                            'active_institution_id' => $isActive ? $user->institution_id : null,
                        ]);

                    $registrar->setPermissionsTeamId($user->institution_id);
                    $user->syncRoles([$membershipRole]);
                }
            });
        }

        // Move legacy editor assignment to pivot table when column exists
        if ($hasLegacyEditorColumn) {
            JourneyCollection::whereNotNull('editor_id')
                ->chunkById(200, function ($collections) use ($now) {
                    foreach ($collections as $collection) {
                        DB::table('collection_user_roles')->updateOrInsert(
                            [
                                'journey_collection_id' => $collection->id,
                                'user_id' => $collection->editor_id,
                                'role' => 'editor',
                            ],
                            [
                                'assigned_by' => $collection->editor_id,
                                'updated_at' => $now,
                                'created_at' => $now,
                            ]
                        );
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
