<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiIndexTableParser;

it('parses VA-Reihe tables and keeps only DSA4/DSA5 rows', function (): void {
    $html = (string) file_get_contents(base_path('tests/Fixtures/wiki/va-reihe-sample.html'));
    $rows = (new WikiIndexTableParser)->parse($html, 'va-reihe');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['title'])->toBe('Offenbarung des Himmels')
        ->and($rows[0]['wiki_path'])->toBe('/wiki/Offenbarung_des_Himmels')
        ->and($rows[0]['rules_raw'])->toBe('DSA5')
        ->and($rows[0]['page_count'])->toBe(64)
        ->and($rows[0]['series_key'])->toBe('va-reihe')
        ->and($rows[1]['title'])->toBe('Der Vampir von Havena')
        ->and(array_column($rows, 'title'))->not->toContain('Altes Abenteuer');
});

it('parses A-Reihe DSA4.1 rows', function (): void {
    $html = (string) file_get_contents(base_path('tests/Fixtures/wiki/a-reihe-sample.html'));
    $rows = (new WikiIndexTableParser)->parse($html, 'a-reihe');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['title'])->toBe('Beispiel DSA4 Abenteuer')
        ->and($rows[0]['rules_raw'])->toBe('DSA4.1')
        ->and($rows[0]['place_raw'])->toContain('Kuslik');
});
