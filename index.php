<?php
/**
 * Fallback main template file
 */
declare(strict_types=1);

get_header();
?>

<div class="main-default-container container">
    <?php if (have_posts()): ?>
        <div class="donghua-grid">
            <?php while (have_posts()): the_post(); ?>
                <?php get_template_part('template-parts/donghua-card'); ?>
            <?php endwhile; ?>
        </div>
        <div class="default-pagination">
            <?php donghua_pagination(); ?>
        </div>
    <?php else: ?>
        <p><?php esc_html_e('لا يوجد محتوى لعرضه حالياً.', 'donghua-theme'); ?></p>
    <?php endif; ?>
</div>

<?php get_footer(); ?>
