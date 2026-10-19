<?php
/**
 * The template for displaying Donghua archives & catalog
 */
declare(strict_types=1);

get_header();
?>

<div class="archive-donghua-page site-container">
    <header class="archive-header">
        <h1 class="archive-title">
            <span class="title-decor"></span>
            <?php
            if (is_tax()) {
                if (is_tax('work_format', 'movie')) { echo esc_html__('أفلام الدونغهوا', 'donghua-theme'); }
                elseif (is_tax('work_format', 'series')) { echo esc_html__('مسلسلات الدونغهوا', 'donghua-theme'); }
                else { single_term_title('تصنيف: '); }
            } else {
                echo 'دليل وقائمة أعمال الدونغهوا الصينية';
            }
            ?>
        </h1>
        <p class="archive-description"><?php echo is_tax('work_format', 'movie') ? esc_html__('الأعمال المصنّفة كأفلام فقط. يُحدَّد نوع العمل من لوحة تحرير العمل.', 'donghua-theme') : esc_html__('تصفح أحدث وأبرز أعمال الدونغهوا الصينية المترجمة والمدبلجة بجودة عالية.', 'donghua-theme'); ?></p>
    </header>

    <!-- Composable Browse Filter Bar -->
    <?php
    $current_status = sanitize_text_field(wp_unslash($_GET['status'] ?? ''));
    $current_type   = sanitize_text_field(wp_unslash($_GET['type'] ?? ''));
    $current_genre  = is_tax('genre') ? sanitize_title((string) get_queried_object()->slug) : sanitize_title(wp_unslash($_GET['genre'] ?? ''));
    $current_sort   = sanitize_text_field(wp_unslash($_GET['sort'] ?? ($_GET['orderby'] ?? 'latest')));
    $archive_url = get_post_type_archive_link('donghua');
    if (is_tax('work_format')) { $term_url = get_term_link(get_queried_object()); if (!is_wp_error($term_url)) $archive_url = $term_url; }
    $has_active_filters = (!empty($current_status) && $current_status !== 'all') || (!empty($current_type) && $current_type !== 'all') || (!empty($current_genre) && $current_genre !== 'all') || ($current_sort !== 'latest' && $current_sort !== 'date');
    $generic_genre = get_term_by('slug', 'donghua', 'genre');
    $genre_args = ['taxonomy' => 'genre', 'hide_empty' => false];
    if ($generic_genre instanceof WP_Term) $genre_args['exclude'] = [(int) $generic_genre->term_id];
    $genres = get_terms($genre_args);
    ?>
    <section class="archive-filter-bar" aria-label="تصفية وفهرسة الأعمال">
        <form method="get" action="<?php echo esc_url($archive_url); ?>" class="archive-filters-form" id="archive-filters-form">
            <div class="filter-controls-row">
                <!-- Status Filter -->
                <div class="filter-field">
                    <label for="filter-status" class="filter-field-label">الحالة:</label>
                    <select id="filter-status" name="status" class="filter-select <?php echo !empty($current_status) && $current_status !== 'all' ? 'is-filtered' : ''; ?>" onchange="this.form.submit()">
                        <option value="all" <?php selected(in_array($current_status, ['', 'all'], true)); ?>>الكل</option>
                        <option value="ongoing" <?php selected($current_status, 'ongoing'); ?>>مستمر</option>
                        <option value="completed" <?php selected($current_status, 'completed'); ?>>مكتمل</option>
                    </select>
                </div>

                <!-- Animation Type Filter -->
                <div class="filter-field">
                    <label for="filter-type" class="filter-field-label">نوع الرسم:</label>
                    <select id="filter-type" name="type" class="filter-select <?php echo !empty($current_type) && $current_type !== 'all' ? 'is-filtered' : ''; ?>" onchange="this.form.submit()">
                        <option value="all">الكل</option>
                        <option value="3d" <?php selected($current_type, '3d'); ?>>3D</option>
                        <option value="2d" <?php selected($current_type, '2d'); ?>>2D</option>
                    </select>
                </div>

                <!-- Genre Filter -->
                <div class="filter-field">
                    <label for="filter-genre" class="filter-field-label">التصنيف:</label>
                    <select id="filter-genre" name="genre" class="filter-select <?php echo !empty($current_genre) && $current_genre !== 'all' ? 'is-filtered' : ''; ?>" onchange="this.form.submit()">
                        <option value="all" <?php selected(in_array($current_genre, ['', 'all'], true)); ?>>الكل</option>
                        <?php if (!empty($genres) && !is_wp_error($genres)): ?>
                            <?php foreach ($genres as $g): ?>
                                <option value="<?php echo esc_attr($g->slug); ?>" <?php selected($current_genre, $g->slug); ?>>
                                    <?php echo esc_html($g->name); ?> (<?php echo esc_html((string)$g->count); ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Sort Filter -->
                <div class="filter-field">
                    <label for="filter-sort" class="filter-field-label">الترتيب:</label>
                    <select id="filter-sort" name="sort" class="filter-select <?php echo $current_sort !== 'latest' && $current_sort !== 'date' ? 'is-filtered' : ''; ?>" onchange="this.form.submit()">
                        <option value="latest" <?php selected($current_sort, 'latest'); ?>>الأحدث</option>
                        <option value="random" <?php selected($current_sort, 'random'); ?>>عشوائي</option>
                        <option value="title" <?php selected($current_sort, 'title'); ?>>الأبجدي</option>
                        <option value="rating" <?php selected(in_array($current_sort, ['rating', 'score'], true), true); ?>>الأعلى تقييماً</option>
                    </select>
                </div>

                <!-- Clear Filters Action -->
                <div class="filter-actions-group">
                    <noscript>
                        <button type="submit" class="filter-apply-btn">تصفية</button>
                    </noscript>
                    <?php if ($has_active_filters): ?>
                        <a href="<?php echo esc_url($archive_url); ?>" class="filter-clear-btn" title="مسح جميع الفلاتر">
                            <?php echo donghua_icon('close', '', 12); ?>
                            <span>مسح الفلاتر</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="filter-meta-bar">
                <span class="filter-count-badge">
                    <?php
                    global $wp_query;
                    printf(esc_html(_n('عرض %s عمل', 'عرض %s أعمال', (int)$wp_query->found_posts, 'donghua-theme')), '<strong>' . number_format_i18n((int)$wp_query->found_posts) . '</strong>');
                    ?>
                </span>
            </div>
        </form>
    </section>

    <!-- Cards Grid -->
    <div class="donghua-grid archive-grid">
        <?php if (have_posts()): ?>
            <?php while (have_posts()): the_post(); ?>
                <?php get_template_part('template-parts/donghua-card'); ?>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-results-box">
                <span class="empty-icon"><?php echo donghua_icon('info', '', 36); ?></span>
                <h2>لم يتم العثور على أعمال مطابقة</h2>
                <p>جرب تصفية مختلفة أو استخدام شريط البحث في الأعلى.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <div class="archive-pagination">
        <?php donghua_pagination(); ?>
    </div>
</div>

<?php get_footer(); ?>
