<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Gegenseitige Facetten für das Wunsch-Formular: gesetzte Antworten bilden den Pool,
 * Optionen je Feld entstehen aus dem Pool ohne dieses Feld (damit die aktuelle Wahl bleibt).
 *
 * Leere Strings bzw. null / „any“ / leere Genres wirken nicht als Filter.
 * Region/Ort: exakt (keine Nachbarn/bloßes Reisen).
 */
final class WizardFacetFilter
{
    public const FIELD_EXPERIENCE = 'experience';

    public const FIELD_GROUP_SIZE = 'groupSize';

    public const FIELD_TIMEFRAME = 'timeframe';

    public const FIELD_REGION = 'region';

    public const FIELD_EXOTIC = 'exotic';

    public const FIELD_GENRES = 'genres';

    public const FIELD_RULES = 'rules';

    public const FIELD_GM = 'gmExperience';

    private const ACCEPTABLE_FIT = 50;

    private const STRICT_EXPERIENCE_DISTANCE = 1;

    public function __construct(
        private readonly ScoreCalculator $scores = new ScoreCalculator,
    ) {}

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<AdventureCandidate>
     */
    public function pool(WizardAnswers $answers, iterable $candidates, ?string $except = null): array
    {
        $pool = [];

        foreach ($candidates as $candidate) {
            if ($this->passes($answers, $candidate, $except)) {
                $pool[] = $candidate;
            }
        }

        return $pool;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<string>
     */
    public function experienceLevels(WizardAnswers $answers, iterable $candidates): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_EXPERIENCE);
        $values = [];

        foreach (ExperienceLevel::cases() as $level) {
            $probe = $this->withAnswers($answers, ['experienceLevel' => $level->value]);
            if ($this->pool($probe, $base) !== []) {
                $values[] = $level->value;
            }
        }

        return $values;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<string> inklusive „egal“
     */
    public function groupSizes(WizardAnswers $answers, iterable $candidates): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_GROUP_SIZE);
        if ($base === []) {
            return [];
        }

        $values = [];
        foreach (GroupSizeWish::cases() as $wish) {
            $probe = $this->withAnswers($answers, ['groupSize' => $wish->value]);
            if ($this->pool($probe, $base) !== []) {
                $values[] = $wish->value;
            }
        }

        $values[] = 'egal';

        return $values;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<string>
     */
    public function timeframes(WizardAnswers $answers, iterable $candidates): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_TIMEFRAME);
        $values = [];

        foreach (DurationWish::cases() as $wish) {
            $probe = $this->withAnswers($answers, ['timeframes' => [$wish->value]]);
            if ($this->pool($probe, $base) !== []) {
                $values[] = $wish->value;
            }
        }

        return $values;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<string>
     */
    public function regionSlugs(WizardAnswers $answers, iterable $candidates): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_REGION);
        $values = [];

        foreach (RegionKey::cases() as $region) {
            $probe = $this->withAnswers($answers, [
                'regionSlugs' => [$region->value],
                'regionsAny' => false,
                'placeSlug' => null,
            ]);
            if ($this->pool($probe, $base) !== []) {
                $values[] = $region->value;
            }
        }

        return $values;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<string>
     */
    public function exoticWishes(WizardAnswers $answers, iterable $candidates): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_EXOTIC);
        if ($base === []) {
            return [];
        }

        $values = [];
        foreach (ExoticWish::cases() as $wish) {
            if ($wish === ExoticWish::Any) {
                $values[] = $wish->value;

                continue;
            }

            // „Andere Kontinente“ nur bei echten Extra-Aventurien-Treffern im Pool.
            if ($wish === ExoticWish::ExtraAventurian) {
                foreach ($base as $candidate) {
                    if ($candidate->isExtraAventurian()) {
                        $values[] = $wish->value;
                        break;
                    }
                }

                continue;
            }

            $probe = $this->withAnswers($answers, [
                'exotics' => [$wish->value],
                'exoticAny' => false,
            ]);
            if ($this->pool($probe, $base) !== []) {
                $values[] = $wish->value;
            }
        }

        return $values;
    }

    /**
     * Genres, die im Pool noch vorkommen. Ein einzelnes Wunsch-Genre passt genau dann,
     * wenn ein verbleibender Kandidat es trägt, deshalb reicht ein Durchgang über den Pool.
     *
     * @param  iterable<AdventureCandidate>  $candidates
     * @param  list<string>  $catalogSlugs
     * @return list<string>
     */
    public function genreSlugs(WizardAnswers $answers, iterable $candidates, array $catalogSlugs): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_GENRES);
        $present = [];

        foreach ($base as $candidate) {
            foreach ($candidate->genres as $genre) {
                $present[$genre] = true;
            }
        }

        $available = [];

        foreach ($catalogSlugs as $slug) {
            if (isset($present[$slug])) {
                $available[] = $slug;
            }
        }

        return $available;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<string>
     */
    public function rulesWishes(WizardAnswers $answers, iterable $candidates): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_RULES);
        if ($base === []) {
            return [];
        }

        $values = [];
        foreach (RuleVersion::cases() as $version) {
            if ($version === RuleVersion::EditionUnabhaengig) {
                continue;
            }

            foreach ($base as $candidate) {
                if ($candidate->ruleVersion === $version) {
                    $values[] = $version->value;
                    break;
                }
            }
        }

        $values[] = 'any';

        return $values;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<string>
     */
    public function gmExperienceLevels(WizardAnswers $answers, iterable $candidates): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_GM);
        $values = [];

        foreach (ExperienceLevel::cases() as $level) {
            $probe = $this->withAnswers($answers, ['gmExperience' => $level->value]);
            if ($this->pool($probe, $base) !== []) {
                $values[] = $level->value;
            }
        }

        return $values;
    }

    private function passes(WizardAnswers $answers, AdventureCandidate $candidate, ?string $except): bool
    {
        if ($candidate->missingFromWiki) {
            return false;
        }

        if ($except !== self::FIELD_EXPERIENCE && $this->hasExperienceConstraint($answers)) {
            $distance = $answers->experience()->distanceTo($candidate->experienceLevel);
            if ($distance > self::STRICT_EXPERIENCE_DISTANCE) {
                return false;
            }
        }

        if ($except !== self::FIELD_GROUP_SIZE) {
            $group = $answers->groupSizeWish();
            if ($group !== null && ! $group->isCompatibleWith($candidate->groupSizeMin, $candidate->groupSizeMax)) {
                return false;
            }
        }

        if ($except !== self::FIELD_TIMEFRAME && $answers->restrictsDuration()) {
            if ($this->scores->bestTimeframeScore($answers, $candidate) < self::ACCEPTABLE_FIT) {
                return false;
            }
        }

        if ($except !== self::FIELD_REGION && $answers->hasRegionWish()) {
            if ($answers->placeSlug !== null) {
                if (! $candidate->matchesPlaceSlug($answers->placeSlug)) {
                    return false;
                }
            } elseif (! $answers->acceptsRegion($candidate->allRegionSlugs())) {
                return false;
            }
        }

        if ($except !== self::FIELD_EXOTIC && $answers->restrictsExotic()) {
            if ($this->scores->bestExoticScore($answers, $candidate) < self::ACCEPTABLE_FIT) {
                return false;
            }
        }

        if ($except !== self::FIELD_GENRES && $answers->genres !== []) {
            if ($this->scores->genreScore($answers->genres, $candidate->genres) <= 0) {
                return false;
            }
        }

        if ($except !== self::FIELD_RULES && $answers->rules !== []) {
            if (! $answers->acceptsRule($candidate->ruleVersion)) {
                return false;
            }
        }

        if ($except !== self::FIELD_GM && $this->hasGmConstraint($answers)) {
            if ($this->scores->gmScore($answers->gmLevel(), $candidate->complexity) < self::ACCEPTABLE_FIT) {
                return false;
            }
        }

        return true;
    }

    private function hasExperienceConstraint(WizardAnswers $answers): bool
    {
        return $answers->experienceLevel !== '' && ExperienceLevel::tryFrom($answers->experienceLevel) !== null;
    }

    private function hasGmConstraint(WizardAnswers $answers): bool
    {
        return $answers->gmExperience !== '' && ExperienceLevel::tryFrom($answers->gmExperience) !== null;
    }

    /**
     * @param  array{
     *     experienceLevel?: string,
     *     groupSize?: ?string,
     *     timeframes?: list<string>,
     *     regionSlugs?: list<string>,
     *     regionsAny?: bool,
     *     placeSlug?: ?string,
     *     exotics?: list<string>,
     *     exoticAny?: bool,
     *     genres?: list<string>,
     *     rules?: list<string>,
     *     rulesAny?: bool,
     *     gmExperience?: string
     * }  $overrides
     */
    private function withAnswers(WizardAnswers $base, array $overrides = []): WizardAnswers
    {
        return new WizardAnswers(
            experienceLevel: $overrides['experienceLevel'] ?? $base->experienceLevel,
            groupSize: array_key_exists('groupSize', $overrides) ? $overrides['groupSize'] : $base->groupSize,
            timeframes: $overrides['timeframes'] ?? $base->timeframes,
            regionSlugs: $overrides['regionSlugs'] ?? $base->regionSlugs,
            regionsAny: $overrides['regionsAny'] ?? $base->regionsAny,
            placeSlug: array_key_exists('placeSlug', $overrides) ? $overrides['placeSlug'] : $base->placeSlug,
            exotics: $overrides['exotics'] ?? $base->exotics,
            exoticAny: $overrides['exoticAny'] ?? $base->exoticAny,
            genres: $overrides['genres'] ?? $base->genres,
            rules: $overrides['rules'] ?? $base->rules,
            rulesAny: $overrides['rulesAny'] ?? $base->rulesAny,
            gmExperience: $overrides['gmExperience'] ?? $base->gmExperience,
        );
    }
}
