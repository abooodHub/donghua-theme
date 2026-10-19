    </main><!-- #main-content -->

    <?php
    $is_checkout_footer = in_array((string) get_query_var('dhm_portal'), ['pricing', 'membership'], true);
    if (!$is_checkout_footer && is_singular()) {
        $footer_content = (string) get_post_field('post_content', get_queried_object_id());
        $is_checkout_footer = has_shortcode($footer_content, 'dh_membership_pricing') || has_shortcode($footer_content, 'dh_my_membership');
    }
    if ($is_checkout_footer) {
        $checkout_privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
        $checkout_terms_page = get_page_by_path('terms');
        $checkout_terms_url = $checkout_terms_page instanceof WP_Post && $checkout_terms_page->post_status === 'publish' ? get_permalink($checkout_terms_page) : '';
        $checkout_help_page = get_page_by_path('contact');
        if (!($checkout_help_page instanceof WP_Post) || $checkout_help_page->post_status !== 'publish') {
            $checkout_help_page = get_page_by_path('help');
        }
        $checkout_help_url = $checkout_help_page instanceof WP_Post && $checkout_help_page->post_status === 'publish' ? get_permalink($checkout_help_page) : '';
    }
    ?>
    <!-- Site Footer (Prestigious OKanime Dark Footer) -->
    <footer id="site-footer" class="site-footer<?php echo $is_checkout_footer ? ' site-footer-checkout' : ''; ?>">
        <div class="footer-container">
            <?php if ($is_checkout_footer) : ?>
                <div class="footer-checkout-row">
                    <div class="footer-checkout-brand">
                        <div class="footer-logo"><?php get_template_part('template-parts/site-logo'); ?></div>
                    </div>
                    <?php if ($checkout_privacy_url || $checkout_terms_url || $checkout_help_url) : ?>
                        <nav class="footer-checkout-links" aria-label="روابط قانونية ومساعدة">
                            <?php if ($checkout_privacy_url) : ?><a href="<?php echo esc_url($checkout_privacy_url); ?>">سياسة الخصوصية</a><?php endif; ?>
                            <?php if ($checkout_terms_url) : ?><a href="<?php echo esc_url($checkout_terms_url); ?>">الشروط والأحكام</a><?php endif; ?>
                            <?php if ($checkout_help_url) : ?><a href="<?php echo esc_url($checkout_help_url); ?>">المساعدة والتواصل</a><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                    <p class="footer-checkout-copy">&copy; <?php echo esc_html((string) wp_date('Y')); ?> <strong><?php echo esc_html(get_bloginfo('name') ?: 'OKdonghua'); ?></strong>. جميع الحقوق محفوظة.</p>
                </div>
            <?php else : ?>
            <div class="footer-grid-columns">
                <!-- Col 1: About & Logo -->
                <div class="footer-col col-about">
                    <div class="footer-logo">
                        <?php get_template_part('template-parts/site-logo'); ?>
                    </div>
                    <p class="footer-desc">
                        دليلك العربي لمتابعة أعمال الدونغهوا واكتشاف عوالمها.
                    </p>
                    <p class="footer-dmca-note">
                        جميع الحقوق محفوظة لأصحاب الأعمال الأصلية. الموقع يقدم روابط خارجية ولا يستضيف أي مواد على خوادمه.
                    </p>
                </div>

                <!-- Col 2: Quick Navigation Links -->
                <div class="footer-col col-links">
                    <h2 class="footer-col-title">تصفّح المنصة</h2>
                    <ul class="footer-list">
                        <li><a href="<?php echo esc_url(home_url('/')); ?>">الصفحة الرئيسية</a></li>
                        <li><a href="<?php echo esc_url(get_post_type_archive_link('donghua')); ?>">دليل الدونغهوا</a></li>
                        <li><a href="<?php echo esc_url(home_url('/genres/')); ?>">التصنيفات</a></li>
                        <li><a href="<?php echo esc_url(home_url('/schedule/')); ?>">جدول المواعيد الأسبوعي</a></li>
                        <li><a href="<?php echo esc_url(home_url('/encyclopedia/')); ?>">موسوعة الدونغهوا</a></li>
                        <li><a href="<?php echo esc_url(get_post_type_archive_link('news')); ?>">أخبار ومقالات</a></li>
                        <li><a href="<?php echo esc_url(home_url('/account/')); ?>">حسابي ومكتبتي</a></li>
                        <li><a href="<?php echo esc_url(home_url('/membership/')); ?>">العضوية</a></li>
                    </ul>
                </div>

                <!-- Col 3: Categories & Types -->
                <div class="footer-col col-categories">
                    <h2 class="footer-col-title">أقسام الدونغهوا</h2>
                    <ul class="footer-list">
                        <li><a href="<?php echo esc_url(get_post_type_archive_link('movie') ?: home_url('/movies/')); ?>">الأفلام</a></li>
                        <li><a href="<?php echo esc_url(home_url('/animation-type/2d/')); ?>">دونغهوا 2D (مرسومة)</a></li>
                        <li><a href="<?php echo esc_url(home_url('/status/ongoing/')); ?>">أعمال مستمرة البث</a></li>
                        <li><a href="<?php echo esc_url(home_url('/status/completed/')); ?>">أعمال مكتملة الحلقات</a></li>
                        <li><a href="<?php echo esc_url(home_url('/status/upcoming/')); ?>">أعمال مرتقبة وقادمة</a></li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom Copyright Bar -->
            <div class="footer-bottom-bar">
                <div class="footer-copy-text">
                    &copy; <?php echo esc_html((string)wp_date('Y')); ?> <strong><?php echo esc_html(get_bloginfo('name') ?: 'OKdonghua'); ?></strong>. جميع الحقوق محفوظة.
                </div>
                <button type="button" id="back-to-top" class="back-to-top-btn" aria-label="العودة لأعلى الصفحة" title="العودة لأعلى الصفحة">
                    <?php echo donghua_icon('arrow-up', '', 18); ?>
                    <span class="screen-reader-text">العودة لأعلى الصفحة</span>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </footer>
</div><!-- #site-wrapper -->

<?php wp_footer(); ?>
</body>
</html>
