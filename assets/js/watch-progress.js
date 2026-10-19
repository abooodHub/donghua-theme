document.addEventListener('DOMContentLoaded', () => {
    'use strict';
    const config = window.donghuaConfig && window.donghuaConfig.watchProgress;
    const root = document.querySelector('.watch-page-container[data-current-episode]');
    const screen = document.getElementById('player-screen');
    const prompt = document.getElementById('watch-resume-prompt');
    const nextAction = document.getElementById('watch-next-after-complete');
    if (!config || !root || !screen || !prompt || !config.episodeId) return;

    const episodeId = Number(config.episodeId);
    const drawer = document.getElementById('episodes-scroll-container');
    const resumeButton = prompt.querySelector('[data-watch-resume]');
    const restartButton = prompt.querySelector('[data-watch-restart]');
    const copy = prompt.querySelector('[data-watch-resume-copy]');
    let video = null;
    let loaded = false;
    let sending = false;
    let timer = 0;
    let sourceSwitchPosition = null;
    let playbackEnded = false;
    const preferences = window.dhPlayerPreferences;
    const autoNext = document.getElementById('watch-auto-next');
    const countdown = document.getElementById('watch-next-countdown');
    const countdownSeconds = countdown && countdown.querySelector('[data-countdown-seconds]');
    let countdownTimer = 0;
    let countdownRemaining = 10;
    let countdownTarget = '';

    const formatTime = value => {
        const seconds = Math.max(0, Math.floor(Number(value) || 0));
        return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
    };
    const request = (url, method, data, keepalive = false) => fetch(url, {
        method,
        credentials: 'same-origin',
        keepalive,
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
        body: data ? JSON.stringify(data) : undefined
    });

    function applyEpisodeState(id, state) {
        if (!drawer || !state) return;
        const link = drawer.querySelector(`[data-episode-id="${Number(id)}"]`);
        if (!link) return;
        const badge = link.querySelector('.episode-watch-state');
        const arrow = link.querySelector('.item-play-arrow');
        const complete = state.completed === true;
        const percent = Math.max(0, Math.min(100, Number(state.progress_percent) || 0));
        const started = Number(state.position_seconds) > 0 || percent > 0;
        if (!complete && !started) return;
        link.classList.toggle('is-watched', complete);
        link.classList.toggle('is-in-progress', !complete);
        link.dataset.watchState = complete ? 'completed' : 'in-progress';
        if (badge) {
            badge.hidden = false;
            badge.textContent = complete ? '✓ تمت المشاهدة' : (percent > 0 ? `متابعة ${percent}%` : 'بدأت');
        }
        if (arrow) arrow.hidden = true;
        const episodeLabel = link.querySelector('.item-text')?.textContent.trim() || 'الحلقة';
        link.setAttribute('aria-label', `${episodeLabel}، ${complete ? 'تمت المشاهدة' : (percent > 0 ? `قيد المشاهدة، ${percent}%` : 'بدأت المشاهدة')}`);
    }

    async function loadEpisodeStates() {
        if (!config.isLoggedIn || !config.historyUrl || !config.workId || !drawer) return;
        try {
            const response = await request(`${config.historyUrl}?donghua_id=${encodeURIComponent(config.workId)}`, 'GET');
            if (!response.ok) return;
            const data = await response.json();
            Object.entries(data.states || {}).forEach(([id, state]) => applyEpisodeState(id, state));
        } catch (_) { /* The episode list remains usable without personal state. */ }
    }

    async function restoreProgress(target) {
        if (!config.isLoggedIn || !config.url) return;
        if (loaded) return;
        loaded = true;
        try {
            const response = await request(`${config.url}/${episodeId}`, 'GET');
            if (!response.ok) return;
            const data = await response.json();
            const saved = data && data.progress;
            if (!saved || saved.completed || saved.position_seconds < 10 || !target.duration) return;
            copy.textContent = `وصلت إلى ${formatTime(saved.position_seconds)} من الحلقة.`;
            prompt.hidden = false;
            target.pause();
        } catch (_) { /* Playback remains usable if personal history is unavailable. */ }
    }

    async function saveProgress(target, keepalive = false) {
        if (!config.isLoggedIn || !config.url) return;
        if (sending || !Number.isFinite(target.duration) || target.duration <= 0 || !Number.isFinite(target.currentTime)) return;
        // Avoid creating a misleading 0% history entry when a player is merely opened.
        if (target.currentTime < 10 && !playbackEnded) return;
        sending = true;
        try {
            const response = await request(config.url, 'POST', {
                episode_id: episodeId,
                position_seconds: Math.floor(target.currentTime),
                duration_seconds: Math.floor(target.duration)
            }, keepalive);
            if (response.ok) {
                const data = await response.json();
                if (data.progress) applyEpisodeState(episodeId, data.progress);
            }
        } catch (_) { /* A later interval or pause will retry. */ }
        finally { sending = false; }
    }

    // Adapter contract for a future player provider; viewer tokens are never persisted here.
    window.dhWatchProgress = Object.freeze({
        report: (reportedEpisodeId, positionSeconds, durationSeconds) => {
            if (!config.isLoggedIn || !config.url) return Promise.resolve(false);
            if (Number(reportedEpisodeId) !== episodeId || !Number.isFinite(Number(positionSeconds)) || !Number.isFinite(Number(durationSeconds))) return Promise.resolve(false);
            return request(config.url, 'POST', { episode_id: episodeId, position_seconds: Number(positionSeconds), duration_seconds: Number(durationSeconds) })
                .then(response => response.ok).catch(() => false);
        }
    });

    function stopCountdown() {
        window.clearInterval(countdownTimer);
        countdownTimer = 0;
        if (countdown) countdown.hidden = true;
    }
    function goNext() {
        stopCountdown();
        if (!countdownTarget) return;
        try {
            const target = new URL(countdownTarget, window.location.href);
            if (target.origin === window.location.origin && target.protocol === window.location.protocol) window.location.assign(target.href);
        } catch (_) { countdownTarget = ''; }
    }
    function startAutoNext(target) {
        stopCountdown();
        if (!target || !autoNext || !autoNext.checked || !countdown) return;
        countdownTarget = target.href;
        countdownRemaining = 10;
        if (countdownSeconds) countdownSeconds.textContent = String(countdownRemaining);
        countdown.hidden = false;
        countdownTimer = window.setInterval(() => {
            countdownRemaining -= 1;
            if (countdownSeconds) countdownSeconds.textContent = String(Math.max(0,countdownRemaining));
            if (countdownRemaining <= 0) goNext();
        }, 1000);
    }
    if (autoNext && preferences) {
        autoNext.checked = preferences.get().autoNext === true;
        autoNext.addEventListener('change', () => preferences.set({autoNext:autoNext.checked}));
    }
    const resetPreferences = document.getElementById('watch-preferences-reset');
    if (resetPreferences && preferences) resetPreferences.addEventListener('click', () => {
        preferences.reset();
        if (autoNext) autoNext.checked = false;
        stopCountdown();
        window.location.reload();
    });
    if (countdown) {
        countdown.querySelector('[data-countdown-now]')?.addEventListener('click', goNext);
        countdown.querySelector('[data-countdown-cancel]')?.addEventListener('click', stopCountdown);
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !countdown.hidden) stopCountdown(); });
    }

    function bindPlayer() {
        if (video && !screen.contains(video)) {
            stopCountdown();
            if (Number.isFinite(video.currentTime) && video.currentTime > 0) sourceSwitchPosition = video.currentTime;
            window.clearInterval(timer);
            if (!playbackEnded) saveProgress(video, true);
        }
        const next = screen.querySelector('video#main-video-player');
        if (!next || next === video) return;
        video = next;
        video.addEventListener('loadedmetadata', () => {
            if (sourceSwitchPosition !== null && Number.isFinite(video.duration) && sourceSwitchPosition < video.duration) {
                video.currentTime = sourceSwitchPosition;
                sourceSwitchPosition = null;
            } else restoreProgress(video);
        }, { once: true });
        video.addEventListener('play', () => {
            playbackEnded = false;
            window.clearInterval(timer);
            timer = window.setInterval(() => saveProgress(video), 15000);
        });
        video.addEventListener('pause', () => { if (!playbackEnded) saveProgress(video); });
        video.addEventListener('ended', () => { playbackEnded = true; window.clearInterval(timer); saveProgress(video, true); });
        video.addEventListener('ended', () => {
            const playerError = document.getElementById('player-error-overlay');
            if (video.ended !== true || (playerError && !playerError.hidden)) return;
            const next = document.querySelector('.watch-nav-quad-bar a.next-btn[href]');
            if (next && nextAction) {
                nextAction.href = next.href;
                nextAction.hidden = false;
                startAutoNext(next);
            }
        });
        if (video.readyState >= 1) restoreProgress(video);
    }

    resumeButton.addEventListener('click', () => {
        const match = copy.textContent.match(/(\d+):(\d{2})/);
        if (video && match) video.currentTime = Number(match[1]) * 60 + Number(match[2]);
        prompt.hidden = true;
        if (video) video.play().catch(() => {});
    });
    restartButton.addEventListener('click', () => {
        if (video) video.currentTime = 0;
        prompt.hidden = true;
        if (video) video.play().catch(() => {});
    });
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden' && video && !playbackEnded) saveProgress(video, true);
    });
    window.addEventListener('pagehide', () => {
        window.clearInterval(timer);
        stopCountdown();
        if (video && !playbackEnded) saveProgress(video, true);
    });
    document.querySelectorAll('.watch-nav-quad-bar a[href]').forEach(link => link.addEventListener('click', stopCountdown));
    bindPlayer();
    loadEpisodeStates();
    new MutationObserver(bindPlayer).observe(screen, { childList: true, subtree: true });
});
