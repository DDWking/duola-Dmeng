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

function duola_pocket_articles_url(): string
{
    $posts_page_id = (int) get_option('page_for_posts');
    return $posts_page_id ? (string) get_permalink($posts_page_id) : home_url('/articles/');
}

function duola_pocket_format_date(int $post_id): string
{
    return get_the_date('Y.m.d', $post_id);
}
