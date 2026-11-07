<?php

namespace App\Models;

use App\Enums\HealthcareProfession;
use App\Enums\ProfessionalHistoryAction;
use App\Enums\ProfessionalStatus;
use Database\Factories\ProfessionalProfileFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $profession_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $sexe
 * @property string|null $id_number
 * @property ProfessionalStatus $status
 * @property string|null $rejection_reason
 * @property string|null $bio
 * @property string|null $avatar
 * @property string|null $phone
 * @property bool $is_available
 * @property Carbon|null $submitted_at
 * @property Carbon|null $validated_at
 * @property int|null $validated_by
 * @property array<string, mixed>|null $pending_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $full_name
 * @property-read string|null $professional_id
 * @property-read bool $is_validated
 * @property-read bool $is_eligible
 * @property-read User $user
 * @property-read Profession|null $profession
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, ProfessionalDocument> $documents
 * @property-read Collection<int, ProfessionalDecisionHistory> $decisionHistories
 * @property-read User|null $validatedBy
 */
class ProfessionalProfile extends Model
{
    /** @use HasFactory<ProfessionalProfileFactory> */
    use HasFactory;

    protected $table = 'professional_profiles';

    protected $fillable = [
        'user_id',
        'profession_id',
        'first_name',
        'last_name',
        'sexe',
        'id_number',
        'status',
        'rejection_reason',
        'bio',
        'avatar',
        'phone',
        'city',
        'birthday',
        'is_available',
        'submitted_at',
        'validated_at',
        'validated_by',
        'pending_data',
        // Also allow aliases on mass assignment
        'firstName',
        'lastName',
        'birth_date',
        'date_of_birth',
        'professional_id',
        'professionalId',
        'ID',
    ];

    /**
     * @var array<string>
     */
    protected $appends = [
        'full_name',
        'professional_id',
        'is_validated',
        'is_eligible',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfessionalStatus::class,
            'is_available' => 'boolean',
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'birthday' => 'date',
            'pending_data' => 'array',
        ];
    }

    protected static function newFactory(): ProfessionalProfileFactory
    {
        return ProfessionalProfileFactory::new();
    }

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Profession relationship.
     */
    public function profession(): BelongsTo
    {
        return $this->belongsTo(Profession::class);
    }

    /**
     * Approved services offered by the professional.
     *
     * @return BelongsToMany<Service, $this>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'professional_service')
            ->withTimestamps();
    }

    /**
     * Uploaded professional documents.
     *
     * @return HasMany<ProfessionalDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProfessionalDocument::class);
    }

    /**
     * History of verification and administrative decisions.
     *
     * @return HasMany<ProfessionalDecisionHistory, $this>
     */
    public function decisionHistories(): HasMany
    {
        return $this->hasMany(ProfessionalDecisionHistory::class)->orderBy('created_at', 'desc');
    }

    /**
     * Administrator who validated the profile.
     */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    /**
     * CamelCase alias accessor & mutator for firstName.
     */
    protected function firstName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes) => $attributes['first_name'] ?? $value,
            set: fn (?string $value) => ['first_name' => $value],
        );
    }

    /**
     * CamelCase alias accessor & mutator for lastName.
     */
    protected function lastName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes) => $attributes['last_name'] ?? $value,
            set: fn (?string $value) => ['last_name' => $value],
        );
    }

    /**
     * Alias accessor & mutator for birth_date / date_of_birth / birthday.
     */
    protected function birthDate(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['birthday'] ?? $value,
            set: fn ($value) => ['birthday' => $value],
        );
    }

    protected function dateOfBirth(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $attributes['birthday'] ?? $value,
            set: fn ($value) => ['birthday' => $value],
        );
    }

    /**
     * Alias accessor & mutator for professional_id / ID / id_number.
     */
    protected function professionalId(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes) => $attributes['id_number'] ?? $value,
            set: fn (?string $value) => ['id_number' => $value],
        );
    }

    /**
     * Full name accessor.
     */
    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => trim(($this->first_name ?? '').' '.($this->last_name ?? '')),
        );
    }

    /**
     * Backward-compatible accessor for birthday.
     */
    public function getBirthdayAttribute(): ?string
    {
        return $this->attributes['birthday'] ?? null;
    }

    /**
     * Backward-compatible accessor for city.
     */
    public function getCityAttribute(): ?string
    {
        return $this->attributes['city'] ?? null;
    }

    /**
     * Accessor for is_validated.
     */
    public function getIsValidatedAttribute(): bool
    {
        return $this->isValidated();
    }

    /**
     * Accessor for is_eligible.
     */
    public function getIsEligibleAttribute(): bool
    {
        return $this->isEligible();
    }

    /**
     * Check if profile is administratively validated.
     */
    public function isValidated(): bool
    {
        return $this->status === ProfessionalStatus::Validated;
    }

    /**
     * Check if professional is currently eligible for missions.
     * (Validated AND Available).
     */
    public function isEligible(): bool
    {
        return $this->isValidated() && (bool) $this->is_available;
    }

    public function isPending(): bool
    {
        return $this->status === ProfessionalStatus::Pending;
    }

    public function isDraft(): bool
    {
        return $this->status === ProfessionalStatus::Draft;
    }

    public function isRejected(): bool
    {
        return $this->status === ProfessionalStatus::Rejected;
    }

    public function hasPendingChanges(): bool
    {
        return $this->status === ProfessionalStatus::PendingChanges;
    }

    /**
     * Record a decision or event in the history log.
     *
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordHistory(
        ProfessionalHistoryAction|string $action,
        ?string $reason = null,
        ?array $metadata = null,
        ?User $admin = null,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
    ): ProfessionalDecisionHistory {
        $actionEnum = $action instanceof ProfessionalHistoryAction
            ? $action
            : ProfessionalHistoryAction::from($action);

        return $this->decisionHistories()->create([
            'admin_id' => $admin?->id,
            'action' => $actionEnum,
            'status_before' => $statusBefore ?? $this->getOriginal('status')?->value ?? (string) $this->status?->value,
            'status_after' => $statusAfter ?? $this->status?->value,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
