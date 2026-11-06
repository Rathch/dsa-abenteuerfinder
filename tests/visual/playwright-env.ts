import fs from 'fs';
import path from 'path';

/**
 * Muss derselbe Default sein wie in playwright.config.ts und scripts/playwright-serve.sh (Port 8010).
 */
export function getPlaywrightBaseUrl(): string {
    return process.env.PLAYWRIGHT_BASE_URL ?? 'http://127.0.0.1:8010';
}

/**
 * Liest `PLAYWRIGHT_*`-Variablen aus der .env (ohne bereits gesetzte Umgebungsvariablen zu überschreiben).
 */
export function loadPlaywrightEnvFromDotenv(): void {
    const envPath = path.join(process.cwd(), '.env');
    if (!fs.existsSync(envPath)) {
        return;
    }

    const text = fs.readFileSync(envPath, 'utf8');
    for (const line of text.split('\n')) {
        const match = line.match(/^\s*(PLAYWRIGHT_[A-Z0-9_]+)\s*=\s*(.*)$/);
        if (!match) {
            continue;
        }

        const key = match[1];
        if (process.env[key] !== undefined) {
            continue;
        }

        let value = match[2].trim();
        if (
            (value.startsWith('"') && value.endsWith('"')) ||
            (value.startsWith("'") && value.endsWith("'"))
        ) {
            value = value.slice(1, -1);
        }

        process.env[key] = value;
    }
}
