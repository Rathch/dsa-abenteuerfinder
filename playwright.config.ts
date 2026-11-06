import path from 'path';
import { defineConfig, devices } from '@playwright/test';
import { getPlaywrightBaseUrl, loadPlaywrightEnvFromDotenv } from './tests/visual/playwright-env';

loadPlaywrightEnvFromDotenv();

/**
 * Visuelle Regression, Barrierefreiheit (axe, WCAG 2.2 AA) und Browser-Flows.
 * — Lokal und in CI startet Playwright denselben isolierten Laravel-Server (scripts/playwright-serve.sh,
 *   SQLite database/playwright.sqlite, frische Migrationen, Abenteuer-Import).
 * — Projekte: chromium-desktop (alle Specs) und chromium-mobile (390x844, nur mit @mobile markierte Tests).
 * Baselines: `npm run test:visual:update` (ideal: Linux/WSL wie CI).
 */
const baseURL = getPlaywrightBaseUrl();
const shouldManageServer = process.env.PLAYWRIGHT_MANAGE_SERVER !== '0';
const playwrightDbPath =
    process.env.PLAYWRIGHT_DB_DATABASE ?? path.join(process.cwd(), 'database', 'playwright.sqlite');
const configuredWorkers = Math.max(
    1,
    Number.parseInt(process.env.PLAYWRIGHT_WORKERS ?? (process.env.CI ? '2' : '4'), 10) || 4
);

// Isolierte SQLite-DB, damit lokale und CI-Läufe identische Daten sehen.
process.env.APP_ENV = 'testing';
process.env.DB_CONNECTION = 'sqlite';
process.env.DB_DATABASE = playwrightDbPath;
process.env.DB_BUSY_TIMEOUT = process.env.DB_BUSY_TIMEOUT ?? '5000';
process.env.DB_JOURNAL_MODE = process.env.DB_JOURNAL_MODE ?? 'WAL';

export default defineConfig({
    testDir: './tests/visual',
    fullyParallel: true,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    workers: configuredWorkers,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : 'list',
    timeout: 60_000,
    expect: {
        timeout: 15_000,
        toHaveScreenshot: {
            maxDiffPixelRatio: 0.04,
            threshold: 0.35,
            animations: 'disabled',
            caret: 'hide',
        },
    },
    use: {
        baseURL,
        ...devices['Desktop Chrome'],
        locale: 'de-DE',
        colorScheme: 'light',
        screenshot: 'only-on-failure',
        video: 'off',
        trace: 'on-first-retry',
    },
    webServer: shouldManageServer
        ? {
              command: 'bash ./scripts/playwright-serve.sh',
              url: `${baseURL}/up`,
              // Nie einen eventuell veralteten Dev-Server wiederverwenden (deterministische Baselines).
              reuseExistingServer: false,
              timeout: 120_000,
          }
        : undefined,
    projects: [
        {
            name: 'chromium-desktop',
            testMatch: ['public/**/*.spec.ts'],
            grepInvert: /@mobile/,
        },
        {
            name: 'chromium-mobile',
            testMatch: ['public/**/*.spec.ts'],
            grep: /@mobile/,
            use: {
                ...devices['Desktop Chrome'],
                viewport: { width: 390, height: 844 },
                isMobile: true,
                hasTouch: true,
                deviceScaleFactor: 1,
            },
        },
    ],
});
