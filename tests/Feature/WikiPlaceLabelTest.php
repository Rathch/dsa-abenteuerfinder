<?php

declare(strict_types=1);

use App\Domain\Matching\LocationType;
use App\Domain\Matching\MatchRecommendation;
use App\Domain\Matching\MatchResult;
use App\Livewire\AdventureWizard;
use App\Services\AdventureImportService;
use App\Services\LocationAutocompleteService;
use ReflectionMethod;

it('shows an unknown wiki place on the result card and keeps the region', function (): void {
    $wizard = app(AdventureWizard::class);
    $cards = (new ReflectionMethod(AdventureWizard::class, 'cards'))->invoke($wizard, new MatchResult([
        new MatchRecommendation(adventureCandidate([
            'title' => 'Nebeltal',
            'primaryPlaceName' => null,
            'primaryRegionSlug' => 'mittelaventurien',
            'locationType' => LocationType::Traveling,
            'regionSlugs' => ['mittelaventurien'],
            'placeLabel' => 'Unbekanntes Nebeltal ohne Alias',
        ]), 80, []),
    ]), []);

    expect($cards[0]['location'])->toContain('Unbekanntes Nebeltal ohne Alias')
        ->and($cards[0]['location'])->toContain('Mittelaventurien');
});

it('keeps unknown wiki places out of the wizard place list', function (): void {
    (new AdventureImportService)->import();

    $places = app(LocationAutocompleteService::class);

    expect($places->search('Unbekanntes Nebeltal'))->toBe([])
        ->and(collect($places->search('Hav'))->pluck('name')->all())->toContain('Havena');
});
