<?php get_header(); ?>
<?php while (have_posts()) : the_post(); ?>
    <?php
    $anime_id = get_the_ID();
    $poster_id = duola_anime_get_poster_id($anime_id);
    $score = duola_anime_get_score($anime_id);
    $alt_title = (string) get_post_meta($anime_id, '_duola_anime_alt_title', true);
    $year = (int) get_post_meta($anime_id, '_duola_anime_year', true);
    $episodes = (int) get_post_meta($anime_id, '_duola_anime_episodes', true);
    $note = duola_anime_get_note($anime_id);
    $reviews = duola_anime_get_reviews($anime_id);
    $rank = null;
    $previous_score = null;
    $current_rank = null;
    foreach (duola_anime_get_ranked_posts() as $index => $ranked_post) {
        $ranked_score = duola_anime_get_score((int) $ranked_post->ID);
        if (null !== $ranked_score && (null === $previous_score || abs($ranked_score - $previous_score) > 0.001)) {
            $current_rank = $index + 1;
        }
        if ((int) $ranked_post->ID === $anime_id) {
            $rank = null === $ranked_score ? null : $current_rank;
            break;
        }
        $previous_score = $ranked_score;
    }
    ?>
    <article class="anime-entry">
        <a class="anime-entry-back" href="<?php echo esc_url(get_post_type_archive_link('anime')); ?>" aria-label="返回异世界">←</a>
        <header class="anime-entry-hero">
            <figure class="anime-entry-poster">
                <?php if ($poster_id) : ?>
                    <?php echo wp_get_attachment_image($poster_id, 'duola-anime-poster', false, [
                        'loading' => 'eager',
                        'decoding' => 'async',
                        'fetchpriority' => 'high',
                        'sizes' => '(max-width: 760px) 82vw, 40vw',
                        'alt' => get_the_title(),
                    ]); ?>
                <?php else : ?>
                    <span class="anime-poster-placeholder"><i>NO IMAGE</i><b><?php the_title(); ?></b></span>
                <?php endif; ?>
                <figcaption><?php echo null === $rank ? 'UNRANKED' : 'RANK ' . esc_html(str_pad((string) $rank, 2, '0', STR_PAD_LEFT)); ?></figcaption>
            </figure>
            <div class="anime-entry-copy">
                <span class="anime-entry-kicker">ISEKAI ARCHIVE</span>
                <h1><?php the_title(); ?></h1>
                <?php if ($alt_title) : ?><p class="anime-entry-alt-title"><?php echo esc_html($alt_title); ?></p><?php endif; ?>
                <div class="anime-entry-score<?php echo null === $score ? ' is-unrated' : ''; ?>"><strong><?php echo null === $score ? 'UNRATED' : esc_html(number_format($score, 1)); ?></strong><?php if (null !== $score) : ?><span>/ 10</span><?php endif; ?></div>
                <?php if ($year || $episodes) : ?>
                    <dl class="anime-entry-facts">
                        <?php if ($year) : ?><div><dt>YEAR</dt><dd><?php echo esc_html($year); ?></dd></div><?php endif; ?>
                        <?php if ($episodes) : ?><div><dt>EPISODES</dt><dd><?php echo esc_html($episodes); ?></dd></div><?php endif; ?>
                    </dl>
                <?php endif; ?>
                <?php if ($note) : ?><div class="anime-entry-note"><?php echo wp_kses_post(wpautop($note)); ?></div><?php endif; ?>
            </div>
        </header>

        <?php if ($reviews) : ?>
            <section class="anime-review-list" aria-labelledby="anime-review-heading">
                <header><span>NOTES &amp; REVIEWS</span><h2 id="anime-review-heading">相关文字</h2></header>
                <div>
                    <?php foreach ($reviews as $review) : ?>
                        <a href="<?php echo esc_url(get_permalink($review)); ?>">
                            <time datetime="<?php echo esc_attr(get_the_date('c', $review)); ?>"><?php echo esc_html(get_the_date('Y.m.d', $review)); ?></time>
                            <strong><?php echo esc_html(get_the_title($review)); ?></strong>
                            <span aria-hidden="true">↗</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>
<?php endwhile; ?>
<?php get_footer(); ?>
