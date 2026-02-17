<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, Impersonate;

    protected $guard_name = 'web';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'is_active',
        'email_verified_at',
        'password',
        'active_institution_id',
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
        'active_institution_id' => 'integer',
    ];

    /**
     * Get the user's currently active institution.
     */
    public function institution()
    {
        return $this->belongsTo(Institution::class, 'active_institution_id');
    }

    public function activeInstitution()
    {
        return $this->institution();
    }

    public function institutions()
    {
        return $this->belongsToMany(Institution::class, 'institution_user')
            ->withPivot(['role', 'is_active', 'activated_at', 'deactivated_at', 'assigned_by'])
            ->withTimestamps();
    }

    public function memberships()
    {
        return $this->hasMany(InstitutionUser::class);
    }

    public function activeMembership()
    {
        return $this->hasOne(InstitutionUser::class)
            ->where('institution_id', $this->active_institution_id)
            ->where('is_active', true);
    }

    /**
     * Get the journey collections managed by this user (for editors).
     */
    public function managedCollections()
    {
        return $this->belongsToMany(JourneyCollection::class, 'collection_user_roles')
            ->wherePivot('role', 'editor')
            ->withPivot(['role', 'assigned_by'])
            ->withTimestamps();
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

    public function tokenGrants()
    {
        return $this->hasMany(UserTokenGrant::class);
    }

    public function tokenTransactions()
    {
        return $this->hasMany(TokenTransaction::class);
    }

    public function tokenPurchases()
    {
        return $this->hasMany(TokenPurchase::class);
    }

    /**
     * Check if user can perform a specific action.
     */
    public function canPerform(string $permission): bool
    {
        if ($this->hasGlobalRole(UserRole::ADMINISTRATOR)) {
            return true;
        }

        return $this->can($permission);
    }

    /**
     * Get user's role label.
     */
    public function getRoleLabelAttribute(): string
    {
        return UserRole::label($this->role);
    }

    public function getRoleAttribute(): string
    {
        if ($this->hasGlobalRole(UserRole::ADMINISTRATOR)) {
            return UserRole::ADMINISTRATOR;
        }

        return optional($this->resolveActiveMembership())->role ?? UserRole::REGULAR;
    }

    public function getInstitutionIdAttribute(): ?int
    {
        return $this->active_institution_id;
    }

    public function getIsActiveAttribute(): bool
    {
        if ($this->hasGlobalRole(UserRole::ADMINISTRATOR)) {
            return true;
        }

        return (bool) optional($this->resolveActiveMembership())->is_active;
    }

    /**
     * Scope to filter users by role.
     */
    public function scopeWithRole($query, string $role)
    {
        if ($role === UserRole::ADMINISTRATOR) {
            return $query->whereHas('roles', function ($q) {
                $q->where('name', UserRole::ADMINISTRATOR);
            });
        }

        return $query->whereHas('memberships', function ($q) use ($role) {
            $q->where('role', $role)->where('is_active', true);
        });
    }

    /**
     * Scope to filter active users.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($builder) {
            $builder->whereHas('memberships', function ($q) {
                $q->where('is_active', true);
            })->orWhereHas('roles', function ($q) {
                $q->where('name', UserRole::ADMINISTRATOR);
            });
        });
    }

    public function hasGlobalRole(string $role): bool
    {
        $teamColumn = config('permission.column_names.team_foreign_key');
        $pivotTable = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');

        return $this->roles()
            ->where($rolesTable . '.name', $role)
            ->whereNull($pivotTable . '.' . $teamColumn)
            ->exists();
    }

    public function hasActiveMembership(): bool
    {
        if ($this->hasGlobalRole(UserRole::ADMINISTRATOR)) {
            return true;
        }

        return (bool) $this->resolveActiveMembership();
    }

    public function hasMembership(int $institutionId, bool $onlyActive = true): bool
    {
        return $this->memberships()
            ->where('institution_id', $institutionId)
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    public function switchActiveInstitution(int $institutionId): void
    {
        if (!$this->hasMembership($institutionId)) {
            abort(403, 'You are not an active member of this institution.');
        }

        $this->update(['active_institution_id' => $institutionId]);
    }

    public function canImpersonate(): bool
    {
        return $this->hasGlobalRole(UserRole::ADMINISTRATOR);
    }

    public function canBeImpersonated(): bool
    {
        return !$this->hasGlobalRole(UserRole::ADMINISTRATOR);
    }

    public function isAdministrator(): bool
    {
        return $this->hasGlobalRole(UserRole::ADMINISTRATOR);
    }

    protected function resolveActiveMembership()
    {
        if ($this->relationLoaded('activeMembership')) {
            return $this->getRelation('activeMembership');
        }

        if (!$this->active_institution_id) {
            return null;
        }

        return $this->activeMembership()->first();
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
