<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenTransaction extends Model
{
    use HasFactory;

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';
    public const TYPE_EXPIRATION = 'expiration';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'user_id',
        'user_token_grant_id',
        'token_purchase_id',
        'journey_id',
        'journey_attempt_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'metadata',
        'occurred_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function grant()
    {
        return $this->belongsTo(UserTokenGrant::class, 'user_token_grant_id');
    }

    public function purchase()
    {
        return $this->belongsTo(TokenPurchase::class, 'token_purchase_id');
    }

    public function journey()
    {
        return $this->belongsTo(Journey::class);
    }

    public function attempt()
    {
        return $this->belongsTo(JourneyAttempt::class, 'journey_attempt_id');
    }
}
