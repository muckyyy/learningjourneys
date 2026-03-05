<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'legal_document_id',
        'accepted',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted' => 'boolean',
    ];

    /* ── Relationships ─────────────────────────────── */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function legalDocument()
    {
        return $this->belongsTo(LegalDocument::class);
    }

    /* ── Helpers ───────────────────────────────────── */

    /**
     * Record consent for a user on a legal document.
     */
    public static function recordConsent(User $user, LegalDocument $document, ?string $ip = null, ?string $userAgent = null): self
    {
        return static::updateOrCreate(
            [
                'user_id'           => $user->id,
                'legal_document_id' => $document->id,
            ],
            [
                'accepted'   => true,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]
        );
    }

    /**
     * Record consent for a user across all currently required documents.
     */
    public static function recordAllRequired(User $user, ?string $ip = null, ?string $userAgent = null): void
    {
        $documents = LegalDocument::currentRequired();

        foreach ($documents as $document) {
            static::recordConsent($user, $document, $ip, $userAgent);
        }
    }

    /**
     * Check if a user has accepted all currently required legal documents.
     */
    public static function hasAcceptedAllRequired(User $user): bool
    {
        $requiredDocs = LegalDocument::currentRequired();

        if ($requiredDocs->isEmpty()) {
            return true; // No required docs, user is good
        }

        $acceptedDocIds = static::where('user_id', $user->id)
            ->where('accepted', true)
            ->pluck('legal_document_id');

        return $requiredDocs->every(fn ($doc) => $acceptedDocIds->contains($doc->id));
    }

    /**
     * Get the documents a user has NOT yet accepted.
     */
    public static function pendingForUser(User $user): \Illuminate\Database\Eloquent\Collection
    {
        $requiredDocs = LegalDocument::currentRequired();

        $acceptedDocIds = static::where('user_id', $user->id)
            ->where('accepted', true)
            ->pluck('legal_document_id');

        return $requiredDocs->reject(fn ($doc) => $acceptedDocIds->contains($doc->id))->values();
    }
}
