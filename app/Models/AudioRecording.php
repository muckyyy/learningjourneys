<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AudioRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'journey_attempt_id',
        'journey_step_id',
        'session_id',
        'file_path',
        'transcription',
        'tokens_used',
        'processing_cost',
        'duration_seconds',
        'status',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'processing_cost' => 'decimal:6'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function journeyAttempt()
    {
        return $this->belongsTo(JourneyAttempt::class);
    }

    public function journeyStep()
    {
        return $this->belongsTo(JourneyStep::class);
    }
}
