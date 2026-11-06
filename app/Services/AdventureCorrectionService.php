<?php

// SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
//
// SPDX-License-Identifier: GPL-3.0-only

namespace App\Services;

use App\Models\Adventure;
use App\Models\AdventureCorrection;
use App\Models\GenreTag;
use App\Models\Location;
use App\Models\Series;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Speichert manuelle Abenteuer-Korrekturen und stellt sie nach dem JSON-Import wieder her.
 */
class AdventureCorrectionService
{
    /** @var list<string> */
    public const EDITABLE_KEYS = [
        'title',
        'series',
        'experience_level',
        'page_count',
        'duration_category',
        'rule_version',
        'exotic',
        'complexity',
        'group_size_min',
        'group_size_max',
        'location_type',
        'primary_location',
        'primary_region',
        'regions',
        'genres',
        'short_description',
        'wiki_url',
        'incomplete',
        'missing_from_wiki',
        'source_note',
    ];

    public function __construct(
        private readonly AdventureImportService $importer,
    ) {}

    /**
     * Schreibt alle gespeicherten Korrekturen auf die aktuellen Abenteuer-Zeilen.
     */
    public function applyAll(): int
    {
        $applied = 0;

        foreach (AdventureCorrection::query()->cursor() as $correction) {
            $adventure = Adventure::query()->where('slug', $correction->adventure_slug)->first();
            if ($adventure === null) {
                continue;
            }

            $this->writeAdventure($adventure, $correction->fields);
            $applied++;
        }

        return $applied;
    }

    /**
     * Aktualisiert das Abenteuer sofort und speichert nur die Abweichung zur JSON-Quelle.
     *
     * @param  array<string, mixed>  $input
     */
    public function save(string $slug, array $input): void
    {
        $adventure = Adventure::query()->where('slug', $slug)->firstOrFail();
        $normalized = $this->normalizeInput($input);
        $this->assertReferences($normalized);

        DB::transaction(function () use ($adventure, $slug, $normalized): void {
            $this->writeAdventure($adventure, $normalized);

            $source = $this->importer->sourceAdventuresBySlug()[$slug] ?? null;
            $diff = $source === null
                ? $normalized
                : $this->diffAgainstSource($normalized, $source);

            if ($diff === []) {
                AdventureCorrection::query()->where('adventure_slug', $slug)->delete();

                return;
            }

            AdventureCorrection::query()->updateOrCreate(
                ['adventure_slug' => $slug],
                ['fields' => $diff],
            );
        });
    }

    /**
     * @return array{
     *     adventure: array{
     *         slug: string,
     *         title: string,
     *         series: string,
     *         experience_level: string,
     *         page_count: ?int,
     *         duration_category: string,
     *         rule_version: string,
     *         exotic: bool,
     *         complexity: string,
     *         group_size_min: ?int,
     *         group_size_max: ?int,
     *         location_type: string,
     *         primary_location: ?string,
     *         primary_region: ?string,
     *         regions: list<string>,
     *         genres: list<string>,
     *         short_description: string,
     *         wiki_url: string,
     *         incomplete: bool,
     *         missing_from_wiki: bool,
     *         source_note: ?string
     *     },
     *     has_correction: bool,
     *     options: array{
     *         series: list<array{slug: string, name: string}>,
     *         places: list<array{slug: string, name: string}>,
     *         regions: list<array{slug: string, name: string}>,
     *         genres: list<array{slug: string, name: string}>
     *     }
     * }
     */
    public function editPayload(string $slug): array
    {
        $adventure = Adventure::query()
            ->with(['series', 'primaryLocation', 'primaryRegion', 'genreTags', 'regions'])
            ->where('slug', $slug)
            ->firstOrFail();

        return [
            'adventure' => $this->toFieldArray($adventure),
            'has_correction' => AdventureCorrection::query()->where('adventure_slug', $slug)->exists(),
            'options' => [
                'series' => array_values(Series::query()->orderBy('name')->get(['slug', 'name'])
                    ->map(static fn (Series $row): array => ['slug' => $row->slug, 'name' => $row->name])
                    ->all()),
                'places' => array_values(Location::query()->where('type', 'place')->orderBy('name')->get(['slug', 'name'])
                    ->map(static fn (Location $row): array => ['slug' => $row->slug, 'name' => $row->name])
                    ->all()),
                'regions' => array_values(Location::query()->where('type', 'region')->orderBy('name')->get(['slug', 'name'])
                    ->map(static fn (Location $row): array => ['slug' => $row->slug, 'name' => $row->name])
                    ->all()),
                'genres' => array_values(GenreTag::query()->orderBy('name')->get(['slug', 'name'])
                    ->map(static fn (GenreTag $row): array => ['slug' => $row->slug, 'name' => $row->name])
                    ->all()),
            ],
        ];
    }

    /**
     * @param  array{q?: string, link?: string}  $filters
     * @return LengthAwarePaginator<int, array{slug: string, title: string, wiki_url: string, incomplete: bool, missing_from_wiki: bool, has_correction: bool}>
     */
    public function paginate(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        $query = Adventure::query()->orderBy('title');

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where('title', 'like', '%'.$search.'%');
        }

        $link = (string) ($filters['link'] ?? '');
        if ($link === 'corrected') {
            $slugs = AdventureCorrection::query()->pluck('adventure_slug');
            $query->whereIn('slug', $slugs);
        } elseif ($link === 'incomplete') {
            $query->where('incomplete', true);
        } elseif ($link === 'missing') {
            $query->where('missing_from_wiki', true);
        }

        $corrected = AdventureCorrection::query()->pluck('adventure_slug')->flip();

        /** @var \Illuminate\Pagination\LengthAwarePaginator<int, Adventure> $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        /** @var LengthAwarePaginator<int, array{slug: string, title: string, wiki_url: string, incomplete: bool, missing_from_wiki: bool, has_correction: bool}> $mapped */
        $mapped = $paginator->through(static function (Adventure $adventure) use ($corrected): array {
            return [
                'slug' => $adventure->slug,
                'title' => $adventure->title,
                'wiki_url' => $adventure->wiki_url,
                'incomplete' => $adventure->incomplete,
                'missing_from_wiki' => $adventure->missing_from_wiki,
                'has_correction' => $corrected->has($adventure->slug),
            ];
        });

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function writeAdventure(Adventure $adventure, array $fields): void
    {
        $seriesSlug = isset($fields['series']) && is_string($fields['series']) ? $fields['series'] : null;
        $seriesId = $seriesSlug !== null
            ? Series::query()->where('slug', $seriesSlug)->value('id')
            : $adventure->series_id;

        if ($seriesId === null) {
            throw ValidationException::withMessages(['series' => 'Unbekannte Reihe.']);
        }

        $locationIds = Location::query()->pluck('id', 'slug');

        $attributes = [];
        foreach ([
            'title' => 'title',
            'experience_level' => 'experience_level',
            'page_count' => 'page_count',
            'duration_category' => 'duration_category',
            'rule_version' => 'rule_version',
            'exotic' => 'exotic',
            'complexity' => 'complexity',
            'group_size_min' => 'group_size_min',
            'group_size_max' => 'group_size_max',
            'location_type' => 'location_type',
            'short_description' => 'short_description',
            'wiki_url' => 'wiki_url',
            'incomplete' => 'incomplete',
            'missing_from_wiki' => 'missing_from_wiki',
            'source_note' => 'source_note',
        ] as $key => $column) {
            if (array_key_exists($key, $fields)) {
                $attributes[$column] = $fields[$key];
            }
        }

        $attributes['series_id'] = $seriesId;

        if (array_key_exists('primary_location', $fields)) {
            $slug = $fields['primary_location'];
            $attributes['primary_location_id'] = is_string($slug) && $slug !== ''
                ? ($locationIds[$slug] ?? null)
                : null;
        }

        if (array_key_exists('primary_region', $fields)) {
            $slug = $fields['primary_region'];
            $attributes['primary_region_id'] = is_string($slug) && $slug !== ''
                ? ($locationIds[$slug] ?? null)
                : null;
        }

        $adventure->fill($attributes);
        $adventure->save();

        if (array_key_exists('genres', $fields) && is_array($fields['genres'])) {
            $tagIds = GenreTag::query()->whereIn('slug', $fields['genres'])->pluck('id')->all();
            $adventure->genreTags()->sync($tagIds);
        }

        if (array_key_exists('regions', $fields) && is_array($fields['regions'])) {
            $regionIds = [];
            foreach ($fields['regions'] as $regionSlug) {
                if (is_string($regionSlug) && isset($locationIds[$regionSlug])) {
                    $regionIds[] = $locationIds[$regionSlug];
                }
            }
            $adventure->regions()->sync($regionIds);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalizeInput(array $input): array
    {
        $regions = $input['regions'] ?? [];
        $genres = $input['genres'] ?? [];
        if (! is_array($regions)) {
            $regions = [];
        }
        if (! is_array($genres)) {
            $genres = [];
        }

        $primaryLocation = $input['primary_location'] ?? null;
        $primaryLocation = is_string($primaryLocation) && $primaryLocation !== '' ? $primaryLocation : null;
        $primaryRegion = $input['primary_region'] ?? null;
        $primaryRegion = is_string($primaryRegion) && $primaryRegion !== '' ? $primaryRegion : null;

        $regionList = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null, $regions),
        )));
        sort($regionList);

        $genreList = array_values(array_unique(array_filter(
            array_map(static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null, $genres),
        )));
        sort($genreList);

        return [
            'title' => trim((string) ($input['title'] ?? '')),
            'series' => (string) ($input['series'] ?? ''),
            'experience_level' => (string) ($input['experience_level'] ?? ''),
            'page_count' => $this->nullableInt($input['page_count'] ?? null),
            'duration_category' => (string) ($input['duration_category'] ?? ''),
            'rule_version' => (string) ($input['rule_version'] ?? ''),
            'exotic' => (bool) ($input['exotic'] ?? false),
            'complexity' => (string) ($input['complexity'] ?? ''),
            'group_size_min' => $this->nullableInt($input['group_size_min'] ?? null),
            'group_size_max' => $this->nullableInt($input['group_size_max'] ?? null),
            'location_type' => (string) ($input['location_type'] ?? ''),
            'primary_location' => $primaryLocation,
            'primary_region' => $primaryRegion,
            'regions' => $regionList,
            'genres' => $genreList,
            'short_description' => trim((string) ($input['short_description'] ?? '')),
            'wiki_url' => trim((string) ($input['wiki_url'] ?? '')),
            'incomplete' => (bool) ($input['incomplete'] ?? false),
            'missing_from_wiki' => (bool) ($input['missing_from_wiki'] ?? false),
            'source_note' => $this->nullableString($input['source_note'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function diffAgainstSource(array $normalized, array $source): array
    {
        $sourceRegions = is_array($source['regions'] ?? null) ? $source['regions'] : [];
        $sourceGenres = is_array($source['genres'] ?? null) ? $source['genres'] : [];
        sort($sourceRegions);
        sort($sourceGenres);

        $sourceNormalized = [
            'title' => (string) ($source['title'] ?? ''),
            'series' => (string) ($source['series'] ?? ''),
            'experience_level' => (string) ($source['experience_level'] ?? ''),
            'page_count' => $source['page_count'] ?? null,
            'duration_category' => (string) ($source['duration_category'] ?? ''),
            'rule_version' => (string) ($source['rule_version'] ?? ''),
            'exotic' => (bool) ($source['exotic'] ?? false),
            'complexity' => (string) ($source['complexity'] ?? ''),
            'group_size_min' => $source['group_size_min'] ?? null,
            'group_size_max' => $source['group_size_max'] ?? null,
            'location_type' => (string) ($source['location_type'] ?? ''),
            'primary_location' => $source['primary_location'] ?? null,
            'primary_region' => $source['primary_region'] ?? null,
            'regions' => $sourceRegions,
            'genres' => $sourceGenres,
            'short_description' => (string) ($source['short_description'] ?? ''),
            'wiki_url' => (string) ($source['wiki_url'] ?? ''),
            'incomplete' => (bool) ($source['incomplete'] ?? false),
            'missing_from_wiki' => (bool) ($source['missing_from_wiki'] ?? false),
            'source_note' => $source['source_note'] ?? null,
        ];

        $diff = [];
        foreach (self::EDITABLE_KEYS as $key) {
            if (($normalized[$key] ?? null) !== ($sourceNormalized[$key] ?? null)) {
                $diff[$key] = $normalized[$key];
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function assertReferences(array $fields): void
    {
        if (! Series::query()->where('slug', $fields['series'])->exists()) {
            throw ValidationException::withMessages(['series' => 'Unbekannte Reihe.']);
        }

        foreach (['primary_location', 'primary_region'] as $key) {
            $slug = $fields[$key] ?? null;
            if (is_string($slug) && $slug !== '' && ! Location::query()->where('slug', $slug)->exists()) {
                throw ValidationException::withMessages([$key => 'Unbekannter Ort.']);
            }
        }

        foreach ($fields['regions'] as $slug) {
            if (! Location::query()->where('slug', $slug)->where('type', 'region')->exists()) {
                throw ValidationException::withMessages(['regions' => 'Unbekannte Region: '.$slug]);
            }
        }

        foreach ($fields['genres'] as $slug) {
            if (! GenreTag::query()->where('slug', $slug)->exists()) {
                throw ValidationException::withMessages(['genres' => 'Unbekanntes Genre: '.$slug]);
            }
        }
    }

    /**
     * @return array{
     *     slug: string,
     *     title: string,
     *     series: string,
     *     experience_level: string,
     *     page_count: ?int,
     *     duration_category: string,
     *     rule_version: string,
     *     exotic: bool,
     *     complexity: string,
     *     group_size_min: ?int,
     *     group_size_max: ?int,
     *     location_type: string,
     *     primary_location: ?string,
     *     primary_region: ?string,
     *     regions: list<string>,
     *     genres: list<string>,
     *     short_description: string,
     *     wiki_url: string,
     *     incomplete: bool,
     *     missing_from_wiki: bool,
     *     source_note: ?string
     * }
     */
    private function toFieldArray(Adventure $adventure): array
    {
        $regions = $adventure->regions->pluck('slug')->all();
        sort($regions);
        $genres = $adventure->genreTags->pluck('slug')->all();
        sort($genres);

        return [
            'slug' => $adventure->slug,
            'title' => $adventure->title,
            'series' => $adventure->series->slug,
            'experience_level' => $adventure->experience_level->value,
            'page_count' => $adventure->page_count,
            'duration_category' => $adventure->duration_category->value,
            'rule_version' => $adventure->rule_version->value,
            'exotic' => $adventure->exotic,
            'complexity' => $adventure->complexity->value,
            'group_size_min' => $adventure->group_size_min,
            'group_size_max' => $adventure->group_size_max,
            'location_type' => $adventure->location_type->value,
            'primary_location' => $adventure->primaryLocation?->slug,
            'primary_region' => $adventure->primaryRegion?->slug,
            'regions' => $regions,
            'genres' => $genres,
            'short_description' => $adventure->short_description,
            'wiki_url' => $adventure->wiki_url,
            'incomplete' => $adventure->incomplete,
            'missing_from_wiki' => $adventure->missing_from_wiki,
            'source_note' => $adventure->source_note,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
