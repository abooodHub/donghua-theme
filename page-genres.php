<?php
/** Public work browser with randomized cards and genre filtering. */
declare(strict_types=1);

get_header();

$genre_order = [
    'action', 'strategy', 'historical', 'chihuan', 'fantasy', 'science-fiction',
    'drama', 'horror', 'romance', 'xuanhuan', 'xianxia', 'mystery', 'comedy',
    'adventure', 'wuxia',
];
$genre_terms = [];
foreach ($genre_order as $genre_slug) {
    $genre_term = get_term_by('slug', $genre_slug, 'genre');
    if ($genre_term instanceof WP_Term) $genre_terms[] = $genre_term;
}

$genre_input = isset($_GET['genre']) && is_string($_GET['genre']) ? wp_unslash($_GET['genre']) : 'all';
$selected_genre_slug = sanitize_title($genre_input);
$selected_genre = $selected_genre_slug !== '' && $selected_genre_slug !== 'all'
    ? get_term_by('slug', $selected_genre_slug, 'genre')
    : null;
if (!$selected_genre instanceof WP_Term || $selected_genre->taxonomy !== 'genre') {
    $selected_genre = null;
    $selected_genre_slug = 'all';
}

$work_query_args = [
    'post_type'              => 'donghua',
    'post_status'            => 'publish',
    'posts_per_page'         => -1,
    'orderby'                => 'rand',
    'has_password'           => false,
    'ignore_sticky_posts'    => true,
    'no_found_rows'          => true,
    'update_post_meta_cache' => true,
    'update_post_term_cache' => true,
];
if ($selected_genre) {
    $work_query_args['tax_query'] = [[
        'taxonomy' => 'genre',
        'field'    => 'term_id',
        'terms'    => (int) $selected_genre->term_id,
    ]];
}
$genre_works_query = new WP_Query($work_query_args);
$genre_work_ids = array_map('intval', wp_list_pluck($genre_works_query->posts, 'ID'));
$genre_episode_summaries = $genre_work_ids
    ? \DonghuaCore\Meta\EpisodeMeta::get_public_card_episodes($genre_work_ids)
    : [];
$genres_page_url = get_permalink(get_queried_object_id());
?>

<div class="genres-directory site-container">
    <header class="genres-directory-hero">
        <div class="genres-directory-hero-copy">
            <span class="genres-directory-kicker"><span aria-hidden="true">✦</span> دليل المشاهدة</span>
            <h1>اعثر على عملك القادم حسب التصنيف</h1>
            <p>اختر التصنيف الذي يناسبك، وتصفّح الأعمال المرتبطة به في مكان واحد.</p>
            <div class="genres-directory-hero-meta">
                <span><strong><?php echo esc_html(number_format_i18n(count($genre_terms))); ?></strong> تصنيفًا</span>
                <span>تظهر الأعمال المطابقة عند اختيار تصنيف</span>
            </div>
        </div>
        <form class="genres-directory-select" method="get" action="<?php echo esc_url($genres_page_url); ?>">
            <label for="genre-directory-select">اذهب مباشرة إلى تصنيف</label>
            <div class="genres-directory-select-control">
                <select id="genre-directory-select" name="genre">
                    <option value="all" <?php selected($selected_genre_slug, 'all'); ?>>الكل</option>
                    <?php foreach ($genre_terms as $genre_term): ?>
                        <option value="<?php echo esc_attr($genre_term->slug); ?>" <?php selected($selected_genre_slug, $genre_term->slug); ?>><?php echo esc_html($genre_term->name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit"><span aria-hidden="true">⌕</span> اعرض الأعمال</button>
            </div>
        </form>
    </header>

    <section class="genres-directory-categories" aria-labelledby="genres-directory-categories-title">
        <div class="genres-directory-categories-heading">
            <div><span class="header-accent-dot" aria-hidden="true"></span><h2 id="genres-directory-categories-title">تصفّح التصنيفات</h2></div>
            <span><?php echo esc_html(sprintf('%s تصنيفًا', number_format_i18n(count($genre_terms)))); ?></span>
        </div>
        <nav class="genres-directory-category-grid" aria-label="روابط تصنيفات الأعمال">
            <a class="genres-directory-category<?php echo $selected_genre_slug === 'all' ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('genre', 'all', $genres_page_url)); ?>"<?php echo $selected_genre_slug === 'all' ? ' aria-current="page"' : ''; ?>>
                <span class="genres-directory-category-name">كل الأعمال</span>
                <span class="genres-directory-category-count"><?php echo esc_html(number_format_i18n(count($genre_work_ids))); ?></span>
            </a>
            <?php foreach ($genre_terms as $genre_term): ?>
                <?php $term_url = add_query_arg('genre', $genre_term->slug, $genres_page_url); ?>
                <a class="genres-directory-category<?php echo $selected_genre_slug === $genre_term->slug ? ' is-active' : ''; ?>" href="<?php echo esc_url($term_url); ?>"<?php echo $selected_genre_slug === $genre_term->slug ? ' aria-current="page"' : ''; ?>>
                    <span class="genres-directory-category-name"><?php echo esc_html($genre_term->name); ?></span>
                    <span class="genres-directory-category-count"><?php echo esc_html(number_format_i18n((int) $genre_term->count)); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    </section>

    <section class="genres-directory-section" aria-labelledby="genres-directory-title">
        <div class="genres-directory-section-heading">
            <div>
                <span class="header-accent-dot" aria-hidden="true"></span>
                <h2 id="genres-directory-title"><?php echo $selected_genre ? esc_html($selected_genre->name) : 'كل الأعمال'; ?></h2>
            </div>
            <span><?php echo esc_html(sprintf('%s عمل', number_format_i18n(count($genre_work_ids)))); ?></span>
        </div>

        <?php if ($genre_works_query->have_posts()): ?>
            <div class="donghua-grid genres-directory-works-grid">
                <?php while ($genre_works_query->have_posts()): $genre_works_query->the_post(); ?>
                    <?php get_template_part('template-parts/donghua-card', null, ['episode_summaries' => $genre_episode_summaries]); ?>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        <?php else: ?>
            <div class="genres-directory-empty">
                <strong>لا توجد أعمال مرتبطة بهذا التصنيف حتى الآن.</strong>
                <p>اختر تصنيفًا آخر أو اعرض كل الأعمال.</p>
                <a href="<?php echo esc_url(add_query_arg('genre', 'all', $genres_page_url)); ?>">عرض كل الأعمال</a>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php get_footer(); ?>
