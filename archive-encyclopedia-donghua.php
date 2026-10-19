<?php
/**
 * Dedicated Donghua Encyclopedia Archive & Relationship Hub
 * Template Name: Donghua Dedicated Encyclopedia
 * URI Pattern: /encyclopedia/donghua/{donghua-slug}/
 */
declare(strict_types=1);

// Constraint 2: Explicit Preview Mode for Editors
$is_preview = isset($_GET['encyclopedia_preview']) && $_GET['encyclopedia_preview'] === '1' && current_user_can('edit_posts');
if ($is_preview) {
    nocache_headers();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
}

get_header();

// 1. Resolve Donghua Post
$dh_slug = get_query_var('donghua_encyclopedia');
$donghua = null;

if (!empty($dh_slug)) {
    $donghua = get_page_by_path(sanitize_title($dh_slug), OBJECT, 'donghua');
}

if (!$donghua || $donghua->post_status !== 'publish') {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    get_template_part('404');
    get_footer();
    exit;
}

$donghua_id = $donghua->ID;
$ar_name    = get_post_meta($donghua_id, '_donghua_ar_name', true) ?: $donghua->post_title;
$en_name    = get_post_meta($donghua_id, '_donghua_en_name', true);
$banner_url = get_post_meta($donghua_id, '_donghua_banner_url', true) ?: get_the_post_thumbnail_url($donghua_id, 'full');
$poster_id  = (int) get_post_meta($donghua_id, '_donghua_poster', true);
$poster_url = $poster_id ? wp_get_attachment_image_url($poster_id, 'medium') : get_the_post_thumbnail_url($donghua_id, 'medium');

// Taxonomy specs for hero
$genres  = wp_get_post_terms($donghua_id, 'genre', ['fields' => 'names']);
$year    = wp_get_post_terms($donghua_id, 'year', ['fields' => 'names']);
$status  = wp_get_post_terms($donghua_id, 'status', ['fields' => 'names']);
$score   = get_post_meta($donghua_id, '_donghua_score', true) ?: '8.5';

// 2. Stats & Cultivation Pathway (Filtered by Preview Mode)
$stats   = class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta') ? \DonghuaCore\Meta\EncyclopediaMeta::get_donghua_encyclopedia_stats($donghua_id, $is_preview) : [];
$pathway = class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta') ? \DonghuaCore\Meta\EncyclopediaMeta::get_donghua_cultivation_pathway($donghua_id, $is_preview) : [];

// 3. Query All Encyclopedia Entries for this Donghua (Constraint 2: Strictly published for non-preview)
$query_args = [
    'post_type'      => 'encyclopedia',
    'posts_per_page' => -1,
    'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
];

if ($is_preview) {
    $query_args['post_status'] = ['publish', 'draft'];
    $query_args['meta_query'] = [
        'relation' => 'OR',
        [
            'key'     => '_related_donghua_ids',
            'value'   => '"' . $donghua_id . '"',
            'compare' => 'LIKE',
        ],
        [
            'key'     => '_related_donghua_ids',
            'value'   => 'i:' . $donghua_id . ';',
            'compare' => 'LIKE',
        ],
    ];
} else {
    $query_args['post_status'] = 'publish';
    $query_args['meta_query'] = [
        'relation' => 'AND',
        [
            'relation' => 'OR',
            [
                'key'     => '_related_donghua_ids',
                'value'   => '"' . $donghua_id . '"',
                'compare' => 'LIKE',
            ],
            [
                'key'     => '_related_donghua_ids',
                'value'   => 'i:' . $donghua_id . ';',
                'compare' => 'LIKE',
            ],
        ],
        [
            'key'     => '_encyclopedia_review_status',
            'value'   => 'published',
            'compare' => '=',
        ],
    ];
}

$entries_query = new WP_Query($query_args);

$all_entries     = $entries_query->posts;
if (!empty($all_entries)) {
    update_post_caches($all_entries, 'encyclopedia', true, true);
}
$total_count     = count($all_entries);

// Canonical 8 Category Taxonomy Mapping
$type_category_map = [
    'characters'         => 'characters',
    'character'          => 'characters',
    'cultivation-realms' => 'cultivation-realms',
    'realm'              => 'cultivation-realms',
    'realms'             => 'cultivation-realms',
    'sects'              => 'sects',
    'sect'               => 'sects',
    'clans'              => 'sects',
    'organizations'      => 'sects',
    'treasures'          => 'treasures',
    'treasure'           => 'treasures',
    'weapons'            => 'treasures',
    'techniques'         => 'techniques',
    'technique'          => 'techniques',
    'martial-arts'       => 'techniques',
    'abilities'          => 'techniques',
    'locations'          => 'locations',
    'location'           => 'locations',
    'regions'            => 'locations',
    'kingdoms'           => 'locations',
    'spirit-beasts'      => 'spirit-beasts',
    'beasts'             => 'spirit-beasts',
    'beast'              => 'spirit-beasts',
    'concepts'           => 'concepts',
    'concept'            => 'concepts',
    'chinese-concepts'   => 'concepts',
    'xianxia'            => 'concepts',
    'wuxia'              => 'concepts',
    'xuanhuan'           => 'concepts',
    'power-systems'      => 'concepts',
];

$entries_by_type = [];
$candidate_nodes = [];
$central_node    = null;

foreach ($all_entries as $e) {
    $e_id     = $e->ID;
    $types    = get_the_terms($e_id, 'encyclopedia_type');
    $raw_slug = (!empty($types) && !is_wp_error($types)) ? $types[0]->slug : 'concepts';
    $cat_key  = $type_category_map[$raw_slug] ?? 'concepts';
    $t_name   = (!empty($types) && !is_wp_error($types)) ? $types[0]->name : 'مصطلح';
    $e_ar     = get_post_meta($e_id, '_encyclopedia_ar_name', true) ?: $e->post_title;
    $e_en     = get_post_meta($e_id, '_encyclopedia_en_name', true);

    $entries_by_type[$cat_key][] = $e;

    $candidate_nodes[] = [
        'id'        => $e_id,
        'title'     => $e_ar,
        'en_name'   => $e_en,
        'type_slug' => $cat_key,
        'type_name' => $t_name,
    ];

    // Priority for default central node: character with highest importance
    if (!$central_node && $cat_key === 'characters') {
        $central_node = $e;
    }
}

if (!$central_node && !empty($all_entries)) {
    $central_node = $all_entries[0];
}

// 4. Initial Graph Relationships for Central Node
$central_relationships = [];
if ($central_node && class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta')) {
    $rel_data = \DonghuaCore\Meta\EncyclopediaMeta::get_all_relationships($central_node->ID, 12, 0, $is_preview);
    $central_relationships = $rel_data['items'] ?? [];
}

// Section Categories Definition (8 Core Types + All)
$categories_def = [
    'characters'         => ['label' => 'الشخصيات', 'icon' => '&#128100;'],
    'cultivation-realms' => ['label' => 'مراحل الزراعة', 'icon' => '&#9874;'],
    'sects'              => ['label' => 'الطوائف والمنظمات', 'icon' => '&#127984;'],
    'treasures'          => ['label' => 'الكنوز والأسلحة', 'icon' => '&#128481;'],
    'techniques'         => ['label' => 'التقنيات والمهارات', 'icon' => '&#9889;'],
    'locations'          => ['label' => 'الأماكن والعوالم', 'icon' => '&#127757;'],
    'spirit-beasts'      => ['label' => 'الوحوش والمخلوقات', 'icon' => '&#128009;'],
    'concepts'           => ['label' => 'المصطلحات والمفاهيم', 'icon' => '&#128218;'],
];
?>

<div class="dedicated-encyclopedia-page" data-donghua-id="<?php echo esc_attr((string)$donghua_id); ?>">

    <!-- 1. Hero Banner Section -->
    <header class="dh-enc-hero">
        <div class="container dh-enc-hero-container">

            <!-- Breadcrumbs -->
            <nav class="dh-enc-breadcrumbs" aria-label="مسار التنقل">
                <a href="<?php echo esc_url(home_url('/')); ?>">الرئيسية</a>
                <span class="sep">&lsaquo;</span>
                <a href="<?php echo esc_url(home_url('/encyclopedia/')); ?>">موسوعة الدونغهوا</a>
                <span class="sep">&lsaquo;</span>
                <span class="current">موسوعة <?php echo esc_html($ar_name); ?></span>
            </nav>

            <div class="dh-enc-hero-grid">
                <!-- Poster Column -->
                <div class="dh-enc-poster-wrap">
                    <?php if ($poster_url): ?>
                        <img src="<?php echo esc_url($poster_url); ?>" alt="<?php echo esc_attr($ar_name); ?>" class="dh-enc-poster-img" />
                    <?php else: ?>
                        <div class="dh-enc-poster-placeholder">&#128214;</div>
                    <?php endif; ?>
                </div>

                <!-- Main Info Column -->
                <div class="dh-enc-info-wrap">
                    <div class="dh-enc-badge-row">
                        <span class="dh-enc-portal-tag">&#10024; موسوعة العمل</span>
                        <?php if (!empty($status)): ?>
                            <span class="dh-enc-status-badge"><?php echo esc_html($status[0]); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($year)): ?>
                            <span class="dh-enc-year-badge"><?php echo esc_html($year[0]); ?></span>
                        <?php endif; ?>
                        <span class="dh-enc-score-badge">&#9733; <?php echo esc_html($score); ?></span>
                    </div>

                    <h1 class="dh-enc-title">
                        موسوعة <?php echo esc_html($ar_name); ?>
                    </h1>

                    <?php if ($en_name): ?>
                        <div class="dh-enc-subtitle-en">
                            <?php echo esc_html($en_name); ?> — Comprehensive Knowledge Hub
                        </div>
                    <?php endif; ?>

                    <p class="dh-enc-hero-desc">
                        مداخل المعرفة المرتبطة بعمل <strong><?php echo esc_html($ar_name); ?></strong>، عند توفرها.
                    </p>

                    <!-- Stats Row -->
                    <div class="dh-enc-stats-bar">
                        <div class="dh-stat-item">
                            <span class="dh-stat-num"><?php echo esc_html((string)$total_count); ?></span>
                            <span class="dh-stat-lbl">إجمالي المداخل</span>
                        </div>
                        <div class="dh-stat-item">
                            <span class="dh-stat-num"><?php echo esc_html((string)count($entries_by_type['characters'] ?? [])); ?></span>
                            <span class="dh-stat-lbl">شخصيات</span>
                        </div>
                        <div class="dh-stat-item">
                            <span class="dh-stat-num"><?php echo esc_html((string)count($entries_by_type['cultivation-realms'] ?? [])); ?></span>
                            <span class="dh-stat-lbl">مراحل زراعة</span>
                        </div>
                        <div class="dh-stat-item">
                            <span class="dh-stat-num"><?php echo esc_html((string)count($entries_by_type['sects'] ?? [])); ?></span>
                            <span class="dh-stat-lbl">طوائف</span>
                        </div>
                        <div class="dh-stat-item">
                            <span class="dh-stat-num"><?php echo esc_html((string)count($entries_by_type['treasures'] ?? [])); ?></span>
                            <span class="dh-stat-lbl">كنوز وأسلحة</span>
                        </div>
                    </div>

                    <!-- Quick Action Buttons -->
                    <div class="dh-enc-actions-row">
                        <a href="<?php echo esc_url(get_permalink($donghua_id)); ?>" class="dh-btn-primary">
                            <span class="icon">&#9658;</span> صفحة العمل والحلقات
                        </a>
                        <a href="#dh-enc-tabs-section" class="dh-btn-secondary">
                            <span class="icon">&#9776;</span> تصفح الأقسام الموسوعية
                        </a>
                        <?php if (!empty($central_relationships) || $central_node): ?>
                            <a href="#dh-relationship-graph-section" class="dh-btn-ghost">
                                <span class="icon">&#128451;</span> خريطة العلاقات
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </header>

    <div class="container dh-enc-main-content">

        <?php if ($is_preview): ?>
            <!-- Editor Preview Mode Alert Banner (Constraint 2) -->
            <div class="encyclopedia-preview-banner">
                <div class="encyclopedia-preview-banner__content">
                    <span class="encyclopedia-preview-banner__icon">⚠️</span>
                    <div>
                        <strong>وضع معاينة المحررين النشط (Editor Preview Mode)</strong>
                        <p>أنت تشاهد حالياً جميع المداخل الموسوعية (بما فيها المسودات والمداخل قيد المراجعة). الزوار العاديون يشاهدون المداخل المعتمدة والمنشورة فقط.</p>
                    </div>
                </div>
                <a href="<?php echo esc_url(remove_query_arg('encyclopedia_preview')); ?>" class="dh-btn-secondary">الخروج من وضع المعاينة</a>
            </div>
        <?php endif; ?>

        <!-- 2. Relationship Graph Section -->
        <section id="dh-relationship-graph-section" class="dh-graph-section" aria-label="خريطة شبكة العلاقات">
            <div class="section-title-wrap dh-graph-title-row">
                <div class="title-left">
                    <span class="decor-dot"></span>
                    <h2 class="section-title">خريطة العلاقات التفاعلية (Relationship Graph)</h2>
                    <p class="section-subtitle">استكشف الترابطات والصلات بين الشخصيات، الطوائف، الأسلحة ومراحل القوة.</p>
                </div>

                <?php if (!empty($candidate_nodes)): ?>
                    <!-- Quick Focal Node Switcher -->
                    <div class="dh-graph-focal-switcher">
                        <label for="dh-graph-select" class="switcher-lbl">اختر مركز الخريطة:</label>
                        <select id="dh-graph-select" class="dh-select-input" aria-label="تغيير الكيان المحوري في الخريطة">
                            <?php foreach ($candidate_nodes as $c_node): ?>
                                <option value="<?php echo esc_attr((string)$c_node['id']); ?>" <?php selected($central_node && $central_node->ID === $c_node['id']); ?>>
                                    <?php echo esc_html($c_node['title']); ?> (<?php echo esc_html($c_node['type_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="dh-graph-viewport" id="dh-graph-viewport">
                <?php if ($central_node && !empty($central_relationships)): ?>
                    <?php
                    $c_ar    = get_post_meta($central_node->ID, '_encyclopedia_ar_name', true) ?: $central_node->post_title;
                    $c_en    = get_post_meta($central_node->ID, '_encyclopedia_en_name', true);
                    $c_types = get_the_terms($central_node->ID, 'encyclopedia_type');
                    $c_tname = (!empty($c_types) && !is_wp_error($c_types)) ? $c_types[0]->name : 'شخصية';
                    $c_tslug = (!empty($c_types) && !is_wp_error($c_types)) ? $c_types[0]->slug : 'character';
                    ?>
                    <div class="dh-graph-network-tree" id="dh-graph-network-tree" data-current-id="<?php echo esc_attr((string)$central_node->ID); ?>">
                        
                        <!-- Center Focal Node -->
                        <div class="dh-graph-central-node enc-badge-<?php echo esc_attr($c_tslug); ?>">
                            <div class="central-node-glow"></div>
                            <span class="central-node-tag"><?php echo esc_html($c_tname); ?></span>
                            <h3 class="central-node-title"><?php echo esc_html($c_ar); ?></h3>
                            <?php if ($c_en): ?>
                                <span class="central-node-en"><?php echo esc_html($c_en); ?></span>
                            <?php endif; ?>
                            <a href="<?php echo esc_url(get_permalink($central_node->ID)); ?>" class="central-node-link">
                                عرض المدخل &lsaquo;
                            </a>
                        </div>

                        <!-- Branches Connector Line -->
                        <div class="dh-graph-connector-spine"></div>

                        <!-- Related Nodes Grid / Branches -->
                        <div class="dh-graph-branches-grid" id="dh-graph-branches-grid">
                            <?php foreach ($central_relationships as $rel): ?>
                                <?php
                                $t_id    = (int) $rel['target_id'];
                                $is_inv  = (bool) ($rel['is_inverse'] ?? false);
                                $dir_txt = $is_inv ? '← واردة' : 'صادرة →';
                                $dir_cls = $is_inv ? 'rel-incoming' : 'rel-outgoing';
                                $arrow   = $is_inv ? '&#8594;' : '&#8592;';
                                ?>
                                <div class="dh-graph-branch-card" data-node-id="<?php echo esc_attr((string)$t_id); ?>">
                                    <div class="branch-relation-badge <?php echo esc_attr($dir_cls); ?>">
                                        <span class="rel-icon"><?php echo esc_html($rel['icon'] ?? '&#8226;'); ?></span>
                                        <span class="rel-name"><?php echo esc_html($rel['relation_label']); ?></span>
                                        <span class="rel-arrow"><?php echo $arrow; ?></span>
                                    </div>
                                    <div class="branch-node-content">
                                        <div class="branch-node-header">
                                            <span class="branch-node-type"><?php echo esc_html($rel['target_type_label'] ?? 'مدخل'); ?></span>
                                            <span class="branch-dir-badge"><?php echo esc_html($dir_txt); ?></span>
                                        </div>
                                        <h4 class="branch-node-title">
                                            <a href="<?php echo esc_url($rel['target_url']); ?>">
                                                <?php echo esc_html($rel['target_ar_name'] ?: $rel['target_title']); ?>
                                            </a>
                                        </h4>
                                        <?php if (!empty($rel['target_en_name'])): ?>
                                            <span class="branch-node-en"><?php echo esc_html($rel['target_en_name']); ?></span>
                                        <?php endif; ?>
                                        <button type="button" class="btn-pivot-graph" data-target-id="<?php echo esc_attr((string)$t_id); ?>" title="اجعل هذا الكيان مركز الخريطة">
                                            &#128451; تركيز الخريطة عليه
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                    </div>
                <?php else: ?>
                    <div class="dh-graph-empty-state">
                        <span class="empty-icon">&#128451;</span>
                        <h3 class="empty-title">خريطة العلاقات غير متوفرة حالياً</h3>
                        <p class="empty-desc">لم يتم ربط علاقات موسوعية بعد لهذا العمل. سيتم إضافة شبكة العلاقات قريباً.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 3. Cultivation Pathway Section -->
        <?php if (!empty($pathway)): ?>
            <section class="dh-cultivation-pathway-section" aria-label="مسار مراحل زراعة الخلود">
                <div class="section-title-wrap">
                    <span class="decor-dot"></span>
                    <h2 class="section-title">مسار مراحل زراعة الخلود (Cultivation Pathway)</h2>
                    <p class="section-subtitle">التسلسل الهرمي الدقيق لمراحل اختراق وتطور الطاقة في عالم <strong><?php echo esc_html($ar_name); ?></strong>.</p>
                </div>

                <!-- Desktop Flow Track -->
                <div class="dh-pathway-desktop-track">
                    <div class="dh-pathway-scroll-wrapper">
                        <?php foreach ($pathway as $index => $stage): ?>
                            <div class="dh-pathway-node-card <?php echo $index === 0 ? 'is-first-realm' : ''; ?>">
                                <div class="dh-pathway-order-pill">
                                    المرحلة <?php echo esc_html((string)($index + 1)); ?>
                                </div>
                                <h3 class="dh-pathway-node-title">
                                    <a href="<?php echo esc_url($stage['url']); ?>">
                                        <?php echo esc_html($stage['ar_name']); ?>
                                    </a>
                                </h3>
                                <?php if (!empty($stage['en_name'])): ?>
                                    <span class="dh-pathway-node-en"><?php echo esc_html($stage['en_name']); ?></span>
                                <?php endif; ?>

                                <?php if (!empty($stage['sub_stages']) && is_array($stage['sub_stages'])): ?>
                                    <div class="dh-pathway-substages-row">
                                        <?php foreach ($stage['sub_stages'] as $stg): ?>
                                            <span class="substage-pill"><?php echo esc_html($stg); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <a href="<?php echo esc_url($stage['url']); ?>" class="dh-pathway-explore-link">
                                    تفاصيل المرحلة &lsaquo;
                                </a>
                            </div>

                            <?php if ($index < count($pathway) - 1): ?>
                                <div class="dh-pathway-flow-arrow" aria-hidden="true">&#8592;</div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Mobile Vertical Timeline -->
                <div class="dh-pathway-mobile-timeline">
                    <div class="dh-timeline-spine"></div>
                    <div class="dh-timeline-items">
                        <?php foreach ($pathway as $index => $stage): ?>
                            <div class="dh-timeline-step-row">
                                <div class="dh-timeline-marker">
                                    <span><?php echo esc_html((string)($index + 1)); ?></span>
                                </div>
                                <div class="dh-timeline-step-content">
                                    <div class="dh-timeline-top">
                                        <span class="dh-timeline-num-badge">المرحلة <?php echo esc_html((string)($index + 1)); ?></span>
                                        <?php if (!empty($stage['en_name'])): ?>
                                            <span class="dh-timeline-en"><?php echo esc_html($stage['en_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <h4 class="dh-timeline-title">
                                        <a href="<?php echo esc_url($stage['url']); ?>">
                                            <?php echo esc_html($stage['ar_name']); ?>
                                        </a>
                                    </h4>
                                    <?php if (!empty($stage['sub_stages']) && is_array($stage['sub_stages'])): ?>
                                        <div class="dh-pathway-substages-row">
                                            <?php foreach ($stage['sub_stages'] as $stg): ?>
                                                <span class="substage-pill"><?php echo esc_html($stg); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </section>
        <?php endif; ?>

        <!-- 4. 8-Category Tabs & Entry Grid Section -->
        <section id="dh-enc-tabs-section" class="dh-entries-section" aria-label="أقسام الموسوعة ومداخلها">
            <div class="section-title-wrap">
                <span class="decor-dot"></span>
                <h2 class="section-title">فهرس مداخل الموسوعة (Encyclopedia Directory)</h2>
                <p class="section-subtitle">تصفح المداخل مقسمة حسب الفئات والأنواع المعرفية.</p>
            </div>

            <div class="dh-enc-work-search">
                <label for="dh-enc-work-search">ابحث في موسوعة هذا العمل</label>
                <input id="dh-enc-work-search" type="search" autocomplete="off" placeholder="الاسم العربي أو الإنجليزي أو الصيني أو الاسم البديل" />
                <p id="dh-enc-work-search-status" role="status" aria-live="polite"></p>
            </div>

            <!-- Tab Navigation Bar -->
            <div class="dh-enc-tabs-bar" role="tablist" aria-label="فئات الموسوعة">
                <button type="button" class="dh-tab-btn is-active" role="tab" aria-selected="true" data-tab="all" id="tab-all" aria-controls="panel-all">
                    <span class="tab-icon">&#10024;</span>
                    <span class="tab-label">الكل</span>
                    <span class="tab-count"><?php echo esc_html((string)$total_count); ?></span>
                </button>

                <?php foreach ($categories_def as $cat_key => $cat_data): ?>
                    <?php
                    $cat_count = isset($entries_by_type[$cat_key]) ? count($entries_by_type[$cat_key]) : 0;
                    if ($cat_count === 0) continue;
                    ?>
                    <button type="button" class="dh-tab-btn" role="tab" aria-selected="false" data-tab="<?php echo esc_attr($cat_key); ?>" id="tab-<?php echo esc_attr($cat_key); ?>" aria-controls="panel-all">
                        <span class="tab-icon"><?php echo $cat_data['icon']; ?></span>
                        <span class="tab-label"><?php echo esc_html($cat_data['label']); ?></span>
                        <span class="tab-count"><?php echo esc_html((string)$cat_count); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Progressive Enhancement Card Grid (Fully Server Rendered) -->
            <div class="dh-entries-grid-container" id="dh-entries-grid-container" role="tabpanel" id="panel-all" aria-labelledby="tab-all">
                <?php if (!empty($all_entries)): ?>
                    <div class="encyclopedia-grid dh-enc-cards-grid" id="dh-enc-cards-grid">
                        <?php foreach ($all_entries as $post): setup_postdata($post); ?>
                            <?php
                            $types    = get_the_terms($post->ID, 'encyclopedia_type');
                            $raw_slug = (!empty($types) && !is_wp_error($types)) ? $types[0]->slug : 'concepts';
                            $c_slug   = $type_category_map[$raw_slug] ?? 'concepts';
                            ?>
                            <?php $entry_aliases = class_exists('\\DonghuaCore\\Meta\\EncyclopediaMeta') ? \DonghuaCore\Meta\EncyclopediaMeta::get_aliases((int)$post->ID) : []; $entry_search_names = array_merge([$post->post_title, get_post_meta($post->ID, '_encyclopedia_ar_name', true), get_post_meta($post->ID, '_encyclopedia_en_name', true), get_post_meta($post->ID, '_encyclopedia_zh_name', true), get_post_meta($post->ID, '_encyclopedia_pinyin', true)], $entry_aliases); ?>
                            <div class="dh-card-wrapper" data-category="<?php echo esc_attr($c_slug); ?>" data-search="<?php echo esc_attr(implode(' ', array_filter($entry_search_names))); ?>">
                                <?php get_template_part('template-parts/encyclopedia-card'); ?>
                            </div>
                        <?php endforeach; wp_reset_postdata(); ?>
                    </div>

                    <!-- Client-side Tab Empty State Notice -->
                    <div class="dh-tab-empty-notice" id="dh-tab-empty-notice" style="display:none;">
                        <span class="notice-icon">&#128214;</span>
                        <h4 class="notice-title">لا توجد مداخل في هذا القسم حتى الآن</h4>
                        <p class="notice-text">لم تتم إضافة عناصر تابعة لهذا التصنيف في موسوعة <strong><?php echo esc_html($ar_name); ?></strong> بعد.</p>
                    </div>

                <?php else: ?>
                    <div class="dh-empty-full-state">
                        <span class="empty-icon">&#128218;</span>
                        <h3 class="empty-title">لا توجد مداخل موسوعية مسجلة بعد</h3>
                        <p class="empty-text">يجري العمل على إدخال مصطلحات وشخصيات ومراحل قوى هذا العمل قريباً.</p>
                    </div>
                <?php endif; ?>
            </div>

        </section>

    </div><!-- .dh-enc-main-content -->

</div><!-- .dedicated-encyclopedia-page -->

<?php get_footer(); ?>
