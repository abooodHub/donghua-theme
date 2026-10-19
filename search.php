<?php
/**
 * The template for displaying search results
 * Separates Donghua, movies and episodes into their matching result sections.
 */
declare(strict_types=1);

get_header();

global $wp_query;
$search_query = get_search_query();

// Partition results from the main query safely
$donghua_posts = [];
$movie_posts = [];
$episode_posts = [];

if (have_posts()) {
    foreach ($wp_query->posts as $p) {
        if ($p->post_type === 'donghua') {
            $donghua_posts[] = $p;
        } elseif ($p->post_type === 'movie') {
            $movie_posts[] = $p;
        } elseif ($p->post_type === 'episode') {
            $episode_posts[] = $p;
        } else {
            $donghua_posts[] = $p;
        }
    }
}
$has_results = !empty($donghua_posts) || !empty($movie_posts) || !empty($episode_posts);
?>

<div class="search-results-page site-container">
    <header class="search-header">
        <h1 class="search-title">
            نتائج البحث عن: <span class="search-term-highlight">"<?php echo esc_html($search_query); ?>"</span>
        </h1>
        <?php if ($has_results): ?>
            <p class="search-summary-text">
                <?php
                $summary_parts = [];
                if (!empty($donghua_posts)) {
                    $summary_parts[] = sprintf(_n('%s عمل', '%s أعمال', count($donghua_posts), 'donghua-theme'), number_format_i18n(count($donghua_posts)));
                }
                if (!empty($movie_posts)) {
                    $summary_parts[] = sprintf(_n('%s فيلم', '%s أفلام', count($movie_posts), 'donghua-theme'), number_format_i18n(count($movie_posts)));
                }
                if (!empty($episode_posts)) {
                    $summary_parts[] = sprintf(_n('%s حلقة', '%s حلقات', count($episode_posts), 'donghua-theme'), number_format_i18n(count($episode_posts)));
                }
                printf(esc_html__('تم العثور على: %s', 'donghua-theme'), '<strong>' . esc_html(implode(' و ', $summary_parts)) . '</strong>');
                ?>
            </p>
        <?php endif; ?>
    </header>

    <?php if ($has_results): ?>

        <!-- 1. Donghua Section (2:3 Vertical Posters) -->
        <?php if (!empty($donghua_posts)): ?>
            <section class="search-results-section search-donghua-section" aria-label="أعمال الدونغهوا">
                <div class="search-section-header">
                    <div class="header-title-box">
                        <span class="header-accent-dot"></span>
                        <h2 class="search-section-title">أعمال الدونغهوا</h2>
                    </div>
                    <span class="search-section-count"><?php echo esc_html(count($donghua_posts)); ?></span>
                </div>
                <div class="donghua-grid search-donghua-grid">
                    <?php foreach ($donghua_posts as $post): setup_postdata($post); ?>
                        <?php get_template_part('template-parts/donghua-card'); ?>
                    <?php endforeach; wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($movie_posts)): ?>
            <section class="search-results-section search-movies-section" aria-label="الأفلام">
                <div class="search-section-header">
                    <div class="header-title-box">
                        <span class="header-accent-dot"></span>
                        <h2 class="search-section-title">الأفلام</h2>
                    </div>
                    <span class="search-section-count"><?php echo esc_html(count($movie_posts)); ?></span>
                </div>
                <div class="movie-grid">
                    <?php foreach ($movie_posts as $post): setup_postdata($post); ?>
                        <?php get_template_part('template-parts/movie-card'); ?>
                    <?php endforeach; wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- 2. Episodes Section (16:9 Episode Cards) -->
        <?php if (!empty($episode_posts)): ?>
            <section class="search-results-section search-episodes-section" aria-label="الحلقات">
                <div class="search-section-header">
                    <div class="header-title-box">
                        <span class="header-accent-dot"></span>
                        <h2 class="search-section-title">الحلقات</h2>
                    </div>
                    <span class="search-section-count"><?php echo esc_html(count($episode_posts)); ?></span>
                </div>
                <div class="search-episodes-grid">
                    <?php foreach ($episode_posts as $post): setup_postdata($post); ?>
                        <?php get_template_part('template-parts/episode-card'); ?>
                    <?php endforeach; wp_reset_postdata(); ?>
                </div>
            </section>
        <?php endif; ?>

        <!-- Pagination -->
        <div class="search-pagination">
            <?php donghua_pagination(); ?>
        </div>

    <?php else: ?>

        <!-- Single Clean Empty State -->
        <div class="no-search-results">
            <span class="empty-icon"><?php echo donghua_icon('search', '', 40); ?></span>
            <h2>لا توجد نتائج مطابقة للبحث</h2>
            <p>تأكد من كتابة الكلمة بشكل صحيح، أو ابحث بالاسم باللغة الإنجليزية أو الصينية (Pinyin).</p>
            <a class="btn-secondary" href="<?php echo esc_url(home_url('/')); ?>">العودة إلى الرئيسية</a>
            <a class="btn-secondary" href="#header-search-input">تجربة عبارة أخرى</a>
        </div>

    <?php endif; ?>
</div>

<?php get_footer(); ?>
