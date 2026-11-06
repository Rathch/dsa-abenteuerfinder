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

it('returns null for empty or boilerplate-only html', function (): void {
    $parser = new WikiBlurbParser;

    expect($parser->extract(''))->toBeNull()
        ->and($parser->extract('<p>Andere Sprachversionen: en</p>'))->toBeNull();
});
