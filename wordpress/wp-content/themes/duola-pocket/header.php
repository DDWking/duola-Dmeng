<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
$photos_url = get_post_type_archive_link('album') ?: home_url('/photos/');
$articles_url = duola_pocket_articles_url();
$asset_url = get_template_directory_uri() . '/assets/images/';
$site_avatar_id = (int) get_option('duola_site_avatar_id');
?>
<div class="site-shell">
    <header class="site-header">
        <nav class="site-nav" aria-label="主导航">
            <a class="<?php echo is_front_page() ? 'is-current' : ''; ?>" href="<?php echo esc_url(home_url('/')); ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>首页</a>
            <a class="<?php echo (is_home() || is_singular('post') || is_tag() || is_category()) ? 'is-current' : ''; ?>" href="<?php echo esc_url($articles_url); ?>"<?php echo (is_home() || is_singular('post') || is_tag() || is_category()) ? ' aria-current="page"' : ''; ?>>文章</a>
            <a class="<?php echo (is_post_type_archive('album') || is_singular('album')) ? 'is-current' : ''; ?>" href="<?php echo esc_url($photos_url); ?>"<?php echo (is_post_type_archive('album') || is_singular('album')) ? ' aria-current="page"' : ''; ?>>相册</a>
        </nav>
        <div class="site-identity">
            <a class="site-avatar-link" href="<?php echo esc_url(duola_pocket_wall_url()); ?>" aria-label="<?php esc_attr_e('打开留言板', 'duola-pocket'); ?>" title="留言板">
                <span class="site-avatar">
                <?php if ($site_avatar_id) : ?>
                    <?php echo wp_get_attachment_image($site_avatar_id, 'thumbnail', false, ['alt' => '']); ?>
                <?php else : ?>
                    <img class="is-default" src="<?php echo esc_url($asset_url . 'anime-girl.webp'); ?>" alt="">
                <?php endif; ?>
                </span>
            </a>
            <a class="site-name" href="<?php echo esc_url(home_url('/')); ?>"><?php bloginfo('name'); ?></a>
        </div>
    </header>
    <main id="main-content" class="site-main<?php echo is_front_page() ? ' is-home' : ''; ?>">
