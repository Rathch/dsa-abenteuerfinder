import {
    type DurationWish,
    type ExperienceLevel,
    type ExoticWish,
    type GroupSizeWish,
    type RuleVersion,
    durationWishFrom,
    exoticFrom,
    experienceFrom,
    groupSizeFrom,
    ExoticWish as ExoticWishValue,
    RuleVersion as RuleVersionValue,
} from './enums';

export interface WizardAnswerInput {
    experienceLevel: string;
    groupSize: string | null;
    timeframes: string[];
    regionSlugs: string[];
    regionsAny: boolean;
    placeSlug: string | null;
    exotics: string[];
    exoticAny: boolean;
    genres: string[];
    rules: string[];
    rulesAny: boolean;
    gmExperience: string;
}

export class WizardAnswers {
    readonly timeframes: string[];

    readonly regionSlugs: string[];

    readonly regionsAny: boolean;

    readonly placeSlug: string | null;

    readonly exotics: string[];

    readonly exoticAny: boolean;

    readonly rules: string[];

    readonly rulesAny: boolean;

    constructor(
        readonly experienceLevel: string,
        readonly groupSize: string | null,
        timeframes: string[],
        regionSlugs: string[],
        regionsAny: boolean,
        placeSlug: string | null,
        exotics: string[],
        exoticAny: boolean,
        readonly genres: string[],
        rules: string[],
        rulesAny: boolean,
        readonly gmExperience: string,
    ) {
        const uniqueRules = uniqueStrings(rules);
        const uniqueRegions = uniqueStrings(regionSlugs);
        const uniqueExotics = uniqueStrings(exotics);
        const uniqueTimeframes = uniqueStrings(timeframes);
        let nextRulesAny = uniqueRules.length > 0 ? false : rulesAny;
        let nextRegionsAny = uniqueRegions.length > 0 ? false : regionsAny;
        let nextExoticAny = uniqueExotics.length > 0 ? false : exoticAny;
        let nextPlace = placeSlug;

        if (nextRegionsAny || uniqueRegions.length !== 1) {
            nextPlace = null;
        }

        this.timeframes = uniqueTimeframes;
        this.regionSlugs = uniqueRegions;
        this.regionsAny = nextRegionsAny;
        this.placeSlug = nextPlace;
        this.exotics = uniqueExotics;
        this.exoticAny = nextExoticAny;
        this.rules = uniqueRules;
        this.rulesAny = nextRulesAny;
    }

    static fromInput(input: WizardAnswerInput): WizardAnswers {
        return new WizardAnswers(
            input.experienceLevel,
            input.groupSize,
            input.timeframes,
            input.regionSlugs,
            input.regionsAny,
            input.placeSlug,
            input.exotics,
            input.exoticAny,
            input.genres,
            input.rules,
            input.rulesAny,
            input.gmExperience,
        );
    }

    static fromSession(stored: Record<string, unknown>): WizardAnswers {
        const rules = storedList(stored, 'rules');
        const regions = storedList(stored, 'regionSlugs');
        const timeframes = storedList(stored, 'timeframes');
        const exotics = storedList(stored, 'exotics');
        const genres = storedList(stored, 'genres') ?? [];
        const groupSize = stored.groupSize;
        const place = stored.placeSlug;

        return new WizardAnswers(
            typeof stored.experienceLevel === 'string' ? stored.experienceLevel : '',
            typeof groupSize === 'string' && groupSize !== '' ? groupSize : null,
            timeframes ?? [],
            regions ?? [],
            regions !== null && stored.regionsAny === true,
            typeof place === 'string' && place !== '' ? place : null,
            exotics ?? [],
            exotics !== null && stored.exoticAny === true,
            genres,
            rules ?? [],
            rules !== null && stored.rulesAny === true,
            typeof stored.gmExperience === 'string' ? stored.gmExperience : '',
        );
    }

    experience(): ExperienceLevel {
        return experienceFrom(this.experienceLevel) ?? 'einsteiger';
    }

    gmLevel(): ExperienceLevel {
        return experienceFrom(this.gmExperience) ?? 'einsteiger';
    }

    groupSizeWish(): GroupSizeWish | null {
        return this.groupSize === null ? null : groupSizeFrom(this.groupSize);
    }

    durationWishes(): DurationWish[] {
        const wishes: DurationWish[] = [];

        for (const value of this.timeframes) {
            const wish = durationWishFrom(value);

            if (wish !== null) {
                wishes.push(wish);
            }
        }

        return wishes;
    }

    restrictsDuration(): boolean {
        return this.durationWishes().length > 0;
    }

    exoticWishes(): ExoticWish[] {
        const wishes: ExoticWish[] = [];

        for (const value of this.exotics) {
            const wish = exoticFrom(value);

            if (wish !== null && wish !== ExoticWishValue.Any) {
                wishes.push(wish);
            }
        }

        return wishes;
    }

    restrictsExotic(): boolean {
        return !this.exoticAny && this.exoticWishes().length > 0;
    }

    acceptsRule(version: RuleVersion): boolean {
        if (this.rules.length === 0) {
            return true;
        }

        if (version === RuleVersionValue.EditionUnabhaengig) {
            return true;
        }

        return this.rules.includes(version);
    }

    acceptsRegion(candidateRegions: string[]): boolean {
        if (this.regionSlugs.length === 0) {
            return true;
        }

        return this.regionSlugs.some((slug) => candidateRegions.includes(slug));
    }

    hasRegionWish(): boolean {
        return this.regionSlugs.length > 0 || this.placeSlug !== null;
    }
}

function uniqueStrings(values: string[]): string[] {
    const unique: string[] = [];

    for (const value of values) {
        if (value !== '' && !unique.includes(value)) {
            unique.push(value);
        }
    }

    return unique;
}

function storedList(stored: Record<string, unknown>, key: string): string[] | null {
    if (!Object.prototype.hasOwnProperty.call(stored, key) || !Array.isArray(stored[key])) {
        return null;
    }

    return uniqueStrings((stored[key] as unknown[]).filter((value): value is string => typeof value === 'string'));
}
