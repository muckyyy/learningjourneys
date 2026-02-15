<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Journey extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'short_description',
        'description',
        'master_prompt',
        'report_prompt',
        'content',
        'journey_collection_id',
        'created_by',
        'sort',
        'is_published',
        'difficulty_level',
        'estimated_duration',
        'recordtime',
        'token_cost',
        'metadata',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'token_cost' => 'integer',
        'metadata' => 'array',
        'sort' => 'integer',
    ];
    /**
     * Get the collection this journey belongs to.
     */
    public function collection()
    {
        return $this->belongsTo(JourneyCollection::class, 'journey_collection_id');
    }

    /**
     * Get the user who created this journey.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the journey attempts for this journey.
     */
    public function attempts()
    {
        return $this->hasMany(JourneyAttempt::class);
    }

    /**
     * Get the journey steps.
     */
    public function steps()
    {
        return $this->hasMany(JourneyStep::class)->orderBy('order');
    }

    /**
     * Scope to filter published journeys.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope to filter by difficulty level.
     */
    public function scopeByDifficulty($query, string $difficulty)
    {
        return $query->where('difficulty_level', $difficulty);
    }
}
