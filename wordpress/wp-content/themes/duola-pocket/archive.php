<?php
get_header();
$albums = get_posts(['post_type' => 'album', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']);
$posts = get_posts(['post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC']);
$years = [];
foreach (array_merge($albums, $posts) as $item) { $years[get_the_date('Y', $item)] = true; }
?>
<section class="page-intro"><span class="eyebrow">时间线</span><h1>归档</h1></section>
<?php foreach (array_keys($years) as $year) : ?>
<section class="section year-section">
    <h2 class="year-title"><?php echo esc_html($year); ?></h2>
    <div class="section-heading"><h3>相册</h3></div>
    <div class="album-grid">
        <?php foreach ($albums as $album) : if (get_the_date('Y', $album) !== $year) { continue; } setup_postdata($album); get_template_part('template-parts/album', 'card'); endforeach; wp_reset_postdata(); ?>
    </div>
    <div class="section-heading" style="margin-top: 2.5rem;"><h3>文章</h3></div>
    <div class="post-list">
        <?php foreach ($posts as $post) : if (get_the_date('Y', $post) !== $year) { continue; } setup_postdata($post); get_template_part('template-parts/post', 'row'); endforeach; wp_reset_postdata(); ?>
    </div>
</section>
<?php endforeach; ?>
<?php if (!$years) : ?><p class="empty-state">这里还没有可以归档的内容。</p><?php endif; ?>
<?php get_footer(); ?>
