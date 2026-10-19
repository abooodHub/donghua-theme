<?php
/**
 * The template for displaying the Streaming Homepage (Front Page)
 * OK.DONGHUA Streaming Experience — Full-Width Cinematic Layout
 */
declare(strict_types=1);

$home_continue_items = [];
$home_continue_enabled = donghua_theme_home_section_enabled('continue_watching');
if (is_user_logged_in() && $home_continue_enabled) {
    if (!defined('DONOTCACHEPAGE')) define('DONOTCACHEPAGE', true);
    nocache_headers();
    $home_continue_items = donghua_theme_home_continue_watching_items();
}

get_header();
?>
<h1 class="screen-reader-text">OK.DONGHUA — مشاهدة ومتابعة أعمال الدونغهوا الصينية</h1>
<?php

$hero_count            = donghua_theme_home_section_count('hero') ?: 6;
$latest_episodes_count = donghua_theme_home_section_count('latest_episodes');
$popular_count         = donghua_theme_home_section_count('most_watched');
$recent_count          = donghua_theme_home_section_count('recent');
$ongoing_count         = donghua_theme_home_section_count('ongoing') ?: 7;
$completed_count       = donghua_theme_home_section_count('completed') ?: 7;
$news_count            = donghua_theme_home_section_count('news') ?: 4;

// 1. Featured / Hero Slider Query (Top scored, fallback to latest)
$hero_query = donghua_cached_query('dh_cache_hero_v3_' . $hero_count, [
    'post_type'      => 'donghua',
    'posts_per_page' => $hero_count,
    'meta_key'       => '_donghua_score',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
]);

if ($hero_query->post_count < 3) {
    $hero_query = new WP_Query([
        'post_type'      => 'donghua',
        'posts_per_page' => $hero_count,
        'post_status'    => 'publish',
        'has_password'   => false,
        'no_found_rows'  => true,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
}

// 2. Latest Episodes Query (Verified with valid parent Donghua)
$latest_episodes_query = donghua_cached_query('dh_cache_latest_episodes_v3_' . $latest_episodes_count, [
    'post_type'      => 'episode',
    'posts_per_page' => $latest_episodes_count,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [
        [
            'key'     => '_episode_parent_donghua',
            'value'   => 0,
            'compare' => '>',
            'type'    => 'NUMERIC',
        ],
    ],
]);

// 3. Popular / Trending Query (Ordered by views, fallback to score)
$popular_sort_meta_key = '_donghua_views';
$popular_query = donghua_cached_query('dh_cache_popular_v3_' . $popular_count, [
    'post_type'      => 'donghua',
    'posts_per_page' => $popular_count,
    'post_status'    => 'publish',
    'meta_key'       => '_donghua_views',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
]);

if ($popular_query->post_count < 4) {
    $popular_sort_meta_key = '_donghua_score';
    $popular_query = donghua_cached_query('dh_cache_top_rated_v3_' . $popular_count, [
        'post_type'      => 'donghua',
        'posts_per_page' => $popular_count,
        'post_status'    => 'publish',
        'meta_key'       => '_donghua_score',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
    ]);
}

// 4. Recently Added Donghua Query (Ordered by date DESC)
$recent_query = donghua_cached_query('dh_cache_recent_v3_' . $recent_count, [
    'post_type'      => 'donghua',
    'posts_per_page' => $recent_count,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

// 5. Ongoing Donghua Query (مستمرة)
$ongoing_query = donghua_cached_query('dh_cache_ongoing_v3_' . $ongoing_count, [
    'post_type'      => 'donghua',
    'posts_per_page' => $ongoing_count,
    'post_status'    => 'publish',
    'tax_query'      => [
        [
            'taxonomy' => 'status',
            'field'    => 'slug',
            'terms'    => 'ongoing',
        ],
    ],
]);

// 6. Completed Donghua Query (مكتملة)
$completed_query = donghua_cached_query('dh_cache_completed_v3_' . $completed_count, [
    'post_type'      => 'donghua',
    'posts_per_page' => $completed_count,
    'post_status'    => 'publish',
    'tax_query'      => [
        [
            'taxonomy' => 'status',
            'field'    => 'slug',
            'terms'    => 'completed',
        ],
    ],
]);

// 7. Latest News Query (Optional)
$news_query = donghua_cached_query('dh_cache_news_v3_' . $news_count, [
    'post_type'      => 'news',
    'posts_per_page' => $news_count,
    'post_status'    => 'publish',
]);
// Prime only rendered work/parent data. Cards consume a single public episode
// summary map; personalized continue-watching remains owned by its user service.
$home_work_ids = [];
foreach ([$hero_query, $popular_query, $recent_query, $ongoing_query, $completed_query] as $home_query) {
    foreach ($home_query->posts as $home_post) $home_work_ids[] = (int)$home_post->ID;
}
foreach ($latest_episodes_query->posts as $home_episode) {
    $home_work_ids[] = (int)get_post_meta($home_episode->ID, '_episode_parent_donghua', true);
}
$home_work_ids = array_values(array_unique(array_filter($home_work_ids)));
if ($home_work_ids) {
    update_meta_cache('post', $home_work_ids);
    update_object_term_cache($home_work_ids, 'donghua');
}
$home_card_ids = [];
foreach ([$popular_query, $recent_query, $ongoing_query, $completed_query] as $home_query) {
    foreach ($home_query->posts as $home_post) $home_card_ids[] = (int)$home_post->ID;
}
$home_episode_summaries = \DonghuaCore\Meta\EpisodeMeta::get_public_card_episodes($home_card_ids);
$home_has_latest = donghua_theme_home_section_enabled('latest_episodes') && (int) $latest_episodes_query->post_count > 0;
?>

<div class="homepage-portal streaming-homepage dh-home-v3">

    <!-- 1. Hero Slider Section (Powered by Swiper 11) — Compact Cinematic Hero -->
    <?php if (donghua_theme_home_section_enabled('hero') && $hero_query->have_posts()): ?>
        <section class="hero-slider-section dh-hero-v3" aria-label="أبرز الأعمال الحصرية">
            <div class="swiper hero-swiper" id="hero-swiper">
                <div class="swiper-wrapper">
                    <?php $slide_idx = 0; ?>
                    <?php while ($hero_query->have_posts()): $hero_query->the_post(); ?>
                        <?php
                        $h_id           = get_the_ID();
                        $banner_url     = donghua_get_work_backdrop_url($h_id) ?: get_the_post_thumbnail_url($h_id, 'donghua-banner');
                        $poster_url     = donghua_get_poster_url($h_id, 'donghua-poster');
                        $has_banner     = !empty($banner_url);
                        $is_fallback    = !$has_banner && !empty($poster_url);
                        $raw_bg         = $has_banner ? $banner_url : $poster_url;
                        $bg_image       = donghua_optimize_image_url($raw_bg);
                        $is_first_slide = ($slide_idx === 0);
                        $slide_idx++;
                        $ar_name        = get_post_meta($h_id, '_donghua_ar_name', true) ?: get_the_title();
                        $en_name        = get_post_meta($h_id, '_donghua_en_name', true);
                        $score          = donghua_get_score($h_id);
                        $synopsis       = get_the_excerpt() ?: wp_trim_words(get_the_content(), 24);
                        
                        $status_terms = donghua_cached_term_names($h_id, 'status');
                        $status_name  = (!is_wp_error($status_terms) && !empty($status_terms)) ? $status_terms[0] : 'مستمر';
                        $anim_terms   = donghua_cached_term_names($h_id, 'animation_type');
                        $anim_name    = (!is_wp_error($anim_terms) && !empty($anim_terms)) ? $anim_terms[0] : '3D';
                        ?>
                        <div class="swiper-slide hero-slide<?php echo $is_fallback ? ' hero-poster-fallback' : ''; ?><?php echo empty($bg_image) ? ' hero-no-image' : ''; ?>" <?php if ($bg_image): ?><?php if ($is_first_slide): ?>style="background-image: url('<?php echo esc_url($bg_image); ?>');"<?php else: ?>data-bg="url('<?php echo esc_url($bg_image); ?>')"<?php endif; ?><?php endif; ?>>
                            <div class="hero-slide-overlay" aria-hidden="true"></div>
                            <div class="hero-slide-content">
                                <div class="hero-badges-row">
                                    <?php if ($score && (float)$score > 0): ?>
                                        <span class="badge-gold-score">&#9733; <?php echo esc_html(number_format((float)$score, 1)); ?></span>
                                    <?php endif; ?>
                                    <span class="badge-status status-<?php echo esc_attr(sanitize_title($status_name)); ?>"><?php echo esc_html($status_name); ?></span>
                                    <span class="badge-anim badge-<?php echo esc_attr(sanitize_title($anim_name)); ?>"><?php echo esc_html($anim_name); ?></span>
                                </div>
                                <h2 class="hero-work-title">
                                    <a href="<?php the_permalink(); ?>"><?php echo esc_html($ar_name); ?></a>
                                </h2>
                                <?php if ($en_name): ?>
                                    <p class="hero-work-en"><?php echo esc_html($en_name); ?></p>
                                <?php endif; ?>
                                <p class="hero-work-synopsis"><?php echo esc_html($synopsis); ?></p>
                                <div class="hero-action-buttons">
                                    <a href="<?php the_permalink(); ?>" class="btn-hero-primary btn-primary">
                                        <span class="btn-icon"><?php echo donghua_icon('play', '', 16); ?></span>
                                        <span>مشاهدة الآن</span>
                                    </a>
                                    <a href="<?php the_permalink(); ?>" class="btn-hero-secondary btn-secondary">
                                        <span class="btn-icon"><?php echo donghua_icon('info', '', 15); ?></span>
                                        <span>التفاصيل</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>

                <!-- Swiper Navigation Controls -->
                <div class="swiper-button-prev" aria-label="السابق"></div>
                <div class="swiper-button-next" aria-label="التالي"></div>
                <div class="swiper-pagination"></div>
            </div>

            <!-- Autoplay Circular Progress & Play/Pause Button -->
            <button type="button" 
                    class="hero-autoplay-control-btn" 
                    id="hero-autoplay-btn" 
                    aria-controls="hero-swiper"
                    aria-label="إيقاف التشغيل التلقائي مؤقتاً" 
                    title="إيقاف / تشغيل مؤقتاً">
                <svg class="autoplay-ring-svg" viewBox="0 0 44 44" width="44" height="44" aria-hidden="true">
                    <circle class="ring-bg" cx="22" cy="22" r="18" />
                    <circle class="ring-progress" id="hero-progress-ring" cx="22" cy="22" r="18" />
                    <circle class="ring-head" id="hero-progress-head" cx="40" cy="22" r="2.5" />
                </svg>
                <span class="autoplay-icon" id="hero-autoplay-icon" aria-hidden="true">
                    <span class="icon-pause"><svg viewBox="0 0 24 24" width="20" height="20" focusable="false"><rect x="6" y="5" width="4" height="14" rx="1.5" /><rect x="14" y="5" width="4" height="14" rx="1.5" /></svg></span>
                    <span class="icon-play"><svg viewBox="0 0 24 24" width="20" height="20" focusable="false"><path d="M8 5.5a1 1 0 0 1 1.5-.86l10 6.5a1 1 0 0 1 0 1.72l-10 6.5A1 1 0 0 1 8 18.5z" /></svg></span>
                </span>
            </button>
        </section>
    <?php endif; ?>

    <nav class="home-quick-nav" aria-label="استكشف الموقع">
        <a href="#latest-episodes" class="is-current"><?php echo donghua_icon('clock', '', 13); ?><span>أحدث الحلقات</span></a>
        <a href="#recent-works"><?php echo donghua_icon('sparkles', '', 13); ?><span>أحدث الأعمال</span></a>
        <a href="#ongoing-works"><?php echo donghua_icon('play', '', 13); ?><span>الأعمال المستمرة</span></a>
        <a href="#completed-works"><?php echo donghua_icon('check', '', 13); ?><span>الأعمال المكتملة</span></a>
    </nav>

    <?php if (function_exists('dh_render_ad_slot')) echo dh_render_ad_slot('home_after_hero'); ?>

    <?php if ($home_continue_enabled): ?>
        <?php get_template_part('template-parts/home-continue-watching', null, ['items' => $home_continue_items, 'standalone' => true]); ?>
    <?php endif; ?>

    <!-- 2. Latest Episodes Section (Dense 2-Column Informative Rows) -->
    <?php if ($home_has_latest): ?>
        <div class="home-feature-layout">
            <div class="home-feature-main">
        <section class="portal-section section-latest-episodes" id="latest-episodes" aria-labelledby="latest-episodes-title">
            <div class="portal-section-header">
                <div class="header-title-box">
                    <span class="header-accent-dot" aria-hidden="true"></span>
                    <h2 class="portal-section-title" id="latest-episodes-title">أحدث الحلقات</h2>
                </div>
            </div>

            <div class="episodes-dense-grid" id="latest-episode-list">
                <?php while ($latest_episodes_query->have_posts()): $latest_episodes_query->the_post(); ?>
                    <?php
                    $ep_id        = get_the_ID();
                    $ep_number    = get_post_meta($ep_id, '_episode_number', true) ?: '1';
                    $parent_id    = (int) get_post_meta($ep_id, '_episode_parent_donghua', true);
                    $parent_title = $parent_id ? (get_post_meta($parent_id, '_donghua_ar_name', true) ?: get_the_title($parent_id)) : get_the_title();
                    $anim_terms   = $parent_id ? donghua_cached_term_names($parent_id, 'animation_type') : [];
                    $anim_type    = (!is_wp_error($anim_terms) && !empty($anim_terms)) ? $anim_terms[0] : '3D';
                    $thumb_url    = get_the_post_thumbnail_url($ep_id, 'thumbnail')
                                    ?: ((string) get_post_meta($ep_id, '_episode_thumbnail', true)
                                    ?: (string) get_post_meta($ep_id, '_episode_thumbnail_url', true));
                    $thumb_url    = $thumb_url ?: ($parent_id ? donghua_get_poster_url($parent_id, 'donghua-thumb') : '');
                    $trans_type   = (string) get_post_meta($ep_id, '_episode_translation_type', true);
                    $ep_disp_num  = is_numeric($ep_number) && (float)$ep_number === (float)(int)$ep_number ? sprintf('%02d', (int)$ep_number) : (string)$ep_number;
                    ?>
                    <a href="<?php the_permalink(); ?>" class="episode-dense-row" aria-label="<?php echo esc_attr(sprintf('%s - الحلقة %s', $parent_title, $ep_number)); ?>">
                        <div class="ep-row-thumb" aria-hidden="true">
                            <?php if ($thumb_url): ?>
                                <img src="<?php echo esc_url($thumb_url); ?>" width="54" height="68" alt="" loading="lazy" decoding="async" />
                            <?php else: ?>
                                <div class="ep-row-placeholder"><?php echo donghua_icon('film', '', 20); ?></div>
                            <?php endif; ?>
                            <span class="ep-row-play-hint"><?php echo donghua_icon('play', '', 14); ?></span>
                        </div>

                        <div class="ep-row-content">
                            <div class="ep-row-top">
                                <h3 class="ep-row-title"><?php echo esc_html($parent_title); ?></h3>
                                <span class="ep-row-badge">الحلقة <?php echo esc_html($ep_disp_num); ?></span>
                            </div>
                            <div class="ep-row-bottom">
                                <span class="ep-row-meta-time">
                                    <?php echo donghua_icon('clock', '', 12); ?>
                                    <?php echo esc_html(human_time_diff(get_the_time('U'), current_time('timestamp')) . ' مضت'); ?>
                                </span>
                                <?php if (!empty($trans_type)): ?>
                                    <span class="ep-row-meta-sub"><?php echo esc_html($trans_type); ?></span>
                                <?php endif; ?>
                                <span class="ep-row-meta-anim"><?php echo esc_html($anim_type); ?></span>
                            </div>
                        </div>

                        <div class="ep-row-action" aria-hidden="true">
                            <span class="ep-row-arrow">&lsaquo;</span>
                        </div>
                    </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
            </div>

            <?php if (donghua_theme_home_section_enabled('most_watched') && !empty($popular_query->posts)): ?>
                <aside class="home-feature-sidebar" aria-labelledby="home-most-watched-title">
                    <section class="home-top-rated-panel">
                        <div class="home-sidebar-heading">
                            <h2 id="home-most-watched-title">الأكثر مشاهدة</h2>
                        </div>
                        <div class="home-sidebar-rank-list home-sidebar-ranking">
                            <?php foreach (array_slice($popular_query->posts, 0, 5) as $rank_index => $popular_post): ?>
                                <?php
                                $popular_id = (int) $popular_post->ID;
                                $popular_title = get_the_title($popular_id);
                                $popular_poster = get_the_post_thumbnail_url($popular_id, 'donghua-thumb') ?: donghua_get_poster_url($popular_id, 'donghua-thumb');
                                $popular_metric = (int) get_post_meta($popular_id, $popular_sort_meta_key, true);
                                $popular_metric_label = $popular_sort_meta_key === '_donghua_views'
                                    ? number_format_i18n($popular_metric) . ' مشاهدة'
                                    : ($popular_metric > 0 ? number_format_i18n($popular_metric, 1) . ' تقييم' : 'عمل رائج');
                                ?>
                                <a class="home-sidebar-rank-link" href="<?php echo esc_url(get_permalink($popular_id)); ?>">
                                    <span class="home-sidebar-rank-number" aria-hidden="true"><?php echo esc_html(sprintf('%02d', $rank_index + 1)); ?></span>
                                    <?php if ($popular_poster): ?>
                                        <img src="<?php echo esc_url($popular_poster); ?>" width="48" height="64" alt="" loading="lazy" decoding="async" />
                                    <?php endif; ?>
                                    <span class="home-sidebar-rank-copy">
                                        <strong><?php echo esc_html($popular_title); ?></strong>
                                        <small><?php echo esc_html($popular_metric_label); ?></small>
                                    </span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <a class="home-sidebar-view-all" href="<?php echo esc_url(get_post_type_archive_link('donghua') . '?sort=views'); ?>">عرض قائمة الأكثر مشاهدة <span aria-hidden="true">‹</span></a>
                    </section>
                </aside>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 4. Recently Added Donghua Section (أحدث الأعمال المضافة) -->
    <?php if (donghua_theme_home_section_enabled('recent') && $recent_query->have_posts()): ?>
        <section class="portal-section section-recent" id="recent-works" aria-label="أحدث الأعمال المضافة">
            <div class="portal-section-header">
                <div class="header-title-box">
                    <span class="header-accent-dot" aria-hidden="true"></span>
                    <h2 class="portal-section-title">أحدث الأعمال المضافة</h2>
                </div>
                <a href="<?php echo esc_url(get_post_type_archive_link('donghua')); ?>" class="section-view-all">
                    <span>عرض الكل</span>
                    <span class="view-all-arrow" aria-hidden="true">&lsaquo;</span>
                </a>
            </div>

            <div class="donghua-grid">
                <?php while ($recent_query->have_posts()): $recent_query->the_post(); ?>
                    <?php get_template_part('template-parts/donghua-card', null, ['episode_summaries' => $home_episode_summaries]); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 5. Ongoing Donghua Section (أعمال دونغهوا مستمرة) -->
    <?php if (donghua_theme_home_section_enabled('ongoing') && $ongoing_query->have_posts()): ?>
        <section class="portal-section section-ongoing" id="ongoing-works" aria-label="أعمال دونغهوا مستمرة">
            <div class="portal-section-header">
                <div class="header-title-box">
                    <span class="header-accent-dot" aria-hidden="true"></span>
                    <h2 class="portal-section-title">أعمال دونغهوا مستمرة</h2>
                </div>
                <a href="<?php echo esc_url(home_url('/status/ongoing/')); ?>" class="section-view-all">
                    <span>عرض الكل</span>
                    <span class="view-all-arrow" aria-hidden="true">&lsaquo;</span>
                </a>
            </div>

            <div class="donghua-grid">
                <?php while ($ongoing_query->have_posts()): $ongoing_query->the_post(); ?>
                    <?php get_template_part('template-parts/donghua-card', null, ['episode_summaries' => $home_episode_summaries]); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 6. Completed Donghua Section (أعمال مكتملة) -->
    <?php if (donghua_theme_home_section_enabled('completed') && $completed_query->have_posts()): ?>
        <section class="portal-section section-completed" id="completed-works" aria-label="أعمال مكتملة جاهزة للمشاهدة">
            <div class="portal-section-header">
                <div class="header-title-box">
                    <span class="header-accent-dot" aria-hidden="true"></span>
                    <h2 class="portal-section-title">أعمال مكتملة جاهزة للمشاهدة</h2>
                </div>
                <a href="<?php echo esc_url(home_url('/status/completed/')); ?>" class="section-view-all">
                    <span>عرض الكل</span>
                    <span class="view-all-arrow" aria-hidden="true">&lsaquo;</span>
                </a>
            </div>

            <div class="donghua-grid">
                <?php while ($completed_query->have_posts()): $completed_query->the_post(); ?>
                    <?php get_template_part('template-parts/donghua-card', null, ['episode_summaries' => $home_episode_summaries]); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- 7. News Section (Optional — Only rendered when posts exist) -->
    <?php if (donghua_theme_home_section_enabled('news') && $news_query->have_posts()): ?>
        <section class="portal-section section-news" aria-label="أحدث أخبار ومقالات الدونغهوا">
            <div class="portal-section-header">
                <div class="header-title-box">
                    <span class="header-accent-dot" aria-hidden="true"></span>
                    <h2 class="portal-section-title">أحدث الأخبار والمقالات</h2>
                </div>
                <a href="<?php echo esc_url(get_post_type_archive_link('news')); ?>" class="section-view-all">
                    <span>جميع الأخبار</span>
                    <span class="view-all-arrow" aria-hidden="true">&lsaquo;</span>
                </a>
            </div>

            <div class="news-list-grid">
                <?php while ($news_query->have_posts()): $news_query->the_post(); ?>
                    <article class="news-portal-item">
                        <a href="<?php the_permalink(); ?>" class="news-thumb-wrap">
                            <?php if (has_post_thumbnail()): ?>
                                <?php the_post_thumbnail('medium', ['class' => 'news-thumb-img', 'alt' => get_the_title()]); ?>
                            <?php else: ?>
                                <div class="news-no-thumb"><?php echo donghua_icon('newspaper', '', 28); ?></div>
                            <?php endif; ?>
                        </a>
                        <div class="news-text-content">
                            <h3 class="news-item-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h3>
                            <p class="news-item-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt(), 18, '...')); ?></p>
                            <span class="news-item-date"><?php echo donghua_icon('calendar', '', 12); ?> <?php echo esc_html(get_the_date('j F Y')); ?></span>
                        </div>
                    </article>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>

</div><!-- .homepage-portal -->

<?php get_footer(); ?>
