<?php get_header(); ?>
<section class="page-intro">
    <span class="eyebrow">文章</span>
    <h1><?php single_post_title(); ?></h1>
</section>
<section class="section">
    <?php if (have_posts()) : ?>
        <div class="post-list">
            <?php while (have_posts()) : the_post(); ?>
                <?php get_template_part('template-parts/post', 'row'); ?>
            <?php endwhile; ?>
        </div>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p class="empty-state">这里还没有文章。</p>
    <?php endif; ?>
</section>
<?php get_footer(); ?>
