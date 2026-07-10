<?php
$album_id = get_the_ID();
$cover_id = duola_albums_get_cover_id($album_id);
$year = duola_albums_get_year($album_id);
$location = get_post_meta($album_id, '_duola_album_location', true);
?>
<article class="album-card">
    <a href="<?php the_permalink(); ?>">
        <div class="album-card-image">
            <?php if ($cover_id) : ?>
                <?php echo wp_get_attachment_image($cover_id, 'duola-album-card', false, ['loading' => 'lazy']); ?>
            <?php else : ?>
                <div aria-hidden="true"></div>
            <?php endif; ?>
        </div>
        <div class="album-card-copy">
            <h3 class="album-card-title"><?php the_title(); ?></h3>
            <p class="album-card-meta"><?php echo esc_html($year ?: get_the_date('Y', $album_id)); ?><?php echo $location ? ' · ' . esc_html($location) : ''; ?></p>
        </div>
    </a>
</article>
