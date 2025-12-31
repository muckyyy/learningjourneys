<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenPurchase extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'token_bundle_id',
        'payment_provider',
        'provider_reference',
        'status',
        'amount_cents',
        'currency',
        'tokens',
        'metadata',
        'purchased_at',
        'completed_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'purchased_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bundle()
    {
        return $this->belongsTo(TokenBundle::class, 'token_bundle_id');
    }

    public function grants()
    {
        return $this->hasMany(UserTokenGrant::class);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }
}
