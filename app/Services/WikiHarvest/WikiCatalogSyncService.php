<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

use RuntimeException;

/**
 * Abgleich Wiki-Index (VA-/A-Reihe) mit adventures.json:
 * neue Einträge hinzufügen, fehlende als missing_from_wiki markieren.
 */
final class WikiCatalogSyncService
{
    public const SOURCE_NOTE_MISSING = 'Im Wiki-Index nicht mehr gelistet.';

    public function __construct(
        private readonly string $dataPath,
        private readonly WikiAdventureMapper $mapper,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rawRows
     * @param  array<string, string>  $covers  Wiki-Pfad => Bild-URL
     * @return array{added: int, marked_missing: int, restored: int, skipped: int, unmapped: list<string>}
     */
    public function sync(array $rawRows, array $covers = []): array
    {
        $adventuresPath = $this->dataPath.'/adventures.json';
        $seriesPath = $this->dataPath.'/series.json';
        $tagsPath = $this->dataPath.'/tags.json';

        $adventuresDoc = $this->readJson($adventuresPath);
        $seriesDoc = $this->readJson($seriesPath);
        $tagsDoc = $this->readJson($tagsPath);

        $this->ensureSeries($seriesDoc, 'va-reihe', 'VA-Reihe');
        $this->ensureSeries($seriesDoc, 'a-reihe', 'A-Reihe');
        $this->ensureTag($tagsDoc, 'sonstiges', 'Sonstiges');

        /** @var array<string, array<string, mixed>> $bySlug */
        $bySlug = [];
        foreach ($adventuresDoc['adventures'] ?? [] as $adventure) {
            if (is_array($adventure) && isset($adventure['slug']) && is_string($adventure['slug'])) {
                $bySlug[$adventure['slug']] = $adventure;
            }
        }

        $wikiPaths = [];
        $wikiTitles = [];
        $wikiSlugs = [];
        $mappedRows = [];
        $unmapped = [];
        $skipped = 0;

        foreach ($rawRows as $row) {
            if (! isset($row['title']) || ! is_string($row['title']) || trim($row['title']) === '') {
                continue;
            }

            $descriptionRaw = $row['short_description_raw'] ?? null;
            $descriptionRaw = is_string($descriptionRaw) && trim($descriptionRaw) !== '' ? $descriptionRaw : null;
            $seriesKey = (string) ($row['series_key'] ?? 'a-reihe');
            $seriesName = (string) ($row['series_name'] ?? $seriesKey);
            $this->ensureSeries($seriesDoc, $seriesKey, $seriesName !== '' ? $seriesName : $seriesKey);

            $wikiPath = isset($row['wiki_path']) && is_string($row['wiki_path']) ? $row['wiki_path'] : null;
            $coverUrl = $this->coverFor($wikiPath, $covers);
            $hasGenreColumn = ($row['has_genre_column'] ?? false) === true;

            $typed = [
                'catalog_number' => (string) ($row['catalog_number'] ?? ''),
                'title' => $row['title'],
                'wiki_path' => $wikiPath,
                'rules_raw' => (string) ($row['rules_raw'] ?? ''),
                'place_raw' => (string) ($row['place_raw'] ?? ''),
                'genre_raw' => (string) ($row['genre_raw'] ?? ''),
                'complexity_raw' => (string) ($row['complexity_raw'] ?? ''),
                'experience_raw' => (string) ($row['experience_raw'] ?? ''),
                'page_count' => isset($row['page_count']) && is_int($row['page_count']) ? $row['page_count'] : null,
                'series_key' => $seriesKey,
                'short_description_raw' => $descriptionRaw,
                'cover_url' => $coverUrl,
            ];

            $mapped = $this->mapper->map($typed);
            $adventure = $mapped['adventure'];
            $slug = (string) $adventure['slug'];
            $pathKey = $this->normalizeWikiPath(
                is_string($typed['wiki_path']) ? $typed['wiki_path'] : null,
                $typed['title'],
            );
            $titleKey = mb_strtolower($typed['title']);

            $wikiPaths[$pathKey] = true;
            $wikiTitles[$titleKey] = true;
            $wikiSlugs[$slug] = true;
            $mappedRows[] = [
                'typed' => $typed,
                'mapped' => $mapped,
                'path' => $pathKey,
                'overwrite' => ($row['overwrite'] ?? true) !== false,
                'has_genre_column' => $hasGenreColumn,
            ];

            if ($mapped['unmapped_location']) {
                $unmapped[] = $typed['title'].' | '.$typed['place_raw'];
            }
        }

        $added = 0;
        $restored = 0;

        foreach ($mappedRows as $entry) {
            /** @var array{adventure: array<string, mixed>, unmapped_location: bool, notes: list<string>} $mapped */
            $mapped = $entry['mapped'];
            $adventure = $mapped['adventure'];
            $slug = (string) $adventure['slug'];
            $titleKey = mb_strtolower((string) $adventure['title']);

            $hasGenreColumn = $entry['has_genre_column'] === true;
            $genreSlugs = $hasGenreColumn
                ? $this->storeGenreSlugs($tagsDoc, $entry['typed']['genre_raw'])
                : null;

            $existingSlug = $this->findExistingSlug($bySlug, $slug, $titleKey, $entry['path']);
            if ($existingSlug !== null) {
                $existing = $bySlug[$existingSlug];
                $wasMissing = (bool) ($existing['missing_from_wiki'] ?? false);

                if ($wasMissing) {
                    $existing['missing_from_wiki'] = false;
                    if (($existing['source_note'] ?? null) === self::SOURCE_NOTE_MISSING) {
                        $existing['source_note'] = null;
                    }
                    $restored++;
                }

                $cover = $adventure['cover_url'] ?? null;
                if (is_string($cover) && $cover !== '' && ($existing['cover_url'] ?? null) === null) {
                    $existing['cover_url'] = $cover;
                }

                // Chronologische Übersichten ergänzen nur, sie ersetzen keine bestehenden Zeilen.
                if ($entry['overwrite'] === false) {
                    if ($genreSlugs !== null) {
                        $existing['genres'] = $genreSlugs;
                    }
                    $bySlug[$existingSlug] = $existing;
                    $skipped++;

                    continue;
                }

                // Nur unvollständige Harvest-Stubs aktualisieren; kuratierte Complete bleiben.
                if (($existing['incomplete'] ?? true) === true) {
                    $adventure['missing_from_wiki'] = false;
                    if ($existing['cover_url'] ?? null) {
                        $adventure['cover_url'] = $existing['cover_url'];
                    }
                    $adventure['genres'] = $genreSlugs ?? $this->keptGenres($existing);
                    $bySlug[$existingSlug] = $adventure;
                } else {
                    if ($genreSlugs !== null) {
                        $existing['genres'] = $genreSlugs;
                    }
                    $bySlug[$existingSlug] = $existing;
                    $skipped++;
                }

                continue;
            }

            $adventure['missing_from_wiki'] = false;
            $adventure['genres'] = $genreSlugs ?? $this->storeGenreSlugs($tagsDoc, '');
            $bySlug[$slug] = $adventure;
            $added++;
        }

        $trackedSeries = ['va-reihe' => true, 'a-reihe' => true];
        foreach ($rawRows as $row) {
            if (isset($row['series_key']) && is_string($row['series_key']) && $row['series_key'] !== '') {
                $trackedSeries[$row['series_key']] = true;
            }
        }

        $markedMissing = 0;
        foreach ($bySlug as $slug => $adventure) {
            $series = (string) ($adventure['series'] ?? '');
            if (! isset($trackedSeries[$series])) {
                continue;
            }

            $pathKey = $this->normalizeWikiPath(null, (string) ($adventure['title'] ?? ''), (string) ($adventure['wiki_url'] ?? ''));
            $titleKey = mb_strtolower((string) ($adventure['title'] ?? ''));
            $stillListed = isset($wikiSlugs[$slug])
                || isset($wikiTitles[$titleKey])
                || isset($wikiPaths[$pathKey]);

            if ($stillListed) {
                continue;
            }

            if (($adventure['missing_from_wiki'] ?? false) === true) {
                continue;
            }

            $adventure['missing_from_wiki'] = true;
            $note = (string) ($adventure['source_note'] ?? '');
            if ($note === '' || $note === self::SOURCE_NOTE_MISSING) {
                $adventure['source_note'] = self::SOURCE_NOTE_MISSING;
            }
            $bySlug[$slug] = $adventure;
            $markedMissing++;
        }

        $adventuresDoc['adventures'] = array_values($bySlug);
        $this->writeJson($adventuresPath, $adventuresDoc);
        $this->writeJson($seriesPath, $seriesDoc);
        $this->writeJson($tagsPath, $tagsDoc);

        return [
            'added' => $added,
            'marked_missing' => $markedMissing,
            'restored' => $restored,
            'skipped' => $skipped,
            'unmapped' => array_values(array_unique($unmapped)),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $bySlug
     */
    private function findExistingSlug(array $bySlug, string $slug, string $titleKey, string $pathKey): ?string
    {
        if (isset($bySlug[$slug])) {
            return $slug;
        }

        foreach ($bySlug as $existingSlug => $existing) {
            if (mb_strtolower((string) ($existing['title'] ?? '')) === $titleKey) {
                return $existingSlug;
            }

            $existingPath = $this->normalizeWikiPath(null, (string) ($existing['title'] ?? ''), (string) ($existing['wiki_url'] ?? ''));
            if ($existingPath === $pathKey) {
                return $existingSlug;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $covers
     */
    private function coverFor(?string $wikiPath, array $covers): ?string
    {
        if ($wikiPath === null || $wikiPath === '') {
            return null;
        }

        $decoded = rawurldecode($wikiPath);

        return $covers[$decoded] ?? $covers[$wikiPath] ?? null;
    }

    private function normalizeWikiPath(?string $wikiPath, string $title, string $wikiUrl = ''): string
    {
        if (is_string($wikiPath) && str_starts_with($wikiPath, '/wiki/')) {
            return rawurldecode($wikiPath);
        }

        if ($wikiUrl !== '' && preg_match('~/wiki/([^?#]+)~', $wikiUrl, $matches) === 1) {
            return '/wiki/'.rawurldecode(str_replace(' ', '_', $matches[1]));
        }

        return '/wiki/'.str_replace(' ', '_', $title);
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Kann Datei nicht lesen: '.$path);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Ungültiges JSON: '.$path);
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeJson(string $path, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('JSON-Encode fehlgeschlagen: '.$path);
        }

        file_put_contents($path, $json."\n");
    }

    /**
     * @param  array<string, mixed>  $seriesDoc
     */
    private function ensureSeries(array &$seriesDoc, string $slug, string $name): void
    {
        $series = $seriesDoc['series'] ?? [];
        if (! is_array($series)) {
            $series = [];
        }

        foreach ($series as $entry) {
            if (is_array($entry) && ($entry['slug'] ?? null) === $slug) {
                $seriesDoc['series'] = $series;

                return;
            }
        }

        $series[] = ['slug' => $slug, 'name' => $name];
        $seriesDoc['series'] = $series;
    }

    /**
     * @param  array<string, mixed>  $tagsDoc
     * @return list<string>
     */
    private function storeGenreSlugs(array &$tagsDoc, string $genreRaw): array
    {
        $slugs = [];
        foreach ($this->mapper->mapGenreTags($genreRaw) as $tag) {
            $this->ensureTag($tagsDoc, $tag['slug'], $tag['name']);
            $slugs[] = $tag['slug'];
        }

        return $slugs;
    }

    /**
     * @param  array<string, mixed>  $existing
     * @return list<string>
     */
    private function keptGenres(array $existing): array
    {
        $genres = $existing['genres'] ?? null;
        if (! is_array($genres) || $genres === []) {
            return ['sonstiges'];
        }

        $slugs = [];
        foreach ($genres as $genre) {
            if (is_string($genre) && $genre !== '') {
                $slugs[] = $genre;
            }
        }

        return $slugs === [] ? ['sonstiges'] : $slugs;
    }

    /**
     * @param  array<string, mixed>  $tagsDoc
     */
    private function ensureTag(array &$tagsDoc, string $slug, string $name): void
    {
        $tags = $tagsDoc['tags'] ?? [];
        if (! is_array($tags)) {
            $tags = [];
        }

        foreach ($tags as $entry) {
            if (is_array($entry) && ($entry['slug'] ?? null) === $slug) {
                $tagsDoc['tags'] = $tags;

                return;
            }
        }

        $tags[] = ['slug' => $slug, 'name' => $name];
        $tagsDoc['tags'] = $tags;
    }
}
