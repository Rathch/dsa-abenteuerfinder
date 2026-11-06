#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
#
# Startet Laravel für Playwright mit isolierter SQLite-Datenbank
# (database/playwright.sqlite), frischen Migrationen und Abenteuer-Import auf Port 8010.
# Voraussetzung: gebautes Frontend (`npm run build`, public/build/manifest.json).
# Für lokale Läufe lieber einen laufenden Dev-Server nutzen (PLAYWRIGHT_MANAGE_SERVER=0).
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PORT="${PLAYWRIGHT_PORT:-8010}"

if [[ ! -f .env ]]; then
  cp .env.example .env
fi

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --force
fi

if [[ ! -f public/build/manifest.json ]]; then
  echo "[playwright-serve] public/build/manifest.json fehlt – bitte zuerst 'npm run build' ausführen." >&2
  exit 1
fi

DB_FILE="${PLAYWRIGHT_DB_DATABASE:-${ROOT}/database/playwright.sqlite}"
mkdir -p "$(dirname "$DB_FILE")"
touch "$DB_FILE"

export DB_CONNECTION=sqlite
export DB_DATABASE="$DB_FILE"
export PLAYWRIGHT_E2E=1
# Wizard-Antworten liegen in der Session; Datenbank-Sessions brauchen keine zusätzliche Infrastruktur.
export SESSION_DRIVER=database
export CACHE_STORE=file
export APP_URL="http://127.0.0.1:${PORT}"
# Passwort für /verwaltung in Playwright: secret
if [[ -z "${ADMIN_PASSWORD_HASH:-}" ]]; then
  export ADMIN_PASSWORD_HASH='$2y$04$dv9mIfazcWZ7jv50qR272.R2USMpwAo1/xEfaVOADZ1FG71kM9gWG'
fi
# Parallele Playwright-Worker brauchen mehr als einen PHP-Prozess.
export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

echo "[playwright-serve] SQLite: ${DB_FILE} (isoliert von database.sqlite), Port ${PORT}" >&2

# Deterministischer Zustand für visuelle Baselines (lokal und in CI).
php artisan migrate:fresh --force
php artisan adventures:import --no-interaction

exec php artisan serve --host=127.0.0.1 --port="${PORT}" --no-reload
