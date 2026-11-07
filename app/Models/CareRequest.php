<?php

namespace App\Models;

use Database\Factories\CareRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CareRequest extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return CareRequestFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'uuid',
        'reference',
        'user_id',
        'beneficiary_id',
        'service_id',
        'address_id',
        'urgent',
        'date',
        'time',
        'scheduled_at',
        'description',
        'notes',
        // 'perimeter',
        // 'evaluation',
        // 'gender',
        // 'certified',
        // 'free_mobility',
        'status',
        'address_label',
        'address_line',
        'city',
        'latitude',
        'longitude',
        'address_instructions',
        'cancelled_at',
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
            'user_id' => 'integer',
            'beneficiary_id' => 'integer',
            'service_id' => 'integer',
            'address_id' => 'integer',
            'urgent' => 'boolean',
            'date' => 'date:Y-m-d',
            'scheduled_at' => 'datetime',
            // 'duration_hours' => 'float',
            // 'perimeter' => 'integer',
            'evaluation' => 'float',
            // 'certified' => 'boolean',
            // 'free_mobility' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CareRequestDocument::class);
    }
}
