(function () {
    'use strict';

    var STORAGE_KEY = 'okdonghua_theme';
    var LEGACY_KEYS = ['okdonghua-theme', 'donghua_theme_mode'];
    var themes = ['night-blue', 'jade', 'moon', 'charcoal'];

    function storage() {
        try { return window.localStorage; } catch (error) { return null; }
    }
    function get(key) {
        try { var store = storage(); return store ? store.getItem(key) : null; } catch (error) { return null; }
    }
    function set(key, value) {
        try { var store = storage(); if (store) store.setItem(key, value); } catch (error) {}
    }
    function normalize(value) {
        if (value === 'dark' || value === 'light') return 'night-blue';
        return themes.indexOf(value) !== -1 ? value : 'night-blue';
    }
    function initialTheme() {
        var saved = get(STORAGE_KEY);
        if (saved && themes.indexOf(saved) !== -1) {
            LEGACY_KEYS.forEach(function (key) {
                try { var store = storage(); if (store) store.removeItem(key); } catch (error) {}
            });
            return saved;
        }
        var legacy = null;
        LEGACY_KEYS.some(function (key) {
            var value = get(key);
            if (value) { legacy = value; return true; }
            return false;
        });
        var oldMode = saved === 'dark' || saved === 'light' ? saved : null;
        var theme = normalize(legacy || oldMode || window.okDonghuaDefaultTheme);
        set(STORAGE_KEY, theme);
        LEGACY_KEYS.forEach(function (key) {
            try { var store = storage(); if (store) store.removeItem(key); } catch (error) {}
        });
        return theme;
    }

    var currentTheme = initialTheme();
    function syncSelector(theme) {
        var root = document.documentElement;
        var trigger = document.getElementById('appearance-trigger');
        var options = document.querySelectorAll('[data-theme-choice]');
        options.forEach(function (option) {
            var selected = option.getAttribute('data-theme-choice') === theme;
            option.setAttribute('aria-checked', selected ? 'true' : 'false');
        });
        if (trigger) trigger.setAttribute('data-active-theme', theme);
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-theme-preference', theme);
        root.style.colorScheme = 'dark';
    }
    function applyTheme(value, persist) {
        currentTheme = normalize(value);
        if (persist) set(STORAGE_KEY, currentTheme);
        syncSelector(currentTheme);
    }

    // This inline file runs in the document head before the theme stylesheets.
    syncSelector(currentTheme);
    window.okDonghuaTheme = {
        getPreference: function () { return currentTheme; },
        setPreference: function (theme) { applyTheme(theme, true); }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var switcher = document.querySelector('[data-appearance-switcher]');
        var trigger = document.getElementById('appearance-trigger');
        var menu = document.getElementById('appearance-menu');
        if (!switcher || !trigger || !menu) return;
        var options = Array.prototype.slice.call(menu.querySelectorAll('[data-theme-choice]'));

        function closeMenu(returnFocus) {
            menu.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            if (returnFocus) trigger.focus();
        }
        function openMenu(focusSelected) {
            menu.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            if (focusSelected) {
                var selected = options.find(function (option) { return option.getAttribute('aria-checked') === 'true'; });
                (selected || options[0]).focus();
            }
        }

        trigger.addEventListener('click', function () {
            if (menu.hidden) openMenu(false); else closeMenu(false);
        });
        trigger.addEventListener('keydown', function (event) {
            if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
                event.preventDefault(); openMenu(true);
            }
        });
        options.forEach(function (option, index) {
            option.addEventListener('click', function () {
                applyTheme(option.getAttribute('data-theme-choice'), true);
                closeMenu(true);
            });
            option.addEventListener('keydown', function (event) {
                var nextIndex = index;
                if (event.key === 'ArrowDown') nextIndex = (index + 1) % options.length;
                else if (event.key === 'ArrowUp') nextIndex = (index - 1 + options.length) % options.length;
                else if (event.key === 'Home') nextIndex = 0;
                else if (event.key === 'End') nextIndex = options.length - 1;
                else if (event.key === 'Escape') { event.preventDefault(); closeMenu(true); return; }
                else return;
                event.preventDefault(); options[nextIndex].focus();
            });
        });
        document.addEventListener('pointerdown', function (event) {
            if (!switcher.contains(event.target) && !menu.hidden) closeMenu(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !menu.hidden) { event.preventDefault(); closeMenu(true); }
        });
        syncSelector(currentTheme);
    });

    window.addEventListener('storage', function (event) {
        if (event.key === STORAGE_KEY) applyTheme(event.newValue || 'night-blue', false);
    });
})();
