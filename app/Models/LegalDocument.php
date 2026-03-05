<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LegalDocument extends Model
{
    use HasFactory;

    public const TYPE_TERMS = 'terms_of_service';
    public const TYPE_PRIVACY = 'privacy_policy';
    public const TYPE_COOKIE = 'cookie_policy';

    public const TYPES = [
        self::TYPE_TERMS   => 'Terms of Service',
        self::TYPE_PRIVACY => 'Privacy Policy',
        self::TYPE_COOKIE  => 'Cookie Policy',
    ];

    protected $fillable = [
        'type',
        'title',
        'slug',
        'body',
        'version',
        'is_active',
        'is_required',
        'published_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'is_required'  => 'boolean',
        'version'      => 'integer',
        'published_at' => 'datetime',
    ];

    /* ── Scopes ────────────────────────────────────── */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /* ── Relationships ─────────────────────────────── */

    public function consents()
    {
        return $this->hasMany(LegalConsent::class);
    }

    /* ── Helpers ───────────────────────────────────── */

    /**
     * Get the latest active document of each required type.
     */
    public static function currentRequired(): \Illuminate\Database\Eloquent\Collection
    {
        return static::active()
            ->required()
            ->orderByDesc('version')
            ->get()
            ->unique('type');
    }

    /**
     * Get the latest active version of a specific type.
     */
    public static function currentOfType(string $type): ?self
    {
        return static::active()
            ->ofType($type)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Get the type label.
     */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /**
     * Auto-generate slug from title when creating.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $doc) {
            if (empty($doc->slug)) {
                $doc->slug = Str::slug($doc->title) . '-v' . ($doc->version ?? 1);
            }
        });
    }

    /**
     * Publish this document (activate it and deactivate previous versions of same type).
     */
    public function publish(): void
    {
        // Deactivate all other versions of the same type
        static::where('type', $this->type)
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        $this->update([
            'is_active'    => true,
            'published_at' => $this->published_at ?? now(),
        ]);
    }
}
