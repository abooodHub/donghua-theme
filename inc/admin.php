<?php
/**
 * Theme administration screen for OK.DONGHUA presentation settings.
 */
declare(strict_types=1);

defined('ABSPATH') || exit;

function donghua_theme_default_mode(): string {
    $mode = sanitize_key((string) get_option('donghua_default_theme_mode', 'night-blue'));
    $themes = ['night-blue', 'jade', 'moon', 'charcoal'];
    return in_array($mode, $themes, true) ? $mode : 'night-blue';
}

function donghua_theme_header_link_enabled(string $link): bool {
    if (!in_array($link, ['community', 'news'], true)) {
        return false;
    }

    return (string) get_option('donghua_header_link_' . $link . '_enabled', '0') === '1';
}

function donghua_theme_home_sections(): array {
    return [
        'hero' => ['label' => __('السلايدر الرئيسي', 'donghua-theme'), 'default_count' => 6, 'min' => 1, 'max' => 12],
        'latest_episodes' => ['label' => __('أحدث الحلقات', 'donghua-theme'), 'default_count' => 10, 'min' => 1, 'max' => 30],
        'popular_genres' => ['label' => __('التصنيفات الشائعة', 'donghua-theme'), 'default_count' => 15, 'min' => 1, 'max' => 15, 'count_only' => true],
        'most_watched' => ['label' => __('الأكثر مشاهدة', 'donghua-theme'), 'default_count' => 6, 'min' => 1, 'max' => 20, 'count_only' => true, 'status_note' => __('يظهر عند توفر أعمال.', 'donghua-theme')],
        'sidebar_popular' => ['label' => __('قائمة الأكثر مشاهدة الجانبية', 'donghua-theme'), 'default_count' => 10, 'min' => 1, 'max' => 20, 'count_only' => true, 'status_note' => __('يظهر عند توفر أعمال.', 'donghua-theme')],
        'recent' => ['label' => __('أحدث الأعمال', 'donghua-theme'), 'default_count' => 7, 'min' => 1, 'max' => 30, 'count_only' => true, 'status_note' => __('يظهر عند توفر أعمال.', 'donghua-theme')],
        'continue_watching' => ['label' => __('متابعة المشاهدة', 'donghua-theme'), 'default_count' => 4, 'min' => 1, 'max' => 12, 'count_only' => true, 'status_note' => __('للمستخدم المسجل عند توفر سجل مشاهدة.', 'donghua-theme')],
        'ongoing' => ['label' => __('أعمال مستمرة', 'donghua-theme'), 'default_count' => 8, 'min' => 4, 'max' => 24],
        'donghua_3d' => ['label' => __('دونغهوا 3D', 'donghua-theme'), 'default_count' => 8, 'min' => 4, 'max' => 24],
        'donghua_2d' => ['label' => __('دونغهوا 2D', 'donghua-theme'), 'default_count' => 8, 'min' => 4, 'max' => 24],
        'top_rated' => ['label' => __('الأعلى تقييماً', 'donghua-theme'), 'default_count' => 8, 'min' => 4, 'max' => 24],
        'completed' => ['label' => __('أعمال مكتملة', 'donghua-theme'), 'default_count' => 8, 'min' => 4, 'max' => 24],
        'upcoming' => ['label' => __('أعمال قادمة', 'donghua-theme'), 'default_count' => 8, 'min' => 4, 'max' => 24],
        'news' => ['label' => __('الأخبار والمقالات', 'donghua-theme'), 'default_count' => 4, 'min' => 1, 'max' => 12],
    ];
}

function donghua_theme_home_section_enabled(string $section): bool {
    $sections = donghua_theme_home_sections();
    if (!isset($sections[$section])) {
        return false;
    }

    if (class_exists('DHM_Site_Control')) {
        return DHM_Site_Control::feature_enabled('home_' . $section);
    }

    return (string) get_option('donghua_home_section_' . $section . '_enabled', '1') === '1';
}

function donghua_theme_home_section_count(string $section): int {
    $sections = donghua_theme_home_sections();
    if (!isset($sections[$section])) {
        return 0;
    }

    $setting = $sections[$section];
    $raw_value = get_option('donghua_home_section_' . $section . '_count', (string) $setting['default_count']);
    return donghua_theme_normalize_home_section_count($setting, $raw_value);
}

function donghua_theme_normalize_home_section_count(array $setting, $raw_value): int {
    $default = (int) $setting['default_count'];
    if (!is_scalar($raw_value) || trim((string) $raw_value) === '') {
        return $default;
    }

    $value = absint($raw_value);
    return $value >= (int) $setting['min'] && $value <= (int) $setting['max'] ? $value : $default;
}

/** Return manually selected genres or fall back to the most populated real terms. */
function donghua_theme_home_popular_genres(): array {
    if (!taxonomy_exists('genre')) {
        return [];
    }

    $manual_enabled = (string) get_option('donghua_home_popular_genres_enabled', '0') === '1';
    $configured = get_option('donghua_home_popular_genres', []);
    $slugs = [];
    if ($manual_enabled && is_array($configured)) {
        foreach (array_slice($configured, 0, donghua_theme_home_section_count('popular_genres')) as $value) {
            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }
            $slug = sanitize_title((string) $value);
            if ($slug !== '' && !in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        if ($slugs) {
            $terms = get_terms(['taxonomy' => 'genre', 'hide_empty' => false, 'slug' => $slugs]);
            if (!is_wp_error($terms) && $terms) {
                $by_slug = [];
                foreach ($terms as $term) {
                    if ($term instanceof WP_Term && $term->taxonomy === 'genre') {
                        $by_slug[$term->slug] = $term;
                    }
                }

                $ordered = [];
                foreach ($slugs as $slug) {
                    if (isset($by_slug[$slug])) {
                        $ordered[] = $by_slug[$slug];
                    }
                }
                if ($ordered) {
                    return $ordered;
                }
            }
        }
    }

    $popular = get_terms([
        'taxonomy'   => 'genre',
        'hide_empty' => false,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);
    if (is_wp_error($popular)) {
        return [];
    }
    $popular = array_values(array_filter($popular, static function ($term): bool {
        return $term instanceof WP_Term
            && $term->taxonomy === 'genre'
            && $term->slug !== 'donghua'
            && trim($term->name) !== 'دونغهوا';
    }));
    return array_slice($popular, 0, donghua_theme_home_section_count('popular_genres'));
}

/** Assigned genre artwork takes priority; otherwise use a cached representative work image. */
function donghua_theme_genre_image_id(WP_Term $term): int {
    $assigned_id = absint(get_term_meta($term->term_id, '_donghua_genre_image_id', true));
    if ($assigned_id && wp_attachment_is_image($assigned_id)) {
        return $assigned_id;
    }

    $cache_key = 'donghua_genre_art_' . absint($term->term_id);
    $cached_id = get_transient($cache_key);
    if ($cached_id !== false) {
        return absint($cached_id);
    }

    $work_ids = get_posts([
        'post_type'              => 'donghua',
        'post_status'            => 'publish',
        'posts_per_page'         => 1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query'             => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
        'tax_query'              => [[
            'taxonomy' => 'genre',
            'field'    => 'term_id',
            'terms'    => [(int) $term->term_id],
        ]],
        'orderby' => 'date',
        'order'   => 'DESC',
    ]);
    $representative_id = $work_ids ? absint(get_post_thumbnail_id((int) $work_ids[0])) : 0;
    set_transient($cache_key, $representative_id, 2 * HOUR_IN_SECONDS);

    return $representative_id;
}

function donghua_theme_genre_image_field_markup(int $attachment_id = 0): void {
    $image_url = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'medium') : false;
    ?>
    <div class="donghua-genre-image-field">
        <?php wp_nonce_field('donghua_genre_image', 'donghua_genre_image_nonce', false); ?>
        <input type="hidden" id="donghua-genre-image-id" name="_donghua_genre_image_id" value="<?php echo esc_attr((string) ($image_url ? $attachment_id : 0)); ?>" />
        <div class="donghua-genre-image-preview" data-genre-image-preview>
            <?php if ($image_url): ?>
                <img src="<?php echo esc_url($image_url); ?>" alt="" />
            <?php else: ?>
                <span><?php echo esc_html__('لم تُحدد صورة بعد.', 'donghua-theme'); ?></span>
            <?php endif; ?>
        </div>
        <p>
            <button type="button" class="button" data-genre-image-choose><?php echo esc_html__('اختيار صورة', 'donghua-theme'); ?></button>
            <button type="button" class="button-link-delete" data-genre-image-remove <?php echo $image_url ? '' : 'hidden'; ?>><?php echo esc_html__('إزالة الصورة', 'donghua-theme'); ?></button>
        </p>
        <p class="description"><?php echo esc_html__('تُستخدم هذه الصورة في واجهة التصنيفات الشائعة.', 'donghua-theme'); ?></p>
    </div>
    <?php
}

function donghua_theme_genre_add_image_field(): void {
    ?>
    <div class="form-field term-group">
        <label for="donghua-genre-image-id"><?php echo esc_html__('صورة التصنيف', 'donghua-theme'); ?></label>
        <?php donghua_theme_genre_image_field_markup(); ?>
    </div>
    <?php
}

function donghua_theme_genre_edit_image_field(WP_Term $term): void {
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="donghua-genre-image-id"><?php echo esc_html__('صورة التصنيف', 'donghua-theme'); ?></label></th>
        <td><?php donghua_theme_genre_image_field_markup(absint(get_term_meta($term->term_id, '_donghua_genre_image_id', true))); ?></td>
    </tr>
    <?php
}

function donghua_theme_save_genre_image(int $term_id): void {
    if (!isset($_POST['donghua_genre_image_nonce'], $_POST['_donghua_genre_image_id'])
        || !is_scalar($_POST['donghua_genre_image_nonce'])
        || !is_scalar($_POST['_donghua_genre_image_id'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['donghua_genre_image_nonce'])), 'donghua_genre_image')) {
        return;
    }

    $taxonomy = get_taxonomy('genre');
    $capability = $taxonomy ? $taxonomy->cap->edit_terms : 'manage_categories';
    if (!$taxonomy || !current_user_can($capability)) {
        return;
    }

    $attachment_id = absint(wp_unslash($_POST['_donghua_genre_image_id']));
    if ($attachment_id === 0) {
        delete_term_meta($term_id, '_donghua_genre_image_id');
        delete_transient('donghua_genre_art_' . $term_id);
    } elseif (wp_attachment_is_image($attachment_id)) {
        update_term_meta($term_id, '_donghua_genre_image_id', $attachment_id);
        delete_transient('donghua_genre_art_' . $term_id);
    }
}

add_action('genre_add_form_fields', 'donghua_theme_genre_add_image_field');
add_action('genre_edit_form_fields', 'donghua_theme_genre_edit_image_field');
add_action('created_genre', 'donghua_theme_save_genre_image');
add_action('edited_genre', 'donghua_theme_save_genre_image');

function donghua_theme_enqueue_genre_image_admin(string $hook_suffix): void {
    if (!in_array($hook_suffix, ['edit-tags.php', 'term.php'], true)) {
        return;
    }
    $screen = get_current_screen();
    if (!$screen || $screen->taxonomy !== 'genre') {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'donghua-genre-image-admin',
        DONGHUA_THEME_URI . '/assets/js/genre-image-admin.js',
        ['jquery'],
        DONGHUA_THEME_VERSION,
        true
    );
    wp_enqueue_style(
        'donghua-genre-image-admin',
        DONGHUA_THEME_URI . '/assets/css/genre-image-admin.css',
        [],
        DONGHUA_THEME_VERSION
    );
}
add_action('admin_enqueue_scripts', 'donghua_theme_enqueue_genre_image_admin');

function donghua_theme_available_genres(): array {
    if (!taxonomy_exists('genre')) {
        return [];
    }
    $terms = get_terms(['taxonomy' => 'genre', 'hide_empty' => false, 'orderby' => 'name', 'order' => 'ASC']);
    return is_wp_error($terms) ? [] : $terms;
}

function donghua_theme_save_popular_genres(): void {
    update_option('donghua_home_popular_genres_enabled', isset($_POST['donghua_home_popular_genres_enabled']) ? '1' : '0', false);

    $raw = isset($_POST['donghua_home_popular_genres']) && is_array($_POST['donghua_home_popular_genres'])
        ? array_slice(wp_unslash($_POST['donghua_home_popular_genres']), 0, 15)
        : [];
    $slugs = [];
    foreach ($raw as $value) {
        if (!is_string($value) && !is_numeric($value)) {
            continue;
        }
        $slug = sanitize_title((string) $value);
        if ($slug !== '' && !in_array($slug, $slugs, true)) {
            $slugs[] = $slug;
        }
    }

    if ($slugs && taxonomy_exists('genre')) {
        $valid = get_terms(['taxonomy' => 'genre', 'hide_empty' => false, 'slug' => $slugs, 'fields' => 'slugs']);
        $valid_slugs = is_wp_error($valid) ? [] : $valid;
        $slugs = array_values(array_filter($slugs, static fn(string $slug): bool => in_array($slug, $valid_slugs, true)));
    } else {
        $slugs = [];
    }

    update_option('donghua_home_popular_genres', $slugs, false);
}

add_action('admin_menu', function (): void {
    add_theme_page(
        __('إدارة قالب OK.DONGHUA', 'donghua-theme'),
        __('OK.DONGHUA', 'donghua-theme'),
        'edit_theme_options',
        'donghua-theme-admin',
        'donghua_theme_render_admin_page'
    );
});

add_action('admin_init', function (): void {
    if (!isset($_POST['donghua_theme_admin_submit'])) {
        return;
    }

    if (!current_user_can('edit_theme_options')) {
        wp_die(esc_html__('لا تملك صلاحية تعديل إعدادات القالب.', 'donghua-theme'));
    }

    check_admin_referer('donghua_theme_admin_save', 'donghua_theme_admin_nonce');

    $home_title = isset($_POST['donghua_home_title'])
        ? sanitize_text_field(wp_unslash($_POST['donghua_home_title']))
        : '';

    $home_description = isset($_POST['donghua_home_description'])
        ? sanitize_text_field(wp_unslash($_POST['donghua_home_description']))
        : '';

    $default_mode = isset($_POST['donghua_default_theme_mode'])
        ? sanitize_key(wp_unslash($_POST['donghua_default_theme_mode']))
        : 'night-blue';

    if (!in_array($default_mode, ['night-blue', 'jade', 'moon', 'charcoal'], true)) {
        $default_mode = 'night-blue';
    }

    set_theme_mod('donghua_home_title', $home_title);
    set_theme_mod('donghua_home_description', $home_description);
    update_option('donghua_default_theme_mode', $default_mode, false);

    foreach (['community', 'news'] as $header_link) {
        $enabled = isset($_POST['donghua_header_link_enabled'][$header_link]) ? '1' : '0';
        update_option('donghua_header_link_' . $header_link . '_enabled', $enabled, false);
    }

    foreach (donghua_theme_home_sections() as $section_key => $section) {
        if (empty($section['count_only'])) {
            $enabled = isset($_POST['donghua_home_section_enabled'][$section_key]) ? '1' : '0';
            update_option('donghua_home_section_' . $section_key . '_enabled', $enabled, false);
        }

        $raw_count = isset($_POST['donghua_home_section_count'][$section_key])
            && is_scalar($_POST['donghua_home_section_count'][$section_key])
            ? wp_unslash($_POST['donghua_home_section_count'][$section_key])
            : null;
        $count = donghua_theme_normalize_home_section_count($section, $raw_count);

        update_option('donghua_home_section_' . $section_key . '_count', (string) $count, false);
    }

    donghua_theme_save_popular_genres();

    $redirect = add_query_arg(
        ['page' => 'donghua-theme-admin', 'settings-updated' => 'true'],
        admin_url('themes.php')
    );

    wp_safe_redirect($redirect);
    exit;
});

function donghua_theme_render_admin_page(): void {
    if (!current_user_can('edit_theme_options')) {
        return;
    }

    $home_title = get_theme_mod('donghua_home_title', 'عالم الدونغهوا، أقرب إليك.');
    $home_description = get_theme_mod('donghua_home_description', 'اكتشف حكايتك القادمة، تابع أحدث الحلقات، وتعرّف على العوالم والشخصيات.');
    $default_mode = donghua_theme_default_mode();
    $logo_state = has_custom_logo() ? __('شعار مخصص مفعل', 'donghua-theme') : __('الشعار الافتراضي مفعل', 'donghua-theme');
    $home_sections = donghua_theme_home_sections();
    $popular_genre_enabled = (string) get_option('donghua_home_popular_genres_enabled', '0') === '1';
    $popular_genre_slugs = get_option('donghua_home_popular_genres', []);
    $popular_genre_slugs = is_array($popular_genre_slugs)
        ? array_slice(array_values(array_filter(array_map(static function ($slug): string {
            return is_string($slug) || is_numeric($slug) ? sanitize_title((string) $slug) : '';
        }, $popular_genre_slugs))), 0, 15)
        : [];
    $available_genres = donghua_theme_available_genres();
    $header_links = [
        'community' => __('المجتمع', 'donghua-theme'),
        'news' => __('الأخبار', 'donghua-theme'),
    ];
    ?>
    <div class="wrap donghua-theme-admin-wrap" dir="rtl">
        <h1><?php echo esc_html__('إدارة قالب OK.DONGHUA', 'donghua-theme'); ?></h1>
        <p class="description">
            <?php echo esc_html__('مركز سريع لإدارة الهوية، واجهة الصفحة الرئيسية، وروابط العمل اليومية للقالب.', 'donghua-theme'); ?>
        </p>

        <?php if (isset($_GET['settings-updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html__('تم حفظ إعدادات القالب بنجاح.', 'donghua-theme'); ?></p>
            </div>
        <?php endif; ?>

        <div class="donghua-theme-admin-grid">
            <form method="post" class="donghua-theme-admin-card donghua-theme-admin-form">
                <?php wp_nonce_field('donghua_theme_admin_save', 'donghua_theme_admin_nonce'); ?>
                <input type="hidden" name="donghua_theme_admin_submit" value="1" />

                <h2><?php echo esc_html__('الهوية', 'donghua-theme'); ?></h2>
                <p><?php echo esc_html__('هذه الإعدادات تظهر في أول قسم من الصفحة الرئيسية.', 'donghua-theme'); ?></p>

                <label for="donghua_home_title"><?php echo esc_html__('عنوان الرئيسية', 'donghua-theme'); ?></label>
                <input
                    type="text"
                    id="donghua_home_title"
                    name="donghua_home_title"
                    class="regular-text"
                    value="<?php echo esc_attr((string) $home_title); ?>"
                />

                <label for="donghua_home_description"><?php echo esc_html__('وصف الرئيسية', 'donghua-theme'); ?></label>
                <textarea
                    id="donghua_home_description"
                    name="donghua_home_description"
                    rows="4"
                    class="large-text"
                ><?php echo esc_textarea((string) $home_description); ?></textarea>

                <h2 class="donghua-theme-admin-subtitle"><?php echo esc_html__('المظهر', 'donghua-theme'); ?></h2>
                <label for="donghua_default_theme_mode"><?php echo esc_html__('الأجواء الافتراضية للزوار الجدد', 'donghua-theme'); ?></label>
                <select id="donghua_default_theme_mode" name="donghua_default_theme_mode">
                    <option value="night-blue" <?php selected($default_mode, 'night-blue'); ?>><?php echo esc_html__('ليل أزرق', 'donghua-theme'); ?></option>
                    <option value="jade" <?php selected($default_mode, 'jade'); ?>><?php echo esc_html__('زمردي', 'donghua-theme'); ?></option>
                    <option value="moon" <?php selected($default_mode, 'moon'); ?>><?php echo esc_html__('قمر نيلي', 'donghua-theme'); ?></option>
                    <option value="charcoal" <?php selected($default_mode, 'charcoal'); ?>><?php echo esc_html__('فحمي', 'donghua-theme'); ?></option>
                </select>

                <h2 class="donghua-theme-admin-subtitle"><?php echo esc_html__('التنقل', 'donghua-theme'); ?></h2>
                <p><?php echo esc_html__('الموسوعة والأفلام ظاهرتان افتراضيًا. يمكنك إظهار الأخبار أو المجتمع من هنا متى رغبت.', 'donghua-theme'); ?></p>
                <?php foreach ($header_links as $link_key => $link_label): ?>
                    <label class="donghua-switch donghua-header-link-setting">
                        <input
                            type="checkbox"
                            name="donghua_header_link_enabled[<?php echo esc_attr((string) $link_key); ?>]"
                            value="1"
                            <?php checked(donghua_theme_header_link_enabled((string) $link_key)); ?>
                        />
                        <span><?php echo esc_html(sprintf(__('إظهار رابط %s في القائمة', 'donghua-theme'), $link_label)); ?></span>
                    </label>
                <?php endforeach; ?>

                <h2 class="donghua-theme-admin-subtitle"><?php echo esc_html__('الرئيسية', 'donghua-theme'); ?></h2>
                <p><?php echo esc_html__('تحكم في الأقسام الظاهرة وعدد العناصر داخل كل قسم.', 'donghua-theme'); ?></p>

                <div class="donghua-home-sections-table" role="group" aria-label="<?php echo esc_attr__('إعدادات أقسام الصفحة الرئيسية', 'donghua-theme'); ?>">
                    <div class="donghua-home-sections-head">
                        <span><?php echo esc_html__('القسم', 'donghua-theme'); ?></span>
                        <span><?php echo esc_html__('إظهار', 'donghua-theme'); ?></span>
                        <span><?php echo esc_html__('العدد', 'donghua-theme'); ?></span>
                    </div>
                    <?php foreach ($home_sections as $section_key => $section): ?>
                        <?php
                        $enabled = donghua_theme_home_section_enabled((string) $section_key);
                        $count = donghua_theme_home_section_count((string) $section_key);
                        ?>
                        <div class="donghua-home-section-row">
                            <label for="donghua_home_section_count_<?php echo esc_attr((string) $section_key); ?>">
                                <?php echo esc_html((string) $section['label']); ?>
                            </label>
                            <?php if (!empty($section['count_only'])): ?>
                                <span class="description"><?php echo esc_html($section['status_note'] ?? __('يعتمد على سجل المشاهدة', 'donghua-theme')); ?></span>
                            <?php else: ?>
                                <label class="donghua-switch">
                                    <input
                                        type="checkbox"
                                        name="donghua_home_section_enabled[<?php echo esc_attr((string) $section_key); ?>]"
                                        value="1"
                                        <?php checked($enabled); ?>
                                    />
                                    <span><?php echo esc_html__('مفعل', 'donghua-theme'); ?></span>
                                </label>
                            <?php endif; ?>
                            <input
                                type="number"
                                id="donghua_home_section_count_<?php echo esc_attr((string) $section_key); ?>"
                                name="donghua_home_section_count[<?php echo esc_attr((string) $section_key); ?>]"
                                min="<?php echo esc_attr((string) $section['min']); ?>"
                                max="<?php echo esc_attr((string) $section['max']); ?>"
                                value="<?php echo esc_attr((string) $count); ?>"
                            />
                        </div>
                    <?php endforeach; ?>
                </div>

                <h2 class="donghua-theme-admin-subtitle"><?php echo esc_html__('التصنيفات', 'donghua-theme'); ?></h2>
                <p><?php echo esc_html__('اختر حتى 15 تصنيفًا حقيقيًا، ورتبها من الأعلى إلى الأسفل عبر مواضع الاختيار.', 'donghua-theme'); ?></p>
                <label class="donghua-switch donghua-popular-genres-toggle">
                    <input type="checkbox" name="donghua_home_popular_genres_enabled" value="1" <?php checked($popular_genre_enabled); ?> />
                    <span><?php echo esc_html__('تفعيل الاختيارات اليدوية للتصنيفات الشائعة', 'donghua-theme'); ?></span>
                </label>
                <div class="donghua-popular-genres-fields">
                    <?php for ($position = 0; $position < 15; $position++): ?>
                        <label for="donghua_home_popular_genre_<?php echo esc_attr((string) $position); ?>">
                            <?php echo esc_html(sprintf(__('الموضع %d', 'donghua-theme'), $position + 1)); ?>
                            <select id="donghua_home_popular_genre_<?php echo esc_attr((string) $position); ?>" name="donghua_home_popular_genres[]">
                                <option value=""><?php echo esc_html__('— بدون تصنيف —', 'donghua-theme'); ?></option>
                                <?php foreach ($available_genres as $genre): ?>
                                    <option value="<?php echo esc_attr($genre->slug); ?>" <?php selected($popular_genre_slugs[$position] ?? '', $genre->slug); ?>><?php echo esc_html($genre->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endfor; ?>
                </div>

                <?php submit_button(__('حفظ إعدادات القالب', 'donghua-theme'), 'primary', 'submit', false); ?>
            </form>

            <div class="donghua-theme-admin-side">
                <section class="donghua-theme-admin-card">
                    <h2><?php echo esc_html__('حالة الهوية', 'donghua-theme'); ?></h2>
                    <ul class="donghua-theme-status-list">
                        <li><strong><?php echo esc_html__('نسخة القالب:', 'donghua-theme'); ?></strong> <?php echo esc_html((string) wp_get_theme()->get('Version')); ?></li>
                        <li><strong><?php echo esc_html__('الشعار:', 'donghua-theme'); ?></strong> <?php echo esc_html($logo_state); ?></li>
                        <li><strong><?php echo esc_html__('المظهر الافتراضي:', 'donghua-theme'); ?></strong> <?php echo esc_html($default_mode); ?></li>
                    </ul>
                </section>

                <section class="donghua-theme-admin-card">
                    <h2><?php echo esc_html__('روابط سريعة', 'donghua-theme'); ?></h2>
                    <div class="donghua-theme-actions">
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('customize.php')); ?>"><?php echo esc_html__('تخصيص القالب', 'donghua-theme'); ?></a>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('nav-menus.php')); ?>"><?php echo esc_html__('القوائم', 'donghua-theme'); ?></a>
                        <a class="button button-secondary" href="<?php echo esc_url(admin_url('site-editor.php')); ?>"><?php echo esc_html__('محرر الموقع', 'donghua-theme'); ?></a>
                        <a class="button button-primary" href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener"><?php echo esc_html__('معاينة الموقع', 'donghua-theme'); ?></a>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <?php
}

add_action('admin_enqueue_scripts', function (string $hook): void {
    if ($hook !== 'appearance_page_donghua-theme-admin') {
        return;
    }

    $css = '
        .donghua-theme-admin-wrap{max-width:1180px}
        .donghua-theme-admin-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:20px;margin-top:18px}
        .donghua-theme-admin-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:20px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
        .donghua-theme-admin-card h2{margin-top:0;font-size:18px}
        .donghua-theme-admin-subtitle{margin-top:28px!important;padding-top:20px;border-top:1px solid #f0f0f1}
        .donghua-theme-admin-form label{display:block;margin:18px 0 7px;font-weight:700}
        .donghua-theme-admin-form input[type=text],.donghua-theme-admin-form textarea,.donghua-theme-admin-form select{width:100%;max-width:680px}
        .donghua-home-sections-table{display:grid;gap:0;margin:14px 0 22px;max-width:780px;border:1px solid #dcdcde;border-radius:10px;overflow:hidden;background:#fff}
        .donghua-home-sections-head,.donghua-home-section-row{display:grid;grid-template-columns:minmax(180px,1fr) 120px 110px;gap:12px;align-items:center;padding:12px 14px}
        .donghua-home-sections-head{background:#f6f7f7;color:#50575e;font-weight:700}
        .donghua-home-section-row{border-top:1px solid #f0f0f1}
        .donghua-home-section-row>label:first-child{margin:0}
        .donghua-home-section-row input[type=number]{width:90px}
        .donghua-popular-genres-toggle{margin:14px 0!important}
        .donghua-popular-genres-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 18px;max-width:780px}
        .donghua-popular-genres-fields label{margin-block:9px!important}
        .donghua-popular-genres-fields select{display:block;margin-top:6px}
        .donghua-switch{display:flex!important;align-items:center;gap:8px;margin:0!important;font-weight:600!important}
        .donghua-theme-status-list{margin:0}
        .donghua-theme-status-list li{display:flex;justify-content:space-between;gap:16px;margin:0;padding:11px 0;border-bottom:1px solid #f0f0f1}
        .donghua-theme-status-list li:last-child{border-bottom:0}
        .donghua-theme-actions{display:grid;gap:10px}
        .donghua-theme-actions .button{text-align:center}
        @media (max-width: 960px){.donghua-theme-admin-grid{grid-template-columns:1fr}.donghua-theme-admin-side{display:grid;gap:20px}}
        @media (max-width: 640px){.donghua-home-sections-head{display:none}.donghua-home-section-row{grid-template-columns:1fr}.donghua-home-section-row input[type=number]{width:100%}.donghua-popular-genres-fields{grid-template-columns:1fr}}
    ';

    wp_register_style('donghua-theme-admin', false, [], DONGHUA_THEME_VERSION);
    wp_enqueue_style('donghua-theme-admin');
    wp_add_inline_style('donghua-theme-admin', $css);
});
