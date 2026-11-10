<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Bekannte Regionen (Slugs) mit Nachbarschaftsbeziehungen.
 */
enum RegionKey: string
{
    case Nordmarken = 'nordmarken';
    case HoherNorden = 'hoher-norden';
    case Nordaventurien = 'nordaventurien';
    case Thorwal = 'thorwal';
    case Bornland = 'bornland';
    case Orkland = 'orkland';
    case AndergastUndNostria = 'andergast-und-nostria';
    case Mittelaventurien = 'mittelaventurien';
    case SchattenlandeTobrien = 'schattenlande-tobrien';
    case Tulamidenlande = 'tulamidenlande';
    case Echsensuempf = 'echsensuempf';
    case Charyptik = 'charyptik';
    case Aranien = 'aranien';
    case Meridiana = 'meridiana';
    case Suedaventurien = 'suedaventurien';
    case Suedwest = 'suedwest';
    case Seefahrt = 'seefahrt';
    case Tiefenreich = 'tiefenreich';
    case Myranor = 'myranor';
    case Uthuria = 'uthuria';
    case Tharun = 'tharun';
    case Rakshazar = 'rakshazar';
    case AufReisen = 'auf-reisen';

    /**
     * @return list<self>
     */
    public function neighbors(): array
    {
        return match ($this) {
            self::Nordmarken => [self::Mittelaventurien, self::Seefahrt],
            self::Mittelaventurien => [self::Nordmarken, self::Tulamidenlande, self::Seefahrt],
            self::Tulamidenlande => [self::Mittelaventurien, self::Suedaventurien, self::Seefahrt],
            self::Suedaventurien => [self::Tulamidenlande, self::Suedwest, self::Seefahrt],
            self::Suedwest => [self::Suedaventurien, self::Seefahrt],
            self::Seefahrt => [
                self::Nordmarken,
                self::Mittelaventurien,
                self::Tulamidenlande,
                self::Suedaventurien,
                self::Suedwest,
            ],
            self::HoherNorden, self::Nordaventurien, self::Thorwal, self::Bornland, self::Orkland, self::AndergastUndNostria, self::SchattenlandeTobrien, self::Echsensuempf, self::Charyptik, self::Aranien, self::Meridiana, self::Tiefenreich, self::Myranor, self::Uthuria, self::Tharun, self::Rakshazar, self::AufReisen => [],
        };
    }

    public function isNeighborOf(self $other): bool
    {
        return in_array($other, $this->neighbors(), true);
    }

    public function isExtraAventurian(): bool
    {
        return in_array($this, [self::Myranor, self::Uthuria, self::Tharun, self::Rakshazar, self::Tiefenreich], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Nordmarken => 'Nordmarken',
            self::HoherNorden => 'Hoher Norden',
            self::Nordaventurien => 'Nordaventurien',
            self::Thorwal => 'Thorwal',
            self::Bornland => 'Bornland',
            self::Orkland => 'Orkland',
            self::AndergastUndNostria => 'Andergast und Nostria',
            self::Mittelaventurien => 'Mittelaventurien',
            self::SchattenlandeTobrien => 'Schattenlande / Tobrien',
            self::Tulamidenlande => 'Tulamidenlande',
            self::Echsensuempf => 'Echsensümpfe',
            self::Charyptik => 'Charyptik',
            self::Aranien => 'Aranien',
            self::Meridiana => 'Meridiana',
            self::Suedaventurien => 'Südaventurien',
            self::Suedwest => 'Südwest',
            self::Seefahrt => 'Seefahrt',
            self::Tiefenreich => 'Tiefenreich',
            self::Myranor => 'Myranor',
            self::Uthuria => 'Uthuria',
            self::Tharun => 'Tharun',
            self::Rakshazar => 'Rakshazar',
            self::AufReisen => 'Auf Reisen',
        };
    }

    /**
     * Anzeigename für einen beliebigen Slug (auch unbekannte Regionen).
     */
    public static function labelFor(string $slug): string
    {
        $key = self::tryFrom($slug);

        return $key !== null ? $key->label() : ucfirst(str_replace('-', ' ', $slug));
    }
}
