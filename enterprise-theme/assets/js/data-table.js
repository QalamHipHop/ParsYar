/**
 * ParsYar — Data Table
 * --------------------------------------------------------------------
 * Generic table with:
 *   - Server-side pagination (data-source=)
 *   - Sortable columns (data-sort=)
 *   - Bulk select (header checkbox + row checkboxes)
 *   - Inline actions (data-action)
 *   - Kanban drag-drop (when data-view="kanban" via Sortable)
 *   - Quick filter (data-filter-input)
 *   - Empty state
 *
 * @package ParsYar
 */
(function (window, document) {
    'use strict';
    var P = window.ParsYar;
    if (!P) { return; }

    function esc(s) { return P.utils.escapeHtml(s); }

    function mount(el, opts) {
        opts = opts || {};
        el._parsyarTable = {
            opts: opts,
            page: opts.page || 1,
            perPage: opts.perPage || 25,
            sort: opts.sort || '-id',
            q: '',
            total: 0,
            rows: []
        };
        bind(el);
        fetch(el);
    }

    function bind(el) {
        if (el._parsyarTable.bound) { return; }
        el._parsyarTable.bound = true;
        el.addEventListener('click', function (e) {
            var sortable = e.target.closest('[data-sort]');
            if (sortable) { onSort(el, sortable.getAttribute('data-sort')); return; }
            var pg = e.target.closest('[data-page]');
            if (pg) { onPage(el, parseInt(pg.getAttribute('data-page'), 10)); return; }
            var act = e.target.closest('[data-action]');
            if (act) { onAction(el, act.getAttribute('data-action'), act.closest('[data-row-id]')); return; }
            var head = e.target.closest('[data-select-all]');
            if (head) { onSelectAll(el, head.checked); return; }
        });
        el.addEventListener('input', P.utils.debounce(function (e) {
            var f = e.target.closest('[data-filter-input]');
            if (f) { el._parsyarTable.q = f.value; el._parsyarTable.page = 1; fetch(el); }
        }, 250));
    }

    function onSort(el, key) {
        var t = el._parsyarTable;
        t.sort = (t.sort === key) ? '-' + key : key;
        fetch(el);
    }

    function onPage(el, p) {
        el._parsyarTable.page = Math.max(1, p);
        fetch(el);
    }

    function onAction(el, action, row) {
        var id = row ? row.getAttribute('data-row-id') : null;
        P.bus.emit('parsyar:action', { action: action, id: id, el: el });
        if (action === 'delete' && id) {
            if (!confirm(P.cfg.i18n && P.cfg.i18n.confirm)) { return; }
            P.utils.fetchJSON(el.getAttribute('data-source') + '/' + id, { method: 'DELETE' })
                .then(function () { fetch(el); })
                .catch(function (e) { P.bus.emit('parsyar:error', e); });
        }
    }

    function onSelectAll(el, checked) {
        P.utils.$$('[data-row-select]', el).forEach(function (c) { c.checked = checked; });
        P.bus.emit('parsyar:selection', { el: el, ids: selectedIds(el) });
    }

    function selectedIds(el) {
        return P.utils.$$('[data-row-select]:checked', el).map(function (c) { return c.value; });
    }

    function fetch(el) {
        var t = el._parsyarTable;
        var url = el.getAttribute('data-source');
        if (!url) { return; }
        var q = (t.q ? '&q=' + encodeURIComponent(t.q) : '') +
                '&page=' + t.page +
                '&per_page=' + t.perPage +
                '&orderby=' + encodeURIComponent(t.sort);
        el.classList.add('is-loading');
        return P.utils.fetchJSON(url + (url.indexOf('?') > -1 ? '&' : '?') + q.replace(/^&/, ''))
            .then(function (resp) {
                el.classList.remove('is-loading');
                t.total = (resp && resp.meta && resp.meta.total) || (resp && resp.data && resp.data.length) || 0;
                t.rows  = (resp && resp.data) || [];
                render(el, t.rows);
                renderPager(el, t);
            })
            .catch(function (e) {
                el.classList.remove('is-loading');
                el.innerHTML = '<div class="p-empty">' + esc((P.cfg.i18n && P.cfg.i18n.error) || 'خطا') + ': ' + esc(e.message) + '</div>';
            });
    }

    function render(el, rows) {
        var tmpl = el.querySelector('template[data-row-template]');
        var tbody = el.querySelector('[data-tbody]');
        if (!tbody) { return; }
        if (!rows.length) {
            tbody.innerHTML = '<tr><td colspan="99" class="p-empty">' + esc((P.cfg.i18n && P.cfg.i18n.noResults) || 'نتیجه‌ای نیست') + '</td></tr>';
            return;
        }
        var html = rows.map(function (r) {
            if (tmpl) {
                return tmpl.innerHTML
                    .replace(/\{\{id\}\}/g, esc(r.id))
                    .replace(/\{\{(\w+)\}\}/g, function (_, k) { return esc(r[k] !== undefined ? r[k] : ''); });
            }
            return '<tr data-row-id="' + esc(r.id) + '"><td>' + esc(r.id) + '</td></tr>';
        }).join('');
        tbody.innerHTML = html;
    }

    function renderPager(el, t) {
        var pager = el.querySelector('[data-pager]');
        if (!pager) { return; }
        var totalPages = Math.max(1, Math.ceil(t.total / t.perPage));
        var html = '';
        html += '<button data-page="' + (t.page - 1) + '" ' + (t.page <= 1 ? 'disabled' : '') + '>‹ قبلی</button>';
        html += '<span class="p-pager__info">' + t.page + ' / ' + totalPages + '</span>';
        html += '<button data-page="' + (t.page + 1) + '" ' + (t.page >= totalPages ? 'disabled' : '') + '>بعدی ›</button>';
        pager.innerHTML = html;
    }

    P.modules['data-table'] = { mount: mount, refresh: fetch };
})(window, document);
