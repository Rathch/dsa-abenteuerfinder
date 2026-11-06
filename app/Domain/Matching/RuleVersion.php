<?php

declare(strict_types=1);

namespace App\Domain\Matching;

enum RuleVersion: string
{
    case Dsa4 = 'dsa4';
    case Dsa5 = 'dsa5';
    case EditionUnabhaengig = 'editionunabhaengig';

    public function label(): string
    {
        return match ($this) {
            self::Dsa4 => 'DSA 4',
            self::Dsa5 => 'DSA 5',
            self::EditionUnabhaengig => 'editionsunabhängig',
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
