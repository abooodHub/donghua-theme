<?php
declare(strict_types=1);

get_header();
the_post();
$movie_id = get_the_ID();
$title = (string) (get_post_meta($movie_id, '_movie_ar_name', true) ?: get_the_title());
$english = (string) get_post_meta($movie_id, '_movie_en_name', true);
$chinese = (string) get_post_meta($movie_id, '_movie_zh_name', true);
$duration = (int) get_post_meta($movie_id, '_movie_duration', true);
$backdrop = (string) get_post_meta($movie_id, '_movie_banner_url', true);
$poster_id = get_post_thumbnail_id($movie_id);
$poster = $poster_id ? wp_get_attachment_image_url($poster_id, 'donghua-poster') : '';
$poster = $poster ?: ($backdrop ?: '');
$backdrop = $backdrop ?: $poster;
$trailer = (string) get_post_meta($movie_id, '_movie_trailer_url', true);
$imdb_id = (string) get_post_meta($movie_id, '_movie_imdb_id', true);
$douban_id = (string) get_post_meta($movie_id, '_movie_douban_id', true);
$years = get_the_terms($movie_id, 'year');
$year = !empty($years) && !is_wp_error($years) ? $years[0]->name : '';
$genres = get_the_terms($movie_id, 'genre');
$studios = get_the_terms($movie_id, 'studio');
$watch_servers = get_post_meta($movie_id, '_movie_watch_servers', true);
$watch_servers = is_array($watch_servers) ? array_values(array_filter($watch_servers, static function ($row): bool {
    if (!is_array($row) || empty($row['url']) || (isset($row['enabled']) && (int)$row['enabled'] !== 1)) return false;
    $path = (string) wp_parse_url((string)$row['url'], PHP_URL_PATH);
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    // Large MKV/archive links are downloads, not embeddable watch sources.
    if (in_array($extension, ['mkv','avi','mov','wmv','zip','rar','7z'], true)) return false;
    return true;
})) : [];
foreach ($watch_servers as &$watch_server) {
    $path = (string) wp_parse_url((string)$watch_server['url'], PHP_URL_PATH);
    $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    if ($extension === 'mp4') $watch_server['type'] = 'mp4';
    if ($extension === 'm3u8') $watch_server['type'] = 'hls';
}
unset($watch_server);
usort($watch_servers, static fn($a, $b) => (int)($a['priority'] ?? 999) <=> (int)($b['priority'] ?? 999));
$download_servers = get_post_meta($movie_id, '_movie_download_servers', true);
$download_servers = is_array($download_servers) ? array_values(array_filter($download_servers, static fn($row) => is_array($row) && !empty($row['url']))) : [];
$synopsis = trim(wp_strip_all_tags(get_the_excerpt() ?: get_the_content()));
$synopsis_preview = wp_trim_words($synopsis, 34, '…');
$synopsis_is_long = count(preg_split('/\s+/u', $synopsis, -1, PREG_SPLIT_NO_EMPTY) ?: []) > 34;
$related = new WP_Query(['post_type' => 'movie', 'post_status' => 'publish', 'posts_per_page' => 7, 'post__not_in' => [$movie_id], 'orderby' => 'date', 'order' => 'DESC']);
?>
<div class="donghua-single-page movie-single-page" data-movie-id="<?php echo esc_attr((string)$movie_id); ?>">
    <div class="site-container single-donghua-hero-container">
        <header class="single-donghua-hero"<?php if ($backdrop): ?> style="background-image:url('<?php echo esc_url($backdrop); ?>')"<?php endif; ?> aria-label="<?php echo esc_attr($title); ?>">
            <div class="single-hero-scrim" aria-hidden="true"></div>
            <div class="single-hero-content-wrap">
                <div class="single-hero-text-col">
                    <div class="single-hero-badges-row"><span class="movie-eyebrow">فيلم دونغهوا</span><?php if (function_exists('donghua_render_rating_widget')) donghua_render_rating_widget((int)$movie_id); ?></div>
                    <h1 class="single-hero-title"><?php echo esc_html($title); ?></h1>
                    <?php if ($english || $chinese): ?><div class="single-hero-subtitles"><?php if ($english): ?><span class="sub-en" lang="en" dir="ltr"><?php echo esc_html($english); ?></span><?php endif; ?><?php if ($english && $chinese): ?><span class="sub-sep">•</span><?php endif; ?><?php if ($chinese): ?><span class="sub-zh" lang="zh" dir="ltr"><?php echo esc_html($chinese); ?></span><?php endif; ?></div><?php endif; ?>
                    <div class="work-facts-rail" aria-label="معلومات الفيلم"><dl>
                        <?php if ($year): ?><div><dt>السنة</dt><dd><?php echo esc_html($year); ?></dd></div><?php endif; ?>
                        <?php if ($duration): ?><div><dt>المدة</dt><dd><?php echo esc_html((string)$duration); ?> دقيقة</dd></div><?php endif; ?>
                        <?php if ($genres && !is_wp_error($genres)): ?><div class="work-facts-genres"><dt>التصنيفات</dt><dd><?php foreach ($genres as $genre): ?><a href="<?php echo esc_url(get_term_link($genre)); ?>"><?php echo esc_html($genre->name); ?></a><?php endforeach; ?></dd></div><?php endif; ?>
                        <?php if ($studios && !is_wp_error($studios)): ?><div><dt>الاستوديو</dt><dd><?php echo esc_html(implode('، ', wp_list_pluck($studios, 'name'))); ?></dd></div><?php endif; ?>
                    </dl></div>
                    <?php if ($synopsis): ?><?php if ($synopsis_is_long): ?><details class="single-hero-synopsis single-hero-synopsis-disclosure"><summary><span class="work-synopsis-preview"><?php echo esc_html($synopsis_preview); ?></span><span class="work-synopsis-toggle">قراءة الملخص كاملًا</span></summary><p class="work-synopsis-full"><?php echo esc_html($synopsis); ?></p></details><?php else: ?><p class="single-hero-synopsis"><?php echo esc_html($synopsis); ?></p><?php endif; ?><?php endif; ?>
                    <div class="single-hero-actions-row">
                        <a class="btn-single-hero-primary" href="#movie-watch"><span class="btn-icon">▶</span><span>مشاهدة الفيلم</span></a>
                        <?php if ($trailer): ?><a class="btn-single-hero-secondary" href="<?php echo esc_url($trailer); ?>" target="_blank" rel="noopener noreferrer">مشاهدة الإعلان</a><?php endif; ?>
                        <?php if ($imdb_id): ?><a class="btn-single-hero-secondary" href="https://www.imdb.com/title/<?php echo esc_attr($imdb_id); ?>/" target="_blank" rel="noopener noreferrer">IMDb</a><?php endif; ?>
                        <?php if ($douban_id): ?><a class="btn-single-hero-secondary" href="https://movie.douban.com/subject/<?php echo esc_attr($douban_id); ?>/" target="_blank" rel="noopener noreferrer">Douban</a><?php endif; ?>
                    </div>
                </div>
                <div class="single-hero-poster-col"><div class="single-hero-poster-frame"><?php if ($poster): ?><img class="single-hero-poster-img" src="<?php echo esc_url($poster); ?>" alt="<?php echo esc_attr($title); ?>" width="235" height="352" fetchpriority="high"><?php else: ?><div class="single-hero-poster-placeholder">▶</div><?php endif; ?></div></div>
            </div>
        </header>
    </div>

    <div class="site-container single-donghua-body movie-single-body">
        <nav class="work-section-nav" aria-label="أقسام الفيلم">
            <a href="#movie-about">حول الفيلم</a>
            <a href="#movie-watch">المشاهدة</a>
            <?php if ($download_servers): ?><a href="#movie-downloads">التحميل</a><?php endif; ?>
            <?php if ($related->have_posts()): ?><a href="#movie-related">أفلام مشابهة</a><?php endif; ?>
            <a href="#movie-comments">التعليقات</a>
        </nav>

        <?php $first_server = $watch_servers[0] ?? null; ?>
        <section class="movie-section movie-playback" id="movie-watch" aria-labelledby="movie-watch-title"><div class="portal-section-header"><div class="header-title-box"><span class="header-accent-dot" aria-hidden="true"></span><h2 class="portal-section-title" id="movie-watch-title">مشاهدة الفيلم</h2></div></div>
            <div class="movie-player" id="movie-player" style="--movie-preview:<?php echo $poster ? 'url(' . esc_url($poster) . ')' : 'none'; ?>">
                <?php if ($watch_servers): ?><div class="movie-player-preview"><button type="button" class="movie-start-play" aria-label="ابدأ مشاهدة الفيلم">▶</button><strong>جاهز للمشاهدة</strong><span>اختر سيرفر المشاهدة ثم اضغط تشغيل</span></div><?php else: ?><div class="movie-player-empty"><span aria-hidden="true">▶</span><strong>مشغل الفيلم</strong><p>لا يوجد رابط مشاهدة مضاف لهذا الفيلم بعد.</p><small>أضف رابط تضمين أو MP4 أو HLS في لوحة التحرير ضمن «سيرفرات مشاهدة الفيلم».</small></div><?php endif; ?>
            </div>
            <?php if ($watch_servers): ?><div class="movie-watch-servers" role="group" aria-label="اختر سيرفر المشاهدة"><?php foreach ($watch_servers as $index => $server): ?><button type="button" class="movie-watch-server<?php echo $index === 0 ? ' is-active' : ''; ?>" data-url="<?php echo esc_url((string)$server['url']); ?>" data-type="<?php echo esc_attr((string)($server['type'] ?? 'embed')); ?>" aria-pressed="<?php echo $index === 0 ? 'true' : 'false'; ?>"><?php echo esc_html((string)($server['name'] ?? 'سيرفر ' . ($index + 1))); ?><?php if (!empty($server['quality'])): ?><small><?php echo esc_html((string)$server['quality']); ?></small><?php endif; ?></button><?php endforeach; ?></div>
            <?php $first_watch_url = (string)($first_server['url'] ?? ''); ?><a class="movie-open-server" data-movie-open-server href="<?php echo esc_url($first_watch_url); ?>" target="_blank" rel="noopener noreferrer">فتح السيرفر في نافذة جديدة ↗</a><?php endif; ?>
        </section>

        <section class="watch-downloads-section movie-downloads" id="movie-downloads" aria-labelledby="movie-download-title">
            <h2 class="downloads-section-title" id="movie-download-title"><span class="downloads-heading-icon" aria-hidden="true"><?php echo function_exists('donghua_icon') ? donghua_icon('download', '', 17) : '↓'; ?></span>سيرفرات التحميل</h2>
            <?php if ($download_servers): ?><div class="watch-download-items">
                <?php foreach ($download_servers as $server):
                    $download_quality = strtolower((string) ($server['quality'] ?? ''));
                    $download_quality_label = in_array($download_quality, ['2160p', '4k'], true) ? '4K / 2160p' : ($download_quality ?: 'جودة عالية');
                    $download_subtitle_label = ['Softsub' => 'ترجمة قابلة للتبديل', 'Hardsub' => 'ترجمة مدمجة', 'Raw' => 'بدون ترجمة'][(string) ($server['sub_type'] ?? '')] ?? '';
                    $download_details = array_filter([$download_quality_label, strtoupper((string) ($server['codec'] ?? 'H264')), $download_subtitle_label, (string) ($server['size'] ?? '')]);
                    $download_identity = strtolower((string) ($server['name'] ?? '') . ' ' . (string) wp_parse_url((string) ($server['url'] ?? ''), PHP_URL_HOST));
                    $download_provider = 'generic';
                    if (strpos($download_identity, 'drive') !== false || strpos($download_identity, 'google') !== false) $download_provider = 'drive';
                    elseif (strpos($download_identity, 'gofile') !== false) $download_provider = 'gofile';
                    elseif (strpos($download_identity, 'mega') !== false) $download_provider = 'mega';
                    $download_icons = [
                        'drive' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="m8.3 3 4.1 7.1h8.2L16.5 3z"/><path fill="#34A853" d="m4.2 10.1 4.1 7.1 4.1-7.1L8.3 3z"/><path fill="#FBBC04" d="m8.3 17.2-2.4 4.1h8.2l2.4-4.1z"/><path fill="#EA4335" d="m12.4 10.1-4.1 7.1h8.2l4.1-7.1z"/></svg>',
                        'gofile' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.5 21 7.7v8.6l-9 5.2-9-5.2V7.7l9-5.2Z" fill="#35a66f"/><path d="M16.7 9.2a5.6 5.6 0 1 0 .7 5.2h-2.6a3.1 3.1 0 1 1-.6-3.4l-1.7 1.7h5.6V7.1l-1.4 2.1Z" fill="#fff"/></svg>',
                        'mega' => '<span aria-hidden="true">M</span>',
                        'generic' => function_exists('donghua_icon') ? donghua_icon('download', 'server-provider-generic-icon', 16) : '↓',
                    ];
                ?>
                <a class="watch-download-item" href="<?php echo esc_url((string) $server['url']); ?>" target="_blank" rel="nofollow noopener noreferrer" title="تحميل الفيلم">
                    <span class="download-item-icon download-provider-icon provider-<?php echo esc_attr($download_provider); ?>" aria-hidden="true"><?php echo $download_icons[$download_provider]; ?></span>
                    <span class="btn-hub-download-copy"><strong><?php echo esc_html((string) ($server['name'] ?? 'تحميل الفيلم')); ?></strong><small><?php echo esc_html(implode(' · ', $download_details)); ?></small></span>
                    <span class="movie-download-arrow" aria-hidden="true">تحميل ←</span>
                </a>
                <?php endforeach; ?>
            </div><?php else: ?><p class="watch-download-empty">لا توجد روابط تحميل متاحة لهذا الفيلم حاليًا.</p><?php endif; ?>
        </section>

        <section class="movie-section movie-synopsis" id="movie-about"><div class="portal-section-header"><div class="header-title-box"><span class="header-accent-dot" aria-hidden="true"></span><h2 class="portal-section-title">حول الفيلم</h2></div></div><div class="movie-prose"><?php echo wp_kses_post(get_the_excerpt() ?: apply_filters('the_content', get_the_content())); ?></div></section>
        <?php if ($related->have_posts()): ?><section class="portal-section section-similar movie-related" id="movie-related" aria-label="أفلام مشابهة"><div class="portal-section-header"><div class="header-title-box"><span class="header-accent-dot" aria-hidden="true"></span><h2 class="portal-section-title">أفلام قد تعجبك</h2></div></div><div class="movie-grid movie-grid-related"><?php while ($related->have_posts()): $related->the_post(); get_template_part('template-parts/movie-card'); endwhile; ?></div></section><?php endif; wp_reset_postdata(); ?>
        <?php if (comments_open() || get_comments_number()): ?><section class="portal-section section-comments movie-comments" id="movie-comments" aria-label="التعليقات والمناقشات"><?php comments_template(); ?></section><?php endif; ?>
    </div>
</div>
<script>
document.addEventListener('click',function(event){var server=event.target.closest('.movie-watch-server');var start=event.target.closest('.movie-start-play');if(!server&&!start)return;var active=server||document.querySelector('.movie-watch-server.is-active');if(!active)return;var player=document.getElementById('movie-player');if(!player)return;if(server){document.querySelectorAll('.movie-watch-server').forEach(function(item){var selected=item===server;item.classList.toggle('is-active',selected);item.setAttribute('aria-pressed',selected?'true':'false');});var openLink=document.querySelector('[data-movie-open-server]');if(openLink)openLink.href=server.dataset.url||'';return;}var url=active.dataset.url||'';var type=active.dataset.type||'embed';var tag=(type==='mp4'||type==='hls')?'video':'iframe';var media=document.createElement(tag);if(tag==='video'){media.controls=true;media.playsInline=true;media.preload='metadata';}else{media.title='مشاهدة الفيلم';media.allow='autoplay; fullscreen; picture-in-picture';media.allowFullscreen=true;media.referrerPolicy='strict-origin-when-cross-origin';}media.src=url;player.replaceChildren(media);});
</script>
<?php get_footer();
