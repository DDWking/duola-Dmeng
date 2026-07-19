<?php

if (!defined('ABSPATH')) {
    exit;
}

const DUOLA_VOLLEYBALL_DB_VERSION = '1';

function duola_volleyball_scores_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'duola_volleyball_scores';
}

function duola_volleyball_install(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = duola_volleyball_scores_table();
    $charset_collate = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        migration_uuid char(36) NOT NULL,
        nickname varchar(40) NOT NULL DEFAULT '',
        score int(10) unsigned NOT NULL DEFAULT 0,
        victory tinyint(1) unsigned NOT NULL DEFAULT 0,
        player_sets tinyint(3) unsigned NOT NULL DEFAULT 0,
        cpu_sets tinyint(3) unsigned NOT NULL DEFAULT 0,
        player_score smallint(5) unsigned NOT NULL DEFAULT 0,
        cpu_score smallint(5) unsigned NOT NULL DEFAULT 0,
        spikes smallint(5) unsigned NOT NULL DEFAULT 0,
        saves smallint(5) unsigned NOT NULL DEFAULT 0,
        blocks smallint(5) unsigned NOT NULL DEFAULT 0,
        perfect_touches smallint(5) unsigned NOT NULL DEFAULT 0,
        max_combo smallint(5) unsigned NOT NULL DEFAULT 0,
        visitor_hash char(64) NOT NULL DEFAULT '',
        ip_hash char(64) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY migration_uuid (migration_uuid),
        KEY score_created (score, created_at),
        KEY visitor_created (visitor_hash, created_at),
        KEY ip_created (ip_hash, created_at)
    ) {$charset_collate};");

    update_option('duola_volleyball_db_version', DUOLA_VOLLEYBALL_DB_VERSION, false);
}

function duola_volleyball_maybe_install(): void
{
    if (DUOLA_VOLLEYBALL_DB_VERSION !== get_option('duola_volleyball_db_version')) {
        duola_volleyball_install();
    }
}
add_action('init', 'duola_volleyball_maybe_install', 6);

function duola_volleyball_visitor_hash(): string
{
    if (function_exists('duola_guestbook_visitor_hash')) {
        return duola_guestbook_visitor_hash();
    }
    return hash_hmac('sha256', duola_guestbook_client_ip(), wp_salt('nonce'));
}

function duola_volleyball_score(array $stats): int
{
    $victory = (int) ($stats['player_sets'] ?? 0) > (int) ($stats['cpu_sets'] ?? 0);
    $score = $victory ? 1200 : 250;
    $score += min(2, absint($stats['player_sets'] ?? 0)) * 260;
    $score += min(99, absint($stats['spikes'] ?? 0)) * 35;
    $score += min(99, absint($stats['saves'] ?? 0)) * 30;
    $score += min(99, absint($stats['blocks'] ?? 0)) * 45;
    $score += min(150, absint($stats['perfect_touches'] ?? 0)) * 30;
    $score += min(30, absint($stats['max_combo'] ?? 0)) * 80;
    return min(99999, $score);
}

function duola_volleyball_public_leaderboard(int $limit = 8): array
{
    duola_volleyball_maybe_install();
    global $wpdb;
    $table = duola_volleyball_scores_table();
    $limit = max(1, min(20, $limit));
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY score DESC, created_at ASC LIMIT 200");
    $entries = [];
    $seen = [];

    foreach ($rows as $row) {
        $identity = $row->visitor_hash ?: $row->migration_uuid;
        if (isset($seen[$identity])) {
            continue;
        }
        $seen[$identity] = true;
        $entries[] = [
            'rank' => count($entries) + 1,
            'nickname' => $row->nickname ?: '匿名球员',
            'score' => (int) $row->score,
            'max_combo' => (int) $row->max_combo,
            'date' => get_date_from_gmt((string) $row->created_at, 'm-d'),
        ];
        if (count($entries) >= $limit) {
            break;
        }
    }
    return $entries;
}

function duola_volleyball_register_rest_routes(): void
{
    register_rest_route('duola/v1', '/volleyball/leaderboard', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => static function (WP_REST_Request $request): array {
            return ['entries' => duola_volleyball_public_leaderboard(absint($request->get_param('limit') ?: 8))];
        },
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('duola/v1', '/volleyball/session', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'duola_volleyball_rest_start_session',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('duola/v1', '/volleyball/scores', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'duola_volleyball_rest_submit_score',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'duola_volleyball_register_rest_routes');

function duola_volleyball_rest_start_session()
{
    $ip_hash = duola_guestbook_ip_hash();
    $rate_key = 'duola_volleyball_session_' . substr($ip_hash, 0, 40);
    if (get_transient($rate_key)) {
        return new WP_Error('duola_volleyball_session_rate', __('请稍后再开始新对局。', 'duola-albums'), ['status' => 429]);
    }

    $token = bin2hex(random_bytes(24));
    $token_hash = hash_hmac('sha256', $token, wp_salt('nonce'));
    set_transient('duola_volleyball_run_' . $token_hash, [
        'started_at' => time(),
        'ip_hash' => $ip_hash,
    ], 2 * HOUR_IN_SECONDS);
    set_transient($rate_key, 1, 3);

    return rest_ensure_response([
        'token' => $token,
        'nonce' => wp_create_nonce('duola_volleyball_submit'),
    ]);
}

function duola_volleyball_rest_submit_score(WP_REST_Request $request)
{
    duola_volleyball_maybe_install();
    $nonce = sanitize_text_field($request->get_header('X-Duola-Volleyball-Nonce'));
    if (!wp_verify_nonce($nonce, 'duola_volleyball_submit')) {
        return new WP_Error('duola_volleyball_nonce', __('积分会话已过期，请重新开始一局。', 'duola-albums'), ['status' => 403]);
    }
    if ('' !== trim((string) $request->get_param('website'))) {
        return new WP_Error('duola_volleyball_rejected', __('积分提交被拒绝。', 'duola-albums'), ['status' => 400]);
    }

    $token = sanitize_text_field((string) $request->get_param('token'));
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return new WP_Error('duola_volleyball_token', __('无效的对局凭证。', 'duola-albums'), ['status' => 403]);
    }
    $token_hash = hash_hmac('sha256', $token, wp_salt('nonce'));
    $transient_key = 'duola_volleyball_run_' . $token_hash;
    $session = get_transient($transient_key);
    delete_transient($transient_key);
    $elapsed = is_array($session) ? time() - absint($session['started_at'] ?? 0) : 0;
    if (!is_array($session) || !hash_equals((string) ($session['ip_hash'] ?? ''), duola_guestbook_ip_hash()) || $elapsed < 20 || $elapsed > 7200) {
        return new WP_Error('duola_volleyball_session', __('这局比赛无法登记，请重新开始。', 'duola-albums'), ['status' => 403]);
    }

    $stats = [];
    foreach (['player_sets', 'cpu_sets', 'player_score', 'cpu_score', 'spikes', 'saves', 'blocks', 'perfect_touches', 'max_combo'] as $key) {
        $stats[$key] = absint($request->get_param($key));
    }
    if (2 !== max($stats['player_sets'], $stats['cpu_sets']) || $stats['player_sets'] === $stats['cpu_sets']) {
        return new WP_Error('duola_volleyball_result', __('只登记完整结束的比赛。', 'duola-albums'), ['status' => 400]);
    }
    if ($stats['player_score'] > 50 || $stats['cpu_score'] > 50 || $stats['spikes'] > 200 || $stats['saves'] > 200 || $stats['blocks'] > 200 || $stats['perfect_touches'] > 300 || $stats['max_combo'] > 50) {
        return new WP_Error('duola_volleyball_bounds', __('比赛数据超出合理范围。', 'duola-albums'), ['status' => 400]);
    }

    global $wpdb;
    $table = duola_volleyball_scores_table();
    $ip_hash = duola_guestbook_ip_hash();
    $visitor_hash = duola_volleyball_visitor_hash();
    $minute_ago = gmdate('Y-m-d H:i:s', time() - MINUTE_IN_SECONDS);
    $hour_ago = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);
    $recent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND created_at >= %s", $ip_hash, $minute_ago));
    $hourly = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND created_at >= %s", $ip_hash, $hour_ago));
    if ($recent >= 2 || $hourly >= 20) {
        return new WP_Error('duola_volleyball_rate', __('积分提交太频繁，请稍后再试。', 'duola-albums'), ['status' => 429]);
    }

    $nickname = mb_substr(trim(sanitize_text_field((string) $request->get_param('nickname'))), 0, 12);
    if ('' === $nickname) {
        $nickname = '匿名球员';
    }
    $score = duola_volleyball_score($stats);
    $data = array_merge($stats, [
        'migration_uuid' => wp_generate_uuid4(),
        'nickname' => $nickname,
        'score' => $score,
        'victory' => $stats['player_sets'] > $stats['cpu_sets'] ? 1 : 0,
        'visitor_hash' => $visitor_hash,
        'ip_hash' => $ip_hash,
        'created_at' => current_time('mysql', true),
    ]);
    if (false === $wpdb->insert($table, $data)) {
        return new WP_Error('duola_volleyball_store', __('积分暂时无法保存。', 'duola-albums'), ['status' => 500]);
    }

    return rest_ensure_response([
        'score' => $score,
        'entries' => duola_volleyball_public_leaderboard(8),
    ]);
}

function duola_volleyball_export(): array
{
    duola_volleyball_maybe_install();
    global $wpdb;
    $table = duola_volleyball_scores_table();
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC");

    return array_map(static function (object $row): array {
        return [
            'uuid' => $row->migration_uuid,
            'nickname' => $row->nickname,
            'score' => (int) $row->score,
            'victory' => (bool) $row->victory,
            'player_sets' => (int) $row->player_sets,
            'cpu_sets' => (int) $row->cpu_sets,
            'player_score' => (int) $row->player_score,
            'cpu_score' => (int) $row->cpu_score,
            'spikes' => (int) $row->spikes,
            'saves' => (int) $row->saves,
            'blocks' => (int) $row->blocks,
            'perfect_touches' => (int) $row->perfect_touches,
            'max_combo' => (int) $row->max_combo,
            'visitor_key' => $row->visitor_hash,
            'created_at' => $row->created_at,
        ];
    }, $rows);
}

function duola_volleyball_import(array $entries): int
{
    duola_volleyball_maybe_install();
    global $wpdb;
    $table = duola_volleyball_scores_table();
    $count = 0;

    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $uuid = sanitize_text_field($entry['uuid'] ?? '');
        if (!wp_is_uuid($uuid)) {
            continue;
        }
        $stats = [];
        foreach (['player_sets', 'cpu_sets', 'player_score', 'cpu_score', 'spikes', 'saves', 'blocks', 'perfect_touches', 'max_combo'] as $key) {
            $stats[$key] = absint($entry[$key] ?? 0);
        }
        $visitor_key = strtolower(sanitize_text_field($entry['visitor_key'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $visitor_key)) {
            $visitor_key = '';
        }
        $data = array_merge($stats, [
            'migration_uuid' => $uuid,
            'nickname' => mb_substr(trim(sanitize_text_field($entry['nickname'] ?? '')), 0, 12) ?: '匿名球员',
            'score' => min(99999, absint($entry['score'] ?? duola_volleyball_score($stats))),
            'victory' => !empty($entry['victory']) ? 1 : 0,
            'visitor_hash' => $visitor_key,
            'ip_hash' => '',
            'created_at' => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($entry['created_at'] ?? '')) ? $entry['created_at'] : current_time('mysql', true),
        ]);
        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE migration_uuid = %s", $uuid));
        if ($existing_id) {
            $wpdb->update($table, $data, ['id' => $existing_id]);
        } else {
            $wpdb->insert($table, $data);
        }
        $count++;
    }
    return $count;
}
