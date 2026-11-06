import AxeBuilder from '@axe-core/playwright';
import { expect, type Page } from '@playwright/test';

export type Theme = 'light' | 'dark';

/**
 * Setzt `localStorage.theme` vor jedem Seitenaufruf (greift im Bootstrap-Skript vor dem ersten Paint).
 * Muss vor `page.goto()` aufgerufen werden.
 */
export async function setTheme(page: Page, theme: Theme): Promise<void> {
    await page.addInitScript((value: string) => {
        localStorage.setItem('theme', value);
    }, theme);
}

export type A11yScanOptions = {
    /** Nur dieser Bereich (z. B. `#main-content`). */
    includeSelector?: string;
};

export const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag22aa'];

const AXE_ANALYZE_TIMEOUT_MS = 60_000;

export async function expectNoA11yViolations(page: Page, options?: A11yScanOptions): Promise<void> {
    let builder = new AxeBuilder({ page }).withTags(AXE_TAGS);

    if (options?.includeSelector) {
        builder = builder.include(options.includeSelector);
    }

    const result = await Promise.race([
        builder.analyze(),
        new Promise<never>((_, reject) => {
            setTimeout(() => {
                reject(
                    new Error(
                        `Axe analyze exceeded ${AXE_ANALYZE_TIMEOUT_MS}ms (${options?.includeSelector ?? 'full document'})`
                    )
                );
            }, AXE_ANALYZE_TIMEOUT_MS);
        }),
    ]);

    const summary = result.violations.map((violation) => ({
        id: violation.id,
        impact: violation.impact,
        help: violation.help,
        nodes: violation.nodes.map((node) => node.target.join(' ')),
    }));

    expect(summary, 'axe violations').toEqual([]);
}
