<?php

if (!defined('ABSPATH')) {
    exit;
}

function duola_admin_customize_menu(): void
{
    global $menu, $submenu;

    remove_menu_page('edit.php?post_type=page');
    remove_menu_page('edit-comments.php');
    remove_menu_page('themes.php');
    remove_menu_page('plugins.php');
    remove_menu_page('users.php');
    remove_menu_page('tools.php');
    remove_menu_page('options-general.php');
    remove_submenu_page('index.php', 'update-core.php');
    remove_submenu_page('edit.php', 'edit-tags.php?taxonomy=category');

    foreach ($menu as $position => $item) {
        if (isset($item[2]) && str_starts_with((string) $item[2], 'separator')) {
            unset($menu[$position]);
        }
    }

    if (isset($menu[2])) {
        $menu[2][0] = __('首页', 'duola-albums');
    }
    if (isset($submenu['index.php'][0])) {
        $submenu['index.php'][0][0] = __('首页', 'duola-albums');
    }
    if (isset($menu[5])) {
        $menu[5][0] = __('文章', 'duola-albums');
    }
}
add_action('admin_menu', 'duola_admin_customize_menu', 999);

function duola_admin_detach_categories(): void
{
    unregister_taxonomy_for_object_type('category', 'post');
}
add_action('init', 'duola_admin_detach_categories', 20);

function duola_admin_simplify_post_types(): void
{
    foreach (['post', 'album'] as $post_type) {
        remove_post_type_support($post_type, 'author');
        remove_post_type_support($post_type, 'comments');
        remove_post_type_support($post_type, 'trackbacks');
        remove_post_type_support($post_type, 'custom-fields');
    }

    remove_post_type_support('post', 'excerpt');
}
add_action('init', 'duola_admin_simplify_post_types', 30);

function duola_admin_remove_meta_boxes(): void
{
    foreach (['post', 'album'] as $post_type) {
        remove_meta_box('authordiv', $post_type, 'normal');
        remove_meta_box('commentstatusdiv', $post_type, 'normal');
        remove_meta_box('commentsdiv', $post_type, 'normal');
        remove_meta_box('trackbacksdiv', $post_type, 'normal');
        remove_meta_box('postcustom', $post_type, 'normal');
        remove_meta_box('slugdiv', $post_type, 'normal');
    }

    remove_meta_box('categorydiv', 'post', 'side');
    remove_meta_box('postexcerpt', 'post', 'normal');
    remove_meta_box('postimagediv', 'album', 'side');
}
add_action('add_meta_boxes', 'duola_admin_remove_meta_boxes', 100);

function duola_admin_allowed_blocks($allowed_blocks, $editor_context)
{
    if (empty($editor_context->post) || 'post' !== $editor_context->post->post_type) {
        return $allowed_blocks;
    }

    return [
        'core/paragraph',
        'core/heading',
        'core/image',
        'core/gallery',
        'core/list',
        'core/list-item',
        'core/quote',
        'core/code',
        'core/preformatted',
        'core/separator',
        'core/spacer',
        'core/audio',
        'core/video',
        'core/file',
        'core/embed',
    ];
}
add_filter('allowed_block_types_all', 'duola_admin_allowed_blocks', 10, 2);

function duola_admin_editor_settings(array $settings, $editor_context): array
{
    if (!empty($editor_context->post) && 'post' === $editor_context->post->post_type) {
        $settings['enableOpenverseMediaCategory'] = false;
        $settings['canLockBlocks'] = false;
        $settings['codeEditingEnabled'] = false;
        $settings['__experimentalBlockPatterns'] = [];
        $settings['__experimentalBlockPatternCategories'] = [];
    }

    return $settings;
}
add_filter('block_editor_settings_all', 'duola_admin_editor_settings', 10, 2);

function duola_admin_post_columns(array $columns): array
{
    return [
        'cb' => $columns['cb'],
        'duola_thumbnail' => __('封面', 'duola-albums'),
        'title' => __('文章', 'duola-albums'),
        'tags' => __('标签', 'duola-albums'),
        'date' => __('状态与日期', 'duola-albums'),
    ];
}
add_filter('manage_post_posts_columns', 'duola_admin_post_columns');

function duola_admin_render_post_column(string $column, int $post_id): void
{
    if ('duola_thumbnail' !== $column) {
        return;
    }

    echo has_post_thumbnail($post_id)
        ? get_the_post_thumbnail($post_id, 'thumbnail')
        : '<span class="duola-no-cover">' . esc_html__('纯文字', 'duola-albums') . '</span>';
}
add_action('manage_post_posts_custom_column', 'duola_admin_render_post_column', 10, 2);

function duola_admin_simplify_bar(WP_Admin_Bar $admin_bar): void
{
    foreach (['wp-logo', 'updates', 'comments', 'customize', 'themes', 'widgets', 'menus', 'new-page', 'new-user'] as $node) {
        $admin_bar->remove_node($node);
    }
}
add_action('admin_bar_menu', 'duola_admin_simplify_bar', 999);

function duola_admin_hide_screen_options(bool $show_screen): bool
{
    return false;
}
add_filter('screen_options_show_screen', 'duola_admin_hide_screen_options');

function duola_admin_remove_help_tabs(): void
{
    $screen = get_current_screen();
    if ($screen) {
        $screen->remove_help_tabs();
    }
}
add_action('admin_head', 'duola_admin_remove_help_tabs');

function duola_admin_footer_text(): string
{
    return esc_html__('哆啦D梦的口袋 · Stay alive', 'duola-albums');
}
add_filter('admin_footer_text', 'duola_admin_footer_text');
add_filter('update_footer', '__return_empty_string', 20);

function duola_admin_dashboard_label(string $translation, string $text): string
{
    global $pagenow;

    if (!is_admin() && 'wp-login.php' !== $pagenow) {
        return $translation;
    }

    if ('Dashboard' === $text) {
        return __('首页', 'duola-albums');
    }

    return str_ireplace('WordPress', get_bloginfo('name'), $translation);
}
add_filter('gettext', 'duola_admin_dashboard_label', 10, 2);

function duola_admin_dashboard_setup(): void
{
    global $wp_meta_boxes;
    remove_all_actions('welcome_panel');
    $wp_meta_boxes['dashboard'] = [];
    wp_add_dashboard_widget('duola_dashboard', __('我的口袋', 'duola-albums'), 'duola_admin_render_dashboard');
}
add_action('wp_dashboard_setup', 'duola_admin_dashboard_setup', 999);

function duola_admin_dashboard_columns(array $columns): array
{
    $columns['dashboard'] = 1;
    return $columns;
}
add_filter('screen_layout_columns', 'duola_admin_dashboard_columns');

function duola_admin_dashboard_layout($layout): int
{
    return 1;
}
add_filter('get_user_option_screen_layout_dashboard', 'duola_admin_dashboard_layout');

function duola_admin_login_title(string $login_title, string $title): string
{
    return sprintf('%1$s ‹ %2$s', $title, get_bloginfo('name'));
}
add_filter('login_title', 'duola_admin_login_title', 10, 2);

function duola_admin_login_header_url(): string
{
    return home_url('/');
}
add_filter('login_headerurl', 'duola_admin_login_header_url');

function duola_admin_login_header_text(): string
{
    return (string) get_bloginfo('name');
}
add_filter('login_headertext', 'duola_admin_login_header_text');

function duola_admin_login_message(string $message): string
{
    $avatar_id = (int) get_option('duola_site_avatar_id');
    $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
    if (!$avatar_url) {
        $avatar_url = get_template_directory_uri() . '/assets/images/anime-girl.webp';
    }

    $brand = '<div class="duola-login-brand">';
    $brand .= '<img src="' . esc_url($avatar_url) . '" alt="">';
    $brand .= '<div><span>' . esc_html__('PRIVATE POCKET', 'duola-albums') . '</span><strong>' . esc_html(get_bloginfo('name')) . '</strong></div>';
    $brand .= '</div>';
    return $brand . $message;
}
add_filter('login_message', 'duola_admin_login_message');

function duola_admin_site_icon_url(string $url, int $size, int $blog_id): string
{
    if ($url) {
        return $url;
    }

    $avatar_id = (int) get_option('duola_site_avatar_id');
    $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, [$size, $size]) : '';
    return $avatar_url ?: get_template_directory_uri() . '/assets/images/anime-girl.webp';
}
add_filter('get_site_icon_url', 'duola_admin_site_icon_url', 10, 3);

remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');

function duola_admin_content_count(string $post_type): int
{
    $counts = wp_count_posts($post_type);
    return isset($counts->publish) ? (int) $counts->publish : 0;
}

function duola_admin_image_count(): int
{
    global $wpdb;
    return (int) $wpdb->get_var("SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_mime_type LIKE 'image/%'");
}

function duola_admin_render_recent_items(string $post_type, string $empty_text): void
{
    $items = get_posts([
        'post_type' => $post_type,
        'post_status' => ['publish', 'draft'],
        'numberposts' => 4,
        'orderby' => 'modified',
        'order' => 'DESC',
    ]);

    if (!$items) {
        echo '<p class="duola-dashboard-empty">' . esc_html($empty_text) . '</p>';
        return;
    }

    echo '<ul class="duola-dashboard-recent">';
    foreach ($items as $item) {
        $status = 'publish' === $item->post_status ? __('已发布', 'duola-albums') : __('草稿', 'duola-albums');
        echo '<li><a href="' . esc_url(get_edit_post_link($item->ID)) . '"><span>' . esc_html(get_the_title($item) ?: __('未命名', 'duola-albums')) . '</span><small>' . esc_html($status . ' · ' . get_the_modified_date('m.d', $item)) . '</small></a></li>';
    }
    echo '</ul>';
}

function duola_admin_render_dashboard(): void
{
    $avatar_id = (int) get_option('duola_site_avatar_id');
    $avatar = $avatar_id ? wp_get_attachment_image($avatar_id, 'thumbnail', false, ['alt' => '']) : '';
    $theme_count = wp_count_terms(['taxonomy' => 'album_theme', 'hide_empty' => false]);
    $theme_count = is_wp_error($theme_count) ? 0 : (int) $theme_count;
    ?>
    <div class="duola-dashboard-shell">
        <div class="duola-dashboard-overview">
            <header class="duola-dashboard-hero">
                <div class="duola-dashboard-identity">
                    <div class="duola-dashboard-avatar"><?php echo $avatar ?: '<span class="dashicons dashicons-format-image" aria-hidden="true"></span>'; ?></div>
                    <div>
                        <span class="duola-dashboard-kicker"><?php echo esc_html(wp_date('Y.m.d')); ?> · <?php esc_html_e('STAY ALIVE', 'duola-albums'); ?></span>
                        <h2><?php echo esc_html(wp_get_current_user()->display_name); ?>，今天想记录什么？</h2>
                        <p><?php echo esc_html(get_option('blogdescription') ?: __('照片和文字，都收进自己的口袋里。', 'duola-albums')); ?></p>
                    </div>
                </div>
                <a class="duola-dashboard-site-link" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="dashicons dashicons-external" aria-hidden="true"></span><?php esc_html_e('查看网站', 'duola-albums'); ?>
                </a>
            </header>

            <section class="duola-dashboard-stats" aria-label="<?php esc_attr_e('内容统计', 'duola-albums'); ?>">
                <a href="<?php echo esc_url(admin_url('edit.php')); ?>"><i class="dashicons dashicons-text-page" aria-hidden="true"></i><strong><?php echo esc_html(duola_admin_content_count('post')); ?></strong><span><?php esc_html_e('篇文章', 'duola-albums'); ?></span></a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=album')); ?>"><i class="dashicons dashicons-format-gallery" aria-hidden="true"></i><strong><?php echo esc_html(duola_admin_content_count('album')); ?></strong><span><?php esc_html_e('本相册', 'duola-albums'); ?></span></a>
                <a href="<?php echo esc_url(admin_url('upload.php')); ?>"><i class="dashicons dashicons-images-alt2" aria-hidden="true"></i><strong><?php echo esc_html(duola_admin_image_count()); ?></strong><span><?php esc_html_e('张照片', 'duola-albums'); ?></span></a>
                <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=album_theme&post_type=album')); ?>"><i class="dashicons dashicons-category" aria-hidden="true"></i><strong><?php echo esc_html($theme_count); ?></strong><span><?php esc_html_e('个主题', 'duola-albums'); ?></span></a>
            </section>
        </div>

        <section class="duola-dashboard-actions">
            <h3><?php esc_html_e('快捷开始', 'duola-albums'); ?></h3>
            <div>
                <a class="duola-action-primary" href="<?php echo esc_url(admin_url('post-new.php')); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span><b><?php esc_html_e('写文章', 'duola-albums'); ?></b><small><?php esc_html_e('打开简洁编辑器', 'duola-albums'); ?></small></a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=album')); ?>"><span class="dashicons dashicons-format-gallery" aria-hidden="true"></span><b><?php esc_html_e('建相册', 'duola-albums'); ?></b><small><?php esc_html_e('一次上传多张照片', 'duola-albums'); ?></small></a>
                <a href="<?php echo esc_url(admin_url('upload.php')); ?>"><span class="dashicons dashicons-images-alt2" aria-hidden="true"></span><b><?php esc_html_e('照片库', 'duola-albums'); ?></b><small><?php esc_html_e('查找和编辑原图', 'duola-albums'); ?></small></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=duola-migration')); ?>"><span class="dashicons dashicons-migrate" aria-hidden="true"></span><b><?php esc_html_e('备份迁移', 'duola-albums'); ?></b><small><?php esc_html_e('导出或导入 ZIP', 'duola-albums'); ?></small></a>
            </div>
        </section>

        <div class="duola-dashboard-columns">
            <section><div class="duola-dashboard-section-heading"><h3><?php esc_html_e('最近文章', 'duola-albums'); ?></h3><a href="<?php echo esc_url(admin_url('edit.php')); ?>"><?php esc_html_e('全部', 'duola-albums'); ?></a></div><?php duola_admin_render_recent_items('post', __('还没有文章。', 'duola-albums')); ?></section>
            <section><div class="duola-dashboard-section-heading"><h3><?php esc_html_e('最近相册', 'duola-albums'); ?></h3><a href="<?php echo esc_url(admin_url('edit.php?post_type=album')); ?>"><?php esc_html_e('全部', 'duola-albums'); ?></a></div><?php duola_admin_render_recent_items('album', __('还没有相册。', 'duola-albums')); ?></section>
        </div>
    </div>
    <?php
}

function duola_admin_enqueue_theme(): void
{
    $style_path = DUOLA_ALBUMS_PATH . 'assets/admin-theme.css';
    wp_enqueue_style('duola-admin-theme', DUOLA_ALBUMS_URL . 'assets/admin-theme.css', [], (string) filemtime($style_path));
}
add_action('admin_enqueue_scripts', 'duola_admin_enqueue_theme', 100);
add_action('login_enqueue_scripts', 'duola_admin_enqueue_theme');
