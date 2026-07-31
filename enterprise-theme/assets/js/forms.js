/**
 * ParsYar — Forms
 * --------------------------------------------------------------------
 * Auto-enhances:
 *   - Floating labels (input-parsyar-float)
 *   - Inline validation (data-validate=json)
 *   - Persian <-> English digit normalization (data-digit="fa|en")
 *   - Submit via fetch with envelope unwrap
 *   - Optimistic UI for [data-optimistic]
 *   - Auto-grow textareas
 *
 * @package ParsYar
 */
(function (window, document) {
    'use strict';
    var P = window.ParsYar;
    if (!P) { return; }

    function esc(s) { return P.utils.escapeHtml(s); }

    function digitize(input, mode) {
        input.addEventListener('input', function () {
            if (mode === 'fa') {
                input.value = P.utils.persianDigit(P.utils.englishDigit(input.value));
            } else if (mode === 'en') {
                input.value = P.utils.englishDigit(input.value);
            }
        });
    }

    function autoGrow(ta) {
        function fit() { ta.style.height = 'auto'; ta.style.height = ta.scrollHeight + 'px'; }
        ta.addEventListener('input', fit);
        fit();
    }

    function float(input) {
        var update = function () { input.classList.toggle('is-filled', !!input.value); };
        input.addEventListener('input', update);
        input.addEventListener('change', update);
        update();
    }

    function validate(form) {
        var rules = [];
        P.utils.$$('[data-validate]', form).forEach(function (el) {
            try { rules.push({ el: el, spec: JSON.parse(el.getAttribute('data-validate') || '{}') }); } catch (e) {}
        });
        function run(e) {
            var ok = true;
            rules.forEach(function (r) {
                var v = (r.el.value || '').trim();
                var err = '';
                if (r.spec.required && !v) { err = r.spec.requiredMessage || 'این فیلد الزامی است.'; }
                else if (r.spec.minLength && v.length < r.spec.minLength) { err = 'حداقل ' + r.spec.minLength + ' کاراکتر.'; }
                else if (r.spec.pattern && v && !new RegExp(r.spec.pattern).test(v)) { err = r.spec.patternMessage || 'مقدار نامعتبر است.'; }
                setError(r.el, err);
                if (err) { ok = false; }
            });
            if (!ok) { e.preventDefault(); e.stopPropagation(); }
            return ok;
        }
        form.addEventListener('submit', run);
        form._parsyarValidate = run;
        return run;
    }

    function setError(el, msg) {
        var id = el.getAttribute('aria-describedby');
        var node = id && document.getElementById(id);
        if (msg) {
            el.classList.add('is-invalid');
            el.setAttribute('aria-invalid', 'true');
            if (node) { node.textContent = msg; }
        } else {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
            if (node) { node.textContent = ''; }
        }
    }

    function ajaxSubmit(form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (form._parsyarValidate && !form._parsyarValidate(e)) { return; }
            var url = form.getAttribute('action') || (P.cfg.restUrl + (form.getAttribute('data-endpoint') || ''));
            var method = (form.getAttribute('method') || 'POST').toUpperCase();
            var fd = new FormData(form);
            var obj = {};
            fd.forEach(function (v, k) {
                if (obj[k] !== undefined) {
                    if (!Array.isArray(obj[k])) { obj[k] = [obj[k]]; }
                    obj[k].push(v);
                } else { obj[k] = v; }
            });
            var btn = form.querySelector('[type=submit]');
            if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }
            P.utils.fetchJSON(url, { method: method, body: JSON.stringify(obj) })
                .then(function (resp) {
                    P.bus.emit('parsyar:form:success', { form: form, resp: resp });
                    if (form.hasAttribute('data-optimistic')) {
                        P.bus.emit('parsyar:notify', { kind: 'positive', message: (P.cfg.i18n && P.cfg.i18n.saved) || 'ذخیره شد' });
                    } else {
                        location.reload();
                    }
                })
                .catch(function (err) {
                    setError(form.querySelector('[name]') || form, err.message);
                    P.bus.emit('parsyar:form:error', { form: form, error: err });
                })
                .then(function () { if (btn) { btn.disabled = false; btn.classList.remove('is-loading'); } });
        });
    }

    function mount(form) {
        P.utils.$$('input[data-parsyar-float], textarea[data-parsyar-float]', form).forEach(float);
        P.utils.$$('input[data-digit], textarea[data-digit]', form).forEach(function (el) { digitize(el, el.getAttribute('data-digit')); });
        P.utils.$$('textarea[data-autogrow]', form).forEach(autoGrow);
        if (form.matches('[data-ajax]') || form.hasAttribute('data-ajax')) { ajaxSubmit(form); }
        validate(form);
    }

    P.modules['forms'] = { mount: mount, setError: setError };
    P.bus.on('parsyar:mounted', function () { P.utils.$$('form.parsyar-form, form[data-parsyar-form]').forEach(mount); });
})(window, document);
