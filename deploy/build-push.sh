#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

IMAGE_REPO="${IMAGE_REPO:-ghcr.io/rathch/dsa-abenteuerfinder}"
SHA="$(git rev-parse HEAD 2>/dev/null || echo local)"
SHORT_SHA="$(git rev-parse --short HEAD 2>/dev/null || echo local)"
PUSH=0

for arg in "$@"; do
  case "$arg" in
    --push) PUSH=1 ;;
    --help|-h)
      cat <<'EOF'
Lokal Image bauen und optional nach GHCR pushen.

  ./deploy/build-push.sh           # nur bauen
  ./deploy/build-push.sh --push    # bauen und pushen

Umgebung:
  IMAGE_REPO   Default ghcr.io/rathch/dsa-abenteuerfinder

Erstes Push: echo TOKEN | docker login ghcr.io -u USER --password-stdin
EOF
      exit 0
      ;;
    *)
      echo "Unbekanntes Argument: $arg" >&2
      exit 1
      ;;
  esac
done

echo "Baue ${IMAGE_REPO}:latest (sha ${SHORT_SHA})"
docker build \
  --build-arg "VCS_REF=${SHA}" \
  -t "${IMAGE_REPO}:latest" \
  -t "${IMAGE_REPO}:sha-${SHA}" \
  -t "${IMAGE_REPO}:sha-${SHORT_SHA}" \
  .

if [ "$PUSH" -eq 1 ]; then
  echo "Pushe nach ${IMAGE_REPO}"
  docker push "${IMAGE_REPO}:latest"
  docker push "${IMAGE_REPO}:sha-${SHA}"
  docker push "${IMAGE_REPO}:sha-${SHORT_SHA}"
  echo "Server: IMAGE=${IMAGE_REPO}:sha-${SHA} in /opt/apps/dsa-abenteuerfinder/.env"
fi
