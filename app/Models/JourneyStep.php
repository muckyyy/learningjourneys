<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'journey_id',
        'title',
        'content',
        'type',
        'order',
        'ratepass',
        'maxattempts',
        'time_limit',
        'config',
        'configuration', // Add support for both field names
        'is_required',
        'expected_output',
        'rating_prompt',
    ];

    protected $casts = [
        'config' => 'array',
        'is_required' => 'boolean',
        'order' => 'integer',
        'ratepass' => 'integer',
        'maxattempts' => 'integer',
        'time_limit' => 'integer',
    ];

    /**
     * Get the journey this step belongs to.
     */
    public function journey()
    {
        return $this->belongsTo(Journey::class);
    }

    /**
     * Get the responses for this step.
     */
    public function responses()
    {
        return $this->hasMany(JourneyStepResponse::class);
    }

    /**
     * Scope to filter required steps.
     */
    public function scopeRequired($query)
    {
        return $query->where('is_required', true);
    }

    /**
     * Scope to order by step order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
