<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
    <a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>">哆啦D梦的口袋</a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">菜单</button>
    <nav id="primary-navigation" class="site-nav" aria-label="主导航">
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container' => false,
            'menu_class' => 'site-nav-list',
            'fallback_cb' => 'duola_pocket_primary_menu_fallback',
        ]);
        ?>
    </nav>
</header>
<main>
