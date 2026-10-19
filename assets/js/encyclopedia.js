/**
 * Donghua Theme - Encyclopedia JavaScript Module v2.0.0
 * Live Multilingual Search, Debounce, Category Tabs, & Interactive Relationship Graph
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    // ── 1. Live Multilingual Search for Portal ─────────────────────────────
    function initLiveSearch() {
        const searchInput = document.getElementById('enc-live-search');
        const dropdown    = document.getElementById('enc-live-dropdown');
        const spinner     = document.getElementById('enc-search-spinner');

        if (!searchInput || !dropdown) {
            return;
        }

        let debounceTimer = null;
        let currentController = null;

        function renderResults(results, query) {
            if (!results || results.length === 0) {
                dropdown.innerHTML = `
                    <div class="enc-dropdown-empty">
                        <span class="icon">&#128269;</span>
                        <p>لم يتم العثور على نتائج موسوعية تطابق "<strong>${escapeHtml(query)}</strong>"</p>
                    </div>
                `;
                dropdown.style.display = 'block';
                return;
            }

            let html = '<div class="enc-dropdown-list">';
            results.forEach(item => {
                const thumbHtml = item.thumbnail
                    ? `<img src="${escapeHtml(item.thumbnail)}" alt="${escapeHtml(item.ar_name)}" class="enc-res-thumb" />`
                    : `<div class="enc-res-thumb-placeholder">&#128214;</div>`;

                const enZh = [item.en_name, item.zh_name, item.pinyin].filter(Boolean).join(' · ');

                const typeBadge = item.type
                    ? `<span class="enc-badge-type enc-badge-${escapeHtml(item.type.slug)}">${escapeHtml(item.type.name)}</span>`
                    : '';

                const dhTag = (item.related_donghua && item.related_donghua.length > 0)
                    ? `<span class="enc-res-dh"><span class="dh-icon">&#9658;</span> ${escapeHtml(item.related_donghua[0].title)}</span>`
                    : '';

                html += `
                    <a href="${escapeHtml(item.url)}" class="enc-dropdown-item">
                        <div class="enc-res-thumb-wrap">${thumbHtml}</div>
                        <div class="enc-res-info">
                            <div class="enc-res-top-row">
                                <h4 class="enc-res-title">${escapeHtml(item.ar_name)}</h4>
                                ${typeBadge}
                            </div>
                            ${enZh ? `<div class="enc-res-sub">${escapeHtml(enZh)}</div>` : ''}
                            <p class="enc-res-excerpt">${escapeHtml(item.excerpt)}</p>
                            ${dhTag}
                        </div>
                    </a>
                `;
            });
            html += '</div>';

            dropdown.innerHTML = html;
            dropdown.style.display = 'block';
        }

        searchInput.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(debounceTimer);

            if (query.length < 2) {
                dropdown.style.display = 'none';
                dropdown.innerHTML = '';
                if (spinner) spinner.style.display = 'none';
                if (currentController) currentController.abort();
                return;
            }

            if (spinner) spinner.style.display = 'block';

            debounceTimer = setTimeout(() => {
                if (currentController) {
                    currentController.abort();
                }
                currentController = new AbortController();

                const endpoint = `/wp-json/donghua/v1/encyclopedia/search?q=${encodeURIComponent(query)}&limit=8`;

                fetch(endpoint, { signal: currentController.signal })
                    .then(res => res.json())
                    .then(data => {
                        if (spinner) spinner.style.display = 'none';
                        if (data && data.success) {
                            renderResults(data.results, query);
                        }
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') {
                            if (spinner) spinner.style.display = 'none';
                            dropdown.style.display = 'none';
                        }
                    });
            }, 280);
        });

        // Close dropdown on click outside
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });

        // Keyboard support: Escape closes dropdown
        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                dropdown.style.display = 'none';
            }
        });
    }

    // ── 2. Dedicated Donghua Encyclopedia Category Tabs ────────────────────
    function initDedicatedEncyclopediaTabs() {
        const tabBtns     = document.querySelectorAll('.dh-tab-btn');
        const cards       = document.querySelectorAll('.dh-card-wrapper');
        const emptyNotice = document.getElementById('dh-tab-empty-notice');
        const searchInput = document.getElementById('dh-enc-work-search');
        const searchStatus = document.getElementById('dh-enc-work-search-status');

        if (!tabBtns.length || !cards.length) {
            return;
        }

        let activeCategory = 'all';
        function activateTab(category, updateHash) {
            if (updateHash === undefined) updateHash = true;
            activeCategory = category;
            const query = searchInput ? searchInput.value.trim().toLocaleLowerCase() : '';
            let visibleCount = 0;

            tabBtns.forEach(btn => {
                const btnCat = btn.getAttribute('data-tab');
                const isActive = (btnCat === category);
                btn.classList.toggle('is-active', isActive);
                btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });

            cards.forEach(card => {
                const cardCat = card.getAttribute('data-category');
                const searchText = (card.getAttribute('data-search') || '').toLocaleLowerCase();
                if ((category === 'all' || cardCat === category) && (!query || searchText.includes(query))) {
                    card.classList.remove('is-hidden');
                    visibleCount++;
                } else {
                    card.classList.add('is-hidden');
                }
            });

            if (emptyNotice) {
                emptyNotice.style.display = (visibleCount === 0) ? 'block' : 'none';
            }
            if (searchStatus) searchStatus.textContent = query ? `النتائج في هذا القسم: ${visibleCount}` : '';

            if (updateHash && window.history && window.history.replaceState) {
                const hash = (category === 'all') ? '' : `#tab-${category}`;
                const newUrl = window.location.pathname + window.location.search + hash;
                window.history.replaceState(null, '', newUrl);
            }
        }

        tabBtns.forEach(btn => {
            btn.addEventListener('click', function () {
                const category = this.getAttribute('data-tab') || 'all';
                activateTab(category);
            });
        });
        if (searchInput) searchInput.addEventListener('input', () => activateTab(activeCategory, false));

        // Check URL hash on initial page load
        if (window.location.hash) {
            const hashCat = window.location.hash.replace('#tab-', '').replace('#', '');
            const matchingBtn = document.querySelector(`.dh-tab-btn[data-tab="${hashCat}"]`);
            if (matchingBtn) {
                activateTab(hashCat, false);
            }
        }
    }

    // ── 3. Dedicated Donghua Interactive Relationship Graph ────────────────
    function initRelationshipGraph() {
        const viewport    = document.getElementById('dh-graph-viewport');
        const treeWrap    = document.getElementById('dh-graph-network-tree');
        const focalSelect = document.getElementById('dh-graph-select');

        if (!viewport || !treeWrap) {
            return;
        }

        let isFetching = false;

        function pivotToNode(nodeId) {
            if (isFetching || !nodeId) return;
            isFetching = true;

            viewport.style.opacity = '0.55';
            viewport.style.pointerEvents = 'none';

            const urlParams = new URLSearchParams(window.location.search);
            const isPrev = urlParams.get('encyclopedia_preview') === '1' ? '&preview=1' : '';
            const endpoint = `/wp-json/donghua/v1/encyclopedia/graph-node?id=${encodeURIComponent(nodeId)}&limit=12${isPrev}`;

            fetch(endpoint)
                .then(res => res.json())
                .then(data => {
                    isFetching = false;
                    viewport.style.opacity = '1';
                    viewport.style.pointerEvents = 'auto';

                    if (data && data.success && data.node) {
                        renderGraph(data.node, data.relationships || []);
                        if (focalSelect && focalSelect.value !== String(nodeId)) {
                            focalSelect.value = String(nodeId);
                        }
                    }
                })
                .catch(err => {
                    isFetching = false;
                    viewport.style.opacity = '1';
                    viewport.style.pointerEvents = 'auto';
                    console.error('Failed to load graph relationships', err);
                });
        }

        function renderGraph(node, relationships) {
            const tSlug = (typeof node.type === 'string') ? node.type : ((node.type && node.type.slug) ? node.type.slug : 'character');
            const tName = node.type_label || ((node.type && node.type.name) ? node.type.name : 'مدخل');

            let branchesHtml = '';
            if (relationships.length > 0) {
                relationships.forEach(rel => {
                    const isInv  = rel.is_inverse;
                    const dirTxt = isInv ? '← واردة' : 'صادرة →';
                    const dirCls = isInv ? 'rel-incoming' : 'rel-outgoing';
                    const arrow  = isInv ? '&#8594;' : '&#8592;';

                    branchesHtml += `
                        <div class="dh-graph-branch-card" data-node-id="${escapeHtml(String(rel.target_id))}">
                            <div class="branch-relation-badge ${dirCls}">
                                <span class="rel-icon">${escapeHtml(rel.icon || '•')}</span>
                                <span class="rel-name">${escapeHtml(rel.relation_label)}</span>
                                <span class="rel-arrow">${arrow}</span>
                            </div>
                            <div class="branch-node-content">
                                <div class="branch-node-header">
                                    <span class="branch-node-type">${escapeHtml(rel.target_type_label || (rel.target_type && rel.target_type.name) || 'مدخل')}</span>
                                    <span class="branch-dir-badge">${dirTxt}</span>
                                </div>
                                <h4 class="branch-node-title">
                                    <a href="${escapeHtml(rel.target_url)}">
                                        ${escapeHtml(rel.target_ar_name || rel.target_title)}
                                    </a>
                                </h4>
                                ${rel.target_en_name ? `<span class="branch-node-en">${escapeHtml(rel.target_en_name)}</span>` : ''}
                                <button type="button" class="btn-pivot-graph" data-target-id="${escapeHtml(String(rel.target_id))}" title="اجعل هذا الكيان مركز الخريطة">
                                    &#128451; تركيز الخريطة عليه
                                </button>
                            </div>
                        </div>
                    `;
                });
            } else {
                branchesHtml = `
                    <div class="dh-graph-empty-state" style="grid-column: 1 / -1;">
                        <span class="empty-icon">&#128451;</span>
                        <p class="empty-desc">لا توجد علاقات مباشرة مسجلة لهذا الكيان حتى الآن.</p>
                    </div>
                `;
            }

            treeWrap.setAttribute('data-current-id', String(node.id));
            treeWrap.innerHTML = `
                <div class="dh-graph-central-node enc-badge-${escapeHtml(tSlug)}">
                    <div class="central-node-glow"></div>
                    <span class="central-node-tag">${escapeHtml(tName)}</span>
                    <h3 class="central-node-title">${escapeHtml(node.ar_name)}</h3>
                    ${node.en_name ? `<span class="central-node-en">${escapeHtml(node.en_name)}</span>` : ''}
                    <a href="${escapeHtml(node.url)}" class="central-node-link">
                        عرض المدخل &lsaquo;
                    </a>
                </div>
                <div class="dh-graph-connector-spine"></div>
                <div class="dh-graph-branches-grid" id="dh-graph-branches-grid">
                    ${branchesHtml}
                </div>
            `;
        }

        // Listener for focal node select dropdown
        if (focalSelect) {
            focalSelect.addEventListener('change', function () {
                pivotToNode(this.value);
            });
        }

        // Event delegation for pivot buttons inside treeWrap
        treeWrap.addEventListener('click', function (e) {
            const pivotBtn = e.target.closest('.btn-pivot-graph');
            if (pivotBtn) {
                const targetId = pivotBtn.getAttribute('data-target-id');
                if (targetId) {
                    pivotToNode(targetId);
                }
            }
        });
    }

    // Initialize all modules
    initLiveSearch();
    initDedicatedEncyclopediaTabs();
    initRelationshipGraph();
});
