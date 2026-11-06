import { expect, test, type Page } from '@playwright/test';
import { expectNoA11yViolations, setTheme, type Theme } from '../a11y-helpers';
import { chooseRadioInFieldsetAndPersist, completeWizard, formHeading } from '../wizard-helpers';

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

async function loginAdmin(page: Page): Promise<void> {
    await page.goto('/verwaltung');
    await page.getByLabel('Passwort').fill('secret');
    await page.getByRole('button', { name: 'Anmelden' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Abenteuer' })).toBeVisible();
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
            await chooseRadioInFieldsetAndPersist(page, /Regelwerk/, 'DSA 5');
            await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Einsteiger');
            await chooseRadioInFieldsetAndPersist(page, /Region/, 'Egal');
            const place = page.getByRole('combobox', { name: /Ort/ });
            await place.fill('Nirgendwo');
            await place.blur();
            await page.getByRole('button', { name: 'Weiter', exact: true }).click();
            await expect(page.locator('#error-summary')).toBeVisible();

            await expectNoA11yViolations(page);
        });

        test('Ortssuche hat keine axe-Verstöße', async ({ page }) => {
            await openThemed(page, theme);
            await chooseRadioInFieldsetAndPersist(page, /Regelwerk/, 'DSA 5');
            await chooseRadioInFieldsetAndPersist(page, /Heldinnen und Helden/, 'Einsteiger');
            await chooseRadioInFieldsetAndPersist(page, /Region/, 'Egal');
            await page.getByRole('combobox', { name: /Ort/ }).fill('ga');
            await expect(page.getByRole('option', { name: /Gareth/ })).toBeVisible();
            await expect(page.getByRole('combobox', { name: /Ort/ })).toHaveAttribute('aria-expanded', 'true');

            await expectNoA11yViolations(page);
        });

        test('Ergebnisseite hat keine axe-Verstöße', async ({ page }) => {
            await openThemed(page, theme);
            await completeWizard(page);
            await expect(page.getByTestId('recommendation').first()).toBeVisible();

            await expectNoA11yViolations(page);
        });

        test('Verwaltung Abenteuerliste hat keine axe-Verstöße', async ({ page }) => {
            await setTheme(page, theme);
            await loginAdmin(page);
            await expect(page.locator('html')).toHaveClass(theme === 'dark' ? /dark/ : /^(?!.*dark).*$/);
            await expectNoA11yViolations(page);
        });

        test('Verwaltung Abenteuerformular hat keine axe-Verstöße', async ({ page }) => {
            await setTheme(page, theme);
            await loginAdmin(page);
            await page.getByRole('link', { name: 'Bearbeiten' }).first().click();
            await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
            await expect(page.locator('#wiki_url')).toBeVisible();
            await expect(page.locator('html')).toHaveClass(theme === 'dark' ? /dark/ : /^(?!.*dark).*$/);
            await expectNoA11yViolations(page);
        });
    });
}
