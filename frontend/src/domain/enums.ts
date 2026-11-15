export const ExperienceLevel = {
    Einsteiger: 'einsteiger',
    Fortgeschritten: 'fortgeschritten',
    Erfahren: 'erfahren',
    Meister: 'meister',
} as const;

export type ExperienceLevel = (typeof ExperienceLevel)[keyof typeof ExperienceLevel];

const EXPERIENCE_RANK: Record<ExperienceLevel, number> = {
    einsteiger: 0,
    fortgeschritten: 1,
    erfahren: 2,
    meister: 3,
};

const EXPERIENCE_LABEL: Record<ExperienceLevel, string> = {
    einsteiger: 'Einsteiger',
    fortgeschritten: 'Fortgeschrittene',
    erfahren: 'Erfahrene',
    meister: 'Meister',
};

export const EXPERIENCE_LEVELS: ExperienceLevel[] = [
    ExperienceLevel.Einsteiger,
    ExperienceLevel.Fortgeschritten,
    ExperienceLevel.Erfahren,
    ExperienceLevel.Meister,
];

export function experienceRank(level: ExperienceLevel): number {
    return EXPERIENCE_RANK[level];
}

export function experienceDistance(left: ExperienceLevel, right: ExperienceLevel): number {
    return Math.abs(experienceRank(left) - experienceRank(right));
}

export function experienceLabel(level: ExperienceLevel): string {
    return EXPERIENCE_LABEL[level];
}

export function experienceFrom(value: string): ExperienceLevel | null {
    return (EXPERIENCE_LEVELS as string[]).includes(value) ? (value as ExperienceLevel) : null;
}

export const RuleVersion = {
    Dsa1: 'dsa1',
    Dsa2: 'dsa2',
    Dsa3: 'dsa3',
    Dsa4: 'dsa4',
    Dsa5: 'dsa5',
    EditionUnabhaengig: 'editionunabhaengig',
} as const;

export type RuleVersion = (typeof RuleVersion)[keyof typeof RuleVersion];

export const RULE_VERSIONS: RuleVersion[] = [
    RuleVersion.Dsa1,
    RuleVersion.Dsa2,
    RuleVersion.Dsa3,
    RuleVersion.Dsa4,
    RuleVersion.Dsa5,
    RuleVersion.EditionUnabhaengig,
];

const RULE_LABEL: Record<RuleVersion, string> = {
    dsa1: 'DSA 1',
    dsa2: 'DSA 2',
    dsa3: 'DSA 3',
    dsa4: 'DSA 4',
    dsa5: 'DSA 5',
    editionunabhaengig: 'editionsunabhängig',
};

export function ruleLabel(version: RuleVersion): string {
    return RULE_LABEL[version];
}

export function ruleFrom(value: string): RuleVersion | null {
    return (RULE_VERSIONS as string[]).includes(value) ? (value as RuleVersion) : null;
}

export const RulesWish = {
    Dsa5: 'dsa5',
    Dsa4: 'dsa4',
    Any: 'any',
} as const;

export type RulesWish = (typeof RulesWish)[keyof typeof RulesWish];

export function rulesWishAccepts(wish: RulesWish, version: RuleVersion): boolean {
    if (wish === RulesWish.Any) {
        return true;
    }

    if (wish === RulesWish.Dsa5) {
        return version === RuleVersion.Dsa5 || version === RuleVersion.EditionUnabhaengig;
    }

    return version === RuleVersion.Dsa4 || version === RuleVersion.EditionUnabhaengig;
}

export function rulesWishLabel(wish: RulesWish): string {
    if (wish === RulesWish.Dsa5) {
        return 'DSA 5';
    }

    if (wish === RulesWish.Dsa4) {
        return 'DSA 4';
    }

    return 'beliebigen Regeln';
}

export const DurationCategory = {
    Kurz: 'kurz',
    Mittel: 'mittel',
    Lang: 'lang',
    Kampagne: 'kampagne',
} as const;

export type DurationCategory = (typeof DurationCategory)[keyof typeof DurationCategory];

const DURATION_LABEL: Record<DurationCategory, string> = {
    kurz: 'Kurzabenteuer für einen Abend',
    mittel: 'Abenteuer für einen langen Abend bis zwei Sitzungen',
    lang: 'Abenteuer über mehrere Sitzungen',
    kampagne: 'Kampagne',
};

export function durationFromPageCount(pages: number): DurationCategory {
    if (pages <= 36) {
        return DurationCategory.Kurz;
    }

    if (pages <= 72) {
        return DurationCategory.Mittel;
    }

    if (pages <= 160) {
        return DurationCategory.Lang;
    }

    return DurationCategory.Kampagne;
}

export function durationLabel(category: DurationCategory): string {
    return DURATION_LABEL[category];
}

export const DurationWish = {
    EveningShort: 'evening_short',
    EveningLong: 'evening_long',
    Multi: 'multi',
    Campaign: 'campaign',
} as const;

export type DurationWish = (typeof DurationWish)[keyof typeof DurationWish];

export const DURATION_WISHES: DurationWish[] = [
    DurationWish.EveningShort,
    DurationWish.EveningLong,
    DurationWish.Multi,
    DurationWish.Campaign,
];

const DURATION_FIT: Record<DurationWish, [number, number, number, number]> = {
    evening_short: [100, 55, 10, 0],
    evening_long: [70, 100, 50, 10],
    multi: [30, 70, 100, 60],
    campaign: [0, 30, 70, 100],
};

function durationIndex(category: DurationCategory): number {
    if (category === DurationCategory.Kurz) {
        return 0;
    }

    if (category === DurationCategory.Mittel) {
        return 1;
    }

    if (category === DurationCategory.Lang) {
        return 2;
    }

    return 3;
}

export function durationFit(wish: DurationWish, category: DurationCategory): number {
    return DURATION_FIT[wish][durationIndex(category)];
}

export function durationWishLabel(wish: DurationWish): string {
    if (wish === DurationWish.EveningShort) {
        return 'einen kurzen Abend';
    }

    if (wish === DurationWish.EveningLong) {
        return 'einen langen Abend';
    }

    if (wish === DurationWish.Multi) {
        return 'mehrere Sitzungen';
    }

    return 'eine Kampagne';
}

export function durationWishFrom(value: string): DurationWish | null {
    return (DURATION_WISHES as string[]).includes(value) ? (value as DurationWish) : null;
}

export const LocationType = {
    Point: 'point',
    Corridor: 'corridor',
    Traveling: 'traveling',
} as const;

export type LocationType = (typeof LocationType)[keyof typeof LocationType];

export const LocationKind = {
    Continent: 'continent',
    Region: 'region',
    Place: 'place',
} as const;

export type LocationKind = (typeof LocationKind)[keyof typeof LocationKind];

export const Complexity = {
    Niedrig: 'niedrig',
    Mittel: 'mittel',
    Hoch: 'hoch',
} as const;

export type Complexity = (typeof Complexity)[keyof typeof Complexity];

export function complexityRank(complexity: Complexity): number {
    if (complexity === Complexity.Niedrig) {
        return 0;
    }

    if (complexity === Complexity.Mittel) {
        return 1;
    }

    return 2;
}

export function complexityIdealFor(level: ExperienceLevel): Complexity {
    if (level === ExperienceLevel.Einsteiger) {
        return Complexity.Niedrig;
    }

    if (level === ExperienceLevel.Fortgeschritten) {
        return Complexity.Mittel;
    }

    return Complexity.Hoch;
}

export const GroupSizeWish = {
    Two: '2',
    ThreeToFive: '3-5',
    SixPlus: '6+',
} as const;

export type GroupSizeWish = (typeof GroupSizeWish)[keyof typeof GroupSizeWish];

export const GROUP_SIZE_WISHES: GroupSizeWish[] = [
    GroupSizeWish.Two,
    GroupSizeWish.ThreeToFive,
    GroupSizeWish.SixPlus,
];

const GROUP_BANDS: Array<[number, number]> = [
    [2, 2],
    [3, 5],
    [6, Number.MAX_SAFE_INTEGER],
];

function groupBand(wish: GroupSizeWish): number {
    if (wish === GroupSizeWish.Two) {
        return 0;
    }

    if (wish === GroupSizeWish.ThreeToFive) {
        return 1;
    }

    return 2;
}

export function groupSizeFrom(value: string): GroupSizeWish | null {
    return (GROUP_SIZE_WISHES as string[]).includes(value) ? (value as GroupSizeWish) : null;
}

export function groupSizeCompatible(wish: GroupSizeWish, min: number | null, max: number | null): boolean {
    if (min === null && max === null) {
        return true;
    }

    const low = min ?? 1;
    const high = max ?? Number.MAX_SAFE_INTEGER;
    const band = groupBand(wish);

    return GROUP_BANDS.some(([bandLow, bandHigh], index) => low <= bandHigh && high >= bandLow && Math.abs(index - band) <= 1);
}

export function groupSizeExactlyCovered(wish: GroupSizeWish, min: number | null, max: number | null): boolean {
    const low = min ?? 1;
    const high = max ?? Number.MAX_SAFE_INTEGER;
    const [bandLow, bandHigh] = GROUP_BANDS[groupBand(wish)];

    return low <= bandHigh && high >= bandLow;
}

export function groupSizeLabel(wish: GroupSizeWish): string {
    if (wish === GroupSizeWish.Two) {
        return '2 Spielende';
    }

    if (wish === GroupSizeWish.ThreeToFive) {
        return '3 bis 5 Spielende';
    }

    return '6 oder mehr Spielende';
}

export const ExoticWish = {
    No: 'no',
    ExtraAventurian: 'extra_aventurian',
    UnusualCultures: 'unusual_cultures',
    Any: 'any',
} as const;

export type ExoticWish = (typeof ExoticWish)[keyof typeof ExoticWish];

export const EXOTIC_WISHES: ExoticWish[] = [
    ExoticWish.No,
    ExoticWish.ExtraAventurian,
    ExoticWish.UnusualCultures,
    ExoticWish.Any,
];

export function exoticFrom(value: string): ExoticWish | null {
    return (EXOTIC_WISHES as string[]).includes(value) ? (value as ExoticWish) : null;
}

export function exoticFit(wish: ExoticWish, exotic: boolean, extraAventurian: boolean): number {
    if (wish === ExoticWish.Any) {
        return 100;
    }

    if (wish === ExoticWish.No) {
        return exotic || extraAventurian ? 0 : 100;
    }

    if (wish === ExoticWish.ExtraAventurian) {
        if (extraAventurian) {
            return 100;
        }

        return exotic ? 60 : 20;
    }

    return exotic ? 100 : 30;
}

export const RegionMatchKind = {
    Any: 'any',
    Place: 'place',
    Region: 'region',
    Traveling: 'traveling',
    Neighbor: 'neighbor',
    None: 'none',
} as const;

export type RegionMatchKind = (typeof RegionMatchKind)[keyof typeof RegionMatchKind];

export const RegionKey = {
    Nordmarken: 'nordmarken',
    HoherNorden: 'hoher-norden',
    Nordaventurien: 'nordaventurien',
    Thorwal: 'thorwal',
    Bornland: 'bornland',
    Orkland: 'orkland',
    AndergastUndNostria: 'andergast-und-nostria',
    Mittelaventurien: 'mittelaventurien',
    SchattenlandeTobrien: 'schattenlande-tobrien',
    Tulamidenlande: 'tulamidenlande',
    Echsensuempf: 'echsensuempf',
    Charyptik: 'charyptik',
    Aranien: 'aranien',
    Meridiana: 'meridiana',
    Suedaventurien: 'suedaventurien',
    Suedwest: 'suedwest',
    Seefahrt: 'seefahrt',
    Tiefenreich: 'tiefenreich',
    Myranor: 'myranor',
    Uthuria: 'uthuria',
    Tharun: 'tharun',
    Rakshazar: 'rakshazar',
    AufReisen: 'auf-reisen',
} as const;

export type RegionKey = (typeof RegionKey)[keyof typeof RegionKey];

export const REGION_KEYS: RegionKey[] = Object.values(RegionKey);

const REGION_NEIGHBORS: Record<RegionKey, RegionKey[]> = {
    nordmarken: [RegionKey.Mittelaventurien, RegionKey.Seefahrt],
    mittelaventurien: [RegionKey.Nordmarken, RegionKey.Tulamidenlande, RegionKey.Seefahrt],
    tulamidenlande: [RegionKey.Mittelaventurien, RegionKey.Suedaventurien, RegionKey.Seefahrt],
    suedaventurien: [RegionKey.Tulamidenlande, RegionKey.Suedwest, RegionKey.Seefahrt],
    suedwest: [RegionKey.Suedaventurien, RegionKey.Seefahrt],
    seefahrt: [
        RegionKey.Nordmarken,
        RegionKey.Mittelaventurien,
        RegionKey.Tulamidenlande,
        RegionKey.Suedaventurien,
        RegionKey.Suedwest,
    ],
    'hoher-norden': [],
    nordaventurien: [],
    thorwal: [],
    bornland: [],
    orkland: [],
    'andergast-und-nostria': [],
    'schattenlande-tobrien': [],
    echsensuempf: [],
    charyptik: [],
    aranien: [],
    meridiana: [],
    tiefenreich: [],
    myranor: [],
    uthuria: [],
    tharun: [],
    rakshazar: [],
    'auf-reisen': [],
};

const REGION_LABEL: Record<RegionKey, string> = {
    nordmarken: 'Nordmarken',
    'hoher-norden': 'Hoher Norden',
    nordaventurien: 'Nordaventurien',
    thorwal: 'Thorwal',
    bornland: 'Bornland',
    orkland: 'Orkland',
    'andergast-und-nostria': 'Andergast und Nostria',
    mittelaventurien: 'Mittelaventurien',
    'schattenlande-tobrien': 'Schattenlande / Tobrien',
    tulamidenlande: 'Tulamidenlande',
    echsensuempf: 'Echsensümpfe',
    charyptik: 'Charyptik',
    aranien: 'Aranien',
    meridiana: 'Meridiana',
    suedaventurien: 'Südaventurien',
    suedwest: 'Südwest',
    seefahrt: 'Seefahrt',
    tiefenreich: 'Tiefenreich',
    myranor: 'Myranor',
    uthuria: 'Uthuria',
    tharun: 'Tharun',
    rakshazar: 'Rakshazar',
    'auf-reisen': 'Auf Reisen',
};

const EXTRA_AVENTURIAN = new Set<RegionKey>([
    RegionKey.Myranor,
    RegionKey.Uthuria,
    RegionKey.Tharun,
    RegionKey.Rakshazar,
    RegionKey.Tiefenreich,
]);

export function regionFrom(value: string): RegionKey | null {
    return (REGION_KEYS as string[]).includes(value) ? (value as RegionKey) : null;
}

export function regionNeighbors(region: RegionKey): RegionKey[] {
    return REGION_NEIGHBORS[region];
}

export function regionIsNeighborOf(region: RegionKey, other: RegionKey): boolean {
    return regionNeighbors(region).includes(other);
}

export function regionIsExtraAventurian(region: RegionKey): boolean {
    return EXTRA_AVENTURIAN.has(region);
}

export function regionLabel(region: RegionKey): string {
    return REGION_LABEL[region];
}

export function regionLabelFor(slug: string): string {
    const key = regionFrom(slug);

    if (key !== null) {
        return regionLabel(key);
    }

    const spaced = slug.replaceAll('-', ' ');

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}
