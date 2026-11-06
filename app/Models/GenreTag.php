<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 */
class GenreTag extends Model
{
    protected $fillable = ['slug', 'name'];

    /**
     * @return BelongsToMany<Adventure, $this>
     */
    public function adventures(): BelongsToMany
    {
        return $this->belongsToMany(Adventure::class, 'adventure_genre_tag');
    }
}
