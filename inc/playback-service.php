<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * Convert public Google Drive file edit/view links to Drive's iframe-safe preview
 * endpoint. This is an output-only normalization: saved server data and Google
 * sharing permissions remain unchanged.
 */
function donghua_normalize_playback_url(string $url): string {
    $url = trim($url);
    if ($url === '') return '';

    $parts = wp_parse_url(str_starts_with($url, '//') ? 'https:' . $url : $url);
    if (!is_array($parts) || strtolower((string) ($parts['host'] ?? '')) !== 'drive.google.com') {
        return $url;
    }

    $path = (string) ($parts['path'] ?? '');
    if (preg_match('#^/file/(?:u/\d+/)?d/([a-zA-Z0-9_-]+)/(?:edit|view|preview)/?#', $path, $match)) {
        return 'https://drive.google.com/file/d/' . rawurlencode($match[1]) . '/preview';
    }

    if ($path === '/open') {
        parse_str((string) ($parts['query'] ?? ''), $query);
        $file_id = isset($query['id']) && is_string($query['id']) ? $query['id'] : '';
        if (preg_match('/^[a-zA-Z0-9_-]+$/', $file_id)) {
            return 'https://drive.google.com/file/d/' . rawurlencode($file_id) . '/preview';
        }
    }

    return $url;
}

/** Provider contract: keep provider credentials server-side; return only a current viewer source. */
interface Donghua_Playback_Provider {
    public function sources_for_episode(int $episode_id,int $viewer_user_id): array;
}

/** Default adapter for the existing public server metadata. */
final class Donghua_Legacy_Playback_Provider implements Donghua_Playback_Provider {
    public function sources_for_episode(int $episode_id,int $viewer_user_id): array {
        $servers=get_post_meta($episode_id,'_episode_watch_servers',true);
        return is_array($servers)?array_values(array_filter($servers,static fn($server)=>is_array($server)&&(!isset($server['enabled'])||(int)$server['enabled']===1))):[];
    }
}

/** Theme integration depends on this provider boundary, not a media vendor or credential format. */
final class Donghua_Playback_Service {
    public static function can_view_episode(int $id): bool {
        $post=get_post($id);
        if (!$post || $post->post_type!=='episode') return false;
        if (current_user_can('manage_options') && current_user_can('edit_post',$id)) return true;
        if ($post->post_status!=='publish' || post_password_required($post)) return false;
        $parent=get_post((int)get_post_meta($id,'_episode_parent_donghua',true));
        return $parent && $parent->post_type==='donghua' && $parent->post_status==='publish' && !post_password_required($parent);
    }
    public static function quality(array $source): string {
        $quality=strtolower(trim((string)($source['quality']??'')));
        if ($quality==='4k') $quality='2160p';
        return in_array($quality,['360p','480p','720p','1080p','2160p'],true)?$quality:'';
    }
    public static function allowed(array $source,int $viewer): bool {
        if ($viewer!==get_current_user_id()) return false;
        $access=\DonghuaCore\Meta\EpisodeMeta::source_access($source);
        // Quality is source-dependent, not a membership gate. Explicit private tags and legacy Premium-only tags remain protected until sources are classified as public or private.
        if ($access==='public') return true;
        if ($access!=='private' || $viewer<1) return false;
        return self::has_entitlement($viewer,'premium_max_access') && self::has_entitlement($viewer,'premium_server_access');
    }
    private static function has_entitlement(int $viewer,string $key): bool {
        if (!class_exists('DHM_Entitlements')) return false;
        return DHM_Entitlements::user_has($viewer,$key);
    }
    private static function available(int $episode_id,int $viewer): array {
        if ($viewer!==get_current_user_id() || !self::can_view_episode($episode_id)) return [];
        $provider=apply_filters('donghua_playback_provider',null,$episode_id,$viewer);
        if (!$provider instanceof Donghua_Playback_Provider) $provider=new Donghua_Legacy_Playback_Provider();
        $sources=$provider->sources_for_episode($episode_id,$viewer);
        $sources=array_values(array_filter($sources,static function($source):bool {
            if (!is_array($source) || !isset($source['url']) || !is_string($source['url']) || isset($source['enabled']) && (int)$source['enabled']!==1) return false;
            $parts=wp_parse_url(str_starts_with($source['url'],'//')?'https:'.$source['url']:$source['url']);
            return is_array($parts) && in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true) && !empty($parts['host']) && !isset($parts['user']) && !isset($parts['pass']);
        }));
        foreach ($sources as &$source) $source['url']=donghua_normalize_playback_url($source['url']);
        unset($source);
        return $sources;
    }
    public static function sources_for_episode(int $episode_id,int $viewer_user_id=0): array {
        $result=[];
        foreach (self::available($episode_id,$viewer_user_id) as $source) {
            if (!self::allowed($source,$viewer_user_id)) continue;
            if (\DonghuaCore\Meta\EpisodeMeta::source_access($source)==='private') {
                $source=class_exists('DHM_Private_Media')?DHM_Private_Media::playable($episode_id,$source,$viewer_user_id):null;
                if (!$source) continue;
            }
            $result[]=$source;
        }
        return $result;
    }
    /** Only explicit direct-file variants qualify; iframe resolutions and HLS masters are not guessed. */
    public static function quality_choices(int $episode_id,int $viewer): array {
        $choices=[];
        foreach (self::available($episode_id,$viewer) as $source) {
            $quality=self::quality($source);$type=strtolower((string)($source['type']??''));
            if ($quality==='' || !in_array($type,['mp4','direct','m4v','webm'],true) || preg_match('/\.m3u8(?:[?#]|$)/i',$source['url'])) continue;
            $allowed=self::allowed($source,$viewer) && (\DonghuaCore\Meta\EpisodeMeta::source_access($source)==='public' || class_exists('DHM_Private_Media') && DHM_Private_Media::ready());
            if (!isset($choices[$quality]) || $allowed) $choices[$quality]=['label'=>$quality==='2160p'?'4K':$quality,'locked'=>!$allowed];
        }
        uksort($choices,static fn($a,$b)=>(int)$a<=>(int)$b);
        return $choices;
    }
    public static function downloads_for_episode(int $episode_id,int $viewer): array {
        if ($viewer!==get_current_user_id() || !self::can_view_episode($episode_id)) return [];
        $sources=get_post_meta($episode_id,'_episode_download_servers',true);
        // Private download issuance needs its own stored-source contract; never
        // release a raw private download merely because the viewer is entitled.
        return is_array($sources)?array_values(array_filter($sources,static fn($source)=>is_array($source) && \DonghuaCore\Meta\EpisodeMeta::source_access($source)==='public' && self::allowed($source,$viewer))):[];
    }
}
