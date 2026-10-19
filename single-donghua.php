<?php
/**
 * The template for displaying a single Donghua post
 * Cinematic Streaming Title Detail Experience
 * Consistent with Header, Homepage & Cinematic Design System
 */
declare(strict_types=1);

get_header();

$donghua_id = get_the_ID();
$resume_episode = is_user_logged_in() && class_exists('DonghuaCore\\Api\\WatchProgress')
    ? DonghuaCore\Api\WatchProgress::latest_for_work(get_current_user_id(), (int)$donghua_id)
    : null;

// Metadata
$ar_name      = get_post_meta($donghua_id, '_donghua_ar_name', true) ?: get_the_title();
$en_name      = get_post_meta($donghua_id, '_donghua_en_name', true);
$zh_name      = get_post_meta($donghua_id, '_donghua_zh_name', true);
$poster_url   = donghua_get_poster_url($donghua_id, 'donghua-poster');
$banner_url   = donghua_get_work_backdrop_url($donghua_id) ?: $poster_url;
$work_accent  = (string) (get_post_meta($donghua_id, '_donghua_accent_color', true) ?: get_post_meta($donghua_id, '_donghua_v3_accent', true));
$work_style   = '';
if (preg_match('/^#[0-9a-fA-F]{6}$/', $work_accent)) {
    $work_hex = ltrim($work_accent, '#');
    $work_rgb = hexdec(substr($work_hex, 0, 2)) . ', ' . hexdec(substr($work_hex, 2, 2)) . ', ' . hexdec(substr($work_hex, 4, 2));
    $work_style = '--work-accent:' . $work_accent . ';--work-accent-rgb:' . $work_rgb . ';';
}
$trailer_url  = get_post_meta($donghua_id, '_donghua_trailer_url', true);
$total_eps    = get_post_meta($donghua_id, '_donghua_total_episodes', true);
$duration     = get_post_meta($donghua_id, '_donghua_duration', true);
$age_rating   = get_post_meta($donghua_id, '_donghua_age_rating', true);
$synopsis     = trim(wp_strip_all_tags(get_the_excerpt() ?: get_the_content()));
$synopsis_preview = wp_trim_words($synopsis, 34, '…');
$synopsis_words = preg_split('/\s+/u', $synopsis, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$synopsis_is_long = count($synopsis_words) > 34;

// Taxonomies
$genres     = get_the_terms($donghua_id, 'genre') ?: [];
$studios    = get_the_terms($donghua_id, 'studio') ?: [];
$years      = get_the_terms($donghua_id, 'year') ?: [];
$statuses   = get_the_terms($donghua_id, 'status') ?: [];
$anim_types = get_the_terms($donghua_id, 'animation_type') ?: [];

$status_name = (!is_wp_error($statuses) && !empty($statuses)) ? $statuses[0]->name : '';
$year_name   = (!is_wp_error($years) && !empty($years)) ? $years[0]->name : '';
$anim_name   = (!is_wp_error($anim_types) && !empty($anim_types)) ? $anim_types[0]->name : '';

// Published episodes for this Donghua
$episodes = donghua_public_episodes($donghua_id);
$first_episode     = !empty($episodes) ? $episodes[0] : null;
$latest_episode    = !empty($episodes) ? end($episodes) : null;
$latest_episode_id = $latest_episode ? $latest_episode->ID : 0;

// Find only explicitly named seasons of this same work; unrelated works are never grouped.
$strip_season_suffix = static function (string $name): string {
    $name = preg_replace('/\s*[-–—:]?\s*الموسم\s*(?:الأول|الثاني|الثالث|الرابع|الخامس|السادس|السابع|الثامن|التاسع|العاشر|\d+)\s*$/u', '', $name) ?? $name;
    $name = preg_replace('/\s*[-–—:]?\s*(?:season\s*\d+|s\d+)\s*$/i', '', $name) ?? $name;
    $name = trim($name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
};
$series_ar_name = $strip_season_suffix((string)$ar_name);
$series_en_name = $strip_season_suffix((string)$en_name);
$has_season_suffix = ($series_ar_name !== (function_exists('mb_strtolower') ? mb_strtolower(trim((string)$ar_name), 'UTF-8') : strtolower(trim((string)$ar_name))))
    || ($series_en_name !== strtolower(trim((string)$en_name)));

$season_candidate_ids = [$donghua_id];
if ($has_season_suffix) {
    $season_meta_query = ['relation' => 'OR'];
    if ($series_ar_name !== '') {
        $season_meta_query[] = ['key' => '_donghua_ar_name', 'value' => $series_ar_name, 'compare' => 'LIKE'];
    }
    if ($series_en_name !== '') {
        $season_meta_query[] = ['key' => '_donghua_en_name', 'value' => $series_en_name, 'compare' => 'LIKE'];
    }
    if (count($season_meta_query) > 1) {
        $season_candidate_ids = array_merge($season_candidate_ids, get_posts([
            'post_type'      => 'donghua',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'post__not_in'   => [$donghua_id],
            'meta_query'     => $season_meta_query,
        ]));
    }
}

$season_number_from_name = static function (string $name): int {
    $ordinals = ['الأول' => 1, 'الثاني' => 2, 'الثالث' => 3, 'الرابع' => 4, 'الخامس' => 5, 'السادس' => 6, 'السابع' => 7, 'الثامن' => 8, 'التاسع' => 9, 'العاشر' => 10];
    if (preg_match('/الموسم\s*(الأول|الثاني|الثالث|الرابع|الخامس|السادس|السابع|الثامن|التاسع|العاشر|\d+)/u', $name, $match)) {
        return isset($ordinals[$match[1]]) ? $ordinals[$match[1]] : (int)$match[1];
    }
    if (preg_match('/(?:season\s*|s)(\d+)/i', $name, $match)) {
        return (int)$match[1];
    }
    return 0;
};

$season_cards = [];
$current_season_label = 'هذا الموسم';
foreach (array_unique(array_map('absint', $season_candidate_ids)) as $season_id) {
    $season_ar_name = (string)(get_post_meta($season_id, '_donghua_ar_name', true) ?: get_the_title($season_id));
    $season_en_name = (string)get_post_meta($season_id, '_donghua_en_name', true);
    $same_ar_series = $series_ar_name !== '' && $strip_season_suffix($season_ar_name) === $series_ar_name;
    $same_en_series = $series_en_name !== '' && $strip_season_suffix($season_en_name) === $series_en_name;
    if ($season_id !== $donghua_id && !$same_ar_series && !$same_en_series) {
        continue;
    }

    $season_number = $season_number_from_name($season_ar_name) ?: $season_number_from_name($season_en_name);
    $season_episode_count = (int)get_post_meta($season_id, '_donghua_total_episodes', true);
    if ($season_episode_count < 1) {
        $season_episode_count = count(donghua_public_episodes($season_id));
    }
    $season_poster = donghua_get_poster_url($season_id, 'donghua-poster');
    if (!$season_poster) {
        $season_poster = get_the_post_thumbnail_url($season_id, 'donghua-poster') ?: get_post_meta($season_id, '_donghua_banner_url', true);
    }

    $season_card = [
        'id'       => $season_id,
        'number'   => $season_number,
        'label'    => $season_number ? sprintf('الموسم %d', $season_number) : $season_ar_name,
        'count'    => $season_episode_count,
        'poster'   => $season_poster,
        'url'      => get_permalink($season_id),
        'selected' => $season_id === $donghua_id,
    ];
    $season_cards[] = $season_card;
    if ($season_card['selected']) {
        $current_season_label = $season_card['label'];
    }
}
usort($season_cards, static function (array $a, array $b) use ($donghua_id): int {
    $a_order = $a['number'] ?: PHP_INT_MAX;
    $b_order = $b['number'] ?: PHP_INT_MAX;
    return ($a_order <=> $b_order) ?: (($a['id'] === $donghua_id ? 1 : 0) <=> ($b['id'] === $donghua_id ? 1 : 0));
});

// Statistics
$donghua_views = class_exists('Donghua_Episode_Stats')
    ? Donghua_Episode_Stats::get_donghua_views($donghua_id)
    : (int) get_post_meta($donghua_id, '_donghua_views', true);

// Recommendations Query (7 cards on 1920px desktop)
$genre_ids = !is_wp_error($genres) ? wp_list_pluck($genres, 'term_id') : [];
$recommendations = new WP_Query([
    'post_type'      => 'donghua',
    'posts_per_page' => 7,
    'post__not_in'   => [$donghua_id],
    'post_status'    => 'publish',
    'tax_query'      => !empty($genre_ids) ? [
        [
            'taxonomy' => 'genre',
            'field'    => 'term_id',
            'terms'    => $genre_ids,
        ],
    ] : [],
]);

if ($recommendations->post_count < 4) {
    $recommendations = new WP_Query([
        'post_type'      => 'donghua',
        'posts_per_page' => 7,
        'post__not_in'   => [$donghua_id],
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);
}
?>

<div class="donghua-single-page" data-work-id="<?php echo esc_attr((string)$donghua_id); ?>"<?php if ($work_style !== ''): ?> style="<?php echo esc_attr($work_style); ?>"<?php endif; ?>>

    <!-- 1. Cinematic Containerized Hero (430-460px Height, 18px Radius) -->
    <div class="site-container single-donghua-hero-container">
        <header class="single-donghua-hero" style="background-image: url('<?php echo esc_url($banner_url ?: $poster_url); ?>');" aria-label="<?php echo esc_attr($ar_name); ?>">
            <div class="single-hero-scrim" aria-hidden="true"></div>

            <div class="single-hero-content-wrap">
                <!-- Text Column (Right side in RTL) -->
                <div class="single-hero-text-col">

                    <!-- Badges Row -->
                    <div class="single-hero-badges-row">
                        <?php donghua_render_rating_widget((int) $donghua_id); ?>

                        <?php if ($age_rating && $age_rating !== 'Unknown' && $age_rating !== '0'): ?>
                            <span class="hero-badge badge-age" dir="ltr"><?php echo esc_html($age_rating); ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Main Work Title -->
                    <h1 class="single-hero-title"><?php echo esc_html($ar_name); ?></h1>

                    <!-- Subtitles (en & zh) -->
                    <?php if ($en_name || $zh_name): ?>
                        <div class="single-hero-subtitles">
                            <?php if ($en_name): ?>
                                <span class="sub-en"><?php echo esc_html($en_name); ?></span>
                            <?php endif; ?>
                            <?php if ($en_name && $zh_name): ?>
                                <span class="sub-sep">•</span>
                            <?php endif; ?>
                            <?php if ($zh_name): ?>
                                <span class="sub-zh"><?php echo esc_html($zh_name); ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Metadata Strip (Real data only) -->
                    <?php if ($donghua_views > 0): ?>
                        <div class="single-hero-metadata-strip">
                            <span class="meta-strip-item">
                                <span class="meta-strip-icon"><?php echo donghua_icon('eye', '', 14); ?></span>
                                <span><?php echo esc_html(number_format_i18n($donghua_views)); ?> مشاهدة</span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Synopsis: short at rest, with a native accessible disclosure for longer copy. -->
                    <?php if ($synopsis): ?>
                        <?php if ($synopsis_is_long): ?>
                            <details class="single-hero-synopsis single-hero-synopsis-disclosure">
                                <summary><span class="work-synopsis-preview"><?php echo esc_html($synopsis_preview); ?></span><span class="work-synopsis-toggle">قراءة الملخص كاملًا</span></summary>
                                <p class="work-synopsis-full"><?php echo esc_html($synopsis); ?></p>
                            </details>
                        <?php else: ?>
                            <p class="single-hero-synopsis"><?php echo esc_html($synopsis); ?></p>
                        <?php endif; ?>
                    <?php endif; ?>

                    <section class="work-facts-rail" id="work-facts" aria-label="معلومات العمل">
                        <dl>
                            <?php if ($status_name): ?><div><dt>الحالة</dt><dd><?php echo esc_html($status_name); ?></dd></div><?php endif; ?>
                            <?php if ($year_name): ?><div><dt>السنة</dt><dd><?php echo esc_html($year_name); ?></dd></div><?php endif; ?>
                            <?php if (!empty($anim_name)): ?><div><dt>التحريك</dt><dd><?php echo esc_html($anim_name); ?></dd></div><?php endif; ?>
                            <?php if (!empty($studios) && !is_wp_error($studios)): ?><div><dt>الاستوديو</dt><dd><?php echo esc_html(implode('، ', wp_list_pluck($studios, 'name'))); ?></dd></div><?php endif; ?>
                            <?php if (!empty($total_eps) || count($episodes) > 0): ?><div><dt>الحلقات</dt><dd><?php echo esc_html((string) (!empty($total_eps) ? $total_eps : count($episodes))); ?></dd></div><?php endif; ?>
                            <?php if (!empty($duration)): ?><div><dt>المدة</dt><dd><?php echo esc_html((string) $duration); ?></dd></div><?php endif; ?>
                            <?php if (!empty($genres) && !is_wp_error($genres)): ?><div class="work-facts-genres"><dt>التصنيفات</dt><dd><?php foreach ($genres as $genre): ?><a href="<?php echo esc_url(get_term_link($genre)); ?>"><?php echo esc_html($genre->name); ?></a><?php endforeach; ?></dd></div><?php endif; ?>
                        </dl>
                    </section>

                    <!-- Action Buttons -->
                    <div class="single-hero-actions-row">
                        <?php $primary_episode_url = ($resume_episode && empty($resume_episode['completed']) && !empty($resume_episode['episode_url'])) ? $resume_episode['episode_url'] : ($first_episode ? get_permalink($first_episode->ID) : ''); ?>
                        <?php if ($primary_episode_url): ?>
                            <a href="<?php echo esc_url($primary_episode_url); ?>" class="btn-single-hero-primary">
                                <span class="btn-icon"><?php echo donghua_icon('play', '', 16); ?></span>
                                <span><?php echo ($resume_episode && empty($resume_episode['completed'])) ? 'أكمل المشاهدة' : 'ابدأ المشاهدة'; ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($episodes)): ?>
                            <a href="#work-episodes" class="btn-single-hero-secondary">
                                <span class="btn-icon"><?php echo donghua_icon('list', '', 15); ?></span>
                                <span>قائمة الحلقات</span>
                            </a>
                        <?php endif; ?>

                        <?php $viewer_lists = is_user_logged_in() && class_exists('DonghuaCore\\Api\\UserLibrary') ? (DonghuaCore\Api\UserLibrary::state_for_user(get_current_user_id(), [$donghua_id])[$donghua_id] ?? []) : []; $library_login_url = add_query_arg('redirect_to', get_permalink($donghua_id), home_url('/login/')); ?>
                        <?php if (is_user_logged_in()): ?>
                            <div class="single-hero-user-ribbon user-action-ribbon" data-donghua-id="<?php echo esc_attr((string)$donghua_id); ?>">
                                <?php $favorite_active = in_array('favorite', $viewer_lists, true); $watching_active = in_array('watching', $viewer_lists, true); ?>
                                <button type="button" class="btn-user-list<?php echo $favorite_active ? ' active' : ''; ?>" data-list="favorite" data-label-on="في المفضلة" data-label-off="إضافة للمفضلة" aria-label="<?php echo esc_attr($favorite_active ? 'إزالة من المفضلة' : 'إضافة للمفضلة'); ?>" aria-pressed="<?php echo $favorite_active ? 'true' : 'false'; ?>">
                                    <span class="btn-icon"><?php echo donghua_icon('heart', '', 14); ?></span><span data-list-label><?php echo $favorite_active ? 'في المفضلة' : 'إضافة للمفضلة'; ?></span>
                                </button>
                                <button type="button" class="btn-user-list<?php echo $watching_active ? ' active' : ''; ?>" data-list="watching" data-label-on="أشاهده حاليًا" data-label-off="أتابع المشاهدة" aria-label="<?php echo esc_attr($watching_active ? 'إزالة من أتابع المشاهدة' : 'إضافة إلى أتابع المشاهدة'); ?>" aria-pressed="<?php echo $watching_active ? 'true' : 'false'; ?>">
                                    <span class="btn-icon"><?php echo donghua_icon('play', '', 14); ?></span><span data-list-label><?php echo $watching_active ? 'أشاهده حاليًا' : 'أتابع المشاهدة'; ?></span>
                                </button>
                                <details class="dh-work-library-more"><summary aria-expanded="false">قوائم أخرى</summary><div role="group" aria-label="قوائم هذا العمل">
                                    <?php foreach (['following' => 'أتابع للتنبيهات', 'plan_to_watch' => 'سأشاهده لاحقًا', 'completed' => 'أكملت مشاهدته'] as $list_type => $list_label): ?><label><input type="checkbox" data-list-toggle="<?php echo esc_attr($list_type); ?>" data-state-label="<?php echo esc_attr($list_label); ?>" <?php checked(in_array($list_type, $viewer_lists, true)); ?>><span><?php echo esc_html($list_label); ?></span></label><?php endforeach; ?>
                                    <p>متابعة العمل تنبهك بحلقاته الجديدة داخل الحساب عند توفر استحقاق الإشعارات. المفضلة لا ترسل إشعارات.</p>
                                </div></details>
                                <span class="dh-work-library-feedback" role="status" aria-live="polite" data-library-feedback></span>
                            </div>
                        <?php else: ?>
                            <div class="single-hero-user-ribbon user-action-ribbon" aria-label="إدارة مكتبتك">
                                <a class="btn-user-list" href="<?php echo esc_url($library_login_url); ?>"><span class="btn-icon"><?php echo donghua_icon('heart', '', 14); ?></span><span>سجّل الدخول للمفضلة</span></a>
                                <a class="btn-user-list" href="<?php echo esc_url($library_login_url); ?>"><span class="btn-icon"><?php echo donghua_icon('bookmark', '', 14); ?></span><span>أضف إلى مكتبتي</span></a>
                            </div>
                        <?php endif; ?>
                    </div>

                </div><!-- .single-hero-text-col -->

                <!-- Poster Column (Left side in RTL) -->
                <div class="single-hero-poster-col">
                    <div class="single-hero-poster-frame">
                        <?php if ($poster_url): ?>
                            <img src="<?php echo esc_url($poster_url); ?>"
                                 class="single-hero-poster-img"
                                 alt="<?php echo esc_attr($ar_name); ?>"
                                 width="235"
                                 height="352"
                                 fetchpriority="high" />
                        <?php else: ?>
                            <div class="single-hero-poster-placeholder">
                                <?php echo donghua_icon('film', '', 44); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div><!-- .single-hero-poster-col -->

            </div><!-- .single-hero-content-wrap -->
        </header>
    </div><!-- .site-container -->

    <!-- Main Content Flow (Containerized, Unified Layout) -->
    <div class="site-container single-donghua-body">

        <nav class="work-section-nav" aria-label="أقسام العمل">
            <a href="#work-facts">حول العمل</a>
            <a href="#work-episodes">الحلقات</a>
            <?php if ($recommendations->post_count > 0): ?><a href="#work-related">أعمال مشابهة</a><?php endif; ?>
            <a href="#work-comments">التعليقات</a>
        </nav>

        <!-- 2. Season cards with direct numbered episode links -->
        <section class="portal-section section-episodes" id="work-episodes" aria-label="المواسم والحلقات">
            <div class="portal-section-header">
                <div class="header-title-box">
                    <span class="header-accent-dot" aria-hidden="true"></span>
                    <h2 class="portal-section-title">المواسم</h2>
                    <span class="badge-count-pill"><?php echo esc_html($current_season_label); ?></span>
                </div>
            </div>

            <?php if ($resume_episode && !$resume_episode['completed'] && (int) ($resume_episode['progress_percent'] ?? 0) > 0): ?>
                <div class="episode-resume" data-server-progress="1" aria-label="متابعة آخر مشاهدة">
                    <a data-resume-link href="<?php echo esc_url($resume_episode['episode_url']); ?>">متابعة الحلقة <?php echo esc_html($resume_episode['episode_number'] ?: $resume_episode['episode_title']); ?></a>
                    <small><?php echo esc_html(number_format_i18n((int)$resume_episode['progress_percent'])); ?>% من الحلقة</small>
                </div>
            <?php endif; ?>

            <?php if (!empty($season_cards)): ?>
                <div class="season-picker-grid" aria-label="اختر الموسم">
                    <?php foreach ($season_cards as $season_card): ?>
                        <a class="season-picker-card<?php echo $season_card['selected'] ? ' is-current-season' : ''; ?>"
                           href="<?php echo esc_url($season_card['url']); ?>"
                           <?php echo $season_card['selected'] ? 'aria-current="page"' : ''; ?>
                           aria-label="<?php echo esc_attr(sprintf('%s، %d حلقة', $season_card['label'], $season_card['count'])); ?>">
                            <div class="season-picker-poster">
                                <?php if (!empty($season_card['poster'])): ?>
                                    <img src="<?php echo esc_url($season_card['poster']); ?>"
                                         alt="<?php echo esc_attr($season_card['label']); ?>"
                                         loading="lazy" />
                                <?php else: ?>
                                    <span class="season-poster-placeholder" aria-hidden="true"><?php echo donghua_icon('film', '', 28); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="season-picker-card-info">
                                <strong><?php echo esc_html($season_card['label']); ?></strong>
                                <small><?php echo esc_html((string)$season_card['count']); ?> حلقة</small>
                                <?php if ($season_card['selected']): ?>
                                    <span class="season-current-badge">الموسم الحالي</span>
                                <?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="season-episodes-header">
                <h3>حلقات <?php echo esc_html($current_season_label); ?></h3>
                <span><?php echo esc_html((string)count($episodes)); ?> حلقة</span>
            </div>

            <?php if (!empty($episodes)): ?>
                <div class="season-episode-buttons" aria-label="أرقام الحلقات">
                    <?php foreach ($episodes as $ep): ?>
                        <?php
                        $num = get_post_meta($ep->ID, '_episode_number', true) ?: '1';
                        $ep_disp_num = function_exists('donghua_episode_label') ? donghua_episode_label($num) : (string)$num;
                        ?>
                        <a href="<?php echo esc_url(get_permalink($ep->ID)); ?>"
                           class="season-episode-button"
                           aria-label="<?php echo esc_attr(sprintf('مشاهدة الحلقة %s من %s', $ep_disp_num, $ar_name)); ?>">
                            <span class="season-episode-play" aria-hidden="true"><?php echo donghua_icon('play', '', 11); ?></span>
                            <span>الحلقة <?php echo esc_html($ep_disp_num); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-episodes-state season-empty-state">
                    <span class="empty-icon"><?php echo donghua_icon('clock', '', 32); ?></span>
                    <p>لا توجد حلقات منشورة لهذا الموسم بعد.</p>
                </div>
            <?php endif; ?>
        </section>

        <?php if (function_exists('dh_render_ad_slot')) echo dh_render_ad_slot('donghua_after_episodes'); ?>

        <!-- 4. Encyclopedia Section (Only if Real Knowledge Entries Exist) -->
        <?php
        $enc_slug = get_post_field('post_name', $donghua_id);
        $enc_url  = home_url('/encyclopedia/donghua/' . $enc_slug . '/');
        $enc_entries = get_posts([
            'post_type'      => 'encyclopedia',
            'posts_per_page' => 6,
            'post_status'    => 'publish',
            'meta_query'     => [
                'relation' => 'OR',
                [
                    'key'     => '_related_donghua_ids',
                    'value'   => '"' . $donghua_id . '"',
                    'compare' => 'LIKE',
                ],
                [
                    'key'     => '_related_donghua_ids',
                    'value'   => 'i:' . $donghua_id . ';',
                    'compare' => 'LIKE',
                ],
            ],
        ]);
        ?>
        <?php if (!empty($enc_entries)): ?>
            <section class="portal-section section-encyclopedia" aria-label="موسوعة العمل المعرفية">
                <div class="portal-section-header">
                    <div class="header-title-box">
                        <span class="header-accent-dot" aria-hidden="true"></span>
                        <h2 class="portal-section-title">استكشف موسوعة العمل</h2>
                    </div>
                    <a href="<?php echo esc_url($enc_url); ?>" class="section-view-all">
                        <span>تصفح الموسوعة الكاملة</span>
                        <span class="view-all-arrow" aria-hidden="true">&lsaquo;</span>
                    </a>
                </div>
                <div class="enc-entries-grid">
                    <?php foreach ($enc_entries as $entry): ?>
                        <?php
                        $type_terms = get_the_terms($entry->ID, 'encyclopedia_type');
                        $type_label = (!empty($type_terms) && !is_wp_error($type_terms)) ? $type_terms[0]->name : 'مصطلح';
                        ?>
                        <a href="<?php echo esc_url(get_permalink($entry->ID)); ?>" class="enc-entry-item">
                            <span class="enc-type-badge"><?php echo esc_html($type_label); ?></span>
                            <h4 class="enc-entry-title"><?php echo esc_html($entry->post_title); ?></h4>
                            <span class="enc-entry-arrow" aria-hidden="true">&lsaquo;</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- 5. Similar Works (Using Canonical Homepage Grid System: 7 on 1920) -->
        <?php if ($recommendations->have_posts()): ?>
            <section class="portal-section section-similar" id="work-related" aria-label="أعمال مشابهة">
                <div class="portal-section-header">
                    <div class="header-title-box">
                        <span class="header-accent-dot" aria-hidden="true"></span>
                        <h2 class="portal-section-title">أعمال قد تعجبك</h2>
                    </div>
                    <a href="<?php echo esc_url(get_post_type_archive_link('donghua')); ?>" class="section-view-all">
                        <span>عرض الكل</span>
                        <span class="view-all-arrow" aria-hidden="true">&lsaquo;</span>
                    </a>
                </div>
                <div class="donghua-grid streaming-donghua-grid">
                    <?php while ($recommendations->have_posts()): $recommendations->the_post(); ?>
                        <?php get_template_part('template-parts/donghua-card'); ?>
                    <?php endwhile; wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- 6. Comments Section -->
        <section class="portal-section section-comments" id="work-comments" aria-label="التعليقات والمناقشات">
            <?php comments_template(); ?>
        </section>

    </div><!-- .site-container -->

</div><!-- .donghua-single-page -->

<?php get_footer(); ?>
