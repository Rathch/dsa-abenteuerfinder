<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Gruppengrößen-Band ("2", "3-5", "6+"). Kein Wunsch (egal) wird durch null abgebildet.
 */
enum GroupSizeWish: string
{
    case Two = '2';
    case ThreeToFive = '3-5';
    case SixPlus = '6+';

    public function band(): int
    {
        return match ($this) {
            self::Two => 0,
            self::ThreeToFive => 1,
            self::SixPlus => 2,
        };
    }

    /**
     * Prüft, ob ein Abenteuer mit Spielerzahl-Spanne [min, max] dieses Band (±1 Band Toleranz) abdeckt.
     * Fehlen beide Grenzen, wird nicht gefiltert.
     */
    public function isCompatibleWith(?int $min, ?int $max): bool
    {
        if ($min === null && $max === null) {
            return true;
        }

        $low = $min ?? 1;
        $high = $max ?? PHP_INT_MAX;

        foreach ([[2, 2], [3, 5], [6, PHP_INT_MAX]] as $band => [$bandLow, $bandHigh]) {
            $overlaps = $low <= $bandHigh && $high >= $bandLow;
            if ($overlaps && abs($band - $this->band()) <= 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Passt das Band ohne Toleranz zur Spanne?
     */
    public function isExactlyCoveredBy(?int $min, ?int $max): bool
    {
        $low = $min ?? 1;
        $high = $max ?? PHP_INT_MAX;

        [$bandLow, $bandHigh] = match ($this) {
            self::Two => [2, 2],
            self::ThreeToFive => [3, 5],
            self::SixPlus => [6, PHP_INT_MAX],
        };

        return $low <= $bandHigh && $high >= $bandLow;
    }

    public function label(): string
    {
        return match ($this) {
            self::Two => '2 Spielende',
            self::ThreeToFive => '3 bis 5 Spielende',
            self::SixPlus => '6 oder mehr Spielende',
        };
    }
}
