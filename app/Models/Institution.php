<?php

namespace App\Models;

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

    /**
     * Get the users that belong to this institution.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the editors that belong to this institution.
     */
    public function editors()
    {
        return $this->hasMany(User::class)->withRole('editor');
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
