#!/usr/bin/env bash
set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repository_root"

git pull --ff-only origin main
docker compose up -d --build --remove-orphans
docker compose ps