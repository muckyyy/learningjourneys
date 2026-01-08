<?php

namespace App\Enums;

class UserRole
{
    const REGULAR = 'regular';
    const EDITOR = 'editor';
    const INSTITUTION = 'institution';
    const ADMINISTRATOR = 'administrator';

    public static function all(): array
    {
        return [
            self::REGULAR,
            self::EDITOR,
            self::INSTITUTION,
            self::ADMINISTRATOR,
        ];
    }

    public static function institutionScopedRoles(): array
    {
        return [self::REGULAR, self::EDITOR, self::INSTITUTION];
    }

    public static function label(string $role): string
    {
        return match ($role) {
            self::REGULAR => 'Regular User',
            self::EDITOR => 'Editor',
            self::INSTITUTION => 'Institution',
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
            self::EDITOR => [
                'journey.take',
                'journey.view',
                'journey.create',
                'journey.edit',
                'journey.delete',
                'journey_collection.manage',
                'profile.update',
            ],
            self::INSTITUTION => [
                'journey.take',
                'journey.view',
                'editor.manage',
                'journey_collection.create',
                'journey_collection.edit',
                'journey_collection.delete',
                'journey_collection.assign',
                'profile.update',
                'reports.view',
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
                'editor.manage',
                'institution.manage',
                'user.manage',
                'system.manage',
                'reports.view',
                'settings.manage',
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
