<?php
get_header();

$groups = [];
$themes = get_terms(['taxonomy' => 'album_theme', 'hide_empty' => true, 'orderby' => 'name', 'order' => 'ASC']);
if (!is_wp_error($themes)) {
    foreach ($themes as $theme) {
        $query = new WP_Query([
            'post_type' => 'album',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'tax_query' => [[
                'taxonomy' => 'album_theme',
                'field' => 'term_id',
                'terms' => [$theme->term_id],
            ]],
        ]);
        if ($query->have_posts()) {
            $groups[] = ['name' => $theme->name, 'description' => $theme->description, 'query' => $query];
        }
    }
}

$unclassified = new WP_Query([
    'post_type' => 'album',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    'tax_query' => [[
        'taxonomy' => 'album_theme',
        'operator' => 'NOT EXISTS',
    ]],
]);
if ($unclassified->have_posts()) {
    $groups[] = ['name' => __('未分类', 'duola-pocket'), 'description' => '', 'query' => $unclassified];
}
?>
<section class="page-intro">
    <span class="eyebrow">Pocket memories</span>
    <h1>那些曾经</h1>
    <p>illusion</p>
</section>

<?php foreach ($groups as $index => $group) : ?>
    <section class="section theme-section">
        <div class="theme-section-heading">
            <span><?php echo esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)); ?></span>
            <div>
                <h2 class="year-title"><?php echo esc_html($group['name']); ?></h2>
                <?php if ($group['description']) : ?><p><?php echo esc_html($group['description']); ?></p><?php endif; ?>
            </div>
        </div>
        <div class="album-grid album-grid-archive">
            <?php while ($group['query']->have_posts()) : $group['query']->the_post(); ?>
                <?php get_template_part('template-parts/album', 'card'); ?>
            <?php endwhile; wp_reset_postdata(); ?>
        </div>
    </section>
<?php endforeach; ?>
<?php if (!$groups) : ?><p class="empty-state">第一本主题相册正在准备中。</p><?php endif; ?>
<?php get_footer(); ?>
