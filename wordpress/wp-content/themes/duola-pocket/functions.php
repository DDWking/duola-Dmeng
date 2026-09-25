<?php
/**
 * Theme setup and helpers for 哆啦D梦的口袋.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_template_directory() . '/includes/music-player.php';

function duola_pocket_setup(): void
{
    load_theme_textdomain('duola-pocket', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('wp-block-styles');

    add_image_size('duola-album-card', 960, 720, true);
    add_image_size('duola-home-note', 720, 960, true);
    add_image_size('duola-lightbox', 2048, 2048, false);
    add_image_size('duola-anime-poster', 900, 1350, true);
}
add_action('after_setup_theme', 'duola_pocket_setup');

function duola_pocket_image_output_format(array $formats, ?string $filename, string $mime_type): array
{
    if (in_array($mime_type, ['image/jpeg', 'image/png'], true) && wp_image_editor_supports(['mime_type' => 'image/webp'])) {
        $formats[$mime_type] = 'image/webp';
    }

    return $formats;
}
add_filter('image_editor_output_format', 'duola_pocket_image_output_format', 10, 3);

function duola_pocket_image_quality(int $quality, string $mime_type): int
{
    return 'image/webp' === $mime_type ? 82 : $quality;
}
add_filter('wp_editor_set_quality', 'duola_pocket_image_quality', 10, 2);

add_filter('big_image_size_threshold', '__return_false');

function duola_pocket_enqueue_assets(): void
{
    $style_path = get_stylesheet_directory() . '/style.css';
    wp_enqueue_style('duola-pocket-style', get_stylesheet_uri(), [], (string) filemtime($style_path));

    $cursor_script_path = get_template_directory() . '/assets/cursor.js';
    wp_enqueue_script('duola-pocket-cursor', get_template_directory_uri() . '/assets/cursor.js', [], (string) filemtime($cursor_script_path), true);

    if (duola_pocket_is_wall_page()) {
        $wall_script_path = get_template_directory() . '/assets/wall.js';
        wp_enqueue_script('duola-pocket-wall', get_template_directory_uri() . '/assets/wall.js', [], (string) filemtime($wall_script_path), true);
        wp_localize_script('duola-pocket-wall', 'duolaWall', [
            'messagesUrl' => esc_url_raw(rest_url('duola/v1/messages')),
            'tokenUrl' => esc_url_raw(rest_url('duola/v1/wall-token')),
            'homeUrl' => esc_url_raw(home_url('/')),
            'nonce' => wp_create_nonce('duola_wall_submit'),
            'anonymous' => __('anonymous', 'duola-pocket'),
            'networkError' => __('Connection failed. Try again.', 'duola-pocket'),
        ]);
        return;
    }

    $script_path = get_template_directory() . '/assets/site.js';
    wp_enqueue_script('duola-pocket-site', get_template_directory_uri() . '/assets/site.js', [], (string) filemtime($script_path), true);
}
add_action('wp_enqueue_scripts', 'duola_pocket_enqueue_assets');

function duola_pocket_enqueue_turbo(): void
{
    if (is_admin() || is_feed() || duola_pocket_is_wall_page()) {
        return;
    }

    $turbo_script_path = get_template_directory() . '/assets/turbo.js';
    wp_enqueue_script(
        'duola-pocket-turbo',
        get_template_directory_uri() . '/assets/turbo.js',
        [],
        (string) filemtime($turbo_script_path),
        [
            'strategy' => 'defer',
            'in_footer' => false,
        ]
    );
}
add_action('wp_enqueue_scripts', 'duola_pocket_enqueue_turbo');

function duola_pocket_turbo_meta(): void
{
    if (is_admin() || is_feed() || duola_pocket_is_wall_page()) {
        return;
    }
    echo '<meta name="turbo-cache-control" content="no-cache">' . "\n";
}
add_action('wp_head', 'duola_pocket_turbo_meta', 1);

/**
 * 输出 meta description。
 * 后台「网站设置」把「一句话说明」标注为“会用于网站简介和搜索摘要”，
 * 但主题此前从未输出过该标签，所有页面都缺 description。
 */
function duola_pocket_meta_description(): void
{
    if (is_admin() || is_feed()) {
        return;
    }

    $description = '';

    if (duola_pocket_is_wall_page()) {
        $description = '留言板：留下你想说的话。';
    } elseif (is_front_page()) {
        // 首页由模板渲染，正文为空；站点「一句话说明」也可能是空的。
        $description = (string) get_option('blogdescription');
        if ('' === trim($description)) {
            $description = sprintf('%s：相册、文章、动画档案与留言板。', get_bloginfo('name'));
        }
    } elseif (is_singular()) {
        // 静态首页同时满足 is_singular()，所以 is_front_page() 必须先判断。
        $post_id = get_queried_object_id();
        $description = trim((string) get_the_excerpt($post_id));
        if ('' === $description) {
            $description = trim((string) get_post_field('post_content', $post_id));
        }
        if ('' === $description && is_singular('album')) {
            // 相册是 CPT，正文为空，用标题+年份兜底
            $description = sprintf('%s：收录 %s 年的照片。', get_the_title($post_id), get_the_date('Y', $post_id));
        }
        if ('' === $description && is_singular('anime')) {
            $year = (int) get_post_meta($post_id, '_duola_anime_year', true);
            $score = function_exists('duola_anime_get_score') ? trim((string) duola_anime_get_score($post_id)) : '';
            $bits = [get_the_title($post_id)];
            if ($year) {
                $bits[] = (string) $year;
            }
            if ('' !== $score) {
                $bits[] = '评分 ' . $score . '/10';
            }
            $description = implode(' · ', $bits) . ' — 动画档案与短评。';
        }
        if ('' === $description) {
            $description = (string) get_the_title($post_id);
        }
    } elseif (is_post_type_archive('album')) {
        $description = '按主题整理的相册，记录走过的地方。';
    } elseif (is_post_type_archive('anime')) {
        $description = '看过的动画档案与评分。';
    } elseif (is_home()) {
        $description = '写下的文章与笔记。';
    }

    if ('' === trim($description)) {
        $description = (string) get_option('blogdescription');
    }

    // 自动摘要会带上 "[…]" 这类尾标，且可能残留 HTML 实体，先剥标签再解码后去掉。
    $description = wp_strip_all_tags($description);
    $description = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
    $description = trim((string) preg_replace('/\s+/u', ' ', $description));
    $description = trim(str_replace(['[…]', '[...]', '…'], '', $description));
    if ('' === $description) {
        return;
    }

    $description = function_exists('mb_substr') ? mb_substr($description, 0, 160) : substr($description, 0, 160);

    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
}
add_action('wp_head', 'duola_pocket_meta_description', 2);

function duola_pocket_turbo_script_attributes(string $tag, string $handle): string
{
    $single_run_handles = [
        'duola-pocket-turbo',
        'duola-pocket-cursor',
        'duola-pocket-luminous-lyrics-core',
        'duola-pocket-music-player',
    ];

    if (in_array($handle, $single_run_handles, true) && !str_contains($tag, 'data-turbo-eval')) {
        return str_replace('<script ', '<script data-turbo-eval="false" ', $tag);
    }

    return $tag;
}
add_filter('script_loader_tag', 'duola_pocket_turbo_script_attributes', 10, 2);

/**
 * 给前端样式/脚本打上 data-turbo-track="reload"。
 * 主题用 filemtime 做 ?ver= 版本号，但 Turbo 只比较带 data-turbo-track 的元素；
 * 此前两边集合都为空 → 永远判定“未变化” → 部署新资源后站内跳转仍执行旧 CSS/JS，
 * 只有硬刷新才更新。
 */
function duola_pocket_turbo_track_styles(string $tag, string $handle): string
{
    if (is_admin() || is_feed() || duola_pocket_is_wall_page() || str_contains($tag, 'data-turbo-track')) {
        return $tag;
    }

    return str_replace('<link ', '<link data-turbo-track="reload" ', $tag);
}
add_filter('style_loader_tag', 'duola_pocket_turbo_track_styles', 10, 2);

function duola_pocket_turbo_track_scripts(string $tag, string $handle): string
{
    // Turbo 本体不能带 track，否则它自身的版本一变就整页重载 Turbo。
    if (is_admin() || is_feed() || duola_pocket_is_wall_page()
        || 'duola-pocket-turbo' === $handle || str_contains($tag, 'data-turbo-track')) {
        return $tag;
    }

    return str_replace('<script ', '<script data-turbo-track="reload" ', $tag);
}
add_filter('script_loader_tag', 'duola_pocket_turbo_track_scripts', 10, 2);

function duola_pocket_register_site_settings(): void
{
    register_setting('duola_site_settings', 'duola_site_avatar_id', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0,
    ]);
    register_setting('duola_site_settings', 'blogname', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    register_setting('duola_site_settings', 'blogdescription', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
}
add_action('admin_init', 'duola_pocket_register_site_settings');

function duola_pocket_add_site_settings_page(): void
{
    add_menu_page(
        __('网站设置', 'duola-pocket'),
        __('网站设置', 'duola-pocket'),
        'manage_options',
        'duola-site-settings',
        'duola_pocket_render_site_settings_page',
        'dashicons-admin-settings',
        8
    );
}
add_action('admin_menu', 'duola_pocket_add_site_settings_page');

function duola_pocket_render_site_settings_page(): void
{
    $avatar_id = (int) get_option('duola_site_avatar_id');
    $fallback_url = get_template_directory_uri() . '/assets/images/anime-girl.webp';
    $avatar_url = $avatar_id ? wp_get_attachment_image_url($avatar_id, 'thumbnail') : '';
    ?>
    <div class="wrap duola-site-settings-page">
        <?php settings_errors(); ?>
        <div class="duola-settings-heading">
            <span><?php esc_html_e('Pocket settings', 'duola-pocket'); ?></span>
            <h1><?php esc_html_e('网站设置', 'duola-pocket'); ?></h1>
            <p><?php esc_html_e('这里管理网站名称、说明和头像。', 'duola-pocket'); ?></p>
        </div>
        <form action="options.php" method="post">
            <?php settings_fields('duola_site_settings'); ?>
            <div class="duola-settings-grid">
                <section class="duola-settings-card">
                    <h2><?php esc_html_e('基础信息', 'duola-pocket'); ?></h2>
                    <p class="duola-settings-field">
                        <label for="duola-blogname"><?php esc_html_e('网站名称', 'duola-pocket'); ?></label>
                        <input id="duola-blogname" class="regular-text" name="blogname" type="text" value="<?php echo esc_attr(get_option('blogname')); ?>" required>
                    </p>
                    <p class="duola-settings-field">
                        <label for="duola-blogdescription"><?php esc_html_e('一句话说明', 'duola-pocket'); ?></label>
                        <input id="duola-blogdescription" class="regular-text" name="blogdescription" type="text" value="<?php echo esc_attr(get_option('blogdescription')); ?>">
                        <span class="description"><?php esc_html_e('会用于网站简介和搜索摘要，留空也可以。', 'duola-pocket'); ?></span>
                    </p>
                </section>
                <section class="duola-settings-card duola-avatar-setting">
                    <div>
                        <h2><?php esc_html_e('网站头像', 'duola-pocket'); ?></h2>
                        <p><?php esc_html_e('显示在首页右上角，建议使用正方形图片。', 'duola-pocket'); ?></p>
                    </div>
                    <div class="duola-avatar-control">
                        <img id="duola-avatar-preview" src="<?php echo esc_url($avatar_url ?: $fallback_url); ?>" alt="">
                        <div>
                            <input id="duola-site-avatar-id" name="duola_site_avatar_id" type="hidden" value="<?php echo esc_attr($avatar_id); ?>">
                            <button id="duola-select-avatar" class="button button-primary" type="button"><?php esc_html_e('从照片库选择', 'duola-pocket'); ?></button>
                            <button id="duola-remove-avatar" class="button" type="button"<?php echo $avatar_id ? '' : ' hidden'; ?>><?php esc_html_e('恢复默认', 'duola-pocket'); ?></button>
                        </div>
                    </div>
                </section>
                <?php duola_pocket_render_music_settings(); ?>
                <div class="duola-settings-submit">
                    <?php submit_button(__('保存网站设置', 'duola-pocket'), 'primary', 'submit', false); ?>
                </div>
            </div>
        </form>
    </div>
    <?php
}

function duola_pocket_enqueue_admin_assets(string $hook): void
{
    if ('toplevel_page_duola-site-settings' !== $hook) {
        return;
    }

    wp_enqueue_media();
    $script_path = get_template_directory() . '/assets/theme-admin.js';
    $style_path = get_template_directory() . '/assets/theme-admin.css';
    wp_enqueue_script('duola-pocket-admin', get_template_directory_uri() . '/assets/theme-admin.js', ['jquery'], (string) filemtime($script_path), true);
    wp_enqueue_style('duola-pocket-admin', get_template_directory_uri() . '/assets/theme-admin.css', [], (string) filemtime($style_path));
    wp_localize_script('duola-pocket-admin', 'duolaAppearance', [
        'title' => __('选择网站头像', 'duola-pocket'),
        'button' => __('使用这张图片', 'duola-pocket'),
        'fallback' => get_template_directory_uri() . '/assets/images/anime-girl.webp',
        'audioTitle' => __('选择音乐文件', 'duola-pocket'),
        'audioButton' => __('使用这首音乐', 'duola-pocket'),
        'coverTitle' => __('选择歌曲封面', 'duola-pocket'),
        'coverButton' => __('使用这张封面', 'duola-pocket'),
        'lyricsTitle' => __('选择歌词文件', 'duola-pocket'),
        'lyricsButton' => __('使用此歌词', 'duola-pocket'),
        'lyricsEmpty' => __('未选择（可选）', 'duola-pocket'),
        'lyricsInvalid' => __('请选择 .lrc 或 .txt 歌词文件', 'duola-pocket'),
    ]);
}
add_action('admin_enqueue_scripts', 'duola_pocket_enqueue_admin_assets');

function duola_pocket_articles_url(): string
{
    $posts_page_id = (int) get_option('page_for_posts');
    return $posts_page_id ? (string) get_permalink($posts_page_id) : home_url('/articles/');
}

function duola_pocket_wall_url(): string
{
    return home_url('/wall/');
}

function duola_pocket_register_wall_route(): void
{
    add_rewrite_rule('^wall/?$', 'index.php?duola_wall=1', 'top');
    add_rewrite_rule('^about/?$', 'index.php?duola_wall_redirect=1', 'top');
}
add_action('init', 'duola_pocket_register_wall_route');

function duola_pocket_wall_query_vars(array $query_vars): array
{
    $query_vars[] = 'duola_wall';
    $query_vars[] = 'duola_wall_redirect';
    return $query_vars;
}
add_filter('query_vars', 'duola_pocket_wall_query_vars');

function duola_pocket_is_wall_page(): bool
{
    return '1' === (string) get_query_var('duola_wall');
}

function duola_pocket_prepare_wall_page(): void
{
    if ('1' === (string) get_query_var('duola_wall_redirect')) {
        wp_safe_redirect(duola_pocket_wall_url(), 301);
        exit;
    }

    if (!duola_pocket_is_wall_page()) {
        return;
    }
    global $wp_query;
    $wp_query->is_404 = false;
    status_header(200);
}
add_action('template_redirect', 'duola_pocket_prepare_wall_page');

function duola_pocket_wall_template(string $template): string
{
    return duola_pocket_is_wall_page() ? get_template_directory() . '/wall.php' : $template;
}
add_filter('template_include', 'duola_pocket_wall_template');

function duola_pocket_wall_document_title(array $title): array
{
    if (duola_pocket_is_wall_page()) {
        $title['title'] = '留言板';
    }
    return $title;
}
add_filter('document_title_parts', 'duola_pocket_wall_document_title');

function duola_pocket_maybe_flush_wall_route(): void
{
    $route_version = '2';
    if ($route_version === get_option('duola_wall_route_version')) {
        return;
    }

    flush_rewrite_rules(false);
    delete_option('duola_about_content');
    delete_option('duola_about_route_version');
    update_option('duola_wall_route_version', $route_version, false);
}
add_action('init', 'duola_pocket_maybe_flush_wall_route', 99);

function duola_pocket_format_date(int $post_id): string
{
    return get_the_date('Y.m.d', $post_id);
}
