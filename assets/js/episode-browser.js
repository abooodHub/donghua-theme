document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const normalize = value => value.normalize('NFKD').toLowerCase()
        .replace(/[\u064b-\u065f\u0670\u0640]/g, '').replace(/ى/g, 'ي')
        .replace(/[٠-٩]/g, digit => String(digit.charCodeAt(0) - 1632));
    document.querySelectorAll('[data-episode-toolbar]').forEach(toolbar => {
        const list = document.getElementById(toolbar.dataset.episodeToolbar);
        if (!list) return;
        const rows = Array.from(list.querySelectorAll('[data-episode-id]'));
        const input = toolbar.querySelector('[data-episode-query]');
        const order = toolbar.querySelector('[data-episode-order]');
        const count = toolbar.querySelector('[data-episode-count]');
        const empty = list.parentElement.querySelector('.episode-search-empty');
        function filter() {
            const query = normalize(input.value.trim());
            let visible = 0;
            rows.forEach(row => {
                row.hidden = !normalize(row.dataset.search).includes(query);
                if (!row.hidden) visible++;
            });
            count.textContent = `${visible} من ${rows.length} حلقة`;
            if (empty) empty.hidden = visible > 0;
        }
        toolbar.hidden = rows.length === 0;
        input.addEventListener('input', filter);
        order.addEventListener('change', () => {
            const sorted = order.value === 'desc' ? [...rows].reverse() : rows;
            sorted.forEach(row => list.appendChild(row));
        });
        filter();
    });
    // Store an episode ID only. Restore through a currently published link, never a stored URL.
    const page = document.querySelector('[data-work-id]');
    if (!page || page.dataset.workId === '0') return;
    const key = `donghua_last_opened_${page.dataset.workId}`;
    try {
        if (page.dataset.currentEpisode) {
            localStorage.setItem(key, page.dataset.currentEpisode);
            return;
        }
        const saved = localStorage.getItem(key);
        const episode = Array.from(page.querySelectorAll('[data-episode-id]')).find(link => link.dataset.episodeId === saved);
        const resume = page.querySelector('.episode-resume');
        if (resume && resume.dataset.serverProgress === '1') return;
        if (episode && resume) {
            const link = resume.querySelector('[data-resume-link]');
            link.href = episode.href;
            link.textContent = `العودة إلى الحلقة ${episode.dataset.number}`;
            resume.hidden = false;
            const clear = resume.querySelector('[data-resume-clear]');
            if (clear) clear.addEventListener('click', () => {
                try { localStorage.removeItem(key); } catch (_) { /* Storage can become unavailable. */ }
                resume.hidden = true;
            });
        }
    } catch (_) { /* All episode navigation works without local storage. */ }
});
