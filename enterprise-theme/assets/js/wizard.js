/**
 * ParsYar — SPA Dashboard Mount
 * --------------------------------------------------------------------
 * Progressive-enhancement entry that mounts the React build (when
 * present) into the in-theme dashboard. Falls back to vanilla
 * server-rendered widgets when the SPA is absent.
 *
 * @package ParsYar
 */
(function (window, document) {
    'use strict';
    var P = window.ParsYar;
    if (!P) { return; }

    function boot() {
        var root = document.getElementById('parsyar-dashboard-root');
        if (!root) { return; }
        var page = root.getAttribute('data-page') || 'home';
        // Render the revenue chart if a canvas is present
        var canvas = root.querySelector('#parsyar-revenue-chart');
        if (canvas && window.Chart) {
            var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            var labels = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
            var series = [12,18,15,22,30,28,35,42,38,46,52,61];
            new window.Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'درآمد (میلیون تومان)',
                        data: series,
                        borderColor: isDark ? '#F5F5F5' : '#0A0A0A',
                        backgroundColor: isDark ? 'rgba(245,245,245,0.12)' : 'rgba(10,10,10,0.08)',
                        fill: true,
                        tension: 0.35,
                        borderWidth: 1.5,
                        pointRadius: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: isDark ? '#B0B0B0' : '#4A4A4A', font: { family: 'Vazirmatn, system-ui' } } },
                        y: { grid: { color: isDark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)' }, ticks: { color: isDark ? '#B0B0B0' : '#4A4A4A' }, beginAtZero: true }
                    }
                }
            });
        }
        P.bus.emit('parsyar:dashboard:page', { page: page });
    }

    P.bus.on('parsyar:ready', boot);
    P.bus.on('parsyar:mounted', boot);
    P.modules['dashboard'] = { boot: boot };
})(window, document);
