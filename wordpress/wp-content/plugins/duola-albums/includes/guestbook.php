<?php

if (!defined('ABSPATH')) {
    exit;
}

const DUOLA_GUESTBOOK_DB_VERSION = '1';

function duola_guestbook_messages_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'duola_guestbook_messages';
}

function duola_guestbook_likes_table(): string
{
    global $wpdb;
    return $wpdb->prefix . 'duola_guestbook_likes';
}

function duola_guestbook_install(): void
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $messages = duola_guestbook_messages_table();
    $likes = duola_guestbook_likes_table();
    $charset_collate = $wpdb->get_charset_collate();

    dbDelta("CREATE TABLE {$messages} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        migration_uuid char(36) NOT NULL,
        parent_id bigint(20) unsigned NOT NULL DEFAULT 0,
        nickname varchar(40) NOT NULL DEFAULT '',
        message text NOT NULL,
        status varchar(20) NOT NULL DEFAULT 'publish',
        pinned tinyint(1) unsigned NOT NULL DEFAULT 0,
        like_count bigint(20) unsigned NOT NULL DEFAULT 0,
        ip_hash char(64) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY migration_uuid (migration_uuid),
        KEY parent_id (parent_id),
        KEY status_created (status, created_at),
        KEY ip_hash_created (ip_hash, created_at)
    ) {$charset_collate};");

    dbDelta("CREATE TABLE {$likes} (
        message_id bigint(20) unsigned NOT NULL,
        visitor_hash char(64) NOT NULL,
        created_at datetime NOT NULL,
        PRIMARY KEY  (message_id, visitor_hash),
        KEY created_at (created_at)
    ) {$charset_collate};");

    update_option('duola_guestbook_db_version', DUOLA_GUESTBOOK_DB_VERSION, false);
}

function duola_guestbook_maybe_install(): void
{
    if (DUOLA_GUESTBOOK_DB_VERSION !== get_option('duola_guestbook_db_version')) {
        duola_guestbook_install();
    }
}
add_action('init', 'duola_guestbook_maybe_install', 5);

function duola_guestbook_client_ip(): string
{
    $remote = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    $candidate = $remote;
    $is_private_proxy = filter_var($remote, FILTER_VALIDATE_IP)
        && false === filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

    if ($is_private_proxy) {
        $forwarded = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwarded) {
            $candidate = trim(explode(',', $forwarded)[0]);
        }
    }

    return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : 'unknown';
}

function duola_guestbook_ip_hash(): string
{
    return hash_hmac('sha256', duola_guestbook_client_ip(), wp_salt('auth'));
}

function duola_guestbook_visitor_hash(): string
{
    $cookie_name = 'duola_wall_visitor';
    $visitor_id = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name] ?? ''));
    if (!preg_match('/^[a-zA-Z0-9]{32}$/', $visitor_id)) {
        $visitor_id = wp_generate_password(32, false, false);
        $cookie_options = [
            'expires' => time() + YEAR_IN_SECONDS,
            'path' => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
            'secure' => is_ssl(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (defined('COOKIE_DOMAIN') && COOKIE_DOMAIN) {
            $cookie_options['domain'] = COOKIE_DOMAIN;
        }
        setcookie($cookie_name, $visitor_id, $cookie_options);
        $_COOKIE[$cookie_name] = $visitor_id;
    }
    return hash_hmac('sha256', $visitor_id . '|' . duola_guestbook_ip_hash(), wp_salt('nonce'));
}

function duola_guestbook_has_url(string $message): bool
{
    return (bool) preg_match('/(?:https?:\/\/|www\.|\b(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\b)/iu', $message);
}

function duola_guestbook_is_blocked(string $ip_hash): bool
{
    $blocked = (array) get_option('duola_guestbook_blocked_hashes', []);
    return isset($blocked[$ip_hash]);
}

function duola_guestbook_rate_limit(string $ip_hash)
{
    global $wpdb;
    $table = duola_guestbook_messages_table();
    $minute_ago = gmdate('Y-m-d H:i:s', time() - MINUTE_IN_SECONDS);
    $hour_ago = gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS);
    $recent = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND created_at >= %s", $ip_hash, $minute_ago));
    $hourly = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE ip_hash = %s AND created_at >= %s", $ip_hash, $hour_ago));

    if ($recent >= 1) {
        return new WP_Error('duola_wall_slow_down', __('Wait one minute before posting again.', 'duola-albums'), ['status' => 429]);
    }
    if ($hourly >= 5) {
        return new WP_Error('duola_wall_hour_limit', __('Too many messages. Try again later.', 'duola-albums'), ['status' => 429]);
    }
    return true;
}

function duola_guestbook_format_row(object $row, array $replies = []): array
{
    return [
        'id' => (int) $row->id,
        'number' => str_pad((string) $row->id, 4, '0', STR_PAD_LEFT),
        'nickname' => $row->nickname ?: 'anonymous',
        'message' => (string) $row->message,
        'date' => get_date_from_gmt((string) $row->created_at, 'Y-m-d H:i'),
        'pinned' => (bool) $row->pinned,
        'likes' => (int) $row->like_count,
        'replies' => $replies,
    ];
}

function duola_guestbook_public_messages(int $limit = 50): array
{
    global $wpdb;
    $table = duola_guestbook_messages_table();
    $limit = max(1, min(100, $limit));
    $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE parent_id = 0 AND status = 'publish' ORDER BY pinned DESC, created_at DESC LIMIT %d", $limit));
    if (!$rows) {
        return [];
    }

    $ids = array_map(static fn(object $row): int => (int) $row->id, $rows);
    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $reply_rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE parent_id IN ({$placeholders}) AND status = 'publish' ORDER BY created_at ASC", ...$ids));
    $replies = [];
    foreach ($reply_rows as $reply) {
        $replies[(int) $reply->parent_id][] = duola_guestbook_format_row($reply);
    }

    return array_map(static fn(object $row): array => duola_guestbook_format_row($row, $replies[(int) $row->id] ?? []), $rows);
}

function duola_guestbook_register_rest_routes(): void
{
    register_rest_route('duola/v1', '/wall-token', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => static fn(): array => ['nonce' => wp_create_nonce('duola_wall_submit')],
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('duola/v1', '/messages', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'duola_guestbook_rest_create_message',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('duola/v1', '/messages/(?P<id>\d+)/like', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'duola_guestbook_rest_toggle_like',
        'permission_callback' => '__return_true',
        'args' => ['id' => ['sanitize_callback' => 'absint']],
    ]);
}
add_action('rest_api_init', 'duola_guestbook_register_rest_routes');

function duola_guestbook_verify_request(WP_REST_Request $request)
{
    $nonce = sanitize_text_field($request->get_header('X-Duola-Wall-Nonce'));
    if (!wp_verify_nonce($nonce, 'duola_wall_submit')) {
        return new WP_Error('duola_wall_invalid_request', __('Session expired. Retrying...', 'duola-albums'), ['status' => 403]);
    }
    return true;
}

function duola_guestbook_rest_create_message(WP_REST_Request $request)
{
    $verified = duola_guestbook_verify_request($request);
    if (is_wp_error($verified)) {
        return $verified;
    }

    if ('' !== trim((string) $request->get_param('website'))) {
        return new WP_Error('duola_wall_rejected', __('Message rejected.', 'duola-albums'), ['status' => 400]);
    }

    $started_at = absint($request->get_param('started_at'));
    $elapsed = time() - $started_at;
    if ($started_at <= 0 || $elapsed < 1 || $elapsed > 7200) {
        return new WP_Error('duola_wall_timing', __('Wait a moment before transmitting.', 'duola-albums'), ['status' => 400]);
    }

    $nickname = sanitize_text_field((string) $request->get_param('nickname'));
    $message = sanitize_textarea_field((string) $request->get_param('message'));
    $nickname = mb_substr(trim($nickname), 0, 32);
    $message = mb_substr(trim($message), 0, 300);
    if ('' === $message) {
        return new WP_Error('duola_wall_empty', __('Write something before transmitting.', 'duola-albums'), ['status' => 400]);
    }

    $ip_hash = duola_guestbook_ip_hash();
    if (duola_guestbook_is_blocked($ip_hash)) {
        return new WP_Error('duola_wall_blocked', __('Transmission denied.', 'duola-albums'), ['status' => 403]);
    }
    $rate_limit = duola_guestbook_rate_limit($ip_hash);
    if (is_wp_error($rate_limit)) {
        return $rate_limit;
    }

    global $wpdb;
    $status = duola_guestbook_has_url($message) ? 'pending' : 'publish';
    $inserted = $wpdb->insert(duola_guestbook_messages_table(), [
        'migration_uuid' => wp_generate_uuid4(),
        'parent_id' => 0,
        'nickname' => $nickname,
        'message' => $message,
        'status' => $status,
        'pinned' => 0,
        'like_count' => 0,
        'ip_hash' => $ip_hash,
        'created_at' => current_time('mysql', true),
    ], ['%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s']);

    if (!$inserted) {
        return new WP_Error('duola_wall_database', __('Could not save the message. Try again.', 'duola-albums'), ['status' => 500]);
    }

    $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . duola_guestbook_messages_table() . ' WHERE id = %d', $wpdb->insert_id));
    return new WP_REST_Response([
        'status' => $status,
        'message' => 'publish' === $status ? duola_guestbook_format_row($row) : null,
        'notice' => 'publish' === $status ? __('Message transmitted.', 'duola-albums') : __('Links detected. Message queued for review.', 'duola-albums'),
    ], 201);
}

function duola_guestbook_rest_toggle_like(WP_REST_Request $request)
{
    $verified = duola_guestbook_verify_request($request);
    if (is_wp_error($verified)) {
        return $verified;
    }

    global $wpdb;
    $message_id = absint($request['id']);
    $messages = duola_guestbook_messages_table();
    $likes = duola_guestbook_likes_table();
    $message = $wpdb->get_row($wpdb->prepare("SELECT id, like_count FROM {$messages} WHERE id = %d AND parent_id = 0 AND status = 'publish'", $message_id));
    if (!$message) {
        return new WP_Error('duola_wall_not_found', __('Message not found.', 'duola-albums'), ['status' => 404]);
    }

    $visitor_hash = duola_guestbook_visitor_hash();
    $was_liked = (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$likes} WHERE message_id = %d AND visitor_hash = %s", $message_id, $visitor_hash));
    if ($was_liked) {
        $deleted = $wpdb->delete($likes, ['message_id' => $message_id, 'visitor_hash' => $visitor_hash], ['%d', '%s']);
        if ($deleted) {
            $wpdb->query($wpdb->prepare("UPDATE {$messages} SET like_count = GREATEST(like_count - 1, 0) WHERE id = %d", $message_id));
        }
    } else {
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$likes} (message_id, visitor_hash, created_at) VALUES (%d, %s, %s)",
            $message_id,
            $visitor_hash,
            current_time('mysql', true)
        ));
        if ($inserted) {
            $wpdb->query($wpdb->prepare("UPDATE {$messages} SET like_count = like_count + 1 WHERE id = %d", $message_id));
        }
    }

    $is_liked = (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$likes} WHERE message_id = %d AND visitor_hash = %s", $message_id, $visitor_hash));

    return [
        'liked' => $is_liked,
        'likes' => (int) $wpdb->get_var($wpdb->prepare("SELECT like_count FROM {$messages} WHERE id = %d", $message_id)),
    ];
}

function duola_guestbook_add_admin_page(): void
{
    add_menu_page(__('留言板', 'duola-albums'), __('留言板', 'duola-albums'), 'manage_options', 'duola-guestbook', 'duola_guestbook_render_admin_page', 'dashicons-format-chat', 8);
}
add_action('admin_menu', 'duola_guestbook_add_admin_page', 25);

function duola_guestbook_admin_action_form(int $id, string $operation, string $label, string $class = 'button'): void
{
    $confirmation = match ($operation) {
        'block' => __('确认封禁这个来源，并隐藏它的全部留言？', 'duola-albums'),
        'delete' => __('确认永久删除这条留言、回复和点赞记录？', 'duola-albums'),
        default => '',
    };
    ?>
    <form class="duola-wall-inline-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post"<?php echo $confirmation ? ' data-wall-confirm="' . esc_attr($confirmation) . '"' : ''; ?>>
        <input type="hidden" name="action" value="duola_guestbook_manage">
        <input type="hidden" name="message_id" value="<?php echo esc_attr($id); ?>">
        <input type="hidden" name="operation" value="<?php echo esc_attr($operation); ?>">
        <?php wp_nonce_field('duola_guestbook_manage_' . $id); ?>
        <button class="<?php echo esc_attr($class); ?>" type="submit"><?php echo esc_html($label); ?></button>
    </form>
    <?php
}

function duola_guestbook_render_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $table = duola_guestbook_messages_table();
    $status = sanitize_key(wp_unslash($_GET['status'] ?? 'all'));
    $allowed_statuses = ['all', 'publish', 'pending', 'hidden'];
    if (!in_array($status, $allowed_statuses, true)) {
        $status = 'all';
    }
    $where = 'all' === $status ? '' : $wpdb->prepare(' AND status = %s', $status);
    $messages = $wpdb->get_results("SELECT * FROM {$table} WHERE parent_id = 0 {$where} ORDER BY pinned DESC, created_at DESC LIMIT 100");
    $blocked = (array) get_option('duola_guestbook_blocked_hashes', []);
    $counts = [];
    foreach (['publish', 'pending', 'hidden'] as $item_status) {
        $counts[$item_status] = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE parent_id = 0 AND status = %s", $item_status));
    }
    ?>
    <div class="wrap duola-wall-admin">
        <h1><?php esc_html_e('留言板', 'duola-albums'); ?></h1>
        <p><?php esc_html_e('管理匿名留言、审核网址内容、回复朋友并处理恶意来源。', 'duola-albums'); ?></p>
        <?php if (isset($_GET['updated'])) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e('留言板已更新。', 'duola-albums'); ?></p></div><?php endif; ?>
        <nav class="duola-wall-filters">
            <?php
            $filter_labels = [
                'all' => __('全部', 'duola-albums'),
                'publish' => sprintf(__('公开 %d', 'duola-albums'), $counts['publish']),
                'pending' => sprintf(__('待审核 %d', 'duola-albums'), $counts['pending']),
                'hidden' => sprintf(__('已隐藏 %d', 'duola-albums'), $counts['hidden']),
            ];
            foreach ($filter_labels as $key => $label) :
                ?><a class="<?php echo $status === $key ? 'is-current' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page' => 'duola-guestbook', 'status' => $key], admin_url('admin.php'))); ?>"><?php echo esc_html($label); ?></a><?php
            endforeach;
            ?>
        </nav>
        <div class="duola-wall-admin-list">
            <?php if (!$messages) : ?><p class="duola-wall-admin-empty"><?php esc_html_e('这里还没有留言。', 'duola-albums'); ?></p><?php endif; ?>
            <?php foreach ($messages as $message) : ?>
                <?php $replies = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE parent_id = %d ORDER BY created_at ASC", $message->id)); ?>
                <article class="duola-wall-admin-item">
                    <header>
                        <strong>#<?php echo esc_html(str_pad((string) $message->id, 4, '0', STR_PAD_LEFT)); ?> · <?php echo esc_html($message->nickname ?: 'anonymous'); ?></strong>
                        <span><?php echo esc_html(get_date_from_gmt($message->created_at, 'Y-m-d H:i')); ?> · <?php echo esc_html($message->status); ?> · +<?php echo esc_html($message->like_count); ?></span>
                    </header>
                    <p><?php echo nl2br(esc_html($message->message)); ?></p>
                    <small><?php echo esc_html__('来源：', 'duola-albums') . esc_html(substr($message->ip_hash, 0, 12)); ?></small>
                    <?php if ($replies) : ?><div class="duola-wall-admin-replies"><?php foreach ($replies as $reply) : ?><p><b><?php echo esc_html($reply->nickname ?: 'ddw'); ?>:</b> <?php echo nl2br(esc_html($reply->message)); ?></p><?php endforeach; ?></div><?php endif; ?>
                    <div class="duola-wall-admin-actions">
                        <?php if ('publish' !== $message->status) duola_guestbook_admin_action_form((int) $message->id, 'publish', __('公开', 'duola-albums')); ?>
                        <?php if ('hidden' !== $message->status) duola_guestbook_admin_action_form((int) $message->id, 'hide', __('隐藏', 'duola-albums')); ?>
                        <?php duola_guestbook_admin_action_form((int) $message->id, $message->pinned ? 'unpin' : 'pin', $message->pinned ? __('取消置顶', 'duola-albums') : __('置顶', 'duola-albums')); ?>
                        <?php if ($message->ip_hash) duola_guestbook_admin_action_form((int) $message->id, 'block', __('封禁来源', 'duola-albums'), 'button'); ?>
                        <?php duola_guestbook_admin_action_form((int) $message->id, 'delete', __('删除', 'duola-albums'), 'button button-link-delete'); ?>
                    </div>
                    <form class="duola-wall-reply-form" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <input type="hidden" name="action" value="duola_guestbook_manage">
                        <input type="hidden" name="message_id" value="<?php echo esc_attr($message->id); ?>">
                        <input type="hidden" name="operation" value="reply">
                        <?php wp_nonce_field('duola_guestbook_manage_' . $message->id); ?>
                        <input name="reply" type="text" maxlength="300" placeholder="<?php esc_attr_e('以 ddw 的身份回复…', 'duola-albums'); ?>" required>
                        <button class="button button-primary" type="submit"><?php esc_html_e('回复', 'duola-albums'); ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($blocked) : ?>
            <section class="duola-wall-blocked-list">
                <h2><?php esc_html_e('已封禁来源', 'duola-albums'); ?></h2>
                <?php foreach ($blocked as $hash => $blocked_at) : ?>
                    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                        <code><?php echo esc_html(substr($hash, 0, 16)); ?></code>
                        <span><?php echo esc_html(wp_date('Y-m-d H:i', absint($blocked_at))); ?></span>
                        <input type="hidden" name="action" value="duola_guestbook_unblock">
                        <input type="hidden" name="source_hash" value="<?php echo esc_attr($hash); ?>">
                        <?php wp_nonce_field('duola_guestbook_unblock_' . $hash); ?>
                        <button class="button" type="submit"><?php esc_html_e('解除封禁', 'duola-albums'); ?></button>
                    </form>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </div>
    <?php
}

function duola_guestbook_manage(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('你没有管理留言的权限。', 'duola-albums'));
    }

    global $wpdb;
    $id = absint($_POST['message_id'] ?? 0);
    $operation = sanitize_key(wp_unslash($_POST['operation'] ?? ''));
    check_admin_referer('duola_guestbook_manage_' . $id);
    $table = duola_guestbook_messages_table();
    $likes = duola_guestbook_likes_table();
    $message = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d AND parent_id = 0", $id));
    if (!$message) {
        wp_die(esc_html__('留言不存在。', 'duola-albums'));
    }

    if (in_array($operation, ['publish', 'hide'], true)) {
        $wpdb->update($table, ['status' => 'publish' === $operation ? 'publish' : 'hidden'], ['id' => $id], ['%s'], ['%d']);
    } elseif (in_array($operation, ['pin', 'unpin'], true)) {
        $wpdb->update($table, ['pinned' => 'pin' === $operation ? 1 : 0], ['id' => $id], ['%d'], ['%d']);
    } elseif ('reply' === $operation) {
        $reply = mb_substr(trim(sanitize_textarea_field(wp_unslash($_POST['reply'] ?? ''))), 0, 300);
        if ($reply) {
            $wpdb->insert($table, [
                'migration_uuid' => wp_generate_uuid4(),
                'parent_id' => $id,
                'nickname' => 'ddw',
                'message' => $reply,
                'status' => 'publish',
                'created_at' => current_time('mysql', true),
            ], ['%s', '%d', '%s', '%s', '%s', '%s']);
        }
    } elseif ('block' === $operation && $message->ip_hash) {
        $blocked = (array) get_option('duola_guestbook_blocked_hashes', []);
        $blocked[$message->ip_hash] = time();
        update_option('duola_guestbook_blocked_hashes', $blocked, false);
        $wpdb->update($table, ['status' => 'hidden'], ['ip_hash' => $message->ip_hash], ['%s'], ['%s']);
    } elseif ('delete' === $operation) {
        $reply_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM {$table} WHERE parent_id = %d", $id));
        $all_ids = array_merge([$id], array_map('absint', $reply_ids));
        $placeholders = implode(',', array_fill(0, count($all_ids), '%d'));
        $wpdb->query($wpdb->prepare("DELETE FROM {$likes} WHERE message_id IN ({$placeholders})", ...$all_ids));
        $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE id IN ({$placeholders})", ...$all_ids));
    }

    wp_safe_redirect(add_query_arg(['page' => 'duola-guestbook', 'updated' => 1], admin_url('admin.php')));
    exit;
}
add_action('admin_post_duola_guestbook_manage', 'duola_guestbook_manage');

function duola_guestbook_unblock(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('你没有管理留言的权限。', 'duola-albums'));
    }
    $hash = sanitize_text_field(wp_unslash($_POST['source_hash'] ?? ''));
    check_admin_referer('duola_guestbook_unblock_' . $hash);
    $blocked = (array) get_option('duola_guestbook_blocked_hashes', []);
    unset($blocked[$hash]);
    update_option('duola_guestbook_blocked_hashes', $blocked, false);
    wp_safe_redirect(add_query_arg(['page' => 'duola-guestbook', 'updated' => 1], admin_url('admin.php')));
    exit;
}
add_action('admin_post_duola_guestbook_unblock', 'duola_guestbook_unblock');

function duola_guestbook_enqueue_admin_assets(string $hook): void
{
    if ('toplevel_page_duola-guestbook' !== $hook) {
        return;
    }
    wp_enqueue_style('duola-guestbook-admin', DUOLA_ALBUMS_URL . 'assets/guestbook-admin.css', [], DUOLA_ALBUMS_VERSION);
    wp_enqueue_script('duola-guestbook-admin', DUOLA_ALBUMS_URL . 'assets/guestbook-admin.js', [], DUOLA_ALBUMS_VERSION, true);
}
add_action('admin_enqueue_scripts', 'duola_guestbook_enqueue_admin_assets');

function duola_guestbook_export(): array
{
    duola_guestbook_maybe_install();
    global $wpdb;
    $table = duola_guestbook_messages_table();
    $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC");
    $uuid_by_id = [];
    foreach ($rows as $row) {
        if (!wp_is_uuid($row->migration_uuid)) {
            $row->migration_uuid = wp_generate_uuid4();
            $wpdb->update($table, ['migration_uuid' => $row->migration_uuid], ['id' => $row->id], ['%s'], ['%d']);
        }
        $uuid_by_id[(int) $row->id] = $row->migration_uuid;
    }

    return array_map(static function (object $row) use ($uuid_by_id): array {
        return [
            'uuid' => $row->migration_uuid,
            'parent_uuid' => $uuid_by_id[(int) $row->parent_id] ?? '',
            'nickname' => $row->nickname,
            'message' => $row->message,
            'status' => $row->status,
            'pinned' => (bool) $row->pinned,
            'likes' => (int) $row->like_count,
            'created_at' => $row->created_at,
        ];
    }, $rows);
}

function duola_guestbook_import(array $entries): int
{
    duola_guestbook_maybe_install();
    global $wpdb;
    $table = duola_guestbook_messages_table();
    $ids = [];
    $allowed_statuses = ['publish', 'pending', 'hidden'];

    foreach ([false, true] as $is_reply_pass) {
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $parent_uuid = sanitize_text_field($entry['parent_uuid'] ?? '');
            if ($is_reply_pass !== ('' !== $parent_uuid)) {
                continue;
            }
            $uuid = sanitize_text_field($entry['uuid'] ?? '');
            if (!wp_is_uuid($uuid)) {
                continue;
            }
            $parent_id = $parent_uuid ? ($ids[$parent_uuid] ?? 0) : 0;
            if ($parent_uuid && !$parent_id) {
                continue;
            }
            $status = sanitize_key($entry['status'] ?? 'publish');
            $data = [
                'migration_uuid' => $uuid,
                'parent_id' => $parent_id,
                'nickname' => mb_substr(sanitize_text_field($entry['nickname'] ?? ''), 0, 32),
                'message' => mb_substr(sanitize_textarea_field($entry['message'] ?? ''), 0, 300),
                'status' => in_array($status, $allowed_statuses, true) ? $status : 'publish',
                'pinned' => !empty($entry['pinned']) ? 1 : 0,
                'like_count' => absint($entry['likes'] ?? 0),
                'ip_hash' => '',
                'created_at' => preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) ($entry['created_at'] ?? '')) ? $entry['created_at'] : current_time('mysql', true),
            ];
            $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE migration_uuid = %s", $uuid));
            if ($existing_id) {
                $wpdb->update($table, $data, ['id' => $existing_id]);
                $ids[$uuid] = $existing_id;
            } else {
                $wpdb->insert($table, $data);
                $ids[$uuid] = (int) $wpdb->insert_id;
            }
        }
    }
    return count($ids);
}
