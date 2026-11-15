import { AdventureCandidate } from './adventure-candidate';
import {
    type Complexity,
    type DurationWish,
    type ExperienceLevel,
    type RegionMatchKind,
    LocationType,
    RegionMatchKind as RegionMatchKindValue,
    complexityIdealFor,
    complexityRank,
    durationFit,
    durationFromPageCount,
    exoticFit,
    experienceDistance,
    regionFrom,
    regionIsNeighborOf,
} from './enums';
import { WizardAnswers } from './wizard-answers';

export class ScoreBreakdown {
    constructor(
        readonly region: number,
        readonly regionKind: RegionMatchKind,
        readonly matchedRegionSlug: string | null,
        readonly timeframe: number,
        readonly genre: number,
        readonly exotic: number,
        readonly gm: number,
        readonly experience: number,
        readonly experienceDistance: number,
        readonly total: number,
    ) {}
}

export class ScoreCalculator {
    static readonly WEIGHT_REGION = 0.25;

    static readonly WEIGHT_TIMEFRAME = 0.2;

    static readonly WEIGHT_GENRE = 0.2;

    static readonly WEIGHT_EXOTIC = 0.1;

    static readonly WEIGHT_GM = 0.1;

    static readonly WEIGHT_EXPERIENCE = 0.15;

    static readonly REGION_PLACE = 100;

    static readonly REGION_REGION = 80;

    static readonly REGION_TRAVELING = 60;

    static readonly REGION_NEIGHBOR = 50;

    static readonly REGION_OTHER = 20;

    static readonly PAGE_PROXY_SHARE = 0.25;

    score(answers: WizardAnswers, candidate: AdventureCandidate): ScoreBreakdown {
        const [region, regionKind, matchedRegion] = this.regionScore(answers, candidate);
        const timeframe = this.bestTimeframeScore(answers, candidate);
        const genre = this.genreScore(answers.genres, candidate.genres);
        const exotic = this.bestExoticScore(answers, candidate);
        const gm = this.gmScore(answers.gmLevel(), candidate.complexity);
        const distance = experienceDistance(answers.experience(), candidate.experienceLevel);
        const experience = this.experienceScore(distance);
        const total = Math.round(
            region * ScoreCalculator.WEIGHT_REGION
            + timeframe * ScoreCalculator.WEIGHT_TIMEFRAME
            + genre * ScoreCalculator.WEIGHT_GENRE
            + exotic * ScoreCalculator.WEIGHT_EXOTIC
            + gm * ScoreCalculator.WEIGHT_GM
            + experience * ScoreCalculator.WEIGHT_EXPERIENCE,
        );

        return new ScoreBreakdown(
            region,
            regionKind,
            matchedRegion,
            timeframe,
            genre,
            exotic,
            gm,
            experience,
            distance,
            Math.max(0, Math.min(100, total)),
        );
    }

    regionScore(answers: WizardAnswers, candidate: AdventureCandidate): [number, RegionMatchKind, string | null] {
        if (!answers.hasRegionWish()) {
            return [100, RegionMatchKindValue.Any, null];
        }

        if (answers.placeSlug !== null && candidate.matchesPlaceSlug(answers.placeSlug)) {
            return [ScoreCalculator.REGION_PLACE, RegionMatchKindValue.Place, candidate.primaryRegionSlug];
        }

        const traveling = candidate.locationType === LocationType.Traveling;
        const wanted = answers.regionSlugs;

        if (wanted.length === 0) {
            return traveling
                ? [ScoreCalculator.REGION_TRAVELING, RegionMatchKindValue.Traveling, null]
                : [ScoreCalculator.REGION_OTHER, RegionMatchKindValue.None, null];
        }

        const regions = candidate.allRegionSlugs();
        const exact = wanted.filter((slug) => regions.includes(slug));

        if (exact.length > 0) {
            return [ScoreCalculator.REGION_REGION, RegionMatchKindValue.Region, exact[0]];
        }

        if (traveling) {
            return [ScoreCalculator.REGION_TRAVELING, RegionMatchKindValue.Traveling, null];
        }

        for (const wantedSlug of wanted) {
            const wantedKey = regionFrom(wantedSlug);

            if (wantedKey === null) {
                continue;
            }

            for (const slug of regions) {
                const key = regionFrom(slug);

                if (key !== null && regionIsNeighborOf(wantedKey, key)) {
                    return [ScoreCalculator.REGION_NEIGHBOR, RegionMatchKindValue.Neighbor, slug];
                }
            }
        }

        return [ScoreCalculator.REGION_OTHER, RegionMatchKindValue.None, null];
    }

    bestTimeframeScore(answers: WizardAnswers, candidate: AdventureCandidate): number {
        const wishes = answers.durationWishes();

        if (wishes.length === 0) {
            return 100;
        }

        return wishes.reduce((best, wish) => Math.max(best, this.timeframeScore(wish, candidate)), 0);
    }

    bestExoticScore(answers: WizardAnswers, candidate: AdventureCandidate): number {
        if (!answers.restrictsExotic()) {
            return 100;
        }

        return answers.exoticWishes().reduce(
            (best, wish) => Math.max(best, exoticFit(wish, candidate.exotic, candidate.isExtraAventurian())),
            0,
        );
    }

    timeframeScore(wish: DurationWish, candidate: AdventureCandidate): number {
        const base = durationFit(wish, candidate.durationCategory);

        if (candidate.pageCount === null) {
            return base;
        }

        const proxy = durationFit(wish, durationFromPageCount(candidate.pageCount));

        return Math.round(base * (1 - ScoreCalculator.PAGE_PROXY_SHARE) + proxy * ScoreCalculator.PAGE_PROXY_SHARE);
    }

    genreScore(wanted: string[], offered: string[]): number {
        const unique = [...new Set(wanted)];

        if (unique.length === 0) {
            return 100;
        }

        const overlap = unique.filter((genre) => offered.includes(genre)).length;

        return Math.round((100 * overlap) / unique.length);
    }

    gmScore(gm: ExperienceLevel, complexity: Complexity): number {
        const diff = complexityRank(complexity) - complexityRank(complexityIdealFor(gm));

        if (diff === 0) {
            return 100;
        }

        if (diff === 1) {
            return 50;
        }

        if (diff >= 2) {
            return 10;
        }

        if (diff === -1) {
            return 80;
        }

        return 60;
    }

    experienceScore(distance: number): number {
        if (distance <= 0) {
            return 100;
        }

        if (distance === 1) {
            return 70;
        }

        if (distance === 2) {
            return 40;
        }

        return 10;
    }
}
