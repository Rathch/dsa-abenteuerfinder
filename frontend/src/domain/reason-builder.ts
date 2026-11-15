import { AdventureCandidate } from './adventure-candidate';
import {
    type DurationWish,
    type ExoticWish,
    ExoticWish as ExoticWishValue,
    durationFit,
    durationLabel,
    durationWishLabel,
    regionLabelFor,
    ruleFrom,
    ruleLabel,
} from './enums';
import { ScoreBreakdown } from './score';
import { WizardAnswers } from './wizard-answers';

const GENRE_LABELS: Record<string, string> = {
    investigation: 'Ermittlung',
    dungeon: 'Dungeon',
    intrigue: 'Intrige',
    horror: 'Horror',
    humor: 'Humor',
    exploration: 'Erkundung',
    social: 'Soziales Spiel',
    seafaring: 'Seefahrt',
};

export class ReasonBuilder {
    build(answers: WizardAnswers, candidate: AdventureCandidate, score: ScoreBreakdown): string[] {
        const reasons = [this.experienceReason(answers, candidate, score)];

        for (const reason of [
            this.regionReason(answers, candidate, score),
            this.genreReason(answers, candidate),
            this.timeframeReason(answers, candidate, score),
            this.rulesReason(answers, candidate),
            this.groupReason(answers, candidate),
            this.exoticReason(answers, candidate, score),
            this.gmReason(answers, candidate, score),
        ]) {
            if (reason !== null) {
                reasons.push(reason);
            }
        }

        return reasons;
    }

    genreLabel(slug: string): string {
        return GENRE_LABELS[slug] ?? (slug.charAt(0).toUpperCase() + slug.slice(1));
    }

    private experienceReason(answers: WizardAnswers, candidate: AdventureCandidate, score: ScoreBreakdown): string {
        const wanted = answers.experience();

        if (score.experienceDistance === 0) {
            return `Geeignet für ${experienceLabelOf(wanted)}.`;
        }

        return `Richtet sich an ${experienceLabelOf(candidate.experienceLevel)} und liegt damit nahe an deiner Wunschstufe (${experienceLabelOf(wanted)}).`;
    }

    private regionReason(answers: WizardAnswers, candidate: AdventureCandidate, score: ScoreBreakdown): string | null {
        const wantedRegion = answers.regionSlugs.length === 1 ? regionLabelFor(answers.regionSlugs[0]) : null;

        if (score.regionKind === 'place') {
            return `Spielt in ${candidate.primaryPlaceName ?? 'deinem Wunschort'} – genau der Ort, den du dir gewünscht hast.`;
        }

        if (score.regionKind === 'region') {
            return `Passt zur Region ${wantedRegion ?? 'deiner Wahl'}.`;
        }

        if (score.regionKind === 'traveling') {
            const target = wantedRegion !== null ? `die Region ${wantedRegion}` : 'deine Wunschregion';

            return `Reiseabenteuer, das sich flexibel in ${target} einbinden lässt.`;
        }

        if (score.regionKind === 'neighbor') {
            return `Spielt in ${regionLabelFor(score.matchedRegionSlug ?? '')}, direkt neben deiner Wunschregion ${wantedRegion ?? ''}.`;
        }

        return null;
    }

    private genreReason(answers: WizardAnswers, candidate: AdventureCandidate): string | null {
        const unique = [...new Set(answers.genres)];
        const common = unique.filter((genre) => candidate.genres.includes(genre));

        if (common.length === 0) {
            return null;
        }

        const labels = common.map((genre) => this.genreLabel(genre));
        const noun = labels.length === 1 ? 'das Genre ' : 'die Genres ';

        return `Deckt ${noun}${joinGerman(labels)} ab.`;
    }

    private timeframeReason(answers: WizardAnswers, candidate: AdventureCandidate, score: ScoreBreakdown): string | null {
        if (score.timeframe < 50 || !answers.restrictsDuration()) {
            return null;
        }

        const label = this.bestDurationLabel(answers, candidate);

        if (label === null) {
            return null;
        }

        return `Dauer passt zu ${label} (${durationLabel(candidate.durationCategory)}).`;
    }

    private bestDurationLabel(answers: WizardAnswers, candidate: AdventureCandidate): string | null {
        let best: DurationWish | null = null;
        let bestFit = -1;

        for (const wish of answers.durationWishes()) {
            const fit = durationFit(wish, candidate.durationCategory);

            if (fit > bestFit) {
                bestFit = fit;
                best = wish;
            }
        }

        return best === null ? null : durationWishLabel(best);
    }

    private rulesReason(answers: WizardAnswers, candidate: AdventureCandidate): string | null {
        if (answers.rules.length === 0) {
            return null;
        }

        const labels: string[] = [];

        for (const value of answers.rules) {
            const version = ruleFrom(value);

            if (version !== null) {
                labels.push(ruleLabel(version));
            }
        }

        if (labels.length === 0) {
            return null;
        }

        return `Verfügbar für ${joinGerman(labels)} (${ruleLabel(candidate.ruleVersion)}).`;
    }

    private groupReason(answers: WizardAnswers, candidate: AdventureCandidate): string | null {
        const wish = answers.groupSizeWish();

        if (wish === null || !groupExactly(wish, candidate.groupSizeMin, candidate.groupSizeMax)) {
            return null;
        }

        if (candidate.groupSizeMin === null && candidate.groupSizeMax === null) {
            return null;
        }

        return `Geeignet für ${groupLabel(wish)}.`;
    }

    private exoticReason(answers: WizardAnswers, candidate: AdventureCandidate, score: ScoreBreakdown): string | null {
        if (!answers.restrictsExotic()) {
            return null;
        }

        for (const wish of answers.exoticWishes()) {
            const reason = exoticReasonFor(wish, candidate, score);

            if (reason !== null) {
                return reason;
            }
        }

        return null;
    }

    private gmReason(answers: WizardAnswers, candidate: AdventureCandidate, score: ScoreBreakdown): string | null {
        if (score.gm < 80) {
            return null;
        }

        return `Komplexität „${candidate.complexity}“ passt zu deiner Spielleitungserfahrung (${experienceLabelOf(answers.gmLevel())}).`;
    }
}

function experienceLabelOf(level: import('./enums').ExperienceLevel): string {
    const labels: Record<string, string> = {
        einsteiger: 'Einsteiger',
        fortgeschritten: 'Fortgeschrittene',
        erfahren: 'Erfahrene',
        meister: 'Meister',
    };

    return labels[level];
}

function groupExactly(wish: import('./enums').GroupSizeWish, min: number | null, max: number | null): boolean {
    const low = min ?? 1;
    const high = max ?? Number.MAX_SAFE_INTEGER;
    const bands: Record<string, [number, number]> = {
        '2': [2, 2],
        '3-5': [3, 5],
        '6+': [6, Number.MAX_SAFE_INTEGER],
    };
    const [bandLow, bandHigh] = bands[wish];

    return low <= bandHigh && high >= bandLow;
}

function groupLabel(wish: import('./enums').GroupSizeWish): string {
    if (wish === '2') {
        return '2 Spielende';
    }

    if (wish === '3-5') {
        return '3 bis 5 Spielende';
    }

    return '6 oder mehr Spielende';
}

function exoticReasonFor(wish: ExoticWish, candidate: AdventureCandidate, score: ScoreBreakdown): string | null {
    if (wish === ExoticWishValue.No) {
        return candidate.exotic || candidate.isExtraAventurian() ? null : 'Bleibt bei klassisch aventurischen Schauplätzen und Kulturen.';
    }

    if (wish === ExoticWishValue.ExtraAventurian) {
        return score.exotic >= 60 ? 'Führt dich abseits des gewohnten Aventurien.' : null;
    }

    if (wish === ExoticWishValue.UnusualCultures) {
        return candidate.exotic ? 'Stellt ungewöhnliche Kulturen in den Mittelpunkt.' : null;
    }

    return null;
}

function joinGerman(items: string[]): string {
    if (items.length <= 1) {
        return items[0] ?? '';
    }

    const copy = [...items];
    const last = copy.pop() ?? '';

    return `${copy.join(', ')} und ${last}`;
}
