/* global jQuery, ParsYarWizard */
(function ($) {
    'use strict';

    if (typeof ParsYarWizard === 'undefined') return;

    const $doc = $(document);
    const $w   = $('.parsyar-wizard');
    if ($w.length === 0) return;

    const state = {
        step: parseInt($w.find('.pw-step.is-current').data('step') || 1, 10),
        busy: false,
    };

    /* --------------- nav: prev/next/skip --------------- */
    $doc.on('click', '[data-pw-nav]', function (e) {
        e.preventDefault();
        if (state.busy) return;
        const action = $(this).data('pw-nav'); // prev|next|skip
        if (action === 'prev') {
            navigate(state.step - 1, 'prev');
        } else if (action === 'next') {
            saveAndNext();
        } else if (action === 'skip') {
            saveAndNext('skip');
        }
    });

    $doc.on('click', '.pw-step', function () {
        const s = parseInt($(this).data('step'), 10);
        if (!s) return;
        // save current then go
        saveAndNext('goto', s);
    });

    /* --------------- mode picker (step 1) --------------- */
    $doc.on('click', '#pw-mode-grid .pw-mode', function () {
        $('#pw-mode-grid .pw-mode').removeClass('is-on');
        $(this).addClass('is-on');
        $('<input>').attr({
            type: 'hidden', name: 'mode', value: $(this).data('mode')
        }).appendTo('#pw-step-body');
    });

    /* --------------- generic toggles (data-toggle="a[b]") --------------- */
    $doc.on('click', '[data-toggle]', function () {
        const name = $(this).data('toggle');
        const isOn = $(this).hasClass('is-on');
        const v = isOn ? 0 : 1;
        // remove existing input for this name
        $('#pw-step-body input[name="' + cssEscape(name) + '"]').remove();
        $('<input>').attr({ type: 'hidden', name: name, value: v }).appendTo('#pw-step-body');
        $(this).toggleClass('is-on');
        const labelOn  = $(this).data('on')  || $(this).text();
        const labelOff = $(this).data('off') || $(this).text();
        $(this).text(isOn ? labelOff : labelOn);
    });

    /* --------------- array toggles (data-toggle-array="a[b][c]") --------------- */
    $doc.on('click', '[data-toggle-array]', function () {
        const name = $(this).data('toggle-array');
        const isOn = $(this).hasClass('is-on');
        const v = isOn ? 0 : 1;
        $('#pw-step-body input[name="' + cssEscape(name) + '"]').remove();
        $('<input>').attr({ type: 'hidden', name: name, value: v }).appendTo('#pw-step-body');
        $(this).toggleClass('is-on');
    });

    /* --------------- currency chips (step 6) --------------- */
    $doc.on('click', '#pw-currencies-list .pw-chip', function () {
        const cur = $(this).data('currency');
        const isOn = $(this).hasClass('is-on');
        const name = 'currencies[]';
        $('#pw-step-body input[name="' + name + '"][value="' + cur + '"]').remove();
        if (!isOn) {
            $('<input>').attr({ type: 'hidden', name: name, value: cur }).appendTo('#pw-step-body');
        }
        $(this).toggleClass('is-on');
    });

    /* --------------- exchange provider toggle --------------- */
    $doc.on('change', '#pw-exch-provider', function () {
        const v = $(this).val();
        $('#pw-exch-api-wrap').prop('hidden', v === 'manual');
    });

    /* --------------- companies/branches add --------------- */
    $doc.on('click', '#pw-add-company', function () {
        const tpl = $('#pw-company-tpl').html();
        const idx = $('#pw-companies-list > div').length;
        $('#pw-companies-list').append(tpl.replaceAll('__i__', idx));
    });
    $doc.on('click', '#pw-add-branch', function () {
        const tpl = $('#pw-branch-tpl').html();
        const idx = $('#pw-branches-list > div').length;
        $('#pw-branches-list').append(tpl.replaceAll('__i__', idx));
    });

    /* --------------- pipelines: add stage / add pipeline --------------- */
    $doc.on('click', '.pw-add-stage', function () {
        const $p = $(this).closest('.pw-pipeline');
        const pi = $p.data('pipeline-index');
        const si = $p.find('.pw-stage').length;
        const row = $(
            '<div class="pw-stage" style="display:grid; grid-template-columns: 2fr 3fr 1fr 1fr auto; gap:8px; align-items:end; padding:6px 0;">' +
                '<input type="text" placeholder="کد" name="pipelines[' + pi + '][stages][' + si + '][id]">' +
                '<input type="text" placeholder="نام" name="pipelines[' + pi + '][stages][' + si + '][name]">' +
                '<input type="number" placeholder="%" min="0" max="100" name="pipelines[' + pi + '][stages][' + si + '][probability]">' +
                '<input type="number" placeholder="WIP" min="0" name="pipelines[' + pi + '][stages][' + si + '][wip_limit]">' +
                '<button type="button" class="button pw-del-stage">حذف</button>' +
            '</div>'
        );
        $(this).siblings('.pw-stages').append(row);
    });
    $doc.on('click', '.pw-del-stage', function () {
        $(this).closest('.pw-stage').remove();
    });
    $doc.on('click', '#pw-add-pipeline', function () {
        // simple: add a fresh default pipeline row
        location.reload(); // fallback: reload page so PHP renders empty form
    });

    /* --------------- module / role pickers --------------- */
    $doc.on('click', '[data-module]', function () {
        const k = $(this).data('module');
        const isOn = $(this).hasClass('is-on');
        const v = isOn ? 0 : 1;
        $('#pw-step-body input[name="modules[' + k + ']"]').remove();
        $('<input>').attr({ type: 'hidden', name: 'modules[' + k + ']', value: v }).appendTo('#pw-step-body');
        $(this).toggleClass('is-on').find('p').last().text(isOn ? 'غیرفعال' : 'فعال');
    });
    $doc.on('click', '[data-role]', function () {
        const k = $(this).data('role');
        const isOn = $(this).hasClass('is-on');
        const v = isOn ? 0 : 1;
        $('#pw-step-body input[name="roles[' + k + ']"]').remove();
        $('<input>').attr({ type: 'hidden', name: 'roles[' + k + ']', value: v }).appendTo('#pw-step-body');
        $(this).toggleClass('is-on');
    });

    /* --------------- export / import / reset --------------- */
    $doc.on('click', '#pw-export', function () {
        window.location.href = ParsYarWizard.ajaxUrl + '?action=parsyar_wizard_export&nonce=' + ParsYarWizard.nonce;
    });
    $doc.on('change', '#pw-import-file', function () {
        const f = this.files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('action', 'parsyar_wizard_import');
        fd.append('nonce', ParsYarWizard.nonce);
        fd.append('file', f);
        $.ajax({
            url: ParsYarWizard.ajaxUrl, type: 'POST', data: fd,
            processData: false, contentType: false,
            success: function (r) { alert(r.data?.message || 'OK'); location.reload(); },
            error: function () { alert('خطا'); }
        });
    });
    $doc.on('click', '#pw-restart', function () {
        if (!confirm(ParsYarWizard.i18n.confirmReset)) return;
        $.post(ParsYarWizard.ajaxUrl, { action: 'parsyar_wizard_reset', nonce: ParsYarWizard.nonce }, function (r) {
            location.reload();
        });
    });

    /* --------------- last step: apply --------------- */
    $doc.on('click', '[data-pw-apply]', function () {
        if (state.busy) return;
        state.busy = true;
        $(this).prop('disabled', true).text('در حال اعمال...');
        $.post(ParsYarWizard.ajaxUrl, { action: 'parsyar_wizard_apply', nonce: ParsYarWizard.nonce, step: state.step }, function (r) {
            if (r.success) {
                alert(r.data.message);
                if (r.data.redirect) location.href = r.data.redirect;
            } else {
                alert(r.data?.message || 'خطا');
                state.busy = false;
                $('[data-pw-apply]').prop('disabled', false).text('پایان');
            }
        }).fail(function () {
            alert('خطای شبکه');
            state.busy = false;
            $('[data-pw-apply]').prop('disabled', false).text('پایان');
        });
    });

    /* --------------- core: save current step then go next --------------- */
    function saveAndNext(action, gotoStep) {
        if (state.busy) return;
        state.busy = true;

        // collect form values
        const $body = $('#pw-step-body');
        const data = collectData($body);
        const step = state.step;

        const req = {
            action: 'parsyar_wizard_save',
            nonce: ParsYarWizard.nonce,
            step: step,
            step_action: action || (gotoStep ? 'goto' : 'next'),
            data: data,
        };

        $.post(ParsYarWizard.ajaxUrl, req, function (r) {
            if (!r.success) {
                alert(r.data?.message || 'خطا');
                state.busy = false;
                return;
            }
            const next = gotoStep || r.data.next;
            location.href = '?page=enterprise-setup&step=' + next;
        }, 'json').fail(function (xhr) {
            alert('خطای ذخیره: ' + (xhr.responseText || ''));
            state.busy = false;
        });
    }

    function navigate(s, dir) {
        location.href = '?page=enterprise-setup&step=' + s;
    }

    function collectData($root) {
        const out = {};
        $root.find('input[name], select[name], textarea[name]').each(function () {
            const $el = $(this);
            if ($el.attr('type') === 'file') return;
            const name = $el.attr('name');
            if (!name) return;
            const val = $el.val();
            if (name.endsWith('[]')) {
                const k = name.slice(0, -2);
                if (!Array.isArray(out[k])) out[k] = [];
                if (val !== null && val !== '') out[k].push(val);
            } else {
                setPath(out, name, val);
            }
        });
        return out;
    }

    function setPath(out, path, val) {
        // supports "a[b][c]" or "a"
        const parts = [];
        let buf = '';
        let inBracket = false;
        for (let i = 0; i < path.length; i++) {
            const ch = path[i];
            if (ch === '[') { parts.push(buf); buf = ''; inBracket = true; }
            else if (ch === ']') { parts.push(buf); buf = ''; }
            else { buf += ch; }
        }
        if (buf !== '') parts.push(buf);
        let cur = out;
        for (let i = 0; i < parts.length - 1; i++) {
            const k = parts[i];
            if (!cur[k] || typeof cur[k] !== 'object') cur[k] = {};
            cur = cur[k];
        }
        cur[parts[parts.length - 1]] = val;
    }

    function cssEscape(s) {
        if (window.CSS && CSS.escape) return CSS.escape(s);
        return s.replace(/[^a-zA-Z0-9_\-]/g, '\\$&');
    }

    /* --------------- last step button override --------------- */
    if (state.step === 23) {
        $('[data-pw-nav="next"]').attr('data-pw-apply', '').text('پایان و اعمال');
    }
})(jQuery);
