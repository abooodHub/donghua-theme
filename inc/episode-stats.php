<?php
declare(strict_types=1);

/**
 * Episode Statistics & Live Viewers Module
 *
 * Provides:
 * 1. Atomic Total Views Counter with 30-Minute Debounce Lock (Post Meta + Object Cache Invalidation)
 * 2. Real-Time Unique Live Viewers Tracker with 90s TTL (Multi-Tab Aware, Unique Viewer Per Episode)
 * 3. Lightweight, Secure Public REST API Endpoints with Rate Limiting (15s Window)
 * 4. Modular Storage Abstraction (Transients / Persistent Object Cache / Redis-Ready)
 *
 * @package DonghuaTheme
 */

defined('ABSPATH') || exit;

class Donghua_Episode_Stats {

    /** TTL in seconds for a viewer to be considered live / online */
    public const LIVE_TTL = 90;

    /** Window in seconds for debouncing duplicate view counts per viewer */
    public const VIEW_DEBOUNCE_WINDOW = 1800; // 30 minutes

    /** Minimum seconds between heartbeats for rate limiting */
    public const RATE_LIMIT_SECONDS = 15;

    /**
     * Bootstraps the module
     */
    public static function init(): void {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Registers lightweight REST API routes
     */
    public static function register_routes(): void {
        // Heartbeat endpoint (POST)
        register_rest_route('donghua/v1', '/episodes/(?P<id>\d+)/heartbeat', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'handle_heartbeat'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => static fn($param) => is_numeric($param) && (int)$param > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Leave / sendBeacon endpoint (POST)
        register_rest_route('donghua/v1', '/episodes/(?P<id>\d+)/leave', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'handle_leave'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => static fn($param) => is_numeric($param) && (int)$param > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // Donghua work stats endpoint (GET)
        register_rest_route('donghua/v1', '/donghua/(?P<id>\d+)/stats', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'handle_donghua_stats'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'validate_callback' => static fn($param) => is_numeric($param) && (int)$param > 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Validates that the requested ID is an existing, published donghua
     */
    private static function is_valid_donghua(int $donghua_id): bool {
        if ($donghua_id <= 0) {
            return false;
        }
        $post = get_post($donghua_id);
        return $post !== null 
            && $post->post_type === 'donghua' 
            && $post->post_status === 'publish';
    }

    /**
     * Validates that the requested ID is an existing, published episode
     */
    private static function is_valid_episode(int $episode_id): bool {
        if ($episode_id <= 0) {
            return false;
        }
        $post = get_post($episode_id);
        return $post !== null 
            && $post->post_type === 'episode' 
            && $post->post_status === 'publish';
    }

    /**
     * Sanitizes and validates client identifier tokens (viewer_id and tab_id)
     */
    private static function sanitize_token(?string $token): ?string {
        if (!is_string($token)) {
            return null;
        }
        $token = trim($token);
        if (preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $token)) {
            return $token;
        }
        return null;
    }

    /**
     * Handles heartbeat requests
     */
    public static function handle_heartbeat(\WP_REST_Request $request): \WP_REST_Response {
        $episode_id = (int) $request->get_param('id');
        if (!self::is_valid_episode($episode_id)) {
            return new \WP_REST_Response(['error' => 'Episode not found'], 404);
        }

        $params    = $request->get_json_params() ?: $request->get_params();
        $viewer_id = self::sanitize_token($params['viewer_id'] ?? null);
        $tab_id    = self::sanitize_token($params['tab_id'] ?? null) ?: ($viewer_id ?: 'tab_default');

        if (!$viewer_id) {
            return new \WP_REST_Response(['error' => 'Invalid viewer identity'], 400);
        }

        // Rate limiting per viewer tab (15 seconds)
        $rl_key = 'dh_rl_' . $episode_id . '_' . substr(md5($viewer_id . '_' . $tab_id), 0, 16);
        $is_throttled = (bool) get_transient($rl_key);
        $parent_id    = (int) get_post_meta($episode_id, '_episode_parent_donghua', true);

        if (!$is_throttled) {
            set_transient($rl_key, 1, self::RATE_LIMIT_SECONDS);

            // Record atomic view increment if eligible (debounced for 30 minutes)
            self::record_atomic_view($episode_id, $viewer_id);

            // Touch viewer in episode live registry
            $live_count = self::touch_viewer($episode_id, $viewer_id, $tab_id);

            // Touch viewer in parent Donghua aggregated live registry
            if ($parent_id > 0) {
                self::touch_donghua_viewer($parent_id, $episode_id, $viewer_id, $tab_id);
            }
        } else {
            // Throttled: Fetch current live count without mutating store
            $live_count = self::get_live_count($episode_id);
        }

        $total_views = self::get_total_views($episode_id);

        return new \WP_REST_Response([
            'live'  => max(1, $live_count),
            'views' => $total_views,
        ], 200);
    }

    /**
     * Handles leave / beforeunload beacon requests
     */
    public static function handle_leave(\WP_REST_Request $request): \WP_REST_Response {
        $episode_id = (int) $request->get_param('id');
        if (!self::is_valid_episode($episode_id)) {
            return new \WP_REST_Response(['error' => 'Episode not found'], 404);
        }

        $params    = $request->get_json_params() ?: $request->get_params();
        $viewer_id = self::sanitize_token($params['viewer_id'] ?? null);
        $tab_id    = self::sanitize_token($params['tab_id'] ?? null) ?: ($viewer_id ?: 'tab_default');

        if ($viewer_id) {
            self::remove_viewer($episode_id, $viewer_id, $tab_id);
            $parent_id = (int) get_post_meta($episode_id, '_episode_parent_donghua', true);
            if ($parent_id > 0) {
                self::remove_donghua_viewer($parent_id, $episode_id, $viewer_id, $tab_id);
            }
        }

        return new \WP_REST_Response([
            'live'  => self::get_live_count($episode_id),
            'views' => self::get_total_views($episode_id),
        ], 200);
    }

    /**
     * Handles Donghua work stats request (GET)
     * Does NOT register any live viewer or heartbeat! Strictly read-only.
     */
    public static function handle_donghua_stats(\WP_REST_Request $request): \WP_REST_Response {
        $donghua_id = (int) $request->get_param('id');
        if (!self::is_valid_donghua($donghua_id)) {
            return new \WP_REST_Response(['error' => 'Donghua not found'], 404);
        }

        return new \WP_REST_Response([
            'live'  => self::get_donghua_live_count($donghua_id),
            'views' => self::get_donghua_views($donghua_id),
        ], 200);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Storage Abstraction Layer (Transient / Object Cache / Redis-Ready)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Gets total views count for an episode
     */
    public static function get_total_views(int $episode_id): int {
        return (int) get_post_meta($episode_id, '_episode_views', true);
    }

    /**
     * Atomically increments total views for an episode with a 30-minute lock per viewer
     */
    public static function record_atomic_view(int $episode_id, string $viewer_id): bool {
        $lock_hash = substr(md5($viewer_id), 0, 16);
        $lock_key  = 'dh_vlock_' . $episode_id . '_' . $lock_hash;

        // Check if user already viewed this episode within the debounce window
        if (get_transient($lock_key)) {
            return false;
        }

        // Set lock for 30 minutes (1800s)
        set_transient($lock_key, 1, self::VIEW_DEBOUNCE_WINDOW);

        global $wpdb;

        // Atomic SQL Increment on wp_postmeta
        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->postmeta} 
                 SET meta_value = CAST(meta_value AS UNSIGNED) + 1 
                 WHERE post_id = %d AND meta_key = '_episode_views'",
                $episode_id
            )
        );

        if ($updated === 0) {
            // Protect first insert against race conditions with lightweight named lock
            $lock_name = 'dh_meta_lock_' . $episode_id;
            $locked = (int) $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock_name));
            try {
                // Re-attempt UPDATE inside lock in case a concurrent thread already inserted it
                $retry_update = $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$wpdb->postmeta} 
                         SET meta_value = CAST(meta_value AS UNSIGNED) + 1 
                         WHERE post_id = %d AND meta_key = '_episode_views'",
                        $episode_id
                    )
                );
                if ($retry_update === 0) {
                    add_post_meta($episode_id, '_episode_views', 1, true);
                }
            } finally {
                if ($locked === 1) {
                    $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
                }
            }
        }

        // Invalidate WordPress Object Cache so get_post_meta reads fresh value
        clean_post_cache($episode_id);
        wp_cache_delete($episode_id, 'post_meta');

        // Increment parent Donghua total views as well if parent exists
        $parent_id = (int) get_post_meta($episode_id, '_episode_parent_donghua', true);
        if ($parent_id > 0) {
            $parent_updated = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->postmeta} 
                     SET meta_value = CAST(meta_value AS UNSIGNED) + 1 
                     WHERE post_id = %d AND meta_key = '_donghua_views'",
                    $parent_id
                )
            );
            if ($parent_updated === 0) {
                add_post_meta($parent_id, '_donghua_views', 1, true);
            }
            clean_post_cache($parent_id);
            wp_cache_delete($parent_id, 'post_meta');
        }

        return true;
    }

    /**
     * Retrieves active viewers array from transient/cache
     *
     * Format:
     * [
     *   viewer_id => [
     *     'tabs' => [ tab_id => timestamp ],
     *     'last_seen' => timestamp
     *   ]
     * ]
     */
    public static function get_live_viewers(int $episode_id): array {
        $transient_key = 'dh_live_ep_' . $episode_id;
        $viewers = get_transient($transient_key);
        return is_array($viewers) ? $viewers : [];
    }

    /**
     * Returns the number of unique active viewers
     */
    public static function get_live_count(int $episode_id): int {
        $viewers = self::get_live_viewers($episode_id);
        $cutoff  = time() - self::LIVE_TTL;
        $count   = 0;

        foreach ($viewers as $data) {
            if (is_array($data) && ($data['last_seen'] ?? 0) >= $cutoff) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Touches a viewer's session/tab and performs pruning of stale viewers (>90s)
     * Protected by MySQL named lock to guarantee zero race conditions on concurrent heartbeats.
     *
     * @return int Number of unique active live viewers after update
     */
    public static function touch_viewer(int $episode_id, string $viewer_id, string $tab_id): int {
        global $wpdb;
        $lock_name = 'dh_live_lock_' . $episode_id;
        $locked    = (int) $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock_name));

        try {
            $transient_key = 'dh_live_ep_' . $episode_id;
            $viewers       = self::get_live_viewers($episode_id);
            $now           = time();
            $cutoff        = $now - self::LIVE_TTL;
            $cleaned       = [];

            // 1. Prune expired viewers and expired tabs (> 90s)
            foreach ($viewers as $vid => $data) {
                if (!is_array($data)) {
                    continue;
                }
                $active_tabs = [];
                if (!empty($data['tabs']) && is_array($data['tabs'])) {
                    foreach ($data['tabs'] as $tid => $ts) {
                        if ($ts >= $cutoff) {
                            $active_tabs[$tid] = $ts;
                        }
                    }
                }

                $last_seen = !empty($active_tabs) ? max($active_tabs) : (int)($data['last_seen'] ?? 0);
                if ($last_seen >= $cutoff) {
                    $cleaned[$vid] = [
                        'tabs'      => $active_tabs,
                        'last_seen' => $last_seen,
                    ];
                }
            }

            // 2. Insert or update the current viewer and tab
            if (!isset($cleaned[$viewer_id])) {
                $cleaned[$viewer_id] = [
                    'tabs'      => [],
                    'last_seen' => $now,
                ];
            }

            $cleaned[$viewer_id]['tabs'][$tab_id] = $now;
            $cleaned[$viewer_id]['last_seen']      = $now;

            // 3. Save transient (TTL 180s so abandoned records auto-prune from options table)
            set_transient($transient_key, $cleaned, 180);

            return count($cleaned);
        } finally {
            if ($locked === 1) {
                $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            }
        }
    }

    /**
     * Removes a specific tab for a viewer. If no tabs remain, removes the viewer.
     *
     * @return int Number of unique active live viewers after removal
     */
    public static function remove_viewer(int $episode_id, string $viewer_id, string $tab_id): int {
        global $wpdb;
        $lock_name = 'dh_live_lock_' . $episode_id;
        $locked    = (int) $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock_name));

        try {
            $transient_key = 'dh_live_ep_' . $episode_id;
            $viewers       = self::get_live_viewers($episode_id);
            $cutoff        = time() - self::LIVE_TTL;
            $cleaned       = [];

            foreach ($viewers as $vid => $data) {
                if (!is_array($data)) {
                    continue;
                }

                if ($vid === $viewer_id) {
                    // Remove the departing tab
                    unset($data['tabs'][$tab_id]);
                }

                $active_tabs = [];
                if (!empty($data['tabs']) && is_array($data['tabs'])) {
                    foreach ($data['tabs'] as $tid => $ts) {
                        if ($ts >= $cutoff) {
                            $active_tabs[$tid] = $ts;
                        }
                    }
                }

                if (!empty($active_tabs)) {
                    $cleaned[$vid] = [
                        'tabs'      => $active_tabs,
                        'last_seen' => max($active_tabs),
                    ];
                }
            }

            set_transient($transient_key, $cleaned, 180);

            return count($cleaned);
        } finally {
            if ($locked === 1) {
                $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Donghua Level Aggregated Storage Abstraction
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Gets total aggregated views count for a Donghua
     */
    public static function get_donghua_views(int $donghua_id): int {
        return (int) get_post_meta($donghua_id, '_donghua_views', true);
    }

    /**
     * Retrieves active viewers array for a Donghua from transient/cache
     * Format:
     * [
     *   viewer_id => [
     *     'episodes' => [ episode_id => [ tab_id => timestamp ] ],
     *     'last_seen' => timestamp
     *   ]
     * ]
     */
    public static function get_donghua_live_viewers(int $donghua_id): array {
        $transient_key = 'dh_live_donghua_' . $donghua_id;
        $viewers = get_transient($transient_key);
        return is_array($viewers) ? $viewers : [];
    }

    /**
     * Returns the number of unique active viewers across all episodes of a Donghua
     */
    public static function get_donghua_live_count(int $donghua_id): int {
        $viewers = self::get_donghua_live_viewers($donghua_id);
        $cutoff  = time() - self::LIVE_TTL;
        $count   = 0;

        foreach ($viewers as $data) {
            if (is_array($data) && ($data['last_seen'] ?? 0) >= $cutoff) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Touches a viewer's session/tab for a Donghua and performs pruning (>90s)
     * Protected by MySQL named lock to ensure zero race conditions.
     *
     * @return int Number of unique active viewers across the Donghua
     */
    public static function touch_donghua_viewer(int $donghua_id, int $episode_id, string $viewer_id, string $tab_id): int {
        global $wpdb;
        $lock_name = 'dh_donghua_lock_' . $donghua_id;
        $locked    = (int) $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock_name));

        try {
            $transient_key = 'dh_live_donghua_' . $donghua_id;
            $viewers       = self::get_donghua_live_viewers($donghua_id);
            $now           = time();
            $cutoff        = $now - self::LIVE_TTL;
            $cleaned       = [];

            // 1. Prune expired viewers and expired episode tabs
            foreach ($viewers as $vid => $data) {
                if (!is_array($data) || empty($data['episodes']) || !is_array($data['episodes'])) {
                    continue;
                }

                $active_episodes = [];
                $max_seen = 0;

                foreach ($data['episodes'] as $eid => $tabs) {
                    if (!is_array($tabs)) {
                        continue;
                    }
                    $active_tabs = [];
                    foreach ($tabs as $tid => $ts) {
                        if ($ts >= $cutoff) {
                            $active_tabs[$tid] = $ts;
                            if ($ts > $max_seen) {
                                $max_seen = $ts;
                            }
                        }
                    }
                    if (!empty($active_tabs)) {
                        $active_episodes[$eid] = $active_tabs;
                    }
                }

                if (!empty($active_episodes) && $max_seen >= $cutoff) {
                    $cleaned[$vid] = [
                        'episodes'  => $active_episodes,
                        'last_seen' => $max_seen,
                    ];
                }
            }

            // 2. Insert or update the current viewer, episode, and tab
            if (!isset($cleaned[$viewer_id])) {
                $cleaned[$viewer_id] = [
                    'episodes'  => [],
                    'last_seen' => $now,
                ];
            }

            if (!isset($cleaned[$viewer_id]['episodes'][$episode_id])) {
                $cleaned[$viewer_id]['episodes'][$episode_id] = [];
            }

            $cleaned[$viewer_id]['episodes'][$episode_id][$tab_id] = $now;
            $cleaned[$viewer_id]['last_seen'] = $now;

            // 3. Save transient
            set_transient($transient_key, $cleaned, 180);

            return count($cleaned);
        } finally {
            if ($locked === 1) {
                $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            }
        }
    }

    /**
     * Removes a specific tab/episode for a viewer on a Donghua
     *
     * @return int Number of unique active viewers remaining across the Donghua
     */
    public static function remove_donghua_viewer(int $donghua_id, int $episode_id, string $viewer_id, string $tab_id): int {
        global $wpdb;
        $lock_name = 'dh_donghua_lock_' . $donghua_id;
        $locked    = (int) $wpdb->get_var($wpdb->prepare("SELECT GET_LOCK(%s, 5)", $lock_name));

        try {
            $transient_key = 'dh_live_donghua_' . $donghua_id;
            $viewers       = self::get_donghua_live_viewers($donghua_id);
            $cutoff        = time() - self::LIVE_TTL;
            $cleaned       = [];

            foreach ($viewers as $vid => $data) {
                if (!is_array($data) || empty($data['episodes']) || !is_array($data['episodes'])) {
                    continue;
                }

                if ($vid === $viewer_id && isset($data['episodes'][$episode_id][$tab_id])) {
                    unset($data['episodes'][$episode_id][$tab_id]);
                    if (empty($data['episodes'][$episode_id])) {
                        unset($data['episodes'][$episode_id]);
                    }
                }

                $active_episodes = [];
                $max_seen = 0;

                foreach ($data['episodes'] as $eid => $tabs) {
                    if (!is_array($tabs)) {
                        continue;
                    }
                    $active_tabs = [];
                    foreach ($tabs as $tid => $ts) {
                        if ($ts >= $cutoff) {
                            $active_tabs[$tid] = $ts;
                            if ($ts > $max_seen) {
                                $max_seen = $ts;
                            }
                        }
                    }
                    if (!empty($active_tabs)) {
                        $active_episodes[$eid] = $active_tabs;
                    }
                }

                if (!empty($active_episodes)) {
                    $cleaned[$vid] = [
                        'episodes'  => $active_episodes,
                        'last_seen' => $max_seen,
                    ];
                }
            }

            set_transient($transient_key, $cleaned, 180);

            return count($cleaned);
        } finally {
            if ($locked === 1) {
                $wpdb->query($wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name));
            }
        }
    }
}

// Auto-initialize
Donghua_Episode_Stats::init();
