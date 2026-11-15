import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './tests/visual',
    testMatch: ['public/static-session.spec.ts'],
    timeout: 60_000,
    use: {
        baseURL: 'http://127.0.0.1:4173',
        ...devices['Desktop Chrome'],
        locale: 'de-DE',
    },
    webServer: {
        command: 'npm run build && npx vite preview --host 127.0.0.1 --port 4173 --strictPort',
        url: 'http://127.0.0.1:4173',
        reuseExistingServer: false,
        timeout: 120_000,
    },
});
