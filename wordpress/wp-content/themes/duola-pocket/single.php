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
    <?php $related_anime = function_exists('duola_anime_get_related_posts') ? duola_anime_get_related_posts(get_the_ID()) : []; ?>
    <?php if ($related_anime) : ?>
        <section class="article-anime-links" aria-labelledby="article-anime-heading">
            <span>CONNECTED STORIES</span>
            <h2 id="article-anime-heading">关联动画</h2>
            <div>
                <?php foreach ($related_anime as $anime) : ?>
                    <?php $poster_id = duola_anime_get_poster_id((int) $anime->ID); ?>
                    <?php $anime_score = duola_anime_get_score((int) $anime->ID); ?>
                    <a href="<?php echo esc_url(get_permalink($anime)); ?>">
                        <?php if ($poster_id) : ?><?php echo wp_get_attachment_image($poster_id, 'thumbnail', false, ['alt' => '']); ?><?php else : ?><i aria-hidden="true">異</i><?php endif; ?>
                        <span><strong><?php echo esc_html(get_the_title($anime)); ?></strong><small><?php echo null === $anime_score ? 'UNRATED' : esc_html(number_format($anime_score, 1)) . ' / 10'; ?></small></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>
<?php endwhile; ?>
<?php get_footer(); ?>
