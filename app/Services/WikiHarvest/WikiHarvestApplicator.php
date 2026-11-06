<?php

declare(strict_types=1);

namespace App\Services\WikiHarvest;

use RuntimeException;

/**
 * Merged Wiki-Harvest in die JSON-Quellen: kuratierte complete-Einträge bleiben erhalten.
 */
final class WikiHarvestApplicator
{
    public function __construct(
        private readonly string $dataPath,
        private readonly WikiAdventureMapper $mapper,
    ) {}

    /**
     * Entfernt alle Abenteuer der genannten Serien (typisch: vorherige Wiki-Harvest-Stubs).
     *
     * @param  list<string>  $seriesSlugs
     */
    public function purgeHarvestedSeries(array $seriesSlugs): int
    {
        $adventuresPath = $this->dataPath.'/adventures.json';
        $adventuresDoc = $this->readJson($adventuresPath);
        $before = is_array($adventuresDoc['adventures'] ?? null) ? $adventuresDoc['adventures'] : [];
        $kept = [];
        $removed = 0;

        foreach ($before as $adventure) {
            if (! is_array($adventure)) {
                continue;
            }
            $series = (string) ($adventure['series'] ?? '');
            if (in_array($series, $seriesSlugs, true)) {
                $removed++;

                continue;
            }
            $kept[] = $adventure;
        }

        $adventuresDoc['adventures'] = $kept;
        $this->writeJson($adventuresPath, $adventuresDoc);

        return $removed;
    }

    /**
     * @param  list<array<string, mixed>>  $rawRows
     * @return array{added: int, skipped: int, unmapped: list<string>}
     */
    public function apply(array $rawRows): array
    {
        $adventuresPath = $this->dataPath.'/adventures.json';
        $seriesPath = $this->dataPath.'/series.json';
        $tagsPath = $this->dataPath.'/tags.json';

        $adventuresDoc = $this->readJson($adventuresPath);
        $seriesDoc = $this->readJson($seriesPath);
        $tagsDoc = $this->readJson($tagsPath);

        /** @var array<string, array<string, mixed>> $bySlug */
        $bySlug = [];
        foreach ($adventuresDoc['adventures'] ?? [] as $adventure) {
            if (is_array($adventure) && isset($adventure['slug']) && is_string($adventure['slug'])) {
                $bySlug[$adventure['slug']] = $adventure;
            }
        }

        $this->ensureSeries($seriesDoc, 'va-reihe', 'VA-Reihe');
        $this->ensureSeries($seriesDoc, 'a-reihe', 'A-Reihe');
        $this->ensureTag($tagsDoc, 'sonstiges', 'Sonstiges');

        $added = 0;
        $skipped = 0;
        $unmapped = [];

        foreach ($rawRows as $row) {
            if (! isset($row['title']) || ! is_string($row['title'])) {
                continue;
            }

            $descriptionRaw = $row['short_description_raw'] ?? null;
            if (! is_string($descriptionRaw) || trim($descriptionRaw) === '') {
                // Keine Dummy-Einträge ohne Wiki-Kurzbeschreibung.
                continue;
            }

            /** @var array{catalog_number: string, title: string, wiki_path: ?string, rules_raw: string, place_raw: string, genre_raw: string, complexity_raw: string, experience_raw: string, page_count: ?int, series_key: string, short_description_raw: string} $typed */
            $typed = [
                'catalog_number' => (string) ($row['catalog_number'] ?? ''),
                'title' => $row['title'],
                'wiki_path' => isset($row['wiki_path']) && is_string($row['wiki_path']) ? $row['wiki_path'] : null,
                'rules_raw' => (string) ($row['rules_raw'] ?? ''),
                'place_raw' => (string) ($row['place_raw'] ?? ''),
                'genre_raw' => (string) ($row['genre_raw'] ?? ''),
                'complexity_raw' => (string) ($row['complexity_raw'] ?? ''),
                'experience_raw' => (string) ($row['experience_raw'] ?? ''),
                'page_count' => isset($row['page_count']) && is_int($row['page_count']) ? $row['page_count'] : null,
                'series_key' => (string) ($row['series_key'] ?? 'a-reihe'),
                'short_description_raw' => $descriptionRaw,
            ];

            $mapped = $this->mapper->map($typed);
            $adventure = $mapped['adventure'];
            $slug = $adventure['slug'];

            if (isset($bySlug[$slug]) && ($bySlug[$slug]['incomplete'] ?? true) === false) {
                $skipped++;

                continue;
            }

            // Auch per Titel gegen kuratierte Complete-Einträge schützen.
            foreach ($bySlug as $existing) {
                if (($existing['incomplete'] ?? true) === false
                    && mb_strtolower((string) ($existing['title'] ?? '')) === mb_strtolower($typed['title'])) {
                    $skipped++;

                    continue 2;
                }
            }

            $bySlug[$slug] = $adventure;
            $added++;

            if ($mapped['unmapped_location']) {
                $unmapped[] = $typed['title'].' | '.$typed['place_raw'];
            }

            foreach ($adventure['genres'] as $genreSlug) {
                if ($genreSlug === 'sonstiges') {
                    $this->ensureTag($tagsDoc, 'sonstiges', 'Sonstiges');
                }
            }
        }

        $adventuresDoc['adventures'] = array_values($bySlug);
        $this->writeJson($adventuresPath, $adventuresDoc);
        $this->writeJson($seriesPath, $seriesDoc);
        $this->writeJson($tagsPath, $tagsDoc);

        return ['added' => $added, 'skipped' => $skipped, 'unmapped' => $unmapped];
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
