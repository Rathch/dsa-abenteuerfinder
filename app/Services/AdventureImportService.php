<?php

namespace App\Services;

use App\Domain\Matching\LocationKind;
use App\Models\Adventure;
use App\Models\GenreTag;
use App\Models\Location;
use App\Models\Series;
use Illuminate\Support\Facades\DB;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

/**
 * Validiert die JSON-Datenbestände (Schema + Referenzen) und importiert sie idempotent per Upsert.
 */
class AdventureImportService
{
    private readonly string $dataPath;

    public function __construct(?string $dataPath = null)
    {
        $this->dataPath = rtrim($dataPath ?? database_path('data'), '/\\');
    }

    /**
     * Validiert alle Dateien ohne Datenbankzugriff.
     *
     * @return list<string> Fehlermeldungen (leer = gültig)
     */
    public function validate(): array
    {
        try {
            return $this->collect()['errors'];
        } catch (InvalidAdventureDataException $e) {
            return $e->errors;
        }
    }

    /**
     * Validiert ein bereits dekodiertes Abenteuer-Dokument ausschließlich gegen das JSON-Schema.
     *
     * @return list<string>
     */
    public function validateAdventuresSchema(object $document): array
    {
        $schema = json_decode($this->read($this->dataPath.'/schema/adventures.schema.json'));
        if (! is_object($schema)) {
            return ['Schema adventures.schema.json ist kein gültiges JSON-Objekt.'];
        }

        $validator = new Validator;
        $validator->setMaxErrors(50);
        $result = $validator->validate($document, $schema);
        if ($result->isValid() || $result->error() === null) {
            return [];
        }

        $errors = [];
        foreach ((new ErrorFormatter)->formatKeyed($result->error()) as $pointer => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = 'adventures.json'.($pointer === '' ? '' : ' @ '.$pointer).': '.(is_string($message) ? $message : 'ungültig');
            }
        }

        return $errors;
    }

    /**
     * @return array{series: int, locations: int, tags: int, adventures: int, incomplete: int}
     *
     * @throws InvalidAdventureDataException
     */
    public function import(): array
    {
        $data = $this->collect();
        if ($data['errors'] !== []) {
            throw new InvalidAdventureDataException($data['errors']);
        }

        return DB::transaction(function () use ($data): array {
            foreach ($data['series'] as $row) {
                Series::query()->updateOrCreate(['slug' => $row['slug']], ['name' => $row['name']]);
            }

            foreach ($data['tags'] as $row) {
                GenreTag::query()->updateOrCreate(['slug' => $row['slug']], ['name' => $row['name']]);
            }

            $locationIds = [];
            foreach ($data['locations'] as $row) {
                $parentId = $row['parent'] !== null ? ($locationIds[$row['parent']] ?? null) : null;
                $location = Location::query()->updateOrCreate(
                    ['slug' => $row['slug']],
                    ['name' => $row['name'], 'type' => $row['type'], 'parent_id' => $parentId],
                );
                $locationIds[$row['slug']] = $location->id;
            }

            /** @var array<string, int> $seriesIds */
            $seriesIds = Series::query()->pluck('id', 'slug')->all();
            /** @var array<string, int> $tagIds */
            $tagIds = GenreTag::query()->pluck('id', 'slug')->all();

            $incomplete = 0;
            foreach ($data['adventures'] as $row) {
                $adventure = Adventure::query()->updateOrCreate(['slug' => $row['slug']], [
                    'title' => $row['title'],
                    'series_id' => $seriesIds[$row['series']],
                    'experience_level' => $row['experience_level'],
                    'page_count' => $row['page_count'],
                    'duration_category' => $row['duration_category'],
                    'rule_version' => $row['rule_version'],
                    'exotic' => $row['exotic'],
                    'complexity' => $row['complexity'],
                    'group_size_min' => $row['group_size_min'],
                    'group_size_max' => $row['group_size_max'],
                    'location_type' => $row['location_type'],
                    'primary_location_id' => $row['primary_location'] !== null ? $locationIds[$row['primary_location']] : null,
                    'primary_region_id' => $row['primary_region'] !== null ? $locationIds[$row['primary_region']] : null,
                    'place_label' => $row['place_label'],
                    'short_description' => $row['short_description'],
                    'klappentext' => $row['klappentext'],
                    'wiki_url' => $row['wiki_url'],
                    'cover_url' => $row['cover_url'],
                    'incomplete' => $row['incomplete'],
                    'missing_from_wiki' => $row['missing_from_wiki'],
                    'source_note' => $row['source_note'],
                ]);

                $adventure->genreTags()->sync(array_map(static fn (string $slug): int => $tagIds[$slug], $row['genres']));
                $adventure->regions()->sync(array_map(static fn (string $slug): int => $locationIds[$slug], $row['regions']));

                if ($row['incomplete']) {
                    $incomplete++;
                }
            }

            // Manuelle Korrekturen aus der Verwaltung bleiben nach dem Upsert erhalten.
            app(AdventureCorrectionService::class)->applyAll();

            return [
                'series' => count($data['series']),
                'locations' => count($data['locations']),
                'tags' => count($data['tags']),
                'adventures' => count($data['adventures']),
                'incomplete' => $incomplete,
            ];
        });
    }

    /**
     * Normalisierte Abenteuer aus der JSON-Quelle, indexiert nach Slug.
     *
     * @return array<string, array{slug: string, title: string, series: string, experience_level: string, page_count: ?int, duration_category: string, rule_version: string, exotic: bool, complexity: string, group_size_min: ?int, group_size_max: ?int, location_type: string, primary_location: ?string, primary_region: ?string, place_label: ?string, regions: list<string>, genres: list<string>, short_description: string, klappentext: ?string, wiki_url: string, cover_url: ?string, incomplete: bool, missing_from_wiki: bool, source_note: ?string}>
     */
    public function sourceAdventuresBySlug(): array
    {
        $data = $this->collect();
        $bySlug = [];
        foreach ($data['adventures'] as $row) {
            $bySlug[$row['slug']] = $row;
        }

        return $bySlug;
    }

    /**
     * Liest und prüft alle Dateien; liefert normalisierte Zeilen und alle gefundenen Fehler.
     *
     * @return array{
     *     errors: list<string>,
     *     series: list<array{slug: string, name: string}>,
     *     tags: list<array{slug: string, name: string}>,
     *     locations: list<array{slug: string, name: string, type: string, parent: ?string}>,
     *     adventures: list<array{slug: string, title: string, series: string, experience_level: string, page_count: ?int, duration_category: string, rule_version: string, exotic: bool, complexity: string, group_size_min: ?int, group_size_max: ?int, location_type: string, primary_location: ?string, primary_region: ?string, place_label: ?string, regions: list<string>, genres: list<string>, short_description: string, klappentext: ?string, wiki_url: string, cover_url: ?string, incomplete: bool, missing_from_wiki: bool, source_note: ?string}>
     * }
     *
     * @throws InvalidAdventureDataException wenn eine Datei fehlt oder kein gültiges JSON ist
     */
    private function collect(): array
    {
        $errors = [];

        $series = $this->simpleList('series.json', 'series', $errors);
        $tags = $this->simpleList('tags.json', 'tags', $errors);
        $locations = $this->locationList($errors);

        $adventuresPath = $this->dataPath.'/adventures.json';
        $raw = $this->read($adventuresPath);
        $document = json_decode($raw);
        if (! is_object($document)) {
            throw new InvalidAdventureDataException(['adventures.json ist kein gültiges JSON-Dokument.']);
        }

        $schemaErrors = $this->validateAdventuresSchema($document);
        $errors = array_merge($errors, $schemaErrors);

        $adventures = [];
        if ($schemaErrors === []) {
            $decoded = json_decode($raw, true);
            $rows = is_array($decoded) && is_array($decoded['adventures'] ?? null) ? $decoded['adventures'] : [];
            $adventures = $this->normalizeAdventures($rows);
            $errors = array_merge($errors, $this->checkReferences($adventures, $series, $tags, $locations));
        }

        return [
            'errors' => $errors,
            'series' => $series,
            'tags' => $tags,
            'locations' => $locations,
            'adventures' => $adventures,
        ];
    }

    /**
     * @param  list<string>  $errors
     * @return list<array{slug: string, name: string}>
     */
    private function simpleList(string $file, string $key, array &$errors): array
    {
        $decoded = json_decode($this->read($this->dataPath.'/'.$file), true);
        $rows = is_array($decoded) && is_array($decoded[$key] ?? null) ? $decoded[$key] : [];
        if ($rows === []) {
            $errors[] = "$file: Schlüssel \"$key\" fehlt oder ist leer.";
        }

        $result = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $slug = is_array($row) ? ($row['slug'] ?? null) : null;
            $name = is_array($row) ? ($row['name'] ?? null) : null;
            if (! is_string($slug) || ! $this->isSlug($slug) || ! is_string($name) || $name === '') {
                $errors[] = "$file @ /$key/$index: slug und name müssen gültige Strings sein.";

                continue;
            }
            if (isset($seen[$slug])) {
                $errors[] = "$file: doppelter Slug \"$slug\".";

                continue;
            }
            $seen[$slug] = true;
            $result[] = ['slug' => $slug, 'name' => $name];
        }

        return $result;
    }

    /**
     * Flacht den verschachtelten Ortsbaum ab (Eltern vor Kindern).
     *
     * @param  list<string>  $errors
     * @return list<array{slug: string, name: string, type: string, parent: ?string}>
     */
    private function locationList(array &$errors): array
    {
        $decoded = json_decode($this->read($this->dataPath.'/locations.json'), true);
        $roots = is_array($decoded) && is_array($decoded['locations'] ?? null) ? $decoded['locations'] : [];
        if ($roots === []) {
            $errors[] = 'locations.json: Schlüssel "locations" fehlt oder ist leer.';
        }

        $flat = [];
        $seen = [];
        $this->walkLocations($roots, null, null, $flat, $seen, $errors);

        return $flat;
    }

    /**
     * @param  array<mixed>  $nodes
     * @param  list<array{slug: string, name: string, type: string, parent: ?string}>  $flat
     * @param  array<string, true>  $seen
     * @param  list<string>  $errors
     */
    private function walkLocations(array $nodes, ?string $parentSlug, ?string $parentType, array &$flat, array &$seen, array &$errors): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                $errors[] = 'locations.json: Ortseintrag ist kein Objekt.';

                continue;
            }

            $slug = $node['slug'] ?? null;
            $name = $node['name'] ?? null;
            $type = $node['type'] ?? null;
            if (! is_string($slug) || ! $this->isSlug($slug) || ! is_string($name) || $name === '' || ! is_string($type) || LocationKind::tryFrom($type) === null) {
                $errors[] = 'locations.json: Ort mit ungültigem slug/name/type.';

                continue;
            }
            if (isset($seen[$slug])) {
                $errors[] = "locations.json: doppelter Slug \"$slug\".";

                continue;
            }
            if (! $this->isValidChildType($parentType, $type)) {
                $errors[] = "locations.json: \"$slug\" ($type) darf nicht unter $parentType stehen.";
            }

            $seen[$slug] = true;
            $flat[] = ['slug' => $slug, 'name' => $name, 'type' => $type, 'parent' => $parentSlug];

            $children = $node['children'] ?? [];
            if (is_array($children)) {
                $this->walkLocations($children, $slug, $type, $flat, $seen, $errors);
            }
        }
    }

    private function isValidChildType(?string $parentType, string $type): bool
    {
        return match ($parentType) {
            null => $type === 'continent',
            'continent' => in_array($type, ['region', 'place'], true),
            'region' => $type === 'place',
            default => false,
        };
    }

    /**
     * @param  array<mixed>  $rows
     * @return list<array{slug: string, title: string, series: string, experience_level: string, page_count: ?int, duration_category: string, rule_version: string, exotic: bool, complexity: string, group_size_min: ?int, group_size_max: ?int, location_type: string, primary_location: ?string, primary_region: ?string, place_label: ?string, regions: list<string>, genres: list<string>, short_description: string, klappentext: ?string, wiki_url: string, cover_url: ?string, incomplete: bool, missing_from_wiki: bool, source_note: ?string}>
     */
    private function normalizeAdventures(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $result[] = [
                'slug' => $this->str($row['slug'] ?? null),
                'title' => $this->str($row['title'] ?? null),
                'series' => $this->str($row['series'] ?? null),
                'experience_level' => $this->str($row['experience_level'] ?? null),
                'page_count' => $this->intOrNull($row['page_count'] ?? null),
                'duration_category' => $this->str($row['duration_category'] ?? null),
                'rule_version' => $this->str($row['rule_version'] ?? null),
                'exotic' => (bool) ($row['exotic'] ?? false),
                'complexity' => $this->str($row['complexity'] ?? null),
                'group_size_min' => $this->intOrNull($row['group_size_min'] ?? null),
                'group_size_max' => $this->intOrNull($row['group_size_max'] ?? null),
                'location_type' => $this->str($row['location_type'] ?? null),
                'primary_location' => $this->strOrNull($row['primary_location'] ?? null),
                'primary_region' => $this->strOrNull($row['primary_region'] ?? null),
                'place_label' => $this->strOrNull($row['place_label'] ?? null),
                'regions' => $this->strList($row['regions'] ?? []),
                'genres' => $this->strList($row['genres'] ?? []),
                'short_description' => $this->cleanShortDescription($this->str($row['short_description'] ?? null)),
                'klappentext' => $this->optionalDescription($row['klappentext'] ?? null),
                'wiki_url' => $this->str($row['wiki_url'] ?? null),
                'cover_url' => $this->strOrNull($row['cover_url'] ?? null),
                'incomplete' => (bool) ($row['incomplete'] ?? false),
                'missing_from_wiki' => (bool) ($row['missing_from_wiki'] ?? false),
                'source_note' => $this->strOrNull($row['source_note'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * Prüft Referenzen und fachliche Konsistenz, die das Schema nicht ausdrücken kann.
     *
     * @param  list<array{slug: string, title: string, series: string, experience_level: string, page_count: ?int, duration_category: string, rule_version: string, exotic: bool, complexity: string, group_size_min: ?int, group_size_max: ?int, location_type: string, primary_location: ?string, primary_region: ?string, place_label: ?string, regions: list<string>, genres: list<string>, short_description: string, klappentext: ?string, wiki_url: string, cover_url: ?string, incomplete: bool, missing_from_wiki: bool, source_note: ?string}>  $adventures
     * @param  list<array{slug: string, name: string}>  $series
     * @param  list<array{slug: string, name: string}>  $tags
     * @param  list<array{slug: string, name: string, type: string, parent: ?string}>  $locations
     * @return list<string>
     */
    private function checkReferences(array $adventures, array $series, array $tags, array $locations): array
    {
        $errors = [];
        $seriesSlugs = array_column($series, 'slug');
        $tagSlugs = array_column($tags, 'slug');
        $locationByType = [];
        $parentOf = [];
        foreach ($locations as $location) {
            $locationByType[$location['slug']] = $location['type'];
            $parentOf[$location['slug']] = $location['parent'];
        }

        $seen = [];
        foreach ($adventures as $adventure) {
            $slug = $adventure['slug'];
            if (isset($seen[$slug])) {
                $errors[] = "adventures.json: doppelter Slug \"$slug\".";
            }
            $seen[$slug] = true;

            if (! in_array($adventure['series'], $seriesSlugs, true)) {
                $errors[] = "$slug: unbekannte Serie \"{$adventure['series']}\".";
            }
            foreach ($adventure['genres'] as $genre) {
                if (! in_array($genre, $tagSlugs, true)) {
                    $errors[] = "$slug: unbekanntes Genre \"$genre\".";
                }
            }

            $place = $adventure['primary_location'];
            if ($place !== null && ($locationByType[$place] ?? null) !== 'place') {
                $errors[] = "$slug: primary_location \"$place\" ist kein bekannter Ort.";
            }

            $regionRefs = $adventure['regions'];
            if ($adventure['primary_region'] !== null) {
                $regionRefs[] = $adventure['primary_region'];
            }
            foreach ($regionRefs as $region) {
                if (! in_array($locationByType[$region] ?? null, ['region', 'continent'], true)) {
                    $errors[] = "$slug: unbekannte Region \"$region\".";
                }
            }

            if ($place !== null && $adventure['primary_region'] !== null && isset($locationByType[$place])) {
                $ancestor = $parentOf[$place] ?? null;
                $found = false;
                while ($ancestor !== null) {
                    if ($ancestor === $adventure['primary_region']) {
                        $found = true;
                        break;
                    }
                    $ancestor = $parentOf[$ancestor] ?? null;
                }
                if (! $found) {
                    $errors[] = "$slug: primary_location \"$place\" liegt nicht in primary_region \"{$adventure['primary_region']}\".";
                }
            }

            $min = $adventure['group_size_min'];
            $max = $adventure['group_size_max'];
            if ($min !== null && $max !== null && $min > $max) {
                $errors[] = "$slug: group_size_min ist größer als group_size_max.";
            }
        }

        return $errors;
    }

    private function read(string $path): string
    {
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            throw new InvalidAdventureDataException(["Datei nicht lesbar: $path"]);
        }

        return $contents;
    }

    private function isSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) === 1;
    }

    private function str(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function cleanShortDescription(string $value): string
    {
        $text = $value;
        do {
            $previous = $text;
            $text = trim(preg_replace('/^>\s*(?:Seitenzahl fehlt|Auflage klären)\s*/u', '', $text) ?? $text);
        } while ($text !== $previous);

        return $text;
    }

    private function optionalDescription(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $text = $this->cleanShortDescription($value);

        return $text === '' || $text === '-' ? null : $text;
    }

    private function strOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    private function intOrNull(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function strList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
