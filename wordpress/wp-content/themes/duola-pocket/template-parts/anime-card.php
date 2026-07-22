<?php
$card_args = wp_parse_args($args ?? [], [
    'rank' => null,
    'position' => 0,
    'featured' => false,
]);
$anime_id = get_the_ID();
$poster_id = function_exists('duola_anime_get_poster_id') ? duola_anime_get_poster_id($anime_id) : (int) get_post_thumbnail_id($anime_id);
$score = function_exists('duola_anime_get_score') ? duola_anime_get_score($anime_id) : null;
$year = (int) get_post_meta($anime_id, '_duola_anime_year', true);
$episodes = (int) get_post_meta($anime_id, '_duola_anime_episodes', true);
$note = function_exists('duola_anime_get_note') ? duola_anime_get_note($anime_id) : '';
$card_class = 'anime-card anime-card-position-' . absint($card_args['position']);
if ($card_args['featured']) {
    $card_class .= ' is-featured';
}
?>
<article class="<?php echo esc_attr($card_class); ?>">
    <a href="<?php the_permalink(); ?>">
        <figure class="anime-card-poster">
            <?php if ($poster_id) : ?>
                <?php echo wp_get_attachment_image($poster_id, 'duola-anime-poster', false, [
                    'loading' => $card_args['position'] <= 3 ? 'eager' : 'lazy',
                    'decoding' => 'async',
                    'fetchpriority' => 1 === $card_args['position'] ? 'high' : 'auto',
                    'sizes' => $card_args['featured'] ? '(max-width: 700px) 72vw, 30vw' : '(max-width: 700px) 44vw, 20vw',
                    'alt' => get_the_title(),
                ]); ?>
            <?php else : ?>
                <span class="anime-poster-placeholder"><i>NO IMAGE</i><b><?php the_title(); ?></b></span>
            <?php endif; ?>
            <span class="anime-card-rank"><?php echo null === $card_args['rank'] ? 'NR' : '#' . esc_html(str_pad((string) $card_args['rank'], 2, '0', STR_PAD_LEFT)); ?></span>
            <span class="anime-card-score<?php echo null === $score ? ' is-unrated' : ''; ?>"><strong><?php echo null === $score ? 'UNRATED' : esc_html(number_format($score, 1)); ?></strong><?php if (null !== $score) : ?><small>/10</small><?php endif; ?></span>
        </figure>
        <div class="anime-card-copy">
            <h2><?php the_title(); ?></h2>
            <?php if ($year || $episodes) : ?>
                <p><?php if ($year) : ?><span><?php echo esc_html($year); ?></span><?php endif; ?><?php if ($episodes) : ?><span><?php echo esc_html($episodes); ?> EP</span><?php endif; ?></p>
            <?php endif; ?>
            <?php if ($note) : ?><div><?php echo esc_html(wp_strip_all_tags($note)); ?></div><?php endif; ?>
        </div>
    </a>
</article>
