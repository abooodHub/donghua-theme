<?php
/**
 * The template for displaying 404 pages (Not Found)
 * Branded OKanime experience returning true HTTP 404 with search and discovery recommendations.
 */
declare(strict_types=1);

// Ensure proper HTTP status header and prevent caching of 404 responses
status_header(404);
nocache_headers();

get_header();

// Fetch 4 top/popular works for quick discovery
$recommended_query = new WP_Query([
    'post_type'      => 'donghua',
    'posts_per_page' => 4,
    'post_status'    => 'publish',
    'orderby'        => 'meta_value_num',
    'meta_key'       => '_donghua_score',
    'order'          => 'DESC',
    'no_found_rows'  => true,
]);
?>

<div class="site-container-wide">
    <div class="dh-404-hero">
        <div class="dh-404-badge-wrap">
            <span class="dh-404-glitch-digit" aria-label="رمز الخطأ 404">404</span>
        </div>

        <h1 class="dh-404-title" id="dh-404-title">الصفحة غير موجودة</h1>
        <p class="dh-404-desc">
            يبدو أن الرابط غير صحيح أو لم يعد متاحًا. ابحث عن عملك المفضل أو تابع من أحد الأقسام أدناه.
        </p>

        <!-- Search Form -->
        <div class="dh-404-search-box">
            <form role="search" method="get" class="dh-404-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                <label for="dh-404-input" class="screen-reader-text">ابحث في الموقع</label>
                <input type="search" id="dh-404-input" class="dh-404-search-input" placeholder="ابحث عن اسم دونغهوا، شخصية، أو استوديو..." value="" name="s" required />
                <button type="submit" class="dh-404-search-btn" aria-label="تنفيذ البحث">
                    <?php echo donghua_icon('search', '', 18); ?>
                    <span>بحث</span>
                </button>
            </form>
        </div>

        <!-- Quick Links -->
        <div class="dh-404-quick-links" aria-label="روابط الوصول السريع">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="dh-404-link-btn">
                <?php echo donghua_icon('arrow-left', '', 16); ?>
                <span>الرئيسية</span>
            </a>
            <a href="<?php echo esc_url(get_post_type_archive_link('donghua')); ?>" class="dh-404-link-btn">
                <?php echo donghua_icon('film', '', 16); ?>
                <span>دليل الدونغهوا</span>
            </a>
            <a href="<?php echo esc_url(home_url('/schedule/')); ?>" class="dh-404-link-btn">
                <?php echo donghua_icon('calendar', '', 16); ?>
                <span>جدول المواعيد</span>
            </a>
            <a href="<?php echo esc_url(home_url('/encyclopedia/')); ?>" class="dh-404-link-btn">
                <?php echo donghua_icon('book-open', '', 16); ?>
                <span>موسوعة الدونغهوا</span>
            </a>
        </div>
    </div>

    <!-- Recommendations Section -->
    <?php if ($recommended_query->have_posts()): ?>
        <section class="dh-404-recommendations-section site-container" aria-label="أعمال دونغهوا مقترحة">
            <div class="dh-404-sec-header">
                <h2 class="dh-404-sec-title">
                    <?php echo donghua_icon('star', '', 20); ?>
                    <span>أعمال دونغهوا أسطورية قد تعجبك</span>
                </h2>
                <a href="<?php echo esc_url(get_post_type_archive_link('donghua')); ?>" class="btn-more-link">
                    <span>عرض الكل</span>
                    <?php echo donghua_icon('arrow-left', '', 14); ?>
                </a>
            </div>

            <div class="donghua-grid">
                <?php while ($recommended_query->have_posts()): $recommended_query->the_post(); ?>
                    <?php get_template_part('template-parts/donghua-card'); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
