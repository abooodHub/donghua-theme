<?php
/** Compact current-user resume rail for the homepage. */
defined('ABSPATH') || exit;
$items = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
$standalone = !empty($args['standalone']);
?>
<section class="portal-section home-continue-section" id="continue-watching" aria-labelledby="continue-watching-title">
    <?php if ($standalone): ?>
        <div class="portal-section-header">
            <div class="header-title-box">
                <span class="header-accent-dot" aria-hidden="true"></span>
                <h2 class="portal-section-title" id="continue-watching-title">متابعة المشاهدة</h2>
            </div>
            <a class="section-view-all" href="<?php echo esc_url(add_query_arg('account_view', 'continue', home_url('/account/'))); ?>">عرض الكل <span class="view-all-arrow" aria-hidden="true">&lsaquo;</span></a>
        </div>
    <?php endif; ?>
    <?php if ($items): ?>
    <div class="home-continue-grid">
        <?php foreach ($items as $item):
            $percent = max(0, min(100, (int)($item['progress_percent'] ?? 0)));
            $episode_label = !empty($item['episode_number']) ? 'الحلقة '.$item['episode_number'] : (string)($item['episode_title'] ?? '');
            $last_watched = isset($item['updated_at']) ? human_time_diff(strtotime((string)$item['updated_at'].' UTC') ?: time(), current_time('timestamp')) : '';
        ?>
            <article class="home-continue-item">
                <a class="home-continue-thumb" href="<?php echo esc_url($item['episode_url'] ?? ''); ?>" aria-label="<?php echo esc_attr('متابعة '.$item['donghua_title'].' · '.$episode_label); ?>">
                    <?php if (!empty($item['poster'])): ?><img src="<?php echo esc_url($item['poster']); ?>" alt="" loading="lazy" decoding="async" width="240" height="135"><?php else: ?><span class="home-continue-placeholder" aria-hidden="true"><?php echo donghua_icon('play', '', 20); ?></span><?php endif; ?>
                </a>
                <div class="home-continue-copy">
                    <h3><a href="<?php echo esc_url($item['episode_url'] ?? ''); ?>"><?php echo esc_html($item['donghua_title'] ?? ''); ?></a></h3>
                    <p><?php echo esc_html($episode_label); ?> <span aria-hidden="true">·</span> <?php echo esc_html(number_format_i18n($percent)); ?>%</p>
                    <div class="home-continue-meta">
                        <span class="home-continue-bar" role="progressbar" aria-label="تقدم المشاهدة" aria-valuenow="<?php echo esc_attr((string)$percent); ?>" aria-valuemin="0" aria-valuemax="100"><i style="width:<?php echo esc_attr((string)$percent); ?>%"></i></span>
                        <?php if ($last_watched !== ''): ?><small><?php echo esc_html('منذ '.$last_watched); ?></small><?php endif; ?>
                    </div>
                    <a class="home-continue-resume" href="<?php echo esc_url($item['episode_url'] ?? ''); ?>">استئناف المشاهدة</a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="home-continue-empty">
            <span class="home-continue-empty-icon" aria-hidden="true"><?php echo donghua_icon('play', '', 20); ?></span>
            <div>
                <strong><?php echo is_user_logged_in() ? 'لا توجد مشاهدة محفوظة بعد' : 'تابع المشاهدة من حيث توقفت'; ?></strong>
                <p><?php echo is_user_logged_in() ? 'ابدأ مشاهدة إحدى الحلقات وستظهر هنا.' : 'سجّل الدخول لحفظ تقدمك والعودة إلى حلقاتك بسهولة.'; ?></p>
            </div>
            <a href="<?php echo esc_url(home_url(is_user_logged_in() ? '/donghua/' : '/account/')); ?>"><?php echo is_user_logged_in() ? 'استكشف الأعمال' : 'تسجيل الدخول'; ?></a>
        </div>
    <?php endif; ?>
</section>
