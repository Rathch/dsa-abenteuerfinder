import { AdventureCandidate } from './adventure-candidate';
import {
    DURATION_WISHES,
    EXOTIC_WISHES,
    EXPERIENCE_LEVELS,
    ExoticWish,
    GROUP_SIZE_WISHES,
    REGION_KEYS,
    RULE_VERSIONS,
    RuleVersion,
    experienceFrom,
} from './enums';
import { ScoreCalculator } from './score';
import { WizardAnswers, type WizardAnswerInput } from './wizard-answers';

const ACCEPTABLE_FIT = 50;
const STRICT_EXPERIENCE_DISTANCE = 1;

export class WizardFacetFilter {
    static readonly FIELD_EXPERIENCE = 'experience';

    static readonly FIELD_GROUP_SIZE = 'groupSize';

    static readonly FIELD_TIMEFRAME = 'timeframe';

    static readonly FIELD_REGION = 'region';

    static readonly FIELD_EXOTIC = 'exotic';

    static readonly FIELD_GENRES = 'genres';

    static readonly FIELD_RULES = 'rules';

    static readonly FIELD_GM = 'gmExperience';

    constructor(private readonly scores = new ScoreCalculator()) {}

    pool(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>, except: string | null = null): AdventureCandidate[] {
        const pool: AdventureCandidate[] = [];

        for (const candidate of candidates) {
            if (this.passes(answers, candidate, except)) {
                pool.push(candidate);
            }
        }

        return pool;
    }

    experienceLevels(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): string[] {
        const base = this.pool(answers, candidates, WizardFacetFilter.FIELD_EXPERIENCE);

        return EXPERIENCE_LEVELS.filter((level) => this.pool(this.withAnswers(answers, { experienceLevel: level }), base).length > 0);
    }

    groupSizes(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): string[] {
        const base = this.pool(answers, candidates, WizardFacetFilter.FIELD_GROUP_SIZE);

        if (base.length === 0) {
            return [];
        }

        const values = GROUP_SIZE_WISHES.filter((wish) => this.pool(this.withAnswers(answers, { groupSize: wish }), base).length > 0);

        values.push('egal');

        return values;
    }

    timeframes(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): string[] {
        const base = this.pool(answers, candidates, WizardFacetFilter.FIELD_TIMEFRAME);

        return DURATION_WISHES.filter((wish) => this.pool(this.withAnswers(answers, { timeframes: [wish] }), base).length > 0);
    }

    regionSlugs(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): string[] {
        const base = this.pool(answers, candidates, WizardFacetFilter.FIELD_REGION);

        return REGION_KEYS.filter((region) => this.pool(this.withAnswers(answers, {
            regionSlugs: [region],
            regionsAny: false,
            placeSlug: null,
        }), base).length > 0);
    }

    exoticWishes(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): string[] {
        const base = this.pool(answers, candidates, WizardFacetFilter.FIELD_EXOTIC);

        if (base.length === 0) {
            return [];
        }

        const values: string[] = [];

        for (const wish of EXOTIC_WISHES) {
            if (wish === ExoticWish.Any) {
                values.push(wish);
                continue;
            }

            if (wish === ExoticWish.ExtraAventurian) {
                if (base.some((candidate) => candidate.isExtraAventurian())) {
                    values.push(wish);
                }

                continue;
            }

            if (this.pool(this.withAnswers(answers, { exotics: [wish], exoticAny: false }), base).length > 0) {
                values.push(wish);
            }
        }

        return values;
    }

    genreSlugs(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>, catalogSlugs: string[]): string[] {
        const present = new Set<string>();

        for (const candidate of this.pool(answers, candidates, WizardFacetFilter.FIELD_GENRES)) {
            for (const genre of candidate.genres) {
                present.add(genre);
            }
        }

        return catalogSlugs.filter((slug) => present.has(slug));
    }

    rulesWishes(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): string[] {
        const base = this.pool(answers, candidates, WizardFacetFilter.FIELD_RULES);

        if (base.length === 0) {
            return [];
        }

        const values = RULE_VERSIONS.filter((version) => version !== RuleVersion.EditionUnabhaengig && base.some((candidate) => candidate.ruleVersion === version));

        values.push('any');

        return values;
    }

    gmExperienceLevels(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): string[] {
        const base = this.pool(answers, candidates, WizardFacetFilter.FIELD_GM);

        return EXPERIENCE_LEVELS.filter((level) => this.pool(this.withAnswers(answers, { gmExperience: level }), base).length > 0);
    }

    private passes(answers: WizardAnswers, candidate: AdventureCandidate, except: string | null): boolean {
        if (candidate.missingFromWiki) {
            return false;
        }

        if (except !== WizardFacetFilter.FIELD_EXPERIENCE && answers.experienceLevel !== '' && experienceFrom(answers.experienceLevel) !== null) {
            if (Math.abs(rank(answers.experience()) - rank(candidate.experienceLevel)) > STRICT_EXPERIENCE_DISTANCE) {
                return false;
            }
        }

        if (except !== WizardFacetFilter.FIELD_GROUP_SIZE) {
            const group = answers.groupSizeWish();

            if (group !== null && !compatible(group, candidate.groupSizeMin, candidate.groupSizeMax)) {
                return false;
            }
        }

        if (except !== WizardFacetFilter.FIELD_TIMEFRAME && answers.restrictsDuration() && this.scores.bestTimeframeScore(answers, candidate) < ACCEPTABLE_FIT) {
            return false;
        }

        if (except !== WizardFacetFilter.FIELD_REGION && answers.hasRegionWish()) {
            if (answers.placeSlug !== null) {
                if (!candidate.matchesPlaceSlug(answers.placeSlug)) {
                    return false;
                }
            } else if (!answers.acceptsRegion(candidate.allRegionSlugs())) {
                return false;
            }
        }

        if (except !== WizardFacetFilter.FIELD_EXOTIC && answers.restrictsExotic() && this.scores.bestExoticScore(answers, candidate) < ACCEPTABLE_FIT) {
            return false;
        }

        if (except !== WizardFacetFilter.FIELD_GENRES && answers.genres.length > 0 && this.scores.genreScore(answers.genres, candidate.genres) <= 0) {
            return false;
        }

        if (except !== WizardFacetFilter.FIELD_RULES && answers.rules.length > 0 && !answers.acceptsRule(candidate.ruleVersion)) {
            return false;
        }

        if (except !== WizardFacetFilter.FIELD_GM && answers.gmExperience !== '' && experienceFrom(answers.gmExperience) !== null && this.scores.gmScore(answers.gmLevel(), candidate.complexity) < ACCEPTABLE_FIT) {
            return false;
        }

        return true;
    }

    private withAnswers(base: WizardAnswers, overrides: Partial<WizardAnswerInput>): WizardAnswers {
        return new WizardAnswers(
            overrides.experienceLevel ?? base.experienceLevel,
            Object.prototype.hasOwnProperty.call(overrides, 'groupSize') ? overrides.groupSize ?? null : base.groupSize,
            overrides.timeframes ?? base.timeframes,
            overrides.regionSlugs ?? base.regionSlugs,
            overrides.regionsAny ?? base.regionsAny,
            Object.prototype.hasOwnProperty.call(overrides, 'placeSlug') ? overrides.placeSlug ?? null : base.placeSlug,
            overrides.exotics ?? base.exotics,
            overrides.exoticAny ?? base.exoticAny,
            overrides.genres ?? base.genres,
            overrides.rules ?? base.rules,
            overrides.rulesAny ?? base.rulesAny,
            overrides.gmExperience ?? base.gmExperience,
        );
    }
}

function rank(level: string): number {
    return { einsteiger: 0, fortgeschritten: 1, erfahren: 2, meister: 3 }[level] ?? 0;
}

function compatible(wish: string, min: number | null, max: number | null): boolean {
    if (min === null && max === null) {
        return true;
    }

    const low = min ?? 1;
    const high = max ?? Number.MAX_SAFE_INTEGER;
    const bands: Array<[number, number]> = [[2, 2], [3, 5], [6, Number.MAX_SAFE_INTEGER]];
    const band = wish === '2' ? 0 : wish === '3-5' ? 1 : 2;

    return bands.some(([bandLow, bandHigh], index) => low <= bandHigh && high >= bandLow && Math.abs(index - band) <= 1);
}
