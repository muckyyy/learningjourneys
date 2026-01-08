<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!class_exists(Permission::class) || !class_exists(Role::class)) {
            return;
        }

        $permission = Permission::firstOrCreate([
            'name' => 'certificate.manage',
            'guard_name' => 'web',
        ]);

        $adminRole = Role::where('name', UserRole::ADMINISTRATOR)
            ->where('guard_name', $permission->guard_name)
            ->first();

        if ($adminRole && !$adminRole->hasPermissionTo($permission)) {
            $adminRole->givePermissionTo($permission);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!class_exists(Permission::class)) {
            return;
        }

        $permission = Permission::where('name', 'certificate.manage')
            ->where('guard_name', 'web')
            ->first();

        if ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};
