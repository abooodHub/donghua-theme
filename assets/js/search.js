/**
 * Donghua Theme - polished debounced live search.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const searchInput = document.getElementById('donghua-live-search') || document.getElementById('header-search-input');
    const resultsBox  = document.getElementById('live-search-results');
    const spinner     = document.getElementById('search-spinner');
    const clearBtn    = document.getElementById('donghua-search-clear');
    const form        = searchInput ? (searchInput.closest('.subbar-search-form') || searchInput.closest('.header-search-form')) : null;

    if (!searchInput || !resultsBox) return;

    function escapeHtml(value) {
        const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(value == null ? '' : value).replace(/[&<>"']/g, char => entities[char]);
    }

    function safeUrl(value) {
        try {
            const url = new URL(String(value || ''), window.location.href);
            return url.protocol === 'http:' || url.protocol === 'https:' ? url.href : '';
        } catch (error) {
            return '';
        }
    }

    let debounceTimeout = null;
    let requestController = null;
    let lastQuery = '';

    function setSearching(isSearching) {
        if (spinner) spinner.style.display = isSearching ? 'block' : 'none';
        if (form) form.classList.toggle('is-searching', isSearching);
    }

    function setResultsVisible(visible) {
        resultsBox.style.display = visible ? 'block' : 'none';
        resultsBox.classList.toggle('is-visible', visible);
        searchInput.setAttribute('aria-expanded', visible ? 'true' : 'false');
    }

    function updateClearButton() {
        if (clearBtn) clearBtn.hidden = searchInput.value.trim().length === 0;
    }

    const hint = document.getElementById('search-hint');
    let hintIndex = 0;
    const hints = [];

    function rotateHint() {
        if (!hint || !hints.length || document.activeElement === searchInput || searchInput.value.trim()) return;
        hintIndex = (hintIndex + 1) % hints.length;
        hint.classList.remove('is-changing');
        window.requestAnimationFrame(() => {
            hint.textContent = hints[hintIndex];
            hint.classList.add('is-changing');
        });
    }

    if (hint && hints.length) {
        window.setInterval(rotateHint, 2800);
    }
    updateClearButton();

    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            searchInput.value = '';
            lastQuery = '';
            if (requestController) { requestController.abort(); requestController = null; }
            clearTimeout(debounceTimeout);
            searchInput.focus();
            resultsBox.innerHTML = '';
            setResultsVisible(false);
            setSearching(false);
            updateClearButton();
        });
    }

    searchInput.addEventListener('focus', function () {
        if (form) form.classList.add('is-focused');
        searchInput.setAttribute('aria-expanded', resultsBox.innerHTML.trim() ? 'true' : 'false');
        if (resultsBox.innerHTML.trim()) setResultsVisible(true);
    });

    searchInput.addEventListener('blur', function () {
        if (form) form.classList.remove('is-focused');
    });

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();
        updateClearButton();
        clearTimeout(debounceTimeout);
        lastQuery = query;
        if (requestController) { requestController.abort(); requestController = null; }

        if (query.length < 2) {
            lastQuery = query;
            setResultsVisible(false);
            resultsBox.innerHTML = '';
            setSearching(false);
            return;
        }

        setSearching(true);

        debounceTimeout = setTimeout(function () {
            if (typeof donghuaConfig === 'undefined') return;

            fetch(donghuaConfig.restUrl + 'search?q=' + encodeURIComponent(query), { signal: (requestController = new AbortController()).signal })
                .then(response => response.json())
                .then(data => {
                    if (lastQuery !== query) return;
                    setSearching(false);

                    if (data.success && data.results && data.results.length > 0) {
                        const html = data.results.map(item => `
                            <a href="${escapeHtml(safeUrl(item.url))}" class="search-result-item">
                                <span class="search-thumb-wrap">
                                    ${item.poster ? `<img src="${escapeHtml(safeUrl(item.poster))}" class="search-thumb" alt="${escapeHtml(item.title)}" />` : '<span class="search-thumb-fallback">▶</span>'}
                                </span>
                                <span class="search-meta-info">
                                    <strong>${escapeHtml(item.ar_name || item.title)}</strong>
                                    ${item.en_name || item.zh_name ? `<span class="search-secondary-title" dir="auto">${escapeHtml(item.en_name || item.zh_name)}</span>` : ''}
                                    <span class="search-meta-badges">
                                        <em>${escapeHtml(item.year || '—')}</em>
                                        <em>${escapeHtml(item.animation_type || '3D')}</em>
                                        <em>${escapeHtml(item.status || '')}</em>
                                    </span>
                                </span>
                                <span class="search-open-arrow" aria-hidden="true">‹</span>
                            </a>
                        `).join('');
                        resultsBox.innerHTML = `<div class="search-results-head">نتائج سريعة</div>${html}`;
                        setResultsVisible(true);
                    } else {
                        resultsBox.innerHTML = '<div class="search-empty-state">لا توجد نتائج مطابقة. جرّب اسم العمل بالعربي أو الإنجليزي.</div>';
                        setResultsVisible(true);
                    }
                })
                .catch(error => {
                    if (error.name === 'AbortError') return;
                    setSearching(false);
                    setResultsVisible(false);
            });
        }, 260);
    });

    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !resultsBox.contains(e.target) && (!clearBtn || !clearBtn.contains(e.target))) {
            setResultsVisible(false);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            setResultsVisible(false);
            searchInput.blur();
        }
    });
});

