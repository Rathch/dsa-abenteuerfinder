<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Findet passende Abenteuer: harte Filter, gewichtete Soft-Scores, Serien-Diversität
 * und schrittweise Lockerung (Fallback) mit transparenten deutschen Hinweisen.
 *
 * Lockerungsreihenfolge bei weniger als {@see self::MIN_RECOMMENDATIONS} Treffern:
 * Region → Zeitrahmen → Erfahrungsstufe. Regeln, Gruppengröße und "unvollständig" bleiben fest.
 */
final class MatchFinder
{
    public const MIN_RECOMMENDATIONS = 3;

    public const MAX_RECOMMENDATIONS = 6;

    public const MAX_PER_SERIES = 2;

    /** Mindest-Score für Region/Zeitrahmen im strikten Durchlauf. */
    private const ACCEPTABLE_FIT = 50;

    private const STRICT_EXPERIENCE_DISTANCE = 1;

    private const RELAXED_EXPERIENCE_DISTANCE = 2;

    private const STAGE_STRICT = 0;

    private const STAGE_REGION = 1;

    private const STAGE_TIMEFRAME = 2;

    private const STAGE_EXPERIENCE = 3;

    public function __construct(
        private readonly ScoreCalculator $calculator = new ScoreCalculator,
        private readonly ReasonBuilder $reasons = new ReasonBuilder,
    ) {}

    /**
     * @param  iterable<AdventureCandidate>  $candidates
     */
    public function find(WizardAnswers $answers, iterable $candidates): MatchResult
    {
        $scored = $this->scoreEligible($answers, $candidates);
        $stages = $this->stages($answers);

        $notes = [];
        $pool = [];

        foreach ($stages as $index => $stage) {
            $pool = $this->applyStage($scored, $answers, $stage);

            if (count($pool) >= self::MIN_RECOMMENDATIONS || ! isset($stages[$index + 1])) {
                break;
            }

            $notes[] = $this->relaxationNote($stages[$index + 1], count($pool));
        }

        return new MatchResult($this->select($answers, $pool), $notes);
    }

    /**
     * @return list<int>
     */
    private function stages(WizardAnswers $answers): array
    {
        $stages = [self::STAGE_STRICT];
        if ($answers->hasRegionWish()) {
            $stages[] = self::STAGE_REGION;
        }
        $stages[] = self::STAGE_TIMEFRAME;
        $stages[] = self::STAGE_EXPERIENCE;

        return $stages;
    }

    private function relaxationNote(int $stage, int $hits): string
    {
        $reason = match ($hits) {
            0 => 'es keine Treffer gab',
            1 => 'nur 1 Treffer gefunden wurde',
            default => 'nur '.$hits.' Treffer gefunden wurden',
        };

        return match ($stage) {
            self::STAGE_REGION => 'Region wurde ignoriert, weil '.$reason.'.',
            self::STAGE_TIMEFRAME => 'Zeitrahmen wurde ignoriert, weil '.$reason.'.',
            default => 'Erfahrungsstufe wurde auf ±'.self::RELAXED_EXPERIENCE_DISTANCE.' Stufen erweitert, weil '.$reason.'.',
        };
    }

    /**
     * Wendet die unveränderlichen harten Filter an (im Wiki fehlend, Regeln, Gruppengröße)
     * und berechnet die Scores einmalig.
     *
     * @param  iterable<AdventureCandidate>  $candidates
     * @return list<array{0: AdventureCandidate, 1: ScoreBreakdown}>
     */
    private function scoreEligible(WizardAnswers $answers, iterable $candidates): array
    {
        $group = $answers->groupSizeWish();
        $eligible = [];

        foreach ($candidates as $candidate) {
            if ($candidate->missingFromWiki) {
                continue;
            }
            if (! $answers->acceptsRule($candidate->ruleVersion)) {
                continue;
            }
            if ($group !== null && ! $group->isCompatibleWith($candidate->groupSizeMin, $candidate->groupSizeMax)) {
                continue;
            }

            $eligible[] = [$candidate, $this->calculator->score($answers, $candidate)];
        }

        return $eligible;
    }

    /**
     * @param  list<array{0: AdventureCandidate, 1: ScoreBreakdown}>  $scored
     * @return list<array{0: AdventureCandidate, 1: ScoreBreakdown}>
     */
    private function applyStage(array $scored, WizardAnswers $answers, int $stage): array
    {
        $maxDistance = $stage >= self::STAGE_EXPERIENCE
            ? self::RELAXED_EXPERIENCE_DISTANCE
            : self::STRICT_EXPERIENCE_DISTANCE;
        $filterRegion = $stage < self::STAGE_REGION && $answers->hasRegionWish();
        $filterTimeframe = $stage < self::STAGE_TIMEFRAME;

        $pool = [];
        foreach ($scored as $entry) {
            $score = $entry[1];

            if ($score->experienceDistance > $maxDistance) {
                continue;
            }
            if ($filterRegion && ($score->regionKind === RegionMatchKind::Neighbor || $score->region < self::ACCEPTABLE_FIT)) {
                continue;
            }
            if ($filterTimeframe && $score->timeframe < self::ACCEPTABLE_FIT) {
                continue;
            }

            $pool[] = $entry;
        }

        return $pool;
    }

    /**
     * Sortiert nach Score, begrenzt Serien-Dopplungen und baut die Empfehlungen.
     *
     * @param  list<array{0: AdventureCandidate, 1: ScoreBreakdown}>  $pool
     * @return list<MatchRecommendation>
     */
    private function select(WizardAnswers $answers, array $pool): array
    {
        usort($pool, static fn (array $a, array $b): int => ($b[1]->total <=> $a[1]->total)
            ?: ($a[1]->experienceDistance <=> $b[1]->experienceDistance)
            ?: strnatcasecmp($a[0]->title, $b[0]->title)
            ?: ($a[0]->id <=> $b[0]->id));

        $selected = [];
        $overflow = [];
        $perSeries = [];

        foreach ($pool as $entry) {
            $series = $entry[0]->seriesSlug;
            if (count($selected) < self::MAX_RECOMMENDATIONS && ($perSeries[$series] ?? 0) < self::MAX_PER_SERIES) {
                $selected[] = $entry;
                $perSeries[$series] = ($perSeries[$series] ?? 0) + 1;
            } else {
                $overflow[] = $entry;
            }
        }

        // Diversität ist eine Präferenz: bei zu wenigen Treffern mit dem Überhang auffüllen.
        while (count($selected) < self::MIN_RECOMMENDATIONS && $overflow !== []) {
            $selected[] = array_shift($overflow);
        }

        usort($selected, static fn (array $a, array $b): int => $b[1]->total <=> $a[1]->total);

        $recommendations = [];
        foreach ($selected as [$candidate, $score]) {
            $recommendations[] = new MatchRecommendation(
                $candidate,
                $score->total,
                $this->reasons->build($answers, $candidate, $score),
            );
        }

        return $recommendations;
    }
}
