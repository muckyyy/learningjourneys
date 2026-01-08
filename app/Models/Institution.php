<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'contact_email',
        'contact_phone',
        'address',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function members()
    {
        return $this->belongsToMany(User::class, 'institution_user')
            ->withPivot(['role', 'is_active', 'activated_at', 'deactivated_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function users()
    {
        return $this->members();
    }

    public function activeMembers()
    {
        return $this->members()->wherePivot('is_active', true);
    }

    public function editors()
    {
        return $this->members()
            ->wherePivot('role', UserRole::EDITOR)
            ->wherePivot('is_active', true);
    }

    /**
     * Get the journey collections owned by this institution.
     */
    public function journeyCollections()
    {
        return $this->hasMany(JourneyCollection::class);
    }

    /**
     * Scope to filter active institutions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
