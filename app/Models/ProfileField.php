<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileField extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'input_type',
        'options',
        'required',
        'is_active',
        'sort_order',
        'description'
    ];

    protected $casts = [
        'options' => 'array',
        'required' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function userProfileValues()
    {
        return $this->hasMany(UserProfileValue::class);
    }

    public function getValueForUser($userId)
    {
        $value = $this->userProfileValues()->where('user_id', $userId)->first();
        return $value ? $value->value : null;
    }

    public static function getInputTypes()
    {
        return [
            'text' => 'Text Input',
            'number' => 'Number Input',
            'textarea' => 'Textarea',
            'select' => 'Select (Single)',
            'select_multiple' => 'Select (Multiple)'
        ];
    }

    /**
     * Parse options into key => display pairs.
     * Supports "key|display" format. If no pipe, value is both key and display.
     */
    public function getParsedOptions(): array
    {
        if (!$this->options) {
            return [];
        }

        $parsed = [];
        foreach ($this->options as $option) {
            if (str_contains($option, '|')) {
                [$key, $display] = explode('|', $option, 2);
                $parsed[trim($key)] = trim($display);
            } else {
                $parsed[$option] = $option;
            }
        }

        return $parsed;
    }

    /**
     * Get display label for a stored key value.
     */
    public function getDisplayLabel($key): string
    {
        $parsed = $this->getParsedOptions();
        return $parsed[$key] ?? $key;
    }
}
