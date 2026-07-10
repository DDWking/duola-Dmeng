<?php
get_header();
$years = duola_albums_get_years();
?>
<section class="page-intro">
    <span class="eyebrow">摄影</span>
    <h1>按年份收好<br>每一段光。</h1>
    <?php if ($years) : ?>
        <nav class="year-nav" aria-label="相册年份">
            <?php foreach ($years as $year) : ?>
                <a href="#year-<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>
</section>

<?php if ($years) : ?>
    <?php foreach ($years as $year) : ?>
        <?php $albums = duola_albums_query_by_year($year); ?>
        <?php if ($albums->have_posts()) : ?>
            <section id="year-<?php echo esc_attr($year); ?>" class="section year-section">
                <h2 class="year-title"><?php echo esc_html($year); ?></h2>
                <div class="album-grid">
                    <?php while ($albums->have_posts()) : $albums->the_post(); ?>
                        <?php get_template_part('template-parts/album', 'card'); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
<?php else : ?>
    <p class="empty-state">第一本相册正在准备中。</p>
<?php endif; ?>
<?php get_footer(); ?>
