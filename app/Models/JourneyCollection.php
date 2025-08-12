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
        'editor_id',
        'is_active',
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

    /**
     * Get the editor that manages this collection.
     */
    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
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
