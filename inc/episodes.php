<?php
declare(strict_types=1);
defined('ABSPATH') || exit;

/** Public navigation must never include draft, scheduled or password-protected episodes. */
function donghua_public_episodes(int $parent_id): array {
    if ($parent_id < 1) return [];
    $episodes = get_posts([
        'post_type' => 'episode', 'post_status' => 'publish', 'has_password' => false,
        'posts_per_page' => -1, 'meta_key' => '_episode_parent_donghua', 'meta_value' => $parent_id,
    ]);
    $numbers = [];
    foreach ($episodes as $episode) {
        $number = get_post_meta($episode->ID, '_episode_sort_number', true);
        $numbers[$episode->ID] = (float)($number !== '' ? $number : get_post_meta($episode->ID, '_episode_number', true));
    }
    usort($episodes, static fn($a, $b) => ($numbers[$a->ID] <=> $numbers[$b->ID]) ?: ($a->ID <=> $b->ID));
    return $episodes;
}

function donghua_episode_label($number): string {
    return is_numeric($number) && (float)$number === (float)(int)$number
        ? sprintf('%02d', (int)$number) : (string)$number;
}

function donghua_episode_toolbar(string $target): void { ?>
    <div class="episode-browser-tools" data-episode-toolbar="<?php echo esc_attr($target); ?>" hidden>
        <label>ابحث عن حلقة<input type="search" placeholder="رقم الحلقة أو عنوانها" data-episode-query aria-controls="<?php echo esc_attr($target); ?>" /></label>
        <label>الترتيب<select data-episode-order aria-controls="<?php echo esc_attr($target); ?>"><option value="asc">من الأولى للأخيرة</option><option value="desc">من الأخيرة للأولى</option></select></label>
        <span data-episode-count role="status" aria-live="polite"></span>
    </div>
<?php }

/**
 * Check if a URL is a direct media file playable in external players like VLC.
 */
function donghua_is_direct_media_url(?string $url): bool {
    if (empty($url)) return false;
    return (bool) preg_match('/\.(mp4|m4v|mkv|m3u8|webm)(\?.*)?$/i', $url);
}

/**
 * A server explicitly configured as a direct media type is a direct source,
 * even when its URL does not expose a file extension.
 */
function donghua_is_direct_media_server(array $server): bool {
    $type = strtolower(trim((string) ($server['type'] ?? 'embed')));

    return in_array($type, ['direct', 'mp4', 'm4v', 'mkv', 'webm', 'hls'], true)
        || donghua_is_direct_media_url($server['url'] ?? '');
}

/**
 * Return neutral, factual server labels based strictly on priority and type.
 *
 * @param array $server Server data array
 * @param int $index 0-based server index
 * @param int $total Total servers count
 * @return array{text: string, type_label: string, class: string, is_recommended: bool, is_direct: bool}
 */
function donghua_get_server_badge(array $server, int $index = 0, int $total = 1): array {
    $priority = (int) ($server['priority'] ?? ($index + 1));
    $type     = strtolower(trim((string)($server['type'] ?? 'embed')));
    $url      = $server['url'] ?? '';
    $is_direct = donghua_is_direct_media_server($server);

    if ($priority === 1 || $index === 0) {
        $text = 'السيرفر الرئيسي';
        $class = 'srv-badge-primary';
        $is_recommended = true;
    } else {
        $text = 'سيرفر بديل';
        $class = 'srv-badge-alt';
        $is_recommended = false;
    }

    $type_label = $is_direct ? 'ملف مباشر' : ($type === 'direct' ? 'رابط مباشر' : 'مشغل مدمج');

    return [
        'text'           => $text,
        'type_label'     => $type_label,
        'class'          => $class,
        'is_recommended' => $is_recommended,
        'is_direct'      => $is_direct,
    ];
}

/**
 * Extract factual media data for an episode without guessing or hardcoding.
 *
 * @param int $episode_id Episode post ID
 * @param array $watch_servers Watch servers array
 * @param array $download_servers Download servers array
 * @return array
 */
function donghua_get_episode_media_meta(int $episode_id, array $watch_servers = [], array $download_servers = []): array {
    $editions   = [];
    $qualities  = [];
    $codecs     = [];
    $containers = [];
    $direct_urls = [];

    // 1. Check download servers for explicit metadata
    foreach ($download_servers as $dl) {
        if (!empty($dl['sub_type'])) {
            $key = strtolower(trim((string)$dl['sub_type']));
            $editions[$key] = trim((string)$dl['sub_type']);
        }
        if (!empty($dl['quality'])) {
            $key = strtolower(trim((string)$dl['quality']));
            $qualities[$key] = trim((string)$dl['quality']);
        }
        if (!empty($dl['codec'])) {
            $key = strtoupper(trim((string)$dl['codec']));
            $codecs[$key] = trim((string)$dl['codec']);
        }
        if (!empty($dl['url']) && donghua_is_direct_media_url($dl['url'])) {
            $direct_urls[] = $dl['url'];
            if (preg_match('/\.([a-z0-9]+)(\?.*)?$/i', $dl['url'], $m)) {
                $containers[strtoupper($m[1])] = strtoupper($m[1]);
            }
        }
    }

    // 2. Check watch servers for direct media URLs and filename hints
    foreach ($watch_servers as $srv) {
        $url = $srv['url'] ?? '';
        if (donghua_is_direct_media_server($srv)) {
            $direct_urls[] = $url;
            if (preg_match('/\.([a-z0-9]+)(\?.*)?$/i', $url, $m)) {
                $containers[strtoupper($m[1])] = strtoupper($m[1]);
            }
            // If filename has quality hints like 4K, 1080p, 720p, extract only if none exists
            if (empty($qualities)) {
                if (preg_match('/\b(4k|2160p)\b/i', $url)) $qualities['4k'] = '4K';
                elseif (preg_match('/\b(1080p|fhd)\b/i', $url)) $qualities['1080p'] = '1080p';
                elseif (preg_match('/\b(720p|hd)\b/i', $url)) $qualities['720p'] = '720p';
                elseif (preg_match('/\b(480p|sd)\b/i', $url)) $qualities['480p'] = '480p';
            }
            // If filename has codec hints
            if (empty($codecs)) {
                if (preg_match('/\b(hevc|x265|h265)\b/i', $url)) $codecs['HEVC'] = 'HEVC';
                elseif (preg_match('/\b(avc|x264|h264)\b/i', $url)) $codecs['H264'] = 'H264';
            }
        }
    }

    return [
        'editions'        => array_values($editions),
        'qualities'       => array_values($qualities),
        'codecs'          => array_values($codecs),
        'containers'      => array_values($containers),
        'direct_urls'     => array_unique($direct_urls),
        'has_direct_url'  => !empty($direct_urls),
        'has_downloads'   => !empty($download_servers),
    ];
}

