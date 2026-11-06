#!/usr/bin/env bash
# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

IMAGE_REPO="${IMAGE_REPO:-ghcr.io/rathch/dsa-abenteuerfinder}"
DEPLOY_SSH="${DEPLOY_SSH:-server_np}"
DEPLOY_DIR="${DEPLOY_DIR:-/opt/apps/dsa-abenteuerfinder}"
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.prod.yml}"
REMOTE_ONLY=0

usage() {
  cat <<'EOF'
Lokal Image bauen, nach GHCR pushen, auf dem Server pullen und die App neu starten.

  ./deploy/deploy.sh                 bauen, pushen, Server-Update
  ./deploy/deploy.sh --remote-only   nur Server (Image liegt schon auf GHCR)

Umgebung:
  DEPLOY_SSH   SSH-Ziel, Default server_np (~/.ssh/config)
  DEPLOY_DIR   App-Verzeichnis auf dem Server, Default /opt/apps/dsa-abenteuerfinder
  IMAGE_REPO   Default ghcr.io/rathch/dsa-abenteuerfinder
  DEPLOY_ALLOW_DIRTY=1  auch bei uncommitteten Änderungen an getrackten Dateien
EOF
}

for arg in "$@"; do
  case "$arg" in
    --remote-only) REMOTE_ONLY=1 ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      echo "Unbekanntes Argument: $arg" >&2
      usage >&2
      exit 1
      ;;
  esac
done

if ! git diff --quiet -- || ! git diff --cached --quiet --; then
  if [ "${DEPLOY_ALLOW_DIRTY:-0}" != "1" ]; then
    echo "Getrackte Dateien sind uncommittet. Erst committen oder DEPLOY_ALLOW_DIRTY=1 setzen." >&2
    exit 1
  fi
  echo "Warnung: uncommittete Änderungen gehen ins Image, der Tag bleibt der letzte Commit-SHA." >&2
fi

SHA="$(git rev-parse HEAD)"
SHORT_SHA="$(git rev-parse --short HEAD)"
IMAGE_TAG="${IMAGE_REPO}:sha-${SHA}"

if [[ ! "$IMAGE_TAG" =~ ^[a-zA-Z0-9._/:@-]+$ ]]; then
  echo "Ungültiger Image-Tag: ${IMAGE_TAG}" >&2
  exit 1
fi

SSH_CTL=""
ssh_cmd() {
  if [ -n "$SSH_CTL" ]; then
    ssh -o ControlPath="$SSH_CTL" "$DEPLOY_SSH" "$@"
  else
    ssh "$DEPLOY_SSH" "$@"
  fi
}

scp_cmd() {
  if [ -n "$SSH_CTL" ]; then
    scp -o ControlPath="$SSH_CTL" "$@"
  else
    scp "$@"
  fi
}

cleanup_ssh() {
  if [ -n "$SSH_CTL" ]; then
    ssh -O exit -o ControlPath="$SSH_CTL" "$DEPLOY_SSH" >/dev/null 2>&1 || true
    rm -f "$SSH_CTL"
  fi
}

echo "SSH ${DEPLOY_SSH} (${DEPLOY_DIR}) …"
SSH_CTL="${XDG_RUNTIME_DIR:-/tmp}/dsa-abenteuerfinder-ssh-$$"
if ssh -fN -o ControlMaster=yes -o ControlPath="$SSH_CTL" -o ControlPersist=20m -o ConnectTimeout=15 "$DEPLOY_SSH"; then
  trap cleanup_ssh EXIT
else
  SSH_CTL=""
  echo "Kein SSH-Multiplex, jede Remote-Aktion öffnet eine eigene Verbindung." >&2
fi

ssh_cmd mkdir -p "${DEPLOY_DIR}/nginx"
echo "Kopiere Compose und Nginx nach ${DEPLOY_DIR}"
scp_cmd "${ROOT}/deploy/docker-compose.prod.yml" "${DEPLOY_SSH}:${DEPLOY_DIR}/docker-compose.prod.yml"
scp_cmd "${ROOT}/deploy/nginx/default.conf" "${DEPLOY_SSH}:${DEPLOY_DIR}/nginx/default.conf"

if ! ssh_cmd test -f "${DEPLOY_DIR}/.env"; then
  echo ".env fehlt auf dem Server: ${DEPLOY_DIR}/.env" >&2
  exit 1
fi

if [ "$REMOTE_ONLY" -eq 0 ]; then
  echo "Baue und pushe ${IMAGE_TAG}"
  ./deploy/build-push.sh --push

  echo "Warte, bis GHCR ${SHORT_SHA} ausliefert …"
  ready=0
  for _ in $(seq 1 20); do
    if docker manifest inspect "$IMAGE_TAG" >/dev/null 2>&1; then
      ready=1
      break
    fi
    sleep 2
  done
  if [ "$ready" -ne 1 ]; then
    echo "GHCR liefert ${IMAGE_TAG} noch nicht." >&2
    exit 1
  fi
fi

echo "Server: pull, Assets, Web neu starten (${IMAGE_TAG})"
ssh_cmd env IMAGE_TAG="$IMAGE_TAG" DEPLOY_DIR="$DEPLOY_DIR" COMPOSE_FILE="$COMPOSE_FILE" bash -s <<'REMOTE'
set -euo pipefail
cd "$DEPLOY_DIR"

tmp="$(mktemp)"
awk -v img="$IMAGE_TAG" '
  BEGIN { found = 0 }
  /^IMAGE=/ { print "IMAGE=" img; found = 1; next }
  { print }
  END { if (!found) print "IMAGE=" img }
' .env > "$tmp"
if command -v chmod >/dev/null && chmod --reference=.env "$tmp" 2>/dev/null; then
  :
else
  chmod 600 "$tmp"
fi
mv "$tmp" .env

if docker compose -f "$COMPOSE_FILE" ps --services --filter "status=running" | grep -q '^app$'; then
  current="$(docker inspect dsa-abenteuerfinder-app --format '{{.Config.Image}}' 2>/dev/null || true)"
  if [ -n "${current:-}" ]; then
    echo "$current" > .last-known-good-image
  fi
fi

docker compose -f "$COMPOSE_FILE" pull
docker compose -f "$COMPOSE_FILE" up -d app scheduler web

actual="$(docker inspect dsa-abenteuerfinder-app --format '{{.Config.Image}}' 2>/dev/null || true)"
echo "Erwartetes App-Image: ${IMAGE_TAG}"
echo "Aktuelles App-Image: ${actual:-<leer>}"
if [ -z "${actual:-}" ] || [ "$actual" != "$IMAGE_TAG" ]; then
  echo "Container dsa-abenteuerfinder-app läuft nicht mit dem erwarteten Image." >&2
  docker compose -f "$COMPOSE_FILE" ps || true
  exit 1
fi

pubvol="$(docker inspect dsa-abenteuerfinder-web --format '{{range .Mounts}}{{if eq .Destination "/var/www/html/public"}}{{.Name}}{{end}}{{end}}')"
if [ -z "${pubvol:-}" ]; then
  echo "Public-Volume von dsa-abenteuerfinder-web konnte nicht ermittelt werden." >&2
  exit 1
fi

sync_public() {
  docker exec dsa-abenteuerfinder-app sh -lc 'tar -C /var/www/html/public -cf - .' \
    | docker run --rm -i -v "$pubvol:/target" alpine:3.22 sh -lc 'rm -rf /target/* /target/.[!.]* /target/..?* 2>/dev/null || true; tar -C /target -xf -'
}

sync_public

if ! docker run --rm -v "$pubvol:/target" alpine:3.22 test -f /target/build/manifest.json; then
  echo "Assets fehlen (build/manifest.json nicht gefunden)." >&2
  exit 1
fi

app_sha="$(docker exec dsa-abenteuerfinder-app sha256sum /var/www/html/public/build/manifest.json | cut -d' ' -f1)"
vol_sha="$(docker run --rm -v "$pubvol:/target" alpine:3.22 sha256sum /target/build/manifest.json | cut -d' ' -f1)"
echo "Manifest-Checksum App-Container: ${app_sha}"
echo "Manifest-Checksum Public-Volume: ${vol_sha}"
if [ "$app_sha" != "$vol_sha" ]; then
  echo "manifest.json weicht zwischen App-Container und Public-Volume ab." >&2
  exit 1
fi

docker exec dsa-abenteuerfinder-app php artisan migrate --force
docker exec dsa-abenteuerfinder-app php artisan optimize:clear
docker compose -f "$COMPOSE_FILE" up -d --force-recreate --remove-orphans web

ready=0
for _ in 1 2 3 4 5 6 7 8 9 10; do
  if docker compose -f "$COMPOSE_FILE" exec -T web wget -q -O /dev/null http://127.0.0.1/; then
    ready=1
    break
  fi
  sleep 1
done
sleep 2

if [ "$ready" -ne 1 ] || ! docker compose -f "$COMPOSE_FILE" ps --status running | grep -q "dsa-abenteuerfinder-web"; then
  echo "Neue Version fehlerhaft, versuche Rollback." >&2
  if [ -f .last-known-good-image ]; then
    rollback="$(cat .last-known-good-image)"
    tmp="$(mktemp)"
    awk -v img="$rollback" '
      BEGIN { found = 0 }
      /^IMAGE=/ { print "IMAGE=" img; found = 1; next }
      { print }
      END { if (!found) print "IMAGE=" img }
    ' .env > "$tmp"
    chmod 600 "$tmp"
    mv "$tmp" .env
    docker compose -f "$COMPOSE_FILE" pull || true
    docker compose -f "$COMPOSE_FILE" up -d --remove-orphans || true
    sync_public || true
  fi
  exit 1
fi

docker image prune -f
echo "App läuft mit ${IMAGE_TAG}"
REMOTE
