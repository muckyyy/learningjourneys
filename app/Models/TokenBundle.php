<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenBundle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'token_amount',
        'price_cents',
        'currency',
        'expires_after_days',
        'is_active',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function grants()
    {
        return $this->hasMany(UserTokenGrant::class);
    }

    public function purchases()
    {
        return $this->hasMany(TokenPurchase::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
