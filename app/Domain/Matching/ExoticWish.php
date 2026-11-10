<?php

declare(strict_types=1);

namespace App\Domain\Matching;

enum ExoticWish: string
{
    case No = 'no';
    case ExtraAventurian = 'extra_aventurian';
    case UnusualCultures = 'unusual_cultures';
    case Any = 'any';

    /**
     * Passung (0–100) zwischen Wunsch und Abenteuer.
     */
    public function fitFor(bool $exotic, bool $extraAventurian): int
    {
        return match ($this) {
            self::Any => 100,
            self::No => ($exotic || $extraAventurian) ? 0 : 100,
            self::ExtraAventurian => match (true) {
                $extraAventurian => 100,
                $exotic => 60,
                default => 20,
            },
            self::UnusualCultures => $exotic ? 100 : 30,
        };
    }
}
