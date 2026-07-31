<?php
/**
 * KPI Grid — 4-up grid of KPI cards
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'kpis' => [],
]);

if (empty($args['kpis'])) {
    $args['kpis'] = [
        ['label' => __('مخاطبین فعال', 'parsyar'), 'value' => '۱٬۲۴۸',  'delta' => __('+۱۲٪', 'parsyar'), 'trend' => 'up'],
        ['label' => __('معاملات جاری', 'parsyar'), 'value' => '۸۷',      'delta' => __('+۵٪', 'parsyar'),  'trend' => 'up'],
        ['label' => __('درآمد ماه', 'parsyar'),     'value' => '۱۲۴٫۵M', 'delta' => __('+۲۳٪', 'parsyar'), 'trend' => 'up'],
        ['label' => __('تیکت‌های باز', 'parsyar'),   'value' => '۴',       'delta' => __('-۲', 'parsyar'),   'trend' => 'down'],
    ];
}
?>
<div class="p-grid p-grid--4 p-stagger" data-component="kpi-grid">
    <?php foreach ($args['kpis'] as $kpi): ?>
        <?php
        get_template_part('template-parts/dashboard/kpi-card', null, $kpi);
        ?>
    <?php endforeach; ?>
</div>
