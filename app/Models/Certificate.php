<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Certificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'enabled',
        'validity_days',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'validity_days' => 'integer',
    ];

    public function institutions()
    {
        return $this->belongsToMany(Institution::class, 'certificate_institution')
            ->withPivot(['granted_at'])
            ->withTimestamps();
    }

    public function elements()
    {
        return $this->hasMany(CertificateElement::class)->orderBy('sorting');
    }

    public function issues()
    {
        return $this->hasMany(CertificateIssue::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function calculateExpiration(?CarbonInterface $issuedAt = null): ?CarbonInterface
    {
        if (!$this->validity_days) {
            return null;
        }

        $issuedAt = $issuedAt ?: now();

        return $issuedAt->copy()->addDays($this->validity_days);
    }
}
