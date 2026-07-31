/**
 * ParsYar — Theme Controller (light / dark / auto)
 * --------------------------------------------------------------------
 * Manages the [data-theme] attribute on <html>, persists user
 * preference via user_meta (saved via personal_options), and reacts
 * to prefers-color-scheme media query.
 *
 * @package ParsYar
 */
(function (window, document) {
    'use strict';
    var P = window.ParsYar;
    if (!P) { return; }

    var STORAGE_KEY = 'parsyar:theme';
    var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function current() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }

    function apply(mode) {
        var resolved = mode;
        if (mode === 'auto') {
            resolved = (mql && mql.matches) ? 'dark' : 'light';
        }
        document.documentElement.setAttribute('data-theme', resolved);
        try { localStorage.setItem(STORAGE_KEY, mode); } catch (e) {}
        P.bus.emit('parsyar:theme', { mode: mode, resolved: resolved });
    }

    function cycle() {
        var modes = ['light', 'dark', 'auto'];
        var idx = modes.indexOf(current());
        apply(modes[(idx + 1) % modes.length]);
    }

    function bind() {
        document.addEventListener('click', function (e) {
            var t = e.target.closest('[data-parsyar-theme-toggle]');
            if (t) {
                e.preventDefault();
                cycle();
            }
        });
        if (mql && mql.addEventListener) {
            mql.addEventListener('change', function () {
                var stored = localStorage.getItem(STORAGE_KEY) || 'auto';
                if (stored === 'auto') { apply('auto'); }
            });
        }
    }

    // Detect and apply stored preference before paint
    function preload() {
        var stored;
        try { stored = localStorage.getItem(STORAGE_KEY); } catch (e) {}
        if (!stored) {
            stored = (P.cfg && P.cfg.themeMode) || 'light';
        }
        apply(stored);
    }

    P.modules['theme-controller'] = {
        apply: apply,
        cycle: cycle,
        current: current,
        mount: function () { /* no-op */ }
    };

    preload();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else { bind(); }
})(window, document);
