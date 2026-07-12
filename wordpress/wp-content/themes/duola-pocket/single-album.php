<?php
get_header();
while (have_posts()) : the_post();
    $photos = duola_albums_get_photos(get_the_ID());
    $year = duola_albums_get_year(get_the_ID());
    $location = get_post_meta(get_the_ID(), '_duola_album_location', true);
    $description = duola_albums_get_description(get_the_ID());
?>
<section class="album-header">
    <div class="album-meta">
        <span><?php echo esc_html($year ?: get_the_date('Y')); ?></span>
        <?php if ($location) : ?><span><?php echo esc_html($location); ?></span><?php endif; ?>
        <span><?php echo esc_html(sprintf(__('%d 张照片', 'duola-pocket'), count($photos))); ?></span>
    </div>
    <h1><?php the_title(); ?></h1>
    <?php if ($description) : ?><div class="album-description"><?php echo wp_kses_post(wpautop($description)); ?></div><?php endif; ?>
</section>

<?php if ($photos) : ?>
    <section class="photo-grid <?php echo 1 === count($photos) ? 'photo-grid-single' : 'photo-grid-masonry'; ?>" data-lightbox-gallery data-gallery-title="<?php echo esc_attr(get_the_title()); ?>">
        <?php foreach ($photos as $index => $photo) : ?>
            <?php
            $full = wp_get_attachment_image_url($photo['id'], 'duola-lightbox') ?: wp_get_attachment_image_url($photo['id'], 'full');
            $settings = $photo['settings'] ?? [];
            $metadata = wp_get_attachment_metadata((int) $photo['id']);
            $photo_width = max(1, (int) ($metadata['width'] ?? 4));
            $photo_height = max(1, (int) ($metadata['height'] ?? 3));
            $photo_ratio = $photo_width / $photo_height;
            $photo_basis = round($photo_ratio * 310, 2);
            ?>
            <button class="photo-button" type="button" style="--photo-ratio: <?php echo esc_attr(number_format($photo_ratio, 4, '.', '')); ?>; --photo-basis: <?php echo esc_attr($photo_basis); ?>px;"
                data-lightbox-image="<?php echo esc_url($full); ?>"
                data-lightbox-srcset="<?php echo esc_attr(wp_get_attachment_image_srcset($photo['id'], 'duola-lightbox') ?: ''); ?>"
                data-lightbox-sizes="(max-width: 620px) 82vw, 82vw"
                data-lightbox-key="<?php echo esc_attr($photo['id']); ?>"
                data-lightbox-caption="<?php echo esc_attr($photo['caption']); ?>"
                data-lightbox-headline="<?php echo esc_attr($settings['headline'] ?? ''); ?>"
                data-lightbox-description="<?php echo esc_attr($settings['description'] ?? ''); ?>"
                data-lightbox-date="<?php echo esc_attr($settings['date'] ?? ''); ?>"
                data-lightbox-layout="<?php echo esc_attr($settings['layout'] ?? 'standard'); ?>"
                data-lightbox-text-position="<?php echo esc_attr($settings['text_position'] ?? 'spread'); ?>"
                data-lightbox-focus-x="<?php echo esc_attr($settings['focus_x'] ?? 50); ?>"
                data-lightbox-focus-y="<?php echo esc_attr($settings['focus_y'] ?? 50); ?>"
                data-lightbox-accent="<?php echo esc_attr($settings['accent'] ?? '#009fe8'); ?>"
                data-lightbox-background="<?php echo esc_attr($settings['background'] ?? '#f3f3f0'); ?>"
                aria-label="查看照片 <?php echo esc_attr($index + 1); ?>">
                <?php echo wp_get_attachment_image($photo['id'], 'large', false, [
                    'loading' => 0 === $index ? 'eager' : 'lazy',
                    'decoding' => 'async',
                    'fetchpriority' => 0 === $index ? 'high' : 'auto',
                    'sizes' => '(max-width: 620px) 94vw, (max-width: 900px) 46vw, 42vw',
                ]); ?>
            </button>
        <?php endforeach; ?>
    </section>
<?php else : ?>
    <p class="empty-state">这个相册还没有照片。</p>
<?php endif; ?>
<?php endwhile; get_footer(); ?>
