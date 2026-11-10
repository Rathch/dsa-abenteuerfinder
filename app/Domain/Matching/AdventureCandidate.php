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
        public ?string $coverUrl = null,
        public ?string $placeLabel = null,
        public bool $missingFromWiki = false,
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

    public function matchesPlaceSlug(string $slug): bool
    {
        if ($this->primaryPlaceSlug === $slug) {
            return true;
        }

        return self::labelContainsPlace($this->placeLabel, $slug);
    }

    /**
     * @param  array<string, string>  $regionByPlaceSlug
     * @return list<string>
     */
    public static function regionsMentionedIn(?string $label, array $regionByPlaceSlug): array
    {
        return self::regionsForLabel($label, self::regionIndex($regionByPlaceSlug));
    }

    /**
     * @param  array<string, string>  $regionByPlaceSlug
     * @return array<string, list<string>>
     */
    public static function regionIndex(array $regionByPlaceSlug): array
    {
        $regionsByKey = [];
        foreach ($regionByPlaceSlug as $placeSlug => $regionSlug) {
            $regionsByKey[$placeSlug][] = $regionSlug;
            $shorter = self::collisionKey($placeSlug, $regionSlug);
            if ($shorter !== null && ($shorter === $regionSlug || isset($regionByPlaceSlug[$shorter]))) {
                $regionsByKey[$shorter][] = $regionSlug;
            }
        }

        return $regionsByKey;
    }

    /**
     * @param  array<string, list<string>>  $regionsByMatchKey
     * @return list<string>
     */
    public static function regionsForLabel(?string $label, array $regionsByMatchKey): array
    {
        $found = [];
        foreach (self::labelMatchKeys($label) as $key) {
            foreach ($regionsByMatchKey[$key] ?? [] as $regionSlug) {
                $found[$regionSlug] = $regionSlug;
            }
        }

        return array_values($found);
    }

    private static function labelContainsPlace(?string $label, string $slug): bool
    {
        if ($label === null || $slug === '') {
            return false;
        }

        $keys = [$slug];
        $shorter = self::collisionKey($slug, null);
        if ($shorter !== null) {
            $keys[] = $shorter;
        }

        foreach (self::labelMatchKeys($label) as $part) {
            if (in_array($part, $keys, true)) {
                return true;
            }
        }

        return false;
    }

    private static function collisionKey(string $slug, ?string $regionSlug): ?string
    {
        $suffixes = $regionSlug !== null ? [$regionSlug] : array_map(static fn (RegionKey $region): string => $region->value, RegionKey::cases());
        foreach ($suffixes as $suffix) {
            $tail = '-'.$suffix;
            if (str_ends_with($slug, $tail) && strlen($slug) > strlen($tail)) {
                return substr($slug, 0, -strlen($tail));
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function labelMatchKeys(?string $label): array
    {
        if ($label === null || trim($label) === '') {
            return [];
        }

        $parts = preg_split('/\s*(?:,|;|:|\/|\bund\b)\s*/u', $label) ?: [];
        $keys = [];
        foreach ($parts as $part) {
            $key = self::placeSlugKey($part);
            if ($key !== '') {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private static function placeSlugKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = strtr($value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);
        $value = str_replace(["'", '’', '`', '´'], '', $value);
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value) ?? $value;

        return trim($value, '-');
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
