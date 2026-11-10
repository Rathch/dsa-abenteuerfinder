<?php

use App\Domain\Matching\MatchResult;
use App\Livewire\AdventureWizard;
use App\Models\Adventure;
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
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlugs', ['nordmarken'])
        ->set('regionConfirmed', true)
        ->set('exotics', ['no'])
        ->set('exoticDone', true)
        ->set('timeframes', ['evening_short'])
        ->set('timeframeDone', true)
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
        ->assertSee('id="ruleset-multi"', false)
        ->assertSee('aria-describedby="ruleset-hint ruleset-multi"', false)
        ->assertDontSee('Nächste Frage.')
        ->assertSee('Eine Frage nach der anderen')
        ->assertDontSee('Schritt 1 von 8')
        ->assertDontSee('Wie erfahren ist die Spielleitung')
        ->assertSee('id="main-content"', false)
        ->assertSee('data-theme-toggle', false);

    $this->get('/impressum')->assertOk()->assertSee('Christian Rath-Ulrich')->assertSee('kontakt@rath-ulrich.de');
    $this->get('/datenschutz')->assertOk()->assertSee('Kein Konto')->assertSee('Kein Tracking');
});

it('shows catalog charts and the finder call to action', function (): void {
    $total = Adventure::query()->where('missing_from_wiki', false)->count();

    $this->get('/')
        ->assertOk()
        ->assertSee('Im Katalog')
        ->assertSee(number_format($total, 0, ',', '.'))
        ->assertSee('Regeln')
        ->assertSee('Erfahrung')
        ->assertSee('Genres')
        ->assertSee('Dauer')
        ->assertSee('Abenteuer finden')
        ->assertSee('role="img"', false)
        ->assertSee('data-slice="0"', false)
        ->assertSee('Tortendiagramm. Name, Anzahl und Anteil stehen in der Liste darunter.')
        ->assertSee('Prozent.', false)
        ->assertSee('id="abenteuer-finden"', false);
});

it('links to the legal pages from the footer', function (): void {
    $this->get('/')
        ->assertSee(route('impressum'), false)
        ->assertSee(route('datenschutz'), false);
});

it('shows restart under every question after the first', function (): void {
    Livewire::test(AdventureWizard::class)
        ->assertSet('focusField', 'ruleset')
        ->assertDontSee('Neu starten')
        ->set('rulesAny', true)
        ->call('continueRules')
        ->assertSet('focusField', 'experienceLevel')
        ->assertSee('Neu starten')
        ->call('restart')
        ->assertSet('focusField', 'ruleset')
        ->assertDontSee('Neu starten');
});

it('keeps egal as a checkbox until weiter is pressed', function (): void {
    Livewire::test(AdventureWizard::class)
        ->assertSet('focusField', 'ruleset')
        ->assertSee('type="checkbox"', false)
        ->assertSee('DSA 1')
        ->assertSee('DSA 4')
        ->assertSee('wire:model.live="rulesAny"', false)
        ->set('rules', ['dsa5'])
        ->set('rulesAny', true)
        ->assertSet('rulesAny', true)
        ->assertSet('rules', [])
        ->assertSet('rulesDone', false)
        ->assertSet('focusField', 'ruleset')
        ->call('continueRules')
        ->assertSet('focusField', 'experienceLevel')
        ->assertSee('Heldinnen und Helden');
});

it('keeps a hero experience until weiter is pressed', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('focusField', 'experienceLevel')
        ->call('continueExperience')
        ->assertSet('focusField', 'experienceLevel')
        ->assertHasErrors('experienceLevel')
        ->set('experienceLevel', 'einsteiger')
        ->assertSet('focusField', 'experienceLevel')
        ->assertSee('wire:click="continueExperience"', false)
        ->call('continueExperience')
        ->assertSet('focusField', 'exotic')
        ->assertSee('Wie exotisch darf das Abenteuer sein?')
        ->assertSee('Nur Aventurien');
});

it('replaces the previous hero experience', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->assertSet('experienceLevel', 'einsteiger')
        ->call('editField', 'experienceLevel')
        ->set('experienceLevel', 'meister')
        ->assertSet('experienceLevel', 'meister');
});

it('shows the optional place only for one region and skips it for several regions or egal', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('focusField', 'region')
        ->assertSet('focusField', 'region')
        ->set('regionSlugs', ['nordmarken'])
        ->assertSee('Havena')
        ->assertSee('Kein bestimmter Ort')
        ->assertSee('role="radiogroup"', false)
        ->assertSee('id="place-list" tabindex="-1"', false)
        ->assertSee('Riva')
        ->assertSee('Xorlosch')
        ->assertDontSee('Gareth')
        ->set('regionSlugs', ['bornland'])
        ->assertSee('Festum')
        ->assertDontSee('Havena')
        ->set('regionSlugs', ['nordmarken'])
        ->call('togglePlace', 'havena')
        ->assertSet('placeSlug', 'havena')
        ->assertSet('placeQuery', 'Havena')
        ->set('placeSlug', '')
        ->assertSet('placeQuery', '')
        ->set('placeSlug', 'havena')
        ->assertSet('placeQuery', 'Havena')
        ->assertSet('focusField', 'region')
        ->call('togglePlace', 'havena')
        ->assertSet('placeSlug', '')
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->call('confirmRegion')
        ->assertSet('regionConfirmed', true)
        ->assertSet('focusField', 'timeframe');

    Livewire::test(AdventureWizard::class)
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('focusField', 'region')
        ->set('regionSlugs', ['nordmarken', 'myranor'])
        ->assertDontSee('Havena')
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->call('confirmRegion')
        ->assertSet('placeSlug', '')
        ->assertSet('focusField', 'timeframe');

    Livewire::test(AdventureWizard::class)
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('regionSlugs', ['nordmarken'])
        ->set('regionConfirmed', false)
        ->set('focusField', 'region')
        ->set('placeSlug', 'havena')
        ->set('placeQuery', 'Havena')
        ->set('regionsAny', true)
        ->assertSet('regionsAny', true)
        ->assertSet('regionSlugs', [])
        ->assertSet('placeSlug', '')
        ->assertSet('regionConfirmed', false)
        ->assertSet('focusField', 'region')
        ->assertSee('wire:model.live="regionsAny"', false)
        ->assertDontSee('Havena')
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->call('confirmRegion')
        ->assertSet('regionConfirmed', true)
        ->assertSet('focusField', 'timeframe');
});

it('clears egal when a concrete region is chosen', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('regionsAny', true)
        ->assertSet('regionsAny', true)
        ->set('regionSlugs', ['nordmarken'])
        ->assertSet('regionsAny', false)
        ->assertSet('regionSlugs', ['nordmarken']);
});

it('keeps group size egal on the question until weiter', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('regionsAny', true)
        ->set('regionConfirmed', true)
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->set('timeframes', ['evening_short'])
        ->set('timeframeDone', true)
        ->set('genresDone', true)
        ->set('gmExperience', 'einsteiger')
        ->set('focusField', 'groupSize')
        ->set('groupSize', 'egal')
        ->assertSet('groupSize', 'egal')
        ->assertSet('focusField', 'groupSize')
        ->assertSee('value="egal"', false)
        ->assertDontSee('toggleGroupEgal')
        ->call('continueGroupSize')
        ->assertSet('focusField', 'review');
});

it('clears a chosen exotic when egal is checked and stays on the question', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('exotics', ['no'])
        ->set('exoticAny', true)
        ->assertSet('exotics', [])
        ->assertSet('exoticAny', true)
        ->assertSet('exoticDone', false)
        ->assertSet('focusField', 'ruleset');
});

it('yields recommendations after submitting from review', function (): void {
    Adventure::query()->update(['cover_url' => null]);

    $component = completeWizard(Livewire::test(AdventureWizard::class))
        ->assertSet('showResults', true)
        ->assertSee('Deine Empfehlungen')
        ->assertSee('Warum passt es?')
        ->assertSee('Klappentext von')
        ->assertSee('Quelle:')
        ->assertSee('https://de.wiki-aventurica.de/wiki/Wiki_Aventurica:Ulisses-Disclaimer', false)
        ->assertSee('(öffnet in neuem Tab)')
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSee('images/cover-fallback.svg', false);

    $result = $component->instance()->matchResult;

    expect($result)->toBeInstanceOf(MatchResult::class)
        ->and(count($result->recommendations))->toBeGreaterThanOrEqual(3)
        ->and(count($result->recommendations))->toBeLessThanOrEqual(6);

    $component->assertSee($result->recommendations[0]->adventure->title);
});

it('shows fallback notes when wishes had to be relaxed', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('experienceLevel', 'meister')
        ->set('regionSlugs', ['myranor'])
        ->set('regionConfirmed', true)
        ->set('exotics', ['extra_aventurian'])
        ->set('exoticDone', true)
        ->set('timeframes', ['campaign'])
        ->set('timeframeDone', true)
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
            'rulesDone' => 'accepted',
            'experienceLevel' => 'required',
            'regionConfirmed' => 'accepted',
            'exoticDone' => 'accepted',
            'timeframeDone' => 'accepted',
            'gmExperience' => 'required',
            'groupSize' => 'required',
        ])
        ->assertSee('Bitte prüfe deine Angaben')
        ->assertDispatched('wizard-focus', target: 'error-summary')
        ->assertSet('showResults', false);
});

it('scrubs unknown option values before they reach validation', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'gott')
        ->assertSet('experienceLevel', '')
        ->set('regionsAny', true)
        ->set('regionConfirmed', true)
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->set('timeframes', ['evening_short'])
        ->set('timeframeDone', true)
        ->set('genresDone', true)
        ->set('gmExperience', 'einsteiger')
        ->set('groupSize', 'egal')
        ->call('submit')
        ->assertHasErrors(['experienceLevel' => 'required'])
        ->assertSet('showResults', false);
});

it('allows submitting without selecting genres', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('regionsAny', true)
        ->set('regionConfirmed', true)
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->set('timeframes', ['evening_long'])
        ->set('timeframeDone', true)
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
        ->set('regionSlugs', ['mittelaventurien'])
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
        ->set('regionSlugs', ['suedaventurien'])
        ->set('placeQuery', 'alan');

    expect(array_column($component->instance()->suggestions, 'slug'))->toContain('al-anfa');
});

it('selects a suggested place inside the chosen region', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlugs', ['mittelaventurien'])
        ->set('placeQuery', 'gar')
        ->call('selectPlace', 'gareth')
        ->assertSet('placeSlug', 'gareth')
        ->assertSet('placeQuery', 'Gareth')
        ->assertSet('regionSlugs', ['mittelaventurien'])
        ->assertSee('Ausgewählter Ort:')
        ->call('clearPlace')
        ->assertSet('placeSlug', '')
        ->assertSet('placeQuery', '');
});

it('rejects a typed place that is not in the list', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('regionsAny', true)
        ->set('regionConfirmed', true)
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->set('timeframes', ['evening_long'])
        ->set('timeframeDone', true)
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
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlugs', ['mittelaventurien'])
        ->set('regionConfirmed', true)
        ->set('exoticAny', true)
        ->set('exoticDone', true)
        ->set('timeframes', ['evening_long'])
        ->set('timeframeDone', true)
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
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'fortgeschritten')
        ->set('groupSize', '6+');

    Livewire::test(AdventureWizard::class)
        ->assertSet('experienceLevel', 'fortgeschritten')
        ->assertSet('groupSize', '6+')
        ->assertSet('rules', ['dsa5'])
        ->call('restart')
        ->assertSet('experienceLevel', '')
        ->assertSet('rules', [])
        ->assertSet('focusField', 'ruleset');

    Livewire::test(AdventureWizard::class)->assertSet('experienceLevel', '')->assertSet('rules', []);
});

it('starts rules empty when a stored session still has a single text value', function (): void {
    session(['adventure_wizard' => [
        'rules' => 'dsa5',
        'experienceLevel' => 'meister',
        'genres' => ['horror'],
        'regionSlug' => 'nordmarken',
        'placeSlug' => 'havena',
    ]]);

    Livewire::test(AdventureWizard::class)
        ->assertSet('rules', [])
        ->assertSet('rulesAny', false)
        ->assertSet('experienceLevel', 'meister')
        ->assertSet('genres', ['horror'])
        ->assertSet('regionSlugs', [])
        ->assertSet('placeSlug', '');
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
        ->set('timeframes', ['multi'])
        ->set('timeframeDone', true)
        ->set('regionsAny', true)
        ->set('regionConfirmed', true)
        ->set('exoticAny', true)
        ->set('genres', ['horror'])
        ->set('rulesAny', true)
        ->set('rulesDone', true)
        ->set('gmExperience', 'meister')
        ->instance()
        ->toAnswers();

    expect($answers->groupSize)->toBeNull()
        ->and($answers->regionsAny)->toBeTrue()
        ->and($answers->regionSlugs)->toBe([])
        ->and($answers->placeSlug)->toBeNull()
        ->and($answers->rulesAny)->toBeTrue()
        ->and($answers->rules)->toBe([])
        ->and($answers->genres)->toBe(['horror'])
        ->and($answers->gmExperience)->toBe('meister');
});

it('hides outside-aventuria exotic after choosing an aventurian region', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->set('regionSlugs', ['suedaventurien'])
        ->call('skipPlaceAndConfirmRegion')
        ->assertSet('focusField', 'exotic');

    $component->assertDontSee('Andere Kontinente');

    $component
        ->set('exotics', ['extra_aventurian'])
        ->assertSet('exotics', []);
});

it('scrubs exotic when an aventurian region makes it invalid', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('exotics', ['extra_aventurian'])
        ->set('regionSlugs', ['suedaventurien'])
        ->assertSet('exotics', []);
});

it('does not suggest places outside the selected region', function (): void {
    $component = Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlugs', ['suedaventurien'])
        ->set('placeQuery', 'ha');

    expect($component->instance()->suggestions)->toBe([]);

    $component
        ->set('regionSlugs', ['nordmarken'])
        ->set('placeQuery', 'ha');

    expect(array_column($component->instance()->suggestions, 'slug'))->toContain('havena');
});

it('clears a place when the region no longer matches', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('focusField', 'region')
        ->set('regionSlugs', ['nordmarken'])
        ->set('placeQuery', 'ha')
        ->call('selectPlace', 'havena')
        ->assertSet('placeSlug', 'havena')
        ->set('regionSlugs', ['suedaventurien'])
        ->assertSet('placeSlug', '')
        ->assertSet('placeQuery', '');
});

it('lets summary chips jump back to a previous field', function (): void {
    Livewire::test(AdventureWizard::class)
        ->set('rules', ['dsa5'])
        ->set('rulesDone', true)
        ->set('experienceLevel', 'einsteiger')
        ->call('editField', 'ruleset')
        ->assertSet('focusField', 'ruleset')
        ->assertSee('Nach welchem Regelwerk')
        ->assertSee('Bisher gewählt');
});

it('explains which rulebooks exist in the catalog', function (): void {
    Livewire::test(AdventureWizard::class)
        ->assertSee('Im Katalog: DSA 1 bis DSA 5 und editionsunabhängige Abenteuer')
        ->assertSee('Nach welchem Regelwerk');
});
