#!/usr/bin/env bash
set -Eeuo pipefail

repository_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$repository_root"

if [[ ! -f .env ]]; then
  echo "Missing .env. Copy .env.example and configure the server first." >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source .env
set +a

timestamp="$(date +%Y%m%d-%H%M%S)"
backup_dir="backups/$timestamp"
mkdir -p "$backup_dir"

docker compose exec -T db mariadb-dump \
  --single-transaction \
  --add-drop-table \
  -u"$WORDPRESS_DB_USER" \
  -p"$WORDPRESS_DB_PASSWORD" \
  "$WORDPRESS_DB_NAME" > "$backup_dir/database.sql"

tar -C data -I 'zstd -19 -T0' -cf "$backup_dir/uploads.tar.zst" uploads
cp .env.example "$backup_dir/.env.example"
printf 'Backup created: %s\n' "$backup_dir"