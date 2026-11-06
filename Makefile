# SPDX-FileCopyrightText: 2026 Christian Rath-Ulrich
#
# SPDX-License-Identifier: GPL-3.0-only

IMAGE_REPO ?= ghcr.io/rathch/dsa-abenteuerfinder
DEPLOY_SSH ?= server_np
DEPLOY_DIR ?= /opt/apps/dsa-abenteuerfinder

export IMAGE_REPO DEPLOY_SSH DEPLOY_DIR

.PHONY: help build push deploy remote

help: ; @printf '%s\n' 'make build    Image lokal bauen' 'make push     bauen und nach GHCR pushen' 'make deploy   bauen, pushen, auf dem Server pullen und App neu starten' 'make remote   nur Server: pull und recreate' '' 'DEPLOY_SSH  SSH-Ziel (Default: server_np)' 'DEPLOY_DIR  Verzeichnis auf dem Server (Default: /opt/apps/dsa-abenteuerfinder)' 'IMAGE_REPO  Default ghcr.io/rathch/dsa-abenteuerfinder' 'DEPLOY_ALLOW_DIRTY=1  Deploy trotz uncommitteter getrackter Dateien'

build: ; ./deploy/build-push.sh

push: ; ./deploy/build-push.sh --push

deploy: ; ./deploy/deploy.sh

remote: ; ./deploy/deploy.sh --remote-only
