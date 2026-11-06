<?php

declare(strict_types=1);

namespace App\Domain\Matching;

enum Complexity: string
{
    case Niedrig = 'niedrig';
    case Mittel = 'mittel';
    case Hoch = 'hoch';

    public function rank(): int
    {
        return match ($this) {
            self::Niedrig => 0,
            self::Mittel => 1,
            self::Hoch => 2,
        };
    }

    /**
     * Komplexität, die für eine Spielleitung der gegebenen Erfahrung ideal ist.
     */
    public static function idealFor(ExperienceLevel $gm): self
    {
        return match ($gm) {
            ExperienceLevel::Einsteiger => self::Niedrig,
            ExperienceLevel::Fortgeschritten => self::Mittel,
            ExperienceLevel::Erfahren, ExperienceLevel::Meister => self::Hoch,
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
