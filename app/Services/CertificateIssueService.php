<?php

namespace App\Services;

use App\Enums\CertificateVariable;
use App\Models\Certificate;
use App\Models\CertificateIssue;
use App\Models\Institution;
use App\Models\User;
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
        ?Institution $institution = null
    ): CertificateIssue {
        $this->assertCertificateEnabled($certificate);

        $institution = $this->resolveInstitutionContext($certificate, $recipient, $institution);

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
        $payload = $this->buildPayload($certificate, $recipient, $institution, $issuedAt, $variableOverrides);

        return DB::transaction(function () use ($certificate, $recipient, $institution, $qrCode, $issuedAt, $expiresAt, $payload) {
            return CertificateIssue::create([
                'certificate_id' => $certificate->id,
                'user_id' => $recipient->id,
                'institution_id' => $institution?->id,
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

    protected function resolveInstitutionContext(
        Certificate $certificate,
        User $recipient,
        ?Institution $preferredInstitution
    ): ?Institution {
        $targetInstitution = $preferredInstitution;

        if (! $targetInstitution && $recipient->relationLoaded('institution')) {
            $targetInstitution = $recipient->getRelation('institution');
        }

        if (! $targetInstitution && $recipient->active_institution_id) {
            $targetInstitution = Institution::find($recipient->active_institution_id);
        }

        if (! $targetInstitution && $certificate->relationLoaded('institutions')) {
            $targetInstitution = $certificate->institutions->first();
        }

        if (! $targetInstitution) {
            $targetInstitution = $certificate->institutions()->first();
        }

        if (! $targetInstitution) {
            return null;
        }

        $isAuthorized = $certificate->institutions()
            ->whereKey($targetInstitution->getKey())
            ->exists();

        if (! $isAuthorized) {
            throw new RuntimeException('Institution is not authorized to issue this certificate.');
        }

        return $targetInstitution;
    }

    protected function buildPayload(
        Certificate $certificate,
        User $recipient,
        ?Institution $institution,
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
            'institution' => $institution ? [
                'id' => $institution->id,
                'name' => $institution->name,
            ] : null,
            'variables' => array_merge($baseVariables, Arr::get($overrides, 'variables', [])),
            'elements' => Arr::get($overrides, 'elements', []),
        ];

        return array_replace_recursive($payload, Arr::except($overrides, ['variables', 'elements']));
    }

    protected function generateUniqueQrCode(int $length = 24): string
    {
        do {
            $code = Str::random($length);
        } while (CertificateIssue::where('qr_code', $code)->exists());

        return $code;
    }

    protected function buildVerificationUrl(string $code): string
    {
        $base = rtrim(config('app.url'), '/');
        return $base . '/verify?code=' . urlencode($code);
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
