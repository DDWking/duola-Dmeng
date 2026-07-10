#!/bin/sh
set -eu

mkdir -p /var/www/html/wp-content/uploads
chown -R www-data:www-data /var/www/html/wp-content/uploads

exec docker-entrypoint.sh "$@"
