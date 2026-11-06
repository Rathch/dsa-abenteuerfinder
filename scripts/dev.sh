#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only
#
# Startet Server, Log-Tail und Vite. Stellt sicher, dass Herd-PHP (≥ 8.5)
# und eine aktuelle Node-Version (≥ 20, bevorzugt nvm 24) im PATH liegen –
# sonst greift unter Ubuntu oft systemweit PHP 8.3 / Node 18 und concurrently scheitert.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export PATH="${HOME}/.config/herd-lite/bin:${PATH}"

if [[ -s "${HOME}/.nvm/nvm.sh" ]]; then
    # shellcheck source=/dev/null
    source "${HOME}/.nvm/nvm.sh"
    if nvm use 24 >/dev/null 2>&1; then
        :
    elif nvm use --lts >/dev/null 2>&1; then
        :
    fi
elif [[ -x /home/linuxbrew/.linuxbrew/bin/node ]]; then
    export PATH="/home/linuxbrew/.linuxbrew/bin:${PATH}"
fi

PHP_BIN="$(command -v php || true)"
NODE_BIN="$(command -v node || true)"

if [[ -z "$PHP_BIN" ]]; then
    echo "PHP nicht gefunden. Bitte PHP ≥ 8.5 installieren (z. B. php.new/Herd)." >&2
    exit 1
fi

PHP_VERSION="$("$PHP_BIN" -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_OK="$("$PHP_BIN" -r 'echo version_compare(PHP_VERSION, "8.5.0", ">=") ? "1" : "0";')"
if [[ "$PHP_OK" != "1" ]]; then
    echo "PHP ${PHP_VERSION} ist zu alt (${PHP_BIN}). Benötigt: PHP ≥ 8.5." >&2
    echo "Install: /bin/bash -c \"\$(curl -fsSL https://php.new/install/linux/8.5)\"" >&2
    echo "Danach Terminal neu öffnen bzw. PATH um ~/.config/herd-lite/bin erweitern." >&2
    exit 1
fi

if [[ -z "$NODE_BIN" ]]; then
    echo "Node.js nicht gefunden. Bitte Node ≥ 20 installieren (empfohlen: nvm, Node 24)." >&2
    exit 1
fi

NODE_MAJOR="$("$NODE_BIN" -p "process.versions.node.split('.')[0]")"
if [[ "$NODE_MAJOR" -lt 20 ]]; then
    echo "Node.js $("$NODE_BIN" -v) ist zu alt (${NODE_BIN}). Benötigt: Node ≥ 20 (Projekt: ≥ 24)." >&2
    echo "Mit nvm: nvm install 24 && nvm use 24" >&2
    exit 1
fi

echo "Dev-Stack: PHP $("$PHP_BIN" -v | head -n1 | cut -d' ' -f2) · Node $("$NODE_BIN" -v) · http://127.0.0.1:8000"

exec npx concurrently \
    -c "#93c5fd,#c4b5fd,#fdba74" \
    "php artisan serve" \
    "php artisan pail --timeout=0" \
    "npm run dev" \
    --names=server,logs,vite \
    --kill-others
