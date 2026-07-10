<?php
/**
 * Plugin Name: 哆啦D梦相册
 * Description: 提供按年份管理、批量上传、封面选择和拖拽排序的相册内容类型。
 * Version: 1.0.0
 * Author: DDWking
 * Text Domain: duola-albums
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DUOLA_ALBUMS_VERSION', '1.0.0');
define('DUOLA_ALBUMS_URL', plugin_dir_url(__FILE__));

function duola_albums_register_content_type(): void
{
    register_post_type('album', [
        'labels' => [
            'name' => __('相册', 'duola-albums'),
            'singular_name' => __('相册', 'duola-albums'),
            'add_new_item' => __('新建相册', 'duola-albums'),
            'edit_item' => __('编辑相册', 'duola-albums'),
            'all_items' => __('所有相册', 'duola-albums'),
            'menu_name' => __('相册', 'duola-albums'),
            'search_items' => __('搜索相册', 'duola-albums'),
            'not_found' => __('还没有相册。', 'duola-albums'),
            'not_found_in_trash' => __('回收站中没有相册。', 'duola-albums'),
        ],
        'public' => true,
        'has_archive' => 'photos',
        'rewrite' => ['slug' => 'photos'],
        'menu_icon' => 'dashicons-format-gallery',
        'show_in_rest' => true,
        'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
    ]);
}
add_action('init', 'duola_albums_register_content_type');

function duola_albums_register_meta(): void
{
    $meta = [
        '_duola_album_year' => ['type' => 'string', 'sanitize_callback' => 'absint'],
        '_duola_album_location' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_duola_album_cover_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
        '_duola_album_photos' => ['type' => 'array', 'sanitize_callback' => 'duola_albums_sanitize_photo_ids'],
    ];

    foreach ($meta as $key => $args) {
        register_post_meta('album', $key, array_merge($args, [
            'single' => true,
            'show_in_rest' => false,
            'auth_callback' => static fn() => current_user_can('edit_posts'),
        ]));
    }
}
add_action('init', 'duola_albums_register_meta');

function duola_albums_sanitize_photo_ids($value): array
{
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_filter(array_map('absint', $value)));
}

function duola_albums_add_meta_boxes(): void
{
    add_meta_box(
        'duola-album-details',
        __('相册信息与照片', 'duola-albums'),
        'duola_albums_render_meta_box',
        'album',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes_album', 'duola_albums_add_meta_boxes');

function duola_albums_render_meta_box(WP_Post $post): void
{
    $year = duola_albums_get_year($post->ID);
    $location = get_post_meta($post->ID, '_duola_album_location', true);
    $cover_id = duola_albums_get_cover_id($post->ID);
    $photos = duola_albums_get_photos($post->ID);
    wp_nonce_field('duola_albums_save_album', 'duola_albums_nonce');
    ?>
    <div class="duola-album-fields">
        <p>
            <label for="duola_album_year"><strong><?php esc_html_e('年份', 'duola-albums'); ?></strong> <span class="description"><?php esc_html_e('必填，用于摄影页分类。', 'duola-albums'); ?></span></label><br>
            <input id="duola_album_year" name="duola_album_year" type="number" min="1900" max="2100" value="<?php echo esc_attr($year ?: wp_date('Y')); ?>" required>
        </p>
        <p>
            <label for="duola_album_location"><strong><?php esc_html_e('地点', 'duola-albums'); ?></strong> <span class="description"><?php esc_html_e('可选。', 'duola-albums'); ?></span></label><br>
            <input id="duola_album_location" name="duola_album_location" class="widefat" type="text" value="<?php echo esc_attr($location); ?>">
        </p>
        <p class="description"><?php esc_html_e('直接批量上传照片即可；标题、说明和拍摄信息都可以以后再补。拖拽下方照片可调整前台顺序。', 'duola-albums'); ?></p>
        <p>
            <button type="button" class="button button-primary" id="duola-add-photos"><?php esc_html_e('批量添加照片', 'duola-albums'); ?></button>
            <button type="button" class="button" id="duola-select-cover"><?php esc_html_e('选择相册封面', 'duola-albums'); ?></button>
        </p>
        <input id="duola-album-cover-id" name="duola_album_cover_id" type="hidden" value="<?php echo esc_attr($cover_id); ?>">
        <input id="duola-album-photo-ids" name="duola_album_photo_ids" type="hidden" value="<?php echo esc_attr(wp_json_encode(wp_list_pluck($photos, 'id'))); ?>">
        <div id="duola-cover-preview" class="duola-cover-preview">
            <?php if ($cover_id) { echo wp_get_attachment_image($cover_id, 'thumbnail'); } ?>
        </div>
        <ul id="duola-album-photo-list" class="duola-album-photo-list">
            <?php foreach ($photos as $photo) : ?>
                <li data-id="<?php echo esc_attr($photo['id']); ?>">
                    <?php echo wp_get_attachment_image($photo['id'], 'thumbnail'); ?>
                    <button type="button" class="duola-remove-photo" aria-label="<?php esc_attr_e('移除照片', 'duola-albums'); ?>">×</button>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
}

function duola_albums_save_album(int $post_id): void
{
    if (!isset($_POST['duola_albums_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['duola_albums_nonce'])), 'duola_albums_save_album')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $year = isset($_POST['duola_album_year']) ? absint($_POST['duola_album_year']) : 0;
    $location = isset($_POST['duola_album_location']) ? sanitize_text_field(wp_unslash($_POST['duola_album_location'])) : '';
    $cover_id = isset($_POST['duola_album_cover_id']) ? absint($_POST['duola_album_cover_id']) : 0;
    $photo_ids = [];

    if (isset($_POST['duola_album_photo_ids'])) {
        $decoded = json_decode(wp_unslash($_POST['duola_album_photo_ids']), true);
        $photo_ids = duola_albums_sanitize_photo_ids($decoded);
    }

    update_post_meta($post_id, '_duola_album_year', $year);
    update_post_meta($post_id, '_duola_album_location', $location);
    update_post_meta($post_id, '_duola_album_photos', $photo_ids);

    if (!$cover_id && $photo_ids) {
        $cover_id = $photo_ids[0];
    }

    update_post_meta($post_id, '_duola_album_cover_id', $cover_id);
    if ($cover_id) {
        set_post_thumbnail($post_id, $cover_id);
    } else {
        delete_post_thumbnail($post_id);
    }
}
add_action('save_post_album', 'duola_albums_save_album');

function duola_albums_admin_assets(string $hook): void
{
    $screen = get_current_screen();
    if (!$screen || $screen->post_type !== 'album') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('duola-albums-admin', DUOLA_ALBUMS_URL . 'assets/admin.css', [], DUOLA_ALBUMS_VERSION);
    wp_enqueue_script('duola-albums-admin', DUOLA_ALBUMS_URL . 'assets/admin.js', ['jquery', 'jquery-ui-sortable'], DUOLA_ALBUMS_VERSION, true);
    wp_localize_script('duola-albums-admin', 'duolaAlbums', [
        'title' => __('选择照片', 'duola-albums'),
        'add' => __('添加到相册', 'duola-albums'),
        'coverTitle' => __('选择封面', 'duola-albums'),
        'coverButton' => __('使用这张照片', 'duola-albums'),
    ]);
}
add_action('admin_enqueue_scripts', 'duola_albums_admin_assets');

function duola_albums_get_year(int $album_id): string
{
    return (string) get_post_meta($album_id, '_duola_album_year', true);
}

function duola_albums_get_cover_id(int $album_id): int
{
    $cover_id = (int) get_post_meta($album_id, '_duola_album_cover_id', true);
    if ($cover_id) {
        return $cover_id;
    }

    $photos = duola_albums_get_photos($album_id);
    return $photos ? $photos[0]['id'] : (int) get_post_thumbnail_id($album_id);
}

function duola_albums_get_photos(int $album_id): array
{
    $photo_ids = duola_albums_sanitize_photo_ids(get_post_meta($album_id, '_duola_album_photos', true));
    $photos = [];
    foreach ($photo_ids as $photo_id) {
        if ('attachment' !== get_post_type($photo_id)) {
            continue;
        }
        $photos[] = [
            'id' => $photo_id,
            'caption' => wp_get_attachment_caption($photo_id),
        ];
    }
    return $photos;
}

function duola_albums_get_years(): array
{
    global $wpdb;
    $years = $wpdb->get_col(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} meta INNER JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id WHERE meta.meta_key = '_duola_album_year' AND posts.post_type = 'album' AND posts.post_status = 'publish' AND meta.meta_value <> '' ORDER BY CAST(meta.meta_value AS UNSIGNED) DESC"
    );
    return array_map('strval', $years);
}

function duola_albums_query_by_year(string $year): WP_Query
{
    return new WP_Query([
        'post_type' => 'album',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'meta_key' => '_duola_album_year',
        'meta_value' => $year,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);
}

function duola_albums_activate(): void
{
    duola_albums_register_content_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'duola_albums_activate');

function duola_albums_deactivate(): void
{
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'duola_albums_deactivate');
