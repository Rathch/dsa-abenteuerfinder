<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiAdventureMapper;
use App\Services\WikiHarvest\WikiBlurbParser;
use App\Services\WikiHarvest\WikiHarvestApplicator;
use App\Services\WikiHarvest\WikiHttpClient;
use App\Services\WikiHarvest\WikiIndexTableParser;

it('harvests from cache fixtures and applies without overwriting curated complete entries', function (): void {
    $cacheDir = storage_path('app/wiki-cache-test');
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir.'/*.html') ?: [] as $file) {
            unlink($file);
        }
    } else {
        mkdir($cacheDir, 0775, true);
    }

    $client = new WikiHttpClient($cacheDir, pauseMilliseconds: 0, fromCacheOnly: false);
    $client->warmFromFixture(
        'https://de.wiki-aventurica.de/wiki/Abenteuer/VA-Reihe',
        (string) file_get_contents(base_path('tests/Fixtures/wiki/va-reihe-sample.html')),
    );
    $client->warmFromFixture(
        'https://de.wiki-aventurica.de/wiki/Abenteuer/A-Reihe',
        (string) file_get_contents(base_path('tests/Fixtures/wiki/a-reihe-sample.html')),
    );
    $blurbHtml = (string) file_get_contents(base_path('tests/Fixtures/wiki/klappentext-sample.html'));
    $blurbParser = new WikiBlurbParser;
    foreach ([
        '/wiki/Offenbarung_des_Himmels',
        '/wiki/Der_Vampir_von_Havena',
        '/wiki/Beispiel_DSA4',
    ] as $path) {
        $client->warmFromFixture($blurbParser->klappentextUrl($path), $blurbHtml);
    }

    $fromCache = new WikiHttpClient($cacheDir, pauseMilliseconds: 0, fromCacheOnly: true);
    $parser = new WikiIndexTableParser;
    $rows = array_merge(
        $parser->parse($fromCache->get('https://de.wiki-aventurica.de/wiki/Abenteuer/VA-Reihe'), 'va-reihe'),
        $parser->parse($fromCache->get('https://de.wiki-aventurica.de/wiki/Abenteuer/A-Reihe'), 'a-reihe'),
    );

    expect($rows)->toHaveCount(3);

    foreach ($rows as $index => $row) {
        $path = is_string($row['wiki_path'] ?? null) ? $row['wiki_path'] : (string) $row['title'];
        $html = $fromCache->tryGet($blurbParser->klappentextUrl($path));
        $rows[$index]['short_description_raw'] = $html !== null ? $blurbParser->extract($html) : null;
    }

    $tmpData = storage_path('app/wiki-harvest-testdata');
    if (! is_dir($tmpData)) {
        mkdir($tmpData, 0775, true);
    }

    foreach (['adventures.json', 'series.json', 'tags.json'] as $file) {
        copy(database_path('data/'.$file), $tmpData.'/'.$file);
    }

    $before = json_decode((string) file_get_contents($tmpData.'/adventures.json'), true);
    $curatedSlug = $before['adventures'][0]['slug'];
    $curatedTitle = $before['adventures'][0]['title'];
    expect($before['adventures'][0]['incomplete'])->toBeFalse();

    // Simuliere Kollision: Harvest-Titel = kuratierter Titel.
    $rows[] = [
        'catalog_number' => '(VA00)',
        'title' => $curatedTitle,
        'wiki_path' => '/wiki/Collision',
        'rules_raw' => 'DSA5',
        'place_raw' => 'Havena',
        'genre_raw' => 'Detektivgeschichte',
        'complexity_raw' => 'gering',
        'experience_raw' => 'Einsteiger',
        'page_count' => 32,
        'series_key' => 'va-reihe',
        'short_description_raw' => 'Eine aus dem Wiki übernommene Kurzbeschreibung für den Kollisionstest mit genug Länge.',
    ];

    $mapper = new WikiAdventureMapper(
        json_decode((string) file_get_contents(database_path('data/wiki-harvest/location-aliases.json')), true),
        json_decode((string) file_get_contents(database_path('data/wiki-harvest/genre-aliases.json')), true),
    );
    $applicator = new WikiHarvestApplicator($tmpData, $mapper);
    $applicator->purgeHarvestedSeries(['va-reihe', 'a-reihe']);
    $summary = $applicator->apply($rows);

    $after = json_decode((string) file_get_contents($tmpData.'/adventures.json'), true);
    $bySlug = collect($after['adventures'])->keyBy('slug');

    expect($bySlug[$curatedSlug]['incomplete'])->toBeFalse()
        ->and($bySlug[$curatedSlug]['short_description'])->toBe($before['adventures'][0]['short_description'])
        ->and($summary['skipped'])->toBeGreaterThanOrEqual(1)
        ->and($summary['added'])->toBeGreaterThanOrEqual(3);

    $harvested = $bySlug->first(fn (array $a): bool => ($a['slug'] ?? '') === 'der-vampir-von-havena-va02');
    expect($harvested)->not->toBeNull()
        ->and($harvested['incomplete'])->toBeFalse()
        ->and($harvested['short_description'])->toContain('Hafen von Havena');

    $this->artisan('adventures:validate')->assertSuccessful();
});
