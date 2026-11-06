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
            $probe = $this->withAnswers($answers, ['timeframe' => $wish->value]);
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
                'regionSlug' => $region->value,
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

            // „Außerhalb Aventuriens“ nur bei echten Extra-Aventurien-Treffern im Pool.
            if ($wish === ExoticWish::ExtraAventurian) {
                foreach ($base as $candidate) {
                    if ($candidate->isExtraAventurian()) {
                        $values[] = $wish->value;
                        break;
                    }
                }

                continue;
            }

            $probe = $this->withAnswers($answers, ['exotic' => $wish->value]);
            if ($this->pool($probe, $base) !== []) {
                $values[] = $wish->value;
            }
        }

        return $values;
    }

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     * @param  list<string>  $catalogSlugs
     * @return list<string>
     */
    public function genreSlugs(WizardAnswers $answers, iterable $candidates, array $catalogSlugs): array
    {
        $base = $this->pool($answers, $candidates, self::FIELD_GENRES);
        $available = [];

        foreach ($catalogSlugs as $slug) {
            $probe = $this->withAnswers($answers, ['genres' => [$slug]]);
            if ($this->pool($probe, $base) !== []) {
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
        foreach (RulesWish::cases() as $wish) {
            if ($wish === RulesWish::Any) {
                $values[] = $wish->value;

                continue;
            }

            $probe = $this->withAnswers($answers, ['rules' => $wish->value]);
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
        if ($candidate->incomplete) {
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

        if ($except !== self::FIELD_TIMEFRAME && $this->hasTimeframeConstraint($answers)) {
            if ($this->scores->timeframeScore($answers->durationWish(), $candidate) < self::ACCEPTABLE_FIT) {
                return false;
            }
        }

        if ($except !== self::FIELD_REGION && $answers->hasRegionWish()) {
            if ($answers->placeSlug !== null) {
                if ($candidate->primaryPlaceSlug !== $answers->placeSlug) {
                    return false;
                }
            } elseif ($answers->regionSlug !== null
                && ! in_array($answers->regionSlug, $candidate->allRegionSlugs(), true)) {
                return false;
            }
        }

        if ($except !== self::FIELD_EXOTIC && $this->hasExoticConstraint($answers)) {
            $exoticFit = $answers->exoticWish()->fitFor($candidate->exotic, $candidate->isExtraAventurian());
            if ($exoticFit < self::ACCEPTABLE_FIT) {
                return false;
            }
        }

        if ($except !== self::FIELD_GENRES && $answers->genres !== []) {
            if ($this->scores->genreScore($answers->genres, $candidate->genres) <= 0) {
                return false;
            }
        }

        if ($except !== self::FIELD_RULES && $this->hasRulesConstraint($answers)) {
            if (! $answers->rulesWish()->accepts($candidate->ruleVersion)) {
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

    private function hasTimeframeConstraint(WizardAnswers $answers): bool
    {
        return $answers->timeframe !== '' && DurationWish::tryFrom($answers->timeframe) !== null;
    }

    private function hasExoticConstraint(WizardAnswers $answers): bool
    {
        if ($answers->exotic === '' || $answers->exotic === ExoticWish::Any->value) {
            return false;
        }

        return ExoticWish::tryFrom($answers->exotic) !== null;
    }

    private function hasRulesConstraint(WizardAnswers $answers): bool
    {
        if ($answers->rules === '' || $answers->rules === RulesWish::Any->value) {
            return false;
        }

        return RulesWish::tryFrom($answers->rules) !== null;
    }

    private function hasGmConstraint(WizardAnswers $answers): bool
    {
        return $answers->gmExperience !== '' && ExperienceLevel::tryFrom($answers->gmExperience) !== null;
    }

    /**
     * @param  array{
     *     experienceLevel?: string,
     *     groupSize?: ?string,
     *     timeframe?: string,
     *     regionSlug?: ?string,
     *     placeSlug?: ?string,
     *     exotic?: string,
     *     genres?: list<string>,
     *     rules?: string,
     *     gmExperience?: string
     * }  $overrides
     */
    private function withAnswers(WizardAnswers $base, array $overrides = []): WizardAnswers
    {
        return new WizardAnswers(
            experienceLevel: $overrides['experienceLevel'] ?? $base->experienceLevel,
            groupSize: array_key_exists('groupSize', $overrides) ? $overrides['groupSize'] : $base->groupSize,
            timeframe: $overrides['timeframe'] ?? $base->timeframe,
            regionSlug: array_key_exists('regionSlug', $overrides) ? $overrides['regionSlug'] : $base->regionSlug,
            placeSlug: array_key_exists('placeSlug', $overrides) ? $overrides['placeSlug'] : $base->placeSlug,
            exotic: $overrides['exotic'] ?? $base->exotic,
            genres: $overrides['genres'] ?? $base->genres,
            rules: $overrides['rules'] ?? $base->rules,
            gmExperience: $overrides['gmExperience'] ?? $base->gmExperience,
        );
    }
}
