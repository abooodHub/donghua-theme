<?php
/**
 * The template for displaying a single Episode (Watch Page)
 * High-Fidelity Cinematic Layout matching TARGET VISUAL DESIGN (IMAGE B)
 * Preserves 100% backend integrity, real WordPress database data, and player logic.
 */
declare(strict_types=1);

nocache_headers();
get_header();

$episode_id = get_the_ID();
$parent_id  = (int) get_post_meta($episode_id, '_episode_parent_donghua', true);
$wiki_entry_ids = class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta') ? \DonghuaCore\Meta\EncyclopediaMeta::get_episode_mentions($episode_id, 30) : [];
$wiki_entries = [];
if ($wiki_entry_ids) {
    update_post_caches(array_map('get_post', $wiki_entry_ids), 'encyclopedia', true, true);
    foreach ($wiki_entry_ids as $wiki_id) {
        $owner_ids = get_post_meta($wiki_id, '_related_donghua_ids', true);
        if (!is_array($owner_ids)) $owner_ids = $owner_ids ? [(int)$owner_ids] : [];
        if ($parent_id && in_array($parent_id, array_map('intval', $owner_ids), true) && get_post_meta($wiki_id, '_encyclopedia_review_status', true) === 'published') $wiki_entries[] = $wiki_id;
    }
}
$ep_number  = (string)get_post_meta($episode_id, '_episode_number', true);
if ($ep_number === '') $ep_number = '1';

// Parent Donghua Info
$parent_post  = $parent_id ? get_post($parent_id) : null;
$parent_title = $parent_id ? (get_post_meta($parent_id, '_donghua_ar_name', true) ?: get_the_title($parent_id)) : get_the_title();
$parent_url   = $parent_post ? get_permalink($parent_post) : home_url('/');

// Taxonomies for breadcrumbs & specs
$anim_terms = $parent_id ? wp_get_post_terms($parent_id, 'animation_type', ['fields' => 'names']) : [];
$anim_name  = (!is_wp_error($anim_terms) && !empty($anim_terms)) ? $anim_terms[0] : 'تصنيفات';

$genre_terms = $parent_id ? wp_get_post_terms($parent_id, 'donghua_genre', ['fields' => 'names']) : [];
$genre_names = (!is_wp_error($genre_terms) && !empty($genre_terms)) ? implode('، ', array_slice($genre_terms, 0, 3)) : '';

// Backdrop artwork for atmospheric cinematic hero
$hero_backdrop_url = '';
if ($parent_id) {
    $hero_backdrop_url = donghua_get_work_backdrop_url($parent_id)
        ?: get_post_meta($parent_id, '_donghua_poster_url', true)
        ?: get_the_post_thumbnail_url($parent_id, 'donghua-banner');
}
if (!$hero_backdrop_url && has_post_thumbnail($episode_id)) {
    $hero_backdrop_url = get_the_post_thumbnail_url($episode_id, 'episode-thumb');
}

// Servers & Media
$watch_servers    = class_exists('Donghua_Playback_Service') ? Donghua_Playback_Service::sources_for_episode($episode_id, get_current_user_id()) : (get_post_meta($episode_id, '_episode_watch_servers', true) ?: []);
$download_servers = class_exists('Donghua_Playback_Service') ? Donghua_Playback_Service::downloads_for_episode($episode_id,get_current_user_id()) : [];
$quality_choices = class_exists('Donghua_Playback_Service') ? Donghua_Playback_Service::quality_choices($episode_id,get_current_user_id()) : [];
$translator       = get_post_meta($episode_id, '_episode_translator', true) ?: 'فريق المنصة';

// Media Meta Details
$media_meta       = function_exists('donghua_get_episode_media_meta')
    ? donghua_get_episode_media_meta($episode_id, $watch_servers, $download_servers)
    : [
        'editions'        => [],
        'qualities'       => [],
        'codecs'          => [],
        'containers'      => [],
        'direct_urls'     => [],
        'has_direct_url'  => false,
        'has_downloads'   => !empty($download_servers),
    ];
$has_multi_editions  = count($media_meta['editions']) > 1;
$has_multi_qualities = count($media_meta['qualities']) > 1;
$active_edition      = !empty($media_meta['editions']) ? reset($media_meta['editions']) : null;
$active_quality      = !empty($media_meta['qualities']) ? reset($media_meta['qualities']) : null;
$active_codec        = !empty($media_meta['codecs']) ? implode(' / ', $media_meta['codecs']) : null;
$active_container    = !empty($media_meta['containers']) ? implode(' / ', $media_meta['containers']) : null;

// Navigation: Next / Prev
$all_episodes  = donghua_public_episodes($parent_id);
$episode_index = array_search($episode_id, array_column($all_episodes, 'ID'), true);
$prev_episode  = $episode_index !== false && $episode_index > 0 ? $all_episodes[$episode_index - 1] : null;
$next_episode  = $episode_index !== false ? ($all_episodes[$episode_index + 1] ?? null) : null;

// Initial default server
$default_server = null;
if (!empty($watch_servers)) {
    foreach ($watch_servers as $srv) {
        if (!isset($srv['enabled']) || (int)$srv['enabled'] === 1) {
            $default_server = $srv;
            break;
        }
    }
}
$default_url = $default_server['url'] ?? '';
$is_default_direct = !empty($default_url) && (
    function_exists('donghua_is_direct_media_server')
        ? donghua_is_direct_media_server($default_server)
        : (function_exists('donghua_is_direct_media_url') && donghua_is_direct_media_url($default_url))
);

$initial_views = class_exists('Donghua_Episode_Stats')
    ? Donghua_Episode_Stats::get_total_views($episode_id)
    : (int) get_post_meta($episode_id, '_episode_views', true);
$initial_live = class_exists('Donghua_Episode_Stats')
    ? Donghua_Episode_Stats::get_live_count($episode_id)
    : 0;

$formatted_ep_num = is_numeric($ep_number) ? str_pad((string)$ep_number, 2, '0', STR_PAD_LEFT) : (string)$ep_number;
$episode_duration = get_post_meta($episode_id, '_episode_duration', true);
?>

<!-- Cinema Mode Dim Backdrop -->
<div id="cinema-backdrop" class="cinema-backdrop" aria-hidden="true"></div>

<div class="watch-page-container target-cinematic-theme" data-work-id="<?php echo esc_attr((string)$parent_id); ?>" data-current-episode="<?php echo esc_attr((string)$episode_id); ?>">

    <!-- Hero Atmospheric Backdrop Layer (IMAGE B Visual Style) -->
    <div class="watch-hero-atmosphere" aria-hidden="true">
        <?php if ($hero_backdrop_url): ?>
            <div class="watch-hero-bg-img" style="background-image: url('<?php echo esc_url($hero_backdrop_url); ?>');"></div>
        <?php endif; ?>
        <div class="watch-hero-gradient-overlay"></div>
        <div class="watch-hero-radial-vignette"></div>
    </div>

    <div class="site-container watch-inner-wrap">

        <!-- Keep the drawer backdrop in the same stacking context as its panel. -->
        <div id="episodes-drawer-backdrop" class="episodes-drawer-backdrop" aria-hidden="true"></div>

        <!-- Episode context spans both columns; video and episode rail align below it. -->
        <header class="watch-header-block">
            <nav class="watch-breadcrumb-trail" aria-label="مسار التصفح">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="trail-crumb home-crumb">
                    <?php echo donghua_icon('home', '', 14); ?>
                    <span>الرئيسية</span>
                </a>
                <span class="trail-sep">&lsaquo;</span>
                <span class="trail-crumb anim-crumb"><?php echo esc_html($anim_name); ?></span>
                <span class="trail-sep">&lsaquo;</span>
                <a href="<?php echo esc_url($parent_url); ?>" class="trail-crumb parent-crumb"><?php echo esc_html($parent_title); ?></a>
                <span class="trail-sep">&lsaquo;</span>
                <span class="trail-crumb current-crumb">الحلقة <?php echo esc_html($formatted_ep_num); ?></span>
            </nav>
            <div class="watch-title-meta-group">
                <h1 class="watch-episode-title">
                    الحلقة <?php echo esc_html($formatted_ep_num); ?> - <?php echo esc_html($parent_title); ?>
                </h1>
                <?php if ($parent_id > 0): donghua_render_rating_widget($parent_id, 'site'); endif; ?>
                <div class="watch-meta-badges-row">
                    <span class="watch-stat-item stat-views">
                        <?php echo donghua_icon('eye', 'stat-icon', 14, ['aria-label' => 'إجمالي المشاهدات']); ?>
                        <span class="stat-count" id="episode-total-views"><?php echo esc_html(number_format_i18n($initial_views)); ?></span>
                        <span class="stat-label">مشاهدة</span>
                    </span>
                    <?php if ($parent_id > 0): donghua_render_rating_widget($parent_id, 'audience'); endif; ?>
                    <?php if (!empty($active_quality)): ?>
                        <span class="badge-gold-quality"><?php echo esc_html($active_quality); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($episode_duration) && (string) $episode_duration !== '0'): ?>
                        <span class="watch-stat-item stat-duration">
                            <?php echo donghua_icon('clock', 'stat-icon', 14, ['aria-hidden' => 'true']); ?>
                            <span><?php echo esc_html((string) $episode_duration); ?></span>
                        </span>
                    <?php endif; ?>
                    <span class="watch-stat-item stat-live" id="episode-live-badge" aria-label="<?php echo esc_attr(sprintf('%s مستخدمًا يشاهدون هذه الحلقة الآن', number_format_i18n(max(1, $initial_live)))); ?>">
                        <span class="live-dot" aria-hidden="true"></span>
                        <span class="stat-count" id="episode-live-viewers"><?php echo esc_html(number_format_i18n(max(1, $initial_live))); ?></span>
                        <span class="stat-label">يشاهد الآن</span>
                    </span>
                </div>
            </div>
        </header>

        <!-- Main 2-Column Stage Layout (Dominant Right + Left Sidebar in RTL) -->
        <div class="watch-stage-layout" id="watch-stage">

            <!-- Video Player & Content Column (Dominant Center-Right) -->
            <div class="player-dominant-column">

                <!-- 16:9 Video Player Screen -->
                <div class="player-fixed-16x9" id="player-screen" data-player-poster="<?php echo esc_url($hero_backdrop_url); ?>">

                    <div class="watch-resume-prompt" id="watch-resume-prompt" role="status" aria-live="polite" hidden>
                        <span data-watch-resume-copy></span>
                        <button type="button" data-watch-resume>متابعة المشاهدة</button>
                        <button type="button" data-watch-restart>من البداية</button>
                    </div>
                    <a class="watch-next-after-complete" id="watch-next-after-complete" hidden>مشاهدة الحلقة التالية</a>
                    <div class="watch-next-countdown" id="watch-next-countdown" role="dialog" aria-modal="false" aria-labelledby="watch-next-countdown-title" aria-live="polite" hidden>
                        <span class="watch-next-countdown-copy"><span id="watch-next-countdown-title">الحلقة التالية</span><span>تبدأ خلال <strong data-countdown-seconds>10</strong> <bdi>ثوانٍ</bdi></span></span>
                        <button type="button" data-countdown-now>تشغيل الآن</button>
                        <button type="button" data-countdown-cancel>إلغاء</button>
                    </div>

                    <div class="player-ready-overlay" id="player-ready-overlay" <?php echo $is_default_direct ? 'hidden' : ''; ?> style="--watch-player-poster: url('<?php echo esc_url($hero_backdrop_url); ?>');">
                        <button type="button" class="player-ready-button" id="player-ready-button" aria-label="بدء مشاهدة الحلقة">
                            <span class="player-ready-play" aria-hidden="true"><?php echo donghua_icon('play', '', 30); ?></span>
                            <strong>جاهز للمشاهدة</strong>
                            <span>اضغط على زر التشغيل لبدء مشاهدة الحلقة</span>
                        </button>
                    </div>

                    <?php if (!empty($active_quality)): ?>
                        <span class="watch-quality-badge"><strong><?php echo (stripos((string) $active_quality, '2160') !== false || stripos((string) $active_quality, '4k') !== false) ? '4K' : (stripos((string) $active_quality, '1080') !== false ? 'FHD' : (stripos((string) $active_quality, '720') !== false ? 'HD' : 'SD')); ?></strong> <?php echo esc_html($active_quality); ?></span>
                    <?php endif; ?>

                    <!-- Fallback / Error State Overlay -->
                    <div id="player-error-overlay" class="player-error-overlay" role="status" hidden>
                        <div class="error-overlay-card">
                            <div class="error-icon-wrap" aria-hidden="true">
                                <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="12" y1="8" x2="12" y2="12"></line>
                                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                                </svg>
                            </div>
                            <h2 class="error-title">تعذر تشغيل هذا السيرفر</h2>
                            <p class="error-desc">قد يكون المصدر غير متاح مؤقتًا. جرّب سيرفرًا آخر أو أبلغنا عن المشكلة.</p>
                            <div class="error-buttons-row">
                                <button type="button" id="player-switch-next-server" class="btn-try-next">
                                    <span>&#9658; تجربة سيرفر آخر</span>
                                </button>
                            </div>
                            <button type="button" id="player-dismiss-overlay" class="btn-dismiss-overlay">إغلاق وإظهار الإطار</button>
                        </div>
                    </div>

                    <!-- Direct files use the browser's native player; embed pages stay in an iframe. -->
                    <?php if ($default_url): ?>
                        <?php if ($is_default_direct): ?>
                            <video id="main-video-player"
                                   controls
                                   playsinline
                                   preload="metadata"
                                   src="<?php echo esc_url($default_url); ?>"
                                   <?php if ($hero_backdrop_url): ?>poster="<?php echo esc_url($hero_backdrop_url); ?>"<?php endif; ?>
                                   title="<?php echo esc_attr(sprintf('%s - الحلقة %s', $parent_title, $ep_number)); ?>"
                                   style="position:absolute;inset:0;width:100%;height:100%;border:0;background:#071019;">
                            </video>
                        <?php else: ?>
                            <iframe id="main-video-frame"
                                    src="<?php echo esc_url($default_url); ?>"
                                    frameborder="0"
                                    scrolling="no"
                                    allowfullscreen
                                    allow="autoplay; fullscreen; encrypted-media; picture-in-picture"
                                    title="<?php echo esc_attr(sprintf('%s - الحلقة %s', $parent_title, $ep_number)); ?>"
                                    loading="eager">
                            </iframe>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="player-empty-box">
                            <span class="empty-icon"><?php echo donghua_icon('film', '', 40); ?></span>
                            <h2>لا يوجد مصدر مشاهدة متاح لهذه الحلقة حاليًا</h2>
                            <p>يمكنك العودة إلى قائمة الحلقات أو إبلاغنا عن المشكلة من قسم الإبلاغ بالأسفل.</p>
                        </div>
                    <?php endif; ?>

                </div><!-- .player-fixed-16x9 -->

                <!-- Unified stream controls. The server, media data and actions share one surface. -->
                <div class="watch-stream-hub" id="watch-stream-hub" role="region" aria-label="سيرفرات المشاهدة وجودة الحلقة">

                    <!-- Stream Hub Header -->
                    <div class="stream-hub-main-header">
                        <div class="hub-header-title-wrap">
                            <span class="hub-header-icon"><?php echo donghua_icon('monitor', '', 18); ?></span>
                            <div class="hub-titles">
                                <h2 class="hub-title">اختر سيرفر المشاهدة</h2>
                                <p class="hub-subtitle">مصادر تشغيل الحلقة المتاحة</p>
                            </div>
                        </div>
                    </div>

                    <!-- Servers Pills Row -->
                    <div class="stream-hub-servers-row" role="group" aria-label="قائمة السيرفرات المتاحة">
                        <span class="server-selector-label">السيرفرات</span>
                        <?php if (!empty($watch_servers)): ?>
                            <?php
                            $active_set = false;
                            $dot_colors = ['#12C7C0', '#F59E0B', '#10B981', '#38BDF8', '#8B5CF6'];
                            foreach ($watch_servers as $idx => $srv):
                                $is_enabled = !isset($srv['enabled']) || (int)$srv['enabled'] === 1;
                                if (!$is_enabled) continue;
                                $is_active = false;
                                if (!$active_set) {
                                    $is_active = true;
                                    $active_set = true;
                                }
                                $srv_name = !empty($srv['name']) ? $srv['name'] : ('سيرفر ' . ($idx + 1));
                                $badge_info = function_exists('donghua_get_server_badge')
                                    ? donghua_get_server_badge($srv, $idx, count($watch_servers))
                                    : [
                                        'text'           => ($idx === 0 ? 'السيرفر الرئيسي' : 'سيرفر بديل'),
                                        'type_label'     => 'مشغل مدمج',
                                        'class'          => ($idx === 0 ? 'srv-badge-primary' : 'srv-badge-alt'),
                                        'is_recommended' => ($idx === 0),
                                        'is_direct'      => false,
                                ];
                                $dot_color = $dot_colors[$idx % count($dot_colors)];
                                $server_host = strtolower((string) wp_parse_url((string) ($srv['url'] ?? ''), PHP_URL_HOST));
                                $server_identity = strtolower($srv_name . ' ' . $server_host);
                                $provider = 'generic';
                                if (strpos($server_identity, 'google') !== false || strpos($server_identity, 'drive') !== false) $provider = 'drive';
                                elseif (strpos($server_identity, 'youtube') !== false || strpos($server_identity, 'youtu.be') !== false) $provider = 'youtube';
                                elseif (strpos($server_identity, 'ok.ru') !== false || strpos($server_identity, 'odnoklassniki') !== false) $provider = 'ok';
                                elseif (strpos($server_identity, 'filemoon') !== false) $provider = 'filemoon';
                                elseif (strpos($server_identity, 'earnvids') !== false) $provider = 'earnvids';
                                elseif (strpos($server_identity, 'mega') !== false) $provider = 'mega';
                                elseif (strpos($server_identity, 'dood') !== false) $provider = 'dood';
                                $provider_marks = [
                                    'drive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="m8.3 3 4.1 7.1h8.2L16.5 3z"/><path fill="#34A853" d="m4.2 10.1 4.1 7.1 4.1-7.1L8.3 3z"/><path fill="#FBBC04" d="m8.3 17.2-2.4 4.1h8.2l2.4-4.1z"/><path fill="#EA4335" d="m12.4 10.1-4.1 7.1h8.2l4.1-7.1z"/></svg>',
                                    'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="4" fill="#ff3040"/><path d="m10 9 6 3-6 3z" fill="#fff"/></svg>',
                                    'ok' => '<span aria-hidden="true">OK</span>',
                                    'filemoon' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#55c6d1" d="M17.8 15.8A8 8 0 0 1 8.2 5.2 9 9 0 1 0 17.8 15.8Z"/><circle cx="16.8" cy="6.3" r="1.3" fill="#a8e8ed"/></svg>',
                                    'earnvids' => '<span aria-hidden="true">E</span>',
                                    'mega' => '<span aria-hidden="true">M</span>',
                                    'dood' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="#f4bd32"/><path d="m10 7 7 5-7 5z" fill="#17212a"/></svg>',
                                    'generic' => donghua_icon('film', 'server-provider-generic-icon', 14),
                                ];
                            ?>
                                <button type="button"

                                        aria-pressed="<?php echo $is_active ? 'true' : 'false'; ?>"
                                        data-server-index="<?php echo esc_attr((string)$idx); ?>"
                                        data-server-url="<?php echo esc_url($srv['url']); ?>"
                                        data-server-key="<?php echo esc_attr($srv_name); ?>"
                                        data-server-type="<?php echo esc_attr($srv['type'] ?? 'embed'); ?>"
                                        data-source-access="<?php echo esc_attr(\DonghuaCore\Meta\EpisodeMeta::source_access($srv)); ?>"
                                        data-source-quality="<?php echo esc_attr(Donghua_Playback_Service::quality($srv)); ?>"
                                        data-is-direct="<?php echo $badge_info['is_direct'] ? '1' : '0'; ?>"
                                        class="server-pill server-card-pill <?php echo $is_active ? 'is-active' : ''; ?>">
                                    <?php if ($idx === 0): ?>
                                        <span class="srv-crown-icon" aria-hidden="true">&#9813;</span>
                                    <?php endif; ?>
                                    <span class="server-provider-icon provider-<?php echo esc_attr($provider); ?>" aria-hidden="true"><?php echo $provider_marks[$provider]; ?></span>
                                    <span class="server-name"><?php echo esc_html($srv_name); ?></span>
                                    <span class="server-status-dot" style="background-color: <?php echo esc_attr($dot_color); ?>;" aria-hidden="true"></span>
                                </button>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-servers-note">لم يتم إرفاق سيرفرات مشاهدة لهذه الحلقة بعد.</div>
                        <?php endif; ?>
                        <span class="server-vlc-action-group">
                            <span class="server-vlc-divider" aria-hidden="true">|</span>
                            <button type="button"
                                    class="watch-vlc-inline"
                                    id="hub-open-vlc"
                                    <?php disabled(!$is_default_direct); ?>
                                    aria-disabled="<?php echo $is_default_direct ? 'false' : 'true'; ?>"
                                    aria-label="فتح الرابط المباشر في مشغل VLC الخارجي"
                                    title="فتح الرابط المباشر في مشغل VLC الخارجي">
                                <svg class="watch-vlc-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <path d="M7 3h10l2 4H5l2-4Z" fill="#f5a623"/>
                                    <path d="m5 8 3 11h8l3-11H5Z" fill="#e87516"/>
                                    <path d="M6.3 12h11.4l-.8 3H7.1l-.8-3Z" fill="#fff1d3"/>
                                    <path d="M7.3 16h9.4l-.7 3H8l-.7-3Z" fill="#fff1d3"/>
                                    <path d="M4 21h16v1H4z" fill="#d9e5e8"/>
                                </svg>
                                <span>فتح في VLC</span>
                            </button>
                        </span>
                    </div>

                    <?php if (count($quality_choices)>1): ?>
                    <div class="watch-quality-control">
                        <label for="watch-quality-select">الجودة</label>
                        <select id="watch-quality-select" aria-describedby="watch-quality-note">
                            <option value="">اختر الجودة</option>
                            <?php foreach ($quality_choices as $quality=>$choice): ?>
                            <option value="<?php echo esc_attr($quality); ?>" data-locked="<?php echo $choice['locked']?'1':'0'; ?>"><?php echo esc_html($choice['label'].($choice['locked']?' · السيرفر الخاص':'')); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small id="watch-quality-note">التبديل بين ملفات فعلية؛ المشغل الخارجي يتحكم بجودته.</small>
                    </div>
                    <dialog id="watch-quality-upgrade" class="watch-quality-upgrade" aria-labelledby="watch-quality-upgrade-title">
                        <h3 id="watch-quality-upgrade-title">هذا المصدر يتطلب باقة بلا حدود</h3>
                        <p>تظهر لك الجودات المتاحة حسب المصادر الفعلية لكل حلقة.</p>
                        <a href="<?php echo esc_url(home_url('/membership/')); ?>">عرض العضوية</a>
                        <button type="button" data-quality-close>متابعة المشاهدة</button>
                    </dialog>
                    <?php endif; ?>

                    <!-- Server Specs Grid (4 Columns matching IMAGE B) -->
                    <div class="stream-specs-quad-card" id="edition-info-panel" role="region" aria-label="معلومات ومواصفات النسخة">
                        <div class="specs-quad-grid">
                            <div class="spec-quad-col">
                                <span class="spec-label">الجودة المتاحة</span>
                                <span class="spec-val" id="spec-val-quality"><?php echo esc_html($active_quality ?: ($media_meta['has_direct_url'] ? 'أصلية' : 'تلقائية')); ?></span>
                            </div>
                            <div class="spec-quad-divider" aria-hidden="true"></div>
                            <div class="spec-quad-col">
                                <span class="spec-label">الصيغة</span>
                                <span class="spec-val" id="spec-val-codec"><?php echo esc_html($active_container ?: ($active_codec ?: 'Web')); ?></span>
                            </div>
                            <div class="spec-quad-divider" aria-hidden="true"></div>
                            <div class="spec-quad-col">
                                <span class="spec-label">الواجهة الصوتية</span>
                                <span class="spec-val" id="spec-val-sub"><?php echo esc_html(!empty($active_edition) && strcasecmp($active_edition, 'multi-audio') === 0 ? 'متعدد اللغات' : 'صينية أصلية'); ?></span>
                            </div>
                            <div class="spec-quad-divider" aria-hidden="true"></div>
                            <div class="spec-quad-col">
                                <span class="spec-label">المشغل الموصى به</span>
                                <span class="spec-val has-play-icon" id="spec-val-player">
                                    <span class="teal-play-icon">&#9658;</span>
                                    <span><?php echo $media_meta['has_direct_url'] ? 'VLC / MX Player' : 'المتصفح / المشغل المدمج'; ?></span>
                                </span>
                            </div>
                        </div>

                        <!-- Notice Banner -->
                        <div class="stream-notice-banner">
                            <span class="notice-info-icon"><?php echo donghua_icon('info', '', 15); ?></span>
                            <span class="notice-text">جميع السيرفرات تعمل بشكل طبيعي، في حال واجهتك أي مشكلة في التشغيل، يرجى تجربة سيرفر آخر.</span>
                        </div>
                    </div>

                    <!-- Remaining episode actions. -->
                    <div class="stream-hub-actions-bar">
                        <!-- Share action falls back to copying the episode URL. -->
                        <button type="button"
                                class="btn-hub-action btn-hub-secondary hub-copy-btn"
                                id="hub-copy-link"
                                data-episode-url="<?php echo esc_url(get_permalink($episode_id)); ?>"
                                data-share-link="1"
                                data-is-direct="<?php echo $is_default_direct ? '1' : '0'; ?>"
                                title="مشاركة رابط الحلقة">
                            <span class="btn-hub-icon"><?php echo donghua_icon('share', '', 15); ?></span>
                            <span class="btn-hub-text" id="hub-copy-text">مشاركة الحلقة</span>
                            <span class="hub-copy-feedback" id="hub-copy-tooltip" aria-hidden="true">✓ تم النسخ!</span>
                        </button>

                        <!-- Report Issue -->
                        <button type="button" class="btn-hub-action btn-hub-secondary" id="hub-report-trigger" title="الإبلاغ عن مشكلة في هذا السيرفر">
                            <span class="btn-hub-icon">&#9873;</span>
                            <span class="btn-hub-text">الإبلاغ عن مشكلة</span>
                        </button>

                    </div>

                </div><!-- .watch-stream-hub -->

                <section class="watch-downloads-section" id="watch-downloads-section" aria-labelledby="watch-downloads-title">
                    <h3 class="downloads-section-title" id="watch-downloads-title"><span class="downloads-heading-icon" aria-hidden="true"><?php echo donghua_icon('download', '', 17); ?></span>سيرفرات التحميل</h3>
                    <?php
                    $visible_download_servers = array_values(array_filter($download_servers, static function ($download_server) {
                        return is_array($download_server) && !empty($download_server['url']);
                    }));
                    ?>
                    <?php if (!empty($visible_download_servers)): ?>
                        <div class="watch-download-items">
                            <?php foreach ($visible_download_servers as $download_server): ?>
                                <?php
                                $download_quality = strtolower((string) ($download_server['quality'] ?? ''));
                                $download_quality_label = in_array($download_quality, ['2160p', '4k'], true) ? '4K / 2160p' : ($download_quality ?: 'جودة عالية');
                                $download_codec = strtoupper((string) ($download_server['codec'] ?? ''));
                                $download_subtitles = (string) ($download_server['sub_type'] ?? '');
                                $download_subtitle_label = ['Softsub' => 'ترجمة قابلة للتبديل', 'Hardsub' => 'ترجمة مدمجة', 'Raw' => 'بدون ترجمة'][$download_subtitles] ?? '';
                                $download_details = array_filter([$download_quality_label, $download_codec, $download_subtitle_label, !empty($download_server['size']) ? (string) $download_server['size'] : '']);
                                $download_provider_identity = strtolower((string) ($download_server['name'] ?? '') . ' ' . (string) wp_parse_url((string) ($download_server['url'] ?? ''), PHP_URL_HOST));
                                $download_provider = 'generic';
                                if (strpos($download_provider_identity, 'drive') !== false || strpos($download_provider_identity, 'google') !== false) $download_provider = 'drive';
                                elseif (strpos($download_provider_identity, 'gofile') !== false) $download_provider = 'gofile';
                                elseif (strpos($download_provider_identity, 'mega') !== false) $download_provider = 'mega';
                                $download_provider_icons = [
                                    'drive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="m8.3 3 4.1 7.1h8.2L16.5 3z"/><path fill="#34A853" d="m4.2 10.1 4.1 7.1 4.1-7.1L8.3 3z"/><path fill="#FBBC04" d="m8.3 17.2-2.4 4.1h8.2l2.4-4.1z"/><path fill="#EA4335" d="m12.4 10.1-4.1 7.1h8.2l4.1-7.1z"/></svg>',
                                    'gofile' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5 21 7.7v8.6l-9 5.2-9-5.2V7.7l9-5.2Z" fill="#35a66f"/><path d="M16.7 9.2a5.6 5.6 0 1 0 .7 5.2h-2.6a3.1 3.1 0 1 1-.6-3.4l-1.7 1.7h5.6V7.1l-1.4 2.1Z" fill="#fff"/></svg>',
                                    'mega' => '<span aria-hidden="true">M</span>',
                                    'generic' => donghua_icon('download', 'server-provider-generic-icon', 16),
                                ];
                                ?>
                                <a class="watch-download-item" href="<?php echo esc_url($download_server['url']); ?>" target="_blank" rel="noopener noreferrer" title="تحميل الحلقة">
                                    <span class="download-item-icon download-provider-icon provider-<?php echo esc_attr($download_provider); ?>" aria-hidden="true"><?php echo $download_provider_icons[$download_provider]; ?></span>
                                    <span class="btn-hub-download-copy"><strong><?php echo esc_html($download_server['name'] ?? 'تحميل الحلقة'); ?></strong><small><?php echo esc_html(implode(' · ', $download_details)); ?></small></span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="watch-download-empty">لا توجد روابط تحميل متاحة لهذه الحلقة حاليًا.</p>
                    <?php endif; ?>
                </section>

                <nav class="watch-nav-quad-bar" role="navigation" aria-label="تنقل الحلقات">
                    <?php if ($prev_episode): ?>
                        <a href="<?php echo esc_url(get_permalink($prev_episode->ID)); ?>" class="nav-quad-btn prev-btn" aria-label="الحلقة السابقة"><span class="quad-arrow">&#9668;</span><span>الحلقة السابقة</span></a>
                    <?php else: ?>
                        <span class="nav-quad-btn is-disabled" aria-disabled="true"><span class="quad-arrow">&#9668;</span><span>الحلقة السابقة</span></span>
                    <?php endif; ?>
                    <button type="button" id="toggle-episodes-drawer" aria-controls="episodes-drawer" aria-expanded="false" class="nav-quad-btn list-toggle-btn" aria-label="تقليب الحلقات"><span class="quad-icon">&#9776;</span><span>قائمة الحلقات</span></button>
                    <?php if ($next_episode): ?>
                        <a href="<?php echo esc_url(get_permalink($next_episode->ID)); ?>" class="nav-quad-btn next-btn is-active-nav" aria-label="الحلقة التالية"><span class="quad-arrow">&#9658;</span><span>الحلقة التالية</span></a>
                    <?php else: ?>
                        <span class="nav-quad-btn is-disabled" aria-disabled="true"><span class="quad-arrow">&#9658;</span><span>الحلقة التالية</span></span>
                    <?php endif; ?>
                </nav>

                <details class="watch-preference-menu">
                    <summary>إعدادات المشاهدة</summary>
                    <button type="button" id="watch-preferences-reset"><span aria-hidden="true">↺</span> استعادة الإعدادات الافتراضية</button>
                </details>

            </div><!-- .player-dominant-column -->

            <!-- Left Column: Tagline + Episodes List Sidebar (IMAGE B) -->
            <div class="watch-sidebar-column">

                <!-- Atmospheric Fantasy Tagline on the Left (IMAGE B Inspiration) -->
                <div class="sidebar-decorative-tagline" aria-hidden="true">
                    <span class="tagline-ar-accent">رحلة</span>
                    <span class="tagline-ar-lead">أطول من الحياة<br>تبدأ بخطوة</span>
                    <span class="tagline-en-brand">DONGHUA A BRIGHTER FANTASY WORLD</span>
                </div>

                <aside class="episodes-drawer-panel" id="episodes-drawer" aria-label="قائمة الحلقات">
                    <!-- Desktop Sidebar Header -->
                    <div class="episodes-sidebar-head">
                        <div class="episodes-sidebar-title">
                            <span class="head-icon"><?php echo donghua_icon('list', '', 16); ?></span>
                            <span class="head-title">قائمة الحلقات</span>
                        </div>
                        <div class="episodes-sidebar-tools" role="group" aria-label="إعدادات قائمة الحلقات">
                            <button type="button" id="sort-episodes-btn" class="episodes-sort-btn" title="ترتيب الحلقات تصاعديًا أو تنازليًا" aria-label="ترتيب الحلقات"><span aria-hidden="true">⇅</span><span>ترتيب</span></button>
                            <label class="episodes-auto-next"><input type="checkbox" id="watch-auto-next"><span>تشغيل التالي تلقائيًا</span></label>
                        </div>
                    </div>

                    <!-- Mobile Header (Hidden on Desktop) -->
                    <div class="drawer-mobile-bar">
                        <span class="drawer-mobile-title">الحلقات</span>
                        <button type="button" id="close-episodes-drawer" class="drawer-close-btn" aria-label="إغلاق">&times;</button>
                    </div>

                    <!-- Scrollable Episode Items directly -->
                    <div class="episodes-items-scroll" id="episodes-scroll-container">
                        <?php if (!empty($all_episodes)): ?>
                            <?php foreach ($all_episodes as $ep): ?>
                                <?php
                                $num = (string)get_post_meta($ep->ID, '_episode_number', true);
                                if ($num === '') $num = '1';
                                $item_formatted_num = is_numeric($num) ? str_pad((string)$num, 2, '0', STR_PAD_LEFT) : (string)$num;
                                $is_current = ($ep->ID === $episode_id);
                                ?>
                                <a href="<?php echo esc_url(get_permalink($ep->ID)); ?>"
                                   class="drawer-episode-item <?php echo $is_current ? 'is-current' : ''; ?>"
                                   data-episode-id="<?php echo esc_attr((string)$ep->ID); ?>"
                                   data-number="<?php echo esc_attr((string)$num); ?>"
                                   data-search="<?php echo esc_attr($num . ' ' . $ep->post_title); ?>"
                                   <?php echo $is_current ? 'aria-current="page"' : ''; ?>>
                                    <span class="item-text">الحلقة <?php echo esc_html($item_formatted_num); ?></span>
                                    <span class="episode-watch-state" hidden aria-hidden="true"></span>
                                    <span class="item-play-arrow" aria-hidden="true">&#9658;</span>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="drawer-empty-msg">لا توجد حلقات أخرى مضافة.</div>
                        <?php endif; ?>
                    </div>

                    <button type="button" class="badge-follow-btn" id="episode-follow-btn" title="إضافة هذا العمل إلى قائمتي">
                        <?php echo donghua_icon('bookmark', '', 14, ['aria-hidden' => 'true']); ?>
                        <span>إضافة إلى قائمتي</span>
                    </button>
                </aside>
            </div>

        </div><!-- .watch-stage-layout -->

        <?php if (function_exists('dh_render_ad_slot')) echo dh_render_ad_slot('episode_below_player'); ?>

        <!-- Hidden until the primary report action opens it. -->
        <?php if (class_exists('DonghuaCore\\Reports\\EpisodeReports')): ?>
            <details class="watch-report-details" id="watch-report-box" hidden>
                <form id="episode-report-form" class="report-clean-form" method="post" action="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-endpoint="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
                    <input type="hidden" name="action" value="donghua_episode_report" />
                    <input type="hidden" name="episode_id" value="<?php echo esc_attr((string)$episode_id); ?>" />
                    <?php wp_nonce_field('donghua_episode_report', 'report_nonce', false); ?>

                    <div class="report-form-grid">
                        <label class="form-field">
                            <span class="field-caption">نوع المشكلة:</span>
                            <select name="reason" class="report-select" required>
                                <option value="server_down">السيرفر لا يعمل</option>
                                <option value="video_start">الفيديو لا يبدأ</option>
                                <option value="stutter">تقطيع</option>
                                <option value="no_audio">الصوت لا يعمل</option>
                                <option value="subtitle_sync">الترجمة غير متزامنة</option>
                                <option value="quality_unavailable">الجودة غير متوفرة</option>
                                <option value="other">مشكلة أخرى</option>
                            </select>
                        </label>
                        <label class="form-field">
                            <span class="field-caption">تفاصيل إضافية (اختياري):</span>
                            <textarea name="details" class="report-textarea" maxlength="1000" rows="2" placeholder="صف المشكلة باختصار لنتمكن من معالجتها فوراً"></textarea>
                        </label>
                    </div>
                    <input type="hidden" name="media_time" value="0" />
                    <input type="hidden" name="player_error_code" value="" />
                    <div class="report-submit-bar">
                        <button type="submit" class="btn-submit-report">إرسال البلاغ</button>
                        <span id="episode-report-status" class="report-status-text" role="status" aria-live="polite"></span>
                    </div>
                </form>
            </details>
        <?php endif; ?>

        <?php if (function_exists('dh_render_ad_slot')) echo dh_render_ad_slot('episode_before_comments'); ?>

        <?php if ($wiki_entries): ?>
            <section class="episode-wiki-mentions" aria-labelledby="episode-wiki-title">
                <h2 id="episode-wiki-title">مصطلحات هذه الحلقة</h2>
                <ul><?php foreach ($wiki_entries as $wiki_id): $wiki_name = get_post_meta($wiki_id, '_encyclopedia_ar_name', true) ?: get_the_title($wiki_id); ?>
                    <li><a href="<?php echo esc_url(get_permalink($wiki_id)); ?>" dir="auto"><bdi><?php echo esc_html($wiki_name); ?></bdi></a></li>
                <?php endforeach; ?></ul>
                <a class="episode-wiki-more" href="<?php echo esc_url(home_url('/encyclopedia/donghua/' . ($parent_post ? $parent_post->post_name : ''))); ?>">موسوعة العمل</a>
            </section>
        <?php endif; ?>

        <section class="watch-comments-surface-card" aria-label="نقاش الحلقة والتعليقات">
            <div class="watch-comments-wrapper">
                <?php comments_template(); ?>
            </div>
        </section>

    </div><!-- .site-container -->
</div><!-- .watch-page-container -->

<?php get_footer(); ?>
