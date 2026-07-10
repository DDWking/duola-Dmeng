<?php
get_header();
$latest_albums = new WP_Query([
    'post_type' => 'album',
    'posts_per_page' => 5,
    'post_status' => 'publish',
]);
$featured_id = (int) get_theme_mod('duola_featured_image');
if (!$featured_id && !empty($latest_albums->posts)) {
    $featured_id = duola_albums_get_cover_id((int) $latest_albums->posts[0]->ID);
}
$latest_posts = new WP_Query([
    'post_type' => 'post',
    'posts_per_page' => 4,
    'post_status' => 'publish',
]);
?>
<section class="hero <?php echo $featured_id ? 'hero-photo' : 'hero-pocket'; ?>">
    <?php if ($featured_id) : ?>
        <?php echo wp_get_attachment_image($featured_id, 'full', false, [
            'class' => 'hero-image',
            'loading' => 'eager',
            'fetchpriority' => 'high',
            'sizes' => '100vw',
            'alt' => '',
        ]); ?>
    <?php endif; ?>
    <div class="hero-content">
        <h1 class="hero-title"><span>Stay</span><span>alive!</span></h1>
        <p class="hero-copy">某年某月某天</p>
        <div class="hero-actions">
            <a class="button" href="<?php echo esc_url(get_post_type_archive_link('album')); ?>">全部照片</a>
            <a class="button button-secondary" href="<?php echo esc_url(get_permalink(get_option('page_for_posts')) ?: home_url('/')); ?>">读点文字</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <h2>那些曾经</h2>
        <a href="<?php echo esc_url(get_post_type_archive_link('album')); ?>">全部照片</a>
    </div>
    <?php if ($latest_albums->have_posts()) : ?>
        <div class="album-grid album-grid-featured">
            <?php while ($latest_albums->have_posts()) : $latest_albums->the_post(); ?>
                <?php get_template_part('template-parts/album', 'card'); ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    <?php else : ?>
        <p class="empty-state">第一本相册正在准备中。</p>
    <?php endif; ?>
</section>

<section class="section">
    <div class="section-heading">
        <h2>胡思乱想</h2>
        <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts')) ?: home_url('/')); ?>">全部文章</a>
    </div>
    <?php if ($latest_posts->have_posts()) : ?>
        <div class="post-list">
            <?php while ($latest_posts->have_posts()) : $latest_posts->the_post(); ?>
                <?php get_template_part('template-parts/post', 'row'); ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    <?php else : ?>
        <p class="empty-state">这里很快会有第一篇文字。</p>
    <?php endif; ?>
</section>
<?php get_footer(); ?>
