<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiLinkChecker;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

it('reports a wiki url whose page is missing and keeps an existing page', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'de.wiki-aventurica.de/de/api.php' => Http::response([
            'query' => [
                'pages' => [
                    '1' => ['pageid' => 1, 'ns' => 0, 'title' => 'Offenbarung des Himmels'],
                    '-1' => ['ns' => 0, 'title' => 'Khunchoms verlorene Karawane', 'missing' => ''],
                ],
            ],
        ]),
    ]);

    $missing = (new WikiLinkChecker)->missingPages([
        'https://de.wiki-aventurica.de/wiki/Offenbarung_des_Himmels',
        'https://de.wiki-aventurica.de/wiki/Khunchoms_verlorene_Karawane',
        'https://example.test/wiki/Fremd',
    ]);

    expect($missing)->toBe([
        'https://example.test/wiki/Fremd',
        'https://de.wiki-aventurica.de/wiki/Khunchoms_verlorene_Karawane',
    ]);

    Http::assertSent(function (Request $request): bool {
        parse_str($request->body(), $fields);

        return ($fields['titles'] ?? '') === 'Offenbarung des Himmels|Khunchoms verlorene Karawane'
            && ($fields['redirects'] ?? '') === '1';
    });
});

it('treats a redirect as a page that exists', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'de.wiki-aventurica.de/de/api.php' => Http::response([
            'query' => [
                'redirects' => [
                    ['from' => 'Die Excursion', 'to' => 'Die Geister, die ich rief'],
                ],
                'pages' => [
                    '5' => ['pageid' => 5, 'ns' => 0, 'title' => 'Die Geister, die ich rief'],
                ],
            ],
        ]),
    ]);

    expect((new WikiLinkChecker)->missingPages([
        'https://de.wiki-aventurica.de/wiki/Die_Excursion',
    ]))->toBe([]);
});

it('sends a title that contains a pipe in its own request', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'de.wiki-aventurica.de/de/api.php' => Http::response([
            'query' => [
                'pages' => [
                    '1' => ['pageid' => 1, 'ns' => 0, 'title' => 'Foo|Bar'],
                    '2' => ['pageid' => 2, 'ns' => 0, 'title' => 'Alpha'],
                ],
            ],
        ]),
    ]);

    expect((new WikiLinkChecker)->missingPages([
        'https://de.wiki-aventurica.de/wiki/Foo%7CBar',
        'https://de.wiki-aventurica.de/wiki/Alpha',
    ]))->toBe([]);

    Http::assertSentCount(2);
});

it('rejects a wiki response that carries an api error', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'de.wiki-aventurica.de/de/api.php' => Http::response([
            'error' => ['code' => 'maxlag', 'info' => 'Waiting for maxlag'],
        ]),
    ]);

    expect(fn () => (new WikiLinkChecker)->missingPages([
        'https://de.wiki-aventurica.de/wiki/Offenbarung_des_Himmels',
    ]))->toThrow(RuntimeException::class);
});

it('gives up when the wiki connection fails', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'de.wiki-aventurica.de/de/api.php' => Http::failedConnection(),
    ]);

    expect(fn () => (new WikiLinkChecker)->missingPages([
        'https://de.wiki-aventurica.de/wiki/Offenbarung_des_Himmels',
    ]))->toThrow(ConnectionException::class);
});

it('classifies the catalog wiki urls from the recorded missing list', function (): void {
    Http::preventStrayRequests();

    /** @var array{adventures: list<array{slug: string, wiki_url: string}>} $catalog */
    $catalog = json_decode((string) file_get_contents(database_path('data/adventures.json')), true, flags: JSON_THROW_ON_ERROR);
    /** @var list<string> $expected */
    $expected = json_decode((string) file_get_contents(base_path('tests/Fixtures/wiki/catalog-wiki-urls-without-page.json')), true, flags: JSON_THROW_ON_ERROR);
    $expectedLookup = array_fill_keys($expected, true);

    $byUrl = [];
    /** @var array<string, true> $missingTitles */
    $missingTitles = [];
    foreach ($catalog['adventures'] as $adventure) {
        $byUrl[$adventure['wiki_url']] = $adventure['slug'];
        if (! isset($expectedLookup[$adventure['slug']])) {
            continue;
        }

        $path = parse_url($adventure['wiki_url'], PHP_URL_PATH);
        if (! is_string($path) || ! str_starts_with($path, '/wiki/') || $path === '/wiki/') {
            continue;
        }

        $title = str_replace('_', ' ', rawurldecode(substr($path, strlen('/wiki/'))));
        $missingTitles[mb_strtolower($title)] = true;
    }

    Http::fake(function (Request $request) use ($missingTitles) {
        parse_str($request->body(), $fields);
        $titles = explode('|', (string) ($fields['titles'] ?? ''));
        $pages = [];
        foreach ($titles as $index => $title) {
            $key = mb_strtolower(str_replace('_', ' ', $title));
            $pages[(string) ($index + 1)] = isset($missingTitles[$key])
                ? ['ns' => 0, 'title' => $title, 'missing' => '']
                : ['pageid' => $index + 1, 'ns' => 0, 'title' => $title];
        }

        return Http::response([
            'query' => [
                'pages' => $pages,
            ],
        ]);
    });

    $disclaimer = 'https://de.wiki-aventurica.de/wiki/Wiki_Aventurica:Ulisses-Disclaimer';
    $missing = (new WikiLinkChecker)->missingPages([...array_keys($byUrl), $disclaimer]);
    $missingSlugs = array_map(fn (string $url): string => $byUrl[$url], $missing);

    sort($missingSlugs);
    sort($expected);

    expect($missing)->not->toContain($disclaimer)
        ->and($missingSlugs)->toBe($expected);

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'de.wiki-aventurica.de/de/api.php'));
});
