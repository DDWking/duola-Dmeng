<?php
get_header();

$albums = new WP_Query([
    'post_type' => 'album',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
]);
?>
<section class="section archive-content-list">
    <?php if ($albums->have_posts()) : ?>
        <div class="album-grid album-grid-archive">
            <?php while ($albums->have_posts()) : $albums->the_post(); ?>
                <?php get_template_part('template-parts/album', 'card'); ?>
            <?php endwhile; ?>
        </div>
        <?php wp_reset_postdata(); ?>
    <?php else : ?>
        <p class="empty-state">第一本相册正在准备中。</p>
    <?php endif; ?>
</section>
<?php get_footer(); ?>
