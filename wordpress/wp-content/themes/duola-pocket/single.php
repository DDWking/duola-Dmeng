<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
<article class="article">
    <header class="article-header">
        <span class="eyebrow">文章</span>
        <h1><?php the_title(); ?></h1>
        <div class="article-meta"><?php echo esc_html(duola_pocket_format_date(get_the_ID())); ?></div>
        <?php $tags = get_the_tags(); if ($tags) : ?>
            <div class="tags">
                <?php foreach ($tags as $tag) : ?><a class="tag" href="<?php echo esc_url(get_tag_link($tag)); ?>"><?php echo esc_html($tag->name); ?></a><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>
    <div class="article-content"><?php the_content(); ?></div>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
