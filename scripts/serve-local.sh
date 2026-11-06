#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
set -euo pipefail
export PATH="${HOME}/.config/herd-lite/bin:${PATH}"
cd "$(dirname "$0")/.."
exec php artisan serve --host=127.0.0.1 --port=8089
