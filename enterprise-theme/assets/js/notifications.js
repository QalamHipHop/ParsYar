/**
 * ParsYar — Notifications / Toasts
 * --------------------------------------------------------------------
 * Stackable bottom-right (LTR) / bottom-left (RTL) toasts.
 * 4s auto-dismiss, click to dismiss, optional action.
 *
 * API:
 *   P.notify('message', { kind: 'positive'|'warning'|'danger'|'info', duration: 4000 })
 *   P.bus.emit('parsyar:notify', { ... })
 *
 * @package ParsYar
 */
(function (window, document) {
    'use strict';
    var P = window.ParsYar;
    if (!P) { return; }

    var stack;

    function ensureDom() {
        if (stack) { return; }
        stack = document.createElement('div');
        stack.className = 'p-toast-stack' + (P.isRTL ? ' p-toast-stack--rtl' : '');
        stack.setAttribute('aria-live', 'polite');
        stack.setAttribute('aria-atomic', 'true');
        document.body.appendChild(stack);
    }

    function iconFor(kind) {
        return { positive: '✓', warning: '!', danger: '×', info: 'i' }[kind] || 'i';
    }

    function show(message, opts) {
        opts = opts || {};
        ensureDom();
        var kind = opts.kind || 'info';
        var duration = opts.duration !== undefined ? opts.duration : 4000;
        var t = document.createElement('div');
        t.className = 'p-toast p-toast--' + kind + ' p-toast--enter';
        t.setAttribute('role', kind === 'danger' || kind === 'warning' ? 'alert' : 'status');
        t.innerHTML =
            '<span class="p-toast__icon" aria-hidden="true">' + iconFor(kind) + '</span>' +
            '<div class="p-toast__body">' + P.utils.escapeHtml(message) + '</div>' +
            (opts.action ? '<button class="p-toast__action">' + P.utils.escapeHtml(opts.action.label) + '</button>' : '') +
            '<button class="p-toast__close" aria-label="بستن">×</button>';
        if (opts.action && typeof opts.action.onClick === 'function') {
            t.querySelector('.p-toast__action').addEventListener('click', function () { opts.action.onClick(); dismiss(); });
        }
        t.querySelector('.p-toast__close').addEventListener('click', dismiss);
        t.addEventListener('click', function (e) { if (!e.target.closest('button')) { dismiss(); } });
        stack.appendChild(t);
        // animate in
        requestAnimationFrame(function () { t.classList.remove('p-toast--enter'); });
        var timer = setTimeout(dismiss, duration);
        function dismiss() {
            clearTimeout(timer);
            t.classList.add('p-toast--leave');
            setTimeout(function () { if (t.parentNode) { t.parentNode.removeChild(t); } }, 240);
        }
        return dismiss;
    }

    P.notify = show;
    P.modules['notifications'] = { show: show, ensure: ensureDom };

    P.bus.on('parsyar:notify', function (p) { show(p.message, p); });
    P.bus.on('parsyar:error',   function (e) { show((e && e.message) || 'خطا', { kind: 'danger' }); });

    // Reduce motion: shorter duration
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        P.modules.notifications._reducedMotion = true;
    }
})(window, document);
