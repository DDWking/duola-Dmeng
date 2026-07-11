<?php
get_header();
$desktop_image_id = duola_pocket_get_desktop_image_id();
$desktop_caption = get_theme_mod('duola_desktop_caption', '某年某月某天');
$desktop_note = get_theme_mod('duola_desktop_note', 'Stay alive!');
$desktop_image_position = get_theme_mod('duola_desktop_image_position', 'center center');
?>
<section class="editorial-desktop<?php echo $desktop_image_id ? ' has-image' : ' no-image'; ?>" aria-labelledby="desktop-title" style="--desktop-image-position: <?php echo esc_attr($desktop_image_position); ?>;">
    <?php if ($desktop_image_id) : ?>
        <div class="editorial-desktop-image" aria-hidden="true">
            <?php echo wp_get_attachment_image($desktop_image_id, 'full', false, [
                'loading' => 'eager',
                'fetchpriority' => 'high',
                'sizes' => '100vw',
                'alt' => '',
            ]); ?>
        </div>
    <?php endif; ?>
    <div class="editorial-caption">
        <h1 id="desktop-title">哆啦D梦的口袋</h1>
        <?php if ($desktop_caption) : ?><p><?php echo esc_html($desktop_caption); ?></p><?php endif; ?>
        <?php if ($desktop_note) : ?><span><?php echo esc_html($desktop_note); ?></span><?php endif; ?>
    </div>
</section>
<?php get_footer(); ?>
