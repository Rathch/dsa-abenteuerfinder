<?php

use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\WizardAnswers;

it('treats egal and an empty selection as no filter and drops egal when values are chosen', function (): void {
    $egal = new WizardAnswers('einsteiger', null, [], [], true, 'havena', [], true, [], [], true, 'einsteiger');
    $empty = new WizardAnswers('einsteiger', null, [], [], false, null, [], false, [], [], false, 'einsteiger');
    $chosen = new WizardAnswers('einsteiger', null, [], ['nordmarken', 'myranor'], true, 'havena', ['no'], true, [], ['dsa5'], true, 'einsteiger');

    expect($egal->rulesAny)->toBeTrue()
        ->and($egal->rules)->toBe([])
        ->and($egal->regionsAny)->toBeTrue()
        ->and($egal->regionSlugs)->toBe([])
        ->and($egal->placeSlug)->toBeNull()
        ->and($egal->exoticAny)->toBeTrue()
        ->and($empty->rulesAny)->toBeFalse()
        ->and($empty->acceptsRule(RuleVersion::Dsa1))->toBeTrue()
        ->and($chosen->rulesAny)->toBeFalse()
        ->and($chosen->rules)->toBe(['dsa5'])
        ->and($chosen->regionsAny)->toBeFalse()
        ->and($chosen->regionSlugs)->toBe(['nordmarken', 'myranor'])
        ->and($chosen->placeSlug)->toBeNull()
        ->and($chosen->exoticAny)->toBeFalse()
        ->and($chosen->exotics)->toBe(['no']);
});

it('matches any selected edition, region, duration or setting', function (): void {
    $answers = wizardAnswers([
        'rules' => ['dsa3', 'dsa5'],
        'rulesAny' => false,
        'regionSlugs' => ['nordmarken', 'myranor'],
        'regionsAny' => false,
        'timeframes' => ['evening_short', 'campaign'],
        'exotics' => ['no', 'extra_aventurian'],
        'exoticAny' => false,
    ]);

    expect($answers->acceptsRule(RuleVersion::Dsa3))->toBeTrue()
        ->and($answers->acceptsRule(RuleVersion::Dsa5))->toBeTrue()
        ->and($answers->acceptsRule(RuleVersion::Dsa4))->toBeFalse()
        ->and($answers->acceptsRule(RuleVersion::Dsa1))->toBeFalse()
        ->and($answers->acceptsRule(RuleVersion::EditionUnabhaengig))->toBeTrue()
        ->and($answers->acceptsRegion(['nordmarken']))->toBeTrue()
        ->and($answers->acceptsRegion(['myranor']))->toBeTrue()
        ->and($answers->acceptsRegion(['mittelaventurien']))->toBeFalse()
        ->and($answers->acceptsDuration(DurationCategory::Kurz))->toBeTrue()
        ->and($answers->acceptsDuration(DurationCategory::Kampagne))->toBeTrue()
        ->and($answers->acceptsExotic(false, false))->toBeTrue()
        ->and($answers->acceptsExotic(true, true))->toBeTrue();
});

it('keeps a stored dsa 4 adventure when dsa 4 is selected', function (): void {
    $answers = wizardAnswers([
        'rules' => ['dsa4'],
        'rulesAny' => false,
    ]);

    expect($answers->acceptsRule(RuleVersion::Dsa4))->toBeTrue()
        ->and($answers->acceptsRule(RuleVersion::EditionUnabhaengig))->toBeTrue()
        ->and($answers->acceptsRule(RuleVersion::Dsa5))->toBeFalse();
});

it('discards stored text values and keeps lists and the other answers', function (): void {
    $discarded = WizardAnswers::fromSession([
        'experienceLevel' => 'meister',
        'groupSize' => '3-5',
        'gmExperience' => 'erfahren',
        'genres' => ['horror'],
        'rules' => 'dsa5',
        'regionSlug' => 'nordmarken',
        'timeframe' => 'campaign',
        'exotic' => 'no',
        'placeSlug' => 'havena',
    ]);

    expect($discarded->rules)->toBe([])
        ->and($discarded->rulesAny)->toBeFalse()
        ->and($discarded->regionSlugs)->toBe([])
        ->and($discarded->timeframes)->toBe([])
        ->and($discarded->exotics)->toBe([])
        ->and($discarded->placeSlug)->toBeNull()
        ->and($discarded->experienceLevel)->toBe('meister')
        ->and($discarded->groupSize)->toBe('3-5')
        ->and($discarded->gmExperience)->toBe('erfahren')
        ->and($discarded->genres)->toBe(['horror']);

    $kept = WizardAnswers::fromSession([
        'regionSlugs' => ['nordmarken'],
        'regionsAny' => false,
        'placeSlug' => 'havena',
        'rules' => ['dsa5'],
        'rulesAny' => false,
    ]);

    expect($kept->regionSlugs)->toBe(['nordmarken'])
        ->and($kept->placeSlug)->toBe('havena')
        ->and($kept->rules)->toBe(['dsa5']);
});
