<?php
declare(strict_types=1);

get_header();
$genres = get_terms(['taxonomy' => 'genre', 'hide_empty' => true, 'object_ids' => get_posts(['post_type' => 'movie', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids'])]);
$active_genre = sanitize_title(wp_unslash($_GET['genre'] ?? ''));
$active_year = sanitize_text_field(wp_unslash($_GET['year'] ?? ''));
$page = max(1, (int) get_query_var('paged'));
$tax_query = [];
if ($active_genre !== '') $tax_query[] = ['taxonomy' => 'genre', 'field' => 'slug', 'terms' => $active_genre];
if ($active_year !== '') $tax_query[] = ['taxonomy' => 'year', 'field' => 'slug', 'terms' => $active_year];
$movie_query = new WP_Query([
    'post_type' => 'movie', 'post_status' => 'publish', 'posts_per_page' => 24, 'paged' => $page,
    'tax_query' => count($tax_query) > 1 ? array_merge(['relation' => 'AND'], $tax_query) : $tax_query,
    'orderby' => 'date', 'order' => 'DESC',
]);
?>
<main class="movies-archive site-container">
    <header class="movies-archive-heading">
        <span class="movies-eyebrow">OK.DONGHUA · CINEMA</span>
        <h1>أفلام الدونغهوا</h1>
        <p>اكتشف الأفلام، واقرأ تفاصيلها، واختر حكايتك القادمة.</p>
    </header>
    <form class="movies-filters" method="get" action="<?php echo esc_url(get_post_type_archive_link('movie')); ?>">
        <label>التصنيف
            <select name="genre"><option value="">كل التصنيفات</option>
                <?php foreach ($genres as $genre): ?><option value="<?php echo esc_attr($genre->slug); ?>" <?php selected($active_genre, $genre->slug); ?>><?php echo esc_html($genre->name); ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>السنة <input type="number" name="year" min="1900" max="2100" value="<?php echo esc_attr($active_year); ?>" placeholder="كل السنوات"></label>
        <button type="submit">تطبيق</button>
        <?php if ($active_genre || $active_year): ?><a href="<?php echo esc_url(get_post_type_archive_link('movie')); ?>">مسح التصفية</a><?php endif; ?>
    </form>
    <p class="movies-result-count">وجدنا <?php echo esc_html(number_format_i18n((int) $movie_query->found_posts)); ?> فيلمًا</p>
    <?php if ($movie_query->have_posts()): ?>
        <div class="movie-grid">
            <?php while ($movie_query->have_posts()): $movie_query->the_post(); get_template_part('template-parts/movie-card'); endwhile; ?>
        </div>
        <nav class="movies-pagination" aria-label="صفحات الأفلام">
            <?php echo wp_kses_post(paginate_links(['total' => $movie_query->max_num_pages, 'current' => $page, 'type' => 'list'])); ?>
        </nav>
    <?php else: ?>
        <div class="movies-empty"><span aria-hidden="true">✦</span><h2>لا توجد أفلام هنا بعد</h2><p>جرّب تصنيفًا أو سنة مختلفة.</p></div>
    <?php endif; wp_reset_postdata(); ?>
</main>
<?php get_footer();
