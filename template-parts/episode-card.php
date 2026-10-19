<?php
/**
 * Template part for displaying an Episode Card
 * Styled with OKanime's prominent episode number ribbon & modern dark card surface
 */
declare(strict_types=1);

$episode_id = get_the_ID();
$ep_number  = get_post_meta($episode_id, '_episode_number', true) ?: '1';
$parent_id  = (int) get_post_meta($episode_id, '_episode_parent_donghua', true);

$parent_title = $parent_id ? (get_post_meta($parent_id, '_donghua_ar_name', true) ?: get_the_title($parent_id)) : get_the_title();
$parent_url   = $parent_id ? get_permalink($parent_id) : get_permalink();
$poster_url   = $parent_id ? get_the_post_thumbnail_url($parent_id, 'donghua-thumb') : '';
$thumb_url    = get_the_post_thumbnail_url($episode_id, 'episode-thumb') ?: $poster_url;
$translator   = get_post_meta($episode_id, '_episode_translator', true) ?: 'فريق الموقع';
?>

<article id="episode-<?php echo esc_attr((string)$episode_id); ?>" <?php post_class('episode-card okanime-ep-card'); ?>>
    <a href="<?php the_permalink(); ?>" class="episode-media-link" title="<?php echo esc_attr(sprintf('%s - الحلقة %s', $parent_title, $ep_number)); ?>">
        <div class="episode-thumb-wrap">
            <?php if ($thumb_url): ?>
                <img src="<?php echo esc_url($thumb_url); ?>" alt="<?php echo esc_attr($parent_title); ?>" class="episode-thumb-img" loading="lazy" />
            <?php else: ?>
                <div class="episode-placeholder">
                    <span class="placeholder-play-icon">&#9658;</span>
                </div>
            <?php endif; ?>

            <!-- Prominent Episode Number Badge (OKanime Signature) -->
            <div class="episode-num-badge">
                <span class="halka-label">الحلقة</span>
                <span class="halka-val"><?php echo esc_html(sprintf('%02d', (int)$ep_number)); ?></span>
            </div>

            <!-- Hover Play Overlay -->
            <div class="episode-hover-overlay">
                <span class="episode-play-circle"><?php echo donghua_icon('play', '', 16); ?></span>
            </div>
        </div>
    </a>

    <div class="episode-info">
        <h4 class="episode-parent-title">
            <a href="<?php the_permalink(); ?>" title="<?php echo esc_attr($parent_title); ?>">
                <?php echo esc_html(wp_trim_words($parent_title, 6, '...')); ?>
            </a>
        </h4>
        <div class="episode-meta-row">
            <span class="episode-date">
                <span class="meta-icon"><?php echo donghua_icon('clock', '', 12); ?></span>
                <?php echo esc_html(human_time_diff(get_the_time('U'), current_time('timestamp')) . ' مضت'); ?>
            </span>
            <?php if ($translator): ?>
                <span class="episode-translator" title="المترجم / الرافع">
                    <span class="meta-icon"><?php echo donghua_icon('user', '', 12); ?></span> <?php echo esc_html(wp_trim_words($translator, 2, '')); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>
</article>

