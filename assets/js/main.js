/**
 * Donghua Theme - Main Front-End JavaScript v2.1.0
 * OKanime Portal Edition — Swiper Slider, Theme Switcher, Mobile Nav, and User Actions
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const scheduleRail = document.querySelector('.schedule-day-rail');
    const activeScheduleDay = scheduleRail && scheduleRail.querySelector('.schedule-day-tab.is-active');
    if (activeScheduleDay) {
        window.requestAnimationFrame(() => activeScheduleDay.scrollIntoView({ block: 'nearest', inline: 'center' }));
    }

    // Use the existing local mark when an image fails; never call a placeholder service.
    function imageFallback(image) {
        if (!(image instanceof HTMLImageElement)) return;
        if (image.dataset.fallbackShown) {
            image.hidden = true;
            return;
        }
        if (typeof donghuaConfig === 'undefined' || !donghuaConfig.imageFallbackUrl) return;
        image.dataset.fallbackShown = '1';
        image.removeAttribute('srcset');
        image.classList.add('dh-image-fallback');
        image.src = donghuaConfig.imageFallbackUrl;
    }
    document.addEventListener('error', function (event) { imageFallback(event.target); }, true);
    Array.from(document.images).filter(image => image.complete && image.naturalWidth === 0).forEach(imageFallback);

    // Theme state and persistence are owned by the single accessible switcher in theme.js.
    // ── 1. Initialize Hero Swiper Slider (Swiper 11 with Fade & Circular Progress) ──
    const swiperContainer = document.getElementById('hero-swiper');
    if (swiperContainer && typeof Swiper !== 'undefined') {
        const slidesCount = swiperContainer.querySelectorAll('.swiper-slide').length;
        const progressRing = document.getElementById('hero-progress-ring');
        const progressHead = document.getElementById('hero-progress-head');
        const autoplayBtn  = document.getElementById('hero-autoplay-btn');
        const circumference = 2 * Math.PI * 18; // ~113.097px (r=18)

        const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        let isAutoplayActive = slidesCount > 1 && !reduceMotion;

        if (progressRing) {
            progressRing.style.strokeDasharray = `${circumference}`;
        }
        function updateHeroProgress(remainingRatio) {
            const remaining = Math.min(1, Math.max(0, remainingRatio));
            if (progressRing) progressRing.style.strokeDashoffset = `${circumference * remaining}`;
            if (progressHead) progressHead.style.transform = `rotate(${(1 - remaining) * 360}deg)`;
        }
        function syncAutoplayControl() {
            if (!autoplayBtn) return;
            const label = isAutoplayActive ? 'إيقاف التشغيل التلقائي مؤقتاً' : 'تشغيل السلايدر تلقائياً';
            autoplayBtn.classList.toggle('is-paused', !isAutoplayActive);
            autoplayBtn.setAttribute('aria-label', label);
            autoplayBtn.setAttribute('title', label);
            autoplayBtn.hidden = slidesCount < 2;
        }
        updateHeroProgress(1);
        syncAutoplayControl();

        // ── Progressive Slide Background Hydration ─────────────────────────
        function hydrateSlide(slide) {
            if (!slide) return;
            const bg = slide.getAttribute('data-bg');
            if (bg) {
                slide.style.backgroundImage = bg;
                slide.removeAttribute('data-bg');
            }
        }

        function hydrateAllRemaining() {
            const allSlides = swiperContainer.querySelectorAll('.hero-slide[data-bg]');
            allSlides.forEach(hydrateSlide);
        }

        // Hydrate slide 2 early so it is ready well before 5s autoplay transition
        const initialSlides = swiperContainer.querySelectorAll('.hero-slide');
        if (initialSlides.length > 1) {
            hydrateSlide(initialSlides[1]);
        }

        // Hydrate all remaining slides after initial critical window (4000ms) or on slide change
        setTimeout(hydrateAllRemaining, 4000);

        function syncHeroFocus(swiper) { swiper.slides.forEach((slide, index) => { slide.inert = index !== swiper.activeIndex; }); }
        const heroSwiper = new Swiper('#hero-swiper', {
            loop: slidesCount > 1,
            speed: reduceMotion ? 0 : 800,
            effect: 'fade',
            fadeEffect: {
                crossFade: true,
            },
            autoplay: {
                enabled: isAutoplayActive,
                delay: 5000,
                disableOnInteraction: false,
                pauseOnMouseEnter: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
                renderBullet: function (index, className) { return '<button type="button" class="' + className + '" aria-label="عرض العمل ' + (index + 1) + '"></button>'; },
            },
            keyboard: {
                enabled: true,
            },
            grabCursor: true,
            touchEventsTarget: 'wrapper',
            on: {
                init: function (swiper) { syncHeroFocus(swiper); },
                slideChangeTransitionStart: function (swiper) {
                    if (swiper.slides && swiper.slides.length) {
                        hydrateSlide(swiper.slides[swiper.activeIndex]);
                        const nextIdx = (swiper.activeIndex + 1) % swiper.slides.length;
                        hydrateSlide(swiper.slides[nextIdx]);
                    }
                },
                autoplayTimeLeft: function (swiper, timeLeft, progress) {
                    if (isAutoplayActive) updateHeroProgress(progress);
                },
                slideChange: function () {
                    syncHeroFocus(this);
                    // Reset ring momentarily on slide change
                    if (isAutoplayActive) updateHeroProgress(1);
                },
            },
        });

        // Circular Play / Pause Button Controller
        if (autoplayBtn && heroSwiper && heroSwiper.autoplay) {
            autoplayBtn.addEventListener('click', function (e) {
                e.preventDefault();
                if (isAutoplayActive) {
                    heroSwiper.autoplay.stop();
                    isAutoplayActive = false;
                } else {
                    heroSwiper.autoplay.start();
                    isAutoplayActive = true;
                }
                syncAutoplayControl();
            });
        }
    }

    // ── 3. Accessible Mobile Menu Drawer & Focus Trap ─────────────────────────
    const menuToggle = document.getElementById('mobile-menu-toggle');
    const navMenu    = document.getElementById('site-navigation');
    const backdrop   = document.getElementById('mobile-nav-backdrop');
    const navClose   = document.getElementById('mobile-nav-close');
    let scrollPosition = 0;
    const mobileViewport = window.matchMedia('(max-width: 1024px)');
    function syncNavVisibility() { if (navMenu) navMenu.inert = mobileViewport.matches && !navMenu.classList.contains('is-open'); }
    syncNavVisibility();
    mobileViewport.addEventListener('change', syncNavVisibility);

    function openMobileNav() {
        if (!navMenu) return;
        scrollPosition = window.pageYOffset || document.documentElement.scrollTop;
        document.body.style.top = `-${scrollPosition}px`;
        document.body.style.position = 'fixed';
        document.body.style.width = '100%';
        document.body.classList.add('mobile-nav-open');

        navMenu.classList.add('is-open');
        navMenu.inert = false;
        if (backdrop) backdrop.classList.add('is-active');
        if (menuToggle) menuToggle.setAttribute('aria-expanded', 'true');

        setTimeout(() => {
            const focusable = Array.from(navMenu.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(el => !el.disabled && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
            if (focusable.length > 0) {
                focusable[0].focus();
            }
        }, 50);
    }

    function closeMobileNav() {
        if (!navMenu) return;
        navMenu.classList.remove('is-open');
        syncNavVisibility();
        if (backdrop) backdrop.classList.remove('is-active');
        if (menuToggle) menuToggle.setAttribute('aria-expanded', 'false');

        document.body.classList.remove('mobile-nav-open');
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        window.scrollTo(0, scrollPosition);

        if (menuToggle) menuToggle.focus();
    }

    if (menuToggle && navMenu) {
        menuToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (navMenu.classList.contains('is-open')) {
                closeMobileNav();
            } else {
                openMobileNav();
            }
        });

        if (navClose) {
            navClose.addEventListener('click', function (e) {
                e.stopPropagation();
                closeMobileNav();
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                closeMobileNav();
            });
        }

        document.addEventListener('keydown', function (e) {
            if (!navMenu.classList.contains('is-open')) return;

            if (e.key === 'Escape' || e.keyCode === 27) {
                closeMobileNav();
                return;
            }

            if (e.key === 'Tab' || e.keyCode === 9) {
                const focusable = Array.from(navMenu.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(el => !el.disabled && el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden');
                if (focusable.length === 0) return;

                const firstEl = focusable[0];
                const lastEl = focusable[focusable.length - 1];

                if (e.shiftKey) {
                    if (document.activeElement === firstEl) {
                        e.preventDefault();
                        lastEl.focus();
                    }
                } else {
                    if (document.activeElement === lastEl) {
                        e.preventDefault();
                        firstEl.focus();
                    }
                }
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024 && navMenu.classList.contains('is-open')) {
                closeMobileNav();
            }
        });
    }

    // ── 3.5. Accessible Expandable Tablet Search (P2.6) ─────────────────────────
    const searchWrap   = document.getElementById('header-search-wrap');
    const searchToggle = document.getElementById('header-search-toggle');
    const searchInput  = document.getElementById('header-search-input');
    const searchClose  = document.getElementById('header-search-close');

    function openTabletSearch() {
        if (!searchWrap) return;
        searchWrap.classList.add('is-expanded');
        if (searchToggle) {
            searchToggle.setAttribute('aria-expanded', 'true');
        }
        if (searchInput) {
            setTimeout(function () {
                searchInput.focus();
            }, 60);
        }
    }

    function closeTabletSearch() {
        if (!searchWrap || !searchWrap.classList.contains('is-expanded')) return;
        searchWrap.classList.remove('is-expanded');
        if (searchToggle) {
            searchToggle.setAttribute('aria-expanded', 'false');
            searchToggle.focus();
        }
    }

    if (searchToggle) {
        searchToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            if (searchWrap.classList.contains('is-expanded')) {
                closeTabletSearch();
            } else {
                openTabletSearch();
            }
        });
    }

    if (searchClose) {
        searchClose.addEventListener('click', function (e) {
            e.stopPropagation();
            closeTabletSearch();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.keyCode === 27) {
            if (searchWrap && searchWrap.classList.contains('is-expanded')) {
                closeTabletSearch();
            }
        }
    });

    document.addEventListener('click', function (e) {
        if (searchWrap && searchWrap.classList.contains('is-expanded')) {
            if (e.target === searchWrap || !searchWrap.contains(e.target)) {
                closeTabletSearch();
            }
        }
    });

    // ── 4. Back To Top Button ────────────────────────────────────────────────
    const backToTop = document.getElementById('back-to-top');
    if (backToTop) {
        let isScrolled = false;
        const scrollThreshold = 500;

        const checkBackToTop = function () {
            const y = window.pageYOffset || document.documentElement.scrollTop || 0;
            if (y > scrollThreshold) {
                if (!isScrolled) {
                    backToTop.classList.add('is-visible');
                    isScrolled = true;
                }
            } else {
                if (isScrolled) {
                    backToTop.classList.remove('is-visible');
                    isScrolled = false;
                }
            }
        };

        window.addEventListener('scroll', checkBackToTop, { passive: true });
        checkBackToTop();

        backToTop.addEventListener('click', function () {
            const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            window.scrollTo({
                top: 0,
                behavior: prefersReducedMotion ? 'auto' : 'smooth'
            });
        });
    }

    // ── 5. User Library controls ──
    const actionRibbon = document.querySelector('.user-action-ribbon, .hero-user-ribbon');
    if (actionRibbon) {
        const donghuaId = actionRibbon.getAttribute('data-donghua-id');
        const feedback = actionRibbon.querySelector('[data-library-feedback]');
        const listLabels = { favorite: 'المفضلة', watching: 'أتابع المشاهدة', following: 'أتابع للتنبيهات', plan_to_watch: 'سأشاهده لاحقًا', completed: 'أكملت مشاهدته' };
        const report = message => { if (feedback) feedback.textContent = message; };
        async function saveList(type, active, control) {
            if (!donghuaId || typeof donghuaConfig === 'undefined' || !donghuaConfig.isLoggedIn) return;
            control.disabled = true;
            control.setAttribute('aria-busy', 'true');
            actionRibbon.setAttribute('aria-busy', 'true');
            report('');
            try {
                const response = await fetch(donghuaConfig.restUrl + 'me/library/' + encodeURIComponent(donghuaId) + '/' + encodeURIComponent(type), {
                    method: active ? 'PUT' : 'DELETE',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': donghuaConfig.nonce, 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) throw new Error('library_update_failed');
                if (control.matches('.btn-user-list')) {
                    control.classList.toggle('active', active);
                    control.setAttribute('aria-pressed', String(active));
                    const label = control.querySelector('[data-list-label]');
                    if (label) label.textContent = active ? control.dataset.labelOn : control.dataset.labelOff;
                    control.setAttribute('aria-label', active ? `إزالة من ${listLabels[type] || 'مكتبتي'}` : `إضافة إلى ${listLabels[type] || 'مكتبتي'}`);
                }
                report(active ? `تمت الإضافة إلى «${listLabels[type] || 'مكتبتك'}».` : `تمت الإزالة من «${listLabels[type] || 'مكتبتك'}».`);
            } catch (_) {
                if (control.matches('input[type="checkbox"]')) control.checked = !active;
                report('تعذر تحديث مكتبتك، حاول مرة أخرى.');
            } finally {
                control.disabled = false;
                control.removeAttribute('aria-busy');
                actionRibbon.removeAttribute('aria-busy');
            }
        }
        actionRibbon.addEventListener('click', event => {
            const button = event.target.closest('button.btn-user-list');
            if (!button) return;
            saveList(button.dataset.list || '', !button.classList.contains('active'), button);
        });
        actionRibbon.addEventListener('change', event => {
            const checkbox = event.target.closest('input[data-list-toggle]');
            if (checkbox) saveList(checkbox.dataset.listToggle || '', checkbox.checked, checkbox);
        });
        actionRibbon.addEventListener('toggle', event => {
            if (event.target.matches('.dh-work-library-more')) event.target.querySelector('summary')?.setAttribute('aria-expanded', String(event.target.open));
        }, true);
        actionRibbon.addEventListener('keydown', event => {
            if (event.key !== 'Escape') return;
            const menu = event.target.closest('.dh-work-library-more[open]');
            if (!menu) return;
            event.preventDefault();
            menu.open = false;
            menu.querySelector('summary')?.focus();
        });
    }

    // Schedule follow buttons write to the canonical UserLibrary relationship.
    document.addEventListener('click', async function (event) {
        const button = event.target.closest('[data-schedule-follow]');
        if (!button || typeof donghuaConfig === 'undefined' || !donghuaConfig.isLoggedIn) return;
        event.preventDefault();
        const workId = button.dataset.workId;
        if (!workId || button.disabled) return;
        const wasFollowing = button.getAttribute('aria-pressed') === 'true';
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        const feedback = document.querySelector('[data-schedule-feedback]');
        try {
            const response = await fetch(donghuaConfig.restUrl + 'me/library/' + encodeURIComponent(workId) + '/following', {
                method: wasFollowing ? 'DELETE' : 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': donghuaConfig.nonce, 'Accept': 'application/json' },
                credentials: 'same-origin'
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) throw new Error('schedule_follow_failed');
            button.setAttribute('aria-pressed', String(!wasFollowing));
            button.classList.toggle('is-following', !wasFollowing);
            button.textContent = !wasFollowing ? '✓ أتابعها' : '+ متابعة';
            if (feedback) feedback.textContent = !wasFollowing ? 'أُضيف العمل إلى قائمة المتابعة.' : 'أُزيل العمل من قائمة المتابعة.';
            if (wasFollowing && new URLSearchParams(window.location.search).get('filter') === 'following') {
                window.location.reload();
                return;
            }
        } catch (_) {
            if (feedback) feedback.textContent = 'تعذر تحديث قائمة المتابعة، حاول مرة أخرى.';
        } finally {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        }
    });

    // ── 6. Comment Character Counter & Form Enhancements ─────────────────────
    const commentInput = document.getElementById('comment');
    const charCounter  = document.getElementById('dh-comment-char-counter');
    if (commentInput && charCounter) {
        const maxChars = 1000;
        function updateCounter() {
            const len = commentInput.value.length;
            charCounter.textContent = `${len} / ${maxChars}`;
            if (len >= maxChars) {
                charCounter.classList.add('is-limit');
            } else {
                charCounter.classList.remove('is-limit');
            }
        }
        commentInput.addEventListener('input', updateCounter);
        updateCounter();
    }

    // ── Comment usefulness votes ────────────────────────────────────────────
    document.addEventListener('invalid', function (event) {
        const guestDetails = event.target.closest('details.dh-guest-fields-details');
        if (guestDetails) guestDetails.open = true;
    }, true);

    const commentVoteButtons = document.querySelectorAll('[data-dh-comment-vote]');
    if (commentVoteButtons.length && window.donghuaConfig && donghuaConfig.ajaxUrl) {
        document.addEventListener('click', async function (event) {
            const button = event.target.closest('[data-dh-comment-vote]');
            if (!button || button.disabled) return;

            const commentRow = button.closest('.dh-comment-item');
            if (!commentRow) return;

            const formData = new FormData();
            formData.append('action', 'donghua_comment_vote');
            formData.append('nonce', donghuaConfig.commentVoteNonce || '');
            formData.append('comment_id', button.dataset.commentId || '');
            formData.append('vote', button.getAttribute('aria-pressed') === 'true' ? '0' : (button.dataset.dhCommentVote || ''));

            const rowButtons = commentRow.querySelectorAll('[data-dh-comment-vote]');
            rowButtons.forEach(item => { item.disabled = true; });

            try {
                const response = await fetch(donghuaConfig.ajaxUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: formData
                });
                const payload = await response.json();
                if (!response.ok || !payload.success) throw new Error('vote-failed');

                rowButtons.forEach(item => {
                    const selected = item.dataset.dhCommentVote === String(payload.data.selected);
                    item.setAttribute('aria-pressed', selected ? 'true' : 'false');
                    item.classList.toggle('is-selected', selected);
                });
                ['up', 'down'].forEach(key => {
                    const count = commentRow.querySelector(`[data-vote-count="${key}"]`);
                    if (count) count.textContent = new Intl.NumberFormat().format(payload.data[key] || 0);
                });
                const score = commentRow.querySelector('[data-vote-score]');
                const voteScore = (payload.data.up || 0) - (payload.data.down || 0);
                if (score) score.textContent = new Intl.NumberFormat().format(voteScore);
                commentRow.dataset.commentScore = String(voteScore);
                const feedback = document.getElementById('dh-comment-feedback'); if (feedback) feedback.textContent = '';
            } catch (_) {
                const feedback = document.getElementById('dh-comment-feedback');
                if (feedback) feedback.textContent = 'تعذر تسجيل التصويت. حاول مجددًا.';
            } finally {
                rowButtons.forEach(item => { item.disabled = false; });
            }
        });
    }

    const commentForm = document.getElementById('commentform');
    if (commentForm) commentForm.addEventListener('submit', function (event) {
        if (commentForm.dataset.submitting === '1') { event.preventDefault(); return; }
        commentForm.dataset.submitting = '1'; commentForm.setAttribute('aria-busy', 'true');
        const button = commentForm.querySelector('[type=submit]'); if (button) { button.disabled = true; button.value = 'جارٍ نشر التعليق…'; }
    });

    // Sort top-level comments without disturbing threaded replies.
    const commentsSort = document.getElementById('dh-comments-sort');
    if (commentsSort) {
        commentsSort.addEventListener('change', function () {
            const list = document.querySelector('#comments > .comment-list');
            if (!list) return;
            const rows = Array.from(list.children).filter(row => row.classList.contains('dh-comment-item'));
            rows.sort((a, b) => commentsSort.value === 'votes'
                ? (Number(b.dataset.commentScore || 0) - Number(a.dataset.commentScore || 0))
                    || (Number(b.dataset.commentTime || 0) - Number(a.dataset.commentTime || 0))
                : Number(b.dataset.commentTime || 0) - Number(a.dataset.commentTime || 0));
            rows.forEach(row => list.appendChild(row));
        });
    }

    document.addEventListener('click', async function (event) {
        const shareButton = event.target.closest('[data-comment-share]');
        if (!shareButton) return;
        const url = shareButton.dataset.commentShare;
        try {
            if (navigator.share) {
                await navigator.share({ title: document.title, url });
            } else {
                await navigator.clipboard.writeText(url);
                shareButton.setAttribute('aria-label', 'تم نسخ رابط التعليق');
            }
        } catch (_) {
            shareButton.setAttribute('aria-label', 'تعذرت مشاركة رابط التعليق');
        }
    });

    // ── 7. Donghua Work Statistics Polling ──────────────────────────────────
    const donghuaPage    = document.querySelector('.donghua-single-page[data-work-id]');
    const donghuaViewsEl = document.getElementById('donghua-total-views');
    const donghuaLiveEl  = document.getElementById('donghua-live-viewers');
    const donghuaBadgeEl = document.getElementById('donghua-live-badge');

    if (donghuaPage && (donghuaViewsEl || donghuaLiveEl)) {
        const workId   = donghuaPage.getAttribute('data-work-id');
        const restBase = (window.donghuaConfig && window.donghuaConfig.restUrl)
            ? window.donghuaConfig.restUrl.replace(/\/+$/, '')
            : '/wp-json/donghua/v1';

        let currentViews  = null;
        let currentLive   = null;
        let lastFetchTime = 0;

        function formatNumber(num) {
            try {
                return new Intl.NumberFormat().format(num);
            } catch (_) {
                return String(num);
            }
        }

        function updateDonghuaUI(data) {
            if (!data || typeof data !== 'object') return;

            // Update Live Viewers only if changed
            if (typeof data.live === 'number' && donghuaLiveEl && currentLive !== data.live) {
                currentLive = data.live;
                donghuaLiveEl.classList.add('is-updating');
                setTimeout(() => {
                    donghuaLiveEl.textContent = formatNumber(data.live);
                    donghuaLiveEl.classList.remove('is-updating');
                }, 150);

                if (donghuaBadgeEl) {
                    donghuaBadgeEl.setAttribute('aria-label', `${formatNumber(data.live)} مستخدمًا يشاهدون حلقات هذا العمل الآن`);
                }
            }

            // Update Total Views only if changed
            if (typeof data.views === 'number' && donghuaViewsEl && currentViews !== data.views) {
                currentViews = data.views;
                donghuaViewsEl.classList.add('is-updating');
                setTimeout(() => {
                    donghuaViewsEl.textContent = formatNumber(data.views);
                    donghuaViewsEl.classList.remove('is-updating');
                }, 150);
            }
        }

        async function fetchDonghuaStats() {
            lastFetchTime = Date.now();
            try {
                const res = await fetch(`${restBase}/donghua/${workId}/stats`, {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                if (res.ok) {
                    const data = await res.json();
                    updateDonghuaUI(data);
                }
            } catch (_) {
                // Silently retain current UI numbers without console noise
            }
        }

        // Initial sync fetch
        fetchDonghuaStats();

        // Periodic poll every 30 seconds (only when visible)
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                fetchDonghuaStats();
            }
        }, 30000);

        // Visibility change trigger (if last fetch was > 25s ago)
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                if (Date.now() - lastFetchTime >= 25000) {
                    fetchDonghuaStats();
                }
            }
        });
    }
});
