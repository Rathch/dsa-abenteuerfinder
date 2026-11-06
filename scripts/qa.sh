#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# Dieselbe Laufzeit wie der Dev-Stack: Herd-PHP ≥ 8.5, Node ≥ 24.
export PATH="${HOME}/.config/herd-lite/bin:${PATH}"
if [[ -s "${HOME}/.nvm/nvm.sh" ]]; then
    # shellcheck source=/dev/null
    source "${HOME}/.nvm/nvm.sh"
    nvm use 24 >/dev/null 2>&1 || true
fi

RUN_PLAYWRIGHT=0

for arg in "$@"; do
    case "$arg" in
        --playwright)
            RUN_PLAYWRIGHT=1
            ;;
        *)
            echo "Unbekanntes Argument: $arg" >&2
            echo "Nutzung: ./scripts/qa.sh [--playwright]" >&2
            exit 1
            ;;
    esac
done

echo "==> QA: Linting (Pint, PHPStan, Deptrac)"
composer run lint

echo "==> QA: Backend-Tests"
composer run test

echo "==> QA: Barrierefreiheit (axe, jede Ansicht, light + dark)"
npm run test:a11y

if [[ "$RUN_PLAYWRIGHT" -eq 1 ]]; then
    echo "==> QA: Playwright visuell und weitere Browser-Flows"
    npx playwright test --grep-invert "axe-Verstöße"
fi

echo ""
echo "QA abgeschlossen."
