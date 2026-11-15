import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { AdventureCandidate, type AdventureCandidateInput } from './adventure-candidate';
import { MatchFinder } from './match-finder';
import { WizardAnswers, type WizardAnswerInput } from './wizard-answers';

interface GoldenCase {
    name: string;
    answers: WizardAnswerInput;
    titles: string[];
    reasons: string[][];
    fallbackNotes: string[];
}

interface GoldenDocument {
    catalog: AdventureCandidateInput[];
    cases: GoldenCase[];
}

const golden = JSON.parse(readFileSync(new URL('./fixtures/match-finder.json', import.meta.url), 'utf8')) as GoldenDocument;
const catalog = golden.catalog.map((row) => AdventureCandidate.fromInput(row));
const finder = new MatchFinder();

describe('match finder golden files', () => {
    it.each(golden.cases)('$name keeps titles, reasons and relaxation notes', (entry) => {
        const result = finder.find(WizardAnswers.fromInput(entry.answers), catalog);

        expect(result.recommendations.map((recommendation) => recommendation.adventure.title)).toEqual(entry.titles);
        expect(result.recommendations.map((recommendation) => recommendation.reasons)).toEqual(entry.reasons);
        expect(result.fallbackNotes).toEqual(entry.fallbackNotes);
    });
});
