<?php

use App\Domain\Matching\AdventureCandidate;
use App\Domain\Matching\Complexity;
use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\DurationWish;
use App\Domain\Matching\ExoticWish;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\GroupSizeWish;
use App\Domain\Matching\LocationKind;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\MatchResult;
use App\Domain\Matching\RegionKey;
use App\Domain\Matching\RulesWish;
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\WizardAnswers;

it('falls back to neutral defaults for unknown wizard values', function (): void {
    $answers = new WizardAnswers('???', 'x', ['???'], [], false, null, ['???'], false, [], ['???'], false, '???');

    expect($answers->experience())->toBe(ExperienceLevel::Einsteiger)
        ->and($answers->gmLevel())->toBe(ExperienceLevel::Einsteiger)
        ->and($answers->groupSizeWish())->toBeNull()
        ->and($answers->restrictsDuration())->toBeFalse()
        ->and($answers->restrictsExotic())->toBeFalse()
        ->and($answers->acceptsRule(RuleVersion::Dsa1))->toBeFalse()
        ->and($answers->hasRegionWish())->toBeFalse();
});

it('parses valid wizard values', function (): void {
    $answers = wizardAnswers([
        'experienceLevel' => 'meister',
        'gmExperience' => 'erfahren',
        'groupSize' => '6+',
        'timeframe' => 'campaign',
        'exotic' => 'no',
        'rules' => 'dsa4',
        'regionSlug' => 'mittelaventurien',
        'placeSlug' => 'gareth',
    ]);

    expect($answers->experience())->toBe(ExperienceLevel::Meister)
        ->and($answers->gmLevel())->toBe(ExperienceLevel::Erfahren)
        ->and($answers->groupSizeWish())->toBe(GroupSizeWish::SixPlus)
        ->and($answers->durationWishes())->toBe([DurationWish::Campaign])
        ->and($answers->exoticWishes())->toBe([ExoticWish::No])
        ->and($answers->rules)->toBe(['dsa4'])
        ->and($answers->placeSlug)->toBe('gareth')
        ->and($answers->hasRegionWish())->toBeTrue();
});

it('computes experience ranks and distances', function (): void {
    expect(ExperienceLevel::Einsteiger->distanceTo(ExperienceLevel::Meister))->toBe(3)
        ->and(ExperienceLevel::Erfahren->distanceTo(ExperienceLevel::Fortgeschritten))->toBe(1)
        ->and(ExperienceLevel::values())->toBe(['einsteiger', 'fortgeschritten', 'erfahren', 'meister'])
        ->and(Complexity::values())->toBe(['niedrig', 'mittel', 'hoch'])
        ->and(DurationCategory::values())->toBe(['kurz', 'mittel', 'lang', 'kampagne'])
        ->and(RuleVersion::values())->toBe(['dsa1', 'dsa2', 'dsa3', 'dsa4', 'dsa5', 'editionunabhaengig'])
        ->and(LocationType::values())->toBe(['point', 'corridor', 'traveling'])
        ->and(LocationKind::values())->toBe(['continent', 'region', 'place']);

    foreach (ExperienceLevel::cases() as $level) {
        expect($level->label())->not->toBeEmpty();
    }
});

it('maps page counts to duration categories', function (): void {
    expect(DurationCategory::fromPageCount(1))->toBe(DurationCategory::Kurz)
        ->and(DurationCategory::fromPageCount(36))->toBe(DurationCategory::Kurz)
        ->and(DurationCategory::fromPageCount(37))->toBe(DurationCategory::Mittel)
        ->and(DurationCategory::fromPageCount(72))->toBe(DurationCategory::Mittel)
        ->and(DurationCategory::fromPageCount(160))->toBe(DurationCategory::Lang)
        ->and(DurationCategory::fromPageCount(161))->toBe(DurationCategory::Kampagne);

    foreach (DurationCategory::cases() as $category) {
        expect($category->label())->not->toBeEmpty();
    }
});

it('has a complete duration fit matrix', function (): void {
    foreach (DurationWish::cases() as $wish) {
        expect($wish->label())->not->toBeEmpty();
        foreach (DurationCategory::cases() as $category) {
            expect($wish->fitFor($category))->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }
    }

    expect(DurationWish::Campaign->fitFor(DurationCategory::Kampagne))->toBe(100)
        ->and(DurationWish::Multi->fitFor(DurationCategory::Lang))->toBe(100)
        ->and(DurationWish::EveningLong->fitFor(DurationCategory::Mittel))->toBe(100);
});

it('maps complexity to the ideal for each GM level', function (): void {
    expect(Complexity::idealFor(ExperienceLevel::Einsteiger))->toBe(Complexity::Niedrig)
        ->and(Complexity::idealFor(ExperienceLevel::Fortgeschritten))->toBe(Complexity::Mittel)
        ->and(Complexity::idealFor(ExperienceLevel::Erfahren))->toBe(Complexity::Hoch)
        ->and(Complexity::idealFor(ExperienceLevel::Meister))->toBe(Complexity::Hoch);
});

it('accepts rule versions according to the wish', function (): void {
    expect(RulesWish::Any->accepts(RuleVersion::Dsa4))->toBeTrue()
        ->and(RulesWish::Any->accepts(RuleVersion::Dsa1))->toBeTrue()
        ->and(RulesWish::Dsa5->accepts(RuleVersion::Dsa4))->toBeFalse()
        ->and(RulesWish::Dsa5->accepts(RuleVersion::Dsa3))->toBeFalse()
        ->and(RulesWish::Dsa5->accepts(RuleVersion::EditionUnabhaengig))->toBeTrue()
        ->and(RulesWish::Dsa4->accepts(RuleVersion::Dsa5))->toBeFalse()
        ->and(RulesWish::Dsa4->accepts(RuleVersion::Dsa2))->toBeFalse()
        ->and(RulesWish::Dsa4->accepts(RuleVersion::Dsa4))->toBeTrue();

    foreach (RulesWish::cases() as $wish) {
        expect($wish->label())->not->toBeEmpty();
    }
    foreach (RuleVersion::cases() as $version) {
        expect($version->label())->not->toBeEmpty();
    }
});

it('scores the exotic wish', function (): void {
    expect(ExoticWish::Any->fitFor(true, false))->toBe(100)
        ->and(ExoticWish::No->fitFor(false, false))->toBe(100)
        ->and(ExoticWish::No->fitFor(true, false))->toBe(0)
        ->and(ExoticWish::No->fitFor(false, true))->toBe(0)
        ->and(ExoticWish::ExtraAventurian->fitFor(true, true))->toBe(100)
        ->and(ExoticWish::ExtraAventurian->fitFor(true, false))->toBe(60)
        ->and(ExoticWish::ExtraAventurian->fitFor(false, false))->toBe(20)
        ->and(ExoticWish::UnusualCultures->fitFor(true, false))->toBe(100)
        ->and(ExoticWish::UnusualCultures->fitFor(false, false))->toBe(30);
});

it('handles group size bands', function (): void {
    expect(GroupSizeWish::Two->band())->toBe(0)
        ->and(GroupSizeWish::ThreeToFive->band())->toBe(1)
        ->and(GroupSizeWish::SixPlus->band())->toBe(2)
        ->and(GroupSizeWish::Two->isCompatibleWith(null, null))->toBeTrue()
        ->and(GroupSizeWish::Two->isCompatibleWith(7, 9))->toBeFalse()
        ->and(GroupSizeWish::SixPlus->isCompatibleWith(2, 2))->toBeFalse()
        ->and(GroupSizeWish::ThreeToFive->isCompatibleWith(2, 2))->toBeTrue()
        ->and(GroupSizeWish::ThreeToFive->isCompatibleWith(null, 5))->toBeTrue()
        ->and(GroupSizeWish::Two->isExactlyCoveredBy(2, 4))->toBeTrue()
        ->and(GroupSizeWish::Two->isExactlyCoveredBy(3, 5))->toBeFalse()
        ->and(GroupSizeWish::ThreeToFive->isExactlyCoveredBy(4, null))->toBeTrue()
        ->and(GroupSizeWish::SixPlus->isExactlyCoveredBy(3, 5))->toBeFalse()
        ->and(GroupSizeWish::SixPlus->isExactlyCoveredBy(4, 8))->toBeTrue();

    foreach (GroupSizeWish::cases() as $wish) {
        expect($wish->label())->not->toBeEmpty();
    }
});

it('matches a place inside the free-text label and ignores shorter words', function (): void {
    $candidate = adventureCandidate([
        'placeLabel' => 'Grangor, Insel Fogstor, Meer der Sieben Winde',
    ]);

    expect($candidate->matchesPlaceSlug('insel-fogstor'))->toBeTrue()
        ->and($candidate->matchesPlaceSlug('insel'))->toBeFalse()
        ->and($candidate->matchesPlaceSlug('havena'))->toBeFalse()
        ->and(AdventureCandidate::regionsMentionedIn(
            'Bornland: Festum',
            ['festum' => 'bornland', 'mark' => 'bornland'],
        ))->toBe(['bornland']);
});

it('knows region neighbours and labels', function (): void {
    expect(RegionKey::Nordmarken->isNeighborOf(RegionKey::Mittelaventurien))->toBeTrue()
        ->and(RegionKey::Mittelaventurien->isNeighborOf(RegionKey::Nordmarken))->toBeTrue()
        ->and(RegionKey::Nordmarken->isNeighborOf(RegionKey::Suedwest))->toBeFalse()
        ->and(RegionKey::Myranor->neighbors())->toBe([])
        ->and(RegionKey::Myranor->isExtraAventurian())->toBeTrue()
        ->and(RegionKey::Tiefenreich->isExtraAventurian())->toBeTrue()
        ->and(RegionKey::Seefahrt->isExtraAventurian())->toBeFalse()
        ->and(RegionKey::labelFor('suedaventurien'))->toBe('Südaventurien')
        ->and(RegionKey::labelFor('neues-land'))->toBe('Neues land');

    foreach (RegionKey::cases() as $key) {
        expect($key->label())->not->toBeEmpty();
        // Nachbarschaft ist symmetrisch.
        foreach ($key->neighbors() as $neighbor) {
            expect($neighbor->isNeighborOf($key))->toBeTrue("{$neighbor->value} <-> {$key->value}");
        }
    }
});

it('derives all region slugs of a candidate without duplicates', function (): void {
    $candidate = adventureCandidate([
        'primaryRegionSlug' => 'nordmarken',
        'regionSlugs' => ['nordmarken', 'mittelaventurien'],
    ]);

    expect($candidate->allRegionSlugs())->toBe(['nordmarken', 'mittelaventurien'])
        ->and(adventureCandidate()->allRegionSlugs())->toBe([])
        ->and($candidate->isExtraAventurian())->toBeFalse();
});

it('exposes match result helpers', function (): void {
    expect((new MatchResult([]))->isEmpty())->toBeTrue()
        ->and((new MatchResult([], ['x']))->usedFallback())->toBeTrue();
});
