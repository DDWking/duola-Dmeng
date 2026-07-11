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
    add_image_size('duola-lightbox', 2048, 2048, false);

    register_nav_menus([
        'primary' => __('主导航', 'duola-pocket'),
    ]);
}
add_action('after_setup_theme', 'duola_pocket_setup');

function duola_pocket_enqueue_assets(): void
{
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('dashicons');
    wp_enqueue_style('duola-pocket-style', get_stylesheet_uri(), [], $version);
    wp_enqueue_script('duola-pocket-site', get_template_directory_uri() . '/assets/site.js', [], $version, true);
}
add_action('wp_enqueue_scripts', 'duola_pocket_enqueue_assets');

function duola_pocket_get_apps(): array
{
    $posts_page_id = (int) get_option('page_for_posts');

    return [
        'photos' => [
            'label' => __('照片', 'duola-pocket'),
            'url' => get_post_type_archive_link('album') ?: home_url('/'),
            'icon' => 'dashicons-format-gallery',
        ],
        'articles' => [
            'label' => __('文章', 'duola-pocket'),
            'url' => $posts_page_id ? get_permalink($posts_page_id) : home_url('/'),
            'icon' => 'dashicons-welcome-write-blog',
        ],
        'archive' => [
            'label' => __('归档', 'duola-pocket'),
            'url' => home_url('/archive/'),
            'icon' => 'dashicons-clock',
        ],
        'about' => [
            'label' => __('关于', 'duola-pocket'),
            'url' => home_url('/about/'),
            'icon' => 'dashicons-admin-users',
        ],
    ];
}

function duola_pocket_get_current_app(): ?string
{
    if (is_post_type_archive('album') || is_singular('album')) {
        return 'photos';
    }

    if (is_home() || is_singular('post') || is_tag() || is_category() || (is_archive() && !is_post_type_archive('album'))) {
        return 'articles';
    }

    if (is_page('archive') || is_page_template('page-archive.php')) {
        return 'archive';
    }

    if (is_page('about')) {
        return 'about';
    }

    return null;
}

function duola_pocket_render_app_dock(): void
{
    $current_app = duola_pocket_get_current_app();
    ?>
    <nav class="app-dock" aria-label="应用导航">
        <?php foreach (duola_pocket_get_apps() as $key => $app) : ?>
            <a class="app-dock-item<?php echo $current_app === $key ? ' is-active' : ''; ?>" href="<?php echo esc_url($app['url']); ?>"<?php echo $current_app === $key ? ' aria-current="page"' : ''; ?>>
                <span class="app-icon" aria-hidden="true"><span class="dashicons <?php echo esc_attr($app['icon']); ?>"></span></span>
                <span class="app-dock-label"><?php echo esc_html($app['label']); ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php
}

function duola_pocket_customize_register(WP_Customize_Manager $customize): void
{
    $customize->add_section('duola_homepage', [
        'title' => __('首页', 'duola-pocket'),
        'priority' => 30,
    ]);
    $customize->add_setting('duola_featured_image', [
        'sanitize_callback' => 'absint',
    ]);
    $customize->add_control(new WP_Customize_Media_Control($customize, 'duola_featured_image', [
        'label' => __('首页精选照片', 'duola-pocket'),
        'section' => 'duola_homepage',
        'mime_type' => 'image',
    ]));
}
add_action('customize_register', 'duola_pocket_customize_register');

function duola_pocket_primary_menu_fallback(): void
{
    $links = [
        __('首页', 'duola-pocket') => home_url('/'),
        __('照片', 'duola-pocket') => get_post_type_archive_link('album'),
        __('文章', 'duola-pocket') => get_permalink((int) get_option('page_for_posts')) ?: home_url('/'),
        __('归档', 'duola-pocket') => home_url('/archive/'),
        __('关于', 'duola-pocket') => home_url('/about/'),
    ];

    echo '<ul class="site-nav-list">';
    foreach ($links as $label => $url) {
        printf('<li><a href="%s">%s</a></li>', esc_url($url), esc_html($label));
    }
    echo '</ul>';
}

function duola_pocket_rename_primary_menu_items(array $items, stdClass $args): array
{
    if ('primary' !== ($args->theme_location ?? '')) {
        return $items;
    }

    foreach ($items as $item) {
        if ('摄影' === trim(wp_strip_all_tags($item->title))) {
            $item->title = __('照片', 'duola-pocket');
        }
    }

    return $items;
}
add_filter('wp_nav_menu_objects', 'duola_pocket_rename_primary_menu_items', 10, 2);

function duola_pocket_format_date(int $post_id): string
{
    return get_the_date('Y.m.d', $post_id);
}
