<?php

namespace App\Models;

use App\Enums\HealthcareServiceCategory;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property string|null $base_price
 * @property int|null $duration_minutes
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Profession> $professions
 */
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    protected static function newFactory(): ServiceFactory
    {
        return ServiceFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
        'description',
        'icon',
        'base_price',
        'duration_minutes',
        'is_active',
        'sort_order',
    ];

    /**
     * @var array<string>
     */
    protected $appends = [
        'service_category_id',
        'service_category',
        'profession_ids',
        'professions_summary',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'category' => HealthcareServiceCategory::class,
            'base_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function getServiceCategoryIdAttribute(): string
    {
        return $this->category instanceof HealthcareServiceCategory
            ? $this->category->value
            : (string) ($this->category ?? 'soins');
    }

    public function getServiceCategoryAttribute(): array
    {
        $cat = $this->category instanceof HealthcareServiceCategory
            ? $this->category
            : HealthcareServiceCategory::tryFrom((string) $this->category) ?? HealthcareServiceCategory::Soins;

        return [
            'id' => $cat->value,
            'value' => $cat->value,
            'name' => $cat->label(),
            'description' => $cat->description(),
        ];
    }

    public function getProfessionIdsAttribute(): array
    {
        if ($this->relationLoaded('professions')) {
            return $this->professions->pluck('id')->all();
        }

        return $this->professions()->pluck('professions.id')->all();
    }

    public function getProfessionsSummaryAttribute(): array
    {
        $profs = $this->relationLoaded('professions')
            ? $this->professions
            : $this->professions()->get();

        return $profs->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
        ])->all();
    }

    /**
     * @return HasMany<CareRequest, $this>
     */
    public function careRequests(): HasMany
    {
        return $this->hasMany(CareRequest::class);
    }

    /**
     * @return BelongsToMany<Profession, $this>
     */
    public function professions(): BelongsToMany
    {
        return $this->belongsToMany(Profession::class, 'profession_service')
            ->withTimestamps();
    }
}
