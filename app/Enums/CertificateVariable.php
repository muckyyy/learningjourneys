<?php

namespace App\Enums;

class CertificateVariable
{
    public const PROFILE_FIRST_NAME = 'profile.first_name';
    public const PROFILE_LAST_NAME = 'profile.last_name';
    public const PROFILE_IMAGE_URL = 'profile.image_url';
    public const PROFILE_FULL_NAME = 'profile.full_name';
    public const COLLECTION_NAME = 'collection.name';
    public const JOURNEY_COUNT = 'journey.count';
    public const QR_CODE = 'qr.code';
    public const QR_IMAGE = 'qr.image';

    public static function all(): array
    {
        return [
            self::PROFILE_FIRST_NAME,
            self::PROFILE_LAST_NAME,
            self::PROFILE_IMAGE_URL,
            self::PROFILE_FULL_NAME,
            self::COLLECTION_NAME,
            self::JOURNEY_COUNT,
            self::QR_CODE,
            self::QR_IMAGE,
        ];
    }

    public static function label(string $variable): string
    {
        return match ($variable) {
            self::PROFILE_FIRST_NAME => 'Profile First Name',
            self::PROFILE_LAST_NAME => 'Profile Last Name',
            self::PROFILE_IMAGE_URL => 'Profile Image URL',
            self::PROFILE_FULL_NAME => 'Profile Full Name',
            self::COLLECTION_NAME => 'Collection Name',
            self::JOURNEY_COUNT => 'Journeys Count',
            self::QR_CODE => 'QR Code Data',
            self::QR_IMAGE => 'QR Code Image Path',
            default => 'Unknown Variable',
        };
    }
}
