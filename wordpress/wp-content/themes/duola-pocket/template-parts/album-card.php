<?php
$album_id = get_the_ID();
$cover_id = duola_albums_get_cover_id($album_id);
$year = duola_albums_get_year($album_id);
$location = get_post_meta($album_id, '_duola_album_location', true);
?>
<article class="album-card<?php echo !$cover_id ? ' album-card-empty' : ''; ?>">
    <a href="<?php the_permalink(); ?>">
        <div class="album-card-image">
            <?php if ($cover_id) : ?>
                <?php echo wp_get_attachment_image($cover_id, 'duola-album-card', false, [
                    'loading' => 'lazy',
                    'decoding' => 'async',
                    'sizes' => '(max-width: 620px) 94vw, (max-width: 900px) 46vw, 33vw',
                ]); ?>
            <?php else : ?>
                <div aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <div class="album-card-copy">
            <h3 class="album-card-title"><?php the_title(); ?></h3>
            <p class="album-card-meta">
                <span><?php echo esc_html($year ?: get_the_date('Y', $album_id)); ?></span>
                <?php if ($location) : ?><span><?php echo esc_html($location); ?></span><?php endif; ?>
            </p>
        </div>
    </a>
</article>
