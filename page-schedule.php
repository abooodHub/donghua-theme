<?php
/**
 * Template Name: جدول المواعيد الأسبوعي
 * Description: Public weekly Donghua release schedule.
 */
declare(strict_types=1);

get_header();

$schedule_days = [0 => ['slug' => 'sunday', 'label' => 'الأحد'], 1 => ['slug' => 'monday', 'label' => 'الاثنين'], 2 => ['slug' => 'tuesday', 'label' => 'الثلاثاء'], 3 => ['slug' => 'wednesday', 'label' => 'الأربعاء'], 4 => ['slug' => 'thursday', 'label' => 'الخميس'], 5 => ['slug' => 'friday', 'label' => 'الجمعة'], 6 => ['slug' => 'saturday', 'label' => 'السبت']];
$today = current_datetime();
$today_index = (int) $today->format('w');
$start_of_week = (int) get_option('start_of_week', 1);
$week_order = [];
for ($offset = 0; $offset < 7; $offset++) $week_order[] = ($start_of_week + $offset) % 7;
$requested_day = isset($_GET['day']) && is_scalar($_GET['day']) ? sanitize_key(wp_unslash($_GET['day'])) : '';
$day_by_slug = [];
foreach ($schedule_days as $day_index => $day) $day_by_slug[$day['slug']] = $day_index;
$active_day = isset($day_by_slug[$requested_day]) ? $day_by_slug[$requested_day] : $today_index;
$following_filter = isset($_GET['filter']) && is_scalar($_GET['filter']) && sanitize_key(wp_unslash($_GET['filter'])) === 'following';
$logged_in = is_user_logged_in();
if ($following_filter && !$logged_in) $following_filter = false;

$schedule_query = new WP_Query([
    'post_type' => 'donghua', 'post_status' => 'publish', 'posts_per_page' => -1,
    'orderby' => 'title', 'order' => 'ASC', 'has_password' => false,
    'no_found_rows' => true, 'ignore_sticky_posts' => true,
    'update_post_meta_cache' => true, 'update_post_term_cache' => true,
    'meta_query' => [
        ['key' => '_donghua_schedule_enabled', 'value' => '1', 'compare' => '='],
        ['key' => '_donghua_schedule_weekday', 'value' => ['0','1','2','3','4','5','6'], 'compare' => 'IN'],
    ],
]);
$works_by_day = array_fill_keys(array_keys($schedule_days), []);
$work_ids = [];
foreach ($schedule_query->posts as $work) {
    if ($work->post_password !== '') continue;
    $weekday = get_post_meta($work->ID, '_donghua_schedule_weekday', true);
    if (!ctype_digit((string) $weekday) || !isset($works_by_day[(int) $weekday])) continue;
    $works_by_day[(int) $weekday][] = $work;
    $work_ids[] = (int) $work->ID;
}
$user_states = [];
if ($logged_in && class_exists('\\DonghuaCore\\Api\\UserLibrary')) {
    foreach (array_chunk($work_ids, 100) as $id_chunk) $user_states += \DonghuaCore\Api\UserLibrary::state_for_user(get_current_user_id(), $id_chunk);
}
$following_filter = $following_filter && $logged_in;
$episodes_by_work = [];
$episode_numbers = [];
foreach ($work_ids as $work_id) {
    $episode_numbers[$work_id] = (string) get_post_meta($work_id, '_donghua_schedule_episode_number', true);
}
$episode_work_ids = array_values(array_filter($work_ids, static fn($id) => $episode_numbers[$id] !== ''));
if ($episode_work_ids) {
    $episode_query = new WP_Query([
        'post_type' => 'episode', 'post_status' => 'publish', 'posts_per_page' => -1,
        'no_found_rows' => true, 'has_password' => false, 'ignore_sticky_posts' => true,
        'update_post_meta_cache' => true, 'update_post_term_cache' => false,
        'meta_query' => [['key' => '_episode_parent_donghua', 'value' => $episode_work_ids, 'compare' => 'IN']],
    ]);
    foreach ($episode_query->posts as $episode) {
        if ($episode->post_password !== '') continue;
        $parent_id = (int) get_post_meta($episode->ID, '_episode_parent_donghua', true);
        $number = (string) get_post_meta($episode->ID, '_episode_number', true);
        if (!isset($episode_numbers[$parent_id]) || $episode_numbers[$parent_id] === '' || $number !== $episode_numbers[$parent_id]) continue;
        if (!isset($episodes_by_work[$parent_id])) $episodes_by_work[$parent_id] = get_permalink($episode);
    }
}
$base_url = get_permalink(get_queried_object_id());
$following_url = add_query_arg(['day' => $schedule_days[$active_day]['slug'], 'filter' => 'following'], $base_url);
$login_following_url = add_query_arg('redirect_to', rawurlencode($following_url), home_url('/login/'));
$week_count = count($work_ids);
$has_any_following = false;
if ($logged_in && class_exists('\\DonghuaCore\\Api\\UserLibrary')) {
    foreach ($user_states as $state) if (in_array('following', $state, true)) { $has_any_following = true; break; }
}
if ($following_filter && !$has_any_following) {
    global $wpdb;
    $has_any_following = (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT 1 FROM {$wpdb->prefix}donghua_user_lists WHERE user_id = %d AND list_type = %s LIMIT 1",
        get_current_user_id(), 'following'
    ));
}
$today_week_offset = ($today_index - $start_of_week + 7) % 7;
$week_start = $today->setTime(0, 0)->modify('-' . $today_week_offset . ' days');
$public_poster_fallback = get_theme_file_uri('assets/images/brand/okdonghua-logo-full.png');
?>

<div class="schedule-page-container site-container">
    <header class="schedule-page-header">
        <span class="schedule-eyebrow"><?php echo esc_html(wp_date('F Y', $today->getTimestamp(), wp_timezone())); ?></span>
        <h1 class="schedule-main-title">جدول مواعيد الدونغهوا الأسبوعي</h1>
        <p class="schedule-subtitle">تابع مواعيد حلقات أعمال الدونغهوا خلال الأسبوع.</p>
        <p class="schedule-week-count"><?php echo esc_html(number_format_i18n($week_count)); ?> عملًا مجدولًا هذا الأسبوع</p>
    </header>

    <nav class="schedule-day-rail" aria-label="أيام جدول العرض الأسبوعي">
        <?php foreach ($week_order as $day_index): $day = $schedule_days[$day_index]; $is_active = $day_index === $active_day; $is_today = $day_index === $today_index; ?>
            <a class="schedule-day-tab<?php echo $is_active ? ' is-active' : ''; ?><?php echo $is_today ? ' is-today' : ''; ?>" href="<?php echo esc_url(add_query_arg('day', $day['slug'], remove_query_arg('filter', $base_url))); ?>" <?php echo $is_active ? 'aria-current="date"' : ''; ?>>
                <span><?php echo esc_html($day['label']); ?></span><?php if ($is_today): ?><small>اليوم</small><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="schedule-filter-row" aria-label="تصفية الجدول">
        <a class="schedule-filter-tab<?php echo !$following_filter ? ' is-active' : ''; ?>" href="<?php echo esc_url(add_query_arg('day', $schedule_days[$active_day]['slug'], remove_query_arg('filter', $base_url))); ?>" <?php echo !$following_filter ? 'aria-current="page"' : ''; ?>>كل الأعمال</a>
        <?php if ($logged_in): ?>
            <a class="schedule-filter-tab<?php echo $following_filter ? ' is-active' : ''; ?>" href="<?php echo esc_url($following_url); ?>" <?php echo $following_filter ? 'aria-current="page"' : ''; ?>>أعمال أتابعها</a>
        <?php else: ?>
            <a class="schedule-filter-tab" href="<?php echo esc_url($login_following_url); ?>">أعمال أتابعها</a>
        <?php endif; ?>
    </div>

    <section class="schedule-day-panel" aria-labelledby="schedule-day-heading">
        <div class="schedule-day-panel-heading"><h2 id="schedule-day-heading"><?php echo esc_html($schedule_days[$active_day]['label']); ?><?php echo $active_day === $today_index ? ' — اليوم' : ''; ?></h2><span><?php echo esc_html(number_format_i18n(count($works_by_day[$active_day]))); ?> أعمال</span></div>
        <div class="schedule-item-list">
            <?php
            $visible_count = 0;
            foreach ($works_by_day[$active_day] as $work):
                $work_id = (int) $work->ID;
                $states = $user_states[$work_id] ?? [];
                $is_following = in_array('following', $states, true);
                if ($following_filter && !$is_following) continue;
                $visible_count++;
                $work_title = get_post_meta($work_id, '_donghua_ar_name', true) ?: get_the_title($work_id);
                $poster = function_exists('donghua_get_poster_url') ? donghua_get_poster_url($work_id) : get_the_post_thumbnail_url($work_id, 'donghua-poster');
                $poster = $poster ?: $public_poster_fallback;
                $episode_number = $episode_numbers[$work_id];
                $time = (string) get_post_meta($work_id, '_donghua_schedule_time', true);
                if ($time !== '' && !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $time)) $time = '';
                $note = (string) get_post_meta($work_id, '_donghua_schedule_note', true);
                $episode_url = $episodes_by_work[$work_id] ?? '';
                $day_offset = ($active_day - $start_of_week + 7) % 7;
                $slot = $week_start->modify('+' . $day_offset . ' days');
                if ($time !== '') $slot = $slot->setTime((int) substr($time, 0, 2), (int) substr($time, 3, 2));
                $slot_passed = $time !== '' && $slot <= $today;
                $day_passed = $day_offset < $today_week_offset;
                if ($episode_url !== '') { $status = 'متاحة الآن'; }
                elseif ($episode_number !== '' && ($slot_passed || ($time === '' && $day_passed))) { $status = 'لم تصدر بعد'; }
                elseif ($active_day === $today_index && ($time === '' || $slot_passed)) { $status = $time === '' ? 'موعد اليوم' : 'موعد العرض'; }
                elseif ($day_passed) { $status = 'موعد هذا الأسبوع'; }
                else { $status = 'قادمة'; }
                $watch_url = $episode_url ?: get_permalink($work_id);
                ?>
                <article class="schedule-item-row" data-work-id="<?php echo esc_attr((string) $work_id); ?>">
                    <a class="schedule-poster-mini" href="<?php echo esc_url(get_permalink($work_id)); ?>" tabindex="-1" aria-hidden="true"><img src="<?php echo esc_url($poster); ?>" alt="" loading="lazy" width="56" height="72"></a>
                    <div class="schedule-info-block">
                        <h3 class="schedule-work-title"><a href="<?php echo esc_url(get_permalink($work_id)); ?>"><?php echo esc_html($work_title); ?></a></h3>
                        <div class="schedule-meta-line">
                            <?php if ($episode_number !== ''): ?><span>الحلقة <bdi dir="ltr"><?php echo esc_html($episode_number); ?></bdi></span><?php endif; ?>
                            <?php if ($time !== ''): ?><time datetime="<?php echo esc_attr($time); ?>"><bdi dir="ltr"><?php echo esc_html($time); ?></bdi></time><?php endif; ?>
                            <?php if ($note !== ''): ?><span><?php echo esc_html($note); ?></span><?php endif; ?>
                        </div>
                    </div>
                    <div class="schedule-item-actions">
                        <span class="schedule-status<?php echo $episode_url ? ' is-released' : ''; ?>"><?php echo esc_html($status); ?></span>
                        <?php if ($episode_url): ?><a class="schedule-watch-btn" href="<?php echo esc_url($watch_url); ?>">مشاهدة الحلقة</a><?php endif; ?>
                        <?php if ($logged_in): ?><button type="button" class="schedule-follow-button<?php echo $is_following ? ' is-following' : ''; ?>" data-schedule-follow data-work-id="<?php echo esc_attr((string) $work_id); ?>" aria-pressed="<?php echo $is_following ? 'true' : 'false'; ?>"><?php echo $is_following ? '✓ أتابعها' : '+ متابعة'; ?></button><?php else: ?><a class="schedule-follow-button" href="<?php echo esc_url($login_following_url); ?>">+ متابعة</a><?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($visible_count === 0): ?>
                <div class="schedule-empty-day"><span class="schedule-empty-icon" aria-hidden="true">◷</span>
                    <?php if ($following_filter && !$has_any_following): ?><h3>لم تضف أي أعمال إلى قائمة المتابعة بعد.</h3><a href="<?php echo esc_url(get_post_type_archive_link('donghua')); ?>">استكشف الدونغهوا</a>
                    <?php elseif ($following_filter): ?><p>لا توجد أعمال تتابعها مجدولة لهذا اليوم.</p>
                    <?php else: ?><p>لا توجد أعمال مجدولة لهذا اليوم.</p><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    <?php if ($logged_in): ?><p class="schedule-live-feedback" data-schedule-feedback role="status" aria-live="polite"></p><?php endif; ?>
</div>

<?php get_footer(); ?>
