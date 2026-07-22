<?php
get_header();

$anime_posts = function_exists('duola_anime_get_ranked_posts') ? duola_anime_get_ranked_posts() : [];
$ranked_anime = [];
$previous_score = null;
$current_rank = null;

foreach ($anime_posts as $index => $anime_post) {
    $score = duola_anime_get_score((int) $anime_post->ID);
    if (null !== $score && (null === $previous_score || abs($score - $previous_score) > 0.001)) {
        $current_rank = $index + 1;
    }
    if (null === $score) {
        $current_rank = null;
    }
    $ranked_anime[] = [
        'post' => $anime_post,
        'rank' => $current_rank,
        'position' => $index + 1,
    ];
    $previous_score = $score;
}

$top_anime = array_slice($ranked_anime, 0, 3);
$remaining_anime = array_slice($ranked_anime, 3);
?>
<section class="isekai-index">
    <header class="isekai-masthead">
        <div>
            <span>MY ANIME ARCHIVE</span>
            <h1>异世界</h1>
        </div>
        <p><strong><?php echo esc_html(str_pad((string) count($ranked_anime), 2, '0', STR_PAD_LEFT)); ?></strong><span>STORIES</span></p>
    </header>

    <?php if ($ranked_anime) : ?>
        <section class="anime-top-ranking" aria-label="动画排行前三名">
            <?php foreach ($top_anime as $entry) : ?>
                <?php
                $post = $entry['post'];
                setup_postdata($post);
                get_template_part('template-parts/anime', 'card', [
                    'rank' => $entry['rank'],
                    'position' => $entry['position'],
                    'featured' => true,
                ]);
                ?>
            <?php endforeach; ?>
            <?php wp_reset_postdata(); ?>
        </section>

        <?php if ($remaining_anime) : ?>
            <section class="anime-ranking-section">
                <header><span>RANKING</span><span>10 POINT SCALE</span></header>
                <div class="anime-ranking-grid">
                    <?php foreach ($remaining_anime as $entry) : ?>
                        <?php
                        $post = $entry['post'];
                        setup_postdata($post);
                        get_template_part('template-parts/anime', 'card', [
                            'rank' => $entry['rank'],
                            'position' => $entry['position'],
                            'featured' => false,
                        ]);
                        ?>
                    <?php endforeach; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>
    <?php else : ?>
        <p class="isekai-empty">这里暂时还是空白。</p>
    <?php endif; ?>
</section>
<?php get_footer(); ?>
