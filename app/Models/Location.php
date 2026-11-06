<?php

namespace App\Models;

use App\Domain\Matching\LocationKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property LocationKind $type
 * @property int|null $parent_id
 * @property-read Location|null $parent
 */
class Location extends Model
{
    protected $fillable = ['slug', 'name', 'type', 'parent_id'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LocationKind::class,
        ];
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Location, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
