<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

define('DONGHUA_THEME_VERSION', '3.0.27');
define('DONGHUA_THEME_DIR', get_template_directory());
define('DONGHUA_THEME_URI', get_template_directory_uri());

require_once DONGHUA_THEME_DIR . '/template-parts/svg-icons.php';
require_once DONGHUA_THEME_DIR . '/inc/customizer.php';
require_once DONGHUA_THEME_DIR . '/inc/admin.php';
require_once DONGHUA_THEME_DIR . '/inc/episodes.php';
require_once DONGHUA_THEME_DIR . '/inc/playback-service.php';
require_once DONGHUA_THEME_DIR . '/inc/episode-stats.php';

function donghua_theme_setup(): void {
    load_theme_textdomain('donghua-theme', DONGHUA_THEME_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 220,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);

    register_nav_menus([
        'primary' => __('القائمة الرئيسية (Header Menu)', 'donghua-theme'),
        'footer'  => __('قائمة الفوتر (Footer Menu)', 'donghua-theme'),
    ]);

    // Custom image sizes for Donghua cards and banners
    add_image_size('donghua-poster', 220, 310, true);
    add_image_size('donghua-thumb', 120, 160, true);
    add_image_size('donghua-banner', 1280, 520, true);
    add_image_size('episode-thumb', 320, 180, true);
}
add_action('after_setup_theme', 'donghua_theme_setup');

/** Use the approved dragon mark when WordPress does not already have a site icon. */
function donghua_theme_brand_mark_favicon(): void {
    if (has_site_icon()) return;
    printf(
        '<link rel="icon" href="%s" type="image/png">' . "\n",
        esc_url(DONGHUA_THEME_URI . '/assets/images/brand/okdonghua-mark.png')
    );
}
add_action('wp_head', 'donghua_theme_brand_mark_favicon', 1);

/** Point film navigation to the dedicated film archive. */
function donghua_correct_movie_menu_item(array $items, object $args): array {
    if (($args->theme_location ?? '') !== 'primary') return $items;
    foreach ($items as $item) {
        if (trim(wp_strip_all_tags((string)$item->title)) !== 'أفلام') continue;
        $item->url = get_post_type_archive_link('movie') ?: home_url('/movies/');
        $item->classes = array_values(array_diff((array)$item->classes, ['current-menu-item','current_page_item']));
        if ((is_post_type_archive('movie') || is_singular('movie')) && !in_array('current-menu-item', (array)$item->classes, true)) $item->classes[] = 'current-menu-item';
    }
    return $items;
}
add_filter('wp_nav_menu_objects', 'donghua_correct_movie_menu_item', 20, 2);

/** Resolve a work backdrop to a responsive local banner size where possible. */
function donghua_get_work_backdrop_url(int $post_id, string $size = 'donghua-banner'): string {
    if ($post_id < 1) return '';
    $banner = (string) get_post_meta($post_id, '_donghua_banner_url', true);
    if ($banner !== '') {
        $uploads = wp_get_upload_dir();
        $banner_parts = wp_parse_url($banner);
        $uploads_parts = wp_parse_url((string)($uploads['baseurl'] ?? ''));
        $banner_path = (string)($banner_parts['path'] ?? '');
        $uploads_path = trailingslashit((string)($uploads_parts['path'] ?? ''));
        $same_upload_host = strtolower((string)($banner_parts['host'] ?? '')) === strtolower((string)($uploads_parts['host'] ?? ''));
        $is_local_upload = $same_upload_host && $uploads_path !== '/' && str_starts_with($banner_path, $uploads_path);
        $attachment_id = attachment_url_to_postid($banner);
        if ($attachment_id > 0) {
            $attachment_file = get_attached_file($attachment_id);
            if (!$attachment_file || !is_file($attachment_file)) return '';
            $sized = wp_get_attachment_image_url($attachment_id, $size);
            if ($sized) return $sized;
        }
        if ($is_local_upload) return '';
        return esc_url_raw($banner);
    }
    $thumbnail = get_post_thumbnail_id($post_id);
    if ($thumbnail) {
        $sized = wp_get_attachment_image_url($thumbnail, $size);
        if ($sized) return $sized;
    }
    return '';
}

function donghua_enqueue_assets(): void {
    // Plugin portal rewrites may retain WordPress's home query flag without rendering the home hero.
    $is_theme_home = is_front_page() && !get_query_var('dhm_portal');
    // Fonts — Optimized: Oswald 700 only, Poppins 400 only, Tajawal 400/500/700/800
    wp_enqueue_style(
        'donghua-google-fonts',
        'https://fonts.googleapis.com/css2?family=Oswald:wght@700&family=Poppins:wght@400&family=Tajawal:wght@400;500;700;800&display=swap',
        [],
        null
    );

    // Swiper Slider CSS & JS — only needed on homepage (hero slider)
    $main_css_deps = ['donghua-style'];
    $main_js_deps  = [];

    if ($is_theme_home) {
        wp_enqueue_style('donghua-swiper-css', DONGHUA_THEME_URI . '/assets/css/swiper-bundle.min.css', [], '11.1.14');
        $main_css_deps[] = 'donghua-swiper-css';

        wp_enqueue_script('donghua-swiper-js', DONGHUA_THEME_URI . '/assets/js/swiper-bundle.min.js', [], '11.1.14', true);
        $main_js_deps[] = 'donghua-swiper-js';
    }

    // Theme Styles
    wp_enqueue_style('donghua-style', get_stylesheet_uri(), [], DONGHUA_THEME_VERSION);
    $tokens_css_path = get_stylesheet_directory() . '/assets/css/tokens.css';
    wp_enqueue_style('donghua-tokens', DONGHUA_THEME_URI . '/assets/css/tokens.css', ['donghua-style'], file_exists($tokens_css_path) ? (string) filemtime($tokens_css_path) : DONGHUA_THEME_VERSION);
    $main_css_deps[] = 'donghua-tokens';
    $main_css_path = DONGHUA_THEME_DIR . '/assets/css/main.css';
    wp_enqueue_style('donghua-main-css', DONGHUA_THEME_URI . '/assets/css/main.css', $main_css_deps, file_exists($main_css_path) ? (string) filemtime($main_css_path) : DONGHUA_THEME_VERSION);
    wp_enqueue_style('donghua-responsive-css', DONGHUA_THEME_URI . '/assets/css/responsive.css', ['donghua-main-css'], DONGHUA_THEME_VERSION);
    wp_enqueue_style('donghua-refinements', DONGHUA_THEME_URI . '/assets/css/refinements.css', ['donghua-responsive-css'], DONGHUA_THEME_VERSION);
    wp_enqueue_style('donghua-branding', DONGHUA_THEME_URI . '/assets/css/branding.css', ['donghua-refinements'], DONGHUA_THEME_VERSION);
    if (is_post_type_archive('encyclopedia') || is_singular('encyclopedia') || get_query_var('donghua_encyclopedia')) {
        $glossary_css_path = DONGHUA_THEME_DIR . '/assets/css/glossary.css';
        wp_enqueue_style('donghua-glossary', DONGHUA_THEME_URI . '/assets/css/glossary.css', ['donghua-refinements'], file_exists($glossary_css_path) ? (string) filemtime($glossary_css_path) : DONGHUA_THEME_VERSION);
    }
    if (is_post_type_archive('encyclopedia')) {
        $glossary_js_path = DONGHUA_THEME_DIR . '/assets/js/glossary.js';
        wp_enqueue_script('donghua-glossary', DONGHUA_THEME_URI . '/assets/js/glossary.js', [], file_exists($glossary_js_path) ? (string) filemtime($glossary_js_path) : DONGHUA_THEME_VERSION, true);
    }
    if ($is_theme_home) {
        wp_enqueue_script('donghua-discovery', DONGHUA_THEME_URI . '/assets/js/discovery.js', [], DONGHUA_THEME_VERSION, true);
    }

    if (is_singular('movie') || is_post_type_archive('movie') || is_search()) {
        $movie_css_path = DONGHUA_THEME_DIR . '/assets/css/movies.css';
        wp_enqueue_style('donghua-movies', DONGHUA_THEME_URI . '/assets/css/movies.css', ['donghua-site-system'], file_exists($movie_css_path) ? (string) filemtime($movie_css_path) : DONGHUA_THEME_VERSION);
    }

    // Player & Single Donghua styles — only needed on single donghua and episode pages
    if (is_singular('donghua') || is_singular('movie') || is_singular('episode')) {
        wp_enqueue_style('donghua-player-css', DONGHUA_THEME_URI . '/assets/css/player.css', ['donghua-main-css'], DONGHUA_THEME_VERSION);
        wp_enqueue_style('donghua-watch-tools', DONGHUA_THEME_URI . '/assets/css/watch-tools.css', ['donghua-player-css', 'donghua-refinements'], DONGHUA_THEME_VERSION);
        wp_enqueue_script('donghua-episode-browser', DONGHUA_THEME_URI . '/assets/js/episode-browser.js', [], DONGHUA_THEME_VERSION, true);
    }

    $premium_css_path = get_stylesheet_directory() . '/assets/css/premium.css';
    wp_enqueue_style('donghua-premium', DONGHUA_THEME_URI . '/assets/css/premium.css', ['donghua-branding'], file_exists($premium_css_path) ? (string) filemtime($premium_css_path) : DONGHUA_THEME_VERSION);

    $theme_v3_css_path = get_stylesheet_directory() . '/assets/css/theme-v3.css';
    wp_enqueue_style('donghua-theme-v3', DONGHUA_THEME_URI . '/assets/css/theme-v3.css', ['donghua-premium'], file_exists($theme_v3_css_path) ? (string) filemtime($theme_v3_css_path) : DONGHUA_THEME_VERSION);

    $account_v3_css_path = get_stylesheet_directory() . '/assets/css/theme-v3-account.css';
    wp_enqueue_style(
        'donghua-theme-v3-account',
        DONGHUA_THEME_URI . '/assets/css/theme-v3-account.css',
        ['donghua-theme-v3'],
        file_exists($account_v3_css_path) ? (string) filemtime($account_v3_css_path) : DONGHUA_THEME_VERSION
    );

    if (is_singular('donghua') || is_singular('movie') || is_singular('episode')) {
        $content_v3_css_path = get_stylesheet_directory() . '/assets/css/theme-v3-content.css';
        wp_enqueue_style(
            'donghua-theme-v3-content',
            DONGHUA_THEME_URI . '/assets/css/theme-v3-content.css',
            ['donghua-theme-v3'],
            file_exists($content_v3_css_path) ? (string) filemtime($content_v3_css_path) : DONGHUA_THEME_VERSION
        );
    }

    $community_v3_css_path = get_stylesheet_directory() . '/assets/css/theme-v3-community.css';
    wp_enqueue_style(
        'donghua-theme-v3-community',
        DONGHUA_THEME_URI . '/assets/css/theme-v3-community.css',
        ['donghua-theme-v3-account'],
        file_exists($community_v3_css_path) ? (string) filemtime($community_v3_css_path) : DONGHUA_THEME_VERSION
    );

    $theme_v4_css_path = get_stylesheet_directory() . '/assets/css/theme-v4.css';
    wp_enqueue_style(
        'donghua-theme-v4',
        DONGHUA_THEME_URI . '/assets/css/theme-v4.css',
        ['donghua-theme-v3-community'],
        file_exists($theme_v4_css_path) ? (string) filemtime($theme_v4_css_path) : DONGHUA_THEME_VERSION
    );

    // Final public layout layer. Keep page-specific behavior in its existing stylesheet;
    // this file owns shared sizing and responsive safety rules only.
    $system_css_path = get_stylesheet_directory() . '/assets/css/site-system.css';
    wp_enqueue_style(
        'donghua-site-system',
        DONGHUA_THEME_URI . '/assets/css/site-system.css',
        ['donghua-theme-v4'],
        file_exists($system_css_path) ? (string) filemtime($system_css_path) : DONGHUA_THEME_VERSION
    );

    // Work detail and episode pages share the work's own artwork and accent palette.
    if (is_singular('donghua') || is_singular('movie') || is_singular('episode')) {
        $work_id = (int) get_queried_object_id();
        if (is_singular('episode')) {
            $work_id = (int) get_post_meta($work_id, '_episode_parent_donghua', true);
        }

        $work_post = $work_id > 0 ? get_post($work_id) : null;
        if ($work_post instanceof WP_Post && in_array($work_post->post_type, ['donghua', 'movie'], true)) {
            $work_accent = $work_post->post_type === 'movie'
                ? (string) get_post_meta($work_id, '_movie_accent_color', true)
                : (string) (get_post_meta($work_id, '_donghua_accent_color', true) ?: get_post_meta($work_id, '_donghua_v3_accent', true));
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $work_accent)) {
                $work_accent = '#26b8ad';
            }

            $hex = substr($work_accent, 1);
            $rgb = hexdec(substr($hex, 0, 2)) . ', ' . hexdec(substr($hex, 2, 2)) . ', ' . hexdec(substr($hex, 4, 2));
            $backdrop = $work_post->post_type === 'movie'
                ? (string) get_post_meta($work_id, '_movie_banner_url', true)
                : donghua_get_work_backdrop_url($work_id, 'full');
            if ($backdrop === '') {
                $backdrop = (string) get_the_post_thumbnail_url($work_id, 'full');
            }

            $work_css = ':root{--work-accent:' . $work_accent . ';--work-accent-rgb:' . $rgb . ';';
            if ($backdrop !== '') {
                $safe_backdrop = esc_url_raw($backdrop);
                if ($safe_backdrop !== '') {
                    $work_css .= '--work-backdrop-image:url(' . wp_json_encode($safe_backdrop) . ');';
                }
            }
            $work_css .= '}';
            wp_add_inline_style('donghua-site-system', $work_css);
        }
    }

    // JavaScript
    wp_enqueue_script('donghua-main-js', DONGHUA_THEME_URI . '/assets/js/main.js', $main_js_deps, (string)filemtime(DONGHUA_THEME_DIR . '/assets/js/main.js'), true);
    wp_enqueue_script('donghua-search-js', DONGHUA_THEME_URI . '/assets/js/search.js', ['donghua-main-js'], DONGHUA_THEME_VERSION, true);

    if (is_singular('episode')) {
        $player_js_path = DONGHUA_THEME_DIR . '/assets/js/player.js';
        wp_enqueue_script('donghua-player-js', DONGHUA_THEME_URI . '/assets/js/player.js', ['donghua-main-js'], file_exists($player_js_path) ? (string) filemtime($player_js_path) : DONGHUA_THEME_VERSION, true);
        $progress_path = DONGHUA_THEME_DIR . '/assets/js/watch-progress.js';
        wp_enqueue_script('donghua-watch-progress', DONGHUA_THEME_URI . '/assets/js/watch-progress.js', ['donghua-player-js'], file_exists($progress_path) ? (string)filemtime($progress_path) : DONGHUA_THEME_VERSION, true);
    }

    if (is_singular('donghua') || is_singular('movie') || is_singular('episode')) {
        $rating_path = DONGHUA_THEME_DIR . '/assets/js/rating.js';
        wp_enqueue_script('donghua-rating', DONGHUA_THEME_URI . '/assets/js/rating.js', ['donghua-main-js'], file_exists($rating_path) ? (string) filemtime($rating_path) : DONGHUA_THEME_VERSION, true);
    }

    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }

    if (is_post_type_archive('encyclopedia') || is_tax('encyclopedia_type') || is_singular('encyclopedia') || get_query_var('donghua_encyclopedia')) {
        $encyclopedia_js_path = DONGHUA_THEME_DIR . '/assets/js/encyclopedia.js';
        wp_enqueue_script('donghua-encyclopedia-js', DONGHUA_THEME_URI . '/assets/js/encyclopedia.js', ['donghua-main-js'], file_exists($encyclopedia_js_path) ? (string) filemtime($encyclopedia_js_path) : DONGHUA_THEME_VERSION, true);
    }

    // Localize Data for Fetch/REST calls
    wp_localize_script('donghua-main-js', 'donghuaConfig', [
        'restUrl' => esc_url_raw(rest_url('donghua/v1/')),
        'nonce'   => wp_create_nonce('wp_rest'),
        'ajaxUrl' => esc_url_raw(admin_url('admin-ajax.php')),
        'commentVoteNonce' => wp_create_nonce('donghua_comment_vote'),
        'isLoggedIn' => is_user_logged_in(),
        'rating' => [
            'restUrl' => esc_url_raw(rest_url('donghua/v1/')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'isLoggedIn' => is_user_logged_in(),
        ],
        'imageFallbackUrl' => esc_url_raw(get_theme_file_uri('assets/images/brand/okdonghua-mark.png')),
        'watchProgress' => is_singular('episode') ? [
            'url' => is_user_logged_in() ? esc_url_raw(rest_url('donghua/v1/me/watch-progress')) : '',
            'historyUrl' => esc_url_raw(rest_url('donghua/v1/me/watch-history')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'isLoggedIn' => is_user_logged_in(),
            'episodeId' => is_singular('episode') ? (int)get_queried_object_id() : 0,
            'workId' => is_singular('episode') ? (int)get_post_meta(get_queried_object_id(), '_episode_parent_donghua', true) : 0,
        ] : null,
    ]);
}
add_action('wp_enqueue_scripts', 'donghua_enqueue_assets');

/**
 * Disable wp-emoji script on the frontend (keeps wp-admin emoji working).
 * Saves ~22.5KB of unused JavaScript on every page load.
 */
function donghua_disable_frontend_emoji(): void {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_filter('the_content_feed', 'wp_staticize_emoji');
    remove_filter('comment_text_rss', 'wp_staticize_emoji');
    remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

    // Remove the TinyMCE emoji plugin (only affects frontend, admin has its own hooks)
    add_filter('tiny_mce_plugins', function (array $plugins): array {
        return array_diff($plugins, ['wpemoji']);
    });

    // Remove emoji DNS prefetch
    add_filter('wp_resource_hints', function (array $urls, string $relation_type): array {
        if ($relation_type === 'dns-prefetch') {
            $urls = array_filter($urls, function ($url) {
                return !is_string($url) || strpos($url, 'https://s.w.org/images/core/emoji') === false;
            });
        }
        return $urls;
    }, 10, 2);
}
add_action('init', 'donghua_disable_frontend_emoji');

/** Use WP's primed relationship cache instead of a fresh names-only term query. */
function donghua_cached_term_names(int $post_id, string $taxonomy): array {
    $terms = get_the_terms($post_id, $taxonomy);
    return is_array($terms) ? array_values(wp_list_pluck($terms, 'name')) : [];
}

/** Helper: Get Donghua Score. */
function donghua_get_score(int $post_id): string {
    $score = get_post_meta($post_id, '_donghua_score', true);
    return !empty($score) ? (string)$score : '8.5';
}

/** Add the public genre directory to configured primary menus without duplication. */
function donghua_theme_add_genres_primary_menu_link(string $items, object $args): string {
    if (($args->theme_location ?? '') !== 'primary') return $items;
    $url = home_url('/genres/');
    if (strpos($items, $url) !== false) return $items;
    $active = is_page('genres') || is_tax('genre');
    $class = $active ? 'menu-item menu-item-genres current-menu-item' : 'menu-item menu-item-genres';
    return $items . '<li class="' . esc_attr($class) . '"><a href="' . esc_url($url) . '">التصنيفات</a></li>';
}
add_filter('wp_nav_menu_items', 'donghua_theme_add_genres_primary_menu_link', 10, 2);

/** Add the movie archive to configured primary menus, not only the fallback menu. */
function donghua_theme_add_movies_primary_menu_link(string $items, object $args): string {
    if (($args->theme_location ?? '') !== 'primary') return $items;
    $url = get_post_type_archive_link('movie') ?: home_url('/movies/');
    if (strpos($items, $url) !== false || preg_match('/>\s*أفلام\s*</u', $items)) return $items;
    $active = is_post_type_archive('movie') || is_singular('movie');
    $class = $active ? 'menu-item menu-item-movies current-menu-item' : 'menu-item menu-item-movies';
    return $items . '<li class="' . esc_attr($class) . '"><a href="' . esc_url($url) . '">أفلام</a></li>';
}
add_filter('wp_nav_menu_items', 'donghua_theme_add_movies_primary_menu_link', 11, 2);

function donghua_get_poster_url(int $post_id, string $size = 'donghua-poster'): string {
    $thumbnail = get_the_post_thumbnail_url($post_id, $size);
    if ($thumbnail) {
        return (string) $thumbnail;
    }

    return (string) get_post_meta($post_id, '_donghua_poster_url', true);
}

/**
 * Helper: Get Score Label (مثل أسلوب أوك انمي "مدهش" / "أسطوري")
 */
function donghua_get_score_badge(float|int|string $score): string {
    $score = (float)$score;
    return match (true) {
        $score >= 9.0 => 'أسطوري',
        $score >= 8.0 => 'مدهش',
        $score >= 7.0 => 'جيد جداً',
        default       => 'متوسط',
    };
}

/** Render public aggregate data and a progressively enhanced first-party rating control. */
function donghua_render_rating_widget(int $donghua_id, string $render_mode = 'full'): void {
    if ($donghua_id < 1 || !class_exists('DonghuaCore\\Api\\RatingService')) return;
    $work = get_post($donghua_id);
    $preview_only = $work instanceof WP_Post && $work->post_status !== 'publish' && is_preview() && current_user_can('edit_post', $donghua_id);
    if (!$work || !in_array($work->post_type, ['donghua', 'movie'], true) || ($work->post_status !== 'publish' && !$preview_only) || post_password_required($work)) return;
    $summary = DonghuaCore\Api\RatingService::get_summary($donghua_id);
    $count = (int) $summary['rating_count'];
    $average = (float) $summary['rating_average'];
    $site_score_raw = get_post_meta($donghua_id, $work->post_type === 'movie' ? '_movie_score' : '_donghua_score', true);
    $site_score = is_numeric($site_score_raw) ? (float) $site_score_raw : null;
    if ($site_score !== null && ($site_score < 0 || $site_score > 10)) $site_score = null;
    $accessible_summary = $count > 0
        ? sprintf('متوسط التقييم %s من 10', number_format_i18n($average, 1))
        : 'لا توجد تقييمات بعد';
    $login_url = add_query_arg('redirect_to', get_permalink($donghua_id), home_url('/login/'));
    ?>
    <section class="okd-rating-widget" data-rating-layout="<?php echo esc_attr($render_mode); ?>" data-donghua-id="<?php echo esc_attr((string) $donghua_id); ?>"<?php echo $preview_only ? ' data-preview-only="true"' : ''; ?> aria-label="تقييمات المستخدمين">
        <?php if ($render_mode !== 'audience'): ?>
        <div class="okd-rating-site-summary" aria-label="تقييم المواقع">
            <span class="okd-rating-summary-label">تقييم المواقع</span>
            <?php if ($site_score !== null && $site_score > 0): ?>
                <strong class="okd-rating-site-average" dir="ltr"><?php echo esc_html(number_format_i18n($site_score, 1)); ?></strong>
                <span class="okd-rating-out-of" dir="ltr">/ 10</span>
                <span class="okd-rating-star" aria-hidden="true">★</span>
            <?php else: ?>
                <span class="okd-rating-empty">غير متاح</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($render_mode !== 'site'): ?>
        <div class="okd-rating-summary" aria-live="polite" aria-label="<?php echo esc_attr($accessible_summary); ?>">
            <span class="okd-rating-summary-label"><?php echo $render_mode === 'audience' ? 'التقييم' : 'تقييم المشاهدين'; ?></span>
            <strong class="okd-rating-average" data-rating-average dir="ltr" <?php echo $count > 0 ? '' : 'hidden'; ?>><?php echo esc_html(number_format($average, 1, '.', '')); ?></strong>
            <span class="okd-rating-out-of" dir="ltr" <?php echo $count > 0 ? '' : 'hidden'; ?>>/ 10</span>
            <span class="okd-rating-stars-display" data-rating-visual aria-hidden="true">
                <?php for ($star_index = 1; $star_index <= 5; $star_index++):
                    $star_fill = max(0, min(100, (($average - (($star_index - 1) * 2)) / 2) * 100)); ?>
                    <span class="okd-rating-average-star" data-average-star="<?php echo esc_attr((string) $star_index); ?>" style="--rating-fill:<?php echo esc_attr((string) $star_fill); ?>%">★</span>
                <?php endfor; ?>
            </span>
            <?php if ($count < 1): ?>
                <span class="okd-rating-empty" data-rating-empty>لا توجد تقييمات بعد</span>
            <?php endif; ?>
            <?php if ($count > 0): ?>
                <span class="okd-rating-count" data-rating-count><?php echo esc_html(number_format_i18n($count)); ?> تقييم</span>
            <?php else: ?>
                <span class="okd-rating-count" data-rating-count hidden>0 تقييم</span>
            <?php endif; ?>
        </div>
        <?php if ($preview_only): ?>
            <p class="okd-rating-status">سيظهر التقييم التفاعلي بعد نشر الفيلم.</p>
        <?php else: ?><details class="okd-rating-picker">
            <summary class="okd-rating-trigger"><span class="okd-rating-trigger-stars" data-user-rating-visual aria-hidden="true">
                <?php for ($star_index = 1; $star_index <= 5; $star_index++): ?><span data-user-star="<?php echo esc_attr((string) $star_index); ?>" style="--rating-fill:0%">★</span><?php endfor; ?>
            </span><span data-rating-trigger-text>أضف تقييمك</span></summary>
            <div class="okd-rating-panel">
                <div class="okd-rating-panel-heading"><strong>تقييمك للعمل</strong><button type="button" class="okd-rating-close" data-rating-close aria-label="إغلاق التقييم">×</button></div>
                <?php if (is_user_logged_in()): ?>
                    <p class="okd-rating-hint">اختر من نجمة إلى عشر نجوم لحفظ تقييمك مباشرة</p>
                    <p class="okd-user-rating" data-user-rating hidden></p>
                    <div class="okd-rating-scale" role="group" aria-label="اختر تقييمًا من 1 إلى 10">
                        <?php for ($rating_value = 1; $rating_value <= 10; $rating_value++): ?>
                            <button type="button" class="okd-rating-choice" data-rating-value="<?php echo esc_attr((string) $rating_value); ?>" style="--rating-index:<?php echo esc_attr((string) $rating_value); ?>" aria-label="<?php echo esc_attr(sprintf('قيّم هذا العمل %d من 10 نجوم', $rating_value)); ?>" title="<?php echo esc_attr(sprintf('%d من 10', $rating_value)); ?>" aria-pressed="false">★</button>
                        <?php endfor; ?>
                    </div>
                    <p class="okd-rating-status" data-rating-status role="status" aria-live="polite"></p>
                <?php else: ?>
                    <p class="okd-rating-login-copy">سجّل الدخول لإضافة تقييمك</p>
                    <a class="okd-rating-login" href="<?php echo esc_url($login_url); ?>">تسجيل الدخول</a>
                <?php endif; ?>
            </div>
        </details><?php endif; ?>
        <?php endif; ?>
    </section>
    <?php
}

/**
 * Custom Pagination with Okanime styling & Persistent Filter Query State
 */
function donghua_pagination(): void {
    $add_args = [];
    foreach (['status', 'type', 'genre', 'sort', 'orderby', 's'] as $key) {
        if (!empty($_GET[$key])) {
            $add_args[$key] = sanitize_text_field(wp_unslash($_GET[$key]));
        }
    }
    the_posts_pagination([
        'mid_size'  => 2,
        'prev_text' => __('&lsaquo; السابق', 'donghua-theme'),
        'next_text' => __('التالي &rsaquo;', 'donghua-theme'),
        'add_args'  => $add_args,
    ]);
}

/**
 * Cached Query Helper using dedicated Donghua transient keys
 */
function donghua_cached_query(string $cache_key, array $query_args): WP_Query {
    $cached_post_ids = get_transient($cache_key);
    if (false === $cached_post_ids) {
        $args = array_merge($query_args, [
            'post_status'            => 'publish',
            'has_password'           => false,
            'fields'                 => 'ids',
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ]);
        $q = new WP_Query($args);
        $cached_post_ids = $q->posts;
        set_transient($cache_key, $cached_post_ids, 2 * HOUR_IN_SECONDS);
    }

    if (empty($cached_post_ids)) {
        return new WP_Query(['post__in' => [0]]);
    }

    return new WP_Query([
        'post_type'              => $query_args['post_type'] ?? 'any',
        // Cached IDs are not proof that a post is still publicly visible.
        'post_status'            => 'publish',
        'has_password'           => false,
        'post__in'               => $cached_post_ids,
        'orderby'                => 'post__in',
        'posts_per_page'         => count($cached_post_ids),
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    ]);
}

/** Current user's small, private homepage resume rail; shares the account progress service. */
function donghua_theme_home_continue_watching_items(): array {
    if (!is_user_logged_in() || get_current_user_id() < 1 || !class_exists('\\DonghuaCore\\Api\\WatchProgress')) return [];
    $count = donghua_theme_home_section_count('continue_watching');
    $page = \DonghuaCore\Api\WatchProgress::page(get_current_user_id(), 1, $count, true);
    return is_array($page['items'] ?? null) ? $page['items'] : [];
}

/**
 * ── Security Headers (Defensive Security) ──────────────────────────────────
 */
function donghua_security_headers(): void {
    if (headers_sent()) {
        return;
    }

    $existing_headers = headers_list();
    $sent_map = [];
    foreach ($existing_headers as $h) {
        $parts = explode(':', $h, 2);
        if (isset($parts[0])) {
            $sent_map[strtolower(trim($parts[0]))] = true;
        }
    }

    if (!isset($sent_map['x-content-type-options'])) {
        header('X-Content-Type-Options: nosniff');
    }

    if (!isset($sent_map['x-frame-options'])) {
        header('X-Frame-Options: SAMEORIGIN');
    }

    if (!isset($sent_map['referrer-policy'])) {
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    if (!isset($sent_map['permissions-policy'])) {
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    // CSP Report-Only enabled for policy discovery; enforcement not yet enabled.
    if (!isset($sent_map['content-security-policy-report-only']) && !isset($sent_map['content-security-policy'])) {
        header("Content-Security-Policy-Report-Only: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data: blob:; img-src 'self' https: data: blob:; media-src 'self' https: blob:; frame-src 'self' https:;");
    }

    // HSTS: strictly only for secure production https, never localhost
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (is_ssl() && !in_array($host, ['localhost', '127.0.0.1'], true) && !isset($sent_map['strict-transport-security'])) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
add_action('send_headers', 'donghua_security_headers');

/**
 * ── Modern Comment Callback ────────────────────────────────────────────────
 */
function donghua_comment_callback($comment, $args, $depth): void {
    $GLOBALS['comment'] = $comment;
    $comment_id = (int)$comment->comment_ID;
    $user_id    = (int)$comment->user_id;
    $is_admin   = $user_id > 0 && user_can($user_id, 'manage_options');
    $is_author  = $user_id > 0 && $user_id === (int)get_the_author_meta('ID');
    $vote_up    = (int) get_comment_meta($comment_id, '_dh_comment_votes_up', true);
    $vote_down  = (int) get_comment_meta($comment_id, '_dh_comment_votes_down', true);
    $vote_score = $vote_up - $vote_down;
    $current_user_id = get_current_user_id();
    $current_vote = $current_user_id > 0
        ? (string) get_comment_meta($comment_id, '_dh_comment_user_vote_' . $current_user_id, true)
        : '';
    $is_demo_comment = get_comment_meta($comment_id, 'okdonghua_demo', true) === '1';
    ?>
    <li <?php comment_class('dh-comment-item'); ?> id="comment-<?php echo $comment_id; ?>" data-comment-time="<?php echo esc_attr((string) get_comment_time('U', true, false, $comment)); ?>" data-comment-score="<?php echo esc_attr((string) $vote_score); ?>">
        <article class="dh-comment-card" id="div-comment-<?php echo $comment_id; ?>">
            <?php if ('1' === (string) $comment->comment_approved): ?>
                <?php if ($current_user_id > 0): ?>
                    <div class="dh-comment-votes" role="group" aria-label="<?php esc_attr_e('التصويت على التعليق', 'donghua-theme'); ?>">
                        <button type="button" class="dh-comment-vote dh-comment-vote--up<?php echo $current_vote === '1' ? ' is-selected' : ''; ?>" data-dh-comment-vote="1" data-comment-id="<?php echo esc_attr((string) $comment_id); ?>" aria-label="<?php esc_attr_e('تصويت إيجابي', 'donghua-theme'); ?>" aria-pressed="<?php echo $current_vote === '1' ? 'true' : 'false'; ?>">
                            <span class="dh-vote-symbol" aria-hidden="true">↑</span>
                        </button>
                        <span class="dh-vote-score" data-vote-score aria-live="polite"><?php echo esc_html(number_format_i18n($vote_score)); ?></span>
                        <button type="button" class="dh-comment-vote dh-comment-vote--down<?php echo $current_vote === '-1' ? ' is-selected' : ''; ?>" data-dh-comment-vote="-1" data-comment-id="<?php echo esc_attr((string) $comment_id); ?>" aria-label="<?php esc_attr_e('تصويت سلبي', 'donghua-theme'); ?>" aria-pressed="<?php echo $current_vote === '-1' ? 'true' : 'false'; ?>">
                            <span class="dh-vote-symbol" aria-hidden="true">↓</span>
                        </button>
                        <span class="screen-reader-text" data-vote-count="up"><?php echo esc_html((string) $vote_up); ?></span>
                        <span class="screen-reader-text" data-vote-count="down"><?php echo esc_html((string) $vote_down); ?></span>
                    </div>
                <?php else: ?>
                    <a class="dh-comment-vote-login" href="<?php echo esc_url(wp_login_url(get_permalink((int)$comment->comment_post_ID))); ?>"><?php esc_html_e('سجّل الدخول للتصويت', 'donghua-theme'); ?></a>
                <?php endif; ?>
            <?php endif; ?>
            <div class="dh-comment-author-wrap">
                <?php echo get_avatar($comment, 36, '', get_comment_author($comment), ['class' => 'dh-comment-avatar']); ?>
                <div class="dh-comment-content-wrap">
                    <header class="dh-comment-header">
                        <span class="dh-comment-author-name">
                            <?php comment_author_link($comment); ?>
                            <?php if ($is_demo_comment): ?><span class="dh-demo-comment-badge"><?php esc_html_e('تعليق تجريبي', 'donghua-theme'); ?></span><?php endif; ?>
                            <?php if ($is_admin): ?>
                                <span class="dh-role-badge dh-role-admin">فريق الموقع</span>
                            <?php elseif ($is_author): ?>
                                <span class="dh-role-badge dh-role-admin">الكاتب</span>
                            <?php elseif ($user_id > 0): ?>
                                <span class="dh-role-badge dh-role-member">عضو</span>
                            <?php endif; ?>
                            <?php if ($user_id > 0 && class_exists('DHM_Badges')): ?><?php echo DHM_Badges::html($user_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php endif; ?>
                        </span>
                        <time class="dh-comment-date" datetime="<?php comment_time('c'); ?>">
                            <?php echo esc_html(human_time_diff(get_comment_time('U'), current_time('timestamp')) . ' مضت'); ?>
                        </time>
                        <?php if (current_user_can('edit_comment', $comment_id)): ?>
                            <span class="dh-comment-admin-actions">
                        <?php edit_comment_link(
                            donghua_icon('edit', '', 13) . ' ' . esc_html__('تعديل', 'donghua-theme'),
                            '<span class="dh-comment-edit-wrap">',
                            '</span>'
                        ); ?>
                            </span>
                        <?php endif; ?>
                    </header>

                    <?php if ('0' === $comment->comment_approved): ?>
                        <em class="dh-comment-moderation">
                            <?php esc_html_e('بانتظار المراجعة', 'donghua-theme'); ?>
                        </em>
                    <?php endif; ?>

                    <div class="dh-comment-body">
                        <?php comment_text(); ?>
                    </div>

                    <div class="dh-comment-actions">
                        <?php
                        comment_reply_link(array_merge($args, [
                    'depth'     => $depth,
                    'max_depth' => $args['max_depth'],
                    'reply_text'=> donghua_icon('chat', '', 13) . ' ' . esc_html__('رد', 'donghua-theme'),
                    'add_below' => 'div-comment',
                        ]));
                        ?>
                        <button type="button" class="dh-comment-share" data-comment-share="<?php echo esc_url(get_comment_link($comment)); ?>">
                            <?php echo donghua_icon('share', '', 13); ?> <span><?php esc_html_e('مشاركة', 'donghua-theme'); ?></span>
                        </button>
                        <?php if ($current_user_id > 0 && '1' === (string) $comment->comment_approved): ?>
                            <details class="dh-comment-report">
                                <summary><?php esc_html_e('الإبلاغ', 'donghua-theme'); ?></summary>
                                <form data-dh-qa-report>
                                    <input type="hidden" name="comment_id" value="<?php echo esc_attr((string) $comment_id); ?>">
                                    <input type="hidden" name="nonce" value="<?php echo esc_attr(wp_create_nonce('dh_qa_comment_report')); ?>">
                                    <label class="screen-reader-text" for="dh-report-reason-<?php echo esc_attr((string) $comment_id); ?>"><?php esc_html_e('سبب البلاغ', 'donghua-theme'); ?></label>
                                    <select id="dh-report-reason-<?php echo esc_attr((string) $comment_id); ?>" name="reason" required>
                                        <option value=""><?php esc_html_e('اختر سببًا', 'donghua-theme'); ?></option>
                                        <option value="abuse"><?php esc_html_e('إساءة', 'donghua-theme'); ?></option>
                                        <option value="spam"><?php esc_html_e('سبام', 'donghua-theme'); ?></option>
                                        <option value="spoilers"><?php esc_html_e('حرق أحداث', 'donghua-theme'); ?></option>
                                        <option value="inappropriate"><?php esc_html_e('محتوى غير مناسب', 'donghua-theme'); ?></option>
                                        <option value="suspicious_link"><?php esc_html_e('رابط مشبوه', 'donghua-theme'); ?></option>
                                        <option value="other"><?php esc_html_e('سبب آخر', 'donghua-theme'); ?></option>
                                    </select>
                                    <button type="submit"><?php esc_html_e('إرسال', 'donghua-theme'); ?></button>
                                    <span role="status" aria-live="polite"></span>
                                </form>
                            </details>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </article>
    <?php
}

/**
 * ── Server-Side Comment Validation ─────────────────────────────────────────
 * Strictly enforce 1000 character limit and prevent empty comments server-side.
 */
function donghua_validate_comment_data(array $commentdata): array {
    $content = $commentdata['comment_content'] ?? '';
    $trimmed = trim($content);

    if ($trimmed === '') {
        wp_die(
            esc_html__('خطأ: لا يمكن إرسال تعليق فارغ.', 'donghua-theme'),
            esc_html__('خطأ في التعليق', 'donghua-theme'),
            ['response' => 400, 'back_link' => true]
        );
    }

    if (mb_strlen($trimmed, 'UTF-8') > 1000) {
        wp_die(
            esc_html__('خطأ: تجاوز التعليق الحد الأقصى المسموح به وهو 1000 حرف.', 'donghua-theme'),
            esc_html__('خطأ في التعليق', 'donghua-theme'),
            ['response' => 400, 'back_link' => true]
        );
    }

    return $commentdata;
}
add_filter('preprocess_comment', 'donghua_validate_comment_data');

/** Apply the same empty/length limits to comments created through the REST API. */
function donghua_validate_rest_comment_data($prepared_comment, $request) {
    unset($request);
    if (!is_array($prepared_comment)) {
        return $prepared_comment;
    }

    $content = $prepared_comment['comment_content'] ?? '';
    $trimmed = is_string($content) ? trim($content) : '';
    if ($trimmed === '') {
        return new WP_Error('rest_comment_content_invalid', __('محتوى التعليق غير صالح.', 'donghua-theme'), ['status' => 400]);
    }
    if (mb_strlen($trimmed, 'UTF-8') > 1000) {
        return new WP_Error('rest_comment_content_too_long', __('تجاوز التعليق الحد الأقصى المسموح به وهو 1000 حرف.', 'donghua-theme'), ['status' => 400]);
    }

    return $prepared_comment;
}
add_filter('rest_pre_insert_comment', 'donghua_validate_rest_comment_data', 10, 2);

/** Store one authenticated user's vote and rebuild counters while the comment row is locked. */
function donghua_update_comment_vote_atomically(int $comment_id, int $user_id, string $vote) {
    global $wpdb;
    if ($comment_id < 1 || $user_id < 1 || !in_array($vote, ['1', '-1', '0'], true)) {
        return new WP_Error('invalid_vote', __('تعذر تسجيل التصويت.', 'donghua-theme'));
    }

    $comments = $wpdb->comments;
    $commentmeta = $wpdb->commentmeta;
    if ($wpdb->query('START TRANSACTION') === false) {
        return new WP_Error('vote_storage', __('تعذر تسجيل التصويت الآن.', 'donghua-theme'));
    }

    $locked_comment = $wpdb->get_var($wpdb->prepare(
        "SELECT comment_ID FROM {$comments} WHERE comment_ID = %d AND comment_approved = '1' FOR UPDATE",
        $comment_id
    ));
    if ((int) $locked_comment !== $comment_id) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('comment_unavailable', __('هذا التعليق غير متاح للتصويت.', 'donghua-theme'));
    }

    $vote_meta = '_dh_comment_user_vote_' . $user_id;
    $vote_rows = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_id, meta_value FROM {$commentmeta} WHERE comment_id = %d AND meta_key = %s ORDER BY meta_id ASC FOR UPDATE",
        $comment_id,
        $vote_meta
    ), ARRAY_A);
    if (!is_array($vote_rows)) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('vote_storage', __('تعذر قراءة التصويت الآن.', 'donghua-theme'));
    }
    $previous = isset($vote_rows[0]['meta_value']) && in_array((string) $vote_rows[0]['meta_value'], ['1', '-1'], true)
        ? (string) $vote_rows[0]['meta_value']
        : '';

    $store_meta = static function (string $key, ?string $value) use ($wpdb, $commentmeta, $comment_id): bool {
        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT meta_id FROM {$commentmeta} WHERE comment_id = %d AND meta_key = %s ORDER BY meta_id ASC FOR UPDATE",
            $comment_id,
            $key
        ));
        if ($rows === null) return false;
        if ($value === null) {
            return $wpdb->query($wpdb->prepare(
                "DELETE FROM {$commentmeta} WHERE comment_id = %d AND meta_key = %s",
                $comment_id,
                $key
            )) !== false;
        }
        if ($rows) {
            $ok = $wpdb->update($commentmeta, ['meta_value' => $value], ['meta_id' => (int) $rows[0]], ['%s'], ['%d']);
            if ($ok === false) return false;
            if (count($rows) > 1) {
                $extra = array_map('intval', array_slice($rows, 1));
                $ids = implode(',', $extra);
                if ($wpdb->query("DELETE FROM {$commentmeta} WHERE meta_id IN ({$ids})") === false) return false;
            }
            return true;
        }
        return $wpdb->insert($commentmeta, [
            'comment_id' => $comment_id,
            'meta_key' => $key,
            'meta_value' => $value,
        ], ['%d', '%s', '%s']) !== false;
    };

    $vote_saved = $vote === '0'
        ? $store_meta($vote_meta, null)
        : $store_meta($vote_meta, $vote);
    $marker_saved = true;
    if ($vote === '0') {
        $marker_saved = $store_meta('_okdonghua_demo_vote_' . $user_id, null);
    } else {
        $qa_batch = sanitize_key((string) get_user_meta($user_id, 'okdonghua_demo_batch', true));
        if ($qa_batch !== '') $marker_saved = $store_meta('_okdonghua_demo_vote_' . $user_id, $qa_batch);
    }
    if (!$vote_saved || !$marker_saved) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('vote_storage', __('تعذر حفظ التصويت الآن.', 'donghua-theme'));
    }

    $totals = $wpdb->get_row($wpdb->prepare(
        "SELECT COALESCE(SUM(meta_value = '1'), 0) AS up_count, COALESCE(SUM(meta_value = '-1'), 0) AS down_count FROM {$commentmeta} WHERE comment_id = %d AND meta_key REGEXP %s AND meta_value IN ('1', '-1')",
        $comment_id,
        '^_dh_comment_user_vote_[0-9]+$'
    ), ARRAY_A);
    if (!is_array($totals)) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('vote_storage', __('تعذر تحديث إجمالي التصويت الآن.', 'donghua-theme'));
    }
    $up = (int) $totals['up_count'];
    $down = (int) $totals['down_count'];
    if (!$store_meta('_dh_comment_votes_up', (string) $up)
        || !$store_meta('_dh_comment_votes_down', (string) $down)
        || $wpdb->query('COMMIT') === false) {
        $wpdb->query('ROLLBACK');
        return new WP_Error('vote_storage', __('تعذر تحديث إجمالي التصويت الآن.', 'donghua-theme'));
    }

    clean_comment_cache($comment_id);
    wp_cache_delete($comment_id, 'comment_meta');
    return ['up' => $up, 'down' => $down, 'selected' => $vote, 'previous' => $previous];
}

/** Store one authenticated user's current vote per approved comment. */
function donghua_ajax_vote_comment(): void {
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        wp_send_json_error(['message' => __('التصويت يتطلب طلب POST.', 'donghua-theme')], 405);
    }
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('سجّل الدخول للتصويت.', 'donghua-theme')], 401);
    }
    check_ajax_referer('donghua_comment_vote', 'nonce');

    $raw_comment_id = isset($_POST['comment_id']) && is_scalar($_POST['comment_id']) ? trim((string)wp_unslash($_POST['comment_id'])) : '';
    $comment_id = preg_match('/^[1-9][0-9]{0,18}$/', $raw_comment_id) ? (int)$raw_comment_id : 0;
    $vote = isset($_POST['vote']) && is_scalar($_POST['vote']) ? sanitize_text_field(wp_unslash((string)$_POST['vote'])) : '';
    if (!$comment_id || !in_array($vote, ['1', '-1', '0'], true)) {
        wp_send_json_error(['message' => __('تعذر تسجيل التصويت.', 'donghua-theme')], 400);
    }

    $result = donghua_update_comment_vote_atomically($comment_id, get_current_user_id(), $vote);
    if (is_wp_error($result)) {
        $status = $result->get_error_code() === 'comment_unavailable' ? 404 : 409;
        wp_send_json_error(['message' => $result->get_error_message()], $status);
    }
    unset($result['previous']);
    wp_send_json_success($result);
}
add_action('wp_ajax_donghua_comment_vote', 'donghua_ajax_vote_comment');

/**
 * ── Font Preconnect Hints ──────────────────────────────────────────────────
 * Pre-establish TCP/TLS connections to fonts.googleapis.com and fonts.gstatic.com
 * Eliminates ~160ms connection delay before font file downloads begin.
 */
function donghua_font_resource_hints(): void {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}
add_action('wp_head', 'donghua_font_resource_hints', 1);

/**
 * Helper: Optimize image URLs for responsive delivery.
 * Downscales TMDB /original/ images to /w1280/ saving up to 90% bandwidth.
 */
function donghua_optimize_image_url(mixed $url): string {
    if (empty($url) || !is_string($url)) {
        return '';
    }
    if (str_contains($url, 'image.tmdb.org/t/p/original/')) {
        return str_replace('/t/p/original/', '/t/p/w1280/', $url);
    }
    return $url;
}

/**
 * ── LCP Image Preload Optimization ─────────────────────────────────────────
 * Preload critical above-the-fold hero and flagship images with fetchpriority="high".
 */
function donghua_lcp_preload(): void {
    // 1. Homepage Hero Slider first slide image (matches front-page.php query)
    if (is_front_page()) {
        $hero_query = donghua_cached_query('dh_cache_hero_slider', [
            'post_type'      => 'donghua',
            'posts_per_page' => 6,
            'meta_key'       => '_donghua_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
        ]);
        if ($hero_query && $hero_query->have_posts()) {
            $h_id = $hero_query->posts[0]->ID;
            $banner_url = donghua_get_work_backdrop_url((int)$h_id);
            $poster_url = get_the_post_thumbnail_url($h_id, 'donghua-poster');
            $img = donghua_optimize_image_url($banner_url ?: $poster_url);
            if ($img) {
                echo '<link rel="preload" as="image" href="' . esc_url($img) . '" fetchpriority="high">' . "\n";
            }
        }
    }

    // 2. Single Donghua Banner
    if (is_singular('donghua')) {
        $id = get_the_ID();
        $banner_url = donghua_get_work_backdrop_url($id);
        if ($banner_url) {
            echo '<link rel="preload" as="image" href="' . esc_url($banner_url) . '" fetchpriority="high">' . "\n";
        }
    }
}
add_action('wp_head', 'donghua_lcp_preload', 2);
 
/**
 * Composable Browse Filters for Donghua Archive & Catalog
 */
function donghua_filter_archive_query(\WP_Query $query): void {
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_post_type_archive('donghua') && !$query->is_tax(['status', 'animation_type', 'genre', 'year', 'studio', 'work_format'])) {
        return;
    }

    $tax_query = (array) $query->get('tax_query');

    // 1. Status Filter (ongoing / completed)
    if (!empty($_GET['status'])) {
        $status = sanitize_text_field(wp_unslash($_GET['status']));
        if ($status !== 'all' && in_array($status, ['ongoing', 'completed'], true)) {
            $tax_query[] = [
                'taxonomy' => 'status',
                'field'    => 'slug',
                'terms'    => $status,
            ];
        }
    }

    // 2. Animation Type Filter (3d / 2d)
    if (!empty($_GET['type'])) {
        $type = sanitize_text_field(wp_unslash($_GET['type']));
        if ($type !== 'all' && in_array($type, ['3d', '2d'], true)) {
            $tax_query[] = [
                'taxonomy' => 'animation_type',
                'field'    => 'slug',
                'terms'    => $type,
            ];
        }
    }

    // 3. Genre Filter
    if (!empty($_GET['genre'])) {
        $genre = sanitize_title(wp_unslash($_GET['genre']));
        if ($genre !== 'all' && !empty($genre)) {
            $tax_query[] = [
                'taxonomy' => 'genre',
                'field'    => 'slug',
                'terms'    => $genre,
            ];
        }
    }

    if (count($tax_query) > 1) {
        $tax_query['relation'] = 'AND';
    }
    if (!empty($tax_query)) {
        $query->set('tax_query', $tax_query);
    }

    // 4. Sort / Orderby
    $sort = !empty($_GET['sort']) ? sanitize_text_field(wp_unslash($_GET['sort'])) : (!empty($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'latest');

    if ($sort === 'score' || $sort === 'rating') {
        $query->set('meta_key', '_donghua_score');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'DESC');
    } elseif ($sort === 'title') {
        $query->set('orderby', 'title');
        $query->set('order', 'ASC');
    } elseif ($sort === 'random') {
        $query->set('orderby', 'rand');
    } else { // latest / date
        $query->set('orderby', 'date');
        $query->set('order', 'DESC');
    }
}
add_action('pre_get_posts', 'donghua_filter_archive_query');

/** Include localized/alternate catalog titles in the public site search. */
function donghua_search_alternate_titles(string $search, WP_Query $query): string {
    if (is_admin() || !$query->is_search() || !$query->is_main_query() || $search === '') {
        return $search;
    }

    global $wpdb;

    // WordPress appends this guard to anonymous searches. Keep it outside both
    // branches where it is present so a matching protected post cannot bypass it.
    $password_guard = " AND ({$wpdb->posts}.post_password = '')";
    $trimmed_search = rtrim($search);
    $has_password_guard = str_ends_with($trimmed_search, $password_guard);
    $title_search = $has_password_guard
        ? substr($trimmed_search, 0, -strlen($password_guard))
        : $trimmed_search;
    $title_search = preg_replace('/^\s*AND\s+/i', '', $title_search, 1);
    if (!is_string($title_search) || $title_search === '') {
        return $search;
    }

    $terms = $query->get('search_terms');
    if (!is_array($terms) || !$terms) {
        $terms = preg_split('/\s+/u', trim((string) $query->get('s')), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }
    if (!$terms) {
        return $search;
    }

    $meta_keys = [
        '_donghua_ar_name', '_donghua_en_name', '_donghua_zh_name',
        '_donghua_name_ar', '_donghua_name_en', '_donghua_name_cn', '_donghua_alt_names',
        '_movie_ar_name', '_movie_en_name', '_movie_zh_name', '_movie_alt_names',
        '_episode_title_ar', '_episode_title_en', '_episode_title_zh',
    ];
    $key_placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));
    $post_types = ['donghua', 'movie', 'episode'];
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
    $meta_terms = [];

    foreach ($terms as $term) {
        $term = trim((string) $term);
        if ($term === '') {
            continue;
        }

        $like = '%' . $wpdb->esc_like($term) . '%';
        $sql = "EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS dh_search_meta
            WHERE dh_search_meta.post_id = {$wpdb->posts}.ID
              AND dh_search_meta.meta_key IN ({$key_placeholders})
              AND dh_search_meta.meta_value LIKE %s)";
        $meta_terms[] = $wpdb->prepare($sql, ...array_merge($meta_keys, [$like]));
    }

    if (!$meta_terms) {
        return $search;
    }

    $meta_match = "({$wpdb->posts}.post_type IN ({$type_placeholders}) AND " . implode(' AND ', $meta_terms) . ')';
    $meta_match = $wpdb->prepare($meta_match, ...$post_types);

    return ' AND ((' . $title_search . ') OR ' . $meta_match . ')' . ($has_password_guard ? $password_guard : '');
}
add_filter('posts_search', 'donghua_search_alternate_titles', 20, 2);
