<?php

declare(strict_types=1);

namespace App\Domain\Matching;

enum ExperienceLevel: string
{
    case Einsteiger = 'einsteiger';
    case Fortgeschritten = 'fortgeschritten';
    case Erfahren = 'erfahren';
    case Meister = 'meister';

    /**
     * Ordinal position (0 = niedrigste Stufe).
     */
    public function rank(): int
    {
        return match ($this) {
            self::Einsteiger => 0,
            self::Fortgeschritten => 1,
            self::Erfahren => 2,
            self::Meister => 3,
        };
    }

    public function distanceTo(self $other): int
    {
        return abs($this->rank() - $other->rank());
    }

    public function label(): string
    {
        return match ($this) {
            self::Einsteiger => 'Einsteiger',
            self::Fortgeschritten => 'Fortgeschrittene',
            self::Erfahren => 'Erfahrene',
            self::Meister => 'Meister',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
