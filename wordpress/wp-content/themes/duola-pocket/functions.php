<?php
/**
 * Theme setup and helpers for 哆啦D梦的口袋.
 */

if (!defined('ABSPATH')) {
    exit;
}

function duola_pocket_setup(): void
{
    load_theme_textdomain('duola-pocket', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_editor_style('assets/editor-style.css');

    add_image_size('duola-album-card', 960, 720, true);
    add_image_size('duola-home-note', 720, 960, true);
    add_image_size('duola-lightbox', 2048, 2048, false);
}
add_action('after_setup_theme', 'duola_pocket_setup');

function duola_pocket_image_output_format(array $formats, ?string $filename, string $mime_type): array
{
    if (in_array($mime_type, ['image/jpeg', 'image/png'], true) && wp_image_editor_supports(['mime_type' => 'image/webp'])) {
        $formats[$mime_type] = 'image/webp';
    }

    return $formats;
}
add_filter('image_editor_output_format', 'duola_pocket_image_output_format', 10, 3);

function duola_pocket_image_quality(int $quality, string $mime_type): int
{
    return 'image/webp' === $mime_type ? 82 : $quality;
}
add_filter('wp_editor_set_quality', 'duola_pocket_image_quality', 10, 2);

add_filter('big_image_size_threshold', '__return_false');

function duola_pocket_enqueue_assets(): void
{
    $style_path = get_stylesheet_directory() . '/style.css';
    wp_enqueue_style('duola-pocket-style', get_stylesheet_uri(), [], (string) filemtime($style_path));

    if (duola_pocket_is_wall_page()) {
        $wall_script_path = get_template_directory() . '/assets/wall.js';
        wp_enqueue_script('duola-pocket-wall', get_template_directory_uri() . '/assets/wall.js', [], (string) filemtime($wall_script_path), true);
        wp_localize_script('duola-pocket-wall', 'duolaWall', [
            'messagesUrl' => esc_url_raw(rest_url('duola/v1/messages')),
            'nonce' => wp_create_nonce('duola_wall_submit'),
            'anonymous' => __('anonymous', 'duola-pocket'),
            'networkError' => __('连接失败，请稍后重试。', 'duola-pocket'),
        ]);
        return;
    }

    $script_path = get_template_directory() . '/assets/site.js';
    wp_enqueue_script('duola-pocket-site', get_template_directory_uri() . '/assets/site.js', [], (string) filemtime($script_path), true);
}
add_action('wp_enqueue_scripts', 'duola_pocket_enqueue_assets');

function duola_pocket_register_site_settings(): void
{
    register_setting('duola_site_settings', 'duola_site_avatar_id', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0,
    ]);
    register_setting('duola_site_settings', 'blogname', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    register_setting('duola_site_settings', 'blogdescription', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
}
add_action('admin_init', 'duola_pocket_register_site_settings');

function duola_pocket_add_site_settings_page(): void
{
    add_menu_page(
        __('网站设置', 'duola-pocket'),
        __('网站设置', 'duola-pocket'),
        'manage_options',
        'duola-site-settings',
        'duola_pocket_render_site_settings_page',
        'dashicons-admin-settings',
        7
    );
}
add_action('admin_menu', 'duola_pocket_add_site_settings_page');

function duola_pocket_render_site_settings_page(): void
{
    $avatar_id = (int) get_option('duola_site_avatar_id');
    $fallback_url = get_template_directory_uri() . '/assets/images/anime-girl.webp';
    $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
    ?>
    <div class="wrap duola-site-settings-page">
        <?php settings_errors(); ?>
        <div class="duola-settings-heading">
            <span><?php esc_html_e('Pocket settings', 'duola-pocket'); ?></span>
            <h1><?php esc_html_e('网站设置', 'duola-pocket'); ?></h1>
            <p><?php esc_html_e('这里管理网站名称、说明和头像。', 'duola-pocket'); ?></p>
        </div>
        <form action="options.php" method="post">
            <?php settings_fields('duola_site_settings'); ?>
            <div class="duola-settings-grid">
                <section class="duola-settings-card">
                    <h2><?php esc_html_e('基础信息', 'duola-pocket'); ?></h2>
                    <p class="duola-settings-field">
                        <label for="duola-blogname"><?php esc_html_e('网站名称', 'duola-pocket'); ?></label>
                        <input id="duola-blogname" class="regular-text" name="blogname" type="text" value="<?php echo esc_attr(get_option('blogname')); ?>" required>
                    </p>
                    <p class="duola-settings-field">
                        <label for="duola-blogdescription"><?php esc_html_e('一句话说明', 'duola-pocket'); ?></label>
                        <input id="duola-blogdescription" class="regular-text" name="blogdescription" type="text" value="<?php echo esc_attr(get_option('blogdescription')); ?>">
                        <span class="description"><?php esc_html_e('会用于网站简介和搜索摘要，留空也可以。', 'duola-pocket'); ?></span>
                    </p>
                </section>
                <section class="duola-settings-card duola-avatar-setting">
                    <div>
                        <h2><?php esc_html_e('网站头像', 'duola-pocket'); ?></h2>
                        <p><?php esc_html_e('显示在首页右上角，建议使用正方形图片。', 'duola-pocket'); ?></p>
                    </div>
                    <div class="duola-avatar-control">
                        <img id="duola-avatar-preview" src="<?php echo esc_url($avatar_url ?: $fallback_url); ?>" alt="">
                        <div>
                            <input id="duola-site-avatar-id" name="duola_site_avatar_id" type="hidden" value="<?php echo esc_attr($avatar_id); ?>">
                            <button id="duola-select-avatar" class="button button-primary" type="button"><?php esc_html_e('从照片库选择', 'duola-pocket'); ?></button>
                            <button id="duola-remove-avatar" class="button" type="button"<?php echo $avatar_id ? '' : ' hidden'; ?>><?php esc_html_e('恢复默认', 'duola-pocket'); ?></button>
                        </div>
                    </div>
                </section>
                <div class="duola-settings-submit">
                    <?php submit_button(__('保存网站设置', 'duola-pocket'), 'primary', 'submit', false); ?>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function duola_pocket_enqueue_admin_assets(string $hook): void
{
    if ('toplevel_page_duola-site-settings' !== $hook) {
        return;
    }

    wp_enqueue_media();
    $script_path = get_template_directory() . '/assets/theme-admin.js';
    $style_path = get_template_directory() . '/assets/theme-admin.css';
    wp_enqueue_script('duola-pocket-admin', get_template_directory_uri() . '/assets/theme-admin.js', ['jquery'], (string) filemtime($script_path), true);
    wp_enqueue_style('duola-pocket-admin', get_template_directory_uri() . '/assets/theme-admin.css', [], (string) filemtime($style_path));
    wp_localize_script('duola-pocket-admin', 'duolaAppearance', [
        'title' => __('选择网站头像', 'duola-pocket'),
        'button' => __('使用这张图片', 'duola-pocket'),
        'fallback' => get_template_directory_uri() . '/assets/images/anime-girl.webp',
    ]);
}
add_action('admin_enqueue_scripts', 'duola_pocket_enqueue_admin_assets');

function duola_pocket_articles_url(): string
{
    $posts_page_id = (int) get_option('page_for_posts');
    return $posts_page_id ? (string) get_permalink($posts_page_id) : home_url('/articles/');
}

function duola_pocket_wall_url(): string
{
    return home_url('/wall/');
}

function duola_pocket_register_wall_route(): void
{
    add_rewrite_rule('^wall/?$', 'index.php?duola_wall=1', 'top');
    add_rewrite_rule('^about/?$', 'index.php?duola_wall_redirect=1', 'top');
}
add_action('init', 'duola_pocket_register_wall_route');

function duola_pocket_wall_query_vars(array $query_vars): array
{
    $query_vars[] = 'duola_wall';
    $query_vars[] = 'duola_wall_redirect';
    return $query_vars;
}
add_filter('query_vars', 'duola_pocket_wall_query_vars');

function duola_pocket_is_wall_page(): bool
{
    return '1' === (string) get_query_var('duola_wall');
}

function duola_pocket_prepare_wall_page(): void
{
    if ('1' === (string) get_query_var('duola_wall_redirect')) {
        wp_safe_redirect(duola_pocket_wall_url(), 301);
        exit;
    }

    if (!duola_pocket_is_wall_page()) {
        return;
    }
    global $wp_query;
    $wp_query->is_404 = false;
    status_header(200);
}
add_action('template_redirect', 'duola_pocket_prepare_wall_page');

function duola_pocket_wall_template(string $template): string
{
    return duola_pocket_is_wall_page() ? get_template_directory() . '/wall.php' : $template;
}
add_filter('template_include', 'duola_pocket_wall_template');

function duola_pocket_wall_document_title(array $title): array
{
    if (duola_pocket_is_wall_page()) {
        $title['title'] = 'wall ddw';
    }
    return $title;
}
add_filter('document_title_parts', 'duola_pocket_wall_document_title');

function duola_pocket_maybe_flush_wall_route(): void
{
    $route_version = '2';
    if ($route_version === get_option('duola_wall_route_version')) {
        return;
    }

    flush_rewrite_rules(false);
    delete_option('duola_about_content');
    delete_option('duola_about_route_version');
    update_option('duola_wall_route_version', $route_version, false);
}
add_action('init', 'duola_pocket_maybe_flush_wall_route', 99);

function duola_pocket_format_date(int $post_id): string
{
    return get_the_date('Y.m.d', $post_id);
}
