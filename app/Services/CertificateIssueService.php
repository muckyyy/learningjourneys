<?php

namespace App\Services;

use App\Enums\CertificateVariable;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\User;
use App\Services\CertificatePdfService;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CertificateIssueService
{
    /**
     * Issue a certificate for a user while enforcing institution restrictions.
     */
    public function issue(
        Certificate $certificate,
        User $recipient,
        array $variableOverrides = [],
        ?int $collectionId = null
    ): CertificateIssue {
        $this->assertCertificateEnabled($certificate);

        $qrCode = $this->generateUniqueQrCode();
        $verificationUrl = $this->buildVerificationUrl($qrCode);

        $variableOverrides['variables'] = array_merge(
            $variableOverrides['variables'] ?? [],
            [
                CertificateVariable::QR_CODE => $qrCode,
                CertificateVariable::QR_IMAGE => $verificationUrl,
            ]
        );

        $issuedAt = now();
        $expiresAt = $certificate->calculateExpiration($issuedAt);
        $payload = $this->buildPayload($certificate, $recipient, $issuedAt, $variableOverrides);

        return DB::transaction(function () use ($certificate, $recipient, $collectionId, $qrCode, $issuedAt, $expiresAt, $payload) {
            return CertificateIssue::create([
                'certificate_id' => $certificate->id,
                'user_id' => $recipient->id,
                'collection_id' => $collectionId,
                'qr_code' => $qrCode,
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'payload' => $payload,
            ]);
        });
    }

    protected function assertCertificateEnabled(Certificate $certificate): void
    {
        if (! $certificate->enabled) {
            throw new RuntimeException('Certificate is disabled.');
        }
    }

    protected function buildPayload(
        Certificate $certificate,
        User $recipient,
        CarbonInterface $issuedAt,
        array $overrides = []
    ): array {
        $baseVariables = [
            CertificateVariable::PROFILE_FULL_NAME => $recipient->name,
            //CertificateVariable::PROFILE_FIRST_NAME => $this->extractFirstName($recipient->name),
            //CertificateVariable::PROFILE_LAST_NAME => $this->extractLastName($recipient->name),
            //CertificateVariable::PROFILE_IMAGE_URL => $recipient->getProfileValue('profile_image') ?: null,
            CertificateVariable::COLLECTION_NAME => null,
            CertificateVariable::JOURNEY_COUNT => 0,
            CertificateVariable::QR_CODE => null,
            CertificateVariable::QR_IMAGE => null,
            //CertificateVariable::CERTIFICATE_ISSUED_AT => $issuedAt->toIso8601String(),
            CertificateVariable::CERTIFICATE_ISSUED_DATE => $issuedAt->format('F j, Y'),
        ];

        $payload = [
            'certificate' => [
                'id' => $certificate->id,
                'name' => $certificate->name,
                'validity_days' => $certificate->validity_days,
            ],
            'user' => [
                'id' => $recipient->id,
                'email' => $recipient->email,
            ],
            'variables' => array_merge($baseVariables, Arr::get($overrides, 'variables', [])),
            'elements' => Arr::get($overrides, 'elements', []),
        ];

        return array_replace_recursive($payload, Arr::except($overrides, ['variables', 'elements']));
    }

    protected function generateUniqueQrCode(int $length = 10): string
    {
        do {
            $code = Str::random($length);
        } while (CertificateIssue::where('qr_code', $code)->exists());

        return $code;
    }

    protected function buildVerificationUrl(string $code): string
    {
        return CertificatePdfService::buildVerificationUrl($code);
    }

    protected function extractFirstName(?string $fullName): ?string
    {
        if (! $fullName) {
            return null;
        }

        return Str::of($fullName)->explode(' ')->first();
    }

    protected function extractLastName(?string $fullName): ?string
    {
        if (! $fullName) {
            return null;
        }

        $parts = Str::of($fullName)->explode(' ');

        return $parts->count() > 1 ? $parts->last() : null;
    }
}
