<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'journey_id',
        'journey_type',
        'mode',
        'status',
        'started_at',
        'completed_at',
        'current_step',
        'progress_data',
        'score',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress_data' => 'array',
        'score' => 'float',
    ];

    /**
     * Get the user who made this attempt.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the journey being attempted.
     */
    public function journey()
    {
        return $this->belongsTo(Journey::class);
    }

    /**
     * Get the step responses for this attempt.
     */
    public function stepResponses()
    {
        return $this->hasMany(JourneyStepResponse::class);
    }

    /**
     * Get the prompt logs for this attempt.
     */
    public function promptLogs()
    {
        return $this->hasMany(JourneyPromptLog::class);
    }

    /**
     * Check if the attempt is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed' && !is_null($this->completed_at);
    }

    /**
     * Check if the attempt is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Scope to filter completed attempts.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope to filter in-progress attempts.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope to filter preview attempts.
     */
    public function scopePreview($query)
    {
        return $query->where('journey_type', 'preview');
    }

    /**
     * Scope to filter regular attempts.
     */
    public function scopeAttempt($query)
    {
        return $query->where('journey_type', 'attempt');
    }

    /**
     * Check if this is a preview attempt.
     */
    public function isPreview(): bool
    {
        return $this->journey_type === 'preview';
    }

    /**
     * Check if this is a regular attempt.
     */
    public function isAttempt(): bool
    {
        return $this->journey_type === 'attempt';
    }
}
