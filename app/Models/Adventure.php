<?php

namespace App\Models;

use App\Domain\Matching\Complexity;
use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\RuleVersion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property int $series_id
 * @property ExperienceLevel $experience_level
 * @property int|null $page_count
 * @property DurationCategory $duration_category
 * @property RuleVersion $rule_version
 * @property bool $exotic
 * @property Complexity $complexity
 * @property int|null $group_size_min
 * @property int|null $group_size_max
 * @property LocationType $location_type
 * @property int|null $primary_location_id
 * @property int|null $primary_region_id
 * @property string $short_description
 * @property string $wiki_url
 * @property bool $incomplete
 * @property bool $missing_from_wiki
 * @property string|null $source_note
 * @property-read Series $series
 * @property-read Location|null $primaryLocation
 * @property-read Location|null $primaryRegion
 */
class Adventure extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'series_id',
        'experience_level',
        'page_count',
        'duration_category',
        'rule_version',
        'exotic',
        'complexity',
        'group_size_min',
        'group_size_max',
        'location_type',
        'primary_location_id',
        'primary_region_id',
        'short_description',
        'wiki_url',
        'incomplete',
        'missing_from_wiki',
        'source_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'experience_level' => ExperienceLevel::class,
            'duration_category' => DurationCategory::class,
            'rule_version' => RuleVersion::class,
            'complexity' => Complexity::class,
            'location_type' => LocationType::class,
            'exotic' => 'boolean',
            'incomplete' => 'boolean',
            'missing_from_wiki' => 'boolean',
            'page_count' => 'integer',
            'group_size_min' => 'integer',
            'group_size_max' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Series, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(Series::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function primaryRegion(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_region_id');
    }

    /**
     * @return BelongsToMany<GenreTag, $this>
     */
    public function genreTags(): BelongsToMany
    {
        return $this->belongsToMany(GenreTag::class, 'adventure_genre_tag');
    }

    /**
     * Zusätzliche Regionen (Korridor / Reise).
     *
     * @return BelongsToMany<Location, $this>
     */
    public function regions(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'adventure_region', 'adventure_id', 'region_id');
    }
}
