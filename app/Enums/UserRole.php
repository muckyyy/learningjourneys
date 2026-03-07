<?php

namespace App\Enums;

class UserRole
{
    const REGULAR = 'regular';
    const ADMINISTRATOR = 'administrator';

    public static function all(): array
    {
        return [
            self::REGULAR,
            self::ADMINISTRATOR,
        ];
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::REGULAR => 'Regular User',
            self::ADMINISTRATOR => 'Administrator',
            default => 'Unknown',
        };
    }

    public static function permissionsMap(): array
    {
        return [
            self::REGULAR => [
                'journey.take',
                'journey.view',
                'profile.update',
            ],
            self::ADMINISTRATOR => [
                'journey.take',
                'journey.view',
                'journey.create',
                'journey.edit',
                'journey.delete',
                'journey_collection.create',
                'journey_collection.edit',
                'journey_collection.delete',
                'journey_collection.assign',
                'journey_collection.manage',
                'user.manage',
                'system.manage',
                'reports.view',
                'settings.manage',
                'certificate.manage',
                'profile.update',
            ],
        ];
    }

    public static function permissions(string $role): array
    {
        return self::permissionsMap()[$role] ?? [];
    }

    public static function canAccess(string $role, string $permission): bool
    {
        return in_array($permission, self::permissions($role));
    }
}
