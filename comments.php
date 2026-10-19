<?php
/**
 * Comments Template for Donghua Theme
 * Modern, accessible, secure, and responsive.
 * 
 * @package DonghuaTheme
 */
declare(strict_types=1);

if (post_password_required()) {
    return;
}

$current_user = wp_get_current_user();
$user_avatar  = $current_user->exists()
    ? get_avatar($current_user->ID, 36, '', $current_user->display_name, ['class' => 'dh-composer-user-avatar'])
    : '<span class="dh-composer-guest-avatar" aria-hidden="true"><svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor"><circle cx="12" cy="8" r="4"></circle><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"></path></svg></span>';
?>

<section id="comments" class="comments-area" aria-label="<?php esc_attr_e('منطقة التعليقات والمناقشة', 'donghua-theme'); ?>">
    <header class="dh-comments-heading">
        <h2 class="comments-title"><?php esc_html_e('التعليقات', 'donghua-theme'); ?></h2>
        <span class="dh-comments-count"><?php echo esc_html(number_format_i18n(get_comments_number())); ?></span>
    </header>

    <p id="dh-comment-feedback" class="dh-comment-feedback" role="status"></p>

    <?php if (have_comments()): ?>
        <div class="dh-comments-toolbar">
            <label for="dh-comments-sort"><?php esc_html_e('ترتيب التعليقات', 'donghua-theme'); ?></label>
            <select id="dh-comments-sort" class="dh-comments-sort">
                <option value="newest"><?php esc_html_e('الأحدث', 'donghua-theme'); ?></option>
                <option value="votes"><?php esc_html_e('الأكثر تصويتًا', 'donghua-theme'); ?></option>
            </select>
        </div>
    <?php endif; ?>

    <?php if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')): ?>
        <p class="no-comments"><?php esc_html_e('التعليقات مغلقة لهذه الصفحة.', 'donghua-theme'); ?></p>
    <?php endif; ?>

    <?php if (comments_open()): ?>
        <?php
        $commenter = wp_get_current_commenter();
        $req       = get_option('require_name_email');
        $html_req  = $req ? " required='required'" : '';
        $compact_episode_guest = is_singular('episode') && !$current_user->exists();
        $guest_fields_open = $compact_episode_guest
            ? '<details class="dh-guest-fields-details"><summary>' . esc_html__('بيانات الضيف', 'donghua-theme') . '</summary><div class="dh-guest-fields-row">'
            : '<div class="dh-guest-fields-row">';
        $guest_fields_close = $compact_episode_guest ? '</div></details>' : '</div>';

        $fields = [
            'author' => $guest_fields_open .
                '<div class="comment-form-author">' .
                '<label for="author">' . esc_html__('الاسم المستعار', 'donghua-theme') . ($req ? ' <span class="required">*</span>' : '') . '</label> ' .
                '<input id="author" name="author" type="text" value="' . esc_attr($commenter['comment_author']) . '" size="30" maxlength="245" autocomplete="name"' . $html_req . ' placeholder="' . esc_attr__('اسمك أو لقبك', 'donghua-theme') . '" /></div>',
            'email'  => '<div class="comment-form-email">' .
                '<label for="email">' . esc_html__('البريد الإلكتروني (لن ينشر)', 'donghua-theme') . ($req ? ' <span class="required">*</span>' : '') . '</label> ' .
                '<input id="email" name="email" type="email" value="' . esc_attr($commenter['comment_author_email']) . '" size="30" maxlength="100" autocomplete="email"' . $html_req . ' placeholder="' . esc_attr__('name@example.com', 'donghua-theme') . '" /></div>' .
                $guest_fields_close,
        ];

        comment_form([
            'title_reply'          => esc_html__('أضف تعليقاً أو رأياً', 'donghua-theme'),
            'title_reply_to'       => esc_html__('الرد على %s', 'donghua-theme'),
            'cancel_reply_link'    => esc_html__('إلغاء الرد', 'donghua-theme'),
            'must_log_in'          => '<p class="must-log-in">' . sprintf(
                '%1$s <a class="dh-login-link" href="%2$s">%3$s</a>',
                esc_html__('سجل دخولك للمشاركة في النقاش', 'donghua-theme'),
                esc_url(wp_login_url(get_permalink())),
                esc_html__('تسجيل دخول', 'donghua-theme')
            ) . '</p>',
            'label_submit'         => esc_html__('نشر التعليق', 'donghua-theme'),
            'submit_button'        => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">' . donghua_icon('send', '', 14) . ' <span>%4$s</span></button>',
            'submit_field'         => '<div class="form-submit">%1$s %2$s</div>',
            'comment_notes_before' => '',
            'comment_notes_after'  => '',
            'logged_in_as'         => '',
            'class_form'           => 'donghua-comment-form',
            'class_submit'         => 'submit-btn-primary',
            'fields'               => $fields,
            'comment_field'        => '<div class="comment-form-comment">' .
                '<div class="dh-composer-top">' .
                    $user_avatar .
                    ($current_user->exists() ? '<span class="dh-composer-user-name">' . esc_html($current_user->display_name) . '</span>' : '') .
                '</div>' .
                '<label for="comment">' . esc_html__('نص التعليق', 'donghua-theme') . ' <span class="required">*</span></label>' .
                '<textarea id="comment" name="comment" cols="45" rows="4" maxlength="1000" required="required" placeholder="' . esc_attr__('شاركنا رأيك أو نظريتك حول هذه الحلقة أو العمل...', 'donghua-theme') . '"></textarea>' .
                '<div class="dh-char-counter-row">' .
                    '<span class="dh-counter-hint">' . esc_html__('الحد الأقصى 1000 حرف', 'donghua-theme') . '</span>' .
                    '<span class="dh-char-counter" id="dh-comment-char-counter">0 / 1000</span>' .
                '</div>' .
            '</div>',
        ]);
        ?>
    <?php endif; ?>

    <?php if (have_comments()): ?>
        <ol class="comment-list">
            <?php
            global $wp_query;
            if (class_exists('DHM_Badges') && !empty($wp_query->comments)) {
                $badge_comments = $wp_query->comments;
                $preview_mode = class_exists('DonghuaCore\\PublicQaIsolation')
                    ? \DonghuaCore\PublicQaIsolation::preview_mode()
                    : 'real';
                if (in_array($preview_mode, ['qa', 'mixed'], true)) {
                    $frontier = array_values(array_filter(array_map(static fn($item) => (int)$item->comment_ID, $badge_comments)));
                    $max_depth = max(1, min(10, (int)get_option('thread_comments_depth', 5)));
                    for ($depth = 1; $frontier && $depth < $max_depth; $depth++) {
                        $children = get_comments([
                            'post_id' => (int)get_queried_object_id(),
                            'parent__in' => $frontier,
                            'status' => 'approve',
                            'number' => 0,
                            'hierarchical' => false,
                            'update_comment_meta_cache' => true,
                            'update_comment_post_cache' => false,
                        ]);
                        if (!$children) break;
                        $badge_comments = array_merge($badge_comments, $children);
                        $frontier = array_values(array_filter(array_map(static fn($item) => (int)$item->comment_ID, $children)));
                    }
                }
                DHM_Badges::prime(array_map(static fn($item) => (int)$item->user_id, $badge_comments));
            }
            wp_list_comments([
                'style'       => 'ol',
                'short_ping'  => true,
                'avatar_size' => 36,
                'callback'    => 'donghua_comment_callback',
            ]);
            ?>
        </ol>

        <?php if (get_comment_pages_count() > 1 && get_option('page_comments')): ?>
            <nav class="comment-navigation" role="navigation" aria-label="<?php esc_attr_e('تنقل التعليقات', 'donghua-theme'); ?>">
                <div class="nav-previous"><?php previous_comments_link(donghua_icon('arrow-right', '', 14) . ' ' . esc_html__('تعليقات أقدم', 'donghua-theme')); ?></div>
                <div class="nav-next"><?php next_comments_link(esc_html__('تعليقات أحدث', 'donghua-theme') . ' ' . donghua_icon('arrow-left', '', 14)); ?></div>
            </nav>
        <?php endif; ?>
    <?php else: ?>
        <div class="dh-comments-empty" role="status">
            <span class="dh-comments-empty-mark" aria-hidden="true">✦</span>
            <h3>لا توجد تعليقات بعد</h3>
            <p>ابدأ النقاش وشارك رأيك حول هذا العمل.</p>
        </div>
    <?php endif; ?>
</section>
