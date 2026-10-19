<?php
/** Encyclopedia glossary: local published content, reference-style browsing. */
declare(strict_types=1);
get_header();
$entries = get_posts([
    'post_type' => 'encyclopedia', 'post_status' => 'publish',
    'posts_per_page' => -1, 'orderby' => ['menu_order' => 'ASC', 'ID' => 'ASC'],
    'has_password' => false, 'update_post_meta_cache' => true, 'update_post_term_cache' => true,
]);
$groups = [
    'general' => ['تصنيفات ومعلومات عامة', ['general', 'chinese-concepts', 'concepts']],
    'cosmology' => ['علم الكونيات والأبعاد', ['cosmology', 'realms', 'locations', 'regions']],
    'cultivation' => ['أنظمة الطاقة والزراعة', ['cultivation', 'power-systems', 'abilities']],
    'cultivation_ranks' => ['مراتب ومراحل الزراعة', ['cultivation_ranks', 'cultivation-realms']],
    'alchemy' => ['الكيمياء وصقل الحبوب', ['alchemy', 'pills', 'herbs']],
    'martial-arts' => ['فنون القتال', ['martial-arts', 'martial']],
    'formations' => ['التشكيلات والرموز', ['formations', 'techniques']],
    'creatures' => ['الوحوش والكائنات', ['creatures', 'spirit-beasts', 'races']],
    'society' => ['المجتمع والطوائف', ['society', 'sects', 'clans', 'organizations', 'kingdoms']],
    'kinship-social-ties' => ['ألقاب القرابة', ['kinship-social-ties', 'kinship', 'titles']],
    'weapons' => ['الأسلحة والأدوات', ['weapons']],
    'artifacts' => ['الكنوز والقطع الأثرية', ['artifacts', 'treasures']],
    'idioms' => ['مصطلحات أخرى', ['idioms', 'other']],
];
$cards = [];
$counts = array_fill_keys(array_keys($groups), 0);
$is_internal_source_filename = static function ($value): bool {
    return is_string($value) && preg_match('~(?:^|[\\\\/])[^\\\\/]*\\.(?:md|txt|csv|json|xml|ya?ml|php|log)(?:$|[?#\\s])~iu', trim($value)) === 1;
};
foreach ($entries as $entry) {
    $id = $entry->ID;
    $group = get_post_meta($id, '_donghua_terms_category_slug', true);
    if (!isset($groups[$group])) {
        $group = 'idioms';
        $entry_terms = get_the_terms($id, 'encyclopedia_type');
        $slugs = (!empty($entry_terms) && !is_wp_error($entry_terms)) ? wp_list_pluck($entry_terms, 'slug') : [];
        if (!is_wp_error($slugs)) {
            foreach ($groups as $key => [$label, $aliases]) {
                if (array_intersect($slugs, $aliases)) { $group = $key; break; }
            }
        }
        $reference_category = get_post_meta($id, '_encyclopedia_reference_category', true);
        foreach ($groups as $key => [$label]) {
            if ($reference_category === $label) { $group = $key; break; }
        }
    }
    $counts[$group]++;
    $cards[] = [
        'id' => $id, 'group' => $group,
        'ar' => get_post_meta($id, '_encyclopedia_ar_name', true) ?: $entry->post_title,
        'en' => get_post_meta($id, '_encyclopedia_en_name', true),
        'zh' => get_post_meta($id, '_encyclopedia_zh_name', true),
        'pinyin' => get_post_meta($id, '_encyclopedia_pinyin', true),
        'aliases' => class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta') ? \DonghuaCore\Meta\EncyclopediaMeta::get_aliases($id) : [],
        'spoiler' => get_post_meta($id, '_encyclopedia_spoiler_level', true) ?: 'none',
        'work' => (($work_ids = get_post_meta($id, '_related_donghua_ids', true)) && is_array($work_ids) && !empty($work_ids[0])) ? (get_post_meta((int)$work_ids[0], '_donghua_ar_name', true) ?: get_the_title((int)$work_ids[0])) : '',
        'excerpt' => wp_strip_all_tags($entry->post_excerpt ?: wp_trim_words($entry->post_content, 28)),
        'content' => wp_kses_post(wpautop($entry->post_content)),
        'source' => get_post_meta($id, '_encyclopedia_reference_url', true),
        'source_name' => (($source_name = get_post_meta($id, '_encyclopedia_source_name', true)) && !$is_internal_source_filename($source_name)) ? $source_name : '',
        'source_is_internal' => $is_internal_source_filename(get_post_meta($id, '_encyclopedia_source_name', true)),
    ];
}
$letters = ['أ','ب','ت','ث','ج','ح','خ','د','ذ','ر','ز','س','ش','ص','ض','ط','ظ','ع','غ','ف','ق','ك','ل','م','ن','ه','و','ي'];
?>
<div class="glossary" id="dh-glossary" dir="rtl">
    <header class="glossary-hero">
        <span class="glossary-eyebrow">معرفة عوالم الدونغهوا</span>
        <h1>الموسوعة</h1>
        <p>تعرّف على المصطلحات والعوالم ومراحل الزراعة، بالأسماء العربية والصينية وطريقة النطق والشرح المفصل.</p>
        <div class="glossary-search" data-glossary-controls hidden>
            <?php echo donghua_icon('search', '', 22); ?>
            <label class="screen-reader-text" for="glossary-search">البحث في الموسوعة</label>
            <input id="glossary-search" type="search" placeholder="ابحث بالعربية أو الإنجليزية أو الصينية..." autocomplete="off" />
        </div>
        <p class="glossary-total"><strong><?php echo esc_html((string)count($cards)); ?></strong> مدخل منشور في الموسوعة</p>
    </header>
    <div class="glossary-body">
        <div class="glossary-categories" role="group" aria-label="تصنيفات الموسوعة" data-glossary-controls hidden>
            <button type="button" data-category="all" aria-pressed="true">الكل <span><?php echo esc_html((string)count($cards)); ?></span></button>
            <?php foreach ($groups as $key => [$label]): ?>
                <?php if (empty($counts[$key])) continue; ?>
                <button type="button" data-category="<?php echo esc_attr($key); ?>" aria-pressed="false"><?php echo esc_html($label); ?> <span><?php echo esc_html((string)$counts[$key]); ?></span></button>
            <?php endforeach; ?>
        </div>
        <div class="glossary-alphabet" role="group" aria-label="التصفية بالحرف الأول" data-glossary-controls hidden>
            <button type="button" data-letter="" aria-pressed="true">الكل</button>
            <?php foreach ($letters as $letter): ?><button type="button" data-letter="<?php echo esc_attr($letter); ?>" aria-pressed="false"><?php echo esc_html($letter); ?></button><?php endforeach; ?>
        </div>
        <div class="glossary-toolbar">
            <p id="glossary-results" role="status" aria-live="polite" aria-atomic="true">عرض جميع المداخل (<?php echo esc_html((string)count($cards)); ?>)</p>
            <div class="glossary-view" role="group" aria-label="طريقة العرض" data-glossary-controls hidden>
                <button type="button" data-view="grid" aria-label="عرض البطاقات" aria-pressed="true"><?php echo donghua_icon('grid', '', 16); ?></button>
                <button type="button" data-view="list" aria-label="عرض القائمة" aria-pressed="false"><?php echo donghua_icon('list', '', 16); ?></button>
                <button type="button" id="glossary-reset">مسح التصفية</button>
            </div>
        </div>
        <div class="glossary-grid" id="glossary-grid">
            <?php foreach ($cards as $card): ?>
                <article class="glossary-card" data-group="<?php echo esc_attr($card['group']); ?>" data-title="<?php echo esc_attr($card['ar']); ?>" data-search="<?php echo esc_attr(implode(' ', array_merge([$card['ar'], $card['en'], $card['zh'], $card['pinyin'], $card['excerpt'], $card['work']], $card['aliases']))); ?>">
                    <a href="<?php echo esc_url(get_permalink($card['id'])); ?>" class="glossary-card-link" data-detail="glossary-detail-<?php echo esc_attr((string)$card['id']); ?>">
                        <div class="glossary-card-top"><span class="glossary-symbol" lang="zh"><?php echo esc_html($card['zh'] ?: '◇'); ?></span><span class="glossary-tag"><?php echo esc_html($groups[$card['group']][0]); ?></span></div>
                        <h2 dir="auto"><bdi><?php echo esc_html($card['ar']); ?></bdi></h2>
                        <p class="glossary-pronunciation" dir="auto"><?php echo esc_html($card['pinyin'] ?: $card['en']); ?></p>
                        <p class="glossary-excerpt"><?php echo esc_html($card['excerpt']); ?></p>
                        <?php if ($card['work']): ?><span class="glossary-work-context">العمل: <?php echo esc_html($card['work']); ?></span><?php endif; ?>
                        <span class="glossary-more">اقرأ الشرح <span aria-hidden="true">←</span></span>
                    </a>
                    <template id="glossary-detail-<?php echo esc_attr((string)$card['id']); ?>">
                        <header class="glossary-detail-head">
                            <span class="glossary-symbol" lang="zh"><?php echo esc_html($card['zh']); ?></span>
                            <h2 id="glossary-dialog-title"><?php echo esc_html($card['ar']); ?></h2>
                            <p dir="auto"><?php echo esc_html($card['pinyin'] ?: $card['en']); ?></p>
                            <span class="glossary-tag"><?php echo esc_html($groups[$card['group']][0]); ?></span>
                        </header>
                        <div class="glossary-detail-body">
                            <h3>التعريف</h3><p><?php echo esc_html($card['excerpt']); ?></p>
                            <?php if (in_array($card['spoiler'], ['minor', 'major'], true)): ?>
                                <p class="glossary-spoiler-note">⚠ هذا المدخل مصنف كمحتوى يحتوي على حرق. افتح صفحته لقراءة التفاصيل بعد التنبيه.</p>
                            <?php else: ?>
                                <h3>الشرح المفصل</h3><div class="glossary-prose"><?php echo $card['content']; ?></div>
                            <?php endif; ?>
                            <?php if (wp_parse_url((string)$card['source'], PHP_URL_HOST) === 'donghua-ar.me' && wp_parse_url((string)$card['source'], PHP_URL_SCHEME) === 'https'): ?>
                                <p class="glossary-source">المصدر: <a href="<?php echo esc_url($card['source']); ?>" target="_blank" rel="noopener noreferrer">موسوعة عالم الدونغهوا</a></p>
                            <?php elseif ($card['source_name']): ?>
                                <p class="glossary-source">المصدر: <?php echo esc_html($card['source_name']); ?></p>
                            <?php elseif (!$card['source_is_internal']): ?>
                                <p class="glossary-source">مدخل محلي سابق؛ لم تُثبت مطابقته للمصدر المرجعي.</p>
                            <?php endif; ?>
                            <a class="glossary-permalink" href="<?php echo esc_url(get_permalink($card['id'])); ?>">فتح صفحة المدخل ←</a>
                        </div>
                    </template>
                </article>
            <?php endforeach; ?>
        </div>
        <nav class="glossary-pagination" id="glossary-pagination" aria-label="صفحات الموسوعة" hidden></nav>
        <div class="glossary-empty" id="glossary-empty" <?php echo $cards ? 'hidden' : ''; ?>>
            <span aria-hidden="true">⌕</span><h2>لا توجد مداخل مطابقة</h2><p>جرّب كلمة أخرى أو امسح التصنيف والحرف المحدد.</p>
        </div>
        <noscript><p>يمكنك فتح صفحات المداخل مباشرة. فعّل JavaScript لاستخدام البحث والتصفية والمعاينة السريعة.</p></noscript>
    </div>
    <dialog class="glossary-dialog" id="glossary-dialog" aria-labelledby="glossary-dialog-title">
        <button type="button" class="glossary-close" aria-label="إغلاق تفاصيل المصطلح">×</button>
        <div id="glossary-dialog-content"></div>
    </dialog>
</div>
<?php get_footer(); ?>
