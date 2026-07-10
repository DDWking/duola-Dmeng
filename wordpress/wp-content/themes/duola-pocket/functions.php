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
    wp_enqueue_style('duola-pocket-style', get_stylesheet_uri(), [], $version);
    wp_enqueue_script('duola-pocket-site', get_template_directory_uri() . '/assets/site.js', [], $version, true);
}
add_action('wp_enqueue_scripts', 'duola_pocket_enqueue_assets');

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
        __('摄影', 'duola-pocket') => get_post_type_archive_link('album'),
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

function duola_pocket_format_date(int $post_id): string
{
    return get_the_date('Y.m.d', $post_id);
}
