<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyDebug extends Model
{
    use HasFactory;

    protected $table = 'journey_debug';

    protected $fillable = [
        'journey_step_response_id',
        'journey_step_id',
        'user_id',
        'debug_type',
        'prompt_sent',
        'ai_response_received',
        'request_data',
        'request_tokens',
        'response_tokens',
        'total_tokens',
        'cost',
        'ai_model',
        'processing_time',
        'status',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'request_data' => 'array',
        'metadata' => 'array',
        'cost' => 'decimal:6',
        'processing_time' => 'float',
    ];

    /**
     * Get the journey step response this debug entry belongs to.
     */
    public function journeyStepResponse()
    {
        return $this->belongsTo(JourneyStepResponse::class);
    }

    /**
     * Get the journey step this debug entry is for.
     */
    public function journeyStep()
    {
        return $this->belongsTo(JourneyStep::class);
    }

    /**
     * Get the user who triggered this debug entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for AI interaction debug entries.
     */
    public function scopeAiInteractions($query)
    {
        return $query->where('debug_type', 'ai_interaction');
    }

    /**
     * Scope for error debug entries.
     */
    public function scopeErrors($query)
    {
        return $query->where('status', 'error');
    }

    /**
     * Get formatted cost display.
     */
    public function getFormattedCostAttribute()
    {
        return $this->cost ? '$' . number_format($this->cost, 6) : null;
    }

    /**
     * Get total tokens used.
     */
    public function getTotalTokensUsedAttribute()
    {
        return ($this->request_tokens ?? 0) + ($this->response_tokens ?? 0);
    }
}
