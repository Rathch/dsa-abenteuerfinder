<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Art der Passung zwischen Wunschregion/-ort und Abenteuer.
 */
enum RegionMatchKind: string
{
    case Any = 'any';
    case Place = 'place';
    case Region = 'region';
    case Traveling = 'traveling';
    case Neighbor = 'neighbor';
    case None = 'none';
}
