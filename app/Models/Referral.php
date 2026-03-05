<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_id',
        'has_paid',
        'first_payment_at',
        'rewarded',
    ];

    protected $casts = [
        'has_paid' => 'boolean',
        'rewarded' => 'boolean',
        'first_payment_at' => 'datetime',
    ];

    /**
     * The user who shared the referral link.
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /**
     * The user who signed up via the referral link.
     */
    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_id');
    }
}
