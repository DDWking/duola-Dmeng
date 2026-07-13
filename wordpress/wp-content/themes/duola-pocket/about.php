<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('about-man-page'); ?> data-home-url="<?php echo esc_url(home_url('/')); ?>">
<?php wp_body_open(); ?>
<main class="man-page" data-man-page>
    <header class="man-page-header" aria-label="man page header">
        <span>DDW(1)</span>
        <span><?php bloginfo('name'); ?></span>
        <span>DDW(1)</span>
    </header>
    <pre tabindex="0" data-man-content><?php echo esc_html(get_option('duola_about_content', duola_pocket_default_about_content())); ?></pre>
</main>
<nav class="man-command-bar" aria-label="man page commands">
    <button type="button" data-man-command="down"><kbd>j</kbd> down</button>
    <button type="button" data-man-command="up"><kbd>k</kbd> up</button>
    <button type="button" data-man-command="search"><kbd>/</kbd> find</button>
    <button type="button" data-man-command="quit"><kbd>q</kbd> quit</button>
</nav>
<form class="man-search" data-man-search hidden>
    <label>/<input type="search" data-man-search-input autocomplete="off" spellcheck="false" aria-label="Search this man page"></label>
    <span class="man-search-status" data-man-search-status aria-live="polite"></span>
    <button type="submit">next</button>
    <button type="button" data-man-search-close>esc</button>
</form>
<?php wp_footer(); ?>
</body>
</html>
