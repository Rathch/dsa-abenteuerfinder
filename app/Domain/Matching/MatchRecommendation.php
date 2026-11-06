<?php

declare(strict_types=1);

namespace App\Domain\Matching;

final readonly class MatchRecommendation
{
    /**
     * @param  list<string>  $reasons
     */
    public function __construct(
        public AdventureCandidate $adventure,
        public int $score,
        public array $reasons,
    ) {}
}
