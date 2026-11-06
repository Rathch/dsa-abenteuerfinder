<!--
SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich

SPDX-License-Identifier: GPL-3.0-only
-->

# Production Deploy (Docker Compose)

Host: `https://dsa-abenteuerfinder.rath-ulrich.de` (Traefik, Let's Encrypt).

## Server-Layout

Auf dem Server liegt ein **flaches** Verzeichnis (kein `deploy/`-Unterordner):

```text
/opt/apps/dsa-abenteuerfinder/
  docker-compose.prod.yml   # aus deploy/docker-compose.prod.yml
  .env                      # Laravel-Produktionswerte + IMAGE=…
  nginx/default.conf        # aus deploy/nginx/default.conf
```

Die `.env` wird **nicht** per Deploy überschrieben (Vorlage: `deploy/.env.example`).
`make deploy` setzt nur die Zeile `IMAGE=` auf den neuen SHA-Tag.

## Services

| Service       | Container                          | Aufgabe                                   |
| ------------- | ---------------------------------- | ----------------------------------------- |
| `public-init` | `dsa-abenteuerfinder-public-init`  | kopiert `public/` aus dem Image ins Volume |
| `app`         | `dsa-abenteuerfinder-app`          | PHP-FPM (Port 9000, intern)               |
| `scheduler`   | `dsa-abenteuerfinder-scheduler`    | `php artisan schedule:run` jede Minute; u. a. `adventures:sync-wiki` täglich 04:15 Europe/Berlin |
| `web`         | `dsa-abenteuerfinder-web`          | Nginx, Sicherheits-Header, Rate-Limit     |

SQLite liegt im Volume `dsaabenteuerfinder_sqlite-data` unter
`/var/lib/dsa-abenteuerfinder/database.sqlite`.

## Deploy

```bash
make build     # Image lokal bauen
make push      # bauen + nach GHCR pushen
make deploy    # bauen, pushen, Server pullen, migrieren, neu starten
make remote    # nur Server-Update (Image schon auf GHCR)
```

Variablen: `DEPLOY_SSH` (Default `server_np`), `DEPLOY_DIR`, `IMAGE_REPO`,
`DEPLOY_ALLOW_DIRTY=1`. Das Deploy-Skript legt `.last-known-good-image` an und
rollt bei fehlerhaftem Start auf das vorherige Image zurück.

## Manuell auf dem Server

```bash
cd /opt/apps/dsa-abenteuerfinder
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Öffentliche Dateien neu aus dem Image füllen:

```bash
PUBLIC_INIT_FORCE=1 docker compose -f docker-compose.prod.yml run --rm public-init
docker compose -f docker-compose.prod.yml up -d
```

## Debug

```bash
docker logs dsa-abenteuerfinder-public-init
docker exec dsa-abenteuerfinder-web nginx -T 2>/dev/null | grep -n fastcgi_pass
docker exec dsa-abenteuerfinder-app php artisan about
curl -sI https://dsa-abenteuerfinder.rath-ulrich.de/ | grep -iE 'content-security|x-frame|referrer|permissions'
```
