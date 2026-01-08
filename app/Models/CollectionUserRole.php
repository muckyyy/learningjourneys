<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CollectionUserRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'journey_collection_id',
        'user_id',
        'role',
        'assigned_by',
    ];

    public function collection()
    {
        return $this->belongsTo(JourneyCollection::class, 'journey_collection_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
