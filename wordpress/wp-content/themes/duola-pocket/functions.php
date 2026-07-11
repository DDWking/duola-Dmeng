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
    add_editor_style('style.css');

    add_image_size('duola-album-card', 960, 720, true);
    add_image_size('duola-home-note', 720, 960, true);
    add_image_size('duola-lightbox', 2048, 2048, false);
}
add_action('after_setup_theme', 'duola_pocket_setup');

function duola_pocket_enqueue_assets(): void
{
    $style_path = get_stylesheet_directory() . '/style.css';
    $script_path = get_template_directory() . '/assets/site.js';
    wp_enqueue_style('duola-pocket-style', get_stylesheet_uri(), [], (string) filemtime($style_path));
    wp_enqueue_script('duola-pocket-site', get_template_directory_uri() . '/assets/site.js', [], (string) filemtime($script_path), true);
}
add_action('wp_enqueue_scripts', 'duola_pocket_enqueue_assets');

function duola_pocket_register_appearance_setting(): void
{
    register_setting('duola_appearance', 'duola_site_avatar_id', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0,
    ]);
}
add_action('admin_init', 'duola_pocket_register_appearance_setting');

function duola_pocket_add_appearance_page(): void
{
    add_theme_page(
        __('网站外观', 'duola-pocket'),
        __('网站外观', 'duola-pocket'),
        'manage_options',
        'duola-appearance',
        'duola_pocket_render_appearance_page'
    );
}
add_action('admin_menu', 'duola_pocket_add_appearance_page');

function duola_pocket_render_appearance_page(): void
{
    $avatar_id = (int) get_option('duola_site_avatar_id');
    $fallback_url = get_template_directory_uri() . '/assets/images/anime-girl.webp';
    $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
    ?>
    <div class="wrap duola-appearance-page">
        <h1><?php esc_html_e('网站外观', 'duola-pocket'); ?></h1>
        <p><?php esc_html_e('设置首页右上角显示的网站头像。', 'duola-pocket'); ?></p>
        <form action="options.php" method="post">
            <?php settings_fields('duola_appearance'); ?>
            <div class="duola-avatar-setting">
                <img id="duola-avatar-preview" src="<?php echo esc_url($avatar_url ?: $fallback_url); ?>" alt="">
                <div>
                    <input id="duola-site-avatar-id" name="duola_site_avatar_id" type="hidden" value="<?php echo esc_attr($avatar_id); ?>">
                    <button id="duola-select-avatar" class="button button-primary" type="button"><?php esc_html_e('从照片库选择头像', 'duola-pocket'); ?></button>
                    <button id="duola-remove-avatar" class="button" type="button"<?php echo $avatar_id ? '' : ' hidden'; ?>><?php esc_html_e('恢复默认头像', 'duola-pocket'); ?></button>
                    <p class="description"><?php esc_html_e('建议使用正方形图片，网站会自动裁成圆形。', 'duola-pocket'); ?></p>
                </div>
            </div>
            <?php submit_button(__('保存头像', 'duola-pocket')); ?>
        </form>
    </div>
    <?php
}

function duola_pocket_enqueue_admin_assets(string $hook): void
{
    if ('appearance_page_duola-appearance' !== $hook) {
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

function duola_pocket_format_date(int $post_id): string
{
    return get_the_date('Y.m.d', $post_id);
}
