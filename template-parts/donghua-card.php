<?php
/**
 * Template part for displaying a Donghua Card
 * Minimal, Clean, High-Contrast & Modern Streaming Card
 */
declare(strict_types=1);

$donghua_id    = get_the_ID();
$score         = donghua_get_score($donghua_id);
$ar_name       = get_post_meta($donghua_id, '_donghua_ar_name', true);
$en_name       = get_post_meta($donghua_id, '_donghua_en_name', true);
$display_title = $ar_name ?: ($en_name ?: get_the_title());
$poster_url    = donghua_get_poster_url($donghua_id, 'donghua-poster');
$poster_id = get_post_thumbnail_id($donghua_id);
$poster_srcset = $poster_id ? wp_get_attachment_image_srcset($poster_id, 'donghua-poster') : false;

$year_terms   = donghua_cached_term_names($donghua_id, 'year');
$status_terms = donghua_cached_term_names($donghua_id, 'status');
$anim_terms   = donghua_cached_term_names($donghua_id, 'animation_type');

$year   = (!is_wp_error($year_terms) && !empty($year_terms)) ? $year_terms[0] : '';
$status = (!is_wp_error($status_terms) && !empty($status_terms)) ? $status_terms[0] : 'غير محدد';
$anim   = (!is_wp_error($anim_terms) && !empty($anim_terms)) ? $anim_terms[0] : '3D';

// Home passes one batch map; other card callers resolve just this public work.
$episode_summaries = isset($args['episode_summaries']) && is_array($args['episode_summaries'])
    ? $args['episode_summaries'] : \DonghuaCore\Meta\EpisodeMeta::get_public_card_episodes([$donghua_id]);
$latest_ep_num = $episode_summaries[$donghua_id]['number'] ?? 0;
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('donghua-card dh-card-v3 modern-card-style'); ?>>
    <a href="<?php the_permalink(); ?>" class="card-media-anchor" title="<?php echo esc_attr($display_title); ?>">
        <div class="card-poster-frame">
            <?php if ($poster_url): ?>
                <img src="<?php echo esc_url($poster_url); ?>" class="card-poster-image" <?php if ($poster_srcset): ?>srcset="<?php echo esc_attr($poster_srcset); ?>" sizes="(max-width:640px) 45vw, (max-width:1024px) 25vw, 220px"<?php endif; ?> alt="<?php echo esc_attr($display_title); ?>" loading="lazy" decoding="async" width="220" height="330" />
            <?php else: ?>
                <div class="card-empty-poster">
                    <span class="empty-icon"><?php echo donghua_icon('film', '', 28); ?></span>
                </div>
            <?php endif; ?>

            <!-- Quiet Rating Badge (Top Left) -->
            <?php if ($score && (float)$score > 0): ?>
                <div class="card-rating-chip" title="التقييم">
                    <span class="rating-star">&#9733;</span>
                    <span class="rating-val"><?php echo esc_html(number_format((float)$score, 1)); ?></span>
                </div>
            <?php endif; ?>

            <!-- Quiet Status Dot (Top Right) -->
            <div class="card-status-dot status-<?php echo esc_attr(sanitize_title($status)); ?>" title="<?php echo esc_attr($status); ?>">
                <span class="dot-indicator"></span>
                <span class="dot-label"><?php echo esc_html($status); ?></span>
            </div>

            <!-- Soft Hover Overlay -->
            <div class="card-hover-scrim" aria-hidden="true">
                <span class="play-indicator">&#9658;</span>
            </div>
        </div>
    </a>

    <div class="card-text-body">
        <h3 class="card-title">
            <a href="<?php the_permalink(); ?>" title="<?php echo esc_attr($display_title); ?>">
                <?php echo esc_html($display_title); ?>
            </a>
        </h3>

        <!-- Subtitle: English or Year -->
        <div class="card-subtitle-row">
            <?php if ($en_name && $en_name !== $display_title): ?>
                <span class="card-sub-en" title="<?php echo esc_attr($en_name); ?>">
                    <?php echo esc_html(wp_trim_words($en_name, 3, '..')); ?>
                </span>
            <?php elseif ($year): ?>
                <span class="card-sub-year"><?php echo esc_html($year); ?></span>
            <?php endif; ?>
        </div>

        <!-- Quiet Metadata Tags -->
        <div class="card-meta-chips">
            <?php if ($latest_ep_num > 0): ?>
                <span class="card-chip chip-ep">حلقة <?php echo esc_html((string)$latest_ep_num); ?></span>
            <?php endif; ?>
            <span class="card-chip chip-anim"><?php echo esc_html($anim); ?></span>
        </div>
    </div>
</article>
