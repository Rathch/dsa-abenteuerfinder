<?php

use App\Domain\Matching\AdventureCandidate;
use App\Domain\Matching\Complexity;
use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\WizardAnswers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Feature-Tests rendern Blade mit @vite; ohne gebautes Manifest würden sie sonst fehlschlagen.
pest()->beforeEach(fn () => $this->withoutVite())->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit/Domain');

pest()->extend(TestCase::class)
    ->in('Unit/Services');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param  array<string, mixed>  $overrides
 */
function wizardAnswers(array $overrides = []): WizardAnswers
{
    return new WizardAnswers(...array_merge([
        'experienceLevel' => 'einsteiger',
        'groupSize' => null,
        'timeframe' => 'evening_short',
        'regionSlug' => null,
        'placeSlug' => null,
        'exotic' => 'any',
        'genres' => [],
        'rules' => 'any',
        'gmExperience' => 'einsteiger',
    ], $overrides));
}

/**
 * Baut einen Kandidaten, der standardmäßig zu wizardAnswers() passt; jede Instanz hat eine eigene Serie.
 *
 * @param  array<string, mixed>  $overrides
 */
function adventureCandidate(array $overrides = []): AdventureCandidate
{
    static $counter = 0;
    $counter++;

    return new AdventureCandidate(...array_merge([
        'id' => $counter,
        'slug' => 'abenteuer-'.$counter,
        'title' => 'Abenteuer '.$counter,
        'seriesSlug' => 'serie-'.$counter,
        'seriesName' => 'Serie '.$counter,
        'experienceLevel' => ExperienceLevel::Einsteiger,
        'pageCount' => null,
        'durationCategory' => DurationCategory::Kurz,
        'ruleVersion' => RuleVersion::Dsa5,
        'exotic' => false,
        'complexity' => Complexity::Niedrig,
        'groupSizeMin' => null,
        'groupSizeMax' => null,
        'locationType' => LocationType::Point,
        'primaryPlaceSlug' => null,
        'primaryPlaceName' => null,
        'primaryRegionSlug' => null,
        'regionSlugs' => [],
        'genres' => [],
        'shortDescription' => 'Eine kurze, spoilerfreie Beschreibung.',
        'wikiUrl' => 'https://de.wiki-aventurica.de/wiki/Test',
    ], $overrides));
}
