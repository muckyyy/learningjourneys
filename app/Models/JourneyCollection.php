<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JourneyCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'institution_id',
        'is_active',
        'certificate_prompt',
        'certificate_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the institution that owns this collection.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function certificate()
    {
        return $this->belongsTo(Certificate::class);
    }

    public function editors()
    {
        return $this->belongsToMany(User::class, 'collection_user_roles')
            ->withPivot(['role', 'assigned_by'])
            ->wherePivot('role', 'editor')
            ->withTimestamps();
    }

    public function primaryEditor()
    {
        return $this->editors()->orderBy('collection_user_roles.created_at')->first();
    }

    /**
     * Get the journeys in this collection.
     */
    public function journeys()
    {
        return $this->hasMany(Journey::class);
    }

    /**
     * Scope to filter active collections.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
