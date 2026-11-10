<?php

declare(strict_types=1);

use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\WizardFacetFilter;

beforeEach(function (): void {
    $this->facets = new WizardFacetFilter;
});

describe('pool constraints', function (): void {
    it('keeps incomplete candidates and drops ones missing from the wiki', function (): void {
        $pool = $this->facets->pool(wizardAnswers([
            'experienceLevel' => '',
            'timeframe' => '',
            'exotic' => '',
            'rules' => '',
            'gmExperience' => '',
        ]), [
            adventureCandidate(['slug' => 'ok']),
            adventureCandidate(['slug' => 'unfertig', 'incomplete' => true]),
            adventureCandidate(['slug' => 'fehlt', 'missingFromWiki' => true]),
        ]);

        expect(array_map(static fn ($c) => $c->slug, $pool))->toBe(['ok', 'unfertig']);
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
                'regionSlug' => 'nordmarken',
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

    it('hides other continents when the wish stays on Aventurien', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'n', 'primaryRegionSlug' => 'nordmarken', 'exotic' => false]),
            adventureCandidate(['slug' => 'm', 'primaryRegionSlug' => 'myranor', 'exotic' => false]),
            adventureCandidate(['slug' => 't', 'primaryRegionSlug' => 'tiefenreich', 'exotic' => false]),
        ];

        $onlyAventurien = $this->facets->regionSlugs(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => 'no',
                'rules' => '',
                'gmExperience' => '',
            ]),
            $candidates,
        );
        $alsoContinents = $this->facets->regionSlugs(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => 'any',
                'rules' => '',
                'gmExperience' => '',
            ]),
            $candidates,
        );

        expect($onlyAventurien)->toContain('nordmarken')->not->toContain('myranor')->not->toContain('tiefenreich')
            ->and($alsoContinents)->toContain('nordmarken', 'myranor', 'tiefenreich');
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

    it('offers an edition only when an adventure uses that exact rule version', function (): void {
        $withDsa1 = $this->facets->rulesWishes(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
            ]),
            [
                adventureCandidate(['ruleVersion' => RuleVersion::Dsa1]),
                adventureCandidate(['ruleVersion' => RuleVersion::EditionUnabhaengig]),
            ],
        );

        $onlyIndependent = $this->facets->rulesWishes(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
            ]),
            [
                adventureCandidate(['ruleVersion' => RuleVersion::EditionUnabhaengig]),
            ],
        );

        expect($withDsa1)->toContain('dsa1', 'any')
            ->not->toContain('dsa2', 'editionunabhaengig')
            ->and($onlyIndependent)->toBe(['any']);
    });

    it('hides a region with no remaining dsa 1 adventure and keeps a matching duration', function (): void {
        $answers = wizardAnswers([
            'experienceLevel' => '',
            'timeframe' => '',
            'exotic' => '',
            'rules' => ['dsa1'],
            'rulesAny' => false,
            'gmExperience' => '',
        ]);
        $candidates = [
            adventureCandidate([
                'primaryRegionSlug' => 'nordmarken',
                'ruleVersion' => RuleVersion::Dsa1,
                'durationCategory' => DurationCategory::Kurz,
            ]),
            adventureCandidate([
                'primaryRegionSlug' => 'myranor',
                'ruleVersion' => RuleVersion::Dsa5,
                'durationCategory' => DurationCategory::Kampagne,
            ]),
        ];

        expect($this->facets->regionSlugs($answers, $candidates))
            ->toContain('nordmarken')
            ->not->toContain('myranor')
            ->and($this->facets->timeframes($answers, $candidates))
            ->toContain('evening_short');
    });
});

describe('genre options', function (): void {
    it('keeps catalog genres the remaining adventures still offer, in catalog order', function (): void {
        $slugs = $this->facets->genreSlugs(
            wizardAnswers([
                'experienceLevel' => '',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
            ]),
            [
                adventureCandidate(['genres' => ['investigation', 'seafaring']]),
                adventureCandidate(['genres' => ['horror']]),
            ],
            ['horror', 'investigation', 'dungeon', 'seafaring'],
        );

        expect($slugs)->toBe(['horror', 'investigation', 'seafaring']);
    });

    it('drops a genre that only adventures outside the experience window offer', function (): void {
        $slugs = $this->facets->genreSlugs(
            wizardAnswers([
                'experienceLevel' => 'meister',
                'timeframe' => '',
                'exotic' => '',
                'rules' => '',
                'gmExperience' => '',
            ]),
            [
                adventureCandidate([
                    'genres' => ['investigation'],
                    'experienceLevel' => ExperienceLevel::Einsteiger,
                ]),
                adventureCandidate([
                    'genres' => ['horror'],
                    'experienceLevel' => ExperienceLevel::Meister,
                ]),
            ],
            ['investigation', 'horror'],
        );

        expect($slugs)->toBe(['horror']);
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
