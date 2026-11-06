<?php

use App\Domain\Matching\MatchResult;
use App\Livewire\AdventureWizard;
use App\Services\AdventureImportService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    (new AdventureImportService)->import();
});

/**
 * Füllt alle Pflichtfelder inkl. Region-/Genre-Bestätigung und sendet ab.
 */
function completeWizard(Testable $component): Testable
{
    return $component
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlug', 'nordmarken')
        ->set('regionConfirmed', true)
        ->set('exotic', 'no')
        ->set('timeframe', 'evening_short')
        ->set('genres', ['investigation'])
        ->set('genresDone', true)
        ->set('gmExperience', 'einsteiger')
        ->set('groupSize', '3-5')
        ->call('submit');
}

it('renders only the first question on the home page', function (): void {
    $this->get('/')
        ->assertOk()
        ->assertSee('Zum Hauptinhalt springen')
        ->assertSee('Deine Wünsche')
        ->assertSee('Nach welchem Regelwerk')
        ->assertSee('Eine Frage nach der anderen')
        ->assertDontSee('Schritt 1 von 8')
        ->assertDontSee('Wie erfahren ist die Spielleitung')
        ->assertSee('id="main-content"', false)
        ->assertSee('data-theme-toggle', false);

    $this->get('/impressum')->assertOk()->assertSee('Christian Rath-Ulrich')->assertSee('kontakt@rath-ulrich.de');
    $this->get('/datenschutz')->assertOk()->assertSee('Kein Konto')->assertSee('Kein Tracking');
});

it('links to the legal pages from the footer', function (): void {
    $this->get('/')
        ->assertSee(route('impressum'), false)
        ->assertSee(route('datenschutz'), false);
});

it('advances focus after answering a radio question', function (): void {
    Livewire::test(AdventureWizard::class)
        ->assertSet('focusField', 'ruleset')
        ->set('ruleset', 'dsa5')
        ->assertSet('focusField', 'experienceLevel')
        ->assertSee('Heldinnen und Helden')
        ->assertDontSee('Nach welchem Regelwerk');
});

it('shows place controls after region choice and advances on confirm', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'einsteiger')
        ->assertSet('focusField', 'region')
        ->set('regionSlug', 'nordmarken')
        ->assertSet('focusField', 'region')
        ->assertSee('Ohne Ort weiter')
        ->call('skipPlaceAndConfirmRegion')
        ->assertSet('regionConfirmed', true)
        ->assertSet('focusField', 'exotic');
});

it('yields recommendations after submitting from review', function (): void {
    $component = completeWizard(Livewire::test(AdventureWizard::class))
        ->assertSet('showResults', true)
        ->assertSee('Deine Empfehlungen')
        ->assertSee('Warum passt es?')
        ->assertSee('(öffnet in neuem Tab)')
        ->assertSee('rel="noopener noreferrer"', false);

    $result = $component->instance()->matchResult;

    expect($result)->toBeInstanceOf(MatchResult::class)
        ->and(count($result->recommendations))->toBeGreaterThanOrEqual(3)
        ->and(count($result->recommendations))->toBeLessThanOrEqual(6);

    $component->assertSee($result->recommendations[0]->adventure->title);
});

it('shows fallback notes when wishes had to be relaxed', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'any')
        ->set('experienceLevel', 'meister')
        ->set('regionSlug', 'myranor')
        ->set('regionConfirmed', true)
        ->set('exotic', 'extra_aventurian')
        ->set('timeframe', 'campaign')
        ->set('genres', ['exploration'])
        ->set('genresDone', true)
        ->set('gmExperience', 'meister')
        ->set('groupSize', '2')
        ->call('submit')
        ->assertSet('showResults', true);

    $result = $component->instance()->matchResult;
    expect($result)->toBeInstanceOf(MatchResult::class)
        ->and($result->recommendations)->not->toBeEmpty();

    if ($result->usedFallback()) {
        $component->assertSee('Hinweis zur Auswahl');
    }
});

it('blocks submit without required fields and focuses the error summary', function (): void {
    Livewire::test(AdventureWizard::class)
        ->call('submit')
        ->assertHasErrors([
            'ruleset' => 'required',
            'experienceLevel' => 'required',
            'regionSlug' => 'required',
            'exotic' => 'required',
            'timeframe' => 'required',
            'gmExperience' => 'required',
            'groupSize' => 'required',
        ])
        ->assertSee('Bitte prüfe deine Angaben')
        ->assertDispatched('wizard-focus', target: 'error-summary')
        ->assertSet('showResults', false);
});

it('scrubs unknown option values before they reach validation', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'gott')
        ->assertSet('experienceLevel', '')
        ->set('regionSlug', 'egal')
        ->set('regionConfirmed', true)
        ->set('exotic', 'any')
        ->set('timeframe', 'evening_short')
        ->set('genresDone', true)
        ->set('gmExperience', 'einsteiger')
        ->set('groupSize', 'egal')
        ->call('submit')
        ->assertHasErrors(['experienceLevel' => 'required'])
        ->assertSet('showResults', false);
});

it('allows submitting without selecting genres', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlug', 'egal')
        ->set('regionConfirmed', true)
        ->set('exotic', 'any')
        ->set('timeframe', 'evening_long')
        ->set('genres', [])
        ->set('genresDone', true)
        ->set('gmExperience', 'einsteiger')
        ->set('groupSize', 'egal')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('showResults', true);
});

it('returns location suggestions for the autocomplete', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlug', 'egal')
        ->set('placeQuery', 'ga')
        ->assertSee('Gareth')
        ->assertSee('Mittelaventurien');

    $suggestions = $component->instance()->suggestions;

    expect(array_column($suggestions, 'slug'))->toContain('gareth')
        ->and($component->get('placeSlug'))->toBe('');

    $component->set('placeQuery', 'zzzz')
        ->assertSee('Keine passenden Orte gefunden.')
        ->assertSet('placeSlug', '');

    expect($component->instance()->suggestions)->toBe([]);
});

it('ignores apostrophes and umlauts in the autocomplete', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlug', 'egal')
        ->set('placeQuery', 'alan');

    expect(array_column($component->instance()->suggestions, 'slug'))->toContain('al-anfa');
});

it('selects a suggested place and adopts its region when region is egal', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlug', 'egal')
        ->set('placeQuery', 'gar')
        ->call('selectPlace', 'gareth')
        ->assertSet('placeSlug', 'gareth')
        ->assertSet('placeQuery', 'Gareth')
        ->assertSet('regionSlug', 'mittelaventurien')
        ->assertSee('Ausgewählter Ort:')
        ->call('clearPlace')
        ->assertSet('placeSlug', '')
        ->assertSet('placeQuery', '');
});

it('rejects a typed place that is not in the list', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlug', 'egal')
        ->set('regionConfirmed', true)
        ->set('exotic', 'any')
        ->set('timeframe', 'evening_long')
        ->set('genresDone', true)
        ->set('gmExperience', 'einsteiger')
        ->set('groupSize', 'egal')
        ->set('placeQuery', 'Nirgendwo')
        ->call('submit')
        ->assertHasErrors(['placeQuery'])
        ->assertSet('showResults', false);
});

it('accepts an exactly typed place name without clicking a suggestion', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlug', 'egal')
        ->set('regionConfirmed', true)
        ->set('exotic', 'any')
        ->set('timeframe', 'evening_long')
        ->set('genresDone', true)
        ->set('gmExperience', 'einsteiger')
        ->set('groupSize', 'egal')
        ->set('placeQuery', 'Gareth')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('showResults', true)
        ->assertSet('placeSlug', 'gareth');
});

it('keeps answers in the session across component instances and can restart', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'fortgeschritten')
        ->set('groupSize', '6+');

    Livewire::test(AdventureWizard::class)
        ->assertSet('experienceLevel', 'fortgeschritten')
        ->assertSet('groupSize', '6+')
        ->assertSet('ruleset', 'dsa5')
        ->call('restart')
        ->assertSet('experienceLevel', '')
        ->assertSet('ruleset', '')
        ->assertSet('focusField', 'ruleset');

    Livewire::test(AdventureWizard::class)->assertSet('experienceLevel', '')->assertSet('ruleset', '');
});

it('restores the results view from the session', function (): void {
    completeWizard(Livewire::test(AdventureWizard::class));

    Livewire::test(AdventureWizard::class)
        ->assertSet('showResults', true)
        ->assertSee('Warum passt es?')
        ->call('editAnswers')
        ->assertSet('showResults', false)
        ->assertSet('focusField', 'review')
        ->assertSet('experienceLevel', 'einsteiger')
        ->assertSee('Passt das so?');
});

it('maps wizard answers to the domain answers object', function (): void {
    $answers = Livewire::test(AdventureWizard::class)
        ->set('experienceLevel', 'erfahren')
        ->set('groupSize', 'egal')
        ->set('timeframe', 'multi')
        ->set('regionSlug', 'egal')
        ->set('regionConfirmed', true)
        ->set('exotic', 'any')
        ->set('genres', ['horror'])
        ->set('ruleset', 'any')
        ->set('gmExperience', 'meister')
        ->instance()
        ->toAnswers();

    expect($answers->groupSize)->toBeNull()
        ->and($answers->regionSlug)->toBeNull()
        ->and($answers->placeSlug)->toBeNull()
        ->and($answers->rules)->toBe('any')
        ->and($answers->genres)->toBe(['horror'])
        ->and($answers->gmExperience)->toBe('meister');
});

it('hides outside-aventuria exotic after choosing an aventurian region', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlug', 'suedaventurien')
        ->call('skipPlaceAndConfirmRegion')
        ->assertSet('focusField', 'exotic');

    $component->assertDontSee('Außerhalb Aventuriens');

    $component
        ->set('exotic', 'extra_aventurian')
        ->assertSet('exotic', '');
});

it('scrubs exotic when an aventurian region makes it invalid', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('exotic', 'extra_aventurian')
        ->set('regionSlug', 'suedaventurien')
        ->assertSet('exotic', '');
});

it('does not suggest places outside the selected region', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlug', 'suedaventurien')
        ->set('placeQuery', 'ha');

    expect($component->instance()->suggestions)->toBe([]);

    $component
        ->set('regionSlug', 'nordmarken')
        ->set('placeQuery', 'ha');

    expect(array_column($component->instance()->suggestions, 'slug'))->toContain('havena');
});

it('clears a place when the region no longer matches', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlug', 'nordmarken')
        ->set('placeQuery', 'ha')
        ->call('selectPlace', 'havena')
        ->assertSet('placeSlug', 'havena')
        ->set('regionSlug', 'suedaventurien')
        ->assertSet('placeSlug', '')
        ->assertSet('placeQuery', '');
});

it('lets summary chips jump back to a previous field', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('ruleset', 'dsa5')
        ->set('experienceLevel', 'einsteiger')
        ->call('editField', 'ruleset')
        ->assertSet('focusField', 'ruleset')
        ->assertSee('Nach welchem Regelwerk')
        ->assertSee('Bisher gewählt');
});

it('explains which rulebooks exist in the catalog', function (): void {
    Livewire::test(AdventureWizard::class)
        ->assertSee('Im Katalog: DSA 5, DSA 4.1 und editionsunabhängige Abenteuer')
        ->assertSee('Nach welchem Regelwerk');
});
