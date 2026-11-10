<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiIndexTableParser;

it('parses every VA-Reihe row, including older editions', function (): void {
    $html = (string) file_get_contents(base_path('tests/Fixtures/wiki/va-reihe-sample.html'));
    $rows = (new WikiIndexTableParser)->parse($html, 'va-reihe');

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['title'])->toBe('Offenbarung des Himmels')
        ->and($rows[0]['wiki_path'])->toBe('/wiki/Offenbarung_des_Himmels')
        ->and($rows[0]['rules_raw'])->toBe('DSA5')
        ->and($rows[0]['page_count'])->toBe(64)
        ->and($rows[0]['series_key'])->toBe('va-reihe')
        ->and($rows[1]['title'])->toBe('Der Vampir von Havena')
        ->and($rows[2]['title'])->toBe('Altes Abenteuer')
        ->and($rows[2]['rules_raw'])->toBe('DSA3');
});

it('keeps rows that have no rules column', function (): void {
    $html = <<<'HTML'
    <table class="wikitable">
    <tr><th>Nr.</th><th>Abenteuertitel</th><th>Ort</th></tr>
    <tr><td>1</td><td><a href="/wiki/Beispiel">Beispiel ohne Regeln</a></td><td>Gareth</td></tr>
    </table>
    HTML;

    $rows = (new WikiIndexTableParser)->parse($html, 'inoffiziell', 'Inoffizielle Abenteuer');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['title'])->toBe('Beispiel ohne Regeln')
        ->and($rows[0]['rules_raw'])->toBe('')
        ->and($rows[0]['place_raw'])->toBe('Gareth')
        ->and($rows[0]['series_key'])->toBe('inoffiziell')
        ->and($rows[0]['series_name'])->toBe('Inoffizielle Abenteuer');
});

it('marks an empty genre cell when the table has a genre column', function (): void {
    $html = <<<'HTML'
    <table class="wikitable">
    <tr><th>Name</th><th>Genre</th></tr>
    <tr><td><a href="/wiki/Leer">Leeres Genre</a></td><td></td></tr>
    </table>
    HTML;

    $rows = (new WikiIndexTableParser)->parse($html, 'a-reihe');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['has_genre_column'])->toBeTrue()
        ->and($rows[0]['genre_raw'])->toBe('');
});

it('marks a table without a genre column', function (): void {
    $html = <<<'HTML'
    <table class="wikitable">
    <tr><th>Name</th><th>Ort</th></tr>
    <tr><td><a href="/wiki/Ohne">Ohne Spalte</a></td><td>Gareth</td></tr>
    </table>
    HTML;

    $rows = (new WikiIndexTableParser)->parse($html, 'a-reihe');

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['has_genre_column'])->toBeFalse()
        ->and($rows[0]['genre_raw'])->toBe('')
        ->and($rows[0]['place_raw'])->toBe('Gareth');
});

it('names a series from the heading above its table', function (): void {
    $html = <<<'HTML'
    <h2><span class="mw-headline" id="Basis">B - Basis-Abenteuer</span></h2>
    <table class="wikitable">
    <tr><th>Name</th><th>Regeln</th></tr>
    <tr><td><a href="/wiki/Basis">Basisabenteuer</a></td><td>DSA5</td></tr>
    </table>
    HTML;

    $rows = (new WikiIndexTableParser)->parse($html, 'weitere-reihen', 'Weitere Reihen', true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['series_name'])->toBe('B - Basis-Abenteuer')
        ->and($rows[0]['series_key'])->toBe('b-basis-abenteuer');
});

it('ignores skin headings outside the article body', function (): void {
    $html = <<<'HTML'
    <h3>Seitenaktionen</h3>
    <div class="mw-parser-output">
    <table class="wikitable">
    <tr><th>Name</th><th>Regeln</th></tr>
    <tr><td><a href="/wiki/Hardcover">Hardcover</a></td><td>DSA4</td></tr>
    </table>
    </div>
    HTML;

    $rows = (new WikiIndexTableParser)->parse($html, 'hw-reihe', 'HW-Reihe', true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['series_name'])->toBe('HW-Reihe')
        ->and($rows[0]['series_key'])->toBe('hw-reihe');
});

it('names a series from the current wiki heading markup', function (): void {
    $html = <<<'HTML'
    <div class="mw-heading mw-heading3"><h3 id="B_-_Basis-Abenteuer">B - Basis-Abenteuer</h3></div>
    <table class="wikitable">
    <tr><th>Name</th><th>Regeln</th></tr>
    <tr><td><a href="/wiki/Basis">Basisabenteuer</a></td><td>DSA5</td></tr>
    </table>
    HTML;

    $rows = (new WikiIndexTableParser)->parse($html, 'weitere-reihen', 'Weitere Reihen', true);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['series_name'])->toBe('B - Basis-Abenteuer')
        ->and($rows[0]['series_key'])->toBe('b-basis-abenteuer');
});

it('parses A-Reihe rows for every edition', function (): void {
    $html = (string) file_get_contents(base_path('tests/Fixtures/wiki/a-reihe-sample.html'));
    $rows = (new WikiIndexTableParser)->parse($html, 'a-reihe');

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['title'])->toBe('Beispiel DSA4 Abenteuer')
        ->and($rows[0]['rules_raw'])->toBe('DSA4.1')
        ->and($rows[0]['place_raw'])->toContain('Kuslik')
        ->and($rows[1]['title'])->toBe('Nur DSA2')
        ->and($rows[1]['rules_raw'])->toBe('DSA2');
});
