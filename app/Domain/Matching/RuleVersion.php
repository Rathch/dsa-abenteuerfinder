<?php

declare(strict_types=1);

namespace App\Domain\Matching;

enum RuleVersion: string
{
    case Dsa1 = 'dsa1';
    case Dsa2 = 'dsa2';
    case Dsa3 = 'dsa3';
    case Dsa4 = 'dsa4';
    case Dsa5 = 'dsa5';
    case EditionUnabhaengig = 'editionunabhaengig';

    public function label(): string
    {
        return match ($this) {
            self::Dsa1 => 'DSA 1',
            self::Dsa2 => 'DSA 2',
            self::Dsa3 => 'DSA 3',
            self::Dsa4 => 'DSA 4',
            self::Dsa5 => 'DSA 5',
            self::EditionUnabhaengig => 'editionsunabhängig',
        };
    }

    public function editionNumber(): ?int
    {
        return match ($this) {
            self::Dsa1 => 1,
            self::Dsa2 => 2,
            self::Dsa3 => 3,
            self::Dsa4 => 4,
            self::Dsa5 => 5,
            self::EditionUnabhaengig => null,
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
