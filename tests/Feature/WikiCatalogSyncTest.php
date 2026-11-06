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

it('registers the daily wiki sync schedule', function (): void {
    $this->artisan('schedule:list')
        ->assertSuccessful()
        ->expectsOutputToContain('adventures:sync-wiki');
});
