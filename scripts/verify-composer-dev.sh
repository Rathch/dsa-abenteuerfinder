#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
set -euo pipefail
cd "$(dirname "$0")/.."
pkill -f "artisan serve" 2>/dev/null || true
pkill -f "artisan pail" 2>/dev/null || true
pkill -f "vite" 2>/dev/null || true
sleep 1

# Ohne Herd/nvm im PATH – so wie ein frisches Ubuntu-Terminal.
PATH="/usr/local/bin:/usr/bin:/bin" timeout 12 composer run dev >/tmp/composer-dev-out.txt 2>&1 || code=$?
code="${code:-0}"
echo "exit=$code"
head -n 40 /tmp/composer-dev-out.txt
# timeout → 124 heißt: Stack lief (Erfolg). 1 = Startfehler.
if [[ "$code" == "124" ]] || grep -q "Server running" /tmp/composer-dev-out.txt; then
    echo OK
    exit 0
fi
echo FAIL
exit 1
