<?php

if (!defined('ABSPATH')) {
    exit;
}

function duola_visual_allowed_styles(): array
{
    return [
        'position', 'top', 'right', 'bottom', 'left', 'width', 'height', 'min-width', 'min-height', 'max-width', 'max-height',
        'z-index', 'display', 'opacity', 'color', 'background', 'background-color', 'font-size', 'font-weight', 'line-height',
        'text-align', 'object-fit', 'object-position', 'transform', 'padding', 'margin', 'border', 'border-radius', 'gap',
        'align-items', 'justify-content', 'flex-direction', 'overflow',
    ];
}

function duola_visual_sanitize_style($style): array
{
    if (!is_array($style)) {
        return [];
    }

    $allowed = array_flip(duola_visual_allowed_styles());
    $clean = [];
    foreach ($style as $property => $value) {
        $property = sanitize_key($property);
        $value = is_scalar($value) ? trim((string) $value) : '';
        if (!isset($allowed[$property]) || '' === $value || strlen($value) > 120) {
            continue;
        }
        if (preg_match('/[{};<>]|expression\s*\(|url\s*\(/i', $value)) {
            continue;
        }
        $clean[$property] = $value;
    }
    return $clean;
}

function duola_visual_sanitize_layout($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $allowed_types = ['home-articles', 'home-preview', 'home-rail', 'home-link', 'photo', 'headline', 'date', 'description', 'text', 'image'];
    $elements = [];
    foreach (array_slice((array) ($value['elements'] ?? []), 0, 24) as $element) {
        if (!is_array($element)) {
            continue;
        }
        $type = sanitize_key($element['type'] ?? '');
        if (!in_array($type, $allowed_types, true)) {
            continue;
        }
        $id = sanitize_key($element['id'] ?? wp_generate_uuid4());
        $elements[] = [
            'id' => $id ?: sanitize_key(wp_generate_uuid4()),
            'type' => $type,
            'label' => sanitize_text_field($element['label'] ?? $type),
            'content' => sanitize_textarea_field($element['content'] ?? ''),
            'src' => esc_url_raw($element['src'] ?? ''),
            'locked' => !empty($element['locked']),
            'desktop' => duola_visual_sanitize_style($element['desktop'] ?? []),
            'mobile' => duola_visual_sanitize_style($element['mobile'] ?? []),
        ];
    }

    $settings = is_array($value['settings'] ?? null) ? $value['settings'] : [];
    return [
        'version' => 1,
        'elements' => $elements,
        'settings' => [
            'background' => sanitize_hex_color($settings['background'] ?? '') ?: '#111315',
            'accent' => sanitize_hex_color($settings['accent'] ?? '') ?: '#009fe8',
            'show_home' => !isset($settings['show_home']) || !empty($settings['show_home']),
            'home_width' => in_array(($settings['home_width'] ?? 'standard'), ['narrow', 'standard', 'wide'], true) ? $settings['home_width'] : 'standard',
            'focus_x' => max(0, min(100, absint($settings['focus_x'] ?? 50))),
            'focus_y' => max(0, min(100, absint($settings['focus_y'] ?? 50))),
            'wave_damping' => max(4, min(30, absint($settings['wave_damping'] ?? 12))),
            'wave_latency' => max(2, min(24, absint($settings['wave_latency'] ?? 7))),
            'wave_amplitude' => max(0, min(80, absint($settings['wave_amplitude'] ?? 28))),
            'wave_expansion' => max(20, min(220, absint($settings['wave_expansion'] ?? 96))),
            'wave_rotation' => max(0, min(12, absint($settings['wave_rotation'] ?? 4))),
        ],
    ];
}

function duola_visual_home_default_layout(): array
{
    return [
        'version' => 1,
        'elements' => [
            [
                'id' => 'home-articles', 'type' => 'home-articles', 'label' => '文章列表', 'content' => '', 'src' => '', 'locked' => true,
                'desktop' => ['position' => 'absolute', 'left' => '3.6%', 'top' => '38%', 'width' => '28%', 'height' => '25%', 'z-index' => '3'], 'mobile' => [],
            ],
            [
                'id' => 'home-preview', 'type' => 'home-preview', 'label' => '顶部刻度', 'content' => '', 'src' => '', 'locked' => true,
                'desktop' => ['position' => 'absolute', 'left' => '36%', 'top' => '5.8%', 'width' => '28%', 'height' => '3%', 'z-index' => '5'], 'mobile' => [],
            ],
            [
                'id' => 'home-rail', 'type' => 'home-rail', 'label' => '照片轨道', 'content' => '', 'src' => '', 'locked' => true,
                'desktop' => ['position' => 'absolute', 'left' => '47%', 'top' => '34%', 'width' => '58%', 'height' => '31%', 'z-index' => '2'], 'mobile' => [],
            ],
            [
                'id' => 'home-link', 'type' => 'home-link', 'label' => '全部照片', 'content' => '全部照片', 'src' => '', 'locked' => true,
                'desktop' => ['position' => 'absolute', 'right' => '2.2%', 'bottom' => '3%', 'width' => '6rem', 'height' => '1.5rem', 'z-index' => '3', 'text-align' => 'right'], 'mobile' => [],
            ],
        ],
        'settings' => [
            'background' => '#111315', 'accent' => '#009fe8', 'show_home' => true, 'home_width' => 'standard', 'focus_x' => 50, 'focus_y' => 50,
            'wave_damping' => 12, 'wave_latency' => 7, 'wave_amplitude' => 28, 'wave_expansion' => 96, 'wave_rotation' => 4,
        ],
    ];
}

function duola_visual_photo_default_layout(int $album_id, int $photo_id): array
{
    $legacy = function_exists('duola_albums_get_photo_settings') ? duola_albums_get_photo_settings($album_id, $photo_id) : [];
    $headline = (string) ($legacy['headline'] ?? '');
    $description = (string) ($legacy['description'] ?? '');
    $date = (string) ($legacy['date'] ?? '');
    $album_title = get_the_title($album_id);
    $focus_x = absint($legacy['focus_x'] ?? 50);
    $focus_y = absint($legacy['focus_y'] ?? 50);
    $accent = sanitize_hex_color($legacy['accent'] ?? '') ?: '#009fe8';
    $background = sanitize_hex_color($legacy['background'] ?? '') ?: '#f3f3f0';

    return [
        'version' => 1,
        'elements' => [
            [
                'id' => 'scene-photo', 'type' => 'photo', 'label' => '主照片', 'content' => '', 'src' => wp_get_attachment_image_url($photo_id, 'duola-lightbox') ?: wp_get_attachment_image_url($photo_id, 'full'), 'locked' => true,
                'desktop' => ['position' => 'absolute', 'left' => '16%', 'top' => '17%', 'width' => '68%', 'height' => '66%', 'z-index' => '2', 'object-fit' => 'cover', 'object-position' => $focus_x . '% ' . $focus_y . '%'], 'mobile' => [],
            ],
            [
                'id' => 'scene-headline', 'type' => 'headline', 'label' => '装饰文字', 'content' => $headline ?: $album_title, 'src' => '', 'locked' => true,
                'desktop' => ['position' => 'absolute', 'left' => '4%', 'top' => '34%', 'width' => '92%', 'height' => '32%', 'z-index' => '4', 'color' => $accent, 'font-size' => '9vw', 'font-weight' => '900', 'line-height' => '.78', 'text-align' => 'center'], 'mobile' => [],
            ],
            [
                'id' => 'scene-date', 'type' => 'date', 'label' => '拍摄日期', 'content' => $date, 'src' => '', 'locked' => true,
                'desktop' => ['position' => 'absolute', 'left' => '3%', 'bottom' => '3%', 'width' => '14rem', 'height' => '2rem', 'z-index' => '5', 'font-size' => '.68rem', 'font-weight' => '700'], 'mobile' => [],
            ],
            [
                'id' => 'scene-description', 'type' => 'description', 'label' => '图片描述', 'content' => $description, 'src' => '', 'locked' => true,
                'desktop' => ['position' => 'absolute', 'right' => '8%', 'bottom' => '3%', 'width' => '28rem', 'min-height' => '2rem', 'z-index' => '5', 'font-size' => '.68rem', 'font-weight' => '700', 'text-align' => 'right'], 'mobile' => [],
            ],
        ],
        'settings' => [
            'background' => $background, 'accent' => $accent, 'show_home' => !isset($legacy['show_home']) || !empty($legacy['show_home']),
            'home_width' => $legacy['home_width'] ?? 'standard', 'focus_x' => $focus_x, 'focus_y' => $focus_y,
            'wave_damping' => 12, 'wave_latency' => 7, 'wave_amplitude' => 28, 'wave_expansion' => 96, 'wave_rotation' => 4,
        ],
    ];
}

function duola_visual_get_home_layout(): array
{
    $saved = get_option('duola_home_visual_layout', []);
    $clean = duola_visual_sanitize_layout($saved);
    return $clean['elements'] ?? [] ? $clean : duola_visual_home_default_layout();
}

function duola_visual_get_photo_scene(int $album_id, int $photo_id): array
{
    $scenes = get_post_meta($album_id, '_duola_album_photo_scenes', true);
    $scene = is_array($scenes) ? ($scenes[(string) $photo_id] ?? null) : null;
    $clean = duola_visual_sanitize_layout($scene);
    return $clean['elements'] ?? [] ? $clean : duola_visual_photo_default_layout($album_id, $photo_id);
}

function duola_visual_register_meta(): void
{
    register_post_meta('album', '_duola_album_photo_scenes', [
        'type' => 'object', 'single' => true, 'show_in_rest' => false,
        'sanitize_callback' => static function ($value): array {
            if (!is_array($value)) {
                return [];
            }
            $clean = [];
            foreach ($value as $photo_id => $scene) {
                $photo_id = absint($photo_id);
                if ($photo_id) {
                    $clean[(string) $photo_id] = duola_visual_sanitize_layout($scene);
                }
            }
            return $clean;
        },
        'auth_callback' => static fn() => current_user_can('edit_posts'),
    ]);
}
add_action('init', 'duola_visual_register_meta');

function duola_visual_admin_menu(): void
{
    add_submenu_page(
        'edit.php?post_type=album',
        __('视觉编排', 'duola-albums'),
        __('视觉编排', 'duola-albums'),
        'edit_posts',
        'duola-visual-editor',
        'duola_visual_render_page'
    );
}
add_action('admin_menu', 'duola_visual_admin_menu', 30);

function duola_visual_editor_url(int $album_id = 0, int $photo_id = 0): string
{
    $args = ['post_type' => 'album', 'page' => 'duola-visual-editor'];
    if ($album_id && $photo_id) {
        $args['mode'] = 'photo';
        $args['album'] = $album_id;
        $args['photo'] = $photo_id;
    }
    return add_query_arg($args, admin_url('edit.php'));
}

function duola_visual_get_request_context(): array
{
    $mode = isset($_GET['mode']) ? sanitize_key(wp_unslash($_GET['mode'])) : 'home';
    $album_id = isset($_GET['album']) ? absint($_GET['album']) : 0;
    $photo_id = isset($_GET['photo']) ? absint($_GET['photo']) : 0;
    if ('photo' !== $mode || !$album_id || !$photo_id || !in_array($photo_id, wp_list_pluck(duola_albums_get_photos($album_id), 'id'), true)) {
        return ['mode' => 'home', 'album_id' => 0, 'photo_id' => 0];
    }
    return ['mode' => 'photo', 'album_id' => $album_id, 'photo_id' => $photo_id];
}

function duola_visual_admin_assets(string $hook): void
{
    if ('album_page_duola-visual-editor' !== $hook) {
        return;
    }
    $context = duola_visual_get_request_context();
    $is_photo = 'photo' === $context['mode'];
    $layout = $is_photo
        ? duola_visual_get_photo_scene($context['album_id'], $context['photo_id'])
        : duola_visual_get_home_layout();

    wp_enqueue_media();
    wp_enqueue_style('duola-grapesjs', DUOLA_ALBUMS_URL . 'assets/grapesjs/grapes.min.css', [], '0.23.2');
    wp_enqueue_style('duola-visual-editor', DUOLA_ALBUMS_URL . 'assets/visual-editor.css', ['duola-grapesjs'], DUOLA_ALBUMS_VERSION);
    wp_enqueue_script('duola-grapesjs', DUOLA_ALBUMS_URL . 'assets/grapesjs/grapes.min.js', [], '0.23.2', true);
    wp_enqueue_script('duola-visual-editor', DUOLA_ALBUMS_URL . 'assets/visual-editor.js', ['duola-grapesjs'], DUOLA_ALBUMS_VERSION, true);

    $preview_url = $is_photo
        ? add_query_arg('duola_photo', $context['photo_id'], get_permalink($context['album_id']))
        : home_url('/');
    wp_localize_script('duola-visual-editor', 'duolaVisualEditor', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('duola_visual_save'),
        'mode' => $context['mode'],
        'albumId' => $context['album_id'],
        'photoId' => $context['photo_id'],
        'layout' => $layout,
        'defaultLayout' => $is_photo ? duola_visual_photo_default_layout($context['album_id'], $context['photo_id']) : duola_visual_home_default_layout(),
        'canvasCssUrl' => DUOLA_ALBUMS_URL . 'assets/visual-canvas.css?ver=' . DUOLA_ALBUMS_VERSION,
        'previewUrl' => $preview_url,
        'homeEditorUrl' => duola_visual_editor_url(),
        'albumTitle' => $is_photo ? get_the_title($context['album_id']) : '',
        'photoUrl' => $is_photo ? (wp_get_attachment_image_url($context['photo_id'], 'duola-lightbox') ?: wp_get_attachment_image_url($context['photo_id'], 'full')) : '',
    ]);
}
add_action('admin_enqueue_scripts', 'duola_visual_admin_assets');

function duola_visual_render_page(): void
{
    $context = duola_visual_get_request_context();
    $photo_mode = 'photo' === $context['mode'];
    ?>
    <div class="wrap duola-visual-wrap">
        <header class="duola-visual-toolbar">
            <div class="duola-visual-title">
                <span><?php echo $photo_mode ? esc_html__('单张照片场景', 'duola-albums') : esc_html__('首页视觉编排', 'duola-albums'); ?></span>
                <strong><?php echo $photo_mode ? esc_html(get_the_title($context['album_id'])) : esc_html__('哆啦D梦的口袋', 'duola-albums'); ?></strong>
            </div>
            <div class="duola-device-switch" role="group" aria-label="<?php esc_attr_e('预览设备', 'duola-albums'); ?>">
                <button type="button" class="is-active" data-device="desktop"><span class="dashicons dashicons-desktop"></span><?php esc_html_e('桌面', 'duola-albums'); ?></button>
                <button type="button" data-device="mobile"><span class="dashicons dashicons-smartphone"></span><?php esc_html_e('手机', 'duola-albums'); ?></button>
            </div>
            <div class="duola-visual-actions">
                <button type="button" class="button" data-command="undo" aria-label="<?php esc_attr_e('撤销', 'duola-albums'); ?>"><span class="dashicons dashicons-undo"></span></button>
                <button type="button" class="button" data-command="redo" aria-label="<?php esc_attr_e('重做', 'duola-albums'); ?>"><span class="dashicons dashicons-redo"></span></button>
                <button type="button" class="button" id="duola-reset-mobile"><?php esc_html_e('手机恢复自动', 'duola-albums'); ?></button>
                <button type="button" class="button" id="duola-reset-layout"><?php esc_html_e('恢复默认', 'duola-albums'); ?></button>
                <a class="button" href="<?php echo esc_url($photo_mode ? get_edit_post_link($context['album_id']) : home_url('/')); ?>"><?php esc_html_e('返回', 'duola-albums'); ?></a>
                <button type="button" class="button" id="duola-open-preview"><?php esc_html_e('预览', 'duola-albums'); ?></button>
                <button type="button" class="button button-primary" id="duola-save-layout"><?php esc_html_e('保存编排', 'duola-albums'); ?></button>
            </div>
        </header>
        <div class="duola-visual-status" id="duola-visual-status" aria-live="polite"></div>
        <div class="duola-visual-shell">
            <aside class="duola-visual-sidebar duola-visual-sidebar-left">
                <section>
                    <h2><?php esc_html_e('添加元素', 'duola-albums'); ?></h2>
                    <div class="duola-visual-add-buttons">
                        <button type="button" class="button" id="duola-add-text"><span class="dashicons dashicons-editor-textcolor"></span><?php esc_html_e('装饰文字', 'duola-albums'); ?></button>
                        <button type="button" class="button" id="duola-add-image"><span class="dashicons dashicons-format-image"></span><?php esc_html_e('装饰图片', 'duola-albums'); ?></button>
                    </div>
                </section>
                <section class="duola-layer-section">
                    <h2><?php esc_html_e('图层', 'duola-albums'); ?></h2>
                    <div id="duola-layers"></div>
                </section>
            </aside>
            <main class="duola-visual-canvas"><div id="duola-gjs"></div></main>
            <aside class="duola-visual-sidebar duola-visual-sidebar-right">
                <section id="duola-content-inspector">
                    <h2><?php esc_html_e('内容', 'duola-albums'); ?></h2>
                    <p class="duola-no-selection"><?php esc_html_e('选择画布中的元素后进行编辑。', 'duola-albums'); ?></p>
                    <div class="duola-selected-controls" hidden>
                        <label for="duola-selected-label"><?php esc_html_e('图层名称', 'duola-albums'); ?></label>
                        <input id="duola-selected-label" type="text">
                        <label for="duola-selected-content"><?php esc_html_e('文字内容', 'duola-albums'); ?></label>
                        <textarea id="duola-selected-content" rows="3"></textarea>
                        <button type="button" class="button" id="duola-selected-image"><?php esc_html_e('更换图片', 'duola-albums'); ?></button>
                        <label class="duola-mobile-visible"><input id="duola-selected-mobile-visible" type="checkbox" checked><?php esc_html_e('手机端显示', 'duola-albums'); ?></label>
                        <button type="button" class="button" id="duola-reset-selected-mobile"><?php esc_html_e('此元素恢复自动适配', 'duola-albums'); ?></button>
                    </div>
                </section>
                <section class="duola-scene-settings" id="duola-scene-settings">
                    <h2><?php echo $photo_mode ? esc_html__('场景设置', 'duola-albums') : esc_html__('波浪设置', 'duola-albums'); ?></h2>
                    <div id="duola-global-settings"></div>
                </section>
                <section class="duola-style-section">
                    <h2><?php esc_html_e('样式', 'duola-albums'); ?></h2>
                    <div id="duola-styles"></div>
                </section>
            </aside>
        </div>
    </div>
    <?php
}

function duola_visual_ajax_save(): void
{
    check_ajax_referer('duola_visual_save', 'nonce');
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => __('没有保存权限。', 'duola-albums')], 403);
    }
    $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : 'home';
    $decoded = isset($_POST['layout']) ? json_decode(wp_unslash($_POST['layout']), true) : [];
    $layout = duola_visual_sanitize_layout($decoded);
    if (!$layout['elements']) {
        wp_send_json_error(['message' => __('布局数据为空。', 'duola-albums')], 400);
    }

    if ('photo' === $mode) {
        $album_id = isset($_POST['album_id']) ? absint($_POST['album_id']) : 0;
        $photo_id = isset($_POST['photo_id']) ? absint($_POST['photo_id']) : 0;
        if (!$album_id || !$photo_id || !current_user_can('edit_post', $album_id)) {
            wp_send_json_error(['message' => __('照片参数无效。', 'duola-albums')], 400);
        }
        $scenes = get_post_meta($album_id, '_duola_album_photo_scenes', true);
        $scenes = is_array($scenes) ? $scenes : [];
        $scenes[(string) $photo_id] = $layout;
        update_post_meta($album_id, '_duola_album_photo_scenes', $scenes);

        $settings = duola_albums_get_all_photo_settings($album_id);
        $legacy = $settings[(string) $photo_id] ?? duola_albums_photo_setting_defaults();
        foreach ($layout['elements'] as $element) {
            if ('headline' === $element['type']) $legacy['headline'] = $element['content'];
            if ('description' === $element['type']) $legacy['description'] = $element['content'];
            if ('date' === $element['type']) $legacy['date'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $element['content']) ? $element['content'] : '';
        }
        $legacy['accent'] = $layout['settings']['accent'];
        $legacy['background'] = $layout['settings']['background'];
        $legacy['show_home'] = $layout['settings']['show_home'];
        $legacy['home_width'] = $layout['settings']['home_width'];
        $legacy['focus_x'] = $layout['settings']['focus_x'];
        $legacy['focus_y'] = $layout['settings']['focus_y'];
        $settings[(string) $photo_id] = $legacy;
        update_post_meta($album_id, '_duola_album_photo_settings', $settings);
    } else {
        update_option('duola_home_visual_layout', $layout, false);
    }
    wp_send_json_success(['message' => __('视觉编排已保存。', 'duola-albums')]);
}
add_action('wp_ajax_duola_visual_save', 'duola_visual_ajax_save');
