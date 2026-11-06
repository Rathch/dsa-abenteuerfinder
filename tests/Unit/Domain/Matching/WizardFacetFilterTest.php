<?php

declare(strict_types=1);

use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\WizardFacetFilter;

beforeEach(function (): void {
    $this->facets = new WizardFacetFilter;
});

describe('pool constraints', function (): void {
    it('drops incomplete candidates', function (): void {
        $pool = $this->facets->pool(wizardAnswers([
            'experienceLevel' => '',
            'timeframe' => '',
            'exotic' => '',
            'rules' => '',
            'gmExperience' => '',
        ]), [
            adventureCandidate(['slug' => 'ok']),
            adventureCandidate(['slug' => 'bad', 'incomplete' => true]),
        ]);

        expect(array_map(static fn ($c) => $c->slug, $pool))->toBe(['ok']);
    });

    it('applies experience within ±1 when set', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'e', 'experienceLevel' => ExperienceLevel::Einsteiger]),
            adventureCandidate(['slug' => 'f', 'experienceLevel' => ExperienceLevel::Fortgeschritten]),
            adventureCandidate(['slug' => 'm', 'experienceLevel' => ExperienceLevel::Meister]),
        ];

        $pool = $this->facets->pool(
            wizardAnswers([
                'experienceLevel' => 'einsteiger',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
            ]),
            $candidates,
        );

        expect(array_map(static fn ($c) => $c->slug, $pool))
            ->toContain('e', 'f')
            ->not->toContain('m');
    });

    it('applies region so myranor drops out of aventurian pools', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'sued', 'primaryRegionSlug' => 'suedaventurien']),
            adventureCandidate(['slug' => 'myr', 'primaryRegionSlug' => 'myranor', 'exotic' => true]),
        ];

        $pool = $this->facets->pool(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
                'regionSlug' => 'suedaventurien',
            ]),
            $candidates,
        );

        expect(array_map(static fn ($c) => $c->slug, $pool))->toBe(['sued']);
    });
});

describe('available exotic wishes', function (): void {
    it('hides extra_aventurian after an aventurian region', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'sued', 'primaryRegionSlug' => 'suedaventurien', 'exotic' => false]),
            adventureCandidate(['slug' => 'exotic-sued', 'primaryRegionSlug' => 'suedaventurien', 'exotic' => true]),
            adventureCandidate(['slug' => 'myr', 'primaryRegionSlug' => 'myranor', 'exotic' => true]),
        ];

        $values = $this->facets->exoticWishes(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
                'regionSlug' => 'suedaventurien',
            ]),
            $candidates,
        );

        expect($values)
            ->toContain('no', 'unusual_cultures', 'any')
            ->not->toContain('extra_aventurian');
    });

    it('keeps extra_aventurian when myranor remains in the pool', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'myr', 'primaryRegionSlug' => 'myranor', 'exotic' => true]),
            adventureCandidate(['slug' => 'myr2', 'primaryRegionSlug' => 'myranor', 'exotic' => true]),
        ];

        $values = $this->facets->exoticWishes(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
                'regionSlug' => 'myranor',
            ]),
            $candidates,
        );

        expect($values)->toContain('extra_aventurian', 'any');
    });

    it('hides extra_aventurian after choosing an aventurian place', function (): void {
        $candidates = [
            adventureCandidate([
                'slug' => 'haven',
                'primaryPlaceSlug' => 'havena',
                'primaryRegionSlug' => 'nordmarken',
            ]),
            adventureCandidate(['slug' => 'myr', 'primaryRegionSlug' => 'myranor', 'exotic' => true]),
        ];

        $values = $this->facets->exoticWishes(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
                'regionSlug' => null,
                'placeSlug' => 'havena',
            ]),
            $candidates,
        );

        expect($values)->not->toContain('extra_aventurian');
    });
});

describe('available region and rules facets', function (): void {
    it('lists only regions that still have candidates after other answers', function (): void {
        $candidates = [
            adventureCandidate([
                'slug' => 'a',
                'experienceLevel' => ExperienceLevel::Einsteiger,
                'primaryRegionSlug' => 'nordmarken',
                'ruleVersion' => RuleVersion::Dsa5,
            ]),
            adventureCandidate([
                'slug' => 'b',
                'experienceLevel' => ExperienceLevel::Meister,
                'primaryRegionSlug' => 'myranor',
                'ruleVersion' => RuleVersion::Dsa5,
            ]),
        ];

        $regions = $this->facets->regionSlugs(
            wizardAnswers([
                'experienceLevel' => 'einsteiger',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
            ]),
            $candidates,
        );

        expect($regions)->toContain('nordmarken')->not->toContain('myranor');
    });

    it('lists only rules wishes that still match the pool', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'v5', 'ruleVersion' => RuleVersion::Dsa5, 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'v5b', 'ruleVersion' => RuleVersion::Dsa5, 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'v4', 'ruleVersion' => RuleVersion::Dsa4, 'primaryRegionSlug' => 'myranor']),
        ];

        $rules = $this->facets->rulesWishes(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
                'regionSlug' => 'nordmarken',
            ]),
            $candidates,
        );

        expect($rules)->toContain('dsa5', 'any')->not->toContain('dsa4');
    });
});

describe('traveling and neighbor region fit', function (): void {
    it('keeps traveling adventures only when they list the selected region', function (): void {
        $pool = $this->facets->pool(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
                'regionSlug' => 'nordmarken',
            ]),
            [
                adventureCandidate([
                    'slug' => 'reise-passend',
                    'locationType' => LocationType::Traveling,
                    'primaryRegionSlug' => 'nordmarken',
                ]),
                adventureCandidate([
                    'slug' => 'reise-fremd',
                    'locationType' => LocationType::Traveling,
                    'primaryRegionSlug' => 'myranor',
                ]),
                adventureCandidate([
                    'slug' => 'nachbar',
                    'primaryRegionSlug' => 'mittelaventurien',
                ]),
            ],
        );

        expect(array_map(static fn ($c) => $c->slug, $pool))->toBe(['reise-passend']);
    });
});
