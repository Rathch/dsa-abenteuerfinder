#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
set -euo pipefail

export PATH="${HOME}/.config/herd-lite/bin:${PATH}"
# shellcheck source=/dev/null
source "${HOME}/.nvm/nvm.sh"
nvm use 24.18.0 >/dev/null

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

npm run build
npx playwright test --update-snapshots
npx playwright test tests/visual/public/accessibility.spec.ts
echo "Visual baselines aktualisiert, Axe-Suite grün."
