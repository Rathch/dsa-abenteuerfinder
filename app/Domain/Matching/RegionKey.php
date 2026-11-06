<?php

declare(strict_types=1);

namespace App\Domain\Matching;

/**
 * Bekannte Regionen (Slugs) mit Nachbarschaftsbeziehungen.
 */
enum RegionKey: string
{
    case Nordmarken = 'nordmarken';
    case Mittelaventurien = 'mittelaventurien';
    case Tulamidenlande = 'tulamidenlande';
    case Suedaventurien = 'suedaventurien';
    case Suedwest = 'suedwest';
    case Seefahrt = 'seefahrt';
    case Myranor = 'myranor';

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
            self::Myranor => [],
        };
    }

    public function isNeighborOf(self $other): bool
    {
        return in_array($other, $this->neighbors(), true);
    }

    public function isExtraAventurian(): bool
    {
        return $this === self::Myranor;
    }

    public function label(): string
    {
        return match ($this) {
            self::Nordmarken => 'Nordmarken',
            self::Mittelaventurien => 'Mittelaventurien',
            self::Tulamidenlande => 'Tulamidenlande',
            self::Suedaventurien => 'Südaventurien',
            self::Suedwest => 'Südwest',
            self::Seefahrt => 'Seefahrt',
            self::Myranor => 'Myranor',
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
