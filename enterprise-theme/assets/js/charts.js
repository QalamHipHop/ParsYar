/**
 * ParsYar — Charts (Chart.js monochrome wrapper)
 * --------------------------------------------------------------------
 * Renders Chart.js charts in a strictly B&W palette.
 * Auto-detects theme (light/dark) and inverts ink scale.
 *
 * Reads <canvas data-parsyar-chart> with JSON spec in data-spec.
 *
 * @package ParsYar
 */
(function (window, document) {
    'use strict';
    var P = window.ParsYar;
    if (!P) { return; }

    function palette(theme) {
        theme = theme || (document.documentElement.getAttribute('data-theme') || 'light');
        if (theme === 'dark') {
            return {
                ink:      ['#F5F5F5', '#E0E0E0', '#B0B0B0', '#6E6E6E', '#3A3A3A'],
                line:     '#1F1F1F',
                fillFrom: 'rgba(245,245,245,0.20)',
                fillTo:   'rgba(245,245,245,0.00)',
                grid:     'rgba(255,255,255,0.06)',
                ticks:    '#B0B0B0'
            };
        }
        return {
            ink:      ['#0A0A0A', '#1F1F1F', '#4A4A4A', '#8A8A8A', '#BFBFBF'],
            line:     '#E5E5E5',
            fillFrom: 'rgba(10,10,10,0.10)',
            fillTo:   'rgba(10,10,10,0.00)',
            grid:     'rgba(0,0,0,0.06)',
            ticks:    '#4A4A4A'
        };
    }

    function build(ctx, spec, pal) {
        var cfg = {
            type: spec.type || 'line',
            data: {
                labels: spec.labels || [],
                datasets: (spec.datasets || []).map(function (d, i) {
                    var c = pal.ink[i % pal.ink.length];
                    var base = {
                        label: d.label || '',
                        data: d.data || [],
                        borderColor: d.color || c,
                        backgroundColor: d.fill ? pal.fillFrom : 'transparent',
                        borderWidth: d.borderWidth || 1.5,
                        tension: 0.32,
                        pointRadius: d.pointRadius !== undefined ? d.pointRadius : (spec.type === 'bar' ? 0 : 2),
                        pointHoverRadius: 4,
                        fill: d.fill === true ? 'origin' : (d.fill || false)
                    };
                    if (spec.type === 'bar') {
                        base.borderRadius = 2;
                        base.maxBarThickness = 28;
                        base.backgroundColor = c;
                    }
                    return base;
                })
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 380, easing: 'easeOutQuart' },
                plugins: {
                    legend: { display: spec.legend !== false, labels: { color: pal.ticks, font: { family: 'Vazirmatn, Inter, system-ui', size: 11 } } },
                    tooltip: {
                        backgroundColor: '#0A0A0A',
                        titleColor: '#FAFAFA',
                        bodyColor: '#FAFAFA',
                        borderColor: '#1F1F1F',
                        borderWidth: 1,
                        padding: 10,
                        cornerRadius: 4,
                        displayColors: false,
                        rtl: P.isRTL,
                        textDirection: P.isRTL ? 'rtl' : 'ltr'
                    }
                },
                scales: spec.type === 'pie' || spec.type === 'doughnut' ? {} : {
                    x: { grid: { color: pal.grid, drawBorder: false }, ticks: { color: pal.ticks, font: { family: 'Vazirmatn, Inter, system-ui', size: 10 } } },
                    y: { grid: { color: pal.grid, drawBorder: false }, ticks: { color: pal.ticks, font: { family: 'Vazirmatn, Inter, system-ui', size: 10 } }, beginAtZero: spec.beginAtZero !== false }
                }
            }
        };
        return new window.Chart(ctx, cfg);
    }

    var instances = [];

    function mount(canvas) {
        try {
            var spec = JSON.parse(canvas.getAttribute('data-spec') || '{}');
            var inst = build(canvas, spec, palette());
            instances.push({ canvas: canvas, instance: inst });
        } catch (e) { console.error('[ParsYar charts] invalid data-spec', e); }
    }

    function rebind() {
        instances.forEach(function (rec) {
            if (rec.instance && rec.instance.destroy) { rec.instance.destroy(); }
            mount(rec.canvas);
        });
    }

    P.modules.charts = {
        mount: mount,
        rebind: rebind,
        palette: palette
    };

    P.bus.on('parsyar:theme', function () { rebind(); });
    P.bus.on('parsyar:ready', function () {
        P.utils.$$('[data-parsyar-chart]').forEach(mount);
    });
})(window, document);
