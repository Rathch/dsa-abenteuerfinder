<?php

use App\Domain\Matching\MatchRecommendation;
use App\Services\AdventureImportService;
use App\Services\AdventureMatchService;
use App\Services\LocationAutocompleteService;

beforeEach(function (): void {
    (new AdventureImportService)->import();
    $this->service = app(AdventureMatchService::class);
});

/**
 * @param  list<MatchRecommendation>  $recommendations
 * @return list<string>
 */
function matchedSlugs(array $recommendations): array
{
    return array_map(static fn (MatchRecommendation $r): string => $r->adventure->slug, $recommendations);
}

it('loads listed adventures, including incomplete ones', function (): void {
    $candidates = $this->service->candidates();
    $slugs = array_map(static fn ($c): string => $c->slug, $candidates);

    expect($candidates)->toBeGreaterThanOrEqual(19)
        ->and($slugs)->toContain('der-weisse-see-va004', 'das-blaue-buch-va006');

    foreach ($candidates as $candidate) {
        expect($candidate->missingFromWiki)->toBeFalse()->and($candidate->genres)->not->toBeEmpty();
    }
});

it('maps relations onto candidates', function (): void {
    $candidates = collect($this->service->candidates())->keyBy('slug');

    $havener = $candidates['der-vampir-von-havena-va002'];
    $corridor = $candidates['siebenstreich-a079'];

    expect($havener->primaryPlaceSlug)->toBe('havena')
        ->and($havener->primaryPlaceName)->toBe('Havena')
        ->and($havener->primaryRegionSlug)->toBe('nordmarken')
        ->and($havener->seriesSlug)->toBe('va-reihe')
        ->and($havener->seriesName)->toBe('VA-Reihe')
        ->and($havener->genres)->toContain('investigation')
        ->and($corridor->regionSlugs)->toContain('mittelaventurien', 'tulamidenlande');
});

it('recommends a beginner adventure in Havena first for a matching wizard run', function (): void {
    $result = $this->service->recommend(wizardAnswers([
        'experienceLevel' => 'einsteiger',
        'groupSize' => '3-5',
        'timeframe' => 'evening_short',
        'regionSlug' => 'nordmarken',
        'placeSlug' => 'havena',
        'exotic' => 'no',
        'genres' => ['investigation', 'seafaring'],
        'rules' => 'dsa5',
        'gmExperience' => 'einsteiger',
    ]));

    $first = $result->recommendations[0];

    expect($result->recommendations)->not->toBeEmpty()
        ->and($first->adventure->primaryPlaceSlug)->toBe('havena')
        ->and($first->adventure->experienceLevel->value)->toBe('einsteiger')
        ->and($first->score)->toBeGreaterThan(85)
        ->and($first->reasons)->toContain('Geeignet für Einsteiger.');
});

it('returns three to six recommendations without incomplete adventures', function (): void {
    $result = $this->service->recommend(wizardAnswers([
        'experienceLevel' => 'erfahren',
        'timeframe' => 'multi',
        'gmExperience' => 'erfahren',
        'genres' => ['intrigue'],
    ]));

    expect(count($result->recommendations))->toBeGreaterThanOrEqual(3)->toBeLessThanOrEqual(6)
        ->and(matchedSlugs($result->recommendations))
        ->not->toContain('der-weisse-see-va004', 'das-blaue-buch-va006');

    foreach ($result->recommendations as $recommendation) {
        expect($recommendation->reasons)->not->toBeEmpty()
            ->and($recommendation->adventure->wikiUrl)->toStartWith('https://');
    }
});

it('keeps at most two adventures per series', function (): void {
    $result = $this->service->recommend(wizardAnswers([
        'experienceLevel' => 'fortgeschritten',
        'timeframe' => 'multi',
        'gmExperience' => 'fortgeschritten',
    ]));

    $perSeries = array_count_values(array_map(
        static fn (MatchRecommendation $r): string => $r->adventure->seriesSlug,
        $result->recommendations,
    ));

    expect(max($perSeries))->toBeLessThanOrEqual(2);
});

it('respects the rules filter against imported data', function (): void {
    $result = $this->service->recommend(wizardAnswers(['rules' => 'dsa4', 'experienceLevel' => 'fortgeschritten']));

    foreach ($result->recommendations as $recommendation) {
        expect($recommendation->adventure->ruleVersion->value)->toBeIn(['dsa4', 'editionunabhaengig']);
    }
});

it('returns recommendations for an extra-aventurian Myranor wish', function (): void {
    $result = $this->service->recommend(wizardAnswers([
        'experienceLevel' => 'erfahren',
        'timeframe' => 'multi',
        'regionSlug' => 'myranor',
        'exotic' => 'extra_aventurian',
        'gmExperience' => 'erfahren',
        'rules' => 'dsa5',
    ]));

    expect($result->recommendations)->not->toBeEmpty()
        ->and($result->recommendations[0]->adventure->wikiUrl)->toStartWith('https://de.wiki-aventurica.de/wiki/');
});

it('explains relaxed wishes when the data offers few matches', function (): void {
    $result = $this->service->recommend(wizardAnswers([
        'experienceLevel' => 'meister',
        'timeframe' => 'evening_short',
        'regionSlug' => 'myranor',
        'rules' => 'dsa4',
        'gmExperience' => 'meister',
    ]));

    // Mit großem Harvest-Katalog liefern Reiseabenteuer oft schon ≥3 Treffer ohne Region-Fallback.
    // Die Lockerungstexte selbst sind in MatchFinderTest abgedeckt.
    expect($result->recommendations)->not->toBeEmpty();
    if ($result->usedFallback()) {
        expect($result->fallbackNotes[0])->toStartWith('Region wurde ignoriert, weil');
    }
});

it('autocompletes places by prefix', function (): void {
    $autocomplete = app(LocationAutocompleteService::class);

    $ga = $autocomplete->search('Ga');
    $alanfa = $autocomplete->search('alan');
    $umlaut = $autocomplete->search('K');

    expect(array_column($ga, 'slug'))->toContain('gareth')
        ->and($alanfa[0]['slug'])->toBe('al-anfa')
        ->and(array_column($autocomplete->search('Khu'), 'slug'))->toContain('khunchom')
        ->and(array_column($autocomplete->search('Kus'), 'slug'))->toContain('kuslik')
        ->and($umlaut)->not->toBeEmpty()
        ->and($autocomplete->search('  '))->toBe([])
        ->and($autocomplete->search('xyz'))->toBe([])
        ->and($autocomplete->search('K', 1))->toHaveCount(1)
        ->and($autocomplete->search('K', 0))->toBe([])
        ->and(array_column($autocomplete->search('Nord'), 'slug'))->not->toContain('nordmarken');
});

it('autocompletes places whose names start with an umlaut-folded prefix', function (): void {
    $autocomplete = app(LocationAutocompleteService::class);

    expect($autocomplete->search('Pelagos'))->toHaveCount(1)
        ->and($autocomplete->search('pel')[0]['region_slug'])->toBe('myranor');
});

it('limits place autocomplete to the selected region', function (): void {
    $autocomplete = app(LocationAutocompleteService::class);

    expect(array_column($autocomplete->search('Ha', regionSlug: 'nordmarken'), 'slug'))->toContain('havena')
        ->and($autocomplete->search('Ha', regionSlug: 'suedaventurien'))->toBe([])
        ->and($autocomplete->regionSlugFor('havena'))->toBe('nordmarken');
});
