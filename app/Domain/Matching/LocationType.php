<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Art der Verortung eines Abenteuers.
 */
enum LocationType: string
{
    case Point = 'point';
    case Corridor = 'corridor';
    case Traveling = 'traveling';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
