<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'institution_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Get the institution that owns the user.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    /**
     * Get the journey collections managed by this user (for editors).
     */
    public function managedCollections()
    {
        return $this->hasMany(JourneyCollection::class, 'editor_id');
    }

    /**
     * Alias for managedCollections - for compatibility with views.
     */
    public function journeyCollections()
    {
        return $this->managedCollections();
    }

    /**
     * Get the journey attempts for this user.
     */
    public function journeyAttempts()
    {
        return $this->hasMany(JourneyAttempt::class);
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    /**
     * Check if user can perform a specific action.
     */
    public function canPerform(string $permission): bool
    {
        return UserRole::canAccess($this->role, $permission);
    }

    /**
     * Get user's role label.
     */
    public function getRoleLabelAttribute(): string
    {
        return UserRole::label($this->role);
    }

    /**
     * Scope to filter users by role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope to filter active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the profile values for this user.
     */
    public function profileValues()
    {
        return $this->hasMany(UserProfileValue::class);
    }

    /**
     * Get a specific profile field value for this user.
     */
    public function getProfileValue($fieldShortName)
    {
        $profileField = ProfileField::where('short_name', $fieldShortName)->first();
        if (!$profileField) {
            return null;
        }

        $value = $this->profileValues()->where('profile_field_id', $profileField->id)->first();
        return $value ? $value->formatted_value : null;
    }

    /**
     * Set a profile field value for this user.
     */
    public function setProfileValue($fieldShortName, $value)
    {
        $profileField = ProfileField::where('short_name', $fieldShortName)->first();
        if (!$profileField) {
            return false;
        }

        // Handle multiple select values
        if ($profileField->input_type === 'select_multiple' && is_array($value)) {
            $value = json_encode($value);
        }

        $this->profileValues()->updateOrCreate(
            ['profile_field_id' => $profileField->id],
            ['value' => $value]
        );

        return true;
    }

    /**
     * Check if the user has completed all required profile fields.
     */
    public function hasCompletedRequiredProfileFields()
    {
        $requiredFields = ProfileField::where('is_active', true)
            ->where('required', true)
            ->get();

        foreach ($requiredFields as $field) {
            $value = $this->getProfileValue($field->short_name);
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get missing required profile fields.
     */
    public function getMissingRequiredProfileFields()
    {
        $requiredFields = ProfileField::where('is_active', true)
            ->where('required', true)
            ->get();

        $missingFields = [];
        foreach ($requiredFields as $field) {
            $value = $this->getProfileValue($field->short_name);
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                $missingFields[] = $field->name;
            }
        }

        return $missingFields;
    }
}
