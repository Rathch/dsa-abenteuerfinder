<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Erzeugt deutsche, spoilerfreie Begründungen, die sich auf die tatsächlichen Wizard-Antworten beziehen.
 */
final class ReasonBuilder
{
    private const GENRE_LABELS = [
        'investigation' => 'Ermittlung',
        'dungeon' => 'Dungeon',
        'intrigue' => 'Intrige',
        'horror' => 'Horror',
        'humor' => 'Humor',
        'exploration' => 'Erkundung',
        'social' => 'Soziales Spiel',
        'seafaring' => 'Seefahrt',
    ];

    /**
     * @return list<string>
     */
    public function build(WizardAnswers $answers, AdventureCandidate $candidate, ScoreBreakdown $score): array
    {
        // Die Erfahrungsstufen-Begründung ist immer vorhanden, die Liste ist somit nie leer.
        $reasons = [$this->experienceReason($answers, $candidate, $score)];

        foreach ([
            $this->regionReason($answers, $candidate, $score),
            $this->genreReason($answers, $candidate),
            $this->timeframeReason($answers, $candidate, $score),
            $this->rulesReason($answers, $candidate),
            $this->groupReason($answers, $candidate),
            $this->exoticReason($answers, $candidate, $score),
            $this->gmReason($answers, $candidate, $score),
        ] as $reason) {
            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    public function genreLabel(string $slug): string
    {
        return self::GENRE_LABELS[$slug] ?? ucfirst($slug);
    }

    private function regionReason(WizardAnswers $answers, AdventureCandidate $candidate, ScoreBreakdown $score): ?string
    {
        $wantedRegion = count($answers->regionSlugs) === 1
            ? RegionKey::labelFor($answers->regionSlugs[0])
            : null;

        return match ($score->regionKind) {
            RegionMatchKind::Place => 'Spielt in '.($candidate->primaryPlaceName ?? 'deinem Wunschort').' – genau der Ort, den du dir gewünscht hast.',
            RegionMatchKind::Region => 'Passt zur Region '.($wantedRegion ?? 'deiner Wahl').'.',
            RegionMatchKind::Traveling => 'Reiseabenteuer, das sich flexibel in '.($wantedRegion !== null ? 'die Region '.$wantedRegion : 'deine Wunschregion').' einbinden lässt.',
            RegionMatchKind::Neighbor => 'Spielt in '.RegionKey::labelFor((string) $score->matchedRegionSlug).', direkt neben deiner Wunschregion '.($wantedRegion ?? '').'.',
            RegionMatchKind::Any, RegionMatchKind::None => null,
        };
    }

    private function experienceReason(WizardAnswers $answers, AdventureCandidate $candidate, ScoreBreakdown $score): string
    {
        $wanted = $answers->experience();

        if ($score->experienceDistance === 0) {
            return 'Geeignet für '.$wanted->label().'.';
        }

        return 'Richtet sich an '.$candidate->experienceLevel->label().' und liegt damit nahe an deiner Wunschstufe ('.$wanted->label().').';
    }

    private function genreReason(WizardAnswers $answers, AdventureCandidate $candidate): ?string
    {
        $common = array_values(array_intersect(array_unique($answers->genres), $candidate->genres));
        if ($common === []) {
            return null;
        }

        $labels = array_map($this->genreLabel(...), $common);

        return 'Deckt '.(count($labels) === 1 ? 'das Genre ' : 'die Genres ').self::joinGerman($labels).' ab.';
    }

    private function timeframeReason(WizardAnswers $answers, AdventureCandidate $candidate, ScoreBreakdown $score): ?string
    {
        if ($score->timeframe < 50 || ! $answers->restrictsDuration()) {
            return null;
        }

        $label = $this->bestDurationLabel($answers, $candidate);
        if ($label === null) {
            return null;
        }

        return 'Dauer passt zu '.$label.' ('.$candidate->durationCategory->label().').';
    }

    private function bestDurationLabel(WizardAnswers $answers, AdventureCandidate $candidate): ?string
    {
        $best = null;
        $bestFit = -1;
        foreach ($answers->durationWishes() as $wish) {
            $fit = $wish->fitFor($candidate->durationCategory);
            if ($fit > $bestFit) {
                $bestFit = $fit;
                $best = $wish;
            }
        }

        return $best?->label();
    }

    private function rulesReason(WizardAnswers $answers, AdventureCandidate $candidate): ?string
    {
        if ($answers->rules === []) {
            return null;
        }

        $labels = [];
        foreach ($answers->rules as $value) {
            $version = RuleVersion::tryFrom($value);
            if ($version !== null) {
                $labels[] = $version->label();
            }
        }
        if ($labels === []) {
            return null;
        }

        return 'Verfügbar für '.self::joinGerman($labels).' ('.$candidate->ruleVersion->label().').';
    }

    private function groupReason(WizardAnswers $answers, AdventureCandidate $candidate): ?string
    {
        $wish = $answers->groupSizeWish();
        if ($wish === null || ! $wish->isExactlyCoveredBy($candidate->groupSizeMin, $candidate->groupSizeMax)) {
            return null;
        }

        if ($candidate->groupSizeMin === null && $candidate->groupSizeMax === null) {
            return null;
        }

        return 'Geeignet für '.$wish->label().'.';
    }

    private function exoticReason(WizardAnswers $answers, AdventureCandidate $candidate, ScoreBreakdown $score): ?string
    {
        if (! $answers->restrictsExotic()) {
            return null;
        }

        foreach ($answers->exoticWishes() as $wish) {
            $reason = match ($wish) {
                ExoticWish::No => ($candidate->exotic || $candidate->isExtraAventurian()) ? null : 'Bleibt bei klassisch aventurischen Schauplätzen und Kulturen.',
                ExoticWish::ExtraAventurian => $score->exotic >= 60 ? 'Führt dich abseits des gewohnten Aventurien.' : null,
                ExoticWish::UnusualCultures => $candidate->exotic ? 'Stellt ungewöhnliche Kulturen in den Mittelpunkt.' : null,
                ExoticWish::Any => null,
            };
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    private function gmReason(WizardAnswers $answers, AdventureCandidate $candidate, ScoreBreakdown $score): ?string
    {
        if ($score->gm < 80) {
            return null;
        }

        return 'Komplexität „'.$candidate->complexity->value.'“ passt zu deiner Spielleitungserfahrung ('.$answers->gmLevel()->label().').';
    }

    /**
     * @param  list<string>  $items
     */
    private static function joinGerman(array $items): string
    {
        if (count($items) <= 1) {
            return $items[0] ?? '';
        }

        $last = array_pop($items);

        return implode(', ', $items).' und '.$last;
    }
}
