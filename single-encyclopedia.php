<?php
/**
 * The template for displaying a single Encyclopedia entry
 * OKanime Modern Knowledge Base Edition
 */
declare(strict_types=1);

get_header();

$entry_id = get_the_ID();

// Track views with session/cookie debouncing
if (class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta')) {
    \DonghuaCore\Meta\EncyclopediaMeta::track_view($entry_id);
}

// 1. Metadata
$ar_name       = get_post_meta($entry_id, '_encyclopedia_ar_name', true) ?: get_the_title($entry_id);
$en_name       = get_post_meta($entry_id, '_encyclopedia_en_name', true);
$zh_name       = get_post_meta($entry_id, '_encyclopedia_zh_name', true);
$pinyin        = get_post_meta($entry_id, '_encyclopedia_pinyin', true);
$aliases       = class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta') ? \DonghuaCore\Meta\EncyclopediaMeta::get_aliases($entry_id) : [];
$spoiler_level = get_post_meta($entry_id, '_encyclopedia_spoiler_level', true) ?: 'none';
$pronunciation = get_post_meta($entry_id, '_encyclopedia_pronunciation', true);

$origin           = get_post_meta($entry_id, '_encyclopedia_origin', true);
$faction          = get_post_meta($entry_id, '_encyclopedia_faction', true);
$realm            = get_post_meta($entry_id, '_encyclopedia_realm', true);
$race             = get_post_meta($entry_id, '_encyclopedia_race', true);
$power_system     = get_post_meta($entry_id, '_encyclopedia_power_system', true);
$first_appearance = get_post_meta($entry_id, '_encyclopedia_first_appearance', true);
$status           = get_post_meta($entry_id, '_encyclopedia_status', true);
$importance       = (int) (get_post_meta($entry_id, '_encyclopedia_importance', true) ?: 3);
$views            = (int) get_post_meta($entry_id, '_encyclopedia_views', true);

// Source & Verification
$source_name   = get_post_meta($entry_id, '_encyclopedia_source_name', true);
$source_url    = get_post_meta($entry_id, '_encyclopedia_source_url', true);
$source_notes  = get_post_meta($entry_id, '_encyclopedia_source_notes', true);
$is_internal_source_filename = static function ($value): bool {
    return is_string($value) && preg_match('~(?:^|[\\\\/])[^\\\\/]*\\.(?:md|txt|csv|json|xml|ya?ml|php|log)(?:$|[?#\\s])~iu', trim($value)) === 1;
};
if ($is_internal_source_filename($source_name)) $source_name = '';
if ($is_internal_source_filename($source_url)) $source_url = '';
if ($is_internal_source_filename($source_notes)) $source_notes = '';

// Taxonomy
$types     = get_the_terms($entry_id, 'encyclopedia_type');
$type_term = (!empty($types) && !is_wp_error($types)) ? $types[0] : null;

// Cultivation Pathway
$pathway = class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta') 
    ? \DonghuaCore\Meta\EncyclopediaMeta::get_cultivation_pathway($entry_id) 
    : null;

// Related Donghua
$dh_ids = get_post_meta($entry_id, '_related_donghua_ids', true);
$related_donghua = [];
if (!empty($dh_ids) && is_array($dh_ids)) {
    foreach ($dh_ids as $dh_id) {
        $dh_post = get_post((int)$dh_id);
        if ($dh_post && $dh_post->post_status === 'publish') {
            $related_donghua[] = $dh_post;
        }
    }
}

// Related Encyclopedia Entries & Dynamic Inverse Relationships
$all_relationships_data = class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta')
    ? \DonghuaCore\Meta\EncyclopediaMeta::get_all_relationships($entry_id)
    : [];
$all_relationships = $all_relationships_data['items'] ?? [];
$entry_work_ids = get_post_meta($entry_id, '_related_donghua_ids', true);
if (!is_array($entry_work_ids)) $entry_work_ids = $entry_work_ids ? [(int)$entry_work_ids] : [];
$entry_work_ids = array_values(array_unique(array_map('intval', $entry_work_ids)));
$all_relationships = array_values(array_filter($all_relationships, static function($rel) use ($entry_work_ids) {
    $target_id = (int)($rel['target_id'] ?? 0);
    $target_works = get_post_meta($target_id, '_related_donghua_ids', true);
    if (!is_array($target_works)) $target_works = $target_works ? [(int)$target_works] : [];
    if (!$entry_work_ids) return true;
    if (!is_array($target_works)) $target_works = $target_works ? [(int)$target_works] : [];
    return (bool) $target_works && (bool) array_intersect($entry_work_ids, array_map('intval', $target_works));
}));
$first_episode_id = (int) get_post_meta($entry_id, '_encyclopedia_first_appearance_episode_id', true);
$mention_rows = get_post_meta($entry_id, '_encyclopedia_episode_mentions', true);
if (!is_array($mention_rows)) $mention_rows = [];
$primary_names = array_filter(array_map(static fn($value): string => trim((string)$value), [$ar_name, get_the_title($entry_id), $en_name, $zh_name, $pinyin, $pronunciation]));
$alias_candidates = [];
foreach ($aliases as $alias) {
    $legacy_parts = preg_split('/\s*[—–]\s*/u', trim((string)$alias)) ?: [];
    foreach ($legacy_parts as $legacy_part) {
        $variants = preg_split('/\s*\/\s*/u', trim($legacy_part)) ?: [];
        foreach ($variants as $variant) {
            $alias_candidates[] = trim($variant);
        }
    }
}
$display_aliases = [];
$seen_display_aliases = [];
$normalize_display_name = static function(string $value): string {
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return preg_replace('/[\p{P}\p{S}\s]+/u', '', $value) ?? $value;
};
$normalized_primary_names = array_filter(array_map(static fn($value): string => $normalize_display_name((string)$value), $primary_names));
foreach ($alias_candidates as $alias) {
    $alias = trim((string)$alias);
    if ($alias === '') continue;
    $normalized_alias = $normalize_display_name($alias);
    $same_primary = false;
    foreach ($normalized_primary_names as $normalized_primary) {
        if ($normalized_alias === $normalized_primary
            || (strlen($normalized_alias) >= 8 && str_contains($normalized_primary, $normalized_alias))) {
            $same_primary = true;
            break;
        }
    }
    if ($same_primary) continue;
    $alias_key = function_exists('mb_strtolower') ? mb_strtolower($alias, 'UTF-8') : strtolower($alias);
    if (isset($seen_display_aliases[$alias_key])) continue;
    $seen_display_aliases[$alias_key] = true;
    $display_aliases[] = $alias;
}
?>

<div class="single-encyclopedia-page">

    <!-- Top Breadcrumb & Hero Bar -->
    <div class="enc-single-hero">
        <div class="container enc-hero-inner">
            
            <!-- Breadcrumbs -->
            <nav class="enc-breadcrumbs" aria-label="مسار التصفح">
                <a href="<?php echo esc_url(home_url('/')); ?>">الرئيسية</a>
                <span class="sep">&rsaquo;</span>
                <a href="<?php echo esc_url(home_url('/encyclopedia/')); ?>">الموسوعة</a>
                <?php foreach ($related_donghua as $crumb_work): ?>
                    <span class="sep">&rsaquo;</span><a href="<?php echo esc_url(get_permalink($crumb_work->ID)); ?>"><?php echo esc_html(get_post_meta($crumb_work->ID, '_donghua_ar_name', true) ?: $crumb_work->post_title); ?></a>
                <?php endforeach; ?>
                <?php if ($type_term): ?>
                    <span class="sep">&rsaquo;</span>
                    <a href="<?php echo esc_url(get_term_link($type_term)); ?>"><?php echo esc_html($type_term->name); ?></a>
                <?php endif; ?>
                <span class="sep">&rsaquo;</span>
                <span class="current"><?php echo esc_html($ar_name); ?></span>
            </nav>

            <!-- Hero Badges -->
            <div class="enc-single-badges-bar">
                <?php if ($type_term): ?>
                    <a href="<?php echo esc_url(get_term_link($type_term)); ?>" class="badge-enc-type badge-type-<?php echo esc_attr($type_term->slug); ?>">
                        <?php echo esc_html($type_term->name); ?>
                    </a>
                <?php endif; ?>

                <?php if ($status): ?>
                    <span class="badge-enc-status status-<?php echo esc_attr(sanitize_title($status)); ?>">
                        <?php echo esc_html($status); ?>
                    </span>
                <?php endif; ?>

                <?php if ($pathway && !empty($pathway['level'])): ?>
                    <span class="badge-enc-level">
                        المستوى <?php echo esc_html($pathway['level']); ?>
                    </span>
                <?php endif; ?>

                <span class="badge-enc-importance" title="أهمية المدخل: <?php echo esc_attr((string)$importance); ?>/5">
                    <?php echo esc_html(str_repeat('★', $importance)); ?>
                </span>
            </div>

            <!-- Main Titles Block -->
            <div class="enc-single-title-box">
                <h1 class="enc-main-title" dir="auto"><bdi><?php echo esc_html($ar_name); ?></bdi></h1>
                
                <div class="enc-foreign-titles">
                    <?php if ($en_name): ?>
                        <span class="title-item title-en">
                            <strong class="lang-tag">EN:</strong> <bdi><?php echo esc_html($en_name); ?></bdi>
                        </span>
                    <?php endif; ?>

                    <?php if ($zh_name): ?>
                        <span class="title-sep">·</span>
                        <span class="title-item title-zh">
                            <strong class="lang-tag">ZH:</strong> <bdi><?php echo esc_html($zh_name); ?></bdi>
                            <?php if ($pinyin): ?>
                                <span class="title-pinyin">(<?php echo esc_html($pinyin); ?>)</span>
                            <?php endif; ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($pronunciation): ?>
                        <span class="title-sep">·</span>
                        <span class="title-item title-pronounce">
                            <span class="icon">&#128266;</span> النطق: <?php echo esc_html($pronunciation); ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($display_aliases): ?><p class="enc-alias-list"><span class="label">أسماء أخرى:</span> <bdi dir="auto"><?php echo esc_html(implode('، ', $display_aliases)); ?></bdi></p><?php endif; ?>
            </div>

        </div>
    </div>

    <!-- Main Content Layout (2 Columns) -->
    <div class="container enc-body-container">
        <div class="enc-body-grid">

            <!-- Right Column (Main Article & Knowledge Graph) in RTL -->
            <div class="enc-content-column">

                <!-- Summary / Excerpt Highlight Box -->
                <?php if (has_excerpt() || !empty(get_post_field('post_content', $entry_id))): ?>
                    <section class="enc-summary-box" aria-label="نبذة تمهيدية">
                        <div class="summary-header">
                            <span class="summary-icon">&#128214;</span>
                            <span class="summary-title">نبذة موجزة</span>
                        </div>
                        <p class="summary-text">
                            <?php echo esc_html(get_the_excerpt() ?: wp_trim_words(get_post_field('post_content', $entry_id), 35)); ?>
                        </p>
                    </section>
                <?php endif; ?>

                <!-- Cultivation Realm Interactive Pathway (If Realm Entry) -->
                <?php if ($pathway): ?>
                    <section class="enc-cultivation-pathway-section" aria-label="مسار وسلسلة مرحلة الزراعة">
                        <div class="section-title-wrap">
                            <span class="decor-dot"></span>
                            <h2 class="section-title">مسار مراحل زراعة الخلود (Cultivation Realm Sequence)</h2>
                        </div>

                        <div class="cultivation-flow-diagram">
                            
                            <!-- Previous Realm Card -->
                            <div class="pathway-step pathway-prev <?php echo empty($pathway['previous']) ? 'step-disabled' : ''; ?>">
                                <div class="step-label">المرحلة السابقة</div>
                                <?php if (!empty($pathway['previous'])): ?>
                                    <a href="<?php echo esc_url($pathway['previous']['link']); ?>" class="step-card">
                                        <span class="step-ar"><?php echo esc_html($pathway['previous']['ar_name']); ?></span>
                                        <?php if (!empty($pathway['previous']['en_name'])): ?>
                                            <span class="step-en"><?php echo esc_html($pathway['previous']['en_name']); ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <div class="step-card step-card-empty">
                                        <span>بداية طريق الزراعة</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Flow Arrow -->
                            <div class="pathway-arrow">&#8592;</div>

                            <!-- Current Realm Card (Active) -->
                            <div class="pathway-step pathway-current">
                                <div class="step-label current-label">
                                    <span class="star">&#9733;</span> المرحلة الحالية
                                    <?php if (!empty($pathway['level'])): ?>
                                        (المستوى <?php echo esc_html($pathway['level']); ?>)
                                    <?php endif; ?>
                                </div>
                                <div class="step-card active-card">
                                    <span class="step-ar-main"><?php echo esc_html($pathway['current']['ar_name']); ?></span>
                                    <?php if (!empty($pathway['current']['en_name'])): ?>
                                        <span class="step-en-main"><?php echo esc_html($pathway['current']['en_name']); ?></span>
                                    <?php endif; ?>

                                    <!-- Sub-Stages Pills -->
                                    <?php if (!empty($pathway['current']['sub_stages'])): ?>
                                        <div class="substages-pills-row">
                                            <?php foreach ($pathway['current']['sub_stages'] as $stg): ?>
                                                <span class="substage-pill"><?php echo esc_html($stg); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Flow Arrow -->
                            <div class="pathway-arrow">&#8592;</div>

                            <!-- Next Realm Card -->
                            <div class="pathway-step pathway-next <?php echo empty($pathway['next']) ? 'step-disabled' : ''; ?>">
                                <div class="step-label">المرحلة التالية</div>
                                <?php if (!empty($pathway['next'])): ?>
                                    <a href="<?php echo esc_url($pathway['next']['link']); ?>" class="step-card">
                                        <span class="step-ar"><?php echo esc_html($pathway['next']['ar_name']); ?></span>
                                        <?php if (!empty($pathway['next']['en_name'])): ?>
                                            <span class="step-en"><?php echo esc_html($pathway['next']['en_name']); ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php else: ?>
                                    <div class="step-card step-card-empty">
                                        <span>ذروة عالم الزراعة</span>
                                    </div>
                                <?php endif; ?>
                            </div>

                        </div>

                        <!-- Requirements & Abilities Details Grid -->
                        <?php if (!empty($pathway['requirements']) || !empty($pathway['abilities']) || !empty($pathway['lifespan'])): ?>
                            <div class="realm-specs-grid">
                                <?php if (!empty($pathway['requirements'])): ?>
                                    <div class="realm-spec-card">
                                        <h4 class="spec-head"><span class="icon">&#9874;</span> شروط ومتطلبات الاختراق</h4>
                                        <p class="spec-desc"><?php echo nl2br(esc_html($pathway['requirements'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($pathway['abilities'])): ?>
                                    <div class="realm-spec-card">
                                        <h4 class="spec-head"><span class="icon">&#9889;</span> القدرات والمهارات المكتسبة</h4>
                                        <p class="spec-desc"><?php echo nl2br(esc_html($pathway['abilities'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($pathway['lifespan'])): ?>
                                    <div class="realm-spec-card realm-lifespan-card">
                                        <h4 class="spec-head"><span class="icon">&#9203;</span> مدى العمر المتوقع</h4>
                                        <p class="spec-desc"><?php echo esc_html($pathway['lifespan']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                    </section>
                <?php endif; ?>

                <?php if (in_array($spoiler_level, ['minor', 'major'], true)): ?>
                    <details class="enc-spoiler-disclosure">
                        <summary>⚠ يحتوي هذا المدخل على <?php echo $spoiler_level === 'major' ? 'حرق كبير' : 'حرق بسيط'; ?> — إظهار المحتوى</summary>
                <?php endif; ?>
                <!-- Full Article Content -->
                <section class="enc-full-article-box" aria-label="الشرح والتحليل الموسوعي">
                    <div class="section-title-wrap">
                        <span class="decor-dot"></span>
                        <h2 class="section-title">الشرح والتفاصيل الكاملة</h2>
                    </div>

                    <div class="enc-prose-content">
                        <?php if (get_the_content()): ?>
                            <?php the_content(); ?>
                        <?php else: ?>
                            <p class="enc-placeholder-content">لم تتم كتابة تفاصيل موسوعية موسعة لهذا المدخل بعد. سيتم تحديث المحتوى قريباً.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Knowledge Graph Relationships Section -->
                <?php if (!empty($all_relationships)): ?>
                    <section class="enc-relationships-section" aria-label="شبكة العلاقات والمفاهيم المرتبطة">
                        <div class="section-title-wrap">
                            <span class="decor-dot"></span>
                            <h2 class="section-title">شبكة العلاقات والمداخل المرتبطة (Knowledge Graph)</h2>
                        </div>

                        <div class="relationships-grid">
                            <?php foreach ($all_relationships as $rel): ?>
                                <?php
                                $r_id    = (int) $rel['target_id'];
                                $is_inv  = (bool) ($rel['is_inverse'] ?? false);
                                $dir_txt = $is_inv ? 'علاقة واردة' : 'علاقة صادرة';
                                $dir_cls = $is_inv ? 'rel-badge-incoming' : 'rel-badge-outgoing';
                                $arrow   = $is_inv ? '&#8594;' : '&#8592;';
                                ?>
                                <div class="relation-item-card">
                                    <div class="rel-relation-badge <?php echo esc_attr($dir_cls); ?>">
                                        <span class="rel-icon"><?php echo esc_html($rel['icon'] ?? '&#8226;'); ?></span>
                                        <span class="rel-label-text"><?php echo esc_html($rel['relation_label']); ?></span>
                                        <span class="rel-arrow"><?php echo $arrow; ?></span>
                                    </div>
                                    <div class="rel-body">
                                        <div class="rel-header-row">
                                            <span class="rel-type-tag"><?php echo esc_html($rel['target_type_label'] ?? 'مدخل'); ?></span>
                                            <span class="rel-dir-tag"><?php echo esc_html($dir_txt); ?></span>
                                            <h4 class="rel-entry-title">
                                                <a href="<?php echo esc_url($rel['target_url']); ?>"><?php echo esc_html($rel['target_ar_name'] ?: $rel['target_title']); ?></a>
                                            </h4>
                                        </div>
                                        <?php if (!empty($rel['target_en_name'])): ?>
                                            <span class="rel-en-subtitle"><?php echo esc_html($rel['target_en_name']); ?></span>
                                        <?php endif; ?>
                                        <p class="rel-short-text">
                                            <?php echo esc_html(get_the_excerpt($r_id) ?: wp_trim_words(get_post_field('post_content', $r_id), 14)); ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if (in_array($spoiler_level, ['minor', 'major'], true)): ?></details><?php endif; ?>

                <?php if ($first_episode_id || $mention_rows): ?>
                    <section class="enc-episode-mentions" aria-label="الظهور في الحلقات">
                        <h2>الظهور في الحلقات</h2>
                        <?php if ($first_episode_id && get_post_status($first_episode_id) === 'publish'): ?><p>أول ظهور: <a href="<?php echo esc_url(get_permalink($first_episode_id)); ?>"><?php echo esc_html(get_the_title($first_episode_id)); ?></a></p><?php endif; ?>
                        <?php if ($mention_rows): ?><ul><?php foreach (array_slice($mention_rows, 0, 50) as $mention): $mention_id = (int)($mention['episode_id'] ?? 0); if (!$mention_id || get_post_status($mention_id) !== 'publish') continue; ?><li><a href="<?php echo esc_url(get_permalink($mention_id)); ?>"><?php echo esc_html(get_the_title($mention_id)); ?></a></li><?php endforeach; ?></ul><?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- Sources & Verification Box -->
                <?php if ($source_name || $source_url || $source_notes): ?>
                    <section class="enc-sources-citation-box" aria-label="المصادر والتوثيق">
                        <h3 class="sources-head">
                            <span class="sources-icon">&#128220;</span> المصادر والتوثيق (Sources & Citations)
                        </h3>
                        <div class="sources-content">
                            <?php if ($source_name): ?>
                                <p><strong>المصدر المرجعي:</strong> <?php echo esc_html($source_name); ?></p>
                            <?php endif; ?>
                            <?php if ($source_url): ?>
                                <p><strong>رابط المرجع:</strong> <a href="<?php echo esc_url($source_url); ?>" target="_blank" rel="noopener noreferrer nofollow" class="source-link"><?php echo esc_html($source_url); ?> &nearr;</a></p>
                            <?php endif; ?>
                            <?php if ($source_notes): ?>
                                <p class="source-notes"><strong>ملاحظات التوثيق:</strong> <?php echo esc_html($source_notes); ?></p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- Prev / Next Navigation -->
                <nav class="enc-entry-navigation" aria-label="التنقل بين المداخل الموسوعية">
                    <?php
                    $prev_post = get_previous_post();
                    $next_post = get_next_post();
                    ?>
                    <div class="enc-nav-links">
                        <?php if ($prev_post): ?>
                            <a href="<?php echo esc_url(get_permalink($prev_post->ID)); ?>" class="enc-nav-btn enc-prev-btn">
                                <span class="nav-sub">&rsaquo; المدخل السابق</span>
                                <span class="nav-title"><?php echo esc_html(get_the_title($prev_post->ID)); ?></span>
                            </a>
                        <?php endif; ?>

                        <?php if ($next_post): ?>
                            <a href="<?php echo esc_url(get_permalink($next_post->ID)); ?>" class="enc-nav-btn enc-next-btn">
                                <span class="nav-sub">المدخل التالي &lsaquo;</span>
                                <span class="nav-title"><?php echo esc_html(get_the_title($next_post->ID)); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                </nav>

                <!-- Comments & Community Discussions -->
                <section class="donghua-comments-section" aria-label="نقاشات الموسوعة">
                    <?php comments_template(); ?>
                </section>

            </div>

            <!-- Left Column (Side Info Card & Donghua Integration) in RTL -->
            <aside class="enc-sidebar-column" aria-label="بطاقة البيانات السريعة">
                
                <!-- Main Spec Card -->
                <div class="enc-specs-card">
                    
                    <!-- Main Poster / Photo -->
                    <div class="specs-thumb-wrap">
                        <?php if (has_post_thumbnail()): ?>
                            <?php the_post_thumbnail('medium_large', ['class' => 'specs-poster-img', 'alt' => esc_attr($ar_name)]); ?>
                        <?php else: ?>
                            <div class="specs-placeholder-wrap">
                                <span class="placeholder-icon">&#128214;</span>
                                <span class="placeholder-text"><?php echo esc_html($ar_name); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="specs-card-header">
                        <h3 class="specs-card-title">بطاقة البيانات السريعة</h3>
                        <span class="specs-card-sub">Quick Facts</span>
                    </div>

                    <ul class="specs-data-list">
                        <?php if ($type_term): ?>
                            <li>
                                <span class="spec-label">النوع والتصنيف:</span>
                                <span class="spec-value">
                                    <a href="<?php echo esc_url(get_term_link($type_term)); ?>" class="spec-link">
                                        <?php echo esc_html($type_term->name); ?>
                                    </a>
                                </span>
                            </li>
                        <?php endif; ?>

                        <?php if ($origin): ?>
                            <li>
                                <span class="spec-label">الأصل / المرجع:</span>
                                <span class="spec-value"><?php echo esc_html($origin); ?></span>
                            </li>
                        <?php endif; ?>

                        <?php if ($faction): ?>
                            <li>
                                <span class="spec-label">الانتماء / المنظمة:</span>
                                <span class="spec-value"><?php echo esc_html($faction); ?></span>
                            </li>
                        <?php endif; ?>

                        <?php if ($realm): ?>
                            <li>
                                <span class="spec-label">العالم / البعد:</span>
                                <span class="spec-value"><?php echo esc_html($realm); ?></span>
                            </li>
                        <?php endif; ?>

                        <?php if ($race): ?>
                            <li>
                                <span class="spec-label">العرق / السلالة:</span>
                                <span class="spec-value"><?php echo esc_html($race); ?></span>
                            </li>
                        <?php endif; ?>

                        <?php if ($power_system): ?>
                            <li>
                                <span class="spec-label">نظام القوة:</span>
                                <span class="spec-value"><?php echo esc_html($power_system); ?></span>
                            </li>
                        <?php endif; ?>

                        <?php if ($first_appearance): ?>
                            <li>
                                <span class="spec-label">أول ظهور:</span>
                                <span class="spec-value"><?php echo esc_html($first_appearance); ?></span>
                            </li>
                        <?php endif; ?>

                        <?php if ($status): ?>
                            <li>
                                <span class="spec-label">الحالة:</span>
                                <span class="spec-value status-tag-<?php echo esc_attr(sanitize_title($status)); ?>">
                                    <?php echo esc_html($status); ?>
                                </span>
                            </li>
                        <?php endif; ?>

                        <li>
                            <span class="spec-label">إجمالي المشاهدات:</span>
                            <span class="spec-value"><?php echo esc_html(number_format(max(1, $views))); ?> قراءة</span>
                        </li>
                    </ul>
                </div>

                <!-- Connected Donghua Card (Knowledge Anchor) -->
                <?php if (!empty($related_donghua)): ?>
                    <div class="enc-sidebar-donghua-box">
                        <div class="donghua-box-header">
                            <span class="icon">&#127916;</span>
                            <h3 class="box-title">الأعمال المرتبطة</h3>
                        </div>

                        <div class="donghua-mini-cards-list">
                            <?php foreach ($related_donghua as $dh_post): ?>
                                <?php
                                $d_id     = $dh_post->ID;
                                $d_ar     = get_post_meta($d_id, '_donghua_ar_name', true) ?: $dh_post->post_title;
                                $d_en     = get_post_meta($d_id, '_donghua_en_name', true);
                                $d_poster = get_the_post_thumbnail_url($d_id, 'thumbnail');
                                $d_score  = donghua_get_score($d_id);
                                ?>
                                <div class="mini-donghua-item">
                                    <?php if ($d_poster): ?>
                                        <a href="<?php echo esc_url(get_permalink($d_id)); ?>" class="mini-thumb-link">
                                            <img src="<?php echo esc_url($d_poster); ?>" alt="<?php echo esc_attr($d_ar); ?>" class="mini-poster-img" />
                                        </a>
                                    <?php endif; ?>
                                    <div class="mini-info">
                                        <h4 class="mini-title">
                                            <a href="<?php echo esc_url(get_permalink($d_id)); ?>"><?php echo esc_html($d_ar); ?></a>
                                        </h4>
                                        <?php if ($d_en): ?>
                                            <span class="mini-en"><?php echo esc_html($d_en); ?></span>
                                        <?php endif; ?>
                                        <div class="mini-links-row">
                                            <a href="<?php echo esc_url(get_permalink($d_id)); ?>" class="mini-watch-btn">
                                                مشاهدة العمل &rsaquo;
                                            </a>
                                            <a href="<?php echo esc_url(home_url('/encyclopedia/donghua/' . $dh_post->post_name . '/')); ?>" class="mini-enc-link">
                                                موسوعة العمل &rsaquo;
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </aside>

        </div><!-- .enc-body-grid -->
    </div><!-- .container -->

</div><!-- .single-encyclopedia-page -->

<?php get_footer(); ?>
