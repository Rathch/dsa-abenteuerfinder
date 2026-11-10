<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiAdventureMapper;

function wikiMapper(): WikiAdventureMapper
{
    $locations = json_decode((string) file_get_contents(database_path('data/wiki-harvest/location-aliases.json')), true);
    $genres = json_decode((string) file_get_contents(database_path('data/wiki-harvest/genre-aliases.json')), true);

    return new WikiAdventureMapper($locations, $genres);
}

it('maps rules experience complexity duration and genres', function (): void {
    $mapper = wikiMapper();

    expect($mapper->mapRules('DSA5'))->toBe('dsa5')
        ->and($mapper->mapRules('DSA4.1'))->toBe('dsa4')
        ->and($mapper->mapRules('DSA4'))->toBe('dsa4')
        ->and($mapper->mapRules('DSA4 Basis'))->toBe('dsa4')
        ->and($mapper->mapRules('DSA1'))->toBe('dsa1')
        ->and($mapper->mapRules('DSA1 Basis'))->toBe('dsa1')
        ->and($mapper->mapRules('DSA2'))->toBe('dsa2')
        ->and($mapper->mapRules('DSA3'))->toBe('dsa3')
        ->and($mapper->mapRules('DSA4.1 / DSA5'))->toBe('editionunabhaengig')
        ->and($mapper->mapRules('DSA4.1 Basis / DSA5'))->toBe('editionunabhaengig')
        ->and($mapper->mapExperience('unerfahren bis kompetent')['level'])->toBe('erfahren')
        ->and($mapper->mapExperience('kompetent bis meisterlich')['level'])->toBe('meister')
        ->and($mapper->mapComplexity('gering'))->toBe('niedrig')
        ->and($mapper->mapGenres('Stadtabenteuer, Intrigenszenario, Detektivgeschichte'))
        ->toContain('social', 'intrigue', 'investigation');
});

it('keeps known genre aliases and turns other wiki phrases into their own genres', function (): void {
    $mapper = wikiMapper();

    expect($mapper->mapGenres('Detektivgeschichte'))->toBe(['investigation'])
        ->and($mapper->mapGenres('Stadtabenteuer'))->toBe(['social'])
        ->and($mapper->mapGenres('Mysterienabenteuer'))->toBe(['mysterienabenteuer'])
        ->and($mapper->mapGenreTags('Mysterienabenteuer')[0]['name'])->toBe('Mysterienabenteuer')
        ->and($mapper->mapGenres('Suchqueste'))->toBe(['suchqueste'])
        ->and($mapper->mapGenreTags('Suchqueste')[0]['name'])->toBe('Suchqueste')
        ->and($mapper->mapGenres('Reiseabenteuer, Suchqueste'))->toBe(['exploration', 'suchqueste'])
        ->and($mapper->mapGenres(''))->toBe(['sonstiges'])
        ->and($mapper->mapGenres('   '))->toBe(['sonstiges'])
        ->and($mapper->mapGenres('Reise- und Wildnisabenteuer'))->toBe(['exploration'])
        ->and($mapper->mapGenreTags('Reise- und Wildnisabenteuer'))->toBe([
            ['slug' => 'exploration', 'name' => 'Erkundung'],
        ])
        ->and($mapper->mapGenres('Reise'))->toBe(['reise'])
        ->and($mapper->mapGenres('Mysterienabenteuer'))->not->toContain('sonstiges')
        ->and($mapper->mapGenres('Reiseabenteuer, Suchqueste'))->not->toContain('sonstiges');
});

it('maps havena to a point location and unknown places as incomplete traveling', function (): void {
    $mapper = wikiMapper();

    $havena = $mapper->mapLocation('Havena, Albernia');
    expect($havena['location_type'])->toBe('point')
        ->and($havena['primary_location'])->toBe('havena')
        ->and($havena['primary_region'])->toBe('nordmarken')
        ->and($havena['unmapped'])->toBeFalse();

    $unknown = $mapper->mapLocation('Unbekanntes Nebeltal ohne Alias');
    expect($unknown['unmapped'])->toBeTrue()
        ->and($unknown['location_type'])->toBe('traveling')
        ->and($unknown['primary_region'])->toBe('mittelaventurien')
        ->and($unknown['regions'])->toBe(['mittelaventurien']);

    $havenaAdventure = $mapper->map([
        'catalog_number' => '(VA02)',
        'title' => 'Der Vampir von Havena',
        'wiki_path' => '/wiki/Der_Vampir_von_Havena',
        'rules_raw' => 'DSA5',
        'place_raw' => 'Havena, Albernia',
        'genre_raw' => 'Detektivgeschichte',
        'complexity_raw' => 'gering',
        'experience_raw' => 'Einsteiger',
        'page_count' => 32,
        'series_key' => 'va-reihe',
        'short_description_raw' => 'Im Hafen von Havena verschwinden nächtens Schiffer und Händler. Die Helden folgen Gerüchten.',
    ]);
    expect($havenaAdventure['adventure']['primary_location'])->toBe('havena')
        ->and($havenaAdventure['adventure']['place_label'])->toBe('Havena, Albernia')
        ->and($havenaAdventure['adventure']['incomplete'])->toBeFalse();

    $unknownAdventure = $mapper->map([
        'catalog_number' => '',
        'title' => 'Nebeltal',
        'wiki_path' => '/wiki/Nebeltal',
        'rules_raw' => 'DSA5',
        'place_raw' => 'Unbekanntes Nebeltal ohne Alias',
        'genre_raw' => 'Detektivgeschichte',
        'complexity_raw' => 'gering',
        'experience_raw' => 'Einsteiger',
        'page_count' => 32,
        'series_key' => 'va-reihe',
        'short_description_raw' => 'Im Nebeltal wartet eine Aufgabe, deren Ort der Katalog noch nicht kennt und die Helden suchen.',
    ]);
    expect($unknownAdventure['adventure']['primary_location'])->toBeNull()
        ->and($unknownAdventure['adventure']['primary_region'])->toBe('mittelaventurien')
        ->and($unknownAdventure['adventure']['place_label'])->toBe('Unbekanntes Nebeltal ohne Alias')
        ->and($unknownAdventure['adventure']['incomplete'])->toBeTrue();
});

it('uses wiki short descriptions and marks complete when location is mapped', function (): void {
    $mapped = wikiMapper()->map([
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
        'short_description_raw' => 'Im Hafen von Havena verschwinden nächtens Schiffer und Händler. Die Helden folgen Gerüchten.',
    ]);

    $adventure = $mapped['adventure'];

    expect($adventure['incomplete'])->toBeFalse()
        ->and($adventure['source_note'])->toBeNull()
        ->and($adventure['short_description'])->toContain('Hafen von Havena')
        ->and($adventure['rule_version'])->toBe('dsa5')
        ->and($adventure['series'])->toBe('va-reihe')
        ->and($adventure['slug'])->toContain('va02')
        ->and($adventure['wiki_url'])->toBe('https://de.wiki-aventurica.de/wiki/Der_Vampir_von_Havena')
        ->and($adventure['primary_location'])->toBe('havena');
});

it('strips editorial soft-note prefixes from short descriptions', function (): void {
    $mapped = wikiMapper()->map([
        'catalog_number' => '(VA010)',
        'title' => 'Klingen der Nacht',
        'wiki_path' => '/wiki/Klingen_der_Nacht',
        'rules_raw' => 'DSA5',
        'place_raw' => 'Havena, Albernia',
        'genre_raw' => 'Mystery',
        'complexity_raw' => 'gering',
        'experience_raw' => 'unerfahren bis kompetent',
        'page_count' => null,
        'series_key' => 'va-reihe',
        'short_description_raw' => '> Auflage klären > Seitenzahl fehlt Unter dem Eis verborgen erhebt sich eine neue Gefahr für Havena und die Helden.',
    ]);

    expect($mapped['adventure']['short_description'])
        ->toStartWith('Unter dem Eis')
        ->not->toContain('Seitenzahl fehlt')
        ->not->toContain('Auflage klären');
});

it('stays incomplete without a wiki short description', function (): void {
    $mapped = wikiMapper()->map([
        'catalog_number' => '(VA01)',
        'title' => 'Offenbarung des Himmels',
        'wiki_path' => '/wiki/Offenbarung_des_Himmels',
        'rules_raw' => 'DSA5',
        'place_raw' => 'Havena',
        'genre_raw' => 'Detektivgeschichte',
        'complexity_raw' => 'gering',
        'experience_raw' => 'Einsteiger',
        'page_count' => 32,
        'series_key' => 'va-reihe',
        'short_description_raw' => null,
    ]);

    expect($mapped['adventure']['incomplete'])->toBeTrue()
        ->and($mapped['adventure']['short_description'])->toBe('-')
        ->and($mapped['adventure']['source_note'])->toContain('Kurzbeschreibung');
});
