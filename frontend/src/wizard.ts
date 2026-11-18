import { AdventureCandidate, type AdventureCandidateInput } from './domain/adventure-candidate';
import {
    DURATION_WISHES,
    durationWishLabel,
    experienceLabel,
    groupSizeLabel,
    regionLabelFor,
    ruleLabel,
    type DurationWish,
    type ExperienceLevel,
    type GroupSizeWish,
    type RuleVersion,
} from './domain/enums';
import { MatchFinder } from './domain/match-finder';
import { WizardAnswers } from './domain/wizard-answers';
import { WizardFacetFilter } from './domain/wizard-facet-filter';

const STORAGE_KEY = 'adventure_wizard';

const STEPS = ['ruleset', 'experienceLevel', 'exotic', 'region', 'timeframe', 'genres', 'gmExperience', 'groupSize', 'review'] as const;

type Step = (typeof STEPS)[number];

type WizardState = {
    step: number;
    rules: string[];
    rulesAny: boolean;
    experienceLevel: string;
    exotics: string[];
    exoticAny: boolean;
    regionsAny: boolean;
    regionSlugs: string[];
    placeSlug: string | null;
    timeframes: string[];
    genres: string[];
    gmExperience: string;
    groupSize: string;
    showResults: boolean;
    error: string | null;
};

export interface PublicCatalog {
    adventures: AdventureCandidateInput[];
    places: Array<{ slug: string; name: string; regionSlug: string }>;
    genres: Array<{ slug: string; name: string }>;
    regions: Array<{ slug: string; name: string }>;
}

type Option = { value: string; label: string };

const facets = new WizardFacetFilter();

function emptyState(): WizardState {
    return {
        step: 0,
        rules: [],
        rulesAny: false,
        experienceLevel: '',
        exotics: [],
        exoticAny: false,
        regionsAny: false,
        regionSlugs: [],
        placeSlug: null,
        timeframes: [],
        genres: [],
        gmExperience: '',
        groupSize: '',
        showResults: false,
        error: null,
    };
}

export function mountWizard(root: HTMLElement, catalog: PublicCatalog): void {
    const candidates = catalog.adventures.map((row) => AdventureCandidate.fromInput(row));
    let state = readState();

    const render = (): void => {
        writeState(state);
        root.replaceChildren(state.showResults ? resultsView(candidates, state, restart) : formView(catalog, candidates, state, update));
        if (state.showResults) {
            document.getElementById('results-heading')?.focus();
        } else if (state.error) {
            document.getElementById('error-summary')?.focus();
        }
    };

    const update = (recipe: (current: WizardState) => void): void => {
        recipe(state);
        render();
    };

    const restart = (): void => {
        state = emptyState();
        render();
    };

    render();
}

function readState(): WizardState {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (raw === null) {
        return emptyState();
    }

    try {
        return { ...emptyState(), ...JSON.parse(raw) as Partial<WizardState>, error: null };
    } catch {
        return emptyState();
    }
}

function writeState(state: WizardState): void {
    if (isEmpty(state)) {
        localStorage.removeItem(STORAGE_KEY);

        return;
    }

    const { error: _error, ...stored } = state;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
}

function isEmpty(state: WizardState): boolean {
    return state.step === 0
        && state.rules.length === 0
        && !state.rulesAny
        && state.experienceLevel === ''
        && state.exotics.length === 0
        && !state.exoticAny
        && !state.regionsAny
        && state.regionSlugs.length === 0
        && state.placeSlug === null
        && state.timeframes.length === 0
        && state.genres.length === 0
        && state.gmExperience === ''
        && state.groupSize === ''
        && !state.showResults;
}

function answersFrom(state: WizardState): WizardAnswers {
    return WizardAnswers.fromInput({
        experienceLevel: state.experienceLevel,
        groupSize: state.groupSize === '' || state.groupSize === 'egal' ? null : state.groupSize,
        timeframes: state.timeframes,
        regionSlugs: state.regionsAny ? [] : state.regionSlugs,
        regionsAny: state.regionsAny,
        placeSlug: state.placeSlug,
        exotics: state.exotics,
        exoticAny: state.exoticAny,
        genres: state.genres,
        rules: state.rules,
        rulesAny: state.rulesAny,
        gmExperience: state.gmExperience,
    });
}

function formView(
    catalog: PublicCatalog,
    candidates: AdventureCandidate[],
    state: WizardState,
    update: (recipe: (current: WizardState) => void) => void,
): HTMLElement {
    const wrap = document.createElement('section');
    wrap.className = 'space-y-6';
    wrap.setAttribute('aria-labelledby', 'wizard-heading');
    wrap.append(heading('h2', 'wizard-heading', 'Deine Wünsche'));
    wrap.append(summary(catalog, state, update));
    if (state.error !== null) {
        wrap.append(errorSummary(state.error));
    }
    wrap.append(stepField(catalog, candidates, state, update));

    return wrap;
}

function summary(catalog: PublicCatalog, state: WizardState, update: (recipe: (current: WizardState) => void) => void): HTMLElement {
    const list = document.createElement('div');
    list.className = 'flex flex-wrap gap-2';
    const chips: Array<{ step: number; label: string }> = [];
    const step = state.step;

    if (step > 0 && (state.rulesAny || state.rules.length > 0)) {
        chips.push({
            step: 0,
            label: `Regeln: ${state.rulesAny ? 'Egal' : state.rules.map((value) => ruleLabel(value as RuleVersion)).join(', ')}`,
        });
    }
    if (step > 1 && state.experienceLevel !== '') {
        chips.push({ step: 1, label: `Helden: ${experienceLabel(state.experienceLevel as ExperienceLevel)}` });
    }
    if (step > 2 && (state.exoticAny || state.exotics.length > 0)) {
        chips.push({ step: 2, label: `Exotik: ${state.exoticAny ? 'Egal' : state.exotics.map((value) => exoticOptionLabel(value)).join(', ')}` });
    }
    if (step > 3 && (state.regionsAny || state.regionSlugs.length > 0)) {
        const place = state.placeSlug === null ? '' : catalog.places.find((item) => item.slug === state.placeSlug)?.name;
        const region = state.regionsAny ? 'Egal' : state.regionSlugs.map((slug) => regionLabelFor(slug)).join(', ');
        chips.push({ step: 3, label: `Region: ${place ? `${region} · ${place}` : region}` });
    }
    if (step > 4 && state.timeframes.length > 0) {
        chips.push({ step: 4, label: `Zeit: ${state.timeframes.map((value) => timeframeLabel(value)).join(', ')}` });
    }
    if (step > 5) {
        const names = state.genres.map((slug) => catalog.genres.find((genre) => genre.slug === slug)?.name ?? slug);
        chips.push({ step: 5, label: `Genres: ${names.length === 0 ? 'Egal' : names.join(', ')}` });
    }
    if (step > 6 && state.gmExperience !== '') {
        chips.push({ step: 6, label: `Spielleitung: ${experienceLabel(state.gmExperience as ExperienceLevel)}` });
    }

    for (const chip of chips) {
        if (chip.label === '') {
            continue;
        }
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'pill';
        button.textContent = chip.label;
        button.addEventListener('click', () => update((current) => {
            current.step = chip.step;
            current.showResults = false;
            current.error = null;
        }));
        list.append(button);
    }

    return list;
}

function stepField(
    catalog: PublicCatalog,
    candidates: AdventureCandidate[],
    state: WizardState,
    update: (recipe: (current: WizardState) => void) => void,
): HTMLElement {
    const step = STEPS[state.step] ?? 'review';
    const answers = answersFrom(state);

    if (step === 'ruleset') {
        return checks('Nach welchem Regelwerk wollt ihr spielen?', 'ruleset', ruleOptions(facets.rulesWishes(answers, candidates)), state.rulesAny ? ['any'] : state.rules, (values) => {
            update((current) => {
                current.rulesAny = values.includes('any');
                current.rules = values.filter((value) => value !== 'any');
            });
        }, () => advance(update));
    }

    if (step === 'experienceLevel') {
        return radios('Wie erfahren sind die Heldinnen und Helden eurer Gruppe?', 'experienceLevel', facets.experienceLevels(answers, candidates).map((value) => ({
            value,
            label: experienceLabel(value as ExperienceLevel),
        })), state.experienceLevel, (value) => update((current) => {
            current.experienceLevel = value;
        }), () => advance(update));
    }

    if (step === 'exotic') {
        return checks('Wie exotisch darf das Abenteuer sein?', 'exotic', facets.exoticWishes(answers, candidates).map((value) => ({
            value,
            label: exoticOptionLabel(value),
        })), state.exoticAny ? ['any'] : state.exotics, (values) => update((current) => {
            current.exoticAny = values.includes('any');
            current.exotics = values.filter((value) => value !== 'any');
        }), () => advance(update));
    }

    if (step === 'region') {
        return regionStep(catalog, candidates, state, answers, update);
    }

    if (step === 'timeframe') {
        return checks('Wie viel Zeit wollt ihr investieren?', 'timeframe', facets.timeframes(answers, candidates).map((value) => ({
            value,
            label: timeframeLabel(value),
        })), state.timeframes, (values) => update((current) => {
            current.timeframes = values;
        }), () => advance(update));
    }

    if (step === 'genres') {
        const slugs = facets.genreSlugs(answers, candidates, catalog.genres.map((genre) => genre.slug));
        const options = slugs.map((slug) => ({
            value: slug,
            label: catalog.genres.find((genre) => genre.slug === slug)?.name ?? slug,
        }));

        return checks('Welche Genres sprechen euch an?', 'genres', options, state.genres, (values) => update((current) => {
            current.genres = values;
        }), () => advance(update));
    }

    if (step === 'gmExperience') {
        return radios('Wie erfahren ist die Spielleitung?', 'gmExperience', facets.gmExperienceLevels(answers, candidates).map((value) => ({
            value,
            label: experienceLabel(value as ExperienceLevel),
        })), state.gmExperience, (value) => update((current) => {
            current.gmExperience = value;
        }), () => advance(update));
    }

    if (step === 'groupSize') {
        return radios('Wie viele Spielende seid ihr (ohne Spielleitung)?', 'groupSize', facets.groupSizes(answers, candidates).map((value) => ({
            value,
            label: value === 'egal' ? 'Egal' : groupSizeLabel(value as GroupSizeWish),
        })), state.groupSize, (value) => update((current) => {
            current.groupSize = value;
        }), () => advance(update));
    }

    const review = document.createElement('div');
    review.className = 'space-y-4';
    review.append(heading('h3', 'review-heading', 'Passt das so?'));
    const submit = document.createElement('button');
    submit.type = 'button';
    submit.className = 'btn btn-primary';
    submit.textContent = 'Empfehlungen anzeigen';
    submit.addEventListener('click', () => update((current) => {
        current.showResults = true;
        current.error = null;
    }));
    review.append(submit);

    return review;
}

function regionStep(
    catalog: PublicCatalog,
    candidates: AdventureCandidate[],
    state: WizardState,
    answers: WizardAnswers,
    update: (recipe: (current: WizardState) => void) => void,
): HTMLElement {
    const options: Option[] = [{ value: 'egal', label: 'Egal' }];
    for (const slug of facets.regionSlugs(answers, candidates)) {
        options.push({ value: slug, label: regionLabelFor(slug) });
    }

    const field = document.createElement('fieldset');
    field.className = 'min-w-0 space-y-4';
    const legend = document.createElement('legend');
    legend.id = 'region-legend';
    legend.className = 'ink-rule mb-1 font-serif text-xl font-bold';
    legend.textContent = 'In welcher Region soll das Abenteuer spielen?';
    field.append(legend);

    const grid = document.createElement('div');
    grid.className = 'grid gap-3 sm:grid-cols-2';
    options.forEach((option, index) => {
        const selected = option.value === 'egal' ? state.regionsAny : state.regionSlugs.includes(option.value);
        grid.append(choice('region', 'checkbox', `regionSlug-${index}`, option.label, selected, () => update((current) => {
            current.error = null;
            if (option.value === 'egal') {
                current.regionsAny = !current.regionsAny;
                if (current.regionsAny) {
                    current.regionSlugs = [];
                    current.placeSlug = null;
                }

                return;
            }
            current.regionsAny = false;
            current.regionSlugs = current.regionSlugs.includes(option.value)
                ? current.regionSlugs.filter((slug) => slug !== option.value)
                : [...current.regionSlugs, option.value];
            if (current.regionSlugs.length !== 1) {
                current.placeSlug = null;
            }
        })));
    });
    field.append(grid);

    if (state.regionSlugs.length === 1 && !state.regionsAny) {
        const region = state.regionSlugs[0];
        const places = catalog.places.filter((place) => place.regionSlug === region);
        const group = document.createElement('div');
        group.id = 'place-list';
        group.setAttribute('role', 'radiogroup');
        group.setAttribute('aria-labelledby', 'place-legend');
        const placeLegend = document.createElement('p');
        placeLegend.id = 'place-legend';
        placeLegend.className = 'ink-rule font-serif text-xl font-bold';
        placeLegend.append('Ort ', optionalMark());
        group.append(placeLegend);
        const placeGrid = document.createElement('div');
        placeGrid.className = 'grid gap-3 sm:grid-cols-2';
        placeGrid.append(choice('placeSlug', 'radio', 'place-none', 'Kein bestimmter Ort', state.placeSlug === null, () => update((current) => {
            current.placeSlug = null;
        })));
        for (const place of places) {
            placeGrid.append(choice('placeSlug', 'radio', `place-${place.slug}`, place.name, state.placeSlug === place.slug, () => update((current) => {
                current.placeSlug = place.slug;
            })));
        }
        group.append(placeGrid);
        field.append(group);
    }

    field.append(nextButton(() => {
        if (!state.regionsAny && state.regionSlugs.length === 0) {
            update((current) => {
                current.error = 'Bitte wähle eine Region oder „Egal“.';
            });

            return;
        }
        advance(update);
    }));

    return field;
}

function checks(legendText: string, idPrefix: string, options: Option[], selected: string[], onChange: (values: string[]) => void, onNext: () => void): HTMLFieldSetElement {
    const field = fieldset(legendText);
    const grid = document.createElement('div');
    grid.className = 'grid gap-3 sm:grid-cols-2';
    options.forEach((option, index) => {
        grid.append(choice(`${idPrefix}[]`, 'checkbox', `${idPrefix}-${index}`, option.label, selected.includes(option.value), () => {
            const next = selected.includes(option.value)
                ? selected.filter((value) => value !== option.value)
                : [...selected, option.value];
            onChange(option.value === 'any' ? (selected.includes('any') ? [] : ['any']) : next.filter((value) => value !== 'any'));
        }));
    });
    field.append(grid, nextButton(onNext));

    return field;
}

function radios(legendText: string, idPrefix: string, options: Option[], selected: string, onChange: (value: string) => void, onNext: () => void): HTMLFieldSetElement {
    const field = fieldset(legendText);
    const grid = document.createElement('div');
    grid.className = 'grid gap-3 sm:grid-cols-2';
    options.forEach((option, index) => {
        grid.append(choice(idPrefix, 'radio', `${idPrefix}-${index}`, option.label, selected === option.value, () => onChange(option.value)));
    });
    field.append(grid, nextButton(onNext));

    return field;
}

function fieldset(legendText: string): HTMLFieldSetElement {
    const field = document.createElement('fieldset');
    field.className = 'min-w-0 space-y-4';
    const legend = document.createElement('legend');
    legend.className = 'ink-rule mb-1 font-serif text-xl font-bold';
    legend.textContent = legendText;
    field.append(legend);

    return field;
}

function choice(name: string, type: 'checkbox' | 'radio', id: string, label: string, checked: boolean, onChange: () => void): HTMLLabelElement {
    const wrap = document.createElement('label');
    wrap.className = 'choice';
    const input = document.createElement('input');
    input.type = type;
    input.name = name;
    input.id = id;
    input.checked = checked;
    input.addEventListener('change', onChange);
    const text = document.createElement('span');
    const inner = document.createElement('span');
    inner.className = 'choice-label';
    inner.textContent = label;
    text.append(inner);
    wrap.append(input, text);

    return wrap;
}

function nextButton(onClick: () => void): HTMLButtonElement {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-primary';
    button.textContent = 'Weiter';
    button.addEventListener('click', onClick);

    return button;
}

function advance(update: (recipe: (current: WizardState) => void) => void): void {
    update((current) => {
        current.error = null;
        current.step = Math.min(current.step + 1, STEPS.length - 1);
    });
}

function errorSummary(message: string): HTMLElement {
    const box = document.createElement('div');
    box.id = 'error-summary';
    box.tabIndex = -1;
    box.setAttribute('role', 'alert');
    box.className = 'callout callout-error';
    const title = document.createElement('h3');
    title.id = 'error-summary-heading';
    title.className = 'text-lg';
    title.textContent = 'Bitte prüfe deine Angaben';
    const list = document.createElement('ul');
    const item = document.createElement('li');
    const link = document.createElement('a');
    link.href = '#region-legend';
    link.textContent = message;
    item.append(link);
    list.append(item);
    box.append(title, list);

    return box;
}

function resultsView(candidates: AdventureCandidate[], state: WizardState, restart: () => void): HTMLElement {
    const result = new MatchFinder().find(answersFrom(state), candidates);
    const section = document.createElement('section');
    section.className = 'space-y-6';
    section.setAttribute('aria-labelledby', 'results-heading');
    const title = heading('h2', 'results-heading', 'Deine Empfehlungen');
    title.tabIndex = -1;
    section.append(title);

    if (result.fallbackNotes.length > 0) {
        const notes = document.createElement('ul');
        for (const note of result.fallbackNotes) {
            const item = document.createElement('li');
            item.textContent = note;
            notes.append(item);
        }
        section.append(notes);
    }

    const list = document.createElement('ol');
    list.className = 'space-y-6';
    list.setAttribute('aria-label', 'Empfohlene Abenteuer');
    for (const match of result.recommendations) {
        list.append(card(match.adventure, match.reasons));
    }
    section.append(list);

    const again = document.createElement('button');
    again.type = 'button';
    again.className = 'btn btn-secondary';
    again.textContent = 'Neu starten';
    again.addEventListener('click', restart);
    section.append(again);

    return section;
}

function card(candidate: AdventureCandidate, reasons: string[]): HTMLLIElement {
    const item = document.createElement('li');
    const article = document.createElement('article');
    article.className = 'card recommendation-card';
    article.dataset.testid = 'recommendation';
    const header = document.createElement('header');
    header.className = 'recommendation-heading space-y-1';
    const title = document.createElement('h3');
    title.className = 'recommendation-title';
    title.textContent = candidate.title;
    const series = document.createElement('p');
    series.className = 'text-muted';
    series.textContent = `Reihe: ${candidate.seriesName}`;
    header.append(title, series);
    article.append(header);

    if (candidate.coverUrl) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'cover-preview';
        button.dataset.coverPreview = '';
        button.setAttribute('aria-expanded', 'false');
        button.setAttribute('aria-label', `Umschlag von ${candidate.title}`);
        const image = document.createElement('img');
        image.src = candidate.coverUrl;
        image.alt = '';
        image.width = 96;
        image.height = 128;
        image.className = 'cover-thumb';
        image.loading = 'lazy';
        button.append(image);
        article.append(button);
    } else {
        const image = document.createElement('img');
        image.src = new URL('../images/cover-fallback.svg', import.meta.url).href;
        image.alt = '';
        image.width = 96;
        image.height = 128;
        image.className = 'cover-thumb';
        article.append(image);
    }

    const why = document.createElement('div');
    why.className = 'recommendation-reasons';
    const whyTitle = document.createElement('h4');
    whyTitle.className = 'meta-heading';
    whyTitle.textContent = 'Warum passt es?';
    const reasonList = document.createElement('ul');
    reasonList.className = 'mt-2 list-disc space-y-1 pl-6';
    for (const reason of reasons) {
        const line = document.createElement('li');
        line.textContent = reason;
        reasonList.append(line);
    }
    why.append(whyTitle, reasonList);
    const links = document.createElement('ul');
    const linkItem = document.createElement('li');
    const wiki = document.createElement('a');
    wiki.href = candidate.wikiUrl;
    wiki.target = '_blank';
    wiki.rel = 'noopener noreferrer';
    wiki.className = 'btn btn-secondary btn-sm';
    wiki.append('Wiki Aventurica', srOnly(' (öffnet in neuem Tab)'));
    linkItem.append(wiki);
    links.append(linkItem);
    article.append(why, links);
    item.append(article);

    return item;
}

function heading(tag: 'h2' | 'h3', id: string, text: string): HTMLHeadingElement {
    const node = document.createElement(tag);
    node.id = id;
    node.textContent = text;

    return node;
}

function srOnly(text: string): HTMLSpanElement {
    const note = document.createElement('span');
    note.className = 'sr-only';
    note.textContent = text;

    return note;
}

function optionalMark(): HTMLSpanElement {
    const mark = document.createElement('span');
    mark.className = 'font-sans text-base font-normal text-muted';
    mark.textContent = '(optional)';

    return mark;
}

function ruleOptions(values: string[]): Option[] {
    return values.map((value) => ({
        value,
        label: value === 'any' ? 'Egal' : ruleLabel(value as RuleVersion),
    }));
}

function exoticOptionLabel(value: string): string {
    if (value === 'no') {
        return 'Nur Aventurien';
    }
    if (value === 'extra_aventurian') {
        return 'Andere Kontinente';
    }
    if (value === 'unusual_cultures') {
        return 'Ungewöhnliche Kulturen';
    }

    return 'Egal';
}

function timeframeLabel(value: string): string {
    if (value === 'evening_short') {
        return 'Ein kurzer Abend';
    }
    if (value === 'evening_long') {
        return 'Ein langer Abend';
    }
    if (value === 'multi') {
        return 'Mehrere Sitzungen';
    }
    if ((DURATION_WISHES as string[]).includes(value)) {
        const label = durationWishLabel(value as DurationWish);

        return label.charAt(0).toUpperCase() + label.slice(1);
    }

    return value;
}
