<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Hierarchie-Ebene eines Ortes im Ortsbaum.
 */
enum LocationKind: string
{
    case Continent = 'continent';
    case Region = 'region';
    case Place = 'place';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
