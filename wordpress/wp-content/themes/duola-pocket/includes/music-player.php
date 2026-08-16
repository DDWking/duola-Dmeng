<?php
/**
 * Music library settings and the site-wide mini player with lyric stage.
 */

if (!defined('ABSPATH')) {
    exit;
}

function duola_pocket_allow_lyrics_upload_mime(array $mimes): array
{
    $mimes['lrc'] = 'text/plain';
    return $mimes;
}
add_filter('upload_mimes', 'duola_pocket_allow_lyrics_upload_mime');

function duola_pocket_fix_lyrics_filetype($data, $file, $filename, $mimes)
{
    if ('lrc' === strtolower(pathinfo($filename, PATHINFO_EXTENSION))) {
        $data['ext'] = 'lrc';
        $data['type'] = 'text/plain';
    }
    return $data;
}
add_filter('wp_check_filetype_and_ext', 'duola_pocket_fix_lyrics_filetype', 10, 4);

function duola_pocket_is_lyrics_attachment(int $attachment_id): bool
{
    if ($attachment_id <= 0) {
        return false;
    }

    $attachment = get_post($attachment_id);
    if (!$attachment || 'attachment' !== $attachment->post_type) {
        return false;
    }

    $mime = (string) get_post_mime_type($attachment_id);
    $path = (string) get_attached_file($attachment_id);
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    if (in_array($ext, ['lrc', 'txt'], true)) {
        return true;
    }

    // Some hosts store .lrc as text/plain or application/octet-stream.
    return 0 === strpos($mime, 'text/') || 'application/octet-stream' === $mime;
}

function duola_pocket_sanitize_music_tracks($tracks): array
{
    if (!is_array($tracks)) {
        return [];
    }

    $sanitized = [];
    foreach (array_slice($tracks, 0, 100) as $track) {
        if (!is_array($track)) {
            continue;
        }

        $audio_id = absint($track['audio_id'] ?? 0);
        $audio = $audio_id ? get_post($audio_id) : null;
        $mime_type = $audio_id ? (string) get_post_mime_type($audio_id) : '';
        if (!$audio || 'attachment' !== $audio->post_type || 0 !== strpos($mime_type, 'audio/')) {
            continue;
        }

        $cover_id = absint($track['cover_id'] ?? 0);
        if ($cover_id && !wp_attachment_is_image($cover_id)) {
            $cover_id = 0;
        }

        $lyrics_id = absint($track['lyrics_id'] ?? 0);
        if ($lyrics_id && !duola_pocket_is_lyrics_attachment($lyrics_id)) {
            $lyrics_id = 0;
        }

        $title = sanitize_text_field($track['title'] ?? '');
        $sanitized[] = [
            'audio_id' => $audio_id,
            'cover_id' => $cover_id,
            'lyrics_id' => $lyrics_id,
            'title' => $title ?: get_the_title($audio_id),
            'artist' => sanitize_text_field($track['artist'] ?? ''),
        ];
    }

    return $sanitized;
}

function duola_pocket_register_music_settings(): void
{
    register_setting('duola_site_settings', 'duola_music_tracks', [
        'type' => 'array',
        'sanitize_callback' => 'duola_pocket_sanitize_music_tracks',
        'default' => [],
    ]);
}
add_action('admin_init', 'duola_pocket_register_music_settings');

function duola_pocket_music_track_admin_data(array $track): array
{
    $audio_id = absint($track['audio_id'] ?? 0);
    $cover_id = absint($track['cover_id'] ?? 0);
    $lyrics_id = absint($track['lyrics_id'] ?? 0);
    $audio_path = $audio_id ? get_attached_file($audio_id) : '';
    $lyrics_path = $lyrics_id ? get_attached_file($lyrics_id) : '';

    return [
        'audio_id' => $audio_id,
        'cover_id' => $cover_id,
        'lyrics_id' => $lyrics_id,
        'title' => (string) ($track['title'] ?? ''),
        'artist' => (string) ($track['artist'] ?? ''),
        'filename' => $audio_path ? wp_basename($audio_path) : '',
        'lyrics_filename' => $lyrics_path ? wp_basename($lyrics_path) : '',
        'cover_url' => $cover_id ? (string) wp_get_attachment_image_url($cover_id, 'thumbnail') : '',
    ];
}

function duola_pocket_render_music_track_row(array $track, string $index): void
{
    $track = duola_pocket_music_track_admin_data($track);
    $field_prefix = 'duola_music_tracks[' . $index . ']';
    ?>
    <article class="duola-music-track" data-music-track>
        <div class="duola-music-cover" data-cover-preview<?php echo $track['cover_url'] ? '' : ' data-empty="true"'; ?>>
            <img src="<?php echo esc_url($track['cover_url']); ?>" alt=""<?php echo $track['cover_url'] ? '' : ' hidden'; ?>>
            <span class="dashicons dashicons-format-audio" aria-hidden="true"></span>
        </div>
        <div class="duola-music-track-fields">
            <input data-track-field="audio_id" name="<?php echo esc_attr($field_prefix . '[audio_id]'); ?>" type="hidden" value="<?php echo esc_attr($track['audio_id']); ?>">
            <input data-track-field="cover_id" name="<?php echo esc_attr($field_prefix . '[cover_id]'); ?>" type="hidden" value="<?php echo esc_attr($track['cover_id']); ?>">
            <input data-track-field="lyrics_id" name="<?php echo esc_attr($field_prefix . '[lyrics_id]'); ?>" type="hidden" value="<?php echo esc_attr($track['lyrics_id']); ?>">
            <div class="duola-music-file-row">
                <strong data-audio-filename><?php echo esc_html($track['filename'] ?: __('尚未选择音乐', 'duola-pocket')); ?></strong>
                <button class="button" type="button" data-select-audio><?php esc_html_e('选择音乐', 'duola-pocket'); ?></button>
                <button class="button" type="button" data-select-cover><?php esc_html_e('选择封面', 'duola-pocket'); ?></button>
                <button class="button" type="button" data-remove-cover<?php echo $track['cover_id'] ? '' : ' hidden'; ?>><?php esc_html_e('移除封面', 'duola-pocket'); ?></button>
            </div>
            <div class="duola-music-file-row duola-music-lyrics-row">
                <span class="duola-music-lyrics-label"><?php esc_html_e('歌词 LRC', 'duola-pocket'); ?></span>
                <strong data-lyrics-filename><?php echo esc_html($track['lyrics_filename'] ?: __('未选择（可选）', 'duola-pocket')); ?></strong>
                <button class="button" type="button" data-select-lyrics><?php esc_html_e('选择歌词', 'duola-pocket'); ?></button>
                <button class="button" type="button" data-remove-lyrics<?php echo $track['lyrics_id'] ? '' : ' hidden'; ?>><?php esc_html_e('移除歌词', 'duola-pocket'); ?></button>
            </div>
            <div class="duola-music-meta-fields">
                <label>
                    <span><?php esc_html_e('歌曲名', 'duola-pocket'); ?></span>
                    <input data-track-field="title" name="<?php echo esc_attr($field_prefix . '[title]'); ?>" type="text" value="<?php echo esc_attr($track['title']); ?>" placeholder="<?php esc_attr_e('默认使用文件标题', 'duola-pocket'); ?>">
                </label>
                <label>
                    <span><?php esc_html_e('歌手', 'duola-pocket'); ?></span>
                    <input data-track-field="artist" name="<?php echo esc_attr($field_prefix . '[artist]'); ?>" type="text" value="<?php echo esc_attr($track['artist']); ?>" placeholder="<?php esc_attr_e('选填', 'duola-pocket'); ?>">
                </label>
            </div>
        </div>
        <div class="duola-music-track-actions">
            <button class="button-link" type="button" data-move-track="up" aria-label="<?php esc_attr_e('上移', 'duola-pocket'); ?>" title="<?php esc_attr_e('上移', 'duola-pocket'); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
            <button class="button-link" type="button" data-move-track="down" aria-label="<?php esc_attr_e('下移', 'duola-pocket'); ?>" title="<?php esc_attr_e('下移', 'duola-pocket'); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
            <button class="button-link-delete" type="button" data-remove-track aria-label="<?php esc_attr_e('删除歌曲', 'duola-pocket'); ?>" title="<?php esc_attr_e('删除', 'duola-pocket'); ?>"><span class="dashicons dashicons-trash"></span></button>
        </div>
    </article>
    <?php
}

function duola_pocket_render_music_settings(): void
{
    $tracks = get_option('duola_music_tracks', []);
    $tracks = is_array($tracks) ? $tracks : [];
    ?>
    <section class="duola-settings-card duola-music-setting">
        <div class="duola-music-setting-heading">
            <div>
                <h2><?php esc_html_e('音乐播放器', 'duola-pocket'); ?></h2>
                <p><?php esc_html_e('从媒体库添加音乐、封面与可选 LRC 歌词。曲库有内容时，首页记忆板右上角会出现圆形控件与歌词舞台。', 'duola-pocket'); ?></p>
            </div>
            <button class="button button-primary" type="button" data-add-music-track><?php esc_html_e('添加歌曲', 'duola-pocket'); ?></button>
        </div>
        <div class="duola-music-track-list" data-music-track-list>
            <?php foreach ($tracks as $index => $track) : ?>
                <?php duola_pocket_render_music_track_row((array) $track, (string) $index); ?>
            <?php endforeach; ?>
        </div>
        <div class="duola-music-empty" data-music-empty<?php echo $tracks ? ' hidden' : ''; ?>>
            <span class="dashicons dashicons-playlist-audio" aria-hidden="true"></span>
            <strong><?php esc_html_e('曲库还是空的', 'duola-pocket'); ?></strong>
            <p><?php esc_html_e('上传 MP3、M4A、Ogg 等浏览器支持的音频后，就可以在这里建立播放列表。歌词请上传 .lrc 文件（媒体库可选「文件」类型）。', 'duola-pocket'); ?></p>
        </div>
        <template id="duola-music-track-template">
            <?php duola_pocket_render_music_track_row([], '__INDEX__'); ?>
        </template>
    </section>
    <?php
}

function duola_pocket_music_tracks(): array
{
    $saved_tracks = get_option('duola_music_tracks', []);
    if (!is_array($saved_tracks)) {
        return [];
    }

    $tracks = [];
    foreach ($saved_tracks as $track) {
        $audio_id = absint($track['audio_id'] ?? 0);
        $source = $audio_id ? wp_get_attachment_url($audio_id) : '';
        if (!$source) {
            continue;
        }

        $title = sanitize_text_field($track['title'] ?? '');
        $cover_id = absint($track['cover_id'] ?? 0);
        $lyrics_id = absint($track['lyrics_id'] ?? 0);
        $lyrics_url = '';
        if ($lyrics_id && duola_pocket_is_lyrics_attachment($lyrics_id)) {
            $lyrics_url = (string) wp_get_attachment_url($lyrics_id);
        }

        $tracks[] = [
            'id' => $audio_id,
            'src' => esc_url_raw($source),
            'type' => (string) get_post_mime_type($audio_id),
            'title' => $title ?: get_the_title($audio_id),
            'artist' => sanitize_text_field($track['artist'] ?? ''),
            'cover' => $cover_id ? (string) wp_get_attachment_image_url($cover_id, 'medium') : '',
            'lyrics' => $lyrics_url ? esc_url_raw($lyrics_url) : '',
        ];
    }

    return $tracks;
}

function duola_pocket_music_player_tracks(): array
{
    static $tracks = null;
    if (null === $tracks) {
        $tracks = duola_pocket_music_tracks();
    }
    return $tracks;
}

function duola_pocket_should_show_music_player(): bool
{
    return !is_admin()
        && !is_feed()
        && !duola_pocket_is_wall_page()
        && (bool) duola_pocket_music_player_tracks();
}

function duola_pocket_enqueue_music_player(): void
{
    if (!duola_pocket_should_show_music_player()) {
        return;
    }

    $style_path = get_template_directory() . '/assets/music-player.css';
    $core_script_path = get_template_directory() . '/assets/luminous-lyrics-core.js';
    $script_path = get_template_directory() . '/assets/music-player.js';
    wp_enqueue_style('duola-pocket-music-player', get_template_directory_uri() . '/assets/music-player.css', ['duola-pocket-style'], (string) filemtime($style_path));
    wp_enqueue_script('duola-pocket-luminous-lyrics-core', get_template_directory_uri() . '/assets/luminous-lyrics-core.js', [], (string) filemtime($core_script_path), true);
    wp_enqueue_script('duola-pocket-music-player', get_template_directory_uri() . '/assets/music-player.js', ['duola-pocket-turbo', 'duola-pocket-luminous-lyrics-core'], (string) filemtime($script_path), true);
    wp_localize_script('duola-pocket-music-player', 'duolaMusicPlayer', [
        'tracks' => duola_pocket_music_player_tracks(),
        'storageKey' => 'duolaMusicPlayer:v3',
        'showLyrics' => is_front_page(),
    ]);
}
add_action('wp_enqueue_scripts', 'duola_pocket_enqueue_music_player', 20);

function duola_pocket_music_icon(string $name): void
{
    $paths = [
        'previous' => '<path d="M19 20 9 12l10-8v16Z"/><path d="M5 19V5"/>',
        'play' => '<path d="m7 4 13 8-13 8V4Z"/>',
        'pause' => '<path d="M9 4H5v16h4V4ZM19 4h-4v16h4V4Z"/>',
        'next' => '<path d="m5 4 10 8-10 8V4Z"/><path d="M19 5v14"/>',
        'expand' => '<path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/>',
        'close' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'note' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    ];

    if (!isset($paths[$name])) {
        return;
    }
    echo '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function duola_pocket_render_music_player(): void
{
    if (!duola_pocket_should_show_music_player()) {
        return;
    }

    ?>
    <aside id="duola-music-player" class="home-music" data-music-player data-turbo-permanent data-show-lyrics="<?php echo is_front_page() ? 'true' : 'false'; ?>" aria-label="音乐典藏馆">
        <div class="home-music-lyric" data-lyric-stage hidden aria-hidden="true">
            <div class="home-music-lyric-line" data-lyric-line></div>
        </div>

        <div class="home-music-panel" id="duola-music-panel" data-panel hidden data-console="archive">
            <div class="home-music-turntable" aria-hidden="true">
                <span class="home-music-vinyl">
                    <span class="home-music-vinyl-grooves"></span>
                    <span class="home-music-vinyl-label"><img data-cover-image alt="" hidden><span data-note-fallback><?php duola_pocket_music_icon('note'); ?></span></span>
                    <span class="home-music-vinyl-shine"></span>
                </span>
                <span class="home-music-tonearm"><i></i></span>
            </div>
            <header class="home-music-panel-head">
                <div>
                    <small class="home-music-archive-number" data-archive-index>ARCHIVE 001</small>
                    <strong class="home-music-panel-brand">音乐典藏馆</strong><strong class="home-music-panel-track" data-panel-title></strong>
                    <span data-panel-artist></span>
                </div>
                <button class="home-music-icon-btn" type="button" data-close-panel aria-label="<?php esc_attr_e('收起面板', 'duola-pocket'); ?>">
                    <?php duola_pocket_music_icon('close'); ?>
                </button>
            </header>

            <div class="home-music-spectrum" aria-hidden="true">
                <?php for ($bar = 0; $bar < 12; $bar++) : ?><i style="--meter-index: <?php echo esc_attr((string) $bar); ?>"></i><?php endfor; ?>
            </div>

            <div class="home-music-transport" aria-label="<?php esc_attr_e('播放控制', 'duola-pocket'); ?>">
                <button class="home-music-icon-btn" type="button" data-previous aria-label="<?php esc_attr_e('上一首', 'duola-pocket'); ?>"><?php duola_pocket_music_icon('previous'); ?></button>
                <button class="home-music-icon-btn home-music-icon-play" type="button" data-play-panel aria-label="<?php esc_attr_e('播放', 'duola-pocket'); ?>">
                    <span data-play-icon-panel><?php duola_pocket_music_icon('play'); ?></span>
                    <span data-pause-icon-panel hidden><?php duola_pocket_music_icon('pause'); ?></span>
                </button>
                <button class="home-music-icon-btn" type="button" data-next aria-label="<?php esc_attr_e('下一首', 'duola-pocket'); ?>"><?php duola_pocket_music_icon('next'); ?></button>
            </div>

            <div class="home-music-timeline">
                <span data-current-time>0:00</span>
                <input data-seek type="range" min="0" max="1000" value="0" step="1" aria-label="<?php esc_attr_e('播放进度', 'duola-pocket'); ?>">
                <span data-duration>0:00</span>
            </div>
        </div>

        <button class="home-music-wave" type="button" data-music-wave aria-label="<?php esc_attr_e('打开音乐播放器', 'duola-pocket'); ?>">
            <?php for ($bar = 0; $bar < 48; $bar++) : ?><i style="--meter-index: <?php echo esc_attr((string) $bar); ?>"></i><?php endfor; ?>
        </button>

        <audio data-audio preload="metadata"></audio>
    </aside>
    <?php
}

function duola_pocket_render_global_music_player(): void
{
    if (!duola_pocket_should_show_music_player()) {
        return;
    }
    ?>
    <div class="memory-board global-music-layer" aria-hidden="false">
        <div class="home-music-slot">
            <?php duola_pocket_render_music_player(); ?>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'duola_pocket_render_global_music_player', 5);
