import { expect, test, type Page } from '@playwright/test';
import { setTheme, type Theme } from '../a11y-helpers';
import { chooseChecks, chooseRadioInFieldsetAndPersist, completeWizard, formHeading } from '../wizard-helpers';

const themes: Theme[] = ['light', 'dark'];

/** Wartet auf Schrift-/Layout-Stabilität, damit Pixelvergleiche deterministisch bleiben. */
async function stabilize(page: Page): Promise<void> {
    await page.evaluate(() => document.fonts.ready);
    // Fokus-/Scroll-Zustand neutralisieren (z. B. nach programmatischem Fokus auf Überschriften).
    await page.evaluate(() => {
        (document.activeElement as HTMLElement | null)?.blur();
        window.scrollTo(0, 0);
    });
}

for (const theme of themes) {
    test.describe(`Screenshots Desktop (${theme})`, () => {
        test(`Startseite ${theme}`, async ({ page }) => {
            await setTheme(page, theme);
            await page.goto('/');
            await expect(formHeading(page)).toBeVisible();
            await stabilize(page);

            await expect(page).toHaveScreenshot(`home-${theme}.png`, { fullPage: true });
        });

        test(`Formular teilweise ausgefüllt ${theme}`, async ({ page }) => {
            await setTheme(page, theme);
            await page.goto('/');
            await chooseChecks(page, /Regelwerk/, ['DSA 5']);
            await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Einsteiger');
            await chooseChecks(page, /exotisch/, ['Nur Aventurien']);
            await page.getByRole('group', { name: /Region/ }).getByRole('checkbox', { name: /^Nordmarken/ }).check();
            await expect(page.getByRole('radio', { name: 'Havena' })).toBeVisible();
            await expect(formHeading(page)).toBeVisible();
            await stabilize(page);

            await expect(page).toHaveScreenshot(`wizard-form-${theme}.png`, { fullPage: true });
        });

        test(`Ergebnisse ${theme}`, async ({ page }) => {
            test.setTimeout(180_000);
            await setTheme(page, theme);
            await page.goto('/');
            await completeWizard(page);
            await expect(page.getByTestId('recommendation').first()).toBeVisible();
            await stabilize(page);

            await expect(page).toHaveScreenshot(`results-${theme}.png`, { fullPage: true });
        });

        test(`Impressum ${theme}`, async ({ page }) => {
            await setTheme(page, theme);
            await page.goto('/impressum');
            await expect(page.getByRole('heading', { level: 1, name: 'Impressum' })).toBeVisible();
            await stabilize(page);

            await expect(page).toHaveScreenshot(`impressum-${theme}.png`, { fullPage: true });
        });
    });
}

for (const theme of themes) {
    test.describe(`Screenshots Mobil 390 (${theme})`, () => {
        test(`Startseite mobil ${theme} @mobile`, async ({ page }) => {
            await setTheme(page, theme);
            await page.goto('/');
            await expect(formHeading(page)).toBeVisible();
            await stabilize(page);

            await expect(page).toHaveScreenshot(`home-mobile-${theme}.png`, { fullPage: true });
        });

        test(`Ergebnisse mobil ${theme} @mobile`, async ({ page }) => {
            test.setTimeout(180_000);
            await setTheme(page, theme);
            await page.goto('/');
            await completeWizard(page);
            await expect(page.getByTestId('recommendation').first()).toBeVisible();
            await stabilize(page);

            await expect(page).toHaveScreenshot(`results-mobile-${theme}.png`, { fullPage: true });
        });
    });
}
