document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const root = document.getElementById('dh-glossary');
    if (!root) return;
    const input = root.querySelector('#glossary-search');
    const grid = root.querySelector('#glossary-grid');
    const status = root.querySelector('#glossary-results');
    const empty = root.querySelector('#glossary-empty');
    const pagination = root.querySelector('#glossary-pagination');
    const dialog = root.querySelector('#glossary-dialog');
    const content = root.querySelector('#glossary-dialog-content');
    const normalize = text => text.toLocaleLowerCase().normalize('NFKD')
        .replace(/[\u0300-\u036f\u064b-\u065f\u0670\u0640]/g, '')
        .replace(/[أإآٱ]/g, 'ا').replace(/ى/g, 'ي');
    const entries = Array.from(grid.children).map(card => ({
        card, search: normalize(card.dataset.search), title: normalize(card.dataset.title), group: card.dataset.group,
    }));
    let category = 'all';
    let letter = '';
    const pageSize = 24;
    let page = 1;
    let returnFocus = null;
    root.querySelectorAll('[data-glossary-controls]').forEach(el => { el.hidden = false; });
    const setPressed = (selector, key, value) => root.querySelectorAll(selector).forEach(button => {
        button.setAttribute('aria-pressed', String(button.dataset[key] === value));
    });
    function update(saveUrl = true) {
        const words = normalize(input.value.trim()).split(/\s+/).filter(Boolean);
        const matches = [];
        entries.forEach(entry => {
            const matchesFilters = (category === 'all' || category === entry.group)
                && (!letter || entry.title.startsWith(normalize(letter)))
                && words.every(word => entry.search.includes(word));
            if (matchesFilters) matches.push(entry);
        });
        const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
        page = Math.min(Math.max(1, page), pageCount);
        const start = (page - 1) * pageSize;
        const pageEntries = new Set(matches.slice(start, start + pageSize));
        entries.forEach(entry => { entry.card.hidden = !pageEntries.has(entry); });
        status.textContent = matches.length
            ? `عرض ${start + 1}–${Math.min(start + pageSize, matches.length)} من ${matches.length} مدخل`
            : 'لا توجد نتائج مطابقة';
        empty.hidden = matches.length > 0;
        renderPagination(pageCount);
        setPressed('[data-category]', 'category', category);
        setPressed('[data-letter]', 'letter', letter);
        if (saveUrl) {
            const url = new URL(location.href);
            [['q', input.value.trim()], ['type', category === 'all' ? '' : category], ['letter', letter], ['page', page > 1 ? String(page) : '']].forEach(([key, value]) => {
                if (value) url.searchParams.set(key, value); else url.searchParams.delete(key);
            });
            history.replaceState(null, '', url);
        }
    }
    function renderPagination(pageCount) {
        if (!pagination) return;
        pagination.replaceChildren();
        pagination.hidden = pageCount <= 1;
        if (pageCount <= 1) return;

        const addButton = (label, targetPage, options = {}) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.disabled = Boolean(options.disabled);
            button.setAttribute('aria-label', options.label || `الصفحة ${targetPage}`);
            if (options.current) button.setAttribute('aria-current', 'page');
            button.addEventListener('click', () => {
                page = targetPage;
                update();
                root.querySelector('.glossary-toolbar').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            pagination.append(button);
        };

        addButton('السابق', page - 1, { disabled: page === 1, label: 'الصفحة السابقة' });
        const first = Math.max(1, Math.min(page - 2, pageCount - 4));
        const last = Math.min(pageCount, first + 4);
        for (let number = first; number <= last; number++) {
            addButton(String(number), number, { current: number === page, label: `الصفحة ${number}` });
        }
        addButton('التالي', page + 1, { disabled: page === pageCount, label: 'الصفحة التالية' });
    }
    function readUrl() {
        const params = new URLSearchParams(location.search);
        input.value = params.get('q') || '';
        const requested = params.get('type');
        category = Array.from(root.querySelectorAll('[data-category]')).some(b => b.dataset.category === requested) ? requested : 'all';
        const initialLetter = params.get('letter') || '';
        letter = Array.from(root.querySelectorAll('[data-letter]')).some(b => b.dataset.letter === initialLetter) ? initialLetter : '';
        const requestedPage = Number.parseInt(params.get('page') || '1', 10);
        page = Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1;
        update(false);
    }
    input.addEventListener('input', () => { page = 1; update(); });
    root.querySelectorAll('[data-category]').forEach(button => button.addEventListener('click', () => { category = button.dataset.category; page = 1; update(); }));
    root.querySelectorAll('[data-letter]').forEach(button => button.addEventListener('click', () => { letter = button.dataset.letter; page = 1; update(); }));
    root.querySelectorAll('[data-view]').forEach(button => button.addEventListener('click', () => {
        grid.classList.toggle('is-list', button.dataset.view === 'list');
        setPressed('[data-view]', 'view', button.dataset.view);
    }));
    root.querySelector('#glossary-reset').addEventListener('click', () => { input.value = ''; category = 'all'; letter = ''; page = 1; update(); input.focus(); });
    if (typeof dialog.showModal === 'function') {
        root.querySelectorAll('[data-detail]').forEach(link => link.addEventListener('click', event => {
            if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            const template = document.getElementById(link.dataset.detail);
            if (!template) return;
            event.preventDefault();
            returnFocus = link;
            content.replaceChildren(template.content.cloneNode(true));
            dialog.showModal();
            dialog.scrollTop = 0;
            document.body.classList.add('glossary-dialog-open');
            dialog.querySelector('button').focus();
        }));
        dialog.querySelector('.glossary-close').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            const bounds = dialog.getBoundingClientRect();
            if (event.target === dialog && (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom)) dialog.close();
        });
        dialog.addEventListener('close', () => {
            document.body.classList.remove('glossary-dialog-open');
            content.replaceChildren();
            if (returnFocus) returnFocus.focus();
        });
    }
    window.addEventListener('popstate', readUrl);
    readUrl();
});
