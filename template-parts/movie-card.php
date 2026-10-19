<?php
declare(strict_types=1);
$movie_id = get_the_ID();
$title = (string) (get_post_meta($movie_id, '_movie_ar_name', true) ?: get_the_title());
$english = (string) get_post_meta($movie_id, '_movie_en_name', true);
$poster_id = get_post_thumbnail_id($movie_id);
$duration = (int) get_post_meta($movie_id, '_movie_duration', true);
$years = get_the_terms($movie_id, 'year');
$year = !empty($years) && !is_wp_error($years) ? $years[0]->name : '';
?>
<article <?php post_class('movie-card'); ?>>
    <a class="movie-card-poster" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr('عرض ' . $title); ?>">
        <?php if ($poster_id): ?><?php echo wp_get_attachment_image($poster_id, 'donghua-poster', false, ['loading' => 'lazy', 'decoding' => 'async']); ?><?php else: ?><span class="movie-card-placeholder" aria-hidden="true">▶</span><?php endif; ?>
        <span class="movie-card-type">فيلم</span><span class="movie-card-play" aria-hidden="true">▶</span>
    </a>
    <div class="movie-card-copy"><h2><a href="<?php the_permalink(); ?>"><?php echo esc_html($title); ?></a></h2>
        <?php if ($english): ?><p class="movie-card-en" lang="en" dir="ltr"><?php echo esc_html($english); ?></p><?php endif; ?>
        <div class="movie-card-meta"><?php if ($year): ?><span><?php echo esc_html($year); ?></span><?php endif; ?><?php if ($duration): ?><span><?php echo esc_html((string) $duration); ?> دقيقة</span><?php endif; ?></div>
    </div>
</article>
