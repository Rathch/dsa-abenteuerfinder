<?php

declare(strict_types=1);

use App\Services\WikiHarvest\WikiBlurbParser;

it('extracts klappentext paragraphs and builds klappentext urls', function (): void {
    $parser = new WikiBlurbParser;
    $html = (string) file_get_contents(base_path('tests/Fixtures/wiki/klappentext-sample.html'));

    $text = $parser->extract($html);

    expect($text)->toBeString()
        ->and($text)->toContain('Im Hafen von Havena')
        ->and($text)->not->toContain('Andere Sprachversionen')
        ->and($parser->klappentextUrl('/wiki/Der_Vampir_von_Havena'))
        ->toBe('https://de.wiki-aventurica.de/wiki/Der_Vampir_von_Havena/Klappentext');
});

it('reads a klappentext stored in a table under the article heading', function (): void {
    $parser = new WikiBlurbParser;
    $html = (string) file_get_contents(base_path('tests/Fixtures/wiki/article-klappentext-table.html'));

    $text = $parser->extractFromArticle($html);

    expect($text)->toBeString()
        ->and($text)->toContain('Yol-Ghurmak')
        ->and($text)->toContain('Weltmaschine')
        ->and($text)->not->toContain('Ulisses-Disclaimer')
        ->and($text)->not->toContain('als Roman erscheinen');
});

it('reads a kurzbeschreibung made of paragraphs after the heading', function (): void {
    $parser = new WikiBlurbParser;
    $html = '<h2 id="Kurzbeschreibung">Kurzbeschreibung</h2><p>Im Hafen von Havena verschwinden nächtens Schiffer und Händler.</p><h2 id="Klappentext">Klappentext</h2><p>Yol-Ghurmak. Ein Name, der Aventuriern das Blut gefrieren lässt und die Helden ruft.</p>';

    expect($parser->kurzbeschreibungFromArticle($html))->toContain('Hafen von Havena')
        ->and($parser->klappentextFromArticle($html))->toContain('Yol-Ghurmak')
        ->and($parser->extractFromArticle($html))->toContain('Yol-Ghurmak');
});

it('returns null for empty or boilerplate-only html', function (): void {
    $parser = new WikiBlurbParser;

    expect($parser->extract(''))->toBeNull()
        ->and($parser->extract('<p>Andere Sprachversionen: en</p>'))->toBeNull()
        ->and($parser->extract('<p>BESCHREIBUNG DES ABENTEUERS</p>'))->toBeNull()
        ->and($parser->extract('<p>Die Krone der Königin/Klappentext</p>'))->toBeNull()
        ->and($parser->extract('<p>Kurzbeschreibung aus Wiki Aventurica steht noch aus; Metadaten wurden übernommen.</p>'))->toBeNull();
});
