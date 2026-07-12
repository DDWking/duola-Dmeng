#!/usr/bin/env bash
set -euo pipefail

if [[ $# -ne 1 || ! -f "$1" ]]; then
  echo "Usage: $0 /path/to/duola-content-export.zip" >&2
  exit 2
fi

package_path=$(realpath "$1")
project_dir=$(cd "$(dirname "$0")/.." && pwd)
test_name="duola-migration-test"
test_dir="/tmp/$test_name"
test_database="duola_migration_test"

cd "$project_dir"

available_kb=$(df --output=avail -k /tmp | tail -1 | tr -d ' ')
if [[ "$available_kb" -lt 2500000 ]]; then
  echo "Insufficient disk space: ${available_kb} KB available." >&2
  exit 3
fi

root_password=$(docker compose exec -T db printenv MARIADB_ROOT_PASSWORD | tr -d '\r')
db_user=$(docker compose exec -T db printenv MARIADB_USER | tr -d '\r')
db_password=$(docker compose exec -T db printenv MARIADB_PASSWORD | tr -d '\r')

cleanup() {
  docker rm -f "$test_name" >/dev/null 2>&1 || true
  if [[ -d "$test_dir" ]]; then
    docker run --rm -v "$test_dir:/cleanup" duola-pocket-wordpress:local \
      sh -c 'rm -rf /cleanup/*' >/dev/null 2>&1 || true
    rm -rf "$test_dir" || true
  fi
  docker compose exec -T db mariadb -uroot -p"$root_password" \
    -e "DROP DATABASE IF EXISTS $test_database;" >/dev/null 2>&1 || true
}
trap cleanup EXIT

cleanup
mkdir -p "$test_dir/uploads"

docker compose exec -T db mariadb -uroot -p"$root_password" -e \
  "CREATE DATABASE $test_database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON $test_database.* TO '$db_user'@'%'; FLUSH PRIVILEGES;"

docker run -d --name "$test_name" \
  --network duola-pocket-network \
  -e WORDPRESS_DB_HOST=duola-mariadb:3306 \
  -e WORDPRESS_DB_NAME="$test_database" \
  -e WORDPRESS_DB_USER="$db_user" \
  -e WORDPRESS_DB_PASSWORD="$db_password" \
  -e WORDPRESS_CONFIG_EXTRA="define('WP_ENVIRONMENT_TYPE', 'development');" \
  -v "$project_dir/wordpress/wp-content/plugins/duola-albums:/var/www/html/wp-content/plugins/duola-albums:ro" \
  -v "$project_dir/wordpress/wp-content/themes/duola-pocket:/var/www/html/wp-content/themes/duola-pocket:ro" \
  -v "$test_dir/uploads:/var/www/html/wp-content/uploads" \
  duola-pocket-wordpress:local >/dev/null

for _ in $(seq 1 30); do
  if docker exec "$test_name" test -f /var/www/html/wp-load.php; then
    break
  fi
  sleep 1
done

docker cp "$package_path" "$test_name:/tmp/import.zip"

docker exec -i "$test_name" php <<'PHP'
<?php
$_SERVER['HTTP_HOST'] = 'migration-test.invalid';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-post.php';
define('WP_INSTALLING', true);
require '/var/www/html/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
wp_install('Migration Test', 'admin', 'test@example.invalid', true, '', 'migration-test-password');
wp_installing(false);
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$result = activate_plugin('duola-albums/duola-albums.php');
if (is_wp_error($result)) {
    fwrite(STDERR, $result->get_error_message());
    exit(4);
}
foreach (get_posts(['post_type' => 'post', 'post_status' => 'any', 'numberposts' => -1]) as $post) {
    wp_delete_post($post->ID, true);
}
echo "WordPress test site installed.\n";
PHP

run_import() {
  docker exec -i "$test_name" php <<'PHP'
<?php
$_SERVER['HTTP_HOST'] = 'migration-test.invalid';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-post.php';
require '/var/www/html/wp-load.php';
$admins = get_users(['role' => 'administrator', 'number' => 1]);
if (!$admins) {
    exit(5);
}
wp_set_current_user($admins[0]->ID);
$_REQUEST['_wpnonce'] = wp_create_nonce('duola_import_content');
$_POST['_wpnonce'] = $_REQUEST['_wpnonce'];
$_FILES['duola_package'] = [
    'name' => 'duola-content-export.zip',
    'type' => 'application/zip',
    'tmp_name' => '/tmp/import.zip',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize('/tmp/import.zip'),
];
duola_migration_import_content();
PHP
}

echo "Running first import..."
run_import
echo "Running idempotence import..."
run_import

docker exec -i "$test_name" php <<'PHP'
<?php
$_SERVER['HTTP_HOST'] = 'migration-test.invalid';
$_SERVER['REQUEST_URI'] = '/wp-admin/admin-post.php';
require '/var/www/html/wp-load.php';

$zip = new ZipArchive();
if (true !== $zip->open('/tmp/import.zip')) {
    exit(6);
}
$manifest = json_decode($zip->getFromName('manifest.json'), true);
$zip->close();

$statuses = ['publish', 'draft', 'pending', 'private', 'future'];
$media = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'numberposts' => -1]);
$posts = get_posts(['post_type' => 'post', 'post_status' => $statuses, 'numberposts' => -1]);
$albums = get_posts(['post_type' => 'album', 'post_status' => $statuses, 'numberposts' => -1]);
$media_by_uuid = [];
$missing_files = [];

foreach ($media as $attachment) {
    $uuid = (string) get_post_meta($attachment->ID, '_duola_migration_uuid', true);
    if ($uuid) {
        $media_by_uuid[$uuid] = (int) $attachment->ID;
    }
    $file = get_attached_file($attachment->ID);
    if (!$file || !is_file($file)) {
        $missing_files[] = $uuid;
    }
}

$settings_mismatches = [];
$order_mismatches = [];
foreach ((array) ($manifest['albums'] ?? []) as $entry) {
    $album_id = duola_migration_find_existing('album', (string) ($entry['uuid'] ?? ''));
    $actual_photo_ids = get_post_meta($album_id, '_duola_album_photos', true);
    $expected_photo_ids = [];
    foreach ((array) ($entry['photo_media_uuids'] ?? []) as $uuid) {
        if (isset($media_by_uuid[$uuid])) {
            $expected_photo_ids[] = $media_by_uuid[$uuid];
        }
    }
    if (array_values((array) $actual_photo_ids) !== array_values($expected_photo_ids)) {
        $order_mismatches[] = $entry['title'] ?? '';
    }

    $expected_settings = [];
    foreach ((array) ($entry['photo_settings'] ?? []) as $uuid => $settings) {
        if (isset($media_by_uuid[$uuid])) {
            $expected_settings[(string) $media_by_uuid[$uuid]] = $settings;
        }
    }
    $expected_settings = duola_albums_sanitize_photo_settings($expected_settings);
    if (duola_albums_get_all_photo_settings($album_id) !== $expected_settings) {
        $settings_mismatches[] = $entry['title'] ?? '';
    }
}

$old_urls = [];
foreach ($posts as $post) {
    if (str_contains($post->post_content, '159.75.236.90')) {
        $old_urls[] = $post->post_title;
    }
}

$result = [
    'media' => count($media),
    'posts' => count($posts),
    'albums' => count($albums),
    'expected_media' => count((array) ($manifest['media'] ?? [])),
    'expected_posts' => count((array) ($manifest['posts'] ?? [])),
    'expected_albums' => count((array) ($manifest['albums'] ?? [])),
    'missing_files' => $missing_files,
    'photo_order_mismatches' => $order_mismatches,
    'photo_settings_mismatches' => $settings_mismatches,
    'old_urls_in_posts' => $old_urls,
    'site_name' => get_option('blogname'),
];
echo wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

$valid = $result['media'] === $result['expected_media']
    && $result['posts'] === $result['expected_posts']
    && $result['albums'] === $result['expected_albums']
    && !$missing_files
    && !$order_mismatches
    && !$settings_mismatches
    && !$old_urls;
exit($valid ? 0 : 7);
PHP

echo "Migration smoke test passed."
