<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Antworten des Wizards.
 *
 * Regeln, Regionen und Exotik sind Listen plus ein „Egal“-Kennzeichen.
 * Die Dauer ist nur eine Liste. Eine leere Liste und „Egal“ filtern nicht.
 * Eine nicht leere Liste hebt „Egal“ auf.
 */
final readonly class WizardAnswers
{
    /** @var list<string> */
    public array $timeframes;

    /** @var list<string> */
    public array $regionSlugs;

    public bool $regionsAny;

    public ?string $placeSlug;

    /** @var list<string> */
    public array $exotics;

    public bool $exoticAny;

    /** @var list<string> */
    public array $rules;

    public bool $rulesAny;

    /**
     * @param  list<string>  $timeframes
     * @param  list<string>  $regionSlugs
     * @param  list<string>  $exotics
     * @param  list<string>  $genres
     * @param  list<string>  $rules
     */
    public function __construct(
        public string $experienceLevel,
        public ?string $groupSize,
        array $timeframes,
        array $regionSlugs,
        bool $regionsAny,
        ?string $placeSlug,
        array $exotics,
        bool $exoticAny,
        public array $genres,
        array $rules,
        bool $rulesAny,
        public string $gmExperience,
    ) {
        $rules = self::uniqueStrings($rules);
        $regionSlugs = self::uniqueStrings($regionSlugs);
        $exotics = self::uniqueStrings($exotics);
        $timeframes = self::uniqueStrings($timeframes);

        if ($rules !== []) {
            $rulesAny = false;
        }
        if ($regionSlugs !== []) {
            $regionsAny = false;
        }
        if ($exotics !== []) {
            $exoticAny = false;
        }

        if ($regionsAny || count($regionSlugs) !== 1) {
            $placeSlug = null;
        }

        $this->timeframes = $timeframes;
        $this->regionSlugs = $regionSlugs;
        $this->regionsAny = $regionsAny;
        $this->placeSlug = $placeSlug;
        $this->exotics = $exotics;
        $this->exoticAny = $exoticAny;
        $this->rules = $rules;
        $this->rulesAny = $rulesAny;
    }

    /**
     * Stellt eine Sitzung wieder her. Textwerte für Regeln, Region, Dauer und Exotik werden verworfen.
     *
     * @param  array<string, mixed>  $stored
     */
    public static function fromSession(array $stored): self
    {
        $rules = self::storedList($stored, 'rules');
        $regions = self::storedList($stored, 'regionSlugs');
        $timeframes = self::storedList($stored, 'timeframes');
        $exotics = self::storedList($stored, 'exotics');
        $genres = self::storedList($stored, 'genres') ?? [];
        $groupSize = $stored['groupSize'] ?? null;
        $place = $stored['placeSlug'] ?? null;

        return new self(
            experienceLevel: is_string($stored['experienceLevel'] ?? null) ? $stored['experienceLevel'] : '',
            groupSize: is_string($groupSize) && $groupSize !== '' ? $groupSize : null,
            timeframes: $timeframes ?? [],
            regionSlugs: $regions ?? [],
            regionsAny: $regions !== null && ($stored['regionsAny'] ?? false) === true,
            placeSlug: is_string($place) && $place !== '' ? $place : null,
            exotics: $exotics ?? [],
            exoticAny: $exotics !== null && ($stored['exoticAny'] ?? false) === true,
            genres: $genres,
            rules: $rules ?? [],
            rulesAny: $rules !== null && ($stored['rulesAny'] ?? false) === true,
            gmExperience: is_string($stored['gmExperience'] ?? null) ? $stored['gmExperience'] : '',
        );
    }

    public function experience(): ExperienceLevel
    {
        return ExperienceLevel::tryFrom($this->experienceLevel) ?? ExperienceLevel::Einsteiger;
    }

    public function gmLevel(): ExperienceLevel
    {
        return ExperienceLevel::tryFrom($this->gmExperience) ?? ExperienceLevel::Einsteiger;
    }

    public function groupSizeWish(): ?GroupSizeWish
    {
        return $this->groupSize === null ? null : GroupSizeWish::tryFrom($this->groupSize);
    }

    /**
     * @return list<DurationWish>
     */
    public function durationWishes(): array
    {
        $wishes = [];
        foreach ($this->timeframes as $value) {
            $wish = DurationWish::tryFrom($value);
            if ($wish !== null) {
                $wishes[] = $wish;
            }
        }

        return $wishes;
    }

    public function restrictsDuration(): bool
    {
        return $this->durationWishes() !== [];
    }

    public function acceptsDuration(DurationCategory $category): bool
    {
        $wishes = $this->durationWishes();
        if ($wishes === []) {
            return true;
        }

        foreach ($wishes as $wish) {
            if ($wish->fitFor($category) >= 50) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ExoticWish>
     */
    public function exoticWishes(): array
    {
        $wishes = [];
        foreach ($this->exotics as $value) {
            $wish = ExoticWish::tryFrom($value);
            if ($wish !== null && $wish !== ExoticWish::Any) {
                $wishes[] = $wish;
            }
        }

        return $wishes;
    }

    public function restrictsExotic(): bool
    {
        return ! $this->exoticAny && $this->exoticWishes() !== [];
    }

    public function acceptsExotic(bool $exotic, bool $extraAventurian): bool
    {
        if (! $this->restrictsExotic()) {
            return true;
        }

        foreach ($this->exoticWishes() as $wish) {
            if ($wish->fitFor($exotic, $extraAventurian) >= 50) {
                return true;
            }
        }

        return false;
    }

    public function acceptsRule(RuleVersion $version): bool
    {
        if ($this->rules === []) {
            return true;
        }

        if ($version === RuleVersion::EditionUnabhaengig) {
            return true;
        }

        return in_array($version->value, $this->rules, true);
    }

    /**
     * @param  list<string>  $candidateRegions
     */
    public function acceptsRegion(array $candidateRegions): bool
    {
        if ($this->regionSlugs === []) {
            return true;
        }

        return array_intersect($this->regionSlugs, $candidateRegions) !== [];
    }

    public function hasRegionWish(): bool
    {
        return $this->regionSlugs !== [] || $this->placeSlug !== null;
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return list<string>|null null, wenn der Wert fehlt oder kein Listenwert ist
     */
    private static function storedList(array $stored, string $key): ?array
    {
        if (! array_key_exists($key, $stored) || ! is_array($stored[$key])) {
            return null;
        }

        $values = [];
        foreach ($stored[$key] as $value) {
            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function uniqueStrings(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            if ($value !== '') {
                $unique[$value] = true;
            }
        }

        return array_keys($unique);
    }
}
