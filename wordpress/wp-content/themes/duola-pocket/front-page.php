<?php
get_header();

$home_photos = [];
$seen_photo_ids = [];

if (function_exists('duola_albums_get_cover_id') && function_exists('duola_albums_get_photos')) {
    $albums = get_posts([
        'post_type' => 'album',
        'post_status' => 'publish',
        'numberposts' => 8,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    foreach ($albums as $album) {
        $photo_ids = [duola_albums_get_cover_id((int) $album->ID)];
        foreach (duola_albums_get_photos((int) $album->ID) as $photo) {
            $photo_ids[] = (int) $photo['id'];
        }

        foreach ($photo_ids as $photo_id) {
            if (!$photo_id || isset($seen_photo_ids[$photo_id])) {
                continue;
            }

            $full_url = wp_get_attachment_image_url($photo_id, 'duola-lightbox') ?: wp_get_attachment_image_url($photo_id, 'full');
            if (!$full_url) {
                continue;
            }

            $seen_photo_ids[$photo_id] = true;
            $home_photos[] = [
                'id' => $photo_id,
                'url' => $full_url,
                'title' => get_the_title($album),
                'caption' => wp_get_attachment_caption($photo_id),
            ];

            if (count($home_photos) >= 6) {
                break 2;
            }
        }
    }
}

$latest_posts = new WP_Query([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 5,
]);
$asset_url = get_template_directory_uri() . '/assets/images/';
$months = ['', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
?>
<section class="scrapbook-home" aria-label="首页">
    <div class="paper-wash" aria-hidden="true"></div>
    <img class="stay-alive" src="<?php echo esc_url($asset_url . 'stay-alive.webp'); ?>" alt="Stay alive!">
    <span class="sparkle sparkle-one" aria-hidden="true"></span>
    <span class="sparkle sparkle-two" aria-hidden="true"></span>
    <span class="brush-mark" aria-hidden="true"></span>

    <section class="home-notes" aria-labelledby="latest-notes-title">
        <div class="home-notes-heading">
            <div>
                <span class="section-kicker">Daily notes</span>
                <h1 id="latest-notes-title">最近写下的</h1>
            </div>
            <a href="<?php echo esc_url(duola_pocket_articles_url()); ?>">查看全部</a>
        </div>
        <?php if ($latest_posts->have_posts()) : ?>
            <div class="home-note-list">
                <?php $post_index = 0; while ($latest_posts->have_posts()) : $latest_posts->the_post(); $post_index++; ?>
                    <?php
                    $tags = get_the_tags();
                    $label = $tags ? $tags[0]->name : '随笔';
                    $summary = get_the_excerpt();
                    if (!$summary) {
                        $summary = wp_trim_words(wp_strip_all_tags(get_the_content()), 24);
                    }
                    $fallback_photo = $home_photos ? $home_photos[($post_index - 1) % count($home_photos)] : null;
                    ?>
                    <article class="home-note-card">
                        <a href="<?php the_permalink(); ?>">
                            <div class="home-note-thumb">
                                <?php if (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail('thumbnail', ['loading' => 1 === $post_index ? 'eager' : 'lazy', 'alt' => '']); ?>
                                <?php elseif ($fallback_photo) : ?>
                                    <?php echo wp_get_attachment_image($fallback_photo['id'], 'thumbnail', false, ['loading' => 1 === $post_index ? 'eager' : 'lazy', 'alt' => '']); ?>
                                <?php else : ?>
                                    <span aria-hidden="true"></span>
                                <?php endif; ?>
                            </div>
                            <div class="home-note-copy">
                                <span class="home-note-tag"><?php echo esc_html($label); ?></span>
                                <h2><?php the_title(); ?></h2>
                                <?php if ($summary) : ?><p><?php echo esc_html($summary); ?></p><?php endif; ?>
                            </div>
                            <time class="home-note-date" datetime="<?php echo esc_attr(get_the_date('c')); ?>">
                                <span><?php echo esc_html($months[(int) get_the_date('n')]); ?></span>
                                <strong><?php echo esc_html(get_the_date('d')); ?></strong>
                                <span><?php echo esc_html(get_the_date('Y')); ?></span>
                            </time>
                        </a>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <p class="empty-state">文字还在路上。</p>
        <?php endif; ?>
    </section>

    <section class="memory-board" aria-labelledby="latest-photos-title">
        <div class="memory-board-heading">
            <span class="section-kicker">Pocket memories</span>
            <h2 id="latest-photos-title">最近珍藏的画面</h2>
        </div>
        <div class="memory-collage" data-memory-collage data-lightbox-gallery data-gallery-title="照片">
            <span class="collage-dots" aria-hidden="true"></span>
            <?php if ($home_photos) : ?>
                <?php foreach ($home_photos as $index => $photo) : ?>
                    <button class="photo-note photo-note-<?php echo esc_attr($index + 1); ?>" type="button"
                        data-collage-note
                        data-depth="<?php echo esc_attr(number_format(0.35 + ($index % 3) * 0.2, 2)); ?>"
                        data-lightbox-image="<?php echo esc_url($photo['url']); ?>"
                        data-lightbox-key="<?php echo esc_attr($photo['id']); ?>"
                        data-lightbox-title="<?php echo esc_attr($photo['title']); ?>"
                        data-lightbox-caption="<?php echo esc_attr($photo['caption']); ?>"
                        aria-label="查看照片 <?php echo esc_attr($index + 1); ?>">
                        <span class="photo-note-tape" aria-hidden="true"></span>
                        <?php echo wp_get_attachment_image($photo['id'], 'duola-home-note', false, [
                            'loading' => $index < 2 ? 'eager' : 'lazy',
                            'fetchpriority' => 0 === $index ? 'high' : 'auto',
                            'alt' => '',
                        ]); ?>
                    </button>
                <?php endforeach; ?>
            <?php else : ?>
                <div class="memory-placeholder">第一张照片正在路上。</div>
            <?php endif; ?>
            <span class="postmark" aria-hidden="true"><i></i></span>
        </div>
        <a class="all-photos-link" href="<?php echo esc_url(get_post_type_archive_link('album') ?: home_url('/photos/')); ?>">
            <span>翻看全部相册</span><i aria-hidden="true">→</i>
        </a>
    </section>

    <img class="home-character" src="<?php echo esc_url($asset_url . 'anime-girl.webp'); ?>" alt="" aria-hidden="true">
</section>
<?php get_footer(); ?>
