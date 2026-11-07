<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $region
 * @property bool $is_active
 * @property int $display_order
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    protected $fillable = [
        'name',
        'slug',
        'region',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    /**
     * Scope active cities.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('display_order')->orderBy('name');
    }
}

