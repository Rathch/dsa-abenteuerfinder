#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
set -uo pipefail
export PATH="${HOME}/.config/herd-lite/bin:${PATH}"
# shellcheck source=/dev/null
source "${HOME}/.nvm/nvm.sh"
nvm use 24.18.0 >/dev/null
cd "$(dirname "$0")/.."

echo "=== php -v ==="
php -v || true
echo "=== which php/node/npx ==="
command -v php; command -v node; command -v npx
echo "=== .env APP_KEY ==="
grep -E '^APP_KEY=' .env || echo "NO APP_KEY LINE"
echo "=== artisan serve briefly ==="
php artisan serve --port=8099 >/tmp/serve-out.txt 2>&1 &
SPID=$!
sleep 2
kill "$SPID" 2>/dev/null || true
wait "$SPID" 2>/dev/null || true
head -n 40 /tmp/serve-out.txt || true
echo "=== artisan pail briefly ==="
timeout 3 php artisan pail --timeout=0 >/tmp/pail-out.txt 2>&1 || echo "pail exit=$?"
head -n 40 /tmp/pail-out.txt || true
echo "=== npm run dev briefly ==="
timeout 5 npm run dev >/tmp/vite-out.txt 2>&1 || echo "vite exit=$?"
head -n 60 /tmp/vite-out.txt || true
echo "=== port 8000 ==="
ss -ltnp 2>/dev/null | grep -E ':8000|:5173' || true
echo DONE
