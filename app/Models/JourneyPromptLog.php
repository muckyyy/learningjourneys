<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyPromptLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'journey_attempt_id',
        'journey_step_response_id',
        'action_type',
        'prompt',
        'response',
        'metadata',
        'ai_model',
        'tokens_used',
        'request_tokens',
        'response_tokens',
        'processing_time_ms'
    ];

    protected $casts = [
        'metadata' => 'array',
        'processing_time_ms' => 'decimal:2'
    ];

    /**
     * Get the journey attempt that owns this prompt log.
     */
    public function journeyAttempt()
    {
        return $this->belongsTo(JourneyAttempt::class);
    }

    /**
     * Get the journey step response that owns this prompt log.
     */
    public function journeyStepResponse()
    {
        return $this->belongsTo(JourneyStepResponse::class);
    }
}
