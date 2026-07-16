<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Parsedown', false)) {
    require_once DUOLA_ALBUMS_PATH . 'vendor/parsedown/Parsedown.php';
}

const DUOLA_MARKDOWN_MAX_BYTES = 2097152;

function duola_markdown_add_admin_page(): void
{
    add_submenu_page(
        'edit.php',
        __('导入 Markdown', 'duola-albums'),
        __('导入 Markdown', 'duola-albums'),
        'edit_posts',
        'duola-markdown-import',
        'duola_markdown_render_admin_page',
        11
    );
}
add_action('admin_menu', 'duola_markdown_add_admin_page', 25);

function duola_markdown_render_admin_page(): void
{
    if (!current_user_can('edit_posts')) {
        return;
    }

    $error_key = 'duola_markdown_error_' . get_current_user_id();
    $error = get_transient($error_key);
    if ($error) {
        delete_transient($error_key);
    }
    ?>
    <div class="wrap duola-markdown-page">
        <div class="duola-settings-heading">
            <span>Markdown import</span>
            <h1><?php esc_html_e('导入 Markdown 文章', 'duola-albums'); ?></h1>
            <p><?php esc_html_e('上传一个 .md 文件，生成文章草稿，然后继续设置标签、封面或发布时间。', 'duola-albums'); ?></p>
        </div>

        <?php if ($error) : ?>
            <div class="notice notice-error"><p><?php echo esc_html($error); ?></p></div>
        <?php endif; ?>

        <form class="duola-markdown-card" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="duola_import_markdown">
            <?php wp_nonce_field('duola_import_markdown'); ?>
            <label class="duola-markdown-file" for="duola-markdown-file">
                <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                <strong><?php esc_html_e('选择 Markdown 文件', 'duola-albums'); ?></strong>
                <small><?php esc_html_e('支持 .md 和 .markdown，文件最大 2 MB', 'duola-albums'); ?></small>
                <input id="duola-markdown-file" name="duola_markdown" type="file" accept=".md,.markdown,text/markdown,text/plain" required>
            </label>
            <div class="duola-markdown-rules">
                <p><b><?php esc_html_e('标题', 'duola-albums'); ?></b><?php esc_html_e('首个非空行如果是一级标题，就用作文章标题；否则使用文件名。', 'duola-albums'); ?></p>
                <p><b><?php esc_html_e('正文', 'duola-albums'); ?></b><?php esc_html_e('段落、标题、列表、引用、链接、图片和代码块会转换为可编辑内容。', 'duola-albums'); ?></p>
                <p><b><?php esc_html_e('状态', 'duola-albums'); ?></b><?php esc_html_e('始终先保存为草稿，不会直接公开。', 'duola-albums'); ?></p>
            </div>
            <?php submit_button(__('导入并打开编辑器', 'duola-albums'), 'primary', 'submit', false); ?>
        </form>
    </div>
    <?php
}

function duola_markdown_fail(string $message): void
{
    set_transient('duola_markdown_error_' . get_current_user_id(), $message, MINUTE_IN_SECONDS * 5);
    wp_safe_redirect(admin_url('edit.php?page=duola-markdown-import'));
    exit;
}

function duola_markdown_parser(): Parsedown
{
    $parser = new Parsedown();
    $parser->setSafeMode(true);
    $parser->setStrictMode(true);
    return $parser;
}

function duola_markdown_block(string $name, string $html, array $attributes = []): string
{
    $json = $attributes ? ' ' . wp_json_encode($attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
    return "<!-- wp:{$name}{$json} -->\n{$html}\n<!-- /wp:{$name} -->";
}

function duola_markdown_add_class(DOMElement $element, string $class_name): void
{
    $classes = array_filter(explode(' ', (string) $element->getAttribute('class')));
    $classes[] = $class_name;
    $element->setAttribute('class', implode(' ', array_unique($classes)));
}

function duola_markdown_html_to_blocks(string $html): string
{
    if ('' === trim($html) || !class_exists('DOMDocument')) {
        return '' === trim($html) ? '' : duola_markdown_block('freeform', $html);
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $previous_errors = libxml_use_internal_errors(true);
    $loaded = $document->loadHTML(
        '<?xml encoding="utf-8" ?><div id="duola-markdown-root">' . $html . '</div>',
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();
    libxml_use_internal_errors($previous_errors);
    if (!$loaded) {
        return duola_markdown_block('freeform', $html);
    }

    $root = (new DOMXPath($document))->query('//*[@id="duola-markdown-root"]')->item(0);
    if (!$root) {
        return duola_markdown_block('freeform', $html);
    }

    $blocks = [];
    foreach (iterator_to_array($root->childNodes) as $node) {
        if (XML_TEXT_NODE === $node->nodeType) {
            $text = trim((string) $node->textContent);
            if ($text) {
                $blocks[] = duola_markdown_block('paragraph', '<p>' . esc_html($text) . '</p>');
            }
            continue;
        }
        if (!($node instanceof DOMElement)) {
            continue;
        }

        $tag = strtolower($node->tagName);
        if ('p' === $tag) {
            $element_children = array_values(array_filter(iterator_to_array($node->childNodes), static fn(DOMNode $child): bool => XML_ELEMENT_NODE === $child->nodeType));
            $non_whitespace_text = trim((string) preg_replace('/\s+/u', '', $node->textContent));
            if (1 === count($element_children) && $element_children[0] instanceof DOMElement && 'img' === strtolower($element_children[0]->tagName) && '' === $non_whitespace_text) {
                $image_html = $document->saveHTML($element_children[0]);
                $blocks[] = duola_markdown_block('image', '<figure class="wp-block-image size-full">' . $image_html . '</figure>', ['sizeSlug' => 'full', 'linkDestination' => 'none']);
            } else {
                $blocks[] = duola_markdown_block('paragraph', $document->saveHTML($node));
            }
            continue;
        }

        if (preg_match('/^h([1-6])$/', $tag, $heading_match)) {
            $level = (int) $heading_match[1];
            duola_markdown_add_class($node, 'wp-block-heading');
            $blocks[] = duola_markdown_block('heading', $document->saveHTML($node), 2 === $level ? [] : ['level' => $level]);
            continue;
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            duola_markdown_add_class($node, 'wp-block-list');
            foreach (iterator_to_array($node->childNodes) as $list_item) {
                if (!($list_item instanceof DOMElement) || 'li' !== strtolower($list_item->tagName)) {
                    continue;
                }
                $node->insertBefore($document->createComment(' wp:list-item '), $list_item);
                $closing_comment = $document->createComment(' /wp:list-item ');
                if ($list_item->nextSibling) {
                    $node->insertBefore($closing_comment, $list_item->nextSibling);
                } else {
                    $node->appendChild($closing_comment);
                }
            }
            $blocks[] = duola_markdown_block('list', $document->saveHTML($node), 'ol' === $tag ? ['ordered' => true] : []);
            continue;
        }

        if ('blockquote' === $tag) {
            duola_markdown_add_class($node, 'wp-block-quote');
            foreach (iterator_to_array($node->childNodes) as $quote_child) {
                if (!($quote_child instanceof DOMElement) || 'p' !== strtolower($quote_child->tagName)) {
                    continue;
                }
                $node->insertBefore($document->createComment(' wp:paragraph '), $quote_child);
                $closing_comment = $document->createComment(' /wp:paragraph ');
                if ($quote_child->nextSibling) {
                    $node->insertBefore($closing_comment, $quote_child->nextSibling);
                } else {
                    $node->appendChild($closing_comment);
                }
            }
            $blocks[] = duola_markdown_block('quote', $document->saveHTML($node));
            continue;
        }

        if ('pre' === $tag) {
            duola_markdown_add_class($node, 'wp-block-code');
            $blocks[] = duola_markdown_block('code', $document->saveHTML($node));
            continue;
        }

        if ('hr' === $tag) {
            duola_markdown_add_class($node, 'wp-block-separator');
            duola_markdown_add_class($node, 'has-alpha-channel-opacity');
            $blocks[] = duola_markdown_block('separator', $document->saveHTML($node));
            continue;
        }

        if ('table' === $tag) {
            $blocks[] = duola_markdown_block('table', '<figure class="wp-block-table">' . $document->saveHTML($node) . '</figure>');
            continue;
        }

        $blocks[] = duola_markdown_block('freeform', $document->saveHTML($node));
    }

    return implode("\n\n", $blocks);
}

function duola_markdown_prepare_post(string $markdown, string $filename): array
{
    $markdown = preg_replace('/^\xEF\xBB\xBF/', '', str_replace(["\r\n", "\r"], "\n", $markdown));
    $lines = explode("\n", $markdown);
    $title = '';

    foreach ($lines as $index => $line) {
        if ('' === trim($line)) {
            continue;
        }
        if (preg_match('/^\s{0,3}#\s+(.+?)\s*#*\s*$/u', $line, $match)) {
            $title = wp_strip_all_tags(duola_markdown_parser()->line($match[1]));
            unset($lines[$index]);
        }
        break;
    }

    if ('' === trim($title)) {
        $title = pathinfo(wp_basename($filename), PATHINFO_FILENAME);
        $title = preg_replace('/[-_]+/u', ' ', $title);
    }
    $title = sanitize_text_field(trim($title)) ?: __('未命名文章', 'duola-albums');
    $body = ltrim(implode("\n", $lines));
    $html = wp_kses_post(duola_markdown_parser()->text($body));

    return [
        'title' => $title,
        'content' => duola_markdown_html_to_blocks($html),
    ];
}

function duola_markdown_import(): void
{
    if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('你没有导入文章的权限。', 'duola-albums'));
    }
    check_admin_referer('duola_import_markdown');

    $file = $_FILES['duola_markdown'] ?? null;
    if (!is_array($file) || UPLOAD_ERR_OK !== (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)) {
        duola_markdown_fail(__('文件上传失败，请重新选择后再试。', 'duola-albums'));
    }

    $filename = sanitize_text_field(wp_unslash((string) ($file['name'] ?? '')));
    $temporary_file = (string) ($file['tmp_name'] ?? '');
    $size = (int) ($file['size'] ?? 0);
    $extension = strtolower(pathinfo(wp_basename($filename), PATHINFO_EXTENSION));
    if (!in_array($extension, ['md', 'markdown'], true)) {
        duola_markdown_fail(__('请选择 .md 或 .markdown 文件。', 'duola-albums'));
    }
    if ($size <= 0 || $size > DUOLA_MARKDOWN_MAX_BYTES) {
        duola_markdown_fail(__('Markdown 文件必须小于 2 MB 且不能为空。', 'duola-albums'));
    }
    if (!$temporary_file || !is_uploaded_file($temporary_file) || !is_readable($temporary_file)) {
        duola_markdown_fail(__('无法读取上传的 Markdown 文件。', 'duola-albums'));
    }

    $markdown = file_get_contents($temporary_file);
    if (false === $markdown || str_contains($markdown, "\0")) {
        duola_markdown_fail(__('文件内容无效，请确认它是纯文本 Markdown。', 'duola-albums'));
    }
    if (function_exists('mb_check_encoding') && !mb_check_encoding($markdown, 'UTF-8')) {
        duola_markdown_fail(__('请将 Markdown 文件保存为 UTF-8 编码。', 'duola-albums'));
    }
    if ('' === trim(preg_replace('/^\xEF\xBB\xBF/', '', $markdown))) {
        duola_markdown_fail(__('Markdown 文件不能为空。', 'duola-albums'));
    }

    $prepared = duola_markdown_prepare_post($markdown, $filename);
    $post_id = wp_insert_post(wp_slash([
        'post_type' => 'post',
        'post_status' => 'draft',
        'post_title' => $prepared['title'],
        'post_content' => $prepared['content'],
    ]), true);
    if (is_wp_error($post_id)) {
        duola_markdown_fail(__('文章草稿创建失败，请稍后重试。', 'duola-albums'));
    }

    wp_safe_redirect(add_query_arg([
        'post' => (int) $post_id,
        'action' => 'edit',
        'duola_markdown_imported' => 1,
    ], admin_url('post.php')));
    exit;
}
add_action('admin_post_duola_import_markdown', 'duola_markdown_import');

function duola_markdown_imported_notice(): void
{
    if (!isset($_GET['duola_markdown_imported']) || !current_user_can('edit_posts')) {
        return;
    }
    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Markdown 已导入为草稿，可以继续编辑、添加标签和封面。', 'duola-albums') . '</p></div>';
}
add_action('admin_notices', 'duola_markdown_imported_notice');
