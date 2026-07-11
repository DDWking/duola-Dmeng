<?php
get_header();
$latest_albums = new WP_Query([
    'post_type' => 'album',
    'posts_per_page' => 5,
    'post_status' => 'publish',
]);
$featured_id = (int) get_theme_mod('duola_featured_image');
$hero_image_ids = [];
$add_hero_image = static function (int $image_id) use (&$hero_image_ids): void {
    if ($image_id && !in_array($image_id, $hero_image_ids, true) && count($hero_image_ids) < 6) {
        $hero_image_ids[] = $image_id;
    }
};

$add_hero_image($featured_id);
foreach ($latest_albums->posts as $album) {
    $add_hero_image(duola_albums_get_cover_id((int) $album->ID));
    foreach (duola_albums_get_photos((int) $album->ID) as $photo) {
        $add_hero_image((int) $photo['id']);
    }
}
?>
<section class="hero desktop-wallpaper <?php echo $hero_image_ids ? 'hero-photo' : 'hero-pocket'; ?>"<?php echo count($hero_image_ids) > 1 ? ' data-hero-carousel data-interval="6500"' : ''; ?>>
    <?php if ($hero_image_ids) : ?>
        <div class="hero-slides">
            <?php foreach ($hero_image_ids as $index => $image_id) : ?>
                <div class="hero-slide <?php echo 0 === $index ? 'is-active' : ''; ?>" data-hero-slide aria-hidden="<?php echo 0 === $index ? 'false' : 'true'; ?>">
                    <?php echo wp_get_attachment_image($image_id, 'full', false, [
                        'class' => 'hero-image',
                        'loading' => 0 === $index ? 'eager' : 'lazy',
                        'fetchpriority' => 0 === $index ? 'high' : 'auto',
                        'sizes' => '100vw',
                        'alt' => '',
                    ]); ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <div class="hero-content">
        <h1 class="hero-title"><span>某年某月某天</span></h1>
        <p class="hero-copy">Stay alive!</p>
    </div>
    <?php if (count($hero_image_ids) > 1) : ?>
        <div class="hero-carousel-controls" aria-label="首页照片轮播">
            <button type="button" class="hero-carousel-arrow" data-carousel-previous aria-label="上一张照片">‹</button>
            <button type="button" class="hero-carousel-toggle" data-carousel-toggle aria-label="暂停轮播">暂停</button>
            <button type="button" class="hero-carousel-arrow" data-carousel-next aria-label="下一张照片">›</button>
            <span class="screen-reader-text" data-carousel-status aria-live="polite"></span>
        </div>
    <?php endif; ?>
</section>
<?php get_footer(); ?>
