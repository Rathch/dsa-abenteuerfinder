<?php

declare(strict_types=1);

namespace App\Domain\Matching;

final readonly class MatchResult
{
    /**
     * @param  list<MatchRecommendation>  $recommendations
     * @param  list<string>  $fallbackNotes  transparente Hinweise, welche Wünsche gelockert wurden
     */
    public function __construct(
        public array $recommendations,
        public array $fallbackNotes = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->recommendations === [];
    }

    public function usedFallback(): bool
    {
        return $this->fallbackNotes !== [];
    }
}
