<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class UserTokenGrant extends Model
{
    use HasFactory;

    public const SOURCE_PURCHASE = 'purchase';
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_ADJUSTMENT = 'adjustment';
    public const SOURCE_PROMO = 'promo';

    protected $fillable = [
        'user_id',
        'token_bundle_id',
        'token_purchase_id',
        'source',
        'tokens_total',
        'tokens_used',
        'tokens_remaining',
        'expires_at',
        'granted_at',
        'granted_by',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'granted_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected $attributes = [
        'tokens_used' => 0,
        'tokens_remaining' => 0,
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bundle()
    {
        return $this->belongsTo(TokenBundle::class, 'token_bundle_id');
    }

    public function purchase()
    {
        return $this->belongsTo(TokenPurchase::class, 'token_purchase_id');
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function transactions()
    {
        return $this->hasMany(TokenTransaction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('tokens_remaining', '>', 0)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function expiresSoon(int $days = 14): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->lessThanOrEqualTo(Carbon::now()->addDays($days));
    }
}
