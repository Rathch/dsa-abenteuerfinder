import { defineConfig, devices } from '@playwright/test';

/**
 * Visuelle Regression, Barrierefreiheit (axe, WCAG 2.2 AA) und Browser-Flows
 * gegen die statische Seite (`vite preview`).
 */
const baseURL = 'http://127.0.0.1:4173';
const shouldManageServer = process.env.PLAYWRIGHT_MANAGE_SERVER !== '0';
const configuredWorkers = Math.max(
    1,
    Number.parseInt(process.env.PLAYWRIGHT_WORKERS ?? (process.env.CI ? '2' : '4'), 10) || 4
);

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
              command: 'npm run build && npx vite preview --host 127.0.0.1 --port 4173 --strictPort',
              url: baseURL,
              reuseExistingServer: false,
              timeout: 180_000,
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
