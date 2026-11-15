import { describe, expect, it } from 'vitest';
import { AdventureCandidate, type AdventureCandidateInput, regionIndex, regionsForLabel, regionsMentionedIn } from './adventure-candidate';
import {
    Complexity,
    DurationCategory,
    DurationWish,
    ExperienceLevel,
    ExoticWish,
    GroupSizeWish,
    LocationKind,
    LocationType,
    RuleVersion,
    RulesWish,
    complexityIdealFor,
    durationFit,
    durationFromPageCount,
    durationWishLabel,
    exoticFit,
    groupSizeCompatible,
    groupSizeExactlyCovered,
    groupSizeLabel,
    regionLabelFor,
    rulesWishAccepts,
    rulesWishLabel,
} from './enums';
import { MatchFinder } from './match-finder';
import { ReasonBuilder } from './reason-builder';
import { ScoreCalculator } from './score';
import { WizardFacetFilter } from './wizard-facet-filter';
import { WizardAnswers, type WizardAnswerInput } from './wizard-answers';

function row(overrides: Partial<AdventureCandidateInput> = {}): AdventureCandidate {
    return AdventureCandidate.fromInput({
        id: 1,
        slug: 'probe',
        title: 'Probe',
        seriesSlug: 'serie',
        seriesName: 'Serie',
        experienceLevel: ExperienceLevel.Einsteiger,
        pageCount: 32,
        durationCategory: DurationCategory.Kurz,
        ruleVersion: RuleVersion.Dsa5,
        exotic: false,
        complexity: Complexity.Niedrig,
        groupSizeMin: null,
        groupSizeMax: null,
        locationType: LocationType.Point,
        primaryPlaceSlug: null,
        primaryPlaceName: null,
        primaryRegionSlug: 'nordmarken',
        regionSlugs: [],
        genres: ['investigation'],
        shortDescription: 'Kurz',
        wikiUrl: 'https://de.wiki-aventurica.de/wiki/Probe',
        ...overrides,
    });
}

function wish(overrides: Partial<WizardAnswerInput> = {}): WizardAnswers {
    return WizardAnswers.fromInput({
        experienceLevel: ExperienceLevel.Einsteiger,
        groupSize: null,
        timeframes: [DurationWish.EveningShort],
        regionSlugs: [],
        regionsAny: false,
        placeSlug: null,
        exotics: [],
        exoticAny: true,
        genres: [],
        rules: [],
        rulesAny: true,
        gmExperience: ExperienceLevel.Einsteiger,
        ...overrides,
    });
}

describe('domain edges', () => {
    it('restores a session and drops legacy text fields', () => {
        const restored = WizardAnswers.fromSession({
            experienceLevel: 'meister',
            groupSize: '3-5',
            timeframes: ['campaign', 'campaign', ''],
            regionSlugs: ['nordmarken'],
            regionsAny: false,
            placeSlug: 'havena',
            exotics: ['no'],
            exoticAny: false,
            genres: ['investigation', 'humor'],
            rules: 'dsa5',
            rulesAny: false,
            gmExperience: 'erfahren',
        });

        expect(restored.rules).toEqual([]);
        expect(restored.placeSlug).toBe('havena');
        expect(restored.timeframes).toEqual(['campaign']);
        expect(restored.groupSizeWish()).toBe(GroupSizeWish.ThreeToFive);
        expect(WizardAnswers.fromSession({}).gmLevel()).toBe(ExperienceLevel.Einsteiger);
        expect(wish({ rules: ['', 'dsa5'], rulesAny: true }).rules).toEqual(['dsa5']);
        expect(wish({ regionSlugs: ['nordmarken', 'myranor'], placeSlug: 'havena' }).placeSlug).toBeNull();
    });

    it('describes klappentext, a dash, and a place label', () => {
        expect(row({ klappentext: 'Ein Klappentext' }).displayDescription()?.kind).toBe('klappentext');
        expect(row({ shortDescription: '-', klappentext: '-' }).displayDescription()).toBeNull();
        expect(row({ placeLabel: 'Havena und Riva' }).matchesPlaceSlug('havena')).toBe(true);
        expect(row({ primaryRegionSlug: 'myranor' }).isExtraAventurian()).toBe(true);

        const index = regionIndex({ 'havena-nordmarken': 'nordmarken', riva: 'nordmarken' });

        expect(regionsForLabel('Havena, Riva', index).length).toBeGreaterThan(0);
        expect(regionsMentionedIn('Thorwal', { thorwal: 'thorwal' })).toContain('thorwal');
        expect(regionsForLabel('   ', index)).toEqual([]);
    });

    it('scores traveling, neighbor, empty wishes and page proxies', () => {
        const calculator = new ScoreCalculator();
        const traveling = row({ locationType: LocationType.Traveling, primaryRegionSlug: 'suedwest' });
        const far = row({ primaryRegionSlug: 'suedwest' });

        expect(calculator.regionScore(wish({ timeframes: [] }), traveling)[1]).toBe('any');
        expect(calculator.regionScore(wish({ regionSlugs: ['nordmarken'] }), traveling)[1]).toBe('traveling');
        expect(calculator.regionScore(wish({ regionSlugs: ['nordmarken'] }), row({ primaryRegionSlug: 'seefahrt' }))[1]).toBe('neighbor');
        expect(calculator.regionScore(wish({ regionSlugs: ['myranor'] }), far)[1]).toBe('none');
        expect(calculator.regionScore(wish({ placeSlug: 'havena', regionSlugs: ['nordmarken'] }), row({ primaryPlaceSlug: 'havena', primaryPlaceName: 'Havena' }))[1]).toBe('place');
        expect(calculator.timeframeScore(DurationWish.Campaign, row({ pageCount: null }))).toBe(durationFit(DurationWish.Campaign, DurationCategory.Kurz));
        expect(calculator.bestTimeframeScore(wish({ timeframes: [] }), row())).toBe(100);
        expect(calculator.bestExoticScore(wish({ exotics: ['unusual_cultures'], exoticAny: false }), row({ exotic: true }))).toBe(100);
        expect(calculator.genreScore([], [])).toBe(100);
        expect(calculator.genreScore(['investigation', 'horror'], ['investigation'])).toBe(50);
        expect(calculator.gmScore(ExperienceLevel.Einsteiger, Complexity.Hoch)).toBe(10);
        expect(calculator.gmScore(ExperienceLevel.Einsteiger, Complexity.Mittel)).toBe(50);
        expect(calculator.gmScore(ExperienceLevel.Meister, Complexity.Mittel)).toBe(80);
        expect(calculator.gmScore(ExperienceLevel.Meister, Complexity.Niedrig)).toBe(60);
        expect(calculator.experienceScore(2)).toBe(40);
        expect(durationFromPageCount(40)).toBe(DurationCategory.Mittel);
        expect(durationFromPageCount(80)).toBe(DurationCategory.Lang);
        expect(durationFromPageCount(200)).toBe(DurationCategory.Kampagne);
        expect(durationWishLabel(DurationWish.EveningLong)).toContain('langen');
        expect(durationWishLabel(DurationWish.Multi)).toContain('mehrere');
        expect(durationWishLabel(DurationWish.Campaign)).toContain('Kampagne');
    });

    it('builds reasons for several wishes and unknown genres', () => {
        const builder = new ReasonBuilder();
        const current = wish({
            regionSlugs: ['nordmarken', 'seefahrt'],
            exotics: ['no'],
            exoticAny: false,
            genres: ['investigation', 'mystery'],
            rules: ['dsa5', 'dsa4'],
            rulesAny: false,
            gmExperience: ExperienceLevel.Meister,
        });
        const adventure = row({
            experienceLevel: ExperienceLevel.Fortgeschritten,
            pageCount: 80,
            durationCategory: DurationCategory.Mittel,
            complexity: Complexity.Hoch,
            groupSizeMin: 3,
            groupSizeMax: 5,
            genres: ['investigation', 'mystery'],
        });
        const reasons = builder.build(current, adventure, new ScoreCalculator().score(current, adventure));

        expect(reasons.join(' ')).toContain('Genres');
        expect(builder.genreLabel('mystery')).toBe('Mystery');

        const extra = wish({ exotics: ['extra_aventurian'], exoticAny: false, regionSlugs: ['myranor'] });
        const abroad = row({ primaryRegionSlug: 'myranor', exotic: true });

        expect(builder.build(extra, abroad, new ScoreCalculator().score(extra, abroad)).join(' ')).toContain('Aventurien');
        expect(builder.build(wish({ groupSize: '2' }), row({ groupSizeMin: 2, groupSizeMax: 2 }), new ScoreCalculator().score(wish({ groupSize: '2' }), row({ groupSizeMin: 2, groupSizeMax: 2 }))).join(' ')).toContain('2 Spielende');
    });

    it('lists facets and drops a wiki gap', () => {
        const filter = new WizardFacetFilter();
        const rows = [
            row(),
            row({
                id: 2,
                slug: 'myr',
                title: 'Myranor',
                seriesSlug: 'myranor',
                seriesName: 'Myranor',
                ruleVersion: RuleVersion.Dsa4,
                exotic: true,
                complexity: Complexity.Hoch,
                groupSizeMin: 6,
                groupSizeMax: 8,
                primaryRegionSlug: 'myranor',
                genres: ['horror'],
            }),
            row({
                id: 3,
                slug: 'weg',
                title: 'Weg',
                seriesSlug: 'frei',
                seriesName: 'Frei',
                durationCategory: DurationCategory.Kampagne,
                pageCount: 200,
                ruleVersion: RuleVersion.EditionUnabhaengig,
                complexity: Complexity.Mittel,
                groupSizeMin: 2,
                groupSizeMax: 2,
                locationType: LocationType.Traveling,
            }),
            row({ id: 4, slug: 'fehlt', title: 'Fehlt', missingFromWiki: true }),
        ];
        const blank = wish({
            experienceLevel: '',
            timeframes: [],
            gmExperience: '',
        });

        expect(filter.experienceLevels(blank, rows)).toContain(ExperienceLevel.Einsteiger);
        expect(filter.groupSizes(blank, rows)).toContain('egal');
        expect(filter.timeframes(blank, rows)).toContain(DurationWish.EveningShort);
        expect(filter.regionSlugs(blank, rows)).toContain('nordmarken');
        expect(filter.exoticWishes(blank, rows)).toContain(ExoticWish.ExtraAventurian);
        expect(filter.genreSlugs(blank, rows, ['investigation', 'horror', 'missing'])).toEqual(['investigation', 'horror']);
        expect(filter.rulesWishes(blank, rows)).toContain(RuleVersion.Dsa5);
        expect(filter.gmExperienceLevels(blank, rows).length).toBeGreaterThan(0);
        expect(filter.pool(wish(), rows).every((entry) => entry.missingFromWiki === false)).toBe(true);
        expect(filter.groupSizes(wish({ genres: ['kein-genre'] }), rows)).toEqual([]);
        expect(filter.exoticWishes(wish({ genres: ['kein-genre'] }), rows)).toEqual([]);
        expect(filter.rulesWishes(wish({ genres: ['kein-genre'] }), rows)).toEqual([]);
    });

    it('returns no recommendation when every row is missing from the wiki', () => {
        const result = new MatchFinder().find(wish(), [row({ missingFromWiki: true })]);

        expect(result.recommendations).toEqual([]);
        expect(result.fallbackNotes.length).toBeGreaterThan(0);
    });

    it('covers enum helpers', () => {
        expect(rulesWishAccepts(RulesWish.Any, RuleVersion.Dsa1)).toBe(true);
        expect(rulesWishAccepts(RulesWish.Dsa5, RuleVersion.Dsa4)).toBe(false);
        expect(rulesWishAccepts(RulesWish.Dsa4, RuleVersion.EditionUnabhaengig)).toBe(true);
        expect(rulesWishLabel(RulesWish.Dsa5)).toBe('DSA 5');
        expect(rulesWishLabel(RulesWish.Dsa4)).toBe('DSA 4');
        expect(rulesWishLabel(RulesWish.Any)).toContain('beliebigen');
        expect(groupSizeCompatible(GroupSizeWish.Two, 6, 8)).toBe(false);
        expect(groupSizeCompatible(GroupSizeWish.Two, null, null)).toBe(true);
        expect(groupSizeExactlyCovered(GroupSizeWish.SixPlus, 6, null)).toBe(true);
        expect(groupSizeLabel(GroupSizeWish.Two)).toContain('2');
        expect(groupSizeLabel(GroupSizeWish.ThreeToFive)).toContain('3');
        expect(groupSizeLabel(GroupSizeWish.SixPlus)).toContain('6');
        expect(exoticFit(ExoticWish.No, true, false)).toBe(0);
        expect(exoticFit(ExoticWish.No, false, false)).toBe(100);
        expect(exoticFit(ExoticWish.ExtraAventurian, true, false)).toBe(60);
        expect(exoticFit(ExoticWish.ExtraAventurian, false, false)).toBe(20);
        expect(exoticFit(ExoticWish.ExtraAventurian, false, true)).toBe(100);
        expect(exoticFit(ExoticWish.UnusualCultures, false, false)).toBe(30);
        expect(exoticFit(ExoticWish.Any, false, false)).toBe(100);
        expect(regionLabelFor('unbekannt-land')).toBe('Unbekannt land');
        expect(regionLabelFor('nordmarken')).toBe('Nordmarken');
        expect(complexityIdealFor(ExperienceLevel.Einsteiger)).toBe(Complexity.Niedrig);
        expect(complexityIdealFor(ExperienceLevel.Fortgeschritten)).toBe(Complexity.Mittel);
        expect(complexityIdealFor(ExperienceLevel.Meister)).toBe(Complexity.Hoch);
        expect(LocationKind.Place).toBe('place');
        expect(LocationKind.Continent).toBe('continent');
        expect(LocationKind.Region).toBe('region');
        expect(durationFit(DurationWish.EveningShort, DurationCategory.Kurz)).toBe(100);
    });
});
