<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiAdventureMapper;
use App\Services\WikiHarvest\WikiCatalogSyncService;
use Illuminate\Support\Facades\File;

it('adds new wiki adventures and marks removed ones as missing', function (): void {
    $tmpData = storage_path('app/wiki-catalog-sync-testdata');
    File::deleteDirectory($tmpData);
    File::makeDirectory($tmpData, 0775, true);
    foreach (['adventures.json', 'series.json', 'tags.json'] as $file) {
        File::copy(database_path('data/'.$file), $tmpData.'/'.$file);
    }

    $before = json_decode((string) File::get($tmpData.'/adventures.json'), true);
    $tracked = collect($before['adventures'])
        ->first(fn (array $row): bool => in_array($row['series'] ?? '', ['va-reihe', 'a-reihe'], true)
            && ($row['incomplete'] ?? true) === false);

    expect($tracked)->not->toBeNull();

    $removedTitle = $tracked['title'];
    $removedPath = null;
    if (preg_match('~/wiki/([^?#]+)~', (string) $tracked['wiki_url'], $matches) === 1) {
        $removedPath = '/wiki/'.$matches[1];
    }

    $mapper = new WikiAdventureMapper(
        json_decode((string) file_get_contents(database_path('data/wiki-harvest/location-aliases.json')), true),
        json_decode((string) file_get_contents(database_path('data/wiki-harvest/genre-aliases.json')), true),
    );
    $sync = new WikiCatalogSyncService($tmpData, $mapper);

    $rows = [
        [
            'catalog_number' => '(VA02)',
            'title' => 'Der Vampir von Havena',
            'wiki_path' => '/wiki/Der_Vampir_von_Havena',
            'rules_raw' => 'DSA5',
            'place_raw' => 'Havena, Albernia',
            'genre_raw' => 'Mystery, Detektivgeschichte',
            'complexity_raw' => 'gering',
            'experience_raw' => 'unerfahren bis kompetent',
            'page_count' => 64,
            'series_key' => 'va-reihe',
            'short_description_raw' => 'Im Hafen von Havena verschwinden nächtens Schiffer und Händler. Die Helden folgen Gerüchten durch die Gassen.',
        ],
        [
            'catalog_number' => '(VA77)',
            'title' => 'Neues Testabenteuer aus dem Wiki',
            'wiki_path' => '/wiki/Neues_Testabenteuer_aus_dem_Wiki',
            'rules_raw' => 'DSA5',
            'place_raw' => 'Havena, Albernia',
            'genre_raw' => 'Detektivgeschichte',
            'complexity_raw' => 'gering',
            'experience_raw' => 'Einsteiger',
            'page_count' => 48,
            'series_key' => 'va-reihe',
            'short_description_raw' => 'Ein frisch gelistetes Abenteuer aus dem Wiki-Index mit genug Textlänge für die Validierung.',
        ],
    ];

    // Alle bestehenden VA-/A-Titel außer dem entfernten Titel weiter listen,
    // damit der Sync nicht den gesamten Bestand als missing markiert.
    foreach ($before['adventures'] as $adventure) {
        if (! in_array($adventure['series'] ?? '', ['va-reihe', 'a-reihe'], true)) {
            continue;
        }
        if (($adventure['title'] ?? '') === $removedTitle) {
            continue;
        }

        $path = null;
        if (preg_match('~/wiki/([^?#]+)~', (string) ($adventure['wiki_url'] ?? ''), $m) === 1) {
            $path = '/wiki/'.$m[1];
        }

        $rows[] = [
            'catalog_number' => '',
            'title' => $adventure['title'],
            'wiki_path' => $path,
            'rules_raw' => match ($adventure['rule_version'] ?? 'dsa5') {
                'dsa4' => 'DSA4',
                default => 'DSA5',
            },
            'place_raw' => 'Havena',
            'genre_raw' => 'Detektivgeschichte',
            'complexity_raw' => 'gering',
            'experience_raw' => 'Einsteiger',
            'page_count' => 32,
            'series_key' => $adventure['series'],
            'short_description_raw' => null,
        ];
    }

    $summary = $sync->sync($rows);
    $after = json_decode((string) File::get($tmpData.'/adventures.json'), true);
    $byTitle = collect($after['adventures'])->keyBy('title');

    expect($summary['added'])->toBeGreaterThanOrEqual(1)
        ->and($summary['marked_missing'])->toBe(1)
        ->and($byTitle[$removedTitle]['missing_from_wiki'])->toBeTrue()
        ->and($byTitle[$removedTitle]['source_note'])->toBe(WikiCatalogSyncService::SOURCE_NOTE_MISSING)
        ->and($byTitle['Neues Testabenteuer aus dem Wiki']['missing_from_wiki'])->toBeFalse()
        ->and($byTitle['Neues Testabenteuer aus dem Wiki']['short_description'])->toContain('frisch gelistetes');

    // Wieder im Index → Flag zurücknehmen, Inhalt kuratierter Complete bleibt.
    $rows[] = [
        'catalog_number' => '',
        'title' => $removedTitle,
        'wiki_path' => $removedPath,
        'rules_raw' => 'DSA5',
        'place_raw' => 'Havena',
        'genre_raw' => 'Detektivgeschichte',
        'complexity_raw' => 'gering',
        'experience_raw' => 'Einsteiger',
        'page_count' => 32,
        'series_key' => $tracked['series'],
        'short_description_raw' => 'Sollte die kuratierte Beschreibung nicht überschreiben, weil complete.',
    ];

    $restore = $sync->sync($rows);
    $restoredDoc = json_decode((string) File::get($tmpData.'/adventures.json'), true);
    $restored = collect($restoredDoc['adventures'])->firstWhere('title', $removedTitle);

    expect($restore['restored'])->toBe(1)
        ->and($restored['missing_from_wiki'])->toBeFalse()
        ->and($restored['short_description'])->toBe($tracked['short_description']);

    File::deleteDirectory($tmpData);
});

it('replaces only genres from a wiki genre cell and keeps rows without that column', function (): void {
    $tmpData = storage_path('app/wiki-catalog-genre-testdata');
    File::deleteDirectory($tmpData);
    File::makeDirectory($tmpData, 0775, true);

    $culture = [
        'slug' => 'das-kulturerlebnis',
        'title' => 'Das Kulturerlebnis',
        'series' => 'a-reihe',
        'experience_level' => 'einsteiger',
        'page_count' => 32,
        'duration_category' => 'kurz',
        'rule_version' => 'dsa5',
        'exotic' => false,
        'complexity' => 'niedrig',
        'group_size_min' => null,
        'group_size_max' => null,
        'location_type' => 'point',
        'primary_location' => 'gareth',
        'primary_region' => 'mittelaventurien',
        'regions' => [],
        'genres' => ['sonstiges'],
        'short_description' => 'Eine kuratierte Beschreibung, die der Abgleich nicht anfassen darf.',
        'wiki_url' => 'https://de.wiki-aventurica.de/wiki/Das_Kulturerlebnis',
        'cover_url' => null,
        'incomplete' => false,
        'missing_from_wiki' => false,
        'source_note' => null,
    ];
    $exploration = [
        'slug' => 'die-erkundung',
        'title' => 'Die Erkundung',
        'series' => 'a-reihe',
        'experience_level' => 'einsteiger',
        'page_count' => 40,
        'duration_category' => 'kurz',
        'rule_version' => 'dsa4',
        'exotic' => false,
        'complexity' => 'niedrig',
        'group_size_min' => null,
        'group_size_max' => null,
        'location_type' => 'point',
        'primary_location' => 'havena',
        'primary_region' => 'nordmarken',
        'regions' => [],
        'genres' => ['exploration'],
        'short_description' => 'Die Helden folgen einem alten Pfad durch die Nordmarken.',
        'wiki_url' => 'https://de.wiki-aventurica.de/wiki/Die_Erkundung',
        'cover_url' => null,
        'incomplete' => false,
        'missing_from_wiki' => false,
        'source_note' => null,
    ];

    File::put($tmpData.'/adventures.json', json_encode(['adventures' => [$culture, $exploration]], JSON_UNESCAPED_UNICODE));
    File::put($tmpData.'/series.json', json_encode(['series' => [['slug' => 'a-reihe', 'name' => 'A-Reihe']]], JSON_UNESCAPED_UNICODE));
    File::put($tmpData.'/tags.json', json_encode(['tags' => [
        ['slug' => 'exploration', 'name' => 'Erkundung'],
        ['slug' => 'sonstiges', 'name' => 'Sonstiges'],
    ]], JSON_UNESCAPED_UNICODE));

    $mapper = new WikiAdventureMapper(
        json_decode((string) file_get_contents(database_path('data/wiki-harvest/location-aliases.json')), true),
        json_decode((string) file_get_contents(database_path('data/wiki-harvest/genre-aliases.json')), true),
    );
    $sync = new WikiCatalogSyncService($tmpData, $mapper);

    $sync->sync([
        [
            'catalog_number' => '',
            'title' => 'Das Kulturerlebnis',
            'wiki_path' => '/wiki/Das_Kulturerlebnis',
            'rules_raw' => 'DSA1',
            'place_raw' => 'Havena, Albernia',
            'genre_raw' => 'Kulturerfahrung',
            'has_genre_column' => true,
            'complexity_raw' => 'hoch',
            'experience_raw' => 'Meister',
            'page_count' => 200,
            'series_key' => 'a-reihe',
            'short_description_raw' => 'Diese Beschreibung darf den kuratierten Text nicht ersetzen.',
            'overwrite' => false,
        ],
        [
            'catalog_number' => '',
            'title' => 'Die Erkundung',
            'wiki_path' => '/wiki/Die_Erkundung',
            'rules_raw' => 'DSA5',
            'place_raw' => 'Gareth',
            'genre_raw' => '',
            'has_genre_column' => false,
            'complexity_raw' => '',
            'experience_raw' => '',
            'page_count' => null,
            'series_key' => 'a-reihe',
            'short_description_raw' => null,
            'overwrite' => true,
        ],
        [
            'catalog_number' => '',
            'title' => 'Neues Abenteuer ohne Genre',
            'wiki_path' => '/wiki/Neues_Abenteuer_ohne_Genre',
            'rules_raw' => 'DSA5',
            'place_raw' => 'Havena',
            'genre_raw' => '',
            'has_genre_column' => false,
            'complexity_raw' => 'gering',
            'experience_raw' => 'Einsteiger',
            'page_count' => 32,
            'series_key' => 'a-reihe',
            'short_description_raw' => 'Ein neues Abenteuer aus einer Liste ohne Genre-Spalte mit genug Text.',
            'overwrite' => true,
        ],
    ]);

    $after = json_decode((string) File::get($tmpData.'/adventures.json'), true);
    $byTitle = collect($after['adventures'])->keyBy('title');
    $tags = json_decode((string) File::get($tmpData.'/tags.json'), true);

    expect($byTitle['Das Kulturerlebnis']['genres'])->toBe(['kulturerfahrung'])
        ->and($byTitle['Das Kulturerlebnis']['title'])->toBe('Das Kulturerlebnis')
        ->and($byTitle['Das Kulturerlebnis']['primary_location'])->toBe('gareth')
        ->and($byTitle['Das Kulturerlebnis']['rule_version'])->toBe('dsa5')
        ->and($byTitle['Das Kulturerlebnis']['short_description'])->toBe($culture['short_description'])
        ->and($byTitle['Die Erkundung']['genres'])->toBe(['exploration'])
        ->and($byTitle['Die Erkundung']['primary_location'])->toBe('havena')
        ->and($byTitle['Neues Abenteuer ohne Genre']['genres'])->toBe(['sonstiges'])
        ->and(collect($tags['tags'])->firstWhere('slug', 'kulturerfahrung'))->toBe([
            'slug' => 'kulturerfahrung',
            'name' => 'Kulturerfahrung',
        ]);

    File::deleteDirectory($tmpData);
});

it('registers the daily wiki sync schedule', function (): void {
    $this->artisan('schedule:list')
        ->assertSuccessful()
        ->expectsOutputToContain('adventures:sync-wiki');
});
