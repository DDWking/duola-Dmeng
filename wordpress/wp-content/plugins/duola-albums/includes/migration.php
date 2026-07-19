<?php

if (!defined('ABSPATH')) {
    exit;
}

function duola_migration_add_admin_page(): void
{
    add_menu_page(
        __('备份迁移', 'duola-albums'),
        __('备份迁移', 'duola-albums'),
        'manage_options',
        'duola-migration',
        'duola_migration_render_admin_page',
        'dashicons-migrate',
        8
    );
}
add_action('admin_menu', 'duola_migration_add_admin_page', 30);

function duola_migration_render_admin_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $error_key = 'duola_migration_error_' . get_current_user_id();
    $error = get_transient($error_key);
    if ($error) {
        delete_transient($error_key);
    }
    ?>
    <div class="wrap duola-migration-page">
        <h1><?php esc_html_e('备份迁移', 'duola-albums'); ?></h1>
        <p><?php esc_html_e('迁移包包含文章、标签、相册、全部图片原图、留言与点赞数、瓦力波排行榜、网站头像和基础站点信息。主题代码和 Docker 配置仍由 Git 管理。', 'duola-albums'); ?></p>

        <?php if ($error) : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_GET['duola_imported'])) : ?>
            <div class="notice notice-success is-dismissible"><p>
                <?php
                echo esc_html(sprintf(
                    __('导入完成：%1$d 篇文章、%2$d 本相册、%3$d 张图片、%4$d 条留言与回复、%5$d 条游戏成绩。', 'duola-albums'),
                    absint($_GET['posts'] ?? 0),
                    absint($_GET['albums'] ?? 0),
                    absint($_GET['media'] ?? 0),
                    absint($_GET['guestbook'] ?? 0),
                    absint($_GET['leaderboard'] ?? 0)
                ));
                ?>
            </p></div>
        <?php endif; ?>

        <div class="duola-migration-grid">
            <section class="duola-migration-card">
                <span class="dashicons dashicons-download" aria-hidden="true"></span>
                <h2><?php esc_html_e('导出迁移包', 'duola-albums'); ?></h2>
                <p><?php esc_html_e('生成一个 ZIP 文件并下载到电脑。图片较多时需要等待一会儿，请不要重复点击。', 'duola-albums'); ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
                    <input type="hidden" name="action" value="duola_export_content">
                    <?php wp_nonce_field('duola_export_content'); ?>
                    <?php submit_button(__('一键导出 ZIP', 'duola-albums'), 'primary', 'submit', false); ?>
                </form>
            </section>
            <section class="duola-migration-card">
                <span class="dashicons dashicons-upload" aria-hidden="true"></span>
                <h2><?php esc_html_e('导入迁移包', 'duola-albums'); ?></h2>
                <p><?php esc_html_e('选择本站导出的 ZIP。已有内容会按唯一标识更新，缺少的内容会创建，不会重复复制。', 'duola-albums'); ?></p>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="duola_import_content">
                    <?php wp_nonce_field('duola_import_content'); ?>
                    <input type="file" name="duola_package" accept=".zip,application/zip" required>
                    <?php submit_button(__('一键导入 ZIP', 'duola-albums'), 'secondary', 'submit', false); ?>
                </form>
                <small><?php esc_html_e('只导入你自己从本站导出的迁移包。当前最大上传文件为 1 GB。', 'duola-albums'); ?></small>
            </section>
        </div>
    </div>
    <?php
}

function duola_migration_get_uuid(int $post_id): string
{
    $uuid = (string) get_post_meta($post_id, '_duola_migration_uuid', true);
    if (!wp_is_uuid($uuid)) {
        $uuid = wp_generate_uuid4();
        update_post_meta($post_id, '_duola_migration_uuid', $uuid);
    }
    return $uuid;
}

function duola_migration_get_original_media_file(int $attachment_id, string $display_file, array $metadata): string
{
    $directory = dirname($display_file);
    $display_stem = pathinfo($display_file, PATHINFO_FILENAME);
    $mime_type = (string) get_post_mime_type($attachment_id);
    $extensions = match ($mime_type) {
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/webp' => ['webp'],
        'image/avif' => ['avif'],
        default => [],
    };
    $candidates = [];

    if (str_ends_with($display_stem, '-scaled')) {
        $candidates[] = substr($display_stem, 0, -7);
    }
    if (!empty($metadata['original_image'])) {
        $candidates[] = pathinfo((string) $metadata['original_image'], PATHINFO_FILENAME);
    }
    $candidates[] = $display_stem;

    foreach (array_unique($candidates) as $stem) {
        foreach ($extensions as $extension) {
            $candidate = $directory . DIRECTORY_SEPARATOR . $stem . '.' . $extension;
            if (is_file($candidate) && is_readable($candidate)) {
                return $candidate;
            }
        }
    }

    return $display_file;
}

function duola_migration_collect_media(): array
{
    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => 'image',
        'numberposts' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
    ]);
    $entries = [];
    $id_to_uuid = [];

    foreach ($attachments as $attachment) {
        $file = get_attached_file($attachment->ID);
        if (!$file || !is_file($file) || !is_readable($file)) {
            continue;
        }

        $metadata = wp_get_attachment_metadata($attachment->ID);
        $source_file = duola_migration_get_original_media_file((int) $attachment->ID, $file, is_array($metadata) ? $metadata : []);
        $uuid = duola_migration_get_uuid((int) $attachment->ID);
        $filename = sanitize_file_name(wp_basename($source_file));
        $urls = ['full' => wp_get_attachment_url($attachment->ID)];
        foreach (array_keys(is_array($metadata['sizes'] ?? null) ? $metadata['sizes'] : []) as $size) {
            $size_url = wp_get_attachment_image_url($attachment->ID, $size);
            if ($size_url) {
                $urls[$size] = $size_url;
            }
        }

        $id_to_uuid[(int) $attachment->ID] = $uuid;
        $entries[] = [
            'uuid' => $uuid,
            'archive_path' => 'media/' . $uuid . '-' . $filename,
            'source_path' => $source_file,
            'filename' => $filename,
            'mime_type' => (string) (wp_check_filetype($source_file)['type'] ?? get_post_mime_type($attachment->ID)),
            'title' => $attachment->post_title,
            'caption' => $attachment->post_excerpt,
            'description' => $attachment->post_content,
            'alt' => (string) get_post_meta($attachment->ID, '_wp_attachment_image_alt', true),
            'date' => $attachment->post_date,
            'date_gmt' => $attachment->post_date_gmt,
            'urls' => array_filter($urls),
        ];
    }

    return [$entries, $id_to_uuid];
}

function duola_migration_export_post(WP_Post $post, array $id_to_uuid): array
{
    $tags = wp_get_post_terms($post->ID, 'post_tag', ['fields' => 'slugs']);
    $thumbnail_id = (int) get_post_thumbnail_id($post->ID);
    return [
        'uuid' => duola_migration_get_uuid((int) $post->ID),
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'status' => $post->post_status,
        'date' => $post->post_date,
        'date_gmt' => $post->post_date_gmt,
        'excerpt' => $post->post_excerpt,
        'content' => $post->post_content,
        'tags' => is_wp_error($tags) ? [] : $tags,
        'featured_media_uuid' => $id_to_uuid[$thumbnail_id] ?? '',
    ];
}

function duola_migration_export_album(WP_Post $album, array $id_to_uuid): array
{
    $photos = function_exists('duola_albums_get_photos') ? duola_albums_get_photos((int) $album->ID) : [];
    $photo_uuids = [];
    $photo_settings = [];
    foreach ($photos as $photo) {
        $photo_uuid = $id_to_uuid[(int) $photo['id']] ?? '';
        if (!$photo_uuid) {
            continue;
        }
        $photo_uuids[] = $photo_uuid;
        $photo_settings[$photo_uuid] = (array) ($photo['settings'] ?? []);
    }
    $cover_id = function_exists('duola_albums_get_cover_id') ? duola_albums_get_cover_id((int) $album->ID) : (int) get_post_thumbnail_id($album->ID);

    return [
        'uuid' => duola_migration_get_uuid((int) $album->ID),
        'title' => $album->post_title,
        'slug' => $album->post_name,
        'status' => $album->post_status,
        'date' => $album->post_date,
        'date_gmt' => $album->post_date_gmt,
        'year' => (string) get_post_meta($album->ID, '_duola_album_year', true),
        'location' => (string) get_post_meta($album->ID, '_duola_album_location', true),
        'description' => (string) get_post_meta($album->ID, '_duola_album_description', true),
        'cover_media_uuid' => $id_to_uuid[$cover_id] ?? '',
        'photo_media_uuids' => $photo_uuids,
        'photo_settings' => $photo_settings,
    ];
}

function duola_migration_export_content(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('你没有执行导出的权限。', 'duola-albums'));
    }
    check_admin_referer('duola_export_content');
    wp_raise_memory_limit('admin');
    set_time_limit(0);

    if (!class_exists('ZipArchive')) {
        wp_die(esc_html__('服务器缺少 ZIP 支持。', 'duola-albums'));
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';

    [$media, $id_to_uuid] = duola_migration_collect_media();
    $statuses = ['publish', 'draft', 'pending', 'private', 'future'];
    $posts = get_posts(['post_type' => 'post', 'post_status' => $statuses, 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC']);
    $albums = get_posts(['post_type' => 'album', 'post_status' => $statuses, 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC']);
    $tag_terms = get_terms(['taxonomy' => 'post_tag', 'hide_empty' => false]);
    $avatar_id = (int) get_option('duola_site_avatar_id');

    $manifest_media = array_map(static function (array $item): array {
        unset($item['source_path']);
        return $item;
    }, $media);

    $manifest = [
        'format' => 'duola-pocket-content',
        'version' => 1,
        'exported_at' => gmdate('c'),
        'site' => [
            'name' => get_option('blogname'),
            'description' => get_option('blogdescription'),
            'avatar_media_uuid' => $id_to_uuid[$avatar_id] ?? '',
        ],
        'tags' => is_wp_error($tag_terms) ? [] : array_map(static fn(WP_Term $term): array => ['name' => $term->name, 'slug' => $term->slug, 'description' => $term->description], $tag_terms),
        'media' => $manifest_media,
        'posts' => array_map(static fn(WP_Post $post): array => duola_migration_export_post($post, $id_to_uuid), $posts),
        'albums' => array_map(static fn(WP_Post $album): array => duola_migration_export_album($album, $id_to_uuid), $albums),
        'guestbook' => function_exists('duola_guestbook_export') ? duola_guestbook_export() : [],
        'leaderboard' => function_exists('duola_volleyball_export') ? duola_volleyball_export() : [],
    ];

    $json = wp_json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!$json) {
        wp_die(esc_html__('无法生成迁移清单。', 'duola-albums'));
    }

    $temporary_file = wp_tempnam('duola-content-export.zip');
    $zip = new ZipArchive();
    if (true !== $zip->open($temporary_file, ZipArchive::CREATE | ZipArchive::OVERWRITE)) {
        wp_die(esc_html__('无法创建迁移包。', 'duola-albums'));
    }
    $zip->addFromString('manifest.json', $json);
    foreach ($media as $item) {
        $zip->addFile($item['source_path'], $item['archive_path']);
    }
    $zip->close();

    $filename = 'duola-content-' . wp_date('Ymd-His') . '.zip';
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($temporary_file));
    readfile($temporary_file);
    unlink($temporary_file);
    exit;
}
add_action('admin_post_duola_export_content', 'duola_migration_export_content');

function duola_migration_fail(string $message): void
{
    set_transient('duola_migration_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS * 5);
    wp_safe_redirect(admin_url('admin.php?page=duola-migration'));
    exit;
}

function duola_migration_validate_archive(ZipArchive $zip): bool
{
    $total_size = 0;
    if ($zip->numFiles > 20000) {
        return false;
    }
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index);
        $name = (string) ($stat['name'] ?? '');
        $total_size += (int) ($stat['size'] ?? 0);
        if (!$name || str_contains($name, "\0") || str_starts_with($name, '/') || str_starts_with($name, '\\') || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $name) || preg_match('#^[A-Za-z]:[\\\\/]#', $name)) {
            return false;
        }
        if ($total_size > 5 * GB_IN_BYTES) {
            return false;
        }
    }
    return true;
}

function duola_migration_remove_directory(string $directory): void
{
    $uploads = wp_upload_dir();
    $base = realpath($uploads['basedir']);
    $target = realpath($directory);
    if (!$base || !$target || !str_starts_with($target, $base . DIRECTORY_SEPARATOR) || !str_starts_with(wp_basename($target), 'duola-import-')) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($target);
}

function duola_migration_find_existing(string $post_type, string $uuid): int
{
    if (!wp_is_uuid($uuid)) {
        return 0;
    }
    $ids = get_posts([
        'post_type' => $post_type,
        'post_status' => 'attachment' === $post_type ? 'inherit' : 'any',
        'numberposts' => 1,
        'fields' => 'ids',
        'meta_key' => '_duola_migration_uuid',
        'meta_value' => $uuid,
    ]);
    return $ids ? (int) $ids[0] : 0;
}

function duola_migration_import_terms(array $terms, string $taxonomy): void
{
    foreach ($terms as $term) {
        $slug = sanitize_title($term['slug'] ?? '');
        $name = sanitize_text_field($term['name'] ?? '');
        if (!$slug || !$name || term_exists($slug, $taxonomy)) {
            continue;
        }
        wp_insert_term($name, $taxonomy, [
            'slug' => $slug,
            'description' => sanitize_textarea_field($term['description'] ?? ''),
        ]);
    }
}

function duola_migration_import_media(array $entries, string $directory): array
{
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $media_ids = [];
    $url_map = [];

    foreach ($entries as $entry) {
        $uuid = sanitize_text_field($entry['uuid'] ?? '');
        $attachment_id = duola_migration_find_existing('attachment', $uuid);
        if (!$attachment_id) {
            $archive_path = ltrim(str_replace('\\', '/', (string) ($entry['archive_path'] ?? '')), '/');
            $source = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $archive_path);
            if (!is_file($source)) {
                continue;
            }
            $filename = sanitize_file_name($entry['filename'] ?? wp_basename($source));
            $temporary_file = wp_tempnam($filename);
            if (!$temporary_file || !copy($source, $temporary_file)) {
                continue;
            }
            $attachment_id = media_handle_sideload([
                'name' => $filename,
                'type' => sanitize_mime_type($entry['mime_type'] ?? ''),
                'tmp_name' => $temporary_file,
                'error' => 0,
                'size' => filesize($temporary_file),
            ], 0, sanitize_text_field($entry['title'] ?? ''), [
                'post_date' => sanitize_text_field($entry['date'] ?? current_time('mysql')),
                'post_date_gmt' => sanitize_text_field($entry['date_gmt'] ?? current_time('mysql', true)),
                'post_excerpt' => sanitize_textarea_field($entry['caption'] ?? ''),
                'post_content' => wp_kses_post($entry['description'] ?? ''),
            ]);
            if (is_wp_error($attachment_id)) {
                @unlink($temporary_file);
                continue;
            }
        } else {
            wp_update_post(wp_slash([
                'ID' => $attachment_id,
                'post_title' => sanitize_text_field($entry['title'] ?? ''),
                'post_excerpt' => sanitize_textarea_field($entry['caption'] ?? ''),
                'post_content' => wp_kses_post($entry['description'] ?? ''),
            ]));
        }

        update_post_meta($attachment_id, '_duola_migration_uuid', $uuid);
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($entry['alt'] ?? ''));
        $media_ids[$uuid] = $attachment_id;
        foreach ((array) ($entry['urls'] ?? []) as $size => $old_url) {
            $new_url = 'full' === $size ? wp_get_attachment_url($attachment_id) : wp_get_attachment_image_url($attachment_id, sanitize_key($size));
            if ($old_url && $new_url) {
                $url_map[(string) $old_url] = $new_url;
            }
        }
    }

    uksort($url_map, static fn(string $left, string $right): int => strlen($right) <=> strlen($left));
    return [$media_ids, $url_map];
}

function duola_migration_replace_urls(string $content, array $url_map): string
{
    return $url_map ? str_replace(array_keys($url_map), array_values($url_map), $content) : $content;
}

function duola_migration_import_posts(array $entries, array $media_ids, array $url_map): int
{
    $count = 0;
    foreach ($entries as $entry) {
        $uuid = sanitize_text_field($entry['uuid'] ?? '');
        $post_id = duola_migration_find_existing('post', $uuid);
        $post_data = [
            'post_type' => 'post',
            'post_title' => sanitize_text_field($entry['title'] ?? ''),
            'post_name' => sanitize_title($entry['slug'] ?? ''),
            'post_status' => in_array($entry['status'] ?? '', ['publish', 'draft', 'pending', 'private', 'future'], true) ? $entry['status'] : 'draft',
            'post_date' => sanitize_text_field($entry['date'] ?? current_time('mysql')),
            'post_date_gmt' => sanitize_text_field($entry['date_gmt'] ?? current_time('mysql', true)),
            'post_excerpt' => sanitize_textarea_field($entry['excerpt'] ?? ''),
            'post_content' => duola_migration_replace_urls((string) ($entry['content'] ?? ''), $url_map),
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ];
        if ($post_id) {
            $post_data['ID'] = $post_id;
        }
        $post_id = wp_insert_post(wp_slash($post_data), true);
        if (is_wp_error($post_id)) {
            continue;
        }
        update_post_meta($post_id, '_duola_migration_uuid', $uuid);
        $tag_ids = [];
        foreach ((array) ($entry['tags'] ?? []) as $slug) {
            $term = get_term_by('slug', sanitize_title($slug), 'post_tag');
            if ($term) {
                $tag_ids[] = $term->term_id;
            }
        }
        wp_set_post_terms($post_id, $tag_ids, 'post_tag', false);
        $featured_uuid = sanitize_text_field($entry['featured_media_uuid'] ?? '');
        if (isset($media_ids[$featured_uuid])) {
            set_post_thumbnail($post_id, $media_ids[$featured_uuid]);
        } else {
            delete_post_thumbnail($post_id);
        }
        $count++;
    }
    return $count;
}

function duola_migration_import_albums(array $entries, array $media_ids): int
{
    $count = 0;
    foreach ($entries as $entry) {
        $uuid = sanitize_text_field($entry['uuid'] ?? '');
        $album_id = duola_migration_find_existing('album', $uuid);
        $post_data = [
            'post_type' => 'album',
            'post_title' => sanitize_text_field($entry['title'] ?? ''),
            'post_name' => sanitize_title($entry['slug'] ?? ''),
            'post_status' => in_array($entry['status'] ?? '', ['publish', 'draft', 'pending', 'private', 'future'], true) ? $entry['status'] : 'draft',
            'post_date' => sanitize_text_field($entry['date'] ?? current_time('mysql')),
            'post_date_gmt' => sanitize_text_field($entry['date_gmt'] ?? current_time('mysql', true)),
            'comment_status' => 'closed',
            'ping_status' => 'closed',
        ];
        if ($album_id) {
            $post_data['ID'] = $album_id;
        }
        $album_id = wp_insert_post(wp_slash($post_data), true);
        if (is_wp_error($album_id)) {
            continue;
        }

        update_post_meta($album_id, '_duola_migration_uuid', $uuid);
        update_post_meta($album_id, '_duola_album_year', absint($entry['year'] ?? 0));
        update_post_meta($album_id, '_duola_album_location', sanitize_text_field($entry['location'] ?? ''));
        update_post_meta($album_id, '_duola_album_description', wp_kses_post($entry['description'] ?? ''));
        $photo_ids = [];
        foreach ((array) ($entry['photo_media_uuids'] ?? []) as $photo_uuid) {
            if (isset($media_ids[$photo_uuid])) {
                $photo_ids[] = $media_ids[$photo_uuid];
            }
        }
        update_post_meta($album_id, '_duola_album_photos', array_values(array_unique($photo_ids)));
        $photo_settings = [];
        foreach ((array) ($entry['photo_settings'] ?? []) as $photo_uuid => $settings) {
            if (isset($media_ids[$photo_uuid]) && is_array($settings)) {
                $photo_settings[(string) $media_ids[$photo_uuid]] = $settings;
            }
        }
        update_post_meta(
            $album_id,
            '_duola_album_photo_settings',
            function_exists('duola_albums_sanitize_photo_settings') ? duola_albums_sanitize_photo_settings($photo_settings) : $photo_settings
        );
        $cover_uuid = sanitize_text_field($entry['cover_media_uuid'] ?? '');
        $cover_id = $media_ids[$cover_uuid] ?? ($photo_ids[0] ?? 0);
        update_post_meta($album_id, '_duola_album_cover_id', $cover_id);
        if ($cover_id) {
            set_post_thumbnail($album_id, $cover_id);
        } else {
            delete_post_thumbnail($album_id);
        }
        $count++;
    }
    return $count;
}

function duola_migration_import_content(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('你没有执行导入的权限。', 'duola-albums'));
    }
    check_admin_referer('duola_import_content');
    wp_raise_memory_limit('admin');
    set_time_limit(0);

    if (!class_exists('ZipArchive') || empty($_FILES['duola_package']['tmp_name'])) {
        duola_migration_fail(__('没有收到有效的 ZIP 文件。', 'duola-albums'));
    }
    $upload = $_FILES['duola_package'];
    if (UPLOAD_ERR_OK !== (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) || 'zip' !== strtolower(pathinfo((string) ($upload['name'] ?? ''), PATHINFO_EXTENSION))) {
        duola_migration_fail(__('ZIP 上传失败，请检查文件大小后重试。', 'duola-albums'));
    }

    $uploads = wp_upload_dir();
    $directory = trailingslashit($uploads['basedir']) . 'duola-import-' . wp_generate_password(12, false, false);
    if (!wp_mkdir_p($directory)) {
        duola_migration_fail(__('无法创建临时导入目录。', 'duola-albums'));
    }

    $zip = new ZipArchive();
    if (true !== $zip->open($upload['tmp_name']) || !duola_migration_validate_archive($zip) || !$zip->extractTo($directory)) {
        if ($zip->status === ZipArchive::ER_OK) {
            $zip->close();
        }
        duola_migration_remove_directory($directory);
        duola_migration_fail(__('迁移包无效或包含不安全的文件路径。', 'duola-albums'));
    }
    $zip->close();

    $manifest_path = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
    $manifest = is_file($manifest_path) ? json_decode((string) file_get_contents($manifest_path), true) : null;
    if (!is_array($manifest) || 'duola-pocket-content' !== ($manifest['format'] ?? '') || 1 !== (int) ($manifest['version'] ?? 0)) {
        duola_migration_remove_directory($directory);
        duola_migration_fail(__('这不是有效的哆啦D梦迁移包。', 'duola-albums'));
    }

    duola_migration_import_terms((array) ($manifest['tags'] ?? []), 'post_tag');
    [$media_ids, $url_map] = duola_migration_import_media((array) ($manifest['media'] ?? []), $directory);
    $post_count = duola_migration_import_posts((array) ($manifest['posts'] ?? []), $media_ids, $url_map);
    $album_count = duola_migration_import_albums((array) ($manifest['albums'] ?? []), $media_ids);
    $guestbook_count = function_exists('duola_guestbook_import') ? duola_guestbook_import((array) ($manifest['guestbook'] ?? [])) : 0;
    $leaderboard_count = function_exists('duola_volleyball_import') ? duola_volleyball_import((array) ($manifest['leaderboard'] ?? [])) : 0;

    $site = (array) ($manifest['site'] ?? []);
    if (!empty($site['name'])) {
        update_option('blogname', sanitize_text_field($site['name']));
    }
    update_option('blogdescription', sanitize_text_field($site['description'] ?? ''));
    $avatar_uuid = sanitize_text_field($site['avatar_media_uuid'] ?? '');
    update_option('duola_site_avatar_id', $media_ids[$avatar_uuid] ?? 0);

    duola_migration_remove_directory($directory);
    wp_safe_redirect(add_query_arg([
        'post_type' => 'album',
        'page' => 'duola-migration',
        'duola_imported' => 1,
        'posts' => $post_count,
        'albums' => $album_count,
        'media' => count($media_ids),
        'guestbook' => $guestbook_count,
        'leaderboard' => $leaderboard_count,
    ], admin_url('admin.php?page=duola-migration')));
    exit;
}
add_action('admin_post_duola_import_content', 'duola_migration_import_content');
