<?php
/**
 * The template for displaying Encyclopedia Type Taxonomy Archives
 * Example: /encyclopedia/type/cultivation-realms/
 */
declare(strict_types=1);

get_header();

$current_term = get_queried_object();
$term_name = ($current_term instanceof \WP_Term) ? $current_term->name : 'تصنيف الموسوعة';
$term_desc = ($current_term instanceof \WP_Term) ? $current_term->description : '';
$term_slug = ($current_term instanceof \WP_Term) ? $current_term->slug : '';

$letter_filter = isset($_GET['letter']) ? sanitize_text_field((string)$_GET['letter']) : '';
$sort_filter   = isset($_GET['sort']) ? sanitize_text_field((string)$_GET['sort']) : 'latest';
$paged         = max(1, get_query_var('paged'), get_query_var('page'));

// All sibling types for quick tabs
$all_types = get_terms([
    'taxonomy'   => 'encyclopedia_type',
    'hide_empty' => true,
]);
?>

<div class="encyclopedia-portal-page taxonomy-encyclopedia-page">

    <!-- Hero Taxonomy Header -->
    <header class="encyclopedia-hero-banner enc-tax-header">
        <div class="container enc-hero-container">
            <div class="enc-breadcrumbs">
                <a href="<?php echo esc_url(home_url('/')); ?>">الرئيسية</a>
                <span class="sep">&rsaquo;</span>
                <a href="<?php echo esc_url(home_url('/encyclopedia/')); ?>">الموسوعة</a>
                <span class="sep">&rsaquo;</span>
                <span class="current"><?php echo esc_html($term_name); ?></span>
            </div>

            <div class="enc-hero-header-box">
                <span class="enc-hero-badge">تصنيف معرفي</span>
                <h1 class="enc-portal-main-title"><?php echo esc_html($term_name); ?></h1>
                <?php if ($term_desc): ?>
                    <p class="enc-portal-main-desc"><?php echo esc_html($term_desc); ?></p>
                <?php else: ?>
                    <p class="enc-portal-main-desc">استكشف جميع المداخل والشروحات المندرجة تحت قسم <?php echo esc_html($term_name); ?> في موسوعة الدونغهوا.</p>
                <?php endif; ?>
            </div>

            <!-- Alphabetical Filter Bar -->
            <div class="enc-alphabet-filter-bar">
                <div class="alphabet-label">
                    <span class="icon">&#128292;</span> تصفح بالحروف:
                </div>
                <div class="alphabet-row alphabet-arabic">
                    <a href="<?php echo esc_url(remove_query_arg('letter')); ?>" class="letter-link <?php echo empty($letter_filter) ? 'active' : ''; ?>">الكل</a>
                    <?php
                    $ar_letters = ['أ', 'ب', 'ت', 'ث', 'ج', 'ح', 'خ', 'د', 'ذ', 'ر', 'ز', 'س', 'ش', 'ص', 'ض', 'ط', 'ظ', 'ع', 'غ', 'ف', 'ق', 'ك', 'ل', 'م', 'ن', 'هـ', 'و', 'ي'];
                    foreach ($ar_letters as $char):
                        $link = add_query_arg('letter', urlencode($char));
                        $active = ($letter_filter === $char) ? 'active' : '';
                    ?>
                        <a href="<?php echo esc_url($link); ?>" class="letter-link <?php echo $active; ?>"><?php echo esc_html($char); ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- Sub-Header Filter Bar (Categories Navigation) -->
    <nav class="encyclopedia-filter-nav" aria-label="أقسام الموسوعة">
        <div class="container enc-filter-container">
            <div class="enc-categories-pills">
                <a href="<?php echo esc_url(home_url('/encyclopedia/')); ?>" class="enc-pill-item">
                    كل الأقسام
                </a>
                <?php if (!empty($all_types) && !is_wp_error($all_types)): ?>
                    <?php foreach ($all_types as $t): ?>
                        <?php
                        $pill_active = ($term_slug === $t->slug) ? 'active' : '';
                        ?>
                        <a href="<?php echo esc_url(get_term_link($t)); ?>" class="enc-pill-item <?php echo $pill_active; ?>">
                            <?php echo esc_html($t->name); ?>
                            <span class="pill-count"><?php echo (int)$t->count; ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="enc-sort-box">
                <label for="enc_tax_sort_select">ترتيب:</label>
                <select id="enc_tax_sort_select" onchange="location = this.value;">
                    <option value="<?php echo esc_url(add_query_arg('sort', 'latest')); ?>" <?php selected($sort_filter, 'latest'); ?>>الأحدث</option>
                    <option value="<?php echo esc_url(add_query_arg('sort', 'views')); ?>" <?php selected($sort_filter, 'views'); ?>>الأكثر قراءة</option>
                    <option value="<?php echo esc_url(add_query_arg('sort', 'alpha')); ?>" <?php selected($sort_filter, 'alpha'); ?>>أبجدياً</option>
                </select>
            </div>
        </div>
    </nav>

    <!-- Content Grid -->
    <div class="container enc-content-layout">
        <section class="enc-entries-section">
            <div class="enc-section-header">
                <h2 class="enc-section-title">
                    <span class="decor-line"></span>
                    مداخل قسم: <?php echo esc_html($term_name); ?>
                    <span class="enc-results-count">(<?php echo (int)$wp_query->found_posts; ?> مدخل)</span>
                </h2>
            </div>

            <?php if (have_posts()): ?>
                <div class="encyclopedia-grid">
                    <?php while (have_posts()): the_post(); ?>
                        <?php get_template_part('template-parts/encyclopedia-card'); ?>
                    <?php endwhile; ?>
                </div>

                <?php if ($wp_query->max_num_pages > 1): ?>
                    <nav class="donghua-pagination enc-pagination" aria-label="صفحات القسم">
                        <?php
                        echo paginate_links([
                            'total'     => $wp_query->max_num_pages,
                            'current'   => $paged,
                            'prev_text' => '&rsaquo; السابق',
                            'next_text' => 'التالي &lsaquo;',
                            'type'      => 'list',
                        ]);
                        ?>
                    </nav>
                <?php endif; ?>

            <?php else: ?>
                <div class="enc-empty-state">
                    <span class="empty-icon">&#128218;</span>
                    <h3>لا توجد مداخل في هذا القسم حالياً</h3>
                    <p>سيتم تحديث هذا القسم قريباً بالمفاهيم والشخصيات المتوافقة.</p>
                    <a href="<?php echo esc_url(home_url('/encyclopedia/')); ?>" class="btn-enc-primary">العودة للموسوعة</a>
                </div>
            <?php endif; ?>
        </section>
    </div>

</div><!-- .encyclopedia-portal-page -->

<?php get_footer(); ?>
