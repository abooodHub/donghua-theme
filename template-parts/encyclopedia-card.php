<?php
/**
 * Template part for displaying an Encyclopedia entry card
 * OKanime Modern Knowledge Card Component
 */
declare(strict_types=1);

$entry_id = get_the_ID();
$ar_name  = get_post_meta($entry_id, '_encyclopedia_ar_name', true) ?: get_the_title($entry_id);
$en_name  = get_post_meta($entry_id, '_encyclopedia_en_name', true);
$zh_name  = get_post_meta($entry_id, '_encyclopedia_zh_name', true);
$pinyin   = get_post_meta($entry_id, '_encyclopedia_pinyin', true);
$excerpt  = get_the_excerpt($entry_id) ?: wp_trim_words(get_post_field('post_content', $entry_id), 16);
$views    = (int) get_post_meta($entry_id, '_encyclopedia_views', true);

// Type Taxonomy
$types     = get_the_terms($entry_id, 'encyclopedia_type');
$type_name = (!empty($types) && !is_wp_error($types)) ? $types[0]->name : 'مصطلح';
$type_slug = (!empty($types) && !is_wp_error($types)) ? $types[0]->slug : 'concept';

// Related Donghua
$dh_ids = get_post_meta($entry_id, '_related_donghua_ids', true);
$primary_dh = null;
if (!empty($dh_ids) && is_array($dh_ids)) {
    $first_dh_id = (int) $dh_ids[0];
    $dh_post = get_post($first_dh_id);
    if ($dh_post) {
        $primary_dh = [
            'id'    => $dh_post->ID,
            'title' => get_the_title($dh_post->ID),
            'url'   => get_permalink($dh_post->ID),
        ];
    }
}

// Cultivation Realm Level
$is_realm = (bool) get_post_meta($entry_id, '_is_cultivation_realm', true);
$realm_level = get_post_meta($entry_id, '_realm_level_number', true);
?>

<article class="encyclopedia-card enc-type-<?php echo esc_attr($type_slug); ?>">
    <a href="<?php echo esc_url(get_permalink($entry_id)); ?>" class="enc-card-link-wrap" aria-label="<?php echo esc_attr($ar_name); ?>">
        
        <!-- Card Poster Wrap -->
        <div class="enc-card-thumb">
            <?php if (has_post_thumbnail($entry_id)): ?>
                <?php echo get_the_post_thumbnail($entry_id, 'medium', ['class' => 'enc-thumb-img', 'alt' => esc_attr($ar_name), 'loading' => 'lazy']); ?>
            <?php else: ?>
                <div class="enc-thumb-placeholder enc-placeholder-<?php echo esc_attr($type_slug); ?>">
                    <span class="placeholder-icon"><?php echo donghua_icon('book', '', 28); ?></span>
                </div>
            <?php endif; ?>
            
            <div class="enc-card-overlay"></div>

            <!-- Top Type Badge -->
            <span class="enc-badge-type enc-badge-<?php echo esc_attr($type_slug); ?>">
                <?php echo esc_html($type_name); ?>
            </span>

            <?php if ($is_realm && $realm_level): ?>
                <span class="enc-badge-level">
                    المستوى <?php echo esc_html($realm_level); ?>
                </span>
            <?php endif; ?>
        </div>

        <!-- Card Content Body -->
        <div class="enc-card-body">
            <h3 class="enc-card-title" dir="auto">
                <?php echo esc_html($ar_name); ?>
            </h3>

            <!-- Secondary Subtitles (EN / ZH) -->
            <div class="enc-card-subtitles">
                <?php if ($en_name): ?>
                    <span class="enc-sub-en"><bdi><?php echo esc_html($en_name); ?></bdi></span>
                <?php endif; ?>
                <?php if ($zh_name): ?>
                    <span class="enc-sub-sep">·</span>
                    <span class="enc-sub-zh"><bdi><?php echo esc_html($zh_name); ?></bdi></span>
                <?php endif; ?>
            </div>

            <!-- Excerpt -->
            <p class="enc-card-excerpt">
                <?php echo esc_html($excerpt); ?>
            </p>

            <!-- Card Bottom Bar -->
            <div class="enc-card-footer">
                <?php if ($primary_dh): ?>
                    <span class="enc-card-dh-tag" title="<?php echo esc_attr($primary_dh['title']); ?>">
                        <span class="dh-icon"><?php echo donghua_icon('play', '', 10); ?></span>
                        <span class="dh-name"><?php echo esc_html($primary_dh['title']); ?></span>
                    </span>
                <?php else: ?>
                    <span class="enc-card-general-tag">مفهوم عام</span>
                <?php endif; ?>

                <?php if ($views > 0): ?>
                    <span class="enc-card-views">
                        <span class="view-icon"><?php echo donghua_icon('eye', '', 12); ?></span> <?php echo esc_html(number_format($views)); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

    </a>
</article>
