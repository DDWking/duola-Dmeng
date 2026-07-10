<?php
get_header();
$featured_id = (int) get_theme_mod('duola_featured_image');
$featured_url = $featured_id ? wp_get_attachment_image_url($featured_id, 'full') : '';
$latest_albums = new WP_Query([
    'post_type' => 'album',
    'posts_per_page' => 6,
    'post_status' => 'publish',
]);
$latest_posts = new WP_Query([
    'post_type' => 'post',
    'posts_per_page' => 5,
    'post_status' => 'publish',
]);
?>
<section class="hero <?php echo $featured_url ? 'hero-photo' : 'hero-pocket'; ?>">
    <?php if ($featured_url) : ?>
        <img class="hero-image" src="<?php echo esc_url($featured_url); ?>" alt="">
    <?php endif; ?>
    <div class="hero-content">
        <h1 class="hero-title">哆啦D梦的口袋</h1>
        <p class="hero-copy">把路过的光、按下的快门和零碎的想法，收进这个小口袋。</p>
        <div class="hero-actions">
            <a class="button" href="<?php echo esc_url(get_post_type_archive_link('album')); ?>">去看照片</a>
            <a class="button button-secondary" href="<?php echo esc_url(get_permalink(get_option('page_for_posts')) ?: home_url('/')); ?>">读点文字</a>
        </div>
    </div>
</section>

<section class="section">
    <div class="section-heading">
        <div><span class="eyebrow">最近收进的光</span><h2>最新相册</h2></div>
        <a href="<?php echo esc_url(get_post_type_archive_link('album')); ?>">全部摄影 →</a>
    </div>
    <?php if ($latest_albums->have_posts()) : ?>
        <div class="album-grid">
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
        <div><span class="eyebrow">零碎的想法</span><h2>最新文章</h2></div>
        <a href="<?php echo esc_url(get_permalink(get_option('page_for_posts')) ?: home_url('/')); ?>">全部文章 →</a>
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
