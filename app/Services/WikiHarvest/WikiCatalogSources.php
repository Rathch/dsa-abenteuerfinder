<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

/**
 * Wiki-Indexseiten, aus denen der Katalog Abenteuer übernimmt.
 */
final class WikiCatalogSources
{
    /**
     * @return list<array{url: string, series_key: string, series_name: string, split_by_heading: bool, overwrite: bool}>
     */
    public static function all(): array
    {
        return [
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/VA-Reihe', 'va-reihe', 'VA-Reihe'),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/A-Reihe', 'a-reihe', 'A-Reihe'),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/Alveraniars-Abenteuer', 'alveraniars-abenteuer', 'Alveraniars Abenteuer'),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/Boten-Abenteuer', 'boten-abenteuer', 'Boten-Abenteuer'),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/Boxen-Abenteuer', 'boxen-abenteuer', 'Boxen-Abenteuer'),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/HW-Reihe', 'hw-reihe', 'HW-Reihe', true),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/Inoffiziell2', 'inoffiziell', 'Inoffizielle Abenteuer', true),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/Kostenlos', 'kostenlos', 'Kostenlose Abenteuer'),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/Weitere_Reihen', 'weitere-reihen', 'Weitere Reihen', true),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/Wettbewerbe', 'wettbewerbe', 'Wettbewerbe', true),
            self::source('https://de.wiki-aventurica.de/wiki/Abenteuer/chronologisch', 'chronologisch', 'Chronologisch', true, false),
        ];
    }

    /**
     * @return array{url: string, series_key: string, series_name: string, split_by_heading: bool, overwrite: bool}
     */
    private static function source(
        string $url,
        string $seriesKey,
        string $seriesName,
        bool $splitByHeading = false,
        bool $overwrite = true,
    ): array {
        return [
            'url' => $url,
            'series_key' => $seriesKey,
            'series_name' => $seriesName,
            'split_by_heading' => $splitByHeading,
            'overwrite' => $overwrite,
        ];
    }
}
