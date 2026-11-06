<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
class Series extends Model
{
    protected $table = 'series';

    protected $fillable = ['slug', 'name'];

    /**
     * @return HasMany<Adventure, $this>
     */
    public function adventures(): HasMany
    {
        return $this->hasMany(Adventure::class);
    }
}
