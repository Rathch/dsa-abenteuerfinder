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

    public const MISSING_DESCRIPTION = '-';

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
     *     short_description_raw?: ?string,
     *     cover_url?: ?string
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

        $series = $row['series_key'] !== '' ? $row['series_key'] : 'a-reihe';

        $slug = $this->slugFor($row['title'], $row['catalog_number'], $series);
        $wikiUrl = $this->wikiUrl($row['title'], $row['wiki_path']);

        $description = $this->normalizeDescription($row['short_description_raw'] ?? null);
        $missingDescription = $description === null;
        if ($missingDescription) {
            $notes[] = self::SOURCE_NOTE_DESCRIPTION;
            $description = self::MISSING_DESCRIPTION;
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
            'place_label' => $this->placeLabel($row['place_raw']),
            'regions' => $location['regions'],
            'genres' => $genres,
            'short_description' => $description,
            'wiki_url' => $wikiUrl,
            'cover_url' => is_string($row['cover_url'] ?? null) && $row['cover_url'] !== '' ? $row['cover_url'] : null,
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
        preg_match_all('/\bdsa\s*([1-5])(?:\.1)?\b/u', $normalized, $matches);
        $editions = array_values(array_unique($matches[1]));

        if (count($editions) !== 1) {
            return 'editionunabhaengig';
        }

        return match ($editions[0]) {
            '1' => 'dsa1',
            '2' => 'dsa2',
            '3' => 'dsa3',
            '4' => 'dsa4',
            '5' => 'dsa5',
            default => 'editionunabhaengig',
        };
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
        $slugs = [];
        foreach ($this->mapGenreTags($raw) as $tag) {
            $slugs[] = $tag['slug'];
        }

        return $slugs;
    }

    /**
     * @return list<array{slug: string, name: string}>
     */
    public function mapGenreTags(string $raw): array
    {
        /** @var array<string, string> $found */
        $found = [];

        foreach ($this->genrePhrases($raw) as $phrase) {
            $lower = mb_strtolower($phrase);
            $matched = false;

            foreach ($this->genreAliases['aliases'] as $needle => $slug) {
                if ($needle !== '' && str_contains($lower, $needle)) {
                    $found[$slug] = $this->genreName($slug, $phrase);
                    $matched = true;
                }
            }

            if ($matched) {
                continue;
            }

            $slug = Str::slug($phrase, '-', 'de');
            if ($slug === '' || isset($found[$slug])) {
                continue;
            }

            $found[$slug] = $this->genreName($slug, $phrase);
        }

        if ($found === []) {
            $fallback = $this->genreAliases['fallback'];

            return [['slug' => $fallback, 'name' => $this->genreName($fallback, 'Sonstiges')]];
        }

        $tags = [];
        foreach ($found as $slug => $name) {
            $tags[] = ['slug' => $slug, 'name' => $name];
        }

        return $tags;
    }

    /**
     * @return list<string>
     */
    private function genrePhrases(string $raw): array
    {
        $parts = preg_split('/[,;&\/]| und /u', $raw) ?: [];
        $phrases = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '') {
                $phrases[] = $part;
            }
        }

        $suffixes = ['abenteuer', 'szenario', 'mission', 'queste', 'reise', 'epos'];
        $count = count($phrases);
        for ($index = 0; $index < $count; $index++) {
            if (! $this->isAbbreviatedStem($phrases[$index])) {
                continue;
            }

            $ending = null;
            for ($donor = $index + 1; $donor < $count; $donor++) {
                if ($this->isAbbreviatedStem($phrases[$donor])) {
                    continue;
                }
                $ending = $this->compoundEnding($phrases[$donor], $suffixes);
                break;
            }

            $stem = rtrim($phrases[$index], '-–—');
            $phrases[$index] = $ending === null ? $stem : $stem.$ending;
        }

        $completed = [];
        foreach ($phrases as $phrase) {
            if ($phrase !== '') {
                $completed[] = $phrase;
            }
        }

        return $completed;
    }

    private function isAbbreviatedStem(string $phrase): bool
    {
        return preg_match('/[-–—]$/u', $phrase) === 1;
    }

    /**
     * @param  list<string>  $suffixes
     */
    private function compoundEnding(string $phrase, array $suffixes): ?string
    {
        $lower = mb_strtolower($phrase);
        foreach ($suffixes as $suffix) {
            if (str_ends_with($lower, $suffix)) {
                return mb_substr($phrase, mb_strlen($phrase) - mb_strlen($suffix));
            }
        }

        return null;
    }

    private function genreName(string $slug, string $wikiPhrase): string
    {
        return match ($slug) {
            'investigation' => 'Ermittlung',
            'dungeon' => 'Dungeon',
            'intrigue' => 'Intrige',
            'horror' => 'Horror',
            'humor' => 'Humor',
            'exploration' => 'Erkundung',
            'social' => 'Soziales Spiel',
            'seafaring' => 'Seefahrt',
            'sonstiges' => 'Sonstiges',
            default => $wikiPhrase,
        };
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
        if ($series === 'a-reihe' && preg_match('/\ba\s*(\d+(?:\.\d+)?)/u', $raw, $matches) === 1) {
            return 'a'.str_replace('.', '-', $matches[1]);
        }

        if ($series === 'va-reihe') {
            return 'va';
        }

        if ($series === 'a-reihe') {
            return 'a';
        }

        $suffix = Str::slug($series, language: 'de');

        return $suffix !== '' ? $suffix : 'reihe';
    }

    private function wikiUrl(string $title, ?string $wikiPath): string
    {
        if ($wikiPath !== null && str_starts_with($wikiPath, '/wiki/')) {
            return 'https://de.wiki-aventurica.de'.$wikiPath;
        }

        $page = str_replace(' ', '_', $title);

        return 'https://de.wiki-aventurica.de/wiki/'.rawurlencode($page);
    }

    private function placeLabel(string $raw): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > 1000) {
            return rtrim(mb_substr($text, 0, 999)).'…';
        }

        return $text;
    }

    private function normalizeDescription(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $raw) ?? $raw);
        $text = str_replace("\u{200b}", '', $text);
        // Harvest-Softnotes dürfen nicht in der öffentlichen Kurzbeschreibung landen.
        $text = $this->stripEditorialDescriptionPrefixes($text);
        if (mb_strlen($text) < 20 || $this->isPlaceholderDescription($text)) {
            return null;
        }

        if (mb_strlen($text) > 1200) {
            return rtrim(mb_substr($text, 0, 1199)).'…';
        }

        return $text;
    }

    private function isPlaceholderDescription(string $text): bool
    {
        $lower = mb_strtolower(trim($text));

        return $lower === '-'
            || str_contains($lower, 'beschreibung des abenteuers')
            || str_contains($lower, 'steht noch aus')
            || preg_match('/\/klappentext\s*$/u', $lower) === 1;
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
