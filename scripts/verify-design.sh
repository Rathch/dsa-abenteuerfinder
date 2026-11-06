#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
set -euo pipefail

export PATH="${HOME}/.config/herd-lite/bin:${PATH}"
# shellcheck source=/dev/null
source "${HOME}/.nvm/nvm.sh"
nvm use 24.18.0 >/dev/null

cd "$(dirname "$0")/.."
npx playwright test
php artisan test --filter=AdventureWizard
echo OK
