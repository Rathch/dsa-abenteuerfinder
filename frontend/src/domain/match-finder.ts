import { AdventureCandidate } from './adventure-candidate';
import { ReasonBuilder } from './reason-builder';
import { ScoreBreakdown, ScoreCalculator } from './score';
import { RegionMatchKind, groupSizeCompatible } from './enums';
import { WizardAnswers } from './wizard-answers';

export interface MatchRecommendation {
    adventure: AdventureCandidate;
    score: number;
    reasons: string[];
}

export interface MatchResult {
    recommendations: MatchRecommendation[];
    fallbackNotes: string[];
}

const MIN_RECOMMENDATIONS = 3;
const MAX_RECOMMENDATIONS = 6;
const MAX_PER_SERIES = 2;
const ACCEPTABLE_FIT = 50;
const STRICT_EXPERIENCE_DISTANCE = 1;
const RELAXED_EXPERIENCE_DISTANCE = 2;
const STAGE_STRICT = 0;
const STAGE_REGION = 1;
const STAGE_TIMEFRAME = 2;
const STAGE_EXPERIENCE = 3;

type Scored = [AdventureCandidate, ScoreBreakdown];

export class MatchFinder {
    constructor(
        private readonly calculator = new ScoreCalculator(),
        private readonly reasons = new ReasonBuilder(),
    ) {}

    find(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): MatchResult {
        const scored = this.scoreEligible(answers, candidates);
        const stages = this.stages(answers);
        const notes: string[] = [];
        let pool: Scored[] = [];

        for (let index = 0; index < stages.length; index += 1) {
            pool = this.applyStage(scored, answers, stages[index]);

            if (pool.length >= MIN_RECOMMENDATIONS || index + 1 >= stages.length) {
                break;
            }

            notes.push(relaxationNote(stages[index + 1], pool.length));
        }

        return {
            recommendations: this.select(answers, pool),
            fallbackNotes: notes,
        };
    }

    private stages(answers: WizardAnswers): number[] {
        const stages = [STAGE_STRICT];

        if (answers.hasRegionWish()) {
            stages.push(STAGE_REGION);
        }

        stages.push(STAGE_TIMEFRAME, STAGE_EXPERIENCE);

        return stages;
    }

    private scoreEligible(answers: WizardAnswers, candidates: Iterable<AdventureCandidate>): Scored[] {
        const group = answers.groupSizeWish();
        const eligible: Scored[] = [];

        for (const candidate of candidates) {
            if (candidate.missingFromWiki) {
                continue;
            }

            if (!answers.acceptsRule(candidate.ruleVersion)) {
                continue;
            }

            if (group !== null && !groupSizeCompatible(group, candidate.groupSizeMin, candidate.groupSizeMax)) {
                continue;
            }

            eligible.push([candidate, this.calculator.score(answers, candidate)]);
        }

        return eligible;
    }

    private applyStage(scored: Scored[], answers: WizardAnswers, stage: number): Scored[] {
        const maxDistance = stage >= STAGE_EXPERIENCE ? RELAXED_EXPERIENCE_DISTANCE : STRICT_EXPERIENCE_DISTANCE;
        const filterRegion = stage < STAGE_REGION && answers.hasRegionWish();
        const filterTimeframe = stage < STAGE_TIMEFRAME;
        const pool: Scored[] = [];

        for (const entry of scored) {
            const score = entry[1];

            if (score.experienceDistance > maxDistance) {
                continue;
            }

            if (filterRegion && (score.regionKind === RegionMatchKind.Neighbor || score.region < ACCEPTABLE_FIT)) {
                continue;
            }

            if (filterTimeframe && score.timeframe < ACCEPTABLE_FIT) {
                continue;
            }

            pool.push(entry);
        }

        return pool;
    }

    private select(answers: WizardAnswers, pool: Scored[]): MatchRecommendation[] {
        const ordered = [...pool].sort(compareScored);
        const selected: Scored[] = [];
        const overflow: Scored[] = [];
        const perSeries = new Map<string, number>();

        for (const entry of ordered) {
            const series = entry[0].seriesSlug;
            const count = perSeries.get(series) ?? 0;

            if (selected.length < MAX_RECOMMENDATIONS && count < MAX_PER_SERIES) {
                selected.push(entry);
                perSeries.set(series, count + 1);
            } else {
                overflow.push(entry);
            }
        }

        while (selected.length < MIN_RECOMMENDATIONS && overflow.length > 0) {
            const next = overflow.shift();

            if (next !== undefined) {
                selected.push(next);
            }
        }

        selected.sort((left, right) => right[1].total - left[1].total);

        return selected.map(([candidate, score]) => ({
            adventure: candidate,
            score: score.total,
            reasons: this.reasons.build(answers, candidate, score),
        }));
    }
}

function relaxationNote(stage: number, hits: number): string {
    const reason = hits === 0
        ? 'es keine Treffer gab'
        : hits === 1
            ? 'nur 1 Treffer gefunden wurde'
            : `nur ${hits} Treffer gefunden wurden`;

    if (stage === STAGE_REGION) {
        return `Region wurde ignoriert, weil ${reason}.`;
    }

    if (stage === STAGE_TIMEFRAME) {
        return `Zeitrahmen wurde ignoriert, weil ${reason}.`;
    }

    return `Erfahrungsstufe wurde auf ±${RELAXED_EXPERIENCE_DISTANCE} Stufen erweitert, weil ${reason}.`;
}

function compareScored(left: Scored, right: Scored): number {
    const total = right[1].total - left[1].total;

    if (total !== 0) {
        return total;
    }

    const distance = left[1].experienceDistance - right[1].experienceDistance;

    if (distance !== 0) {
        return distance;
    }

    const title = strnatcasecmp(left[0].title, right[0].title);

    if (title !== 0) {
        return title;
    }

    return left[0].id - right[0].id;
}

function strnatcasecmp(left: string, right: string): number {
    const split = (value: string): string[] => value.toLocaleLowerCase('en').match(/\d+|\D+/gu) ?? [];
    const leftParts = split(left);
    const rightParts = split(right);
    const length = Math.max(leftParts.length, rightParts.length);

    for (let index = 0; index < length; index += 1) {
        const a = leftParts[index] ?? '';
        const b = rightParts[index] ?? '';
        const aNumber = /^\d+$/u.test(a);
        const bNumber = /^\d+$/u.test(b);

        if (aNumber && bNumber) {
            const diff = Number(a) - Number(b);

            if (diff !== 0) {
                return diff < 0 ? -1 : 1;
            }

            continue;
        }

        if (a < b) {
            return -1;
        }

        if (a > b) {
            return 1;
        }
    }

    return 0;
}
