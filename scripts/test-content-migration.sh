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

docker exec -i "$test_name" php <<'PHP'
<?php
$_SERVER['HTTP_HOST'] = 'migration-test.invalid';
$_SERVER['REQUEST_URI'] = '/wp-json/duola/v1/messages';
require '/var/www/html/wp-load.php';

function assert_guestbook(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(8);
    }
}

duola_guestbook_install();
duola_volleyball_install();
$nonce = wp_create_nonce('duola_wall_submit');
$_SERVER['REMOTE_ADDR'] = '172.18.0.3';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';
assert_guestbook('1.1.1.1' === duola_guestbook_client_ip(), 'Trusted proxy IP resolution failed.');
$_SERVER['REMOTE_ADDR'] = '8.8.8.8';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9';
assert_guestbook('8.8.8.8' === duola_guestbook_client_ip(), 'Untrusted forwarded IP was accepted.');

$_SERVER['REMOTE_ADDR'] = '172.18.0.3';
$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';
$request = new WP_REST_Request('POST', '/duola/v1/messages');
$request->set_header('X-Duola-Wall-Nonce', $nonce);
$request->set_body_params(['nickname' => '', 'message' => 'guestbook smoke test', 'website' => '', 'started_at' => time() - 3]);
$created = duola_guestbook_rest_create_message($request);
assert_guestbook($created instanceof WP_REST_Response, 'Clean message was not accepted.');
$created_data = $created->get_data();
assert_guestbook('publish' === $created_data['status'], 'Clean message was not published.');
assert_guestbook('anonymous' === $created_data['message']['nickname'], 'Blank nickname did not become anonymous.');

$limited = duola_guestbook_rest_create_message($request);
assert_guestbook(is_wp_error($limited) && 429 === $limited->get_error_data()['status'], 'One-minute rate limit failed.');

$_SERVER['HTTP_X_FORWARDED_FOR'] = '9.9.9.9';
$url_request = new WP_REST_Request('POST', '/duola/v1/messages');
$url_request->set_header('X-Duola-Wall-Nonce', $nonce);
$url_request->set_body_params(['nickname' => 'link', 'message' => 'visit example.dev/path', 'website' => '', 'started_at' => time() - 3]);
$pending = duola_guestbook_rest_create_message($url_request);
assert_guestbook($pending instanceof WP_REST_Response && 'pending' === $pending->get_data()['status'], 'URL message did not enter review.');

$_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1';
unset($_COOKIE['duola_wall_visitor']);
$message_id = (int) $created_data['message']['id'];
$like_request = new WP_REST_Request('POST', '/duola/v1/messages/' . $message_id . '/like');
$like_request->set_header('X-Duola-Wall-Nonce', $nonce);
$like_request->set_url_params(['id' => $message_id]);
$liked = duola_guestbook_rest_toggle_like($like_request);
$unliked = duola_guestbook_rest_toggle_like($like_request);
assert_guestbook(true === $liked['liked'] && 1 === $liked['likes'], 'First +1 did not increment.');
assert_guestbook(false === $unliked['liked'] && 0 === $unliked['likes'], 'Second +1 did not reverse.');

$_SERVER['HTTP_X_FORWARDED_FOR'] = '2.2.2.2';
$session_response = duola_volleyball_rest_start_session();
assert_guestbook($session_response instanceof WP_REST_Response, 'Volleyball score session was not created.');
$session_data = $session_response->get_data();
$run_key = 'duola_volleyball_run_' . hash_hmac('sha256', $session_data['token'], wp_salt('nonce'));
$run_data = get_transient($run_key);
$run_data['started_at'] = time() - 30;
set_transient($run_key, $run_data, 2 * HOUR_IN_SECONDS);
$score_request = new WP_REST_Request('POST', '/duola/v1/volleyball/scores');
$score_request->set_header('X-Duola-Volleyball-Nonce', $session_data['nonce']);
$score_request->set_body_params([
    'token' => $session_data['token'],
    'nickname' => 'smoke',
    'website' => '',
    'player_sets' => 2,
    'cpu_sets' => 0,
    'player_score' => 11,
    'cpu_score' => 7,
    'spikes' => 3,
    'saves' => 2,
    'blocks' => 1,
    'perfect_touches' => 4,
    'max_combo' => 3,
]);
$score_response = duola_volleyball_rest_submit_score($score_request);
assert_guestbook($score_response instanceof WP_REST_Response, 'Valid volleyball score was not accepted.');
assert_guestbook(2290 === $score_response->get_data()['score'], 'Volleyball score was not calculated on the server.');
$score_export = duola_volleyball_export();
assert_guestbook(1 === count($score_export) && preg_match('/^[a-f0-9]{64}$/', $score_export[0]['visitor_key']), 'Volleyball visitor key was not included in the migration export.');

global $wpdb;
$wpdb->query('TRUNCATE TABLE ' . duola_guestbook_likes_table());
$wpdb->query('TRUNCATE TABLE ' . duola_guestbook_messages_table());
$wpdb->query('TRUNCATE TABLE ' . duola_volleyball_scores_table());

$zip = new ZipArchive();
assert_guestbook(true === $zip->open('/tmp/import.zip'), 'Could not open migration package for guestbook fixture.');
$manifest = json_decode($zip->getFromName('manifest.json'), true);
$manifest['guestbook'] = [
    [
        'uuid' => '10000000-0000-4000-8000-000000000001',
        'parent_uuid' => '',
        'nickname' => 'alice',
        'message' => 'hello from migration',
        'status' => 'publish',
        'pinned' => true,
        'likes' => 7,
        'created_at' => '2026-07-13 10:00:00',
    ],
    [
        'uuid' => '10000000-0000-4000-8000-000000000002',
        'parent_uuid' => '10000000-0000-4000-8000-000000000001',
        'nickname' => 'ddw',
        'message' => 'welcome',
        'status' => 'publish',
        'pinned' => false,
        'likes' => 0,
        'created_at' => '2026-07-13 10:01:00',
    ],
];
$manifest['leaderboard'] = [
    [
        'uuid' => '20000000-0000-4000-8000-000000000001',
        'nickname' => 'ace',
        'score' => 2290,
        'victory' => true,
        'player_sets' => 2,
        'cpu_sets' => 0,
        'player_score' => 11,
        'cpu_score' => 7,
        'spikes' => 3,
        'saves' => 2,
        'blocks' => 1,
        'perfect_touches' => 4,
        'max_combo' => 3,
        'visitor_key' => str_repeat('a', 64),
        'created_at' => '2026-07-20 00:00:00',
    ],
    [
        'uuid' => '20000000-0000-4000-8000-000000000002',
        'nickname' => 'ace-old',
        'score' => 1800,
        'victory' => false,
        'player_sets' => 0,
        'cpu_sets' => 2,
        'player_score' => 7,
        'cpu_score' => 11,
        'spikes' => 2,
        'saves' => 1,
        'blocks' => 0,
        'perfect_touches' => 2,
        'max_combo' => 2,
        'visitor_key' => str_repeat('a', 64),
        'created_at' => '2026-07-19 00:00:00',
    ],
];
$zip->deleteName('manifest.json');
$zip->addFromString('manifest.json', wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
$zip->close();
echo "Guestbook behavior smoke test passed.\n";
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
$guestbook_table = duola_guestbook_messages_table();
$guestbook_rows = $wpdb->get_results("SELECT * FROM {$guestbook_table} ORDER BY id ASC");
$leaderboard_table = duola_volleyball_scores_table();
$leaderboard_rows = $wpdb->get_results("SELECT * FROM {$leaderboard_table} ORDER BY id ASC");
$public_leaderboard = duola_volleyball_public_leaderboard(8);
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
    'guestbook' => count($guestbook_rows),
    'expected_guestbook' => count((array) ($manifest['guestbook'] ?? [])),
    'leaderboard' => count($leaderboard_rows),
    'expected_leaderboard' => count((array) ($manifest['leaderboard'] ?? [])),
    'missing_files' => $missing_files,
    'photo_order_mismatches' => $order_mismatches,
    'photo_settings_mismatches' => $settings_mismatches,
    'old_urls_in_posts' => $old_urls,
    'site_name' => get_option('blogname'),
];

$guestbook_by_uuid = [];
foreach ($guestbook_rows as $row) {
    $guestbook_by_uuid[$row->migration_uuid] = $row;
}
$guestbook_parent = $guestbook_by_uuid['10000000-0000-4000-8000-000000000001'] ?? null;
$guestbook_reply = $guestbook_by_uuid['10000000-0000-4000-8000-000000000002'] ?? null;
$result['guestbook_relationship_valid'] = $guestbook_parent
    && $guestbook_reply
    && (int) $guestbook_reply->parent_id === (int) $guestbook_parent->id
    && 7 === (int) $guestbook_parent->like_count;
$result['leaderboard_valid'] = 2 === count($leaderboard_rows)
    && 'ace' === $leaderboard_rows[0]->nickname
    && 2290 === (int) $leaderboard_rows[0]->score
    && 1 === count($public_leaderboard)
    && 'ace' === $public_leaderboard[0]['nickname']
    && 2290 === $public_leaderboard[0]['score'];
echo wp_json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;

$valid = $result['media'] === $result['expected_media']
    && $result['posts'] === $result['expected_posts']
    && $result['albums'] === $result['expected_albums']
    && $result['guestbook'] === $result['expected_guestbook']
    && $result['guestbook_relationship_valid']
    && $result['leaderboard'] === $result['expected_leaderboard']
    && $result['leaderboard_valid']
    && !$missing_files
    && !$order_mismatches
    && !$settings_mismatches
    && !$old_urls;
exit($valid ? 0 : 7);
PHP

echo "Migration smoke test passed."
