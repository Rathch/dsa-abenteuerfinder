<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Alle für das Matching nötigen Daten eines Abenteuers (ohne Persistenzbezug).
 */
final readonly class AdventureCandidate
{
    /**
     * @param  list<string>  $regionSlugs  zusätzliche Regionen (Korridor / Reise)
     * @param  list<string>  $genres  Genre-Slugs
     */
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
        public string $seriesSlug,
        public string $seriesName,
        public ExperienceLevel $experienceLevel,
        public ?int $pageCount,
        public DurationCategory $durationCategory,
        public RuleVersion $ruleVersion,
        public bool $exotic,
        public Complexity $complexity,
        public ?int $groupSizeMin,
        public ?int $groupSizeMax,
        public LocationType $locationType,
        public ?string $primaryPlaceSlug,
        public ?string $primaryPlaceName,
        public ?string $primaryRegionSlug,
        public array $regionSlugs,
        public array $genres,
        public string $shortDescription,
        public string $wikiUrl,
        public bool $incomplete = false,
    ) {}

    /**
     * Primäre Region plus alle weiteren Regionen, ohne Duplikate.
     *
     * @return list<string>
     */
    public function allRegionSlugs(): array
    {
        $slugs = $this->regionSlugs;
        if ($this->primaryRegionSlug !== null) {
            array_unshift($slugs, $this->primaryRegionSlug);
        }

        return array_values(array_unique($slugs));
    }

    public function isExtraAventurian(): bool
    {
        foreach ($this->allRegionSlugs() as $slug) {
            if (RegionKey::tryFrom($slug)?->isExtraAventurian() === true) {
                return true;
            }
        }

        return false;
    }
}
