<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Berechnet gewichtete Soft-Scores (0–100) eines Abenteuers zu den Wizard-Antworten.
 */
final class ScoreCalculator
{
    public const WEIGHT_REGION = 0.25;

    public const WEIGHT_TIMEFRAME = 0.20;

    public const WEIGHT_GENRE = 0.20;

    public const WEIGHT_EXOTIC = 0.10;

    public const WEIGHT_GM = 0.10;

    public const WEIGHT_EXPERIENCE = 0.15;

    public const REGION_PLACE = 100;

    public const REGION_REGION = 80;

    public const REGION_TRAVELING = 60;

    public const REGION_NEIGHBOR = 50;

    public const REGION_OTHER = 20;

    /** Anteil der Seitenzahl-Schätzung an der Zeitrahmen-Wertung. */
    private const PAGE_PROXY_SHARE = 0.25;

    public function score(WizardAnswers $answers, AdventureCandidate $candidate): ScoreBreakdown
    {
        [$region, $regionKind, $matchedRegion] = $this->regionScore($answers, $candidate);
        $timeframe = $this->bestTimeframeScore($answers, $candidate);
        $genre = $this->genreScore($answers->genres, $candidate->genres);
        $exotic = $this->bestExoticScore($answers, $candidate);
        $gm = $this->gmScore($answers->gmLevel(), $candidate->complexity);
        $distance = $answers->experience()->distanceTo($candidate->experienceLevel);
        $experience = $this->experienceScore($distance);

        $total = (int) round(
            $region * self::WEIGHT_REGION
            + $timeframe * self::WEIGHT_TIMEFRAME
            + $genre * self::WEIGHT_GENRE
            + $exotic * self::WEIGHT_EXOTIC
            + $gm * self::WEIGHT_GM
            + $experience * self::WEIGHT_EXPERIENCE
        );

        return new ScoreBreakdown(
            region: $region,
            regionKind: $regionKind,
            matchedRegionSlug: $matchedRegion,
            timeframe: $timeframe,
            genre: $genre,
            exotic: $exotic,
            gm: $gm,
            experience: $experience,
            experienceDistance: $distance,
            total: max(0, min(100, $total)),
        );
    }

    /**
     * @return array{0: int, 1: RegionMatchKind, 2: ?string}
     */
    public function regionScore(WizardAnswers $answers, AdventureCandidate $candidate): array
    {
        if (! $answers->hasRegionWish()) {
            return [100, RegionMatchKind::Any, null];
        }

        if ($answers->placeSlug !== null && $candidate->matchesPlaceSlug($answers->placeSlug)) {
            return [self::REGION_PLACE, RegionMatchKind::Place, $candidate->primaryRegionSlug];
        }

        $traveling = $candidate->locationType === LocationType::Traveling;
        $wanted = $answers->regionSlugs;

        if ($wanted === []) {
            return $traveling
                ? [self::REGION_TRAVELING, RegionMatchKind::Traveling, null]
                : [self::REGION_OTHER, RegionMatchKind::None, null];
        }

        $regions = $candidate->allRegionSlugs();
        $exact = array_values(array_intersect($wanted, $regions));

        if ($exact !== []) {
            return [self::REGION_REGION, RegionMatchKind::Region, $exact[0]];
        }

        if ($traveling) {
            return [self::REGION_TRAVELING, RegionMatchKind::Traveling, null];
        }

        foreach ($wanted as $wantedSlug) {
            $wantedKey = RegionKey::tryFrom($wantedSlug);
            if ($wantedKey === null) {
                continue;
            }
            foreach ($regions as $slug) {
                $key = RegionKey::tryFrom($slug);
                if ($key !== null && $wantedKey->isNeighborOf($key)) {
                    return [self::REGION_NEIGHBOR, RegionMatchKind::Neighbor, $slug];
                }
            }
        }

        return [self::REGION_OTHER, RegionMatchKind::None, null];
    }

    public function bestTimeframeScore(WizardAnswers $answers, AdventureCandidate $candidate): int
    {
        $wishes = $answers->durationWishes();
        if ($wishes === []) {
            return 100;
        }

        $best = 0;
        foreach ($wishes as $wish) {
            $best = max($best, $this->timeframeScore($wish, $candidate));
        }

        return $best;
    }

    public function bestExoticScore(WizardAnswers $answers, AdventureCandidate $candidate): int
    {
        if (! $answers->restrictsExotic()) {
            return 100;
        }

        $best = 0;
        foreach ($answers->exoticWishes() as $wish) {
            $best = max($best, $wish->fitFor($candidate->exotic, $candidate->isExtraAventurian()));
        }

        return $best;
    }

    public function timeframeScore(DurationWish $wish, AdventureCandidate $candidate): int
    {
        $base = $wish->fitFor($candidate->durationCategory);

        if ($candidate->pageCount === null) {
            return $base;
        }

        $proxy = $wish->fitFor(DurationCategory::fromPageCount($candidate->pageCount));

        return (int) round($base * (1 - self::PAGE_PROXY_SHARE) + $proxy * self::PAGE_PROXY_SHARE);
    }

    /**
     * @param  list<string>  $wanted
     * @param  list<string>  $offered
     */
    public function genreScore(array $wanted, array $offered): int
    {
        $wanted = array_values(array_unique($wanted));
        if ($wanted === []) {
            return 100;
        }

        $overlap = count(array_intersect($wanted, $offered));

        return (int) round(100 * $overlap / count($wanted));
    }

    public function gmScore(ExperienceLevel $gm, Complexity $complexity): int
    {
        $diff = $complexity->rank() - Complexity::idealFor($gm)->rank();

        return match (true) {
            $diff === 0 => 100,
            $diff === 1 => 50,
            $diff >= 2 => 10,
            $diff === -1 => 80,
            default => 60,
        };
    }

    public function experienceScore(int $distance): int
    {
        return match (true) {
            $distance <= 0 => 100,
            $distance === 1 => 70,
            $distance === 2 => 40,
            default => 10,
        };
    }
}
