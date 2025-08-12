<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfileValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'profile_field_id',
        'value'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function profileField()
    {
        return $this->belongsTo(ProfileField::class);
    }

    public function getFormattedValueAttribute()
    {
        if ($this->profileField->input_type === 'select_multiple') {
            return json_decode($this->value, true) ?: [];
        }
        
        return $this->value;
    }
}
