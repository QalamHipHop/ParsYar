/**
 * ParsYar — Command Palette (⌘K / Ctrl+K)
 * --------------------------------------------------------------------
 * Slide-down modal with fuzzy search across:
 *   - Navigation pillars (19)
 *   - Recent records (last 10)
 *   - Quick-create actions
 *   - Settings
 *
 * @package ParsYar
 */
(function (window, document) {
    'use strict';
    var P = window.ParsYar;
    if (!P) { return; }

    var NAV = [
        { icon: '◧', label: 'داشبورد',         href: '/?p=dashboard',  group: 'main' },
        { icon: '◉', label: 'مخاطبین',         href: '/?p=contacts',   group: 'main' },
        { icon: '◇', label: 'معاملات',         href: '/?p=deals',      group: 'main' },
        { icon: '△', label: 'سرنخ‌ها',         href: '/?p=leads',      group: 'main' },
        { icon: '◌', label: 'فعالیت‌ها',       href: '/?p=activities', group: 'main' },
        { icon: '▦', label: 'تقویم',           href: '/?p=calendar',   group: 'main' },
        { icon: '▤', label: 'صندوق',           href: '/?p=inbox',      group: 'main' },
        { icon: '◐', label: 'فروش',            href: '/?p=sales',      group: 'business' },
        { icon: '◫', label: 'انبار',           href: '/?p=inventory',  group: 'business' },
        { icon: '✦', label: 'بازاریابی',       href: '/?p=marketing',  group: 'business' },
        { icon: '⌘', label: 'اتوماسیون',       href: '/?p=automation', group: 'business' },
        { icon: '◢', label: 'گزارش‌ها',        href: '/?p=reports',    group: 'ops' },
        { icon: '◭', label: 'منابع انسانی',    href: '/?p=hr',         group: 'ops' },
        { icon: '◮', label: 'حسابداری',        href: '/?p=accounting', group: 'ops' },
        { icon: '◯', label: 'پشتیبانی',        href: '/?p=support',    group: 'ops' },
        { icon: '◰', label: 'پروژه‌ها',        href: '/?p=projects',   group: 'ops' },
        { icon: '◱', label: 'مستندات',         href: '/?p=documents',  group: 'ops' },
        { icon: '◳', label: 'یکپارچگی',        href: '/?p=integrations', group: 'system' },
        { icon: '◴', label: 'تنظیمات',         href: '/?p=settings',   group: 'system' }
    ];

    var QUICK_CREATE = [
        { label: 'مخاطب جدید',       href: '/?p=contacts&action=new',   icon: '＋' },
        { label: 'سرنخ جدید',        href: '/?p=leads&action=new',      icon: '＋' },
        { label: 'معامله جدید',      href: '/?p=deals&action=new',      icon: '＋' },
        { label: 'فاکتور فروش',      href: '/?p=sales&action=new',      icon: '＋' },
        { label: 'تیکت پشتیبانی',    href: '/?p=support&action=new',    icon: '＋' },
        { label: 'یادداشت',          href: '/?p=activities&action=new', icon: '＋' }
    ];

    var el, input, results;
    var open = false;
    var active = 0;
    var current = [];

    function ensureDom() {
        if (el) { return; }
        el = document.createElement('div');
        el.className = 'p-command';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'جستجوی فرمان');
        el.innerHTML =
            '<div class="p-command__backdrop" data-cmd-close></div>' +
            '<div class="p-command__panel" role="document">' +
                '<div class="p-command__input-row">' +
                    '<span class="p-command__hint" aria-hidden="true">⌘K</span>' +
                    '<input class="p-command__input" type="text" placeholder="جستجو در همه‌جا…" autocomplete="off" spellcheck="false" />' +
                    '<span class="p-command__esc" aria-hidden="true">Esc</span>' +
                '</div>' +
                '<div class="p-command__results" role="listbox"></div>' +
            '</div>';
        document.body.appendChild(el);
        input = el.querySelector('.p-command__input');
        results = el.querySelector('.p-command__results');
        input.addEventListener('input', onInput);
        el.addEventListener('click', function (e) {
            if (e.target.matches('[data-cmd-close], [data-cmd-jump]')) { close(); return; }
            var item = e.target.closest('[data-idx]');
            if (item) { activate(parseInt(item.getAttribute('data-idx'), 10)); }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); toggle(); }
            else if (e.key === 'Escape' && open) { e.preventDefault(); close(); }
            else if (open && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
                e.preventDefault(); move(e.key === 'ArrowDown' ? 1 : -1);
            } else if (open && e.key === 'Enter') {
                e.preventDefault(); activate(active);
            }
        });
    }

    function fuzzyScore(needle, hay) {
        needle = (needle || '').toLowerCase();
        hay = (hay || '').toLowerCase();
        if (!needle) { return 1; }
        var i = 0, j = 0, score = 0, run = 0;
        while (i < needle.length && j < hay.length) {
            if (needle[i] === hay[j]) { run++; score += run * 2; i++; }
            else { run = 0; }
            j++;
        }
        return i === needle.length ? score : 0;
    }

    function build(q) {
        var pool = NAV.concat(QUICK_CREATE.map(function (q) { q.group = 'create'; return q; }));
        var scored = pool.map(function (item) {
            return { item: item, score: Math.max(fuzzyScore(q, item.label), q && item.href && item.href.indexOf(q) > -1 ? 0.5 : 0) };
        }).filter(function (x) { return x.score > 0; })
          .sort(function (a, b) { return b.score - a.score; })
          .slice(0, 12)
          .map(function (x) { return x.item; });
        return scored.length ? scored : pool.slice(0, 8);
    }

    function render(q) {
        current = build(q);
        active = 0;
        var html = '';
        var lastGroup = '';
        current.forEach(function (it, idx) {
            if (it.group !== lastGroup) {
                html += '<div class="p-command__group">' + esc(it.group) + '</div>';
                lastGroup = it.group;
            }
            html += '<a class="p-command__item ' + (idx === 0 ? 'is-active' : '') + '" data-idx="' + idx + '" href="' + esc(it.href) + '" data-cmd-jump>' +
                        '<span class="p-command__icon" aria-hidden="true">' + esc(it.icon || '·') + '</span>' +
                        '<span class="p-command__label">' + esc(it.label) + '</span>' +
                        (it.group === 'create' ? '<span class="p-command__tag">ساخت</span>' : '') +
                    '</a>';
        });
        results.innerHTML = html;
    }

    function onInput() {
        render(P.utils.englishDigit(input.value).trim());
    }

    function move(delta) {
        if (!current.length) { return; }
        active = (active + delta + current.length) % current.length;
        P.utils.$$('.p-command__item', results).forEach(function (n, i) {
            n.classList.toggle('is-active', i === active);
        });
        var a = results.querySelector('.p-command__item.is-active');
        if (a) { a.scrollIntoView({ block: 'nearest' }); }
    }

    function activate(idx) {
        var it = current[idx];
        if (!it) { return; }
        P.bus.emit('parsyar:command', it);
        if (it.href) { window.location.href = it.href; }
        close();
    }

    function esc(s) { return P.utils.escapeHtml(s); }

    function show() {
        ensureDom();
        el.classList.add('is-open');
        open = true;
        input.value = '';
        render('');
        setTimeout(function () { input.focus(); }, 30);
    }

    function close() {
        if (!el) { return; }
        el.classList.remove('is-open');
        open = false;
    }

    function toggle() { open ? close() : show(); }

    P.modules['command-palette'] = { show: show, close: close, toggle: toggle };
})(window, document);
