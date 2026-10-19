<!DOCTYPE html>
<html <?php language_attributes(); ?> dir="rtl">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>window.okDonghuaDefaultTheme = <?php echo wp_json_encode(donghua_theme_default_mode()); ?>;</script>
    <script><?php readfile(get_template_directory() . '/assets/js/theme.js'); ?></script>
    <?php wp_head(); ?>
</head>
<body <?php body_class('donghua-portal-body'); ?>>
<?php wp_body_open(); ?>
<a class="dh-skip-link" href="#main-content">تخطّ إلى المحتوى</a>

<div id="site-wrapper" class="site-wrapper">
    <!-- Main Site Header — Modern Unified Platform Navigation -->
    <header id="site-header" class="site-header dh-header-v3">
        <div class="header-container">
            <!-- Brand / Logo -->
            <div class="site-branding">
                <?php get_template_part('template-parts/site-logo'); ?>
            </div>

            <!-- Primary Navigation Menu -->
            <nav id="site-navigation" class="site-navigation" aria-label="<?php esc_attr_e('القائمة الرئيسية', 'donghua-theme'); ?>">
                <div class="mobile-nav-header">
                    <span class="mobile-nav-title"><?php esc_html_e('القائمة الرئيسية', 'donghua-theme'); ?></span>
                    <button type="button" id="mobile-nav-close" class="mobile-nav-close-btn" aria-label="<?php esc_attr_e('إغلاق القائمة', 'donghua-theme'); ?>">
                        <?php echo donghua_icon('close', '', 20); ?>
                    </button>
                </div>
                <?php
                if (has_nav_menu('primary')) {
                    wp_nav_menu([
                        'theme_location' => 'primary',
                        'menu_class'     => 'primary-menu',
                        'container'      => false,
                    ]);
                } else {
                    echo '<ul class="primary-menu">';
                    echo '<li class="' . (is_front_page() ? 'current-menu-item' : '') . '"><a href="' . esc_url(home_url('/')) . '">الرئيسية</a></li>';
                    echo '<li class="' . (is_post_type_archive('donghua') ? 'current-menu-item' : '') . '"><a href="' . esc_url(get_post_type_archive_link('donghua')) . '">قائمة الأعمال</a></li>';
                    echo '<li class="' . ((is_page('genres') || is_tax('genre')) ? 'current-menu-item' : '') . '"><a href="' . esc_url(home_url('/genres/')) . '">التصنيفات</a></li>';
                    $movies_url = get_post_type_archive_link('movie') ?: home_url('/movies/');
                    echo '<li class="' . ((is_post_type_archive('movie') || is_singular('movie')) ? 'current-menu-item' : '') . '"><a href="' . esc_url($movies_url) . '">أفلام</a></li>';
                    $encyclopedia_active = is_post_type_archive('encyclopedia') || is_tax('encyclopedia_type') || is_singular('encyclopedia');
                    echo '<li class="' . ($encyclopedia_active ? 'current-menu-item' : '') . '"><a href="' . esc_url(get_post_type_archive_link('encyclopedia')) . '">الموسوعة</a></li>';
                    $vip_active = get_query_var('dhm_portal') === 'pricing';
                    echo '<li class="menu-item menu-item-vip ' . ($vip_active ? 'current-menu-item' : '') . '"><a class="dh-vip-header-link" href="' . esc_url(home_url('/membership/')) . '"><span>VIP</span></a></li>';
                    if (function_exists('donghua_theme_header_link_enabled') && donghua_theme_header_link_enabled('community')) {
                        echo '<li class="' . (is_page('community') ? 'current-menu-item' : '') . '"><a href="' . esc_url(home_url('/community/')) . '">المجتمع</a></li>';
                    }
                    if (function_exists('donghua_theme_header_link_enabled') && donghua_theme_header_link_enabled('news')) {
                        echo '<li class="' . (is_post_type_archive('news') || is_singular('news') ? 'current-menu-item' : '') . '"><a href="' . esc_url(get_post_type_archive_link('news')) . '">الأخبار</a></li>';
                    }
                    echo '</ul>';
                }
                ?>
                <div class="mobile-nav-footer">
                    <?php if (is_user_logged_in()): ?>
                        <?php $current_user = wp_get_current_user(); ?>
                        <div class="mobile-user-row">
                            <a href="<?php echo esc_url(home_url('/account/')); ?>" class="mobile-user-name"><span class="mobile-account-avatar"><?php echo get_avatar($current_user->ID,30,'',$current_user->display_name,['class'=>'dh-header-avatar']); ?></span><span>حسابي · <?php echo esc_html($current_user->display_name); ?></span></a>
                            <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="mobile-auth-btn logout">خروج</a>
                        </div>
                    <?php else: ?>
                        <div class="mobile-auth-actions">
                            <a href="<?php echo esc_url(home_url('/login/')); ?>" class="mobile-auth-btn login">تسجيل الدخول</a>
                            <a href="<?php echo esc_url(home_url('/register/')); ?>" class="mobile-auth-btn register">عضو جديد</a>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>

            <!-- Header Actions: Search + Theme + Auth + Mobile Toggle -->
            <div class="header-actions">
                <!-- Header Live Search Field (IMAGE B Style: Sleek Rounded Pill) -->
                <div class="header-search-wrap" id="header-search-wrap">
                    <button type="button" class="header-search-toggle" id="header-search-toggle" aria-expanded="false" aria-label="<?php esc_attr_e('فتح البحث', 'donghua-theme'); ?>" aria-controls="header-search-form">
                        <?php echo donghua_icon('search', '', 18); ?>
                    </button>
                    <form role="search" method="get" id="header-search-form" class="header-search-form subbar-search-form" action="<?php echo esc_url(home_url('/')); ?>">
                        <label class="screen-reader-text" for="header-search-input"><?php esc_html_e('البحث في الدونغهوا', 'donghua-theme'); ?></label>
                        <span class="header-search-icon" aria-hidden="true"><?php echo donghua_icon('search', '', 15); ?></span>
                        <input type="search" id="header-search-input" class="header-search-input" aria-controls="live-search-results" aria-expanded="false" placeholder="<?php esc_attr_e('ابحث عن اسم عمل دونغهوا...', 'donghua-theme'); ?>" value="<?php echo get_search_query(); ?>" name="s" autocomplete="off" />
                        <button type="button" class="header-search-close" id="header-search-close" aria-label="<?php esc_attr_e('إغلاق البحث', 'donghua-theme'); ?>">
                            <?php echo donghua_icon('close', '', 16); ?>
                        </button>
                    </form>
                    <div id="live-search-results" class="live-search-results" role="region" aria-label="نتائج البحث السريع" aria-live="polite"></div>
                </div>

                <!-- Compact all-dark appearance selector -->
                <div class="appearance-switcher" data-appearance-switcher>
                    <button type="button" class="appearance-trigger" id="appearance-trigger" aria-label="اختيار المظهر" title="اختيار المظهر" aria-haspopup="menu" aria-expanded="false" aria-controls="appearance-menu">
                        <span class="appearance-current-dot" aria-hidden="true"></span>
                    </button>
                    <div class="appearance-menu" id="appearance-menu" role="menu" aria-label="اختيار المظهر" hidden>
                        <div class="appearance-menu-heading">المظهر</div>
                        <button type="button" class="appearance-option" role="menuitemradio" aria-checked="true" data-theme-choice="night-blue"><span class="appearance-option-dot" aria-hidden="true"></span><span>ليل أزرق</span><span class="appearance-check" aria-hidden="true">✓</span></button>
                        <button type="button" class="appearance-option" role="menuitemradio" aria-checked="false" data-theme-choice="jade"><span class="appearance-option-dot" aria-hidden="true"></span><span>زمردي</span><span class="appearance-check" aria-hidden="true">✓</span></button>
                        <button type="button" class="appearance-option" role="menuitemradio" aria-checked="false" data-theme-choice="moon"><span class="appearance-option-dot" aria-hidden="true"></span><span>قمر نيلي</span><span class="appearance-check" aria-hidden="true">✓</span></button>
                        <button type="button" class="appearance-option" role="menuitemradio" aria-checked="false" data-theme-choice="charcoal"><span class="appearance-option-dot" aria-hidden="true"></span><span>فحمي</span><span class="appearance-check" aria-hidden="true">✓</span></button>
                    </div>
                </div>

                <!-- User Account / Auth (IMAGE B Style: [ 👤 حسابي ⌵ ]) -->
                <div class="header-user-auth">
                    <?php if (is_user_logged_in()): ?>
                        <?php $current_user = wp_get_current_user(); ?>
                        <a href="<?php echo esc_url(home_url('/account/')); ?>" class="header-account-pill is-logged-in" title="حسابي — <?php echo esc_attr($current_user->display_name); ?>">
                            <span class="user-avatar-icon"><?php echo get_avatar($current_user->ID,24,'',$current_user->display_name,['class'=>'dh-header-avatar']); ?></span>
                            <span class="account-pill-label"><?php echo esc_html($current_user->display_name); ?></span>
                            <span class="account-pill-chevron" aria-hidden="true">&#9662;</span>
                        </a>
                        <a href="<?php echo esc_url(wp_logout_url(home_url())); ?>" class="header-logout-quick" title="تسجيل الخروج" aria-label="تسجيل الخروج">
                            <?php echo donghua_icon('logout', '', 12); ?>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo esc_url(home_url('/login/')); ?>" class="header-account-pill is-guest" title="حسابي / تسجيل الدخول">
                            <span class="user-avatar-icon"><?php echo donghua_icon('user', '', 14); ?></span>
                            <span class="account-pill-label">حسابي</span>
                            <span class="account-pill-chevron" aria-hidden="true">&#9662;</span>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Toggle Button -->
                <div class="header-mobile-trigger">
                    <button type="button"
                            id="mobile-menu-toggle"
                            class="mobile-menu-toggle"
                            aria-controls="site-navigation"
                            aria-expanded="false"
                            aria-label="فتح القائمة الرئيسية">
                        <span></span><span></span><span></span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Nav Backdrop Overlay -->
    <div id="mobile-nav-backdrop" class="mobile-nav-backdrop" aria-hidden="true"></div>

    <!-- Main Content Container Wrapper -->
    <main id="main-content" class="main-content" tabindex="-1">
