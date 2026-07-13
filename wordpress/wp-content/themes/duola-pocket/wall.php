<?php
$messages = function_exists('duola_guestbook_public_messages') ? duola_guestbook_public_messages(50) : [];
$render_message = static function (array $message, bool $is_reply = false) use (&$render_message): void {
    ?>
    <article class="wall-message<?php echo $message['pinned'] ? ' is-pinned' : ''; ?><?php echo $is_reply ? ' is-reply' : ''; ?>" data-wall-message="<?php echo esc_attr($message['id']); ?>">
        <header>
            <span>[<?php echo esc_html($message['number']); ?>]</span>
            <time datetime="<?php echo esc_attr(str_replace(' ', 'T', $message['date'])); ?>"><?php echo esc_html($message['date']); ?></time>
            <strong><?php echo esc_html($message['nickname']); ?></strong>
            <?php if ($message['pinned']) : ?><i>PINNED</i><?php endif; ?>
            <?php if (!$is_reply) : ?>
                <button type="button" data-wall-like aria-label="<?php esc_attr_e('给这条留言 +1', 'duola-pocket'); ?>">+1 <span><?php echo esc_html($message['likes']); ?></span></button>
            <?php endif; ?>
        </header>
        <pre><?php echo esc_html($message['message']); ?></pre>
        <?php if (!$is_reply && $message['replies']) : ?>
            <div class="wall-replies">
                <?php foreach ($message['replies'] as $reply) $render_message($reply, true); ?>
            </div>
        <?php endif; ?>
    </article>
    <?php
};
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class('wall-terminal-page'); ?>>
<?php wp_body_open(); ?>
<main class="wall-page">
    <header class="wall-page-header">
        <span>WALL DDW(1)</span>
        <span><?php bloginfo('name'); ?></span>
        <span>WALL DDW(1)</span>
    </header>

    <section class="wall-compose" aria-labelledby="wall-compose-title">
        <h1 id="wall-compose-title">LEAVE A MESSAGE</h1>
        <form data-wall-form>
            <label><span>nickname:</span><input name="nickname" type="text" maxlength="32" autocomplete="nickname" placeholder="anonymous"></label>
            <label><span>message:</span><textarea name="message" maxlength="300" rows="4" required></textarea></label>
            <label class="wall-honeypot" aria-hidden="true"><span>website:</span><input name="website" type="text" tabindex="-1" autocomplete="off"></label>
            <input name="started_at" type="hidden" value="<?php echo esc_attr(time()); ?>">
            <div class="wall-compose-actions">
                <button type="submit">[ POST MESSAGE ]</button>
                <span><b data-wall-count>0</b>/300</span>
                <output data-wall-status aria-live="polite"></output>
            </div>
        </form>
    </section>

    <section class="wall-log" aria-labelledby="wall-log-title">
        <h2 id="wall-log-title">MESSAGES</h2>
        <div data-wall-messages>
            <?php if (!$messages) : ?><p class="wall-empty" data-wall-empty>no messages yet.</p><?php endif; ?>
            <?php foreach ($messages as $message) $render_message($message); ?>
        </div>
    </section>
</main>
<a class="wall-quit" href="<?php echo esc_url(home_url('/')); ?>">[ q ] quit</a>
<?php wp_footer(); ?>
</body>
</html>
