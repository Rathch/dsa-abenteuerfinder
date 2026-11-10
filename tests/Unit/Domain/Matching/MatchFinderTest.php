<?php

use App\Domain\Matching\AdventureCandidate;
use App\Domain\Matching\Complexity;
use App\Domain\Matching\DurationCategory;
use App\Domain\Matching\DurationWish;
use App\Domain\Matching\ExperienceLevel;
use App\Domain\Matching\LocationType;
use App\Domain\Matching\MatchFinder;
use App\Domain\Matching\MatchRecommendation;
use App\Domain\Matching\ReasonBuilder;
use App\Domain\Matching\RuleVersion;
use App\Domain\Matching\ScoreCalculator;

/**
 * @param  list<MatchRecommendation>  $recommendations
 * @return list<string>
 */
function recommendedSlugs(array $recommendations): array
{
    return array_map(static fn (MatchRecommendation $r): string => $r->adventure->slug, $recommendations);
}

beforeEach(function (): void {
    $this->finder = new MatchFinder;
});

describe('hard filters', function (): void {
    it('allows experience levels within ±1 and excludes anything further away', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'exakt', 'experienceLevel' => ExperienceLevel::Einsteiger]),
            adventureCandidate(['slug' => 'exakt-2', 'experienceLevel' => ExperienceLevel::Einsteiger]),
            adventureCandidate(['slug' => 'eins-hoeher', 'experienceLevel' => ExperienceLevel::Fortgeschritten]),
            adventureCandidate(['slug' => 'zwei-hoeher', 'experienceLevel' => ExperienceLevel::Erfahren]),
            adventureCandidate(['slug' => 'drei-hoeher', 'experienceLevel' => ExperienceLevel::Meister]),
        ];

        $result = $this->finder->find(wizardAnswers(['experienceLevel' => 'einsteiger']), $candidates);

        expect(recommendedSlugs($result->recommendations))
            ->toContain('exakt', 'exakt-2', 'eins-hoeher')
            ->not->toContain('zwei-hoeher')
            ->not->toContain('drei-hoeher')
            ->and($result->fallbackNotes)->toBe([]);
    });

    it('tolerates one level in both directions', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'darunter', 'experienceLevel' => ExperienceLevel::Fortgeschritten]),
            adventureCandidate(['slug' => 'exakt', 'experienceLevel' => ExperienceLevel::Erfahren]),
            adventureCandidate(['slug' => 'darueber', 'experienceLevel' => ExperienceLevel::Meister]),
            adventureCandidate(['slug' => 'zu-niedrig', 'experienceLevel' => ExperienceLevel::Einsteiger]),
        ];

        $result = $this->finder->find(wizardAnswers(['experienceLevel' => 'erfahren']), $candidates);

        expect(recommendedSlugs($result->recommendations))
            ->toContain('darunter', 'exakt', 'darueber')
            ->not->toContain('zu-niedrig');
    });

    it('ranks an exact experience match above a neighbouring level', function (): void {
        $result = $this->finder->find(wizardAnswers(['experienceLevel' => 'fortgeschritten']), [
            adventureCandidate(['slug' => 'nah', 'experienceLevel' => ExperienceLevel::Einsteiger]),
            adventureCandidate(['slug' => 'exakt', 'experienceLevel' => ExperienceLevel::Fortgeschritten]),
            adventureCandidate(['slug' => 'nah-2', 'experienceLevel' => ExperienceLevel::Erfahren]),
        ]);

        expect($result->recommendations[0]->adventure->slug)->toBe('exakt');
    });

    it('filters by rule version but keeps edition independent adventures', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'v5-a', 'ruleVersion' => RuleVersion::Dsa5]),
            adventureCandidate(['slug' => 'v5-b', 'ruleVersion' => RuleVersion::Dsa5]),
            adventureCandidate(['slug' => 'frei', 'ruleVersion' => RuleVersion::EditionUnabhaengig]),
            adventureCandidate(['slug' => 'v4', 'ruleVersion' => RuleVersion::Dsa4]),
        ];

        $dsa5 = recommendedSlugs($this->finder->find(wizardAnswers(['rules' => 'dsa5']), $candidates)->recommendations);
        $dsa4 = recommendedSlugs($this->finder->find(wizardAnswers(['rules' => 'dsa4']), $candidates)->recommendations);
        $any = recommendedSlugs($this->finder->find(wizardAnswers(['rules' => 'any']), $candidates)->recommendations);

        expect($dsa5)->toContain('v5-a', 'v5-b', 'frei')->not->toContain('v4')
            ->and($dsa4)->toContain('v4', 'frei')->not->toContain('v5-a')
            ->and($any)->toHaveCount(4);
    });

    it('keeps adventures of either selected edition and drops a third', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'drei', 'ruleVersion' => RuleVersion::Dsa3]),
            adventureCandidate(['slug' => 'fuenf', 'ruleVersion' => RuleVersion::Dsa5]),
            adventureCandidate(['slug' => 'frei', 'ruleVersion' => RuleVersion::EditionUnabhaengig]),
            adventureCandidate(['slug' => 'eins', 'ruleVersion' => RuleVersion::Dsa1]),
        ];

        $result = $this->finder->find(wizardAnswers([
            'rules' => ['dsa3', 'dsa5'],
            'rulesAny' => false,
        ]), $candidates);

        expect(recommendedSlugs($result->recommendations))
            ->toContain('drei', 'fuenf', 'frei')
            ->not->toContain('eins');
    });

    it('does not filter editions when no rule is selected', function (): void {
        $result = $this->finder->find(wizardAnswers([
            'rules' => [],
            'rulesAny' => false,
        ]), [
            adventureCandidate(['slug' => 'alt', 'ruleVersion' => RuleVersion::Dsa1]),
            adventureCandidate(['slug' => 'neu', 'ruleVersion' => RuleVersion::Dsa5]),
            adventureCandidate(['slug' => 'frei', 'ruleVersion' => RuleVersion::EditionUnabhaengig]),
        ]);

        expect(recommendedSlugs($result->recommendations))->toContain('alt', 'neu', 'frei');
    });

    it('requires an exact region and excludes a neighbour until the region is relaxed', function (): void {
        $strict = $this->finder->find(wizardAnswers([
            'regionSlugs' => ['nordmarken', 'myranor'],
            'regionsAny' => false,
        ]), [
            adventureCandidate(['slug' => 'nord-1', 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'nord-2', 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'myr', 'primaryRegionSlug' => 'myranor']),
            adventureCandidate(['slug' => 'nachbar', 'primaryRegionSlug' => 'mittelaventurien']),
            adventureCandidate(['slug' => 'fremd', 'primaryRegionSlug' => 'suedwest']),
        ]);

        expect(recommendedSlugs($strict->recommendations))
            ->toContain('nord-1', 'nord-2', 'myr')
            ->not->toContain('nachbar', 'fremd')
            ->and($strict->fallbackNotes)->toBe([]);

        $relaxed = $this->finder->find(wizardAnswers(['regionSlug' => 'nordmarken']), [
            adventureCandidate(['slug' => 'nord', 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'nachbar', 'primaryRegionSlug' => 'mittelaventurien']),
            adventureCandidate(['slug' => 'fremd', 'primaryRegionSlug' => 'suedwest']),
        ]);

        expect(recommendedSlugs($relaxed->recommendations))->toContain('nord', 'nachbar')
            ->and($relaxed->fallbackNotes[0])->toStartWith('Region wurde ignoriert');
    });

    it('never relaxes the rules filter in fallback mode', function (): void {
        $result = $this->finder->find(wizardAnswers(['rules' => 'dsa4']), [
            adventureCandidate(['ruleVersion' => RuleVersion::Dsa5]),
            adventureCandidate(['ruleVersion' => RuleVersion::Dsa5]),
            adventureCandidate(['ruleVersion' => RuleVersion::Dsa5]),
        ]);

        expect($result->isEmpty())->toBeTrue();
    });

    it('keeps incomplete adventures and skips ones missing from the wiki', function (): void {
        $result = $this->finder->find(wizardAnswers(), [
            adventureCandidate(['slug' => 'a']),
            adventureCandidate(['slug' => 'b']),
            adventureCandidate(['slug' => 'c']),
            adventureCandidate(['slug' => 'unfertig', 'incomplete' => true]),
            adventureCandidate(['slug' => 'fehlt', 'missingFromWiki' => true]),
        ]);

        expect(recommendedSlugs($result->recommendations))->toContain('unfertig')
            ->not->toContain('fehlt')
            ->toHaveCount(4);
    });

    it('applies the group size filter with one band of tolerance', function (): void {
        $candidates = [
            adventureCandidate(['slug' => 'zu-zweit', 'groupSizeMin' => 2, 'groupSizeMax' => 2]),
            adventureCandidate(['slug' => 'drei-bis-fuenf', 'groupSizeMin' => 3, 'groupSizeMax' => 5]),
            adventureCandidate(['slug' => 'gross', 'groupSizeMin' => 6, 'groupSizeMax' => 8]),
            adventureCandidate(['slug' => 'offen-nach-oben', 'groupSizeMin' => 6, 'groupSizeMax' => null]),
            adventureCandidate(['slug' => 'ohne-angabe', 'groupSizeMin' => null, 'groupSizeMax' => null]),
        ];

        $two = recommendedSlugs($this->finder->find(wizardAnswers(['groupSize' => '2']), $candidates)->recommendations);
        $mid = recommendedSlugs($this->finder->find(wizardAnswers(['groupSize' => '3-5']), $candidates)->recommendations);
        $six = recommendedSlugs($this->finder->find(wizardAnswers(['groupSize' => '6+']), $candidates)->recommendations);
        $egal = recommendedSlugs($this->finder->find(wizardAnswers(['groupSize' => null]), $candidates)->recommendations);

        expect($two)->toContain('zu-zweit', 'drei-bis-fuenf', 'ohne-angabe')->not->toContain('gross')->not->toContain('offen-nach-oben')
            ->and($mid)->toContain('zu-zweit', 'drei-bis-fuenf', 'gross', 'offen-nach-oben', 'ohne-angabe')
            ->and($six)->toContain('drei-bis-fuenf', 'gross', 'offen-nach-oben')->not->toContain('zu-zweit')
            ->and($egal)->toHaveCount(5);
    });
});

describe('region scoring', function (): void {
    it('scores exact place, region, traveling and neighbouring region as specified', function (): void {
        $calc = new ScoreCalculator;
        $answers = wizardAnswers(['regionSlug' => 'nordmarken', 'placeSlug' => 'havena']);

        $place = adventureCandidate(['primaryPlaceSlug' => 'havena', 'primaryRegionSlug' => 'nordmarken']);
        $region = adventureCandidate(['primaryPlaceSlug' => 'riva', 'primaryRegionSlug' => 'nordmarken']);
        $corridor = adventureCandidate([
            'locationType' => LocationType::Corridor,
            'primaryRegionSlug' => 'mittelaventurien',
            'regionSlugs' => ['mittelaventurien', 'nordmarken'],
        ]);
        $traveling = adventureCandidate(['locationType' => LocationType::Traveling, 'regionSlugs' => ['suedwest']]);
        $neighbor = adventureCandidate(['primaryRegionSlug' => 'mittelaventurien']);
        $other = adventureCandidate(['primaryRegionSlug' => 'suedwest']);

        expect($calc->regionScore($answers, $place)[0])->toBe(100)
            ->and($calc->regionScore($answers, $region)[0])->toBe(80)
            ->and($calc->regionScore($answers, $corridor)[0])->toBe(80)
            ->and($calc->regionScore($answers, $traveling)[0])->toBe(60)
            ->and($calc->regionScore($answers, $neighbor)[0])->toBe(50)
            ->and($calc->regionScore($answers, $other)[0])->toBe(20);
    });

    it('treats Seefahrt as neighbour of every Aventurian region', function (): void {
        $calc = new ScoreCalculator;
        $sea = adventureCandidate(['primaryRegionSlug' => 'seefahrt']);

        foreach (['nordmarken', 'mittelaventurien', 'tulamidenlande', 'suedaventurien', 'suedwest'] as $region) {
            expect($calc->regionScore(wizardAnswers(['regionSlug' => $region]), $sea)[0])->toBe(50);
        }

        expect($calc->regionScore(wizardAnswers(['regionSlug' => 'seefahrt']), $sea)[0])->toBe(80);
    });

    it('follows the neighbour chain Nordmarken to Südwest', function (): void {
        $calc = new ScoreCalculator;
        $candidate = fn (string $region) => adventureCandidate(['primaryRegionSlug' => $region]);

        $pairs = [
            ['nordmarken', 'mittelaventurien', 50],
            ['mittelaventurien', 'tulamidenlande', 50],
            ['tulamidenlande', 'suedaventurien', 50],
            ['suedaventurien', 'suedwest', 50],
            ['nordmarken', 'tulamidenlande', 20],
            ['nordmarken', 'suedwest', 20],
            ['myranor', 'nordmarken', 20],
        ];

        foreach ($pairs as [$wanted, $actual, $expected]) {
            expect($calc->regionScore(wizardAnswers(['regionSlug' => $wanted]), $candidate($actual))[0])
                ->toBe($expected, "$wanted vs $actual");
        }
    });

    it('scores a neighbour of any selected region and withholds the bonus from an unconnected region', function (): void {
        $calc = new ScoreCalculator;
        $answers = wizardAnswers([
            'regionSlugs' => ['nordmarken', 'myranor'],
            'regionsAny' => false,
        ]);

        expect($calc->regionScore($answers, adventureCandidate(['primaryRegionSlug' => 'mittelaventurien']))[0])->toBe(50)
            ->and($calc->regionScore($answers, adventureCandidate(['primaryRegionSlug' => 'suedwest']))[0])->toBe(20)
            ->and($calc->regionScore($answers, adventureCandidate(['primaryRegionSlug' => 'nordmarken']))[0])->toBe(80);
    });

    it('is neutral without a region wish and handles a place-only wish', function (): void {
        $calc = new ScoreCalculator;

        expect($calc->regionScore(wizardAnswers(), adventureCandidate())[0])->toBe(100);

        $withoutRegion = wizardAnswers(['placeSlug' => 'gareth']);
        expect($withoutRegion->placeSlug)->toBeNull()
            ->and($calc->regionScore($withoutRegion, adventureCandidate(['primaryPlaceSlug' => 'gareth']))[0])->toBe(100);
    });

    it('ranks place above region above traveling above neighbour', function (): void {
        $result = $this->finder->find(wizardAnswers(['regionSlug' => 'nordmarken', 'placeSlug' => 'havena']), [
            adventureCandidate(['slug' => 'nachbar', 'primaryRegionSlug' => 'mittelaventurien']),
            adventureCandidate(['slug' => 'reise', 'locationType' => LocationType::Traveling, 'regionSlugs' => ['suedwest']]),
            adventureCandidate(['slug' => 'region', 'primaryPlaceSlug' => 'riva', 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'ort', 'primaryPlaceSlug' => 'havena', 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'woanders', 'primaryRegionSlug' => 'suedwest']),
        ]);

        expect(recommendedSlugs($result->recommendations))->toBe(['ort', 'region', 'reise'])
            ->and($result->fallbackNotes)->toBe([]);
    });

    it('treats Myranor as extra-Aventurian for the exotic wish', function (): void {
        $result = $this->finder->find(wizardAnswers(['exotic' => 'extra_aventurian']), [
            adventureCandidate(['slug' => 'myranor', 'primaryRegionSlug' => 'myranor', 'exotic' => true]),
            adventureCandidate(['slug' => 'exotisch', 'primaryRegionSlug' => 'tulamidenlande', 'exotic' => true]),
            adventureCandidate(['slug' => 'klassisch', 'primaryRegionSlug' => 'tulamidenlande']),
        ]);

        expect(recommendedSlugs($result->recommendations))->toBe(['myranor', 'exotisch', 'klassisch']);
    });
});

describe('soft scores', function (): void {
    it('scores genre overlap as ratio of wished genres', function (): void {
        $calc = new ScoreCalculator;

        expect($calc->genreScore(['horror', 'dungeon'], ['horror', 'dungeon', 'humor']))->toBe(100)
            ->and($calc->genreScore(['horror', 'dungeon'], ['horror']))->toBe(50)
            ->and($calc->genreScore(['horror', 'dungeon'], ['humor']))->toBe(0)
            ->and($calc->genreScore(['horror', 'horror'], ['horror']))->toBe(100)
            ->and($calc->genreScore([], ['horror']))->toBe(100);
    });

    it('ranks adventures by genre overlap', function (): void {
        $result = $this->finder->find(wizardAnswers(['genres' => ['horror', 'dungeon']]), [
            adventureCandidate(['slug' => 'keins', 'genres' => ['humor']]),
            adventureCandidate(['slug' => 'beide', 'genres' => ['horror', 'dungeon']]),
            adventureCandidate(['slug' => 'eins', 'genres' => ['dungeon', 'humor']]),
        ]);

        expect(recommendedSlugs($result->recommendations))->toBe(['beide', 'eins', 'keins'])
            ->and($result->recommendations[0]->reasons)->toContain('Deckt die Genres Horror und Dungeon ab.');
    });

    it('matches the timeframe to the duration category with a page count proxy', function (): void {
        $calc = new ScoreCalculator;
        $wish = DurationWish::EveningShort;

        $short = adventureCandidate(['durationCategory' => DurationCategory::Kurz, 'pageCount' => 24]);
        $mismatchedPages = adventureCandidate(['durationCategory' => DurationCategory::Kurz, 'pageCount' => 400]);
        $long = adventureCandidate(['durationCategory' => DurationCategory::Lang]);

        expect($calc->timeframeScore($wish, $short))->toBe(100)
            ->and($calc->timeframeScore($wish, $mismatchedPages))->toBeLessThan(100)->toBeGreaterThan(50)
            ->and($calc->timeframeScore($wish, $long))->toBe(10);
    });

    it('scores the GM experience against adventure complexity', function (): void {
        $calc = new ScoreCalculator;

        expect($calc->gmScore(ExperienceLevel::Einsteiger, Complexity::Niedrig))->toBe(100)
            ->and($calc->gmScore(ExperienceLevel::Einsteiger, Complexity::Mittel))->toBe(50)
            ->and($calc->gmScore(ExperienceLevel::Einsteiger, Complexity::Hoch))->toBe(10)
            ->and($calc->gmScore(ExperienceLevel::Fortgeschritten, Complexity::Niedrig))->toBe(80)
            ->and($calc->gmScore(ExperienceLevel::Meister, Complexity::Niedrig))->toBe(60)
            ->and($calc->gmScore(ExperienceLevel::Meister, Complexity::Hoch))->toBe(100);
    });

    it('prefers adventures matching the exotic wish', function (): void {
        $noExotic = $this->finder->find(wizardAnswers(['exotic' => 'no']), [
            adventureCandidate(['slug' => 'exotisch', 'exotic' => true]),
            adventureCandidate(['slug' => 'normal-1']),
            adventureCandidate(['slug' => 'normal-2']),
        ]);
        $cultures = $this->finder->find(wizardAnswers(['exotic' => 'unusual_cultures']), [
            adventureCandidate(['slug' => 'exotisch', 'exotic' => true]),
            adventureCandidate(['slug' => 'normal-1']),
            adventureCandidate(['slug' => 'normal-2']),
        ]);

        expect($noExotic->recommendations[2]->adventure->slug)->toBe('exotisch')
            ->and($cultures->recommendations[0]->adventure->slug)->toBe('exotisch');
    });

    it('keeps all scores within 0 to 100', function (): void {
        $result = $this->finder->find(wizardAnswers(['genres' => ['horror']]), [
            adventureCandidate(['genres' => ['horror']]),
            adventureCandidate(['genres' => []]),
            adventureCandidate(['genres' => ['horror', 'humor']]),
        ]);

        foreach ($result->recommendations as $recommendation) {
            expect($recommendation->score)->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100);
        }
    });
});

describe('selection', function (): void {
    it('limits results to at most six recommendations', function (): void {
        $candidates = array_map(fn () => adventureCandidate(), range(1, 12));

        $result = $this->finder->find(wizardAnswers(), $candidates);

        expect($result->recommendations)->toHaveCount(MatchFinder::MAX_RECOMMENDATIONS);
    });

    it('prefers at most two adventures per series', function (): void {
        $same = fn (string $slug) => adventureCandidate([
            'slug' => $slug,
            'seriesSlug' => 'dominante-serie',
            'seriesName' => 'Dominante Serie',
            'genres' => ['horror'],
        ]);

        $candidates = [
            $same('dom-1'), $same('dom-2'), $same('dom-3'), $same('dom-4'), $same('dom-5'),
            adventureCandidate(['slug' => 'andere-1', 'genres' => []]),
            adventureCandidate(['slug' => 'andere-2', 'genres' => []]),
            adventureCandidate(['slug' => 'andere-3', 'genres' => []]),
        ];

        $result = $this->finder->find(wizardAnswers(['genres' => ['horror']]), $candidates);

        $seriesCounts = array_count_values(array_map(
            static fn (MatchRecommendation $r): string => $r->adventure->seriesSlug,
            $result->recommendations,
        ));

        expect($result->recommendations)->toHaveCount(5)
            ->and($seriesCounts['dominante-serie'])->toBe(2)
            ->and(recommendedSlugs($result->recommendations))->toContain('dom-1', 'dom-2', 'andere-1');
    });

    it('backfills from the same series when diversity would leave fewer than three results', function (): void {
        $candidates = array_map(fn (int $i) => adventureCandidate([
            'slug' => "gleich-$i",
            'seriesSlug' => 'einzige-serie',
        ]), range(1, 4));

        $result = $this->finder->find(wizardAnswers(), $candidates);

        expect($result->recommendations)->toHaveCount(MatchFinder::MIN_RECOMMENDATIONS);
    });

    it('is deterministic and orders by score descending', function (): void {
        $candidates = [
            adventureCandidate(['title' => 'B Titel', 'genres' => ['horror']]),
            adventureCandidate(['title' => 'A Titel', 'genres' => ['horror']]),
            adventureCandidate(['title' => 'C Titel', 'genres' => []]),
        ];
        $answers = wizardAnswers(['genres' => ['horror']]);

        $first = $this->finder->find($answers, $candidates);
        $second = $this->finder->find($answers, array_reverse($candidates));

        expect(recommendedSlugs($first->recommendations))->toBe(recommendedSlugs($second->recommendations))
            ->and($first->recommendations[0]->adventure->title)->toBe('A Titel')
            ->and($first->recommendations[0]->score)->toBeGreaterThan($first->recommendations[2]->score);
    });

    it('accepts any iterable of candidates', function (): void {
        $generator = (function (): Generator {
            yield adventureCandidate();
            yield adventureCandidate();
            yield adventureCandidate();
        })();

        expect($this->finder->find(wizardAnswers(), $generator)->recommendations)->toHaveCount(3);
    });
});

describe('fallback', function (): void {
    it('ignores the region and explains why when too few strict matches exist', function (): void {
        $result = $this->finder->find(wizardAnswers(['regionSlug' => 'nordmarken']), [
            adventureCandidate(['slug' => 'nord', 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'sued-1', 'primaryRegionSlug' => 'suedwest']),
            adventureCandidate(['slug' => 'sued-2', 'primaryRegionSlug' => 'suedwest']),
            adventureCandidate(['slug' => 'sued-3', 'primaryRegionSlug' => 'myranor']),
        ]);

        expect($result->fallbackNotes)->toBe(['Region wurde ignoriert, weil nur 1 Treffer gefunden wurde.'])
            ->and($result->usedFallback())->toBeTrue()
            ->and($result->recommendations)->toHaveCount(4)
            ->and($result->recommendations[0]->adventure->slug)->toBe('nord');
    });

    it('uses plural wording and ignores the timeframe after the region', function (): void {
        $lang = fn (string $region) => adventureCandidate([
            'primaryRegionSlug' => $region,
            'durationCategory' => DurationCategory::Lang,
        ]);

        $result = $this->finder->find(wizardAnswers(['regionSlug' => 'nordmarken']), [
            adventureCandidate(['primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['primaryRegionSlug' => 'nordmarken']),
            $lang('nordmarken'),
            $lang('suedwest'),
        ]);

        expect($result->fallbackNotes)->toBe([
            'Region wurde ignoriert, weil nur 2 Treffer gefunden wurden.',
            'Zeitrahmen wurde ignoriert, weil nur 2 Treffer gefunden wurden.',
        ])->and($result->recommendations)->toHaveCount(4);
    });

    it('ignores the timeframe when no region is wished', function (): void {
        $result = $this->finder->find(wizardAnswers(['timeframe' => 'evening_short']), [
            adventureCandidate(['durationCategory' => DurationCategory::Lang]),
            adventureCandidate(['durationCategory' => DurationCategory::Lang]),
            adventureCandidate(['durationCategory' => DurationCategory::Kampagne]),
        ]);

        expect($result->fallbackNotes)->toBe(['Zeitrahmen wurde ignoriert, weil es keine Treffer gab.'])
            ->and($result->recommendations)->toHaveCount(3);
    });

    it('widens the experience tolerance as the last step', function (): void {
        $result = $this->finder->find(wizardAnswers(['experienceLevel' => 'einsteiger']), [
            adventureCandidate(['experienceLevel' => ExperienceLevel::Erfahren]),
            adventureCandidate(['experienceLevel' => ExperienceLevel::Erfahren]),
            adventureCandidate(['experienceLevel' => ExperienceLevel::Erfahren]),
            adventureCandidate(['slug' => 'zu-weit-weg', 'experienceLevel' => ExperienceLevel::Meister]),
        ]);

        expect($result->fallbackNotes)->toBe([
            'Zeitrahmen wurde ignoriert, weil es keine Treffer gab.',
            'Erfahrungsstufe wurde auf ±2 Stufen erweitert, weil es keine Treffer gab.',
        ])->and($result->recommendations)->toHaveCount(3)
            ->and(recommendedSlugs($result->recommendations))->not->toContain('zu-weit-weg');
    });

    it('relaxes in the documented order region, timeframe, experience', function (): void {
        $result = $this->finder->find(wizardAnswers(['regionSlug' => 'nordmarken']), [
            adventureCandidate([
                'primaryRegionSlug' => 'suedwest',
                'durationCategory' => DurationCategory::Lang,
                'experienceLevel' => ExperienceLevel::Erfahren,
            ]),
        ]);

        expect($result->fallbackNotes)->toHaveCount(3)
            ->and($result->fallbackNotes[0])->toStartWith('Region wurde ignoriert')
            ->and($result->fallbackNotes[1])->toStartWith('Zeitrahmen wurde ignoriert')
            ->and($result->fallbackNotes[2])->toStartWith('Erfahrungsstufe wurde')
            ->and($result->recommendations)->toHaveCount(1);
    });

    it('returns an empty result with notes when nothing matches at all', function (): void {
        $result = $this->finder->find(wizardAnswers(['regionSlug' => 'nordmarken']), []);

        expect($result->isEmpty())->toBeTrue()
            ->and($result->fallbackNotes)->toHaveCount(3);
    });

    it('adds no notes when enough strict matches exist', function (): void {
        $result = $this->finder->find(wizardAnswers(['regionSlug' => 'nordmarken']), array_map(
            fn () => adventureCandidate(['primaryRegionSlug' => 'nordmarken']),
            range(1, 3),
        ));

        expect($result->fallbackNotes)->toBe([])->and($result->usedFallback())->toBeFalse();
    });
});

describe('reasons', function (): void {
    it('always provides at least one German reason', function (): void {
        $result = $this->finder->find(wizardAnswers(), array_map(fn () => adventureCandidate(), range(1, 4)));

        foreach ($result->recommendations as $recommendation) {
            expect($recommendation->reasons)->not->toBeEmpty();
            foreach ($recommendation->reasons as $reason) {
                expect($reason)->toBeString()->not->toBeEmpty();
            }
        }
    });

    it('references the actual answers', function (): void {
        $answers = wizardAnswers([
            'experienceLevel' => 'einsteiger',
            'groupSize' => '3-5',
            'timeframe' => 'evening_short',
            'regionSlug' => 'nordmarken',
            'placeSlug' => 'havena',
            'exotic' => 'no',
            'genres' => ['investigation', 'seafaring'],
            'rules' => 'dsa5',
            'gmExperience' => 'einsteiger',
        ]);

        $result = $this->finder->find($answers, [
            adventureCandidate([
                'primaryPlaceSlug' => 'havena',
                'primaryPlaceName' => 'Havena',
                'primaryRegionSlug' => 'nordmarken',
                'groupSizeMin' => 3,
                'groupSizeMax' => 5,
                'genres' => ['investigation', 'seafaring'],
            ]),
            adventureCandidate(['primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['primaryRegionSlug' => 'nordmarken']),
        ]);

        $reasons = $result->recommendations[0]->reasons;

        expect($reasons)->toContain('Geeignet für Einsteiger.')
            ->and(implode(' ', $reasons))
            ->toContain('Havena')
            ->toContain('Deckt die Genres Ermittlung und Seefahrt ab.')
            ->toContain('Dauer passt zu einen kurzen Abend')
            ->toContain('Verfügbar für DSA 5')
            ->toContain('Geeignet für 3 bis 5 Spielende.')
            ->toContain('klassisch aventurischen')
            ->toContain('Komplexität „niedrig“');
    });

    it('mentions the region name for region, traveling and neighbour matches', function (): void {
        $answers = wizardAnswers(['regionSlug' => 'nordmarken']);

        $result = $this->finder->find($answers, [
            adventureCandidate(['slug' => 'region', 'primaryRegionSlug' => 'nordmarken']),
            adventureCandidate(['slug' => 'reise', 'locationType' => LocationType::Traveling]),
            adventureCandidate(['slug' => 'nachbar', 'primaryRegionSlug' => 'mittelaventurien']),
        ]);

        $bySlug = [];
        foreach ($result->recommendations as $recommendation) {
            $bySlug[$recommendation->adventure->slug] = implode(' ', $recommendation->reasons);
        }

        expect($bySlug['region'])->toContain('Passt zur Region Nordmarken.')
            ->and($bySlug['reise'])->toContain('Reiseabenteuer')->toContain('Nordmarken')
            ->and($bySlug['nachbar'])->toContain('Mittelaventurien')->toContain('neben deiner Wunschregion Nordmarken');
    });

    it('explains nearby experience levels and extra-Aventurian settings', function (): void {
        $result = $this->finder->find(wizardAnswers([
            'experienceLevel' => 'fortgeschritten',
            'exotic' => 'extra_aventurian',
            'gmExperience' => 'meister',
            'regionSlug' => 'myranor',
        ]), [
            adventureCandidate([
                'experienceLevel' => ExperienceLevel::Erfahren,
                'primaryRegionSlug' => 'myranor',
                'exotic' => true,
                'complexity' => Complexity::Hoch,
            ]),
        ]);

        $text = implode(' ', $result->recommendations[0]->reasons);

        expect($text)->toContain('Richtet sich an Erfahrene')
            ->toContain('Fortgeschrittene')
            ->toContain('abseits des gewohnten Aventurien')
            ->toContain('Passt zur Region Myranor');
    });

    it('describes unusual cultures and single genres', function (): void {
        $result = $this->finder->find(wizardAnswers(['exotic' => 'unusual_cultures', 'genres' => ['humor']]), [
            adventureCandidate(['exotic' => true, 'genres' => ['humor']]),
        ]);

        $text = implode(' ', $result->recommendations[0]->reasons);

        expect($text)->toContain('ungewöhnliche Kulturen')->toContain('Deckt das Genre Humor ab.');
    });

    it('formats unknown genre slugs gracefully', function (): void {
        $builder = new ReasonBuilder;

        expect($builder->genreLabel('horror'))->toBe('Horror')
            ->and($builder->genreLabel('mystery'))->toBe('Mystery');
    });
});

describe('performance', function (): void {
    it('scores 2000 synthetic candidates in under one second', function (): void {
        mt_srand(42);

        $experiences = ExperienceLevel::cases();
        $durations = DurationCategory::cases();
        $rules = RuleVersion::cases();
        $complexities = Complexity::cases();
        $types = LocationType::cases();
        $regions = ['nordmarken', 'mittelaventurien', 'tulamidenlande', 'suedaventurien', 'suedwest', 'seefahrt', 'myranor'];
        $genres = ['investigation', 'dungeon', 'intrigue', 'horror', 'humor', 'exploration', 'social', 'seafaring'];

        /** @var list<AdventureCandidate> $candidates */
        $candidates = [];
        for ($i = 1; $i <= 2000; $i++) {
            $region = $regions[mt_rand(0, count($regions) - 1)];
            $candidates[] = new AdventureCandidate(
                id: $i,
                slug: "synthetisch-$i",
                title: "Synthetisch $i",
                seriesSlug: 'serie-'.mt_rand(1, 40),
                seriesName: 'Serie',
                experienceLevel: $experiences[mt_rand(0, 3)],
                pageCount: mt_rand(0, 1) === 1 ? mt_rand(16, 320) : null,
                durationCategory: $durations[mt_rand(0, 3)],
                ruleVersion: $rules[mt_rand(0, count($rules) - 1)],
                exotic: mt_rand(0, 4) === 0,
                complexity: $complexities[mt_rand(0, 2)],
                groupSizeMin: mt_rand(0, 1) === 1 ? mt_rand(2, 4) : null,
                groupSizeMax: mt_rand(0, 1) === 1 ? mt_rand(5, 8) : null,
                locationType: $types[mt_rand(0, 2)],
                primaryPlaceSlug: null,
                primaryPlaceName: null,
                primaryRegionSlug: $region,
                regionSlugs: [$regions[mt_rand(0, count($regions) - 1)]],
                genres: array_slice($genres, mt_rand(0, 5), mt_rand(1, 3)),
                shortDescription: 'Synthetisch erzeugte Beschreibung.',
                wikiUrl: 'https://de.wiki-aventurica.de/wiki/Synthetisch',
            );
        }

        $answers = wizardAnswers([
            'experienceLevel' => 'fortgeschritten',
            'groupSize' => '3-5',
            'timeframe' => 'multi',
            'regionSlug' => 'mittelaventurien',
            'genres' => ['investigation', 'intrigue'],
            'rules' => 'dsa5',
            'gmExperience' => 'fortgeschritten',
        ]);

        $start = microtime(true);
        $result = $this->finder->find($answers, $candidates);
        $elapsed = microtime(true) - $start;

        expect($elapsed)->toBeLessThan(1.0)
            ->and($result->recommendations)->not->toBeEmpty()
            ->and(count($result->recommendations))->toBeLessThanOrEqual(MatchFinder::MAX_RECOMMENDATIONS);
    });
});
