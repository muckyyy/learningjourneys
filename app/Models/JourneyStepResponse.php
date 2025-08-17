<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyStepResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'journey_attempt_id',
        'journey_step_id',
        'step_action',
        'step_rate',
        'user_input',
        'ai_response',
        'interaction_type',
        'ai_metadata',
        'response_data',
        'is_correct',
        'score',
        'submitted_at',
    ];

    protected $casts = [
        'ai_metadata' => 'array',
        'response_data' => 'array',
        'is_correct' => 'boolean',
        'score' => 'float',
        'step_rate' => 'integer',
        'submitted_at' => 'datetime',
    ];

    /**
     * Get the journey attempt this response belongs to.
     */
    public function attempt()
    {
        return $this->belongsTo(JourneyAttempt::class, 'journey_attempt_id');
    }

    /**
     * Get the journey step this response is for.
     */
    public function step()
    {
        return $this->belongsTo(JourneyStep::class, 'journey_step_id');
    }

    /**
     * Get the debug entries for this response.
     */
    public function debugEntries()
    {
        return $this->hasMany(JourneyDebug::class);
    }

    /**
     * Get the prompt logs for this response.
     */
    public function promptLogs()
    {
        return $this->hasMany(JourneyPromptLog::class);
    }

    /**
     * Scope to filter correct responses.
     */
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    /**
     * Scope to filter by interaction type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('interaction_type', $type);
    }

    /**
     * Scope to filter by score range.
     */
    public function scopeByScore($query, float $minScore, float $maxScore = null)
    {
        $query = $query->where('score', '>=', $minScore);
        
        if ($maxScore !== null) {
            $query = $query->where('score', '<=', $maxScore);
        }
        
        return $query;
    }
}
