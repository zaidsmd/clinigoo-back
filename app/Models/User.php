<?php

namespace App\Models;

use App\Enums\HealthcareProfession;
use App\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserType $type
 * @property HealthcareProfession|string|null $profession
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PatientProfile|null $patientProfile
 * @property-read ProfessionalProfile|null $professionalProfile
 * @property-read PatientProfile|ProfessionalProfile|null $profile
 */
#[Fillable(['name', 'email', 'password', 'type', 'profession'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'profession' => HealthcareProfession::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Check if user is a patient.
     */
    public function isPatient(): bool
    {
        return $this->type === UserType::Patient;
    }

    /**
     * Check if user is a professional.
     */
    public function isProfessional(): bool
    {
        return $this->type === UserType::Professional;
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->type === UserType::Admin;
    }

    /**
     * Scope query to only patients.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopePatients(Builder $query): Builder
    {
        return $query->where('type', UserType::Patient->value);
    }

    /**
     * Scope query to only professionals.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeProfessionals(Builder $query): Builder
    {
        return $query->where('type', UserType::Professional->value);
    }

    /**
     * Scope query to only admins.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('type', UserType::Admin->value);
    }

    /**
     * Patient profile relationship.
     */
    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    /**
     * Professional profile relationship.
     */
    public function professionalProfile(): HasOne
    {
        return $this->hasOne(ProfessionalProfile::class);
    }

    /**
     * Beneficiaries relationship.
     *
     * @return HasMany<Beneficiary>
     */
    public function beneficiaries(): HasMany
    {
        return $this->hasMany(Beneficiary::class);
    }

    /**
     * Addresses relationship.
     *
     * @return HasMany<Address>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    /**
     * Care requests relationship.
     *
     * @return HasMany<CareRequest>
     */
    public function careRequests(): HasMany
    {
        return $this->hasMany(CareRequest::class);
    }

    /**
     * Dynamic profile accessor.
     */
    public function getProfileAttribute(): PatientProfile|ProfessionalProfile|null
    {
        if ($this->isProfessional()) {
            return $this->professionalProfile;
        }

        if ($this->isPatient()) {
            return $this->patientProfile;
        }

        return null;
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array<string, mixed>
     */
    public function getJWTCustomClaims(): array
    {
        return [
            'type' => $this->type?->value,
        ];
    }
}
