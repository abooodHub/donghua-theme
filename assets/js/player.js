/**
 * Donghua Theme - Episode Watch Page & Video Player Script
 * Features:
 * 1. Seamless Server Switching with Jade Highlight & LocalStorage Memory
 * 2. Custom Error Overlay (restricted or blocked source fallback) + Auto-switch next server
 * 3. Mobile / Tablet Bottom Sheet Drawer (Smooth sliding episodes catalog)
 * 4. Auto-scrolling to Active Episode
 * 5. Cinema Mode (إطفاء الأنوار) with ESC key support
 * 6. Non-blocking AJAX Episode Problem Reporting
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // ── Elements ────────────────────────────────────────────────────────────
    const playerScreen      = document.getElementById('player-screen');
    const playerReadyOverlay = document.getElementById('player-ready-overlay');
    const playerReadyButton  = document.getElementById('player-ready-button');
    const serverPills       = document.querySelectorAll('.server-pill, .server-tab-btn');
    const cinemaBtn         = document.getElementById('cinema-mode-btn');
    const cinemaBackdrop    = document.getElementById('cinema-backdrop');

    // Persist only native-player preferences, never a media URL or credential.
    if (playerScreen) {
        const boundVideos = new WeakSet();
        const preferenceKey = 'okdonghua_player_preferences_v1';
        const defaults = {version:1, volume:1, muted:false, rate:1, serverKey:'', quality:'', autoNext:false};
        const allowedRates = [0.5,0.75,1,1.25,1.5,2];
        const allowedQualities = ['','360p','480p','720p','1080p','2160p'];
        function normalizePreferences(value) {
            const safe = Object.assign({}, defaults);
            if (!value || typeof value !== 'object' || Array.isArray(value)) return safe;
            if (typeof value.volume === 'number' && Number.isFinite(value.volume)) safe.volume = Math.min(1, Math.max(0, value.volume));
            if (typeof value.muted === 'boolean') safe.muted = value.muted;
            if (allowedRates.includes(value.rate)) safe.rate = value.rate;
            if (typeof value.serverKey === 'string' && value.serverKey.length <= 120 && !/[\u0000-\u001f/?#]/.test(value.serverKey) && !/https?:/i.test(value.serverKey)) safe.serverKey = value.serverKey;
            if (allowedQualities.includes(value.quality)) safe.quality = value.quality;
            if (typeof value.autoNext === 'boolean') safe.autoNext = value.autoNext;
            return safe;
        }
        function readPreferences() {
            try {
                const saved = JSON.parse(localStorage.getItem(preferenceKey) || 'null');
                if (saved && saved.version === 1) return normalizePreferences(saved);
                if (saved && typeof saved === 'object') return Object.assign({}, defaults);
                const legacy = JSON.parse(localStorage.getItem('donghua_native_player_preferences') || '{}');
                return normalizePreferences({
                    version:1,
                    volume: typeof legacy.volume === 'number' && legacy.volume >= 0 && legacy.volume <= 1 ? legacy.volume : 1,
                    muted: typeof legacy.muted === 'boolean' ? legacy.muted : false,
                    rate: [0.5,0.75,1,1.25,1.5,2].includes(legacy.rate) ? legacy.rate : 1,
                    serverKey: typeof localStorage.getItem('donghua_preferred_server') === 'string' ? localStorage.getItem('donghua_preferred_server').slice(0,120) : ''
                });
            } catch (_) { return Object.assign({}, defaults); }
        }
        let preferences = readPreferences();
        function savePreferences(patch) {
            if (!patch || typeof patch !== 'object' || Array.isArray(patch)) return;
            const next = Object.assign({}, preferences);
            if (typeof patch.volume === 'number' && Number.isFinite(patch.volume)) next.volume = Math.min(1, Math.max(0, patch.volume));
            if (typeof patch.muted === 'boolean') next.muted = patch.muted;
            if (allowedRates.includes(patch.rate)) next.rate = patch.rate;
            if (typeof patch.serverKey === 'string' && patch.serverKey.length <= 120 && !/[\u0000-\u001f/?#]/.test(patch.serverKey) && !/https?:/i.test(patch.serverKey)) next.serverKey = patch.serverKey;
            if (allowedQualities.includes(patch.quality)) next.quality = patch.quality;
            if (typeof patch.autoNext === 'boolean') next.autoNext = patch.autoNext;
            preferences = next;
            try { localStorage.setItem(preferenceKey, JSON.stringify(preferences)); } catch (_) {}
        }
        window.dhPlayerPreferences = Object.freeze({
            get: () => Object.assign({}, preferences),
            set: savePreferences,
            reset: () => { preferences = Object.assign({}, defaults); try { localStorage.removeItem(preferenceKey); localStorage.removeItem('donghua_native_player_preferences'); localStorage.removeItem('donghua_preferred_server'); } catch (_) {} }
        });
        function bindPreferences() {
            const video = playerScreen.querySelector('video');
            if (!video || boundVideos.has(video)) return;
            boundVideos.add(video);
            try {
                const saved = preferences;
                if (typeof saved.volume === 'number' && saved.volume >= 0 && saved.volume <= 1) video.volume = saved.volume;
                if (typeof saved.muted === 'boolean') video.muted = saved.muted;
                if ([0.5,0.75,1,1.25,1.5,2].includes(saved.rate)) video.playbackRate = saved.rate;
            } catch (_) { /* Storage may be unavailable or contain an older value. */ }
            const save = function () {
                savePreferences({volume:video.volume, muted:video.muted, rate:video.playbackRate});
            };
            video.addEventListener('volumechange', save);
            video.addEventListener('ratechange', save);
        }
        bindPreferences();
        new MutationObserver(bindPreferences).observe(playerScreen, {childList:true});
    }

    // Error Overlay Elements
    const errorOverlay      = document.getElementById('player-error-overlay');
    const errorTriggerBtn   = document.getElementById('player-error-trigger');
    const dismissOverlayBtn = document.getElementById('player-dismiss-overlay');
    const switchNextBtn     = document.getElementById('player-switch-next-server');
    const triggerReportBtn  = document.getElementById('player-trigger-report');
    const underReportBtn    = document.getElementById('trigger-report-details');
    const reportDetailsBox  = document.getElementById('watch-report-box');
    const reportForm        = document.getElementById('episode-report-form');

    // Drawer Elements
    const drawerPanel       = document.getElementById('episodes-drawer');
    const drawerBackdrop    = document.getElementById('episodes-drawer-backdrop');
    const toggleDrawerBtn   = document.getElementById('toggle-episodes-drawer');
    const closeDrawerBtn    = document.getElementById('close-episodes-drawer');
    const scrollContainer   = document.getElementById('episodes-scroll-container');
    const currentEpisodeItem= document.querySelector('.drawer-episode-item.is-current, .scroll-ep-item.active-episode');

    // ── 1. Server Switching Logic ───────────────────────────────────────────
    if (serverPills.length > 0 && playerScreen) {
        let loadTimer = null;

        function isDirectMediaUrl(url, pill) {
            return (pill && pill.getAttribute('data-is-direct') === '1')
                || /\.(mp4|m4v|mkv|m3u8|webm)(\?.*)?$/i.test(url);
        }

        function createDirectVideo(url, title) {
            const video = document.createElement('video');
            video.id = 'main-video-player';
            video.controls = true;
            video.playsInline = true;
            video.preload = 'metadata';
            video.src = url;
            video.title = title;
            video.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0;background:#071019;transition:opacity .25s ease;';

            const poster = playerScreen.getAttribute('data-player-poster');
            if (poster) video.poster = poster;

            video.addEventListener('error', function () {
                showErrorOverlay('media');
            });

            return video;
        }

        function createEmbedFrame(url, title) {
            const frame = document.createElement('iframe');
            frame.id = 'main-video-frame';
            frame.src = url;
            frame.frameBorder = '0';
            frame.scrolling = 'no';
            frame.allowFullscreen = true;
            frame.allow = 'autoplay; fullscreen; encrypted-media; picture-in-picture';
            frame.title = title;
            frame.loading = 'eager';
            return frame;
        }

        function replacePlayerSource(url, direct) {
            const current = playerScreen.querySelector('#main-video-frame, #main-video-player');
            const title = current ? current.getAttribute('title') : 'مشغل الحلقة';
            const replacement = direct ? createDirectVideo(url, title) : createEmbedFrame(url, title);

            if (current) {
                if (current.tagName === 'VIDEO') {
                    current.pause();
                    current.removeAttribute('src');
                    current.load();
                }
                current.replaceWith(replacement);
            } else {
                playerScreen.appendChild(replacement);
            }

            return replacement;
        }

        const qualitySelect = document.getElementById('watch-quality-select');
        const upgradeDialog = document.getElementById('watch-quality-upgrade');
        if (qualitySelect) {
            let chosen = '';
            const initiallyActive = Array.from(serverPills).find(pill => pill.classList.contains('is-active'));
            const initialQuality = initiallyActive ? initiallyActive.dataset.sourceQuality || '' : '';
            if (initialQuality && Array.from(qualitySelect.options).some(option => option.value === initialQuality)) {
                qualitySelect.value = initialQuality;
                chosen = initialQuality;
            }
            const savedQuality = window.dhPlayerPreferences ? window.dhPlayerPreferences.get().quality : '';
            const savedOption = Array.from(qualitySelect.options).find(option => option.value === savedQuality && option.dataset.locked !== '1');
            const savedQualityServer = savedOption && Array.from(serverPills).find(pill => pill.dataset.sourceAccess !== 'private' && pill.dataset.sourceQuality === savedQuality);
            if (savedQualityServer) { chooseServer(savedQualityServer, false, true); chosen = savedQuality; }
            qualitySelect.addEventListener('change', function () {
                const option = qualitySelect.selectedOptions[0];
                if (option && option.dataset.locked === '1') {
                    const currentPill = Array.from(serverPills).find(pill => pill.classList.contains('is-active'));
                    qualitySelect.value = currentPill ? currentPill.dataset.sourceQuality || '' : chosen;
                    if (upgradeDialog) upgradeDialog.showModal();
                    return;
                }
                const matching = Array.from(serverPills).find(pill => pill.dataset.sourceQuality === qualitySelect.value);
                if (matching) { chooseServer(matching, false, true); chosen = qualitySelect.value; if (window.dhPlayerPreferences && matching.dataset.sourceAccess !== 'private') window.dhPlayerPreferences.set({quality:chosen}); }
            });
            if (upgradeDialog) {
                upgradeDialog.querySelector('[data-quality-close]').addEventListener('click', function () { upgradeDialog.close(); });
                upgradeDialog.addEventListener('close', function () { qualitySelect.focus(); });
            }
        }

        const initialDirectVideo = playerScreen.querySelector('#main-video-player');
        if (initialDirectVideo) {
            initialDirectVideo.addEventListener('error', function () {
                showErrorOverlay('media');
            });
        }

        function chooseServer(pill, remember, preservePosition) {
            const previousVideo = playerScreen.querySelector('video');
            const position = preservePosition && previousVideo ? previousVideo.currentTime : null;
            const wasPlaying = Boolean(preservePosition && previousVideo && !previousVideo.paused);
            const targetUrl = pill.getAttribute('data-server-url');
            if (!targetUrl) return;

            try {
                const parsed = new URL(targetUrl, window.location.href);
                if (!['https:', 'http:'].includes(parsed.protocol)) return;
            } catch (_) {
                return;
            }

            // Update active styles
            serverPills.forEach(item => {
                const isActive = (item === pill);
                item.classList.toggle('is-active', isActive);
                item.classList.toggle('active', isActive);
                                item.setAttribute('aria-pressed', String(isActive));
            });

            const qualityControl = document.getElementById('watch-quality-select');
            if (qualityControl) qualityControl.value = pill.dataset.sourceQuality || '';

            // Keep the VLC control in place; enable it only for direct media servers.
            const isDirectMedia = isDirectMediaUrl(targetUrl, pill);
            const hubVlcBtn = document.getElementById('hub-open-vlc');
            if (hubVlcBtn) {
                hubVlcBtn.disabled = !isDirectMedia;
                hubVlcBtn.setAttribute('aria-disabled', String(!isDirectMedia));
                hubVlcBtn.title = isDirectMedia
                    ? 'فتح الرابط المباشر في مشغل VLC الخارجي'
                    : 'يتوفر فتح VLC مع روابط الفيديو المباشرة فقط';
            }
            const hubCopyBtn = document.getElementById('hub-copy-link');
            const hubCopyText = document.getElementById('hub-copy-text');
            if (hubCopyBtn) {
                hubCopyBtn.setAttribute('data-is-direct', isDirectMedia ? '1' : '0');
                hubCopyBtn.setAttribute('title', hubCopyBtn.dataset.shareLink === '1'
                    ? 'مشاركة رابط الحلقة'
                    : (isDirectMedia ? 'نسخ رابط ملف الفيديو المباشر' : 'نسخ رابط صفحة هذه الحلقة'));
            }
            if (hubCopyText && (!hubCopyBtn || hubCopyBtn.dataset.shareLink !== '1')) {
                hubCopyText.textContent = isDirectMedia ? 'نسخ رابط الملف' : 'نسخ رابط الحلقة';
            }

            // Remember preference
            if (remember && pill.dataset.serverKey && pill.dataset.sourceAccess !== 'private') {
                if (window.dhPlayerPreferences) window.dhPlayerPreferences.set({serverKey:pill.dataset.serverKey,quality:pill.dataset.sourceQuality||''});
            }

            // An embed URL alone does not mean playback failed. Keep the
            // cinematic poster state until the viewer opens the player.
            hideErrorOverlay();

            // Swap only the player surface. A direct file must never be loaded in an iframe.
            clearTimeout(loadTimer);
            const playerSurface = replacePlayerSource(targetUrl, isDirectMedia);
            playerSurface.style.opacity = '0.4';

            const finishLoad = function () {
                clearTimeout(loadTimer);
                playerSurface.style.opacity = '1';
                if (position !== null && playerSurface.tagName === 'VIDEO') {
                    playerSurface.currentTime = Number.isFinite(playerSurface.duration) ? Math.min(position, playerSurface.duration) : position;
                    if (wasPlaying) playerSurface.play().catch(function () {});
                }
            };
            playerSurface.addEventListener(isDirectMedia ? 'loadedmetadata' : 'load', finishLoad, { once: true });

            // Leave the surface visible if an external embed never sends a load event.
            loadTimer = setTimeout(function () {
                playerSurface.style.opacity = '1';
            }, 12000);
        }

        serverPills.forEach(pill => {
            pill.addEventListener('click', function (e) {
                e.preventDefault();
                chooseServer(this, true);
            });
        });

        // Restore preferred server if saved
        try {
            const preferred = window.dhPlayerPreferences ? window.dhPlayerPreferences.get().serverKey : '';
            if (preferred) {
                const match = Array.from(serverPills).find(p => p.dataset.sourceAccess !== 'private' && p.dataset.serverKey === preferred);
                if (match && !match.classList.contains('is-active') && !match.classList.contains('active')) {
                    chooseServer(match, false);
                }
            }
        } catch (_) {}
    }

    // ── 2. Error Overlay & Fallback Controls ────────────────────────────────
    function showErrorOverlay(reason) {
        if (!errorOverlay) return;
        if (playerReadyOverlay) playerReadyOverlay.hidden = true;
        const title = errorOverlay.querySelector('.error-title');
        const description = errorOverlay.querySelector('.error-desc');
        if (reason === 'media') {
            if (title) title.textContent = 'تعذر تشغيل ملف الفيديو في المتصفح';
            if (description) description.textContent = 'قد لا يدعم المتصفح صيغة الملف أو ترميزه. جرّب مشغّل VLC عند توفر الرابط المباشر.';
        } else {
            if (title) title.textContent = 'تعذر تشغيل هذا السيرفر';
            if (description) description.textContent = 'قد يكون المصدر غير متاح مؤقتًا. جرّب سيرفرًا آخر أو أبلغنا عن المشكلة.';
        }
        errorOverlay.removeAttribute('hidden');
        errorOverlay.classList.add('is-visible');
    }

    function hideErrorOverlay() {
        if (!errorOverlay) return;
        errorOverlay.setAttribute('hidden', '');
        errorOverlay.classList.remove('is-visible');
    }

    if (playerReadyButton && playerReadyOverlay) {
        playerReadyButton.addEventListener('click', function () {
            playerReadyOverlay.hidden = true;
            const video = playerScreen ? playerScreen.querySelector('#main-video-player') : null;
            if (video && typeof video.play === 'function') {
                const playRequest = video.play();
                if (playRequest && typeof playRequest.catch === 'function') {
                    playRequest.catch(function () { showErrorOverlay('media'); });
                }
            }
        });
    }

    // Manual trigger button "⚠️ الفيديو لا يعمل؟"
    if (errorTriggerBtn) {
        errorTriggerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (errorOverlay && errorOverlay.classList.contains('is-visible')) {
                hideErrorOverlay();
            } else {
                showErrorOverlay();
            }
        });
    }

    if (dismissOverlayBtn) {
        dismissOverlayBtn.addEventListener('click', function (e) {
            e.preventDefault();
            hideErrorOverlay();
        });
    }

    // Switch to Next Available Server
    if (switchNextBtn && serverPills.length > 1) {
        switchNextBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const pillsArray = Array.from(serverPills);
            const activeIndex = pillsArray.findIndex(p => p.classList.contains('is-active') || p.classList.contains('active'));
            const nextIndex = (activeIndex + 1) % pillsArray.length;
            const nextPill = pillsArray[nextIndex];
            if (nextPill) {
                hideErrorOverlay();
                nextPill.click();
            }
        });
    }

    // Open Report Section from overlay or under-player button
    function openReportSection() {
        hideErrorOverlay();
        if (reportDetailsBox) {
            reportDetailsBox.hidden = false;
            reportDetailsBox.open = true;
            reportDetailsBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const textarea = reportDetailsBox.querySelector('textarea, select');
            if (textarea) setTimeout(() => textarea.focus(), 300);
        }
    }

    if (triggerReportBtn) {
        triggerReportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openReportSection();
        });
    }

    if (underReportBtn) {
        underReportBtn.addEventListener('click', function (e) {
            e.preventDefault();
            openReportSection();
        });
    }

    // ── 3. Mobile / Tablet Bottom Sheet Episodes Drawer ────────────────────
    function isMobileView() {
        return window.innerWidth <= 1024;
    }

    function syncEpisodeDrawer() { if (drawerPanel) drawerPanel.inert = isMobileView() && !drawerPanel.classList.contains('is-open'); }
    syncEpisodeDrawer();
    window.addEventListener('resize', syncEpisodeDrawer);
    function drawerFocusables() { return drawerPanel ? Array.from(drawerPanel.querySelectorAll('button,a[href],input,select,textarea')).filter(el => !el.disabled && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden') : []; }
    document.addEventListener('keydown', function (event) {
        if (!drawerPanel || !drawerPanel.classList.contains('is-open') || event.key !== 'Tab') return;
        const items = drawerFocusables(); if (!items.length) return;
        if (event.shiftKey && document.activeElement === items[0]) { event.preventDefault(); items[items.length - 1].focus(); }
        else if (!event.shiftKey && document.activeElement === items[items.length - 1]) { event.preventDefault(); items[0].focus(); }
    });
    function openEpisodesDrawer() {
        if (!drawerPanel) return;
        drawerPanel.classList.add('is-open');
        drawerPanel.inert = false;
        const first = drawerFocusables()[0]; if (first) first.focus();
        if (drawerBackdrop) drawerBackdrop.classList.add('is-active');
        if (toggleDrawerBtn) toggleDrawerBtn.setAttribute('aria-expanded', 'true');
        document.body.classList.add('drawer-locked');

        // Center active episode in scroll view
        scrollToCurrentEpisode();
    }

    function closeEpisodesDrawer() {
        if (!drawerPanel) return;
        drawerPanel.classList.remove('is-open');
        syncEpisodeDrawer();
        if (toggleDrawerBtn) toggleDrawerBtn.focus();
        if (drawerBackdrop) drawerBackdrop.classList.remove('is-active');
        if (toggleDrawerBtn) toggleDrawerBtn.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('drawer-locked');
    }

    if (toggleDrawerBtn) {
        toggleDrawerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            if (isMobileView()) {
                if (drawerPanel && drawerPanel.classList.contains('is-open')) {
                    closeEpisodesDrawer();
                } else {
                    openEpisodesDrawer();
                }
            } else {
                // On desktop, scroll smoothly down to drawer sidebar if needed
                if (drawerPanel) {
                    drawerPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            }
        });
    }

    if (closeDrawerBtn) {
        closeDrawerBtn.addEventListener('click', function (e) {
            e.preventDefault();
            closeEpisodesDrawer();
        });
    }

    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', function () {
            closeEpisodesDrawer();
        });
    }

    // ── 3.1 Episode Sorting Toggle (Ascending / Descending) ────────────────
    const sortEpisodesBtn = document.getElementById('sort-episodes-btn');
    if (sortEpisodesBtn && scrollContainer) {
        let isReversed = false;
        sortEpisodesBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const items = Array.from(scrollContainer.querySelectorAll('.drawer-episode-item'));
            if (items.length <= 1) return;
            isReversed = !isReversed;
            items.reverse();
            items.forEach(item => scrollContainer.appendChild(item));
            sortEpisodesBtn.classList.toggle('is-reversed', isReversed);
            sortEpisodesBtn.setAttribute('title', isReversed ? 'ترتيب تصاعدي (1 ← الأحدث)' : 'ترتيب تنازلي (الأحدث ← 1)');
            scrollToCurrentEpisode();
        });
    }

    // ── 4. Auto-Scroll to Current Active Episode ───────────────────────────
    function scrollToCurrentEpisode() {
        if (!currentEpisodeItem || !scrollContainer) return;
        window.requestAnimationFrame(function () {
            const containerHeight = scrollContainer.clientHeight;
            const itemOffset = currentEpisodeItem.offsetTop - scrollContainer.offsetTop;
            const targetScroll = Math.max(0, itemOffset - (containerHeight / 2) + 24);
            scrollContainer.scrollTo({
                top: targetScroll,
                behavior: 'smooth'
            });
        });
    }

    // Run auto-scroll on initial load
    scrollToCurrentEpisode();

    // ── 5. Cinema Mode (إطفاء الأنوار) ──────────────────────────────────────
    function toggleCinemaMode() {
        document.body.classList.toggle('cinema-mode-active');
        const isActive = document.body.classList.contains('cinema-mode-active');
        if (cinemaBtn) {
            cinemaBtn.setAttribute('aria-pressed', String(isActive));
            const labelSpan = cinemaBtn.querySelector('.util-label, .utility-text');
            if (labelSpan) {
                labelSpan.textContent = isActive ? 'تشغيل الأنوار' : 'إطفاء الأنوار';
            }
        }
    }

    if (cinemaBtn) {
        cinemaBtn.addEventListener('click', function (e) {
            e.preventDefault();
            toggleCinemaMode();
        });
    }

    if (cinemaBackdrop) {
        cinemaBackdrop.addEventListener('click', function () {
            if (document.body.classList.contains('cinema-mode-active')) {
                toggleCinemaMode();
            }
        });
    }

    // Keyboard shortcuts (Escape key)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (drawerPanel && drawerPanel.classList.contains('is-open')) {
                closeEpisodesDrawer();
            } else if (document.body.classList.contains('cinema-mode-active')) {
                toggleCinemaMode();
            } else if (errorOverlay && errorOverlay.classList.contains('is-visible')) {
                hideErrorOverlay();
            }
        }
    });

    // ── 6. Episode Problem Report AJAX Submission ───────────────────────────
    if (reportForm) {
        reportForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            const button  = reportForm.querySelector('button[type="submit"]');
            const statusMsg = document.getElementById('episode-report-status');
            if (!button || button.disabled) return;

            const body = new FormData(reportForm);
            const activePill = document.querySelector('.server-pill.is-active, .server-tab-btn.active');
            body.set('server_index', activePill ? (activePill.dataset.serverIndex || '0') : '0');
            const reportVideo = document.querySelector('.player-screen video, .watch-player video');
            body.set('media_time', reportVideo && Number.isFinite(reportVideo.currentTime) ? String(Math.max(0, Math.floor(reportVideo.currentTime))) : '0');
            body.set('player_error_code', reportVideo && reportVideo.error ? 'media_' + reportVideo.error.code : '');

            button.disabled = true;
            if (statusMsg) {
                statusMsg.textContent = 'جارٍ إرسال البلاغ…';
                statusMsg.style.color = 'var(--brand-primary)';
            }

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 12000);

            try {
                const response = await fetch(reportForm.dataset.endpoint || reportForm.action, {
                    method: 'POST',
                    body: body,
                    signal: controller.signal,
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.data?.message || 'تعذر تسجيل البلاغ، يرجى المحاولة مرة أخرى.');
                }
                if (statusMsg) {
                    statusMsg.textContent = '✓ تم استلام البلاغ بنجاح، شكراً لمساعدتنا!';
                    statusMsg.style.color = '#10b981';
                }
                reportForm.reset();
            } catch (error) {
                if (statusMsg) {
                    statusMsg.textContent = error.name === 'AbortError'
                        ? 'انتهت مهلة الاتصال بالخادم. يرجى إعادة المحاولة.'
                        : (error.message || 'حدث خطأ أثناء الإرسال.');
                    statusMsg.style.color = '#ef4444';
                }
            } finally {
                clearTimeout(timeout);
                button.disabled = false;
            }
        });
    }

    // ── 7. Real-Time Episode Viewers & Live Counter ─────────────────────────
    const watchContainer = document.querySelector('.watch-page-container[data-current-episode]');
    const totalViewsEl   = document.getElementById('episode-total-views');
    const liveViewersEl  = document.getElementById('episode-live-viewers');
    const liveBadgeEl    = document.getElementById('episode-live-badge');

    if (watchContainer && (totalViewsEl || liveViewersEl)) {
        const episodeId = watchContainer.getAttribute('data-current-episode');
        const restBase  = (window.donghuaConfig && window.donghuaConfig.restUrl)
            ? window.donghuaConfig.restUrl.replace(/\/+$/, '')
            : '/wp-json/donghua/v1';

        // 1. Persistent Browser/Viewer ID (localStorage)
        let viewerId = '';
        try {
            viewerId = localStorage.getItem('dh_viewer_id') || '';
            if (!viewerId || !/^[a-zA-Z0-9_\-]{8,64}$/.test(viewerId)) {
                viewerId = 'v_' + Math.random().toString(36).substring(2, 11) + Date.now().toString(36);
                localStorage.setItem('dh_viewer_id', viewerId);
            }
        } catch (_) {
            viewerId = 'v_' + Math.random().toString(36).substring(2, 11) + Date.now().toString(36);
        }

        // 2. Tab-specific ID (sessionStorage)
        let tabId = '';
        try {
            tabId = sessionStorage.getItem('dh_tab_id') || '';
            if (!tabId || !/^[a-zA-Z0-9_\-]{8,64}$/.test(tabId)) {
                tabId = 't_' + Math.random().toString(36).substring(2, 9) + Date.now().toString(36).substring(4);
                sessionStorage.setItem('dh_tab_id', tabId);
            }
        } catch (_) {
            tabId = 't_' + Math.random().toString(36).substring(2, 9) + Date.now().toString(36).substring(4);
        }

        let currentLive  = null;
        let currentViews = null;
        let lastHeartbeatTime = 0;

        function formatNumber(num) {
            try {
                return new Intl.NumberFormat().format(num);
            } catch (_) {
                return String(num);
            }
        }

        function updateStatsUI(data) {
            if (!data || typeof data !== 'object') return;

            // Update Live Viewers only if changed
            if (typeof data.live === 'number' && liveViewersEl && currentLive !== data.live) {
                currentLive = data.live;
                liveViewersEl.classList.add('is-updating');
                setTimeout(() => {
                    liveViewersEl.textContent = formatNumber(data.live);
                    liveViewersEl.classList.remove('is-updating');
                }, 150);

                if (liveBadgeEl) {
                    liveBadgeEl.setAttribute('aria-label', `${formatNumber(data.live)} مستخدمًا يشاهدون هذه الحلقة الآن`);
                }
            }

            // Update Total Views only if changed
            if (typeof data.views === 'number' && totalViewsEl && currentViews !== data.views) {
                currentViews = data.views;
                totalViewsEl.classList.add('is-updating');
                setTimeout(() => {
                    totalViewsEl.textContent = formatNumber(data.views);
                    totalViewsEl.classList.remove('is-updating');
                }, 150);
            }
        }

        async function sendHeartbeat() {
            lastHeartbeatTime = Date.now();
            try {
                const response = await fetch(`${restBase}/episodes/${episodeId}/heartbeat`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        viewer_id: viewerId,
                        tab_id: tabId
                    }),
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    const data = await response.json();
                    updateStatsUI(data);
                }
            } catch (_) {
                // Silently retain current UI numbers without console noise
            }
        }

        function sendLeaveBeacon() {
            const leaveUrl = `${restBase}/episodes/${episodeId}/leave`;
            const payload  = JSON.stringify({ viewer_id: viewerId, tab_id: tabId });

            if (navigator.sendBeacon) {
                try {
                    const blob = new Blob([payload], { type: 'application/json' });
                    navigator.sendBeacon(leaveUrl, blob);
                    return;
                } catch (_) {}
            }

            try {
                fetch(leaveUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: payload,
                    keepalive: true,
                    credentials: 'same-origin'
                }).catch(() => {});
            } catch (_) {}
        }

        // 1. Initial Heartbeat on page load
        sendHeartbeat();

        // 2. Periodic Heartbeat every 30 seconds
        setInterval(sendHeartbeat, 30000);

        // 3. Visibility Change (Only send if visible & last heartbeat was > 25s ago)
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                if (Date.now() - lastHeartbeatTime >= 25000) {
                    sendHeartbeat();
                }
            }
        });

        // 4. Best-effort Leave Beacon on tab close / navigate
        window.addEventListener('beforeunload', sendLeaveBeacon);
        window.addEventListener('pagehide', sendLeaveBeacon);
    }

    // ── 8. Streaming & Edition Hub (Data-Driven Interactions) ──────────────────
    const streamHub = document.getElementById('watch-stream-hub');
    if (streamHub) {
        // Layer 1: Edition Tabs (Only if multiple editions exist)
        const editionTabs = streamHub.querySelectorAll('.edition-tab');
        const editionHint = document.getElementById('active-edition-hint');
        const specType    = document.getElementById('spec-val-type');
        const specCompat  = document.getElementById('spec-compat-text');

        if (editionTabs.length > 0) {
            editionTabs.forEach(tab => {
                tab.addEventListener('click', function () {
                    const edKey = (this.dataset.edition || '').toLowerCase();
                    editionTabs.forEach(t => {
                        const isSelected = (t === tab);
                        t.classList.toggle('is-active', isSelected);
                        t.setAttribute('aria-selected', String(isSelected));
                    });
                    if (specType) {
                        const titleEl = this.querySelector('.tab-title');
                        const subEl = this.querySelector('.tab-sub');
                        specType.textContent = (titleEl ? titleEl.textContent.trim() : edKey) + (subEl ? ' (' + subEl.textContent.trim() + ')' : '');
                    }
                    if (editionHint) {
                        editionHint.textContent = edKey === 'softsub'
                            ? 'ترجمة منفصلة مدمجة قابلة للتخصيص والتعديل'
                            : (edKey === 'hardsub' ? 'ترجمة ملصقة متوافقة مع كافة الأجهزة' : 'النسخة المختارة للحلقة');
                    }
                    if (specCompat) {
                        specCompat.innerHTML = edKey === 'softsub'
                            ? '<strong>نصيحة المشاهدة:</strong> نسخ Softsub توفر أفضل وضوح للخطوط العربية. في حال واجهت أي اختلاف في الخطوط بالمتصفح، يمكنك استخدام مشغل خارجي مثل VLC أو زر "فتح في VLC" عند توفر الرابط المباشر.'
                            : '<strong>نصيحة المشاهدة:</strong> يمكنك التبديل بين السيرفرات في حال واجهت أي بطء في التحميل، أو استخدام زر نسخ الرابط لمشاركته أو تشغيله.';
                    }
                });
            });
        }

        // Layer 5: Action Buttons
        // 1. Watch Now (Smooth scroll to player screen)
        const watchNowBtn = document.getElementById('hub-watch-now');
        const playerScreen = document.getElementById('player-screen');
        if (watchNowBtn && playerScreen) {
            watchNowBtn.addEventListener('click', function (e) {
                e.preventDefault();
                playerScreen.scrollIntoView({ behavior: 'smooth', block: 'center' });
                playerScreen.classList.add('player-glow-pulse');
                setTimeout(() => playerScreen.classList.remove('player-glow-pulse'), 1500);
            });
        }

        // Helper to check active server pill
        function getActivePill() {
            return streamHub.querySelector('.server-pill.is-active, .server-pill.active') || serverPills[0] || null;
        }

        // 2. Open in VLC (Direct Media URLs ONLY)
        const openVlcBtn = document.getElementById('hub-open-vlc');
        if (openVlcBtn) {
            openVlcBtn.addEventListener('click', function (e) {
                e.preventDefault();
                const activePill = getActivePill();
                const url = activePill ? (activePill.getAttribute('data-server-url') || '') : '';
                if (!url) return;

                const isDirect = (activePill && activePill.getAttribute('data-is-direct') === '1') || Boolean(url.match(/\.(mp4|mkv|m3u8|webm)(\?.*)?$/i));
                if (isDirect) {
                    window.location.href = 'vlc://' + url.replace(/^https?:\/\//, '');
                }
            });
        }

        // 3. Download Jump (Only when downloads exist)
        const downloadJumpBtn = document.getElementById('hub-download-jump');
        const downloadsSection = document.getElementById('watch-downloads-section');
        if (downloadJumpBtn && downloadsSection) {
            downloadJumpBtn.addEventListener('click', function (e) {
                e.preventDefault();
                downloadsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                downloadsSection.classList.add('downloads-highlight-pulse');
                setTimeout(() => downloadsSection.classList.remove('downloads-highlight-pulse'), 1800);
            });
        }

        // 4. Copy Link (Copies direct file URL if direct, copies episode canonical page URL if embed)
        const copyBtn = document.getElementById('hub-copy-link');
        const copyFeedback = document.getElementById('hub-copy-tooltip');
        const copyTextEl = document.getElementById('hub-copy-text');
        if (copyBtn) {
            copyBtn.addEventListener('click', async function (e) {
                e.preventDefault();
                const activePill = getActivePill();
                const canonicalPageUrl = copyBtn.getAttribute('data-episode-url') || window.location.href;
                const isDirect = (copyBtn.getAttribute('data-is-direct') === '1') || (activePill && activePill.getAttribute('data-is-direct') === '1');

                // Only copy the raw media file URL if direct; if embed/drive, copy the canonical episode URL
                const urlToCopy = (isDirect && activePill && activePill.getAttribute('data-server-url'))
                    ? activePill.getAttribute('data-server-url')
                    : canonicalPageUrl;

                if (copyBtn.dataset.shareLink === '1' && navigator.share) {
                    try {
                        await navigator.share({ title: document.title, url: canonicalPageUrl });
                        return;
                    } catch (shareError) {
                        if (shareError && shareError.name === 'AbortError') return;
                    }
                }

                const shareUrl = copyBtn.dataset.shareLink === '1' ? canonicalPageUrl : urlToCopy;

                try {
                    await navigator.clipboard.writeText(shareUrl);
                    if (copyFeedback) {
                        copyFeedback.classList.add('is-visible');
                        setTimeout(() => copyFeedback.classList.remove('is-visible'), 2200);
                    }
                    if (copyTextEl) {
                        const originalText = copyTextEl.textContent;
                        copyTextEl.textContent = 'تم النسخ!';
                        setTimeout(() => { copyTextEl.textContent = originalText; }, 2200);
                    }
                } catch (_) {
                    prompt('انسخ الرابط من هنا:', shareUrl);
                }
            });
        }

        // 5. Report Issue
        const hubReportBtn = document.getElementById('hub-report-trigger');
        if (hubReportBtn) {
            hubReportBtn.addEventListener('click', function (e) {
                e.preventDefault();
                openReportSection();
            });
        }
    }
});
