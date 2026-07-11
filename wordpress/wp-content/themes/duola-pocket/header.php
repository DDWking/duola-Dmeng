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
$is_desktop = is_front_page();
$apps = duola_pocket_get_apps();
$current_app_key = duola_pocket_get_current_app();
$current_app = $current_app_key && isset($apps[$current_app_key]) ? $apps[$current_app_key] : null;
$window_label = $current_app['label'] ?? (get_the_title() ?: __('内容', 'duola-pocket'));
?>
<div class="desktop-shell<?php echo $is_desktop ? ' is-home' : ' has-open-app'; ?>">
    <header class="system-bar">
        <a class="site-brand" href="<?php echo esc_url(home_url('/')); ?>">
            <span class="site-brand-mark" aria-hidden="true"></span>
            <span>哆啦D梦的口袋</span>
        </a>
        <div class="system-status">
            <span class="system-indicator"><span aria-hidden="true"></span>ONLINE</span>
            <a class="system-about" href="<?php echo esc_url(home_url('/about/')); ?>">关于</a>
            <time data-system-clock datetime="<?php echo esc_attr(current_time('c')); ?>"><?php echo esc_html(wp_date('m月d日 H:i')); ?></time>
        </div>
    </header>
    <?php if (!$is_desktop) : ?>
        <section class="app-window" aria-label="<?php echo esc_attr($window_label); ?>应用窗口">
            <header class="app-window-bar">
                <a class="app-window-home" href="<?php echo esc_url(home_url('/')); ?>" aria-label="返回桌面">
                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </a>
                <div class="app-window-title">
                    <span class="dashicons <?php echo esc_attr($current_app['icon'] ?? 'dashicons-admin-page'); ?>" aria-hidden="true"></span>
                    <span><?php echo esc_html($window_label); ?></span>
                </div>
                <span class="app-window-context">哆啦D梦的口袋</span>
            </header>
            <div class="app-window-scroll">
    <?php endif; ?>
    <main id="main-content" class="<?php echo $is_desktop ? 'desktop-home' : 'app-content'; ?>">
