#!/usr/bin/env bash
set -Eeuo pipefail

if [[ $# -ne 2 ]]; then
  echo "Usage: ./scripts/restore.sh /path/to/database.sql /path/to/uploads.tar.zst" >&2
  exit 1
fi

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

database_file="$1"
uploads_file="$2"

[[ -f "$database_file" ]] || { echo "Database file not found: $database_file" >&2; exit 1; }
[[ -f "$uploads_file" ]] || { echo "Uploads archive not found: $uploads_file" >&2; exit 1; }

read -r -p "This imports a database and replaces files under data/uploads. Continue? [y/N] " answer
[[ "$answer" =~ ^[Yy]$ ]] || exit 0

docker compose up -d db
until docker compose exec -T db mariadb-admin ping -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" --silent; do sleep 2; done

docker compose exec -T db mariadb -u"$WORDPRESS_DB_USER" -p"$WORDPRESS_DB_PASSWORD" "$WORDPRESS_DB_NAME" < "$database_file"
rm -rf data/uploads
mkdir -p data
tar -C data -I zstd -xf "$uploads_file"
docker compose up -d --build
printf 'Restore completed. Check the website and /wp-admin before changing DNS.\n'