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

    public static function label(string $role): string
    {
        switch ($role) {
            case self::REGULAR:
                return 'Regular User';
            case self::EDITOR:
                return 'Editor';
            case self::INSTITUTION:
                return 'Institution';
            case self::ADMINISTRATOR:
                return 'Administrator';
            default:
                return 'Unknown';
        }
    }

    public static function permissions(string $role): array
    {
        switch ($role) {
            case self::REGULAR:
                return [
                    'journey.take',
                    'journey.view',
                    'profile.update',
                ];
            case self::EDITOR:
                return [
                    'journey.take',
                    'journey.view',
                    'journey.create',
                    'journey.edit',
                    'journey.delete',
                    'journey_collection.manage',
                    'profile.update',
                ];
            case self::INSTITUTION:
                return [
                    'journey.take',
                    'journey.view',
                    'editor.manage',
                    'journey_collection.create',
                    'journey_collection.edit',
                    'journey_collection.delete',
                    'journey_collection.assign',
                    'profile.update',
                    'reports.view',
                ];
            case self::ADMINISTRATOR:
                return [
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
                ];
            default:
                return [];
        }
    }

    public static function canAccess(string $role, string $permission): bool
    {
        return in_array($permission, self::permissions($role));
    }
}
