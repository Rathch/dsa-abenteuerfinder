<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Zeitrahmen-Wunsch aus dem Wizard.
 */
enum DurationWish: string
{
    case EveningShort = 'evening_short';
    case EveningLong = 'evening_long';
    case Multi = 'multi';
    case Campaign = 'campaign';

    /**
     * Passung (0–100) der gewünschten Dauer zur Dauerkategorie eines Abenteuers.
     */
    public function fitFor(DurationCategory $category): int
    {
        $matrix = match ($this) {
            self::EveningShort => [100, 55, 10, 0],
            self::EveningLong => [70, 100, 50, 10],
            self::Multi => [30, 70, 100, 60],
            self::Campaign => [0, 30, 70, 100],
        };

        return $matrix[match ($category) {
            DurationCategory::Kurz => 0,
            DurationCategory::Mittel => 1,
            DurationCategory::Lang => 2,
            DurationCategory::Kampagne => 3,
        }];
    }

    public function label(): string
    {
        return match ($this) {
            self::EveningShort => 'einen kurzen Abend',
            self::EveningLong => 'einen langen Abend',
            self::Multi => 'mehrere Sitzungen',
            self::Campaign => 'eine Kampagne',
        };
    }
}
