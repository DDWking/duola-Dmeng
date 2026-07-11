<?php
/**
 * Plugin Name: 哆啦D梦相册
 * Description: 提供按年份管理、批量上传、封面选择和拖拽排序的相册内容类型。
 * Version: 1.3.0
 * Author: DDWking
 * Text Domain: duola-albums
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DUOLA_ALBUMS_VERSION', '1.3.0');
define('DUOLA_ALBUMS_URL', plugin_dir_url(__FILE__));

function duola_albums_register_content_type(): void
{
    register_post_type('album', [
        'labels' => [
            'name' => __('相册', 'duola-albums'),
            'singular_name' => __('相册', 'duola-albums'),
            'add_new_item' => __('新建相册', 'duola-albums'),
            'edit_item' => __('编辑相册', 'duola-albums'),
            'all_items' => __('相册', 'duola-albums'),
            'menu_name' => __('照片', 'duola-albums'),
            'search_items' => __('搜索相册', 'duola-albums'),
            'not_found' => __('还没有相册。', 'duola-albums'),
            'not_found_in_trash' => __('回收站中没有相册。', 'duola-albums'),
        ],
        'public' => true,
        'has_archive' => 'photos',
        'rewrite' => ['slug' => 'photos'],
        'menu_icon' => 'dashicons-format-gallery',
        'menu_position' => 6,
        'show_in_rest' => true,
        'supports' => ['title', 'thumbnail', 'revisions'],
    ]);
}
add_action('init', 'duola_albums_register_content_type');

function duola_albums_use_classic_editor(bool $use_block_editor, string $post_type): bool
{
    return 'album' === $post_type ? false : $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'duola_albums_use_classic_editor', 10, 2);

function duola_albums_title_placeholder(string $title, WP_Post $post): string
{
    return 'album' === $post->post_type ? __('给这组照片起个名字', 'duola-albums') : $title;
}
add_filter('enter_title_here', 'duola_albums_title_placeholder', 10, 2);

function duola_albums_organize_admin_menu(): void
{
    remove_menu_page('upload.php');
    remove_menu_page('edit-comments.php');
    add_submenu_page(
        'edit.php?post_type=album',
        __('照片库', 'duola-albums'),
        __('照片库', 'duola-albums'),
        'upload_files',
        'upload.php'
    );
}
add_action('admin_menu', 'duola_albums_organize_admin_menu', 80);

function duola_albums_media_menu_parent(string $parent_file): string
{
    global $pagenow, $submenu_file;

    if (in_array($pagenow, ['upload.php', 'media-new.php'], true)) {
        $parent_file = 'edit.php?post_type=album';
        $submenu_file = 'upload.php';
    }

    return $parent_file;
}
add_filter('parent_file', 'duola_albums_media_menu_parent');

function duola_albums_simplify_admin_bar(WP_Admin_Bar $admin_bar): void
{
    $admin_bar->remove_node('comments');
}
add_action('admin_bar_menu', 'duola_albums_simplify_admin_bar', 90);

function duola_albums_disable_comments_support(): void
{
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
        }
        if (post_type_supports($post_type, 'trackbacks')) {
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}
add_action('admin_init', 'duola_albums_disable_comments_support');
add_filter('comments_open', '__return_false', 20);
add_filter('pings_open', '__return_false', 20);
add_filter('comments_array', '__return_empty_array', 20);

function duola_albums_register_meta(): void
{
    $meta = [
        '_duola_album_year' => ['type' => 'string', 'sanitize_callback' => 'absint'],
        '_duola_album_location' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_duola_album_description' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
        '_duola_album_cover_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
        '_duola_album_photos' => ['type' => 'array', 'sanitize_callback' => 'duola_albums_sanitize_photo_ids'],
        '_duola_album_photo_settings' => ['type' => 'object', 'sanitize_callback' => 'duola_albums_sanitize_photo_settings'],
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

function duola_albums_photo_setting_defaults(): array
{
    return [
        'headline' => '',
        'description' => '',
        'date' => '',
        'layout' => 'standard',
        'text_position' => 'spread',
        'focus_x' => 50,
        'focus_y' => 50,
        'accent' => '#009fe8',
        'background' => '#f3f3f0',
        'home_width' => 'standard',
        'show_home' => true,
    ];
}

function duola_albums_sanitize_photo_settings($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $settings = [];
    $allowed_layouts = ['compact', 'standard', 'wide'];
    $allowed_positions = ['left', 'spread', 'right'];
    $allowed_home_widths = ['narrow', 'standard', 'wide'];

    foreach ($value as $photo_id => $photo_settings) {
        $photo_id = absint($photo_id);
        if (!$photo_id || !is_array($photo_settings)) {
            continue;
        }

        $layout = sanitize_key($photo_settings['layout'] ?? 'standard');
        $text_position = sanitize_key($photo_settings['text_position'] ?? 'spread');
        $home_width = sanitize_key($photo_settings['home_width'] ?? 'standard');
        $date = sanitize_text_field($photo_settings['date'] ?? '');
        $accent = sanitize_hex_color($photo_settings['accent'] ?? '') ?: '#009fe8';
        $background = sanitize_hex_color($photo_settings['background'] ?? '') ?: '#f3f3f0';

        $settings[(string) $photo_id] = [
            'headline' => sanitize_text_field($photo_settings['headline'] ?? ''),
            'description' => sanitize_textarea_field($photo_settings['description'] ?? ''),
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : '',
            'layout' => in_array($layout, $allowed_layouts, true) ? $layout : 'standard',
            'text_position' => in_array($text_position, $allowed_positions, true) ? $text_position : 'spread',
            'focus_x' => max(0, min(100, absint($photo_settings['focus_x'] ?? 50))),
            'focus_y' => max(0, min(100, absint($photo_settings['focus_y'] ?? 50))),
            'accent' => $accent,
            'background' => $background,
            'home_width' => in_array($home_width, $allowed_home_widths, true) ? $home_width : 'standard',
            'show_home' => !empty($photo_settings['show_home']),
        ];
    }

    return $settings;
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
    $description = duola_albums_get_description($post->ID);
    $cover_id = duola_albums_get_cover_id($post->ID);
    $photos = duola_albums_get_photos($post->ID);
    $photo_settings = duola_albums_get_all_photo_settings($post->ID);
    wp_nonce_field('duola_albums_save_album', 'duola_albums_nonce');
    ?>
    <div class="duola-album-workspace">
        <section class="duola-upload-panel">
            <div>
                <h3><?php esc_html_e('把照片放进这个相册', 'duola-albums'); ?></h3>
                <p><?php esc_html_e('可以一次选择多张照片，无需填写照片名称或说明。', 'duola-albums'); ?></p>
            </div>
            <button type="button" class="button button-primary button-hero" id="duola-add-photos">
                <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                <?php esc_html_e('批量上传或选择照片', 'duola-albums'); ?>
            </button>
        </section>

        <input id="duola-album-cover-id" name="duola_album_cover_id" type="hidden" value="<?php echo esc_attr($cover_id); ?>">
        <input id="duola-album-photo-ids" name="duola_album_photo_ids" type="hidden" value="<?php echo esc_attr(wp_json_encode(wp_list_pluck($photos, 'id'))); ?>">
        <input id="duola-album-photo-settings" name="duola_album_photo_settings" type="hidden" value="<?php echo esc_attr(wp_json_encode($photo_settings)); ?>">

        <div class="duola-photo-toolbar">
            <strong id="duola-photo-count"><?php echo esc_html(sprintf(_n('%d 张照片', '%d 张照片', count($photos), 'duola-albums'), count($photos))); ?></strong>
            <span><?php esc_html_e('拖拽照片可以调整前台顺序，点击“设为封面”即可更换封面。', 'duola-albums'); ?></span>
        </div>
        <ul id="duola-album-photo-list" class="duola-album-photo-list">
            <?php foreach ($photos as $photo) : ?>
                <li data-id="<?php echo esc_attr($photo['id']); ?>" class="<?php echo trim(($cover_id === $photo['id'] ? 'is-cover ' : '') . (!empty($photo_settings[(string) $photo['id']]) ? 'has-settings' : '')); ?>">
                    <?php echo wp_get_attachment_image($photo['id'], 'thumbnail'); ?>
                    <div class="duola-photo-actions">
                        <button type="button" class="button-link duola-edit-photo"><?php esc_html_e('编辑信息与排版', 'duola-albums'); ?></button>
                        <button type="button" class="button-link duola-set-cover"><?php esc_html_e('设为封面', 'duola-albums'); ?></button>
                        <button type="button" class="button-link-delete duola-remove-photo"><?php esc_html_e('移除', 'duola-albums'); ?></button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <section id="duola-photo-editor" class="duola-photo-editor" hidden>
            <header class="duola-photo-editor-header">
                <div>
                    <span><?php esc_html_e('单张照片设置', 'duola-albums'); ?></span>
                    <strong id="duola-photo-editor-title"></strong>
                </div>
                <button type="button" class="button-link" id="duola-close-photo-editor"><?php esc_html_e('关闭', 'duola-albums'); ?></button>
            </header>
            <div class="duola-photo-editor-body">
                <div class="duola-editor-preview" id="duola-editor-preview">
                    <img id="duola-editor-preview-image" alt="">
                    <div class="duola-editor-preview-headline" id="duola-editor-preview-headline"></div>
                    <div class="duola-editor-preview-meta">
                        <span id="duola-editor-preview-date"></span>
                        <p id="duola-editor-preview-description"></p>
                    </div>
                </div>
                <div class="duola-photo-fields">
                    <div class="duola-field duola-field-wide">
                        <label for="duola-photo-headline"><?php esc_html_e('覆盖装饰文字', 'duola-albums'); ?></label>
                        <input id="duola-photo-headline" type="text" maxlength="80" placeholder="<?php esc_attr_e('例如：STAY ALIVE / 山海之间', 'duola-albums'); ?>">
                        <span><?php esc_html_e('留空时使用相册标题。', 'duola-albums'); ?></span>
                    </div>
                    <div class="duola-field duola-field-wide">
                        <label for="duola-photo-description"><?php esc_html_e('图片描述', 'duola-albums'); ?></label>
                        <textarea id="duola-photo-description" rows="3" maxlength="240" placeholder="<?php esc_attr_e('一两句就够，留空则不显示。', 'duola-albums'); ?>"></textarea>
                    </div>
                    <div class="duola-field">
                        <label for="duola-photo-date"><?php esc_html_e('拍摄日期', 'duola-albums'); ?></label>
                        <input id="duola-photo-date" type="date">
                    </div>
                    <div class="duola-field">
                        <label for="duola-photo-layout"><?php esc_html_e('看图页画面尺寸', 'duola-albums'); ?></label>
                        <select id="duola-photo-layout">
                            <option value="compact"><?php esc_html_e('紧凑', 'duola-albums'); ?></option>
                            <option value="standard"><?php esc_html_e('标准', 'duola-albums'); ?></option>
                            <option value="wide"><?php esc_html_e('宽幅', 'duola-albums'); ?></option>
                        </select>
                    </div>
                    <div class="duola-field">
                        <label for="duola-photo-text-position"><?php esc_html_e('装饰文字位置', 'duola-albums'); ?></label>
                        <select id="duola-photo-text-position">
                            <option value="left"><?php esc_html_e('靠左', 'duola-albums'); ?></option>
                            <option value="spread"><?php esc_html_e('跨画面分散', 'duola-albums'); ?></option>
                            <option value="right"><?php esc_html_e('靠右', 'duola-albums'); ?></option>
                        </select>
                    </div>
                    <div class="duola-field">
                        <label for="duola-photo-home-width"><?php esc_html_e('首页切片宽度', 'duola-albums'); ?></label>
                        <select id="duola-photo-home-width">
                            <option value="narrow"><?php esc_html_e('窄', 'duola-albums'); ?></option>
                            <option value="standard"><?php esc_html_e('标准', 'duola-albums'); ?></option>
                            <option value="wide"><?php esc_html_e('宽', 'duola-albums'); ?></option>
                        </select>
                    </div>
                    <div class="duola-field duola-field-range">
                        <label for="duola-photo-focus-x"><?php esc_html_e('水平焦点', 'duola-albums'); ?> <output id="duola-photo-focus-x-value">50%</output></label>
                        <input id="duola-photo-focus-x" type="range" min="0" max="100" value="50">
                    </div>
                    <div class="duola-field duola-field-range">
                        <label for="duola-photo-focus-y"><?php esc_html_e('垂直焦点', 'duola-albums'); ?> <output id="duola-photo-focus-y-value">50%</output></label>
                        <input id="duola-photo-focus-y" type="range" min="0" max="100" value="50">
                    </div>
                    <div class="duola-field duola-field-color">
                        <label for="duola-photo-accent"><?php esc_html_e('装饰文字颜色', 'duola-albums'); ?></label>
                        <input id="duola-photo-accent" type="color" value="#009fe8">
                    </div>
                    <div class="duola-field duola-field-color">
                        <label for="duola-photo-background"><?php esc_html_e('看图页背景', 'duola-albums'); ?></label>
                        <input id="duola-photo-background" type="color" value="#f3f3f0">
                    </div>
                    <label class="duola-home-visibility">
                        <input id="duola-photo-show-home" type="checkbox" checked>
                        <span><?php esc_html_e('在首页照片轨道中显示', 'duola-albums'); ?></span>
                    </label>
                    <div class="duola-photo-editor-actions">
                        <button type="button" class="button" id="duola-reset-photo-settings"><?php esc_html_e('恢复默认', 'duola-albums'); ?></button>
                        <span><?php esc_html_e('修改会在保存相册时一起保存。', 'duola-albums'); ?></span>
                    </div>
                </div>
            </div>
        </section>

        <details class="duola-optional-settings" <?php echo ($location || $description) ? 'open' : ''; ?>>
            <summary><?php esc_html_e('补充相册信息（可选）', 'duola-albums'); ?></summary>
            <div class="duola-fields-grid">
                <p>
                    <label for="duola_album_year"><strong><?php esc_html_e('年份', 'duola-albums'); ?></strong></label>
                    <input id="duola_album_year" name="duola_album_year" type="number" min="1900" max="2100" value="<?php echo esc_attr($year ?: wp_date('Y')); ?>" required>
                    <span class="description"><?php esc_html_e('已经默认填好当前年份。', 'duola-albums'); ?></span>
                </p>
                <p>
                    <label for="duola_album_location"><strong><?php esc_html_e('地点', 'duola-albums'); ?></strong></label>
                    <input id="duola_album_location" name="duola_album_location" type="text" value="<?php echo esc_attr($location); ?>" placeholder="<?php esc_attr_e('例如：成都', 'duola-albums'); ?>">
                </p>
            </div>
            <p>
                <label for="duola_album_description"><strong><?php esc_html_e('简短说明', 'duola-albums'); ?></strong></label>
                <textarea id="duola_album_description" name="duola_album_description" rows="4" placeholder="<?php esc_attr_e('想写的时候再写，留空也没关系。', 'duola-albums'); ?>"><?php echo esc_textarea($description); ?></textarea>
            </p>
        </details>
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
    $description = isset($_POST['duola_album_description']) ? wp_kses_post(wp_unslash($_POST['duola_album_description'])) : '';
    $cover_id = isset($_POST['duola_album_cover_id']) ? absint($_POST['duola_album_cover_id']) : 0;
    $photo_ids = [];
    $photo_settings = [];

    if (isset($_POST['duola_album_photo_ids'])) {
        $decoded = json_decode(wp_unslash($_POST['duola_album_photo_ids']), true);
        $photo_ids = duola_albums_sanitize_photo_ids($decoded);
    }

    if (isset($_POST['duola_album_photo_settings'])) {
        $decoded_settings = json_decode(wp_unslash($_POST['duola_album_photo_settings']), true);
        $photo_settings = duola_albums_sanitize_photo_settings($decoded_settings);
    }

    update_post_meta($post_id, '_duola_album_year', $year);
    update_post_meta($post_id, '_duola_album_location', $location);
    update_post_meta($post_id, '_duola_album_description', $description);
    update_post_meta($post_id, '_duola_album_photos', $photo_ids);
    update_post_meta($post_id, '_duola_album_photo_settings', array_intersect_key($photo_settings, array_flip(array_map('strval', $photo_ids))));

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
    wp_enqueue_style('duola-albums-admin', DUOLA_ALBUMS_URL . 'assets/admin.css', [], DUOLA_ALBUMS_VERSION);

    if (!$screen || $screen->post_type !== 'album') {
        return;
    }

    if ('post' !== $screen->base) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_script('duola-albums-admin', DUOLA_ALBUMS_URL . 'assets/admin.js', ['jquery', 'jquery-ui-sortable'], DUOLA_ALBUMS_VERSION, true);
    wp_localize_script('duola-albums-admin', 'duolaAlbums', [
        'title' => __('选择照片', 'duola-albums'),
        'add' => __('添加到相册', 'duola-albums'),
        'count' => __('%d 张照片', 'duola-albums'),
    ]);
}
add_action('admin_enqueue_scripts', 'duola_albums_admin_assets');

function duola_albums_render_admin_intro(): void
{
    $screen = get_current_screen();
    if (!$screen || 'edit-album' !== $screen->id) {
        return;
    }
    ?>
    <div class="duola-admin-intro">
        <div>
            <h2><?php esc_html_e('照片从相册开始整理', 'duola-albums'); ?></h2>
            <p><?php esc_html_e('一次拍摄建立一个相册，再批量上传照片。照片库保留了所有原图，方便以后查找和复用。', 'duola-albums'); ?></p>
        </div>
        <div class="duola-admin-intro-actions">
            <a class="button button-primary" href="<?php echo esc_url(admin_url('post-new.php?post_type=album')); ?>"><?php esc_html_e('新建相册', 'duola-albums'); ?></a>
            <a class="button" href="<?php echo esc_url(admin_url('upload.php')); ?>"><?php esc_html_e('打开照片库', 'duola-albums'); ?></a>
        </div>
    </div>
    <?php
}
add_action('all_admin_notices', 'duola_albums_render_admin_intro');

function duola_albums_admin_columns(array $columns): array
{
    return [
        'cb' => $columns['cb'],
        'duola_cover' => __('封面', 'duola-albums'),
        'title' => __('相册', 'duola-albums'),
        'duola_year' => __('年份', 'duola-albums'),
        'duola_photos' => __('照片', 'duola-albums'),
        'date' => __('状态与日期', 'duola-albums'),
    ];
}
add_filter('manage_album_posts_columns', 'duola_albums_admin_columns');

function duola_albums_render_admin_column(string $column, int $post_id): void
{
    if ('duola_cover' === $column) {
        $cover_id = duola_albums_get_cover_id($post_id);
        echo $cover_id ? wp_get_attachment_image($cover_id, 'thumbnail') : '<span class="duola-no-cover">' . esc_html__('暂无封面', 'duola-albums') . '</span>';
    }

    if ('duola_year' === $column) {
        echo esc_html(duola_albums_get_year($post_id) ?: get_the_date('Y', $post_id));
    }

    if ('duola_photos' === $column) {
        echo esc_html(sprintf(__('%d 张', 'duola-albums'), count(duola_albums_get_photos($post_id))));
    }
}
add_action('manage_album_posts_custom_column', 'duola_albums_render_admin_column', 10, 2);

function duola_albums_sortable_admin_columns(array $columns): array
{
    $columns['duola_year'] = 'duola_year';
    return $columns;
}
add_filter('manage_edit-album_sortable_columns', 'duola_albums_sortable_admin_columns');

function duola_albums_sort_admin_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query() || 'duola_year' !== $query->get('orderby')) {
        return;
    }

    $query->set('meta_key', '_duola_album_year');
    $query->set('orderby', 'meta_value_num');
}
add_action('pre_get_posts', 'duola_albums_sort_admin_query');

function duola_albums_get_year(int $album_id): string
{
    return (string) get_post_meta($album_id, '_duola_album_year', true);
}

function duola_albums_get_description(int $album_id): string
{
    $description = (string) get_post_meta($album_id, '_duola_album_description', true);
    return '' !== $description ? $description : (string) get_post_field('post_content', $album_id);
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
            'settings' => duola_albums_get_photo_settings($album_id, $photo_id),
        ];
    }
    return $photos;
}

function duola_albums_get_all_photo_settings(int $album_id): array
{
    return duola_albums_sanitize_photo_settings(get_post_meta($album_id, '_duola_album_photo_settings', true));
}

function duola_albums_get_photo_settings(int $album_id, int $photo_id): array
{
    $all_settings = duola_albums_get_all_photo_settings($album_id);
    return array_merge(duola_albums_photo_setting_defaults(), $all_settings[(string) $photo_id] ?? []);
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
