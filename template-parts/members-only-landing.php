<?php
/** Lightweight public entrance shown only to anonymous members-only homepage visitors. */
defined('ABSPATH') || exit;
$registration_open = class_exists('DHM_Site_Control') && DHM_Site_Control::registration_enabled();
$login_url = add_query_arg('redirect_to', home_url('/'), home_url('/login/'));
$privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
$terms_page = get_page_by_path('terms');
$terms_url = $terms_page instanceof WP_Post && $terms_page->post_status === 'publish' ? get_permalink($terms_page) : '';
$logo_url = get_template_directory_uri() . '/assets/images/brand/okdonghua-logo-full.png';
$theme_script = get_template_directory() . '/assets/js/theme.js';
?>
<!doctype html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script>window.okDonghuaDefaultTheme = <?php echo wp_json_encode(function_exists('donghua_theme_default_mode') ? donghua_theme_default_mode() : 'night-blue'); ?>;</script>
    <?php if (is_file($theme_script)): ?><script><?php readfile($theme_script); ?></script><?php endif; ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class('donghua-portal-body members-only-landing'); ?>>
<?php wp_body_open(); ?>
<a class="dhm-landing-skip" href="#dhm-landing-main"><?php esc_html_e('تخطّ إلى المحتوى', 'donghua-theme'); ?></a>
<div class="dhm-landing-shell">
    <header class="dhm-landing-header" aria-label="OK.DONGHUA">
        <a class="dhm-landing-brand" href="<?php echo esc_url(home_url('/')); ?>" aria-label="OK.DONGHUA">
            <img src="<?php echo esc_url($logo_url); ?>" alt="OK.DONGHUA" width="2172" height="724" fetchpriority="high">
        </a>
        <nav class="dhm-landing-nav" aria-label="<?php esc_attr_e('حسابك', 'donghua-theme'); ?>">
            <a class="dhm-landing-nav-login" href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('تسجيل الدخول', 'donghua-theme'); ?></a>
            <?php if ($registration_open): ?><a class="dhm-landing-nav-register" href="<?php echo esc_url(home_url('/register/')); ?>"><?php esc_html_e('إنشاء حساب', 'donghua-theme'); ?></a><?php endif; ?>
        </nav>
    </header>
    <main id="dhm-landing-main" class="dhm-landing-main">
        <section class="dhm-landing-hero" aria-labelledby="dhm-landing-title">
            <p class="dhm-landing-eyebrow">للأعضاء فقط</p>
            <h1 id="dhm-landing-title"><?php esc_html_e('عالم الدونغهوا يبدأ من هنا', 'donghua-theme'); ?></h1>
            <p class="dhm-landing-description"><?php esc_html_e('سجّل دخولك للوصول إلى الأعمال والحلقات ومكتبتك الشخصية ومتابعة المشاهدة.', 'donghua-theme'); ?></p>
            <div class="dhm-landing-actions">
                <a class="dhm-landing-primary" href="<?php echo esc_url($login_url); ?>"><?php esc_html_e('تسجيل الدخول', 'donghua-theme'); ?></a>
                <?php if ($registration_open): ?>
                    <a class="dhm-landing-secondary" href="<?php echo esc_url(home_url('/register/')); ?>"><?php esc_html_e('إنشاء حساب', 'donghua-theme'); ?></a>
                <?php else: ?>
                    <p class="dhm-landing-registration-note" role="status"><?php esc_html_e('التسجيل مغلق حاليًا.', 'donghua-theme'); ?></p>
                <?php endif; ?>
            </div>
            <ul class="dhm-landing-benefits" aria-label="<?php esc_attr_e('مزايا العضوية', 'donghua-theme'); ?>">
                <li><?php esc_html_e('متابعة المشاهدة', 'donghua-theme'); ?></li>
                <li><?php esc_html_e('مكتبتك الشخصية', 'donghua-theme'); ?></li>
                <li><?php esc_html_e('محتوى الأعضاء', 'donghua-theme'); ?></li>
            </ul>
        </section>
    </main>
    <footer class="dhm-landing-footer">
        <span>© <?php echo esc_html((string)wp_date('Y')); ?> OK.DONGHUA</span>
        <nav aria-label="<?php esc_attr_e('روابط قانونية', 'donghua-theme'); ?>">
            <?php if ($privacy_url): ?><a href="<?php echo esc_url($privacy_url); ?>"><?php esc_html_e('الخصوصية', 'donghua-theme'); ?></a><?php endif; ?>
            <?php if ($terms_url): ?><a href="<?php echo esc_url($terms_url); ?>"><?php esc_html_e('الشروط والأحكام', 'donghua-theme'); ?></a><?php endif; ?>
        </nav>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
