(function () {
    'use strict';

    var config = window.donghuaConfig && window.donghuaConfig.rating;
    if (!config || !config.restUrl) return;

    function setSelected(widget, score, draft) {
        widget.querySelectorAll('[data-rating-value]').forEach(function (button) {
            var value = Number(button.getAttribute('data-rating-value'));
            var selected = value === score;
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.classList.toggle('is-selected', score > 0 && value <= score);
        });
        setStarFill(widget.querySelectorAll('[data-user-star]'), score);
        var userRating = widget.querySelector('[data-user-rating]');
        if (userRating) {
            if (score >= 1 && score <= 10) {
                userRating.textContent = (draft ? 'اختيارك: ' : 'تقييمك: ') + score + '/10';
                userRating.hidden = false;
            } else {
                userRating.textContent = '';
                userRating.hidden = true;
            }
        }
    }

    function setStarFill(stars, score) {
        stars.forEach(function (star) {
            var index = Number(star.getAttribute('data-average-star') || star.getAttribute('data-user-star'));
            var fill = Math.max(0, Math.min(100, ((score - ((index - 1) * 2)) / 2) * 100));
            star.style.setProperty('--rating-fill', fill + '%');
        });
    }

    function updateSummary(widget, payload) {
        var average = widget.querySelector('[data-rating-average]');
        var count = widget.querySelector('[data-rating-count]');
        var empty = widget.querySelector('[data-rating-empty]');
        var outOf = widget.querySelector('.okd-rating-out-of');
        var summary = widget.querySelector('.okd-rating-summary');
        var votes = Number(payload.rating_count);
        var score = Number(payload.rating_average);
        if (!Number.isFinite(votes) || votes < 0) return;
        if (votes > 0 && Number.isFinite(score)) {
            if (average) { average.textContent = score.toFixed(1); average.hidden = false; }
            if (outOf) outOf.hidden = false;
            setStarFill(widget.querySelectorAll('[data-average-star]'), score);
            if (empty) empty.hidden = true;
            if (summary) summary.setAttribute('aria-label', 'متوسط التقييم ' + score.toFixed(1) + ' من 10');
        } else {
            if (average) average.hidden = true;
            if (outOf) outOf.hidden = true;
            setStarFill(widget.querySelectorAll('[data-average-star]'), 0);
            if (empty) empty.hidden = false;
            if (summary) summary.setAttribute('aria-label', 'لا توجد تقييمات بعد');
        }
        if (count) {
            count.textContent = new Intl.NumberFormat('en-US').format(votes) + ' تقييم';
            count.hidden = false;
        }
    }

    function setStatus(widget, message, error) {
        var status = widget.querySelector('[data-rating-status]');
        if (!status) return;
        status.textContent = message;
        status.classList.toggle('is-error', Boolean(error));
    }

    function init(widget) {
        if (widget.dataset.ratingReady === '1') return;
        widget.dataset.ratingReady = '1';
        var picker = widget.querySelector('.okd-rating-picker');
        var trigger = widget.querySelector('.okd-rating-trigger');
        function closePicker(restoreFocus) {
            if (!picker || !picker.open) return;
            picker.open = false;
            if (restoreFocus && trigger) trigger.focus();
        }
        var closeButton = widget.querySelector('[data-rating-close]');
        if (closeButton) closeButton.addEventListener('click', function () { closePicker(true); });
        document.addEventListener('click', function (event) {
            if (!widget.contains(event.target)) closePicker(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && picker && picker.open) {
                event.preventDefault();
                closePicker(true);
            }
        });
        if (!config.isLoggedIn) return;

        var donghuaId = widget.getAttribute('data-donghua-id');
        if (!donghuaId || !/^[1-9]\d*$/.test(donghuaId)) return;
        var headers = { 'X-WP-Nonce': config.nonce || '' };
        var savedScore = 0;
        var draftScore = 0;
        var touched = false;
        var saving = false;
        var savedAfterSubmit = false;
        function setSavedScore(score) {
            savedScore = score;
            draftScore = score;
            var label = widget.querySelector('[data-rating-trigger-text]');
            if (label) label.textContent = 'تقييمك';
            setStarFill(widget.querySelectorAll('[data-user-star]'), score);
        }

        var scale = widget.querySelector('.okd-rating-scale');
        if (scale) {
            scale.addEventListener('pointerover', function (event) {
                var button = event.target.closest('[data-rating-value]');
                if (button && !button.disabled) setSelected(widget, Number(button.getAttribute('data-rating-value')), true);
            });
            scale.addEventListener('pointerleave', function () { setSelected(widget, draftScore || savedScore, false); });
            scale.addEventListener('focusin', function (event) {
                var button = event.target.closest('[data-rating-value]');
                if (button) setSelected(widget, Number(button.getAttribute('data-rating-value')), true);
            });
            scale.addEventListener('focusout', function (event) {
                if (!scale.contains(event.relatedTarget)) setSelected(widget, draftScore || savedScore, false);
            });
        }

        fetch(config.restUrl + 'me/rating/' + encodeURIComponent(donghuaId), {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: headers
        }).then(function (response) {
            if (!response.ok) throw new Error('session');
            return response.json();
        }).then(function (payload) {
            if (savedAfterSubmit) return;
            if (payload && payload.success && Number.isInteger(Number(payload.user_rating)) && Number(payload.user_rating) >= 1 && Number(payload.user_rating) <= 10) {
                setSavedScore(Number(payload.user_rating));
                if (!touched) {
                    draftScore = savedScore;
                    setSelected(widget, savedScore);
                }
            }
        }).catch(function () {
            if (savedAfterSubmit || saving) return;
            setStatus(widget, 'تعذر تحميل تقييمك الشخصي. حدّث الصفحة أو سجّل الدخول مجددًا.', true);
        });

        widget.addEventListener('click', function (event) {
            var button = event.target.closest('[data-rating-value]');
            if (!button || !widget.contains(button) || button.disabled) return;
            var rating = Number(button.getAttribute('data-rating-value'));
            if (!Number.isInteger(rating) || rating < 1 || rating > 10) return;
            if (saving) return;
            touched = true;
            draftScore = rating;
            setSelected(widget, rating, true);
            widget.classList.remove('is-rating-started');
            void widget.offsetWidth;
            widget.classList.add('is-rating-started');
            window.setTimeout(function () { widget.classList.remove('is-rating-started'); }, 900);
            setStatus(widget, '', false);
            saving = true;
            widget.setAttribute('aria-busy', 'true');

            var buttons = widget.querySelectorAll('[data-rating-value]');
            buttons.forEach(function (choice) { choice.disabled = true; });
            setStatus(widget, 'جارٍ حفظ تقييمك…', false);

            fetch(config.restUrl + 'rate', {
                method: 'POST',
                credentials: 'same-origin',
                cache: 'no-store',
                headers: Object.assign({ 'Content-Type': 'application/json' }, headers),
                body: JSON.stringify({ donghua_id: Number(donghuaId), rating: rating })
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok) {
                        var failure = new Error(payload.message || 'تعذر حفظ التقييم، حاول مرة أخرى.');
                        failure.status = response.status;
                        throw failure;
                    }
                    return payload;
                });
            }).then(function (payload) {
                if (!payload || !payload.success || !Number.isInteger(Number(payload.user_rating)) || Number(payload.user_rating) < 1 || Number(payload.user_rating) > 10) throw new Error('تعذر حفظ التقييم، حاول مرة أخرى.');
                savedAfterSubmit = true;
                updateSummary(widget, payload);
                setSavedScore(Number(payload.user_rating));
                setSelected(widget, Number(payload.user_rating));
                setStatus(widget, 'تم حفظ تقييمك', false);
            }).catch(function (error) {
                setSelected(widget, savedScore);
                setStatus(widget, error.status === 401 || error.status === 403
                    ? 'انتهت صلاحية الجلسة. سجّل الدخول ثم حاول مجددًا.'
                    : (error.message || 'تعذر حفظ التقييم، حاول مرة أخرى.'), true);
            }).finally(function () {
                buttons.forEach(function (choice) { choice.disabled = false; });
                saving = false;
                widget.removeAttribute('aria-busy');
            });
        });
    }

    function start() {
        document.querySelectorAll('.okd-rating-widget').forEach(init);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
}());
