<?php

namespace App\Enums;

class CertificateElementType
{
    public const TEXT = 'text';
    public const VARIABLE = 'variable';
    public const IMAGE = 'image';

    public static function all(): array
    {
        return [
            self::TEXT,
            self::VARIABLE,
            self::IMAGE,
        ];
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::TEXT => 'Static Text',
            self::VARIABLE => 'Dynamic Variable',
            self::IMAGE => 'Uploaded Image',
            default => 'Unknown',
        };
    }
}
