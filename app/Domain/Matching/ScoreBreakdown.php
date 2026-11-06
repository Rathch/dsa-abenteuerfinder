<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Einzelwertungen (je 0–100) eines Abenteuers für eine Wizard-Antwort.
 */
final readonly class ScoreBreakdown
{
    public function __construct(
        public int $region,
        public RegionMatchKind $regionKind,
        public ?string $matchedRegionSlug,
        public int $timeframe,
        public int $genre,
        public int $exotic,
        public int $gm,
        public int $experience,
        public int $experienceDistance,
        public int $total,
    ) {}
}
