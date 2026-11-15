import { expect, test, type Page } from '@playwright/test';
import { expectNoA11yViolations, setTheme, type Theme } from '../a11y-helpers';
import { chooseChecks, chooseRadioInFieldsetAndPersist, completeWizard, formHeading } from '../wizard-helpers';

/**
 * Festes QA-Gate: jede Ansicht in Hell und Dunkel, geprüft mit axe (WCAG 2.2 AA).
 */

const themes: Theme[] = ['light', 'dark'];

const routes = [
    { path: '/', name: 'Startseite' },
    { path: '/impressum', name: 'Impressum' },
    { path: '/datenschutz', name: 'Datenschutz' },
    { path: '/verwaltung', name: 'Verwaltung Login' },
    { path: '/seite-gibt-es-nicht', name: '404' },
];

async function openThemed(page: Page, theme: Theme, path = '/'): Promise<void> {
    await setTheme(page, theme);
    await page.goto(path);
    await expect(page.locator('main#main-content')).toBeVisible();
    await expect(page.locator('html')).toHaveClass(theme === 'dark' ? /dark/ : /^(?!.*dark).*$/);
}

for (const theme of themes) {
    test.describe(`axe (${theme})`, () => {
        for (const { path, name } of routes) {
            test(`${name} (${path}) hat keine axe-Verstöße`, async ({ page }) => {
                await openThemed(page, theme, path);
                await expectNoA11yViolations(page);
            });
        }

        test('Wunsch-Formular hat keine axe-Verstöße', async ({ page }) => {
            await openThemed(page, theme);
            await expect(formHeading(page)).toBeVisible();
            await expectNoA11yViolations(page);
        });

        test('Formular-Fehlerübersicht hat keine axe-Verstöße', async ({ page }) => {
            await openThemed(page, theme);
            await chooseChecks(page, /Regelwerk/, ['DSA 5']);
            await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Einsteiger');
            await chooseChecks(page, /exotisch/, ['Nur Aventurien']);
            await page.getByRole('group', { name: /Region/ }).getByRole('button', { name: 'Weiter', exact: true }).click();
            await expect(page.locator('#error-summary')).toBeVisible();

            await expectNoA11yViolations(page);
        });

        test('Ortsauswahl hat keine axe-Verstöße', async ({ page }) => {
            await openThemed(page, theme);
            await chooseChecks(page, /Regelwerk/, ['DSA 5']);
            await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Einsteiger');
            await chooseChecks(page, /exotisch/, ['Nur Aventurien']);
            await page.getByRole('group', { name: /Region/ }).getByRole('checkbox', { name: /^Nordmarken/ }).check();
            await expect(page.getByRole('radio', { name: 'Havena' })).toBeVisible();
            await expect(page.getByRole('radio', { name: 'Riva' })).toBeVisible();
            await expect(page.getByRole('radiogroup', { name: /Ort/ })).toBeVisible();

            await expectNoA11yViolations(page);
        });

        test('Ergebnisseite hat keine axe-Verstöße', async ({ page }) => {
            await openThemed(page, theme);
            await completeWizard(page);
            await expect(page.getByTestId('recommendation').first()).toBeVisible();

            await expectNoA11yViolations(page);
        });
    });
}
