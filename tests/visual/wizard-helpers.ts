import { expect, type Page } from '@playwright/test';

export function formHeading(page: Page) {
    return page.getByRole('heading', { level: 2, name: 'Deine Wünsche' });
}

export function resultsHeading(page: Page) {
    return page.getByRole('heading', { level: 2, name: 'Deine Empfehlungen' });
}

export function reviewHeading(page: Page) {
    return page.getByRole('heading', { level: 3, name: 'Passt das so?' });
}

/** Wartet auf den nächsten Livewire-Update. */
async function waitForLivewire(page: Page): Promise<void> {
    await page.waitForResponse(
        (response) => response.url().includes('/livewire') && response.ok(),
        { timeout: 15_000 },
    );
}

/** Wählt eine Radio-Option (Name beginnt mit `label`). */
export async function chooseRadio(page: Page, label: string | RegExp): Promise<void> {
    const name = typeof label === 'string' ? new RegExp(`^${label}`) : label;
    await Promise.all([waitForLivewire(page), page.getByRole('radio', { name }).check()]);
}

/** Wählt eine Radio-Option innerhalb des sichtbaren Fieldsets. */
export async function chooseRadioInFieldset(
    page: Page,
    legend: string | RegExp,
    label: string | RegExp,
): Promise<void> {
    const fieldset = page.getByRole('group', { name: legend });
    const name = typeof label === 'string' ? new RegExp(`^${label}`) : label;
    await Promise.all([waitForLivewire(page), fieldset.getByRole('radio', { name }).check()]);
}

export async function chooseRadioInFieldsetAndPersist(
    page: Page,
    legend: string | RegExp,
    label: string | RegExp,
): Promise<void> {
    await chooseRadioInFieldset(page, legend, label);
}

export async function submitForm(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Empfehlungen anzeigen' }).click();
    await expect(resultsHeading(page)).toBeVisible({ timeout: 30_000 });
}

/**
 * Füllt Progressive Reveal Schritt für Schritt und sendet ab.
 */
export async function completeWizard(page: Page): Promise<void> {
    await expect(formHeading(page)).toBeVisible();

    await expect(page.getByRole('group', { name: /Regelwerk/ })).toBeVisible();
    await chooseRadioInFieldsetAndPersist(page, /Regelwerk/, 'DSA 5');

    await expect(page.getByRole('group', { name: /Heldinnen und Helden/ })).toBeVisible();
    await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Einsteiger');

    await expect(page.getByRole('group', { name: /Region/ })).toBeVisible();
    await chooseRadioInFieldsetAndPersist(page, /Region/, 'Nordmarken');
    await expect(page.getByRole('button', { name: 'Ohne Ort weiter' })).toBeVisible();
    await Promise.all([
        waitForLivewire(page),
        page.getByRole('button', { name: 'Ohne Ort weiter' }).click(),
    ]);

    await expect(page.getByRole('group', { name: /exotisch/ })).toBeVisible();
    await chooseRadioInFieldsetAndPersist(page, /exotisch/, 'Klassisches Aventurien');

    await expect(page.getByRole('group', { name: /Zeit/ })).toBeVisible();
    await chooseRadioInFieldsetAndPersist(page, /Zeit/, 'Ein kurzer Abend');

    await expect(page.getByRole('group', { name: /Genres/ })).toBeVisible();
    const investigation = page.getByRole('checkbox', { name: 'Ermittlung' });
    if (await investigation.count()) {
        await Promise.all([waitForLivewire(page), investigation.check()]);
    } else {
        await Promise.all([
            waitForLivewire(page),
            page.locator('fieldset input[type="checkbox"]').first().check(),
        ]);
    }
    await Promise.all([
        waitForLivewire(page),
        page.getByRole('button', { name: 'Weiter', exact: true }).click(),
    ]);

    await expect(page.getByRole('group', { name: /Spielleitung/ })).toBeVisible();
    await chooseRadioInFieldsetAndPersist(page, /Spielleitung/, 'Einsteiger');

    await expect(page.getByRole('group', { name: /Spielende/ })).toBeVisible();
    await chooseRadioInFieldsetAndPersist(page, /Spielende/, '3 bis 5 Spielende');

    await expect(reviewHeading(page)).toBeVisible({ timeout: 30_000 });
    await submitForm(page);
}
