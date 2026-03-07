<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\LegalConsent;
use App\Models\LegalDocument;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Sanctum\HasApiTokens;
use MeShaon\RequestAnalytics\Contracts\CanAccessAnalyticsDashboard;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail, CanAccessAnalyticsDashboard
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
        'referral_id',
        'referred_by',
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
    ];

    /**
     * Auto-generate a unique referral_id on user creation.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (User $user) {
            if (empty($user->referral_id)) {
                $user->referral_id = self::generateReferralCode();
            }
        });
    }

    /**
     * Generate a unique 8-character referral code.
     */
    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_id', $code)->exists());

        return $code;
    }

    /* ── Legal Consent ──────────────────────────────── */

    public function legalConsents()
    {
        return $this->hasMany(LegalConsent::class);
    }

    public function hasAcceptedAllLegalDocuments(): bool
    {
        return LegalConsent::hasAcceptedAllRequired($this);
    }

    public function pendingLegalDocuments(): \Illuminate\Database\Eloquent\Collection
    {
        return LegalConsent::pendingForUser($this);
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
     * Users this user has referred.
     */
    public function referrals()
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * The referral record where this user was referred.
     */
    public function referredBy()
    {
        return $this->hasOne(Referral::class, 'referred_id');
    }

    /**
     * The user who referred this user.
     */
    public function referrer()
    {
        return $this->belongsTo(User::class, 'referred_by');
    }

    /**
     * Check if user can perform a specific action.
     */
    public function canPerform(string $permission): bool
    {
        if ($this->hasRole(UserRole::ADMINISTRATOR)) {
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
        if ($this->hasRole(UserRole::ADMINISTRATOR)) {
            return UserRole::ADMINISTRATOR;
        }

        return UserRole::REGULAR;
    }

    public function getIsActiveAttribute(): bool
    {
        return (bool) $this->attributes['is_active'];
    }

    /**
     * Scope to filter users by role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        });
    }

    /**
     * Scope to filter active users.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }



    public function canImpersonate(): bool
    {
        return $this->hasRole(UserRole::ADMINISTRATOR);
    }

    public function canBeImpersonated(): bool
    {
        return !$this->hasRole(UserRole::ADMINISTRATOR);
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole(UserRole::ADMINISTRATOR);
    }

    public function canAccessAnalyticsDashboard(): bool
    {
        return $this->isAdministrator();
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
