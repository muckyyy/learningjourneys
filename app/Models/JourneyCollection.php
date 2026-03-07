<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'certificate_prompt',
        'certificate_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    /**
     * Get the journeys in this collection.
     */
    public function journeys()
    {
        return $this->hasMany(Journey::class);
    }

    /**
     * Scope to filter active collections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
