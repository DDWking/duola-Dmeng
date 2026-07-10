<?php
get_header();
while (have_posts()) : the_post();
    $photos = duola_albums_get_photos(get_the_ID());
    $year = duola_albums_get_year(get_the_ID());
    $location = get_post_meta(get_the_ID(), '_duola_album_location', true);
    $description = get_the_content();
?>
<section class="album-header">
    <span class="eyebrow"><?php echo esc_html($year ?: __('相册', 'duola-pocket')); ?><?php echo $location ? ' · ' . esc_html($location) : ''; ?></span>
    <h1><?php the_title(); ?></h1>
    <?php if ($description) : ?><div class="album-description"><?php echo wp_kses_post(wpautop($description)); ?></div><?php endif; ?>
</section>

<?php if ($photos) : ?>
    <section class="photo-grid" data-lightbox-gallery>
        <?php foreach ($photos as $index => $photo) : ?>
            <?php $full = wp_get_attachment_image_url($photo['id'], 'duola-lightbox') ?: wp_get_attachment_image_url($photo['id'], 'full'); ?>
            <button class="photo-button" type="button" data-lightbox-image="<?php echo esc_url($full); ?>" data-lightbox-caption="<?php echo esc_attr($photo['caption']); ?>" aria-label="查看照片 <?php echo esc_attr($index + 1); ?>">
                <?php echo wp_get_attachment_image($photo['id'], 'large', false, ['loading' => 'lazy']); ?>
            </button>
        <?php endforeach; ?>
    </section>
<?php else : ?>
    <p class="empty-state">这个相册还没有照片。</p>
<?php endif; ?>
<?php endwhile; get_footer(); ?>
