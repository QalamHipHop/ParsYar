/**
 * ParsYar — Core Frontend Bootstrap
 * --------------------------------------------------------------------
 * Minimal, dependency-free frontend runtime that wires the design
 * system, command palette, theme controller, data tables, forms,
 * notifications, and the dashboard SPA mount point.
 *
 * Exposes: window.ParsYar
 * Depends on: jQuery (WordPress), window.ParsYarConfig (localized).
 *
 * @package ParsYar
 */
(function (window, document, $) {
    'use strict';

    if (window.ParsYar) {
        return;
    }

    var P = window.ParsYar = {
        version: '1.0.0',
        cfg: window.ParsYarConfig || {},
        locale: (window.ParsYarConfig && window.ParsYarConfig.locale) || 'fa_IR',
        isRTL: (window.ParsYarConfig && window.ParsYarConfig.isRTL) === true,
        ready: false,
        modules: {},
        bus: createBus(),
        utils: {}
    };

    /* ------------------------------------------------------------------ */
    /* Tiny event bus                                                     */
    /* ------------------------------------------------------------------ */
    function createBus() {
        var listeners = {};
        return {
            on: function (event, fn) {
                (listeners[event] || (listeners[event] = [])).push(fn);
                return function () {
                    listeners[event] = (listeners[event] || []).filter(function (h) { return h !== fn; });
                };
            },
            off: function (event) { delete listeners[event]; },
            emit: function (event, payload) {
                (listeners[event] || []).slice().forEach(function (fn) {
                    try { fn(payload); } catch (e) { console.error('[ParsYar bus]', event, e); }
                });
            }
        };
    }

    /* ------------------------------------------------------------------ */
    /* DOM helpers                                                        */
    /* ------------------------------------------------------------------ */
    P.utils.$  = function (sel, ctx) { return (ctx || document).querySelector(sel); };
    P.utils.$$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
    P.utils.escapeHtml = function (s) {
        if (s === null || s === undefined) { return ''; }
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    };
    P.utils.persianDigit = function (n) {
        return String(n).replace(/[0-9]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[+d]; });
    };
    P.utils.englishDigit = function (s) {
        return String(s).replace(/[۰-۹]/g, function (d) { return d.charCodeAt(0) - 1776; })
            .replace(/[٠-٩]/g, function (d) { return d.charCodeAt(0) - 1632; });
    };
    P.utils.formatMoney = function (amount, currency) {
        currency = currency || 'IRT';
        var sym = { IRT: 'تومان', IRR: 'ریال', USD: '$', EUR: '€', AED: 'د.إ', TRY: '₺' }[currency] || currency;
        var n = P.utils.persianDigit(P.utils.englishDigit(String(amount)).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
        return P.isRTL ? (n + ' ' + sym) : (sym + ' ' + n);
    };
    P.utils.debounce = function (fn, wait) {
        var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, wait); };
    };
    P.utils.fetchJSON = function (url, opts) {
        opts = opts || {};
        var headers = Object.assign({
            'X-WP-Nonce': P.cfg.restNonce || '',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }, opts.headers || {});
        return fetch(url, { credentials: 'same-origin', headers: headers })
            .then(function (r) {
                if (!r.ok) { return r.json().catch(function () { return {}; }).then(function (b) { throw new Error((b && b.error && b.error.message) || ('HTTP ' + r.status)); }); }
                return r.json();
            });
    };

    /* ------------------------------------------------------------------ */
    /* Boot                                                               */
    /* ------------------------------------------------------------------ */
    document.addEventListener('DOMContentLoaded', function () {
        P.ready = true;
        P.bus.emit('parsyar:ready', P);
        // Auto-mount any container with [data-parsyar-component]
        P.utils.$$('[data-parsyar-component]').forEach(function (el) {
            var name = el.getAttribute('data-parsyar-component');
            if (P.modules[name] && typeof P.modules[name].mount === 'function') {
                try { P.modules[name].mount(el, el.dataset); } catch (e) { console.error('[ParsYar mount]', name, e); }
            }
        });
        P.bus.emit('parsyar:mounted');
    });

    // Expose jQuery bridge for legacy code
    if ($ && $.fn) {
        $.fn.parsyar = function (name, opts) {
            return this.each(function () {
                if (P.modules[name] && typeof P.modules[name].mount === 'function') {
                    P.modules[name].mount(this, Object.assign({}, opts || {}));
                }
            });
        };
    }
})(window, document, window.jQuery);
