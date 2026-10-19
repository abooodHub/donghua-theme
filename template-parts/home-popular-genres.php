<?php
/** Homepage discovery links for the selected existing genre terms. */
declare(strict_types=1);

$terms = isset($args['terms']) && is_array($args['terms']) ? array_slice($args['terms'], 0, 15) : [];
$items = [];
foreach ($terms as $term) {
    if (!$term instanceof WP_Term || $term->taxonomy !== 'genre') {
        continue;
    }
    $url = get_term_link($term);
    if (is_wp_error($url)) {
        continue;
    }
    $items[] = ['term' => $term, 'url' => $url, 'image_id' => donghua_theme_genre_image_id($term)];
}

if (!$items) {
    return;
}

$item_count = count($items);
if ($item_count === 1) {
    $only_term = $items[0]['term'];
    $is_generic_donghua = $only_term->slug === 'donghua'
        || trim($only_term->name) === 'دونغهوا';
    if ($is_generic_donghua) {
        return;
    }
}

$genre_classes = ['home-popular-genres'];
if ($item_count < 4) {
    $genre_classes[] = 'home-popular-genres--compact';
}
if ($item_count === 1) {
    $genre_classes[] = 'home-popular-genres--single';
}
?>
<section class="<?php echo esc_attr(implode(' ', $genre_classes)); ?>" aria-labelledby="home-popular-genres-title">
    <div class="home-popular-genres-heading">
        <span class="header-accent-dot" aria-hidden="true"></span>
        <h2 id="home-popular-genres-title" class="portal-section-title"><?php echo esc_html__('التصنيفات الشائعة', 'donghua-theme'); ?></h2>
    </div>
    <div class="home-popular-genres-grid">
        <?php foreach ($items as $item): ?>
            <a class="home-popular-genre-tile" href="<?php echo esc_url($item['url']); ?>">
                <?php if ($item['image_id']): ?>
                    <?php echo wp_get_attachment_image((int) $item['image_id'], 'donghua-thumb', false, ['class' => 'home-popular-genre-art', 'alt' => '', 'loading' => 'lazy', 'decoding' => 'async']); ?>
                <?php else: ?>
                    <span class="home-popular-genre-art home-popular-genre-art-placeholder" aria-hidden="true"></span>
                <?php endif; ?>
                <span class="home-popular-genre-label"><?php echo esc_html($item['term']->name); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
