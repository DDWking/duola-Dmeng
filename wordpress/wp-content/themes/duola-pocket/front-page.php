<?php
get_header();

$latest_posts = new WP_Query([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => 4,
]);

$home_photos = [];
$home_photo_slots = [];
$seen_photo_ids = [];
if (function_exists('duola_albums_get_cover_id') && function_exists('duola_albums_get_photos')) {
    $albums = get_posts([
        'post_type' => 'album',
        'post_status' => 'publish',
        'numberposts' => 6,
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

            $settings = function_exists('duola_albums_get_photo_settings')
                ? duola_albums_get_photo_settings((int) $album->ID, $photo_id)
                : [];
            if (isset($settings['show_home']) && !$settings['show_home']) {
                continue;
            }

            $seen_photo_ids[$photo_id] = true;
            $home_photos[] = [
                'id' => $photo_id,
                'url' => $full_url,
                'title' => get_the_title($album),
                'caption' => wp_get_attachment_caption($photo_id),
                'settings' => $settings,
            ];

            if (count($home_photos) >= 10) {
                break 2;
            }
        }
    }
}

if ($home_photos) {
    $slot_count = max(9, count($home_photos));
    for ($slot_index = 0; $slot_index < $slot_count; $slot_index++) {
        $photo_index = $slot_index % count($home_photos);
        $home_photo_slots[] = [
            'photo' => $home_photos[$photo_index],
            'photo_index' => $photo_index,
            'position' => max(8, min(92, (int) ($home_photos[$photo_index]['settings']['focus_x'] ?? 50) + ($slot_index >= count($home_photos) ? (($slot_index * 17) % 21) - 10 : 0))),
        ];
    }
}
?>
<section class="kinetic-home" aria-label="首页">
    <div class="home-articles">
        <div class="home-section-heading">
            <span>NOTES</span>
            <a href="<?php echo esc_url(get_permalink((int) get_option('page_for_posts')) ?: home_url('/')); ?>">全部文章</a>
        </div>
        <?php if ($latest_posts->have_posts()) : ?>
            <div class="home-article-list">
                <?php $post_index = 0; while ($latest_posts->have_posts()) : $latest_posts->the_post(); $post_index++; ?>
                    <article class="home-article-item">
                        <a href="<?php the_permalink(); ?>">
                            <span class="home-article-index"><?php echo esc_html(str_pad((string) $post_index, 2, '0', STR_PAD_LEFT)); ?></span>
                            <span class="home-article-copy">
                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(duola_pocket_format_date(get_the_ID())); ?></time>
                                <strong><?php the_title(); ?></strong>
                            </span>
                        </a>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else : ?>
            <p class="home-empty">文字还在路上。</p>
        <?php endif; ?>
    </div>

    <?php if ($home_photos) : ?>
        <div class="home-preview-control" data-home-preview-control>
            <span class="home-preview-current" data-home-preview-current>01</span>
            <div class="home-preview-track">
                <span aria-hidden="true"></span>
                <input type="range" min="0" max="<?php echo esc_attr(max(0, count($home_photos) - 1)); ?>" value="0" step="1" aria-label="滑动预览照片">
            </div>
            <span class="home-preview-total"><?php echo esc_html(str_pad((string) count($home_photos), 2, '0', STR_PAD_LEFT)); ?></span>
        </div>
        <div class="home-photo-rail" data-home-photo-rail data-lightbox-gallery data-gallery-title="照片">
            <?php foreach ($home_photo_slots as $slot_index => $slot) : ?>
                <?php $photo = $slot['photo']; $settings = $photo['settings']; ?>
                <button class="home-photo-slice is-width-<?php echo esc_attr($settings['home_width'] ?? 'standard'); ?>" type="button"
                    style="--slice-position: <?php echo esc_attr($slot['position']); ?>%;"
                    data-lightbox-image="<?php echo esc_url($photo['url']); ?>"
                    data-lightbox-key="<?php echo esc_attr($photo['id']); ?>"
                    data-lightbox-title="<?php echo esc_attr($photo['title']); ?>"
                    data-lightbox-caption="<?php echo esc_attr($photo['caption']); ?>"
                    data-lightbox-headline="<?php echo esc_attr($settings['headline'] ?? ''); ?>"
                    data-lightbox-description="<?php echo esc_attr($settings['description'] ?? ''); ?>"
                    data-lightbox-date="<?php echo esc_attr($settings['date'] ?? ''); ?>"
                    data-lightbox-layout="<?php echo esc_attr($settings['layout'] ?? 'standard'); ?>"
                    data-lightbox-text-position="<?php echo esc_attr($settings['text_position'] ?? 'spread'); ?>"
                    data-lightbox-focus-x="<?php echo esc_attr($settings['focus_x'] ?? 50); ?>"
                    data-lightbox-focus-y="<?php echo esc_attr($settings['focus_y'] ?? 50); ?>"
                    data-lightbox-accent="<?php echo esc_attr($settings['accent'] ?? '#009fe8'); ?>"
                    data-lightbox-background="<?php echo esc_attr($settings['background'] ?? '#f3f3f0'); ?>"
                    aria-label="查看照片 <?php echo esc_attr($slot['photo_index'] + 1); ?>">
                    <?php echo wp_get_attachment_image($photo['id'], 'large', false, [
                        'loading' => $slot_index < 5 ? 'eager' : 'lazy',
                        'fetchpriority' => 0 === $slot_index ? 'high' : 'auto',
                        'alt' => '',
                    ]); ?>
                </button>
            <?php endforeach; ?>
        </div>
        <a class="home-all-photos" href="<?php echo esc_url(get_post_type_archive_link('album')); ?>">全部照片</a>
    <?php endif; ?>
</section>
<?php get_footer(); ?>
