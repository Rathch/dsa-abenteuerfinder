#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
set -uo pipefail
cd "$(dirname "$0")/.."

echo "=== default PATH php ==="
hash -r
command -v php || true
php -v 2>&1 | head -n 1 || true

echo "=== ports ==="
ss -ltnp 2>/dev/null | grep -E ':8000|:5173|:8089' || echo "(none)"

# clean leftover vite from diagnose
pkill -f "vite" 2>/dev/null || true
sleep 1

echo "=== composer without herd PATH ==="
# Simulate a shell that only has system php 8.3
PATH="/usr/bin:/bin:${HOME}/.nvm/versions/node/v24.18.0/bin" \
  timeout 8 composer run dev >/tmp/dev-system.txt 2>&1 || echo "system-dev exit=$?"
head -n 80 /tmp/dev-system.txt || true

echo "=== composer with herd PATH ==="
export PATH="${HOME}/.config/herd-lite/bin:${PATH}"
# shellcheck source=/dev/null
source "${HOME}/.nvm/nvm.sh"
nvm use 24.18.0 >/dev/null
pkill -f "artisan serve" 2>/dev/null || true
pkill -f "artisan pail" 2>/dev/null || true
pkill -f "vite" 2>/dev/null || true
sleep 1
timeout 10 composer run dev >/tmp/dev-herd.txt 2>&1 || echo "herd-dev exit=$?"
head -n 100 /tmp/dev-herd.txt || true
echo DONE
