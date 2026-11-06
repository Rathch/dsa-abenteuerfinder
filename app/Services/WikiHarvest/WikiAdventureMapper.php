<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

use App\Domain\Matching\DurationCategory;
use Illuminate\Support\Str;

/**
 * Mappt Wiki-Rohzeilen auf adventures.json-Schemazeilen.
 */
final class WikiAdventureMapper
{
    public const SOURCE_NOTE_DESCRIPTION = 'Kurzbeschreibung im Wiki nicht gefunden (Wiki-Harvest).';

    /**
     * @param  array{
     *     places: array<string, array{place: string, region: string}>,
     *     regions: array<string, string>
     * }  $locationAliases
     * @param  array{aliases: array<string, string>, fallback: string}  $genreAliases
     */
    public function __construct(
        private readonly array $locationAliases,
        private readonly array $genreAliases,
    ) {}

    /**
     * @param  array{
     *     catalog_number: string,
     *     title: string,
     *     wiki_path: ?string,
     *     rules_raw: string,
     *     place_raw: string,
     *     genre_raw: string,
     *     complexity_raw: string,
     *     experience_raw: string,
     *     page_count: ?int,
     *     series_key: string,
     *     short_description_raw?: ?string
     * }  $row
     * @return array{
     *     adventure: array<string, mixed>,
     *     unmapped_location: bool,
     *     notes: list<string>
     * }
     */
    public function map(array $row): array
    {
        $notes = [];
        $ruleVersion = $this->mapRules($row['rules_raw']);
        $experience = $this->mapExperience($row['experience_raw']);
        if ($experience['uncertain']) {
            $notes[] = 'Erfahrungsstufe aus Wiki-Text geschätzt.';
        }

        $location = $this->mapLocation($row['place_raw']);
        if ($location['unmapped']) {
            $notes[] = 'Schauplatz konnte nicht sicher gemappt werden.';
        }

        $genres = $this->mapGenres($row['genre_raw']);
        $pageCount = $row['page_count'];
        $duration = $pageCount !== null
            ? DurationCategory::fromPageCount($pageCount)->value
            : 'mittel';

        if ($pageCount === null) {
            $notes[] = 'Seitenzahl fehlt; Dauer geschätzt.';
        }

        $series = match ($row['series_key']) {
            'va-reihe' => 'va-reihe',
            default => 'a-reihe',
        };

        $slug = $this->slugFor($row['title'], $row['catalog_number'], $series);
        $wikiUrl = $this->wikiUrl($row['title'], $row['wiki_path']);

        $description = $this->normalizeDescription($row['short_description_raw'] ?? null);
        $missingDescription = $description === null;
        if ($missingDescription) {
            $notes[] = self::SOURCE_NOTE_DESCRIPTION;
            $description = 'Kurzbeschreibung aus Wiki Aventurica steht noch aus; Metadaten wurden übernommen.';
        }

        // Nur fehlende Beschreibung oder ungemappter Ort → incomplete (Erfahrung/Seiten sind soft).
        $incomplete = $location['unmapped'] || $missingDescription;
        if ($incomplete === false) {
            $notes = [];
        }

        $adventure = [
            'slug' => $slug,
            'title' => $row['title'],
            'series' => $series,
            'experience_level' => $experience['level'],
            'page_count' => $pageCount,
            'duration_category' => $duration,
            'rule_version' => $ruleVersion,
            'exotic' => $location['exotic'],
            'complexity' => $this->mapComplexity($row['complexity_raw']),
            'group_size_min' => null,
            'group_size_max' => null,
            'location_type' => $location['location_type'],
            'primary_location' => $location['primary_location'],
            'primary_region' => $location['primary_region'],
            'regions' => $location['regions'],
            'genres' => $genres,
            'short_description' => $description,
            'wiki_url' => $wikiUrl,
            'incomplete' => $incomplete,
            'missing_from_wiki' => false,
            'source_note' => $incomplete ? implode(' ', array_unique($notes)) : null,
        ];

        return [
            'adventure' => $adventure,
            'unmapped_location' => $location['unmapped'],
            'notes' => $notes,
        ];
    }

    public function mapRules(string $raw): string
    {
        $normalized = mb_strtolower($raw);
        if (preg_match('/\bdsa\s*5\b/u', $normalized) === 1) {
            return 'dsa5';
        }

        return 'dsa4';
    }

    /**
     * @return array{level: string, uncertain: bool}
     */
    public function mapExperience(string $raw): array
    {
        $text = mb_strtolower($raw);
        if ($text === '') {
            return ['level' => 'einsteiger', 'uncertain' => true];
        }

        // Höchste erkennbare Stufe im Text gewinnt (DSA5-Terminologie + klassisch).
        if (str_contains($text, 'legendär') || str_contains($text, 'brilliant') || str_contains($text, 'brillant')
            || str_contains($text, 'meisterlich') || str_contains($text, 'experte')
            || preg_match('/\bmeister\b/u', $text) === 1) {
            return ['level' => 'meister', 'uncertain' => false];
        }

        if (str_contains($text, 'kompetent') || str_contains($text, 'erfahren')) {
            return ['level' => 'erfahren', 'uncertain' => false];
        }

        if (str_contains($text, 'fortgeschritten')) {
            return ['level' => 'fortgeschritten', 'uncertain' => false];
        }

        if (str_contains($text, 'einsteiger') || str_contains($text, 'unerfahren')) {
            return ['level' => 'einsteiger', 'uncertain' => false];
        }

        return ['level' => 'einsteiger', 'uncertain' => true];
    }

    public function mapComplexity(string $raw): string
    {
        $text = mb_strtolower($raw);

        return match (true) {
            str_contains($text, 'hoch') || str_contains($text, 'experte') || str_contains($text, 'sehr') => 'hoch',
            str_contains($text, 'mittel') => 'mittel',
            str_contains($text, 'gering') || str_contains($text, 'niedrig') || str_contains($text, 'einsteiger') => 'niedrig',
            default => 'mittel',
        };
    }

    /**
     * @return list<string>
     */
    public function mapGenres(string $raw): array
    {
        $aliases = $this->genreAliases['aliases'];
        $fallback = $this->genreAliases['fallback'];
        $found = [];

        $chunks = preg_split('/[,;&\/]| und /u', mb_strtolower($raw)) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            foreach ($aliases as $needle => $slug) {
                if (str_contains($chunk, $needle) || str_contains($needle, $chunk)) {
                    $found[$slug] = true;
                }
            }
        }

        if ($found === []) {
            return [$fallback];
        }

        return array_keys($found);
    }

    /**
     * @return array{
     *     location_type: string,
     *     primary_location: ?string,
     *     primary_region: ?string,
     *     regions: list<string>,
     *     exotic: bool,
     *     unmapped: bool
     * }
     */
    public function mapLocation(string $raw): array
    {
        $haystack = mb_strtolower($raw);
        $haystack = strtr($haystack, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        foreach ($this->locationAliases['places'] as $needle => $target) {
            $needleFolded = strtr(mb_strtolower((string) $needle), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
            if ($needleFolded !== '' && str_contains($haystack, $needleFolded)) {
                return [
                    'location_type' => 'point',
                    'primary_location' => $target['place'],
                    'primary_region' => $target['region'],
                    'regions' => [],
                    'exotic' => $target['region'] === 'myranor',
                    'unmapped' => false,
                ];
            }
        }

        $matchedRegions = [];
        foreach ($this->locationAliases['regions'] as $needle => $regionSlug) {
            $needleFolded = strtr(mb_strtolower((string) $needle), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);
            if ($needleFolded !== '' && str_contains($haystack, $needleFolded)) {
                $matchedRegions[$regionSlug] = true;
            }
        }

        $regions = array_keys($matchedRegions);
        if ($regions === ['myranor']) {
            return [
                'location_type' => 'traveling',
                'primary_location' => null,
                'primary_region' => 'myranor',
                'regions' => ['myranor'],
                'exotic' => true,
                'unmapped' => false,
            ];
        }

        $aventurian = array_values(array_filter($regions, static fn (string $slug): bool => $slug !== 'myranor'));
        if ($aventurian !== []) {
            $exotic = in_array('myranor', $regions, true);

            if (count($aventurian) === 1) {
                return [
                    'location_type' => 'traveling',
                    'primary_location' => null,
                    'primary_region' => $aventurian[0],
                    'regions' => $aventurian,
                    'exotic' => $exotic,
                    'unmapped' => false,
                ];
            }

            return [
                'location_type' => 'corridor',
                'primary_location' => null,
                'primary_region' => $aventurian[0],
                'regions' => array_slice($aventurian, 0, 4),
                'exotic' => $exotic,
                'unmapped' => false,
            ];
        }

        return [
            'location_type' => 'traveling',
            'primary_location' => null,
            'primary_region' => 'mittelaventurien',
            'regions' => ['mittelaventurien'],
            'exotic' => false,
            'unmapped' => true,
        ];
    }

    public function slugFor(string $title, string $catalogNumber, string $series): string
    {
        $base = Str::slug($title, language: 'de');
        if ($base === '') {
            $base = 'abenteuer';
        }

        $suffix = $this->catalogSuffix($catalogNumber, $series);
        $slug = $base;
        if ($suffix !== '') {
            $slug = $base.'-'.$suffix;
        }

        return mb_substr($slug, 0, 120);
    }

    private function catalogSuffix(string $catalogNumber, string $series): string
    {
        $raw = mb_strtolower($catalogNumber);
        if (preg_match('/va\s*(\d+(?:\.\d+)?)/u', $raw, $matches) === 1) {
            return 'va'.str_replace('.', '-', $matches[1]);
        }
        if (preg_match('/\ba\s*(\d+(?:\.\d+)?)/u', $raw, $matches) === 1) {
            return 'a'.str_replace('.', '-', $matches[1]);
        }

        return $series === 'va-reihe' ? 'va' : 'a';
    }

    private function wikiUrl(string $title, ?string $wikiPath): string
    {
        if ($wikiPath !== null && str_starts_with($wikiPath, '/wiki/')) {
            return 'https://de.wiki-aventurica.de'.$wikiPath;
        }

        $page = str_replace(' ', '_', $title);

        return 'https://de.wiki-aventurica.de/wiki/'.rawurlencode($page);
    }

    private function normalizeDescription(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        // Harvest-Softnotes dürfen nicht in der öffentlichen Kurzbeschreibung landen.
        $text = $this->stripEditorialDescriptionPrefixes($text);
        if (mb_strlen($text) < 20) {
            return null;
        }

        if (mb_strlen($text) > 1200) {
            return rtrim(mb_substr($text, 0, 1199)).'…';
        }

        return $text;
    }

    private function stripEditorialDescriptionPrefixes(string $text): string
    {
        do {
            $previous = $text;
            $text = trim(preg_replace('/^>\s*(?:Seitenzahl fehlt|Auflage klären)\s*/u', '', $text) ?? $text);
        } while ($text !== $previous);

        return $text;
    }
}
