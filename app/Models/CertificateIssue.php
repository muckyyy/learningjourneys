<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'certificate_id',
        'user_id',
        'institution_id',
        'qr_code',
        'qr_image_path',
        'issued_at',
        'expires_at',
        'payload',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'expires_at' => 'datetime',
        'payload' => 'array',
    ];

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
