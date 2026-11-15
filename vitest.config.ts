import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        include: ['frontend/src/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            include: ['frontend/src/domain/**/*.ts'],
            exclude: ['frontend/src/domain/**/*.test.ts'],
            thresholds: {
                lines: 90,
            },
        },
    },
});
