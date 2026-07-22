<?php

if (!defined('ABSPATH')) {
    exit;
}

function duola_anime_register_content_type(): void
{
    register_post_type('anime', [
        'labels' => [
            'name' => __('异世界', 'duola-albums'),
            'singular_name' => __('动画', 'duola-albums'),
            'add_new' => __('记录动画', 'duola-albums'),
            'add_new_item' => __('记录一部动画', 'duola-albums'),
            'edit_item' => __('编辑动画', 'duola-albums'),
            'new_item' => __('新动画', 'duola-albums'),
            'view_item' => __('查看动画', 'duola-albums'),
            'all_items' => __('全部动画', 'duola-albums'),
            'menu_name' => __('异世界', 'duola-albums'),
            'search_items' => __('搜索动画', 'duola-albums'),
            'not_found' => __('还没有记录动画。', 'duola-albums'),
            'not_found_in_trash' => __('回收站中没有动画。', 'duola-albums'),
        ],
        'public' => true,
        'has_archive' => 'isekai',
        'rewrite' => ['slug' => 'isekai', 'with_front' => false],
        'menu_icon' => 'dashicons-star-filled',
        'menu_position' => 7,
        'show_in_rest' => true,
        'supports' => ['title', 'thumbnail', 'revisions'],
    ]);
}
add_action('init', 'duola_anime_register_content_type', 5);

function duola_anime_sanitize_score($value): string
{
    if ('' === $value || null === $value) {
        return '';
    }

    if (!is_scalar($value)) {
        return '';
    }

    $normalized = trim(str_replace(',', '.', (string) $value));
    if ('' === $normalized || !is_numeric($normalized)) {
        return '';
    }

    $score = (float) $normalized;
    $score = max(0, min(10, round($score * 2) / 2));
    return number_format($score, 1, '.', '');
}

function duola_anime_sanitize_ids($value): array
{
    if (!is_array($value)) {
        return [];
    }

    return array_values(array_unique(array_filter(array_map('absint', $value), static function (int $anime_id): bool {
        return $anime_id > 0 && 'anime' === get_post_type($anime_id);
    })));
}

function duola_anime_register_meta(): void
{
    $anime_meta = [
        '_duola_anime_alt_title' => ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
        '_duola_anime_year' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
        '_duola_anime_episodes' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
        '_duola_anime_score' => ['type' => 'string', 'sanitize_callback' => 'duola_anime_sanitize_score'],
        '_duola_anime_note' => ['type' => 'string', 'sanitize_callback' => 'wp_kses_post'],
        '_duola_anime_poster_id' => ['type' => 'integer', 'sanitize_callback' => 'absint'],
    ];

    foreach ($anime_meta as $key => $args) {
        register_post_meta('anime', $key, array_merge($args, [
            'single' => true,
            'show_in_rest' => false,
            'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
        ]));
    }

    register_post_meta('post', '_duola_related_anime', [
        'type' => 'array',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => 'duola_anime_sanitize_ids',
        'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
    ]);
}
add_action('init', 'duola_anime_register_meta', 12);

function duola_anime_get_score(int $anime_id): ?float
{
    $score = get_post_meta($anime_id, '_duola_anime_score', true);
    return '' === $score ? null : (float) $score;
}

function duola_anime_format_score(int $anime_id): string
{
    $score = duola_anime_get_score($anime_id);
    return null === $score ? __('未评分', 'duola-albums') : number_format($score, 1);
}

function duola_anime_get_poster_id(int $anime_id): int
{
    return (int) (get_post_meta($anime_id, '_duola_anime_poster_id', true) ?: get_post_thumbnail_id($anime_id));
}

function duola_anime_get_note(int $anime_id): string
{
    return (string) get_post_meta($anime_id, '_duola_anime_note', true);
}

function duola_anime_get_related_ids(int $post_id): array
{
    return duola_anime_sanitize_ids(get_post_meta($post_id, '_duola_related_anime', true));
}

function duola_anime_get_related_posts(int $post_id): array
{
    $anime_ids = duola_anime_get_related_ids($post_id);
    if (!$anime_ids) {
        return [];
    }

    return get_posts([
        'post_type' => 'anime',
        'post_status' => 'publish',
        'numberposts' => -1,
        'post__in' => $anime_ids,
        'orderby' => 'post__in',
    ]);
}

function duola_anime_get_reviews(int $anime_id): array
{
    return get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => [[
            'key' => '_duola_related_anime',
            'value' => 'i:' . $anime_id . ';',
            'compare' => 'LIKE',
        ]],
    ]);
}

function duola_anime_get_ranked_posts(): array
{
    $anime_posts = get_posts([
        'post_type' => 'anime',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    usort($anime_posts, static function (WP_Post $left, WP_Post $right): int {
        $left_score = duola_anime_get_score((int) $left->ID);
        $right_score = duola_anime_get_score((int) $right->ID);

        if (null === $left_score || null === $right_score) {
            if (null !== $left_score) {
                return -1;
            }
            if (null !== $right_score) {
                return 1;
            }
        } else {
            $score_order = $right_score <=> $left_score;
            if (0 !== $score_order) {
                return $score_order;
            }
        }

        $title_order = strnatcasecmp($left->post_title, $right->post_title);
        return 0 !== $title_order ? $title_order : ((int) $left->ID <=> (int) $right->ID);
    });

    return $anime_posts;
}

function duola_anime_add_meta_boxes(): void
{
    add_meta_box(
        'duola-anime-details',
        __('动画资料', 'duola-albums'),
        'duola_anime_render_details_meta_box',
        'anime',
        'normal',
        'high'
    );
    add_meta_box(
        'duola-related-anime',
        __('关联动画', 'duola-albums'),
        'duola_anime_render_relation_meta_box',
        'post',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'duola_anime_add_meta_boxes');

function duola_anime_render_details_meta_box(WP_Post $post): void
{
    $poster_id = duola_anime_get_poster_id((int) $post->ID);
    $poster_url = $poster_id ? wp_get_attachment_image_url($poster_id, 'medium') : '';
    $score = get_post_meta($post->ID, '_duola_anime_score', true);
    $alt_title = (string) get_post_meta($post->ID, '_duola_anime_alt_title', true);
    $year = (int) get_post_meta($post->ID, '_duola_anime_year', true);
    $episodes = (int) get_post_meta($post->ID, '_duola_anime_episodes', true);
    $note = duola_anime_get_note((int) $post->ID);
    wp_nonce_field('duola_anime_save_details', 'duola_anime_nonce');
    ?>
    <div class="duola-anime-editor">
        <section class="duola-anime-poster-field">
            <div id="duola-anime-poster-preview" class="duola-anime-poster-preview<?php echo $poster_url ? ' has-image' : ''; ?>">
                <?php if ($poster_url) : ?><img src="<?php echo esc_url($poster_url); ?>" alt=""><?php else : ?><span class="dashicons dashicons-format-image" aria-hidden="true"></span><?php endif; ?>
            </div>
            <input id="duola-anime-poster-id" name="duola_anime_poster_id" type="hidden" value="<?php echo esc_attr($poster_id); ?>">
            <button id="duola-anime-select-poster" class="button button-primary" type="button"><?php esc_html_e('选择海报', 'duola-albums'); ?></button>
            <button id="duola-anime-remove-poster" class="button" type="button"<?php echo $poster_url ? '' : ' hidden'; ?>><?php esc_html_e('移除海报', 'duola-albums'); ?></button>
        </section>
        <div class="duola-anime-fields">
            <div class="duola-anime-fields-row">
                <p class="duola-anime-score-field">
                    <label for="duola_anime_score"><?php esc_html_e('我的评分', 'duola-albums'); ?></label>
                    <span><input id="duola_anime_score" name="duola_anime_score" type="number" min="0" max="10" step="0.5" inputmode="decimal" value="<?php echo esc_attr($score); ?>"><b>/ 10</b></span>
                </p>
                <p>
                    <label for="duola_anime_alt_title"><?php esc_html_e('别名', 'duola-albums'); ?></label>
                    <input id="duola_anime_alt_title" name="duola_anime_alt_title" type="text" value="<?php echo esc_attr($alt_title); ?>" placeholder="<?php esc_attr_e('可留空', 'duola-albums'); ?>">
                </p>
            </div>
            <div class="duola-anime-fields-row">
                <p>
                    <label for="duola_anime_year"><?php esc_html_e('年份', 'duola-albums'); ?></label>
                    <input id="duola_anime_year" name="duola_anime_year" type="number" min="1900" max="2100" inputmode="numeric" value="<?php echo esc_attr($year ?: ''); ?>">
                </p>
                <p>
                    <label for="duola_anime_episodes"><?php esc_html_e('集数', 'duola-albums'); ?></label>
                    <input id="duola_anime_episodes" name="duola_anime_episodes" type="number" min="0" max="9999" inputmode="numeric" value="<?php echo esc_attr($episodes ?: ''); ?>">
                </p>
            </div>
            <p>
                <label for="duola_anime_note"><?php esc_html_e('我的记录', 'duola-albums'); ?></label>
                <textarea id="duola_anime_note" name="duola_anime_note" rows="8" placeholder="<?php esc_attr_e('写一点对这部动画的印象，也可以留空。', 'duola-albums'); ?>"><?php echo esc_textarea($note); ?></textarea>
            </p>
        </div>
    </div>
    <?php
}

function duola_anime_render_relation_meta_box(WP_Post $post): void
{
    $selected_ids = duola_anime_get_related_ids((int) $post->ID);
    $anime_posts = get_posts([
        'post_type' => 'anime',
        'post_status' => ['publish', 'draft', 'private'],
        'numberposts' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);
    wp_nonce_field('duola_anime_save_relation', 'duola_anime_relation_nonce');
    if (!$anime_posts) {
        echo '<p>' . esc_html__('先在“异世界”中记录动画，之后就能关联到文章。', 'duola-albums') . '</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('post-new.php?post_type=anime')) . '">' . esc_html__('记录动画', 'duola-albums') . '</a></p>';
        return;
    }
    ?>
    <div class="duola-anime-relation-picker">
        <label class="screen-reader-text" for="duola-anime-relation-search"><?php esc_html_e('搜索动画', 'duola-albums'); ?></label>
        <input id="duola-anime-relation-search" type="search" placeholder="<?php esc_attr_e('搜索动画', 'duola-albums'); ?>" autocomplete="off">
        <div class="duola-anime-relation-list">
            <?php foreach ($anime_posts as $anime) : ?>
                <?php $score = duola_anime_get_score((int) $anime->ID); ?>
                <label data-anime-option>
                    <input name="duola_related_anime[]" type="checkbox" value="<?php echo esc_attr($anime->ID); ?>"<?php checked(in_array((int) $anime->ID, $selected_ids, true)); ?>>
                    <span><?php echo esc_html(get_the_title($anime) ?: __('未命名动画', 'duola-albums')); ?></span>
                    <small><?php echo null === $score ? esc_html__('未评分', 'duola-albums') : esc_html(number_format($score, 1)); ?></small>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="duola-anime-relation-empty" hidden><?php esc_html_e('没有找到动画。', 'duola-albums'); ?></p>
    </div>
    <?php
}

function duola_anime_save_details(int $post_id): void
{
    if (!isset($_POST['duola_anime_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['duola_anime_nonce'])), 'duola_anime_save_details')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $score = duola_anime_sanitize_score(isset($_POST['duola_anime_score']) ? wp_unslash($_POST['duola_anime_score']) : '');
    $alt_title = isset($_POST['duola_anime_alt_title']) ? sanitize_text_field(wp_unslash($_POST['duola_anime_alt_title'])) : '';
    $year = isset($_POST['duola_anime_year']) ? absint($_POST['duola_anime_year']) : 0;
    $episodes = isset($_POST['duola_anime_episodes']) ? absint($_POST['duola_anime_episodes']) : 0;
    $note = isset($_POST['duola_anime_note']) ? wp_kses_post(wp_unslash($_POST['duola_anime_note'])) : '';
    $poster_id = isset($_POST['duola_anime_poster_id']) ? absint($_POST['duola_anime_poster_id']) : 0;
    if ($poster_id && !wp_attachment_is_image($poster_id)) {
        $poster_id = 0;
    }

    update_post_meta($post_id, '_duola_anime_score', $score);
    update_post_meta($post_id, '_duola_anime_alt_title', $alt_title);
    update_post_meta($post_id, '_duola_anime_year', $year);
    update_post_meta($post_id, '_duola_anime_episodes', $episodes);
    update_post_meta($post_id, '_duola_anime_note', $note);
    update_post_meta($post_id, '_duola_anime_poster_id', $poster_id);

    if ($poster_id && wp_attachment_is_image($poster_id)) {
        set_post_thumbnail($post_id, $poster_id);
    } else {
        delete_post_thumbnail($post_id);
    }
}
add_action('save_post_anime', 'duola_anime_save_details');

function duola_anime_save_relation(int $post_id): void
{
    if (!isset($_POST['duola_anime_relation_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['duola_anime_relation_nonce'])), 'duola_anime_save_relation')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
        return;
    }

    $anime_ids = isset($_POST['duola_related_anime']) ? duola_anime_sanitize_ids(wp_unslash($_POST['duola_related_anime'])) : [];
    update_post_meta($post_id, '_duola_related_anime', $anime_ids);
}
add_action('save_post_post', 'duola_anime_save_relation');

function duola_anime_admin_assets(): void
{
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->post_type, ['anime', 'post'], true)) {
        return;
    }

    $style_path = DUOLA_ALBUMS_PATH . 'assets/anime-admin.css';
    $script_path = DUOLA_ALBUMS_PATH . 'assets/anime-admin.js';
    $dependencies = [];
    if ('anime' === $screen->post_type && 'post' === $screen->base) {
        wp_enqueue_media();
        $dependencies[] = 'media-editor';
    }
    wp_enqueue_style('duola-anime-admin', DUOLA_ALBUMS_URL . 'assets/anime-admin.css', [], (string) filemtime($style_path));
    wp_enqueue_script('duola-anime-admin', DUOLA_ALBUMS_URL . 'assets/anime-admin.js', $dependencies, (string) filemtime($script_path), true);
    wp_localize_script('duola-anime-admin', 'duolaAnimeAdmin', [
        'posterTitle' => __('选择动画海报', 'duola-albums'),
        'posterButton' => __('使用这张海报', 'duola-albums'),
    ]);
}
add_action('admin_enqueue_scripts', 'duola_anime_admin_assets', 110);

function duola_anime_title_placeholder(string $title, WP_Post $post): string
{
    return 'anime' === $post->post_type ? __('动画名称', 'duola-albums') : $title;
}
add_filter('enter_title_here', 'duola_anime_title_placeholder', 20, 2);

function duola_anime_remove_default_meta_boxes(): void
{
    remove_meta_box('postimagediv', 'anime', 'side');
    remove_meta_box('slugdiv', 'anime', 'normal');
    remove_meta_box('authordiv', 'anime', 'normal');
}
add_action('add_meta_boxes', 'duola_anime_remove_default_meta_boxes', 100);

function duola_anime_admin_columns(array $columns): array
{
    return [
        'cb' => $columns['cb'],
        'duola_anime_poster' => __('海报', 'duola-albums'),
        'title' => __('动画', 'duola-albums'),
        'duola_anime_score' => __('评分', 'duola-albums'),
        'duola_anime_year' => __('年份', 'duola-albums'),
        'duola_anime_reviews' => __('相关文章', 'duola-albums'),
        'date' => __('状态与日期', 'duola-albums'),
    ];
}
add_filter('manage_anime_posts_columns', 'duola_anime_admin_columns');

function duola_anime_render_admin_column(string $column, int $post_id): void
{
    if ('duola_anime_poster' === $column) {
        $poster_id = duola_anime_get_poster_id($post_id);
        echo $poster_id ? wp_get_attachment_image($poster_id, 'thumbnail') : '<span class="duola-no-cover">' . esc_html__('暂无海报', 'duola-albums') . '</span>';
    }
    if ('duola_anime_score' === $column) {
        echo '<strong>' . esc_html(duola_anime_format_score($post_id)) . '</strong>';
    }
    if ('duola_anime_year' === $column) {
        echo esc_html((string) (get_post_meta($post_id, '_duola_anime_year', true) ?: __('未填', 'duola-albums')));
    }
    if ('duola_anime_reviews' === $column) {
        echo esc_html((string) count(duola_anime_get_reviews($post_id)));
    }
}
add_action('manage_anime_posts_custom_column', 'duola_anime_render_admin_column', 10, 2);

function duola_anime_sortable_columns(array $columns): array
{
    $columns['duola_anime_score'] = 'duola_anime_score';
    $columns['duola_anime_year'] = 'duola_anime_year';
    return $columns;
}
add_filter('manage_edit-anime_sortable_columns', 'duola_anime_sortable_columns');

function duola_anime_admin_ordering(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query() || 'anime' !== $query->get('post_type')) {
        return;
    }

    if ('duola_anime_score' === $query->get('orderby')) {
        $query->set('meta_key', '_duola_anime_score');
        $query->set('orderby', 'meta_value_num');
    }
    if ('duola_anime_year' === $query->get('orderby')) {
        $query->set('meta_key', '_duola_anime_year');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'duola_anime_admin_ordering');

function duola_anime_maybe_flush_routes(): void
{
    $route_version = '1';
    if ($route_version === get_option('duola_anime_route_version')) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('duola_anime_route_version', $route_version, false);
}
add_action('init', 'duola_anime_maybe_flush_routes', 99);

function duola_anime_keep_empty_archive_available($preempt, WP_Query $query)
{
    if (!$query->is_main_query() || !$query->is_post_type_archive('anime')) {
        return $preempt;
    }

    return true;
}
add_filter('pre_handle_404', 'duola_anime_keep_empty_archive_available', 10, 2);
