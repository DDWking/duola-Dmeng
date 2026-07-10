<article class="post-row">
    <time class="post-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(duola_pocket_format_date(get_the_ID())); ?></time>
    <div>
        <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
        <?php if (has_excerpt()) : ?><p class="post-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
        <?php $tags = get_the_tags(); if ($tags) : ?><div class="tags"><?php foreach ($tags as $tag) : ?><a class="tag" href="<?php echo esc_url(get_tag_link($tag)); ?>"><?php echo esc_html($tag->name); ?></a><?php endforeach; ?></div><?php endif; ?>
    </div>
</article>
