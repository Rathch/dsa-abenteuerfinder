#!/bin/sh
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only

set -eu

rev_file=/var/www/html/.image-revision
rev_raw=$(cat "$rev_file" 2>/dev/null || true)
rev=$(printf '%s' "$rev_raw" | tr -d '\r\n')
[ -n "$rev" ] || rev=no-image-revision

marker=/public-vol/.dsa_abenteuerfinder_public_image_revision
desired="v1:${rev}"

current_raw=$(cat "$marker" 2>/dev/null || true)
current=$(printf '%s' "$current_raw" | tr -d '\r\n')

if [ "${PUBLIC_INIT_FORCE:-0}" = "1" ]; then
  current=force-resync
fi

if [ "$current" != "$desired" ]; then
  echo "[public-init] syncing public/ from image (${desired}, was=${current:-empty})"
  rm -rf /public-vol/* /public-vol/.[!.]* /public-vol/..?* 2>/dev/null || true
  mkdir -p /public-vol
  cp -a /var/www/html/public/. /public-vol/
  printf '%s' "$desired" > "$marker"
else
  echo "[public-init] public/ already up-to-date (${desired})"
fi

if [ -d /var/www/html/storage/app/public ]; then
  rm -rf /public-vol/storage 2>/dev/null || true
  ln -snf /var/www/html/storage/app/public /public-vol/storage
fi

chmod -R a+rX /public-vol
