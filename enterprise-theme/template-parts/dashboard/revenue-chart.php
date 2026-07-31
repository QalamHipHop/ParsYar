<?php
/**
 * Revenue Chart — SVG sparkline
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'title'    => __('درآمد ۱۲ ماه اخیر', 'parsyar'),
    'data'     => [],
    'currency' => 'IRT',
]);

if (empty($args['data'])) {
    $args['data'] = [12, 18, 14, 22, 28, 24, 32, 38, 36, 42, 48, 56]; // millions IRT
}

$max = max($args['data']) ?: 1;
$w = 600;
$h = 200;
$pad = 24;
$count = count($args['data']);
$step = ($w - 2 * $pad) / max(($count - 1), 1);

$points = [];
foreach ($args['data'] as $i => $v) {
    $x = $pad + $i * $step;
    $y = $h - $pad - (($v / $max) * ($h - 2 * $pad));
    $points[] = [$x, $y];
}

$path_d = 'M ' . implode(' L ', array_map(static fn($p) => $p[0] . ' ' . $p[1], $points));
$area_d = $path_d . " L {$points[$count-1][0]} " . ($h - $pad) . " L {$points[0][0]} " . ($h - $pad) . " Z";

$grid_lines = 4;
?>
<div class="p-card" data-component="revenue-chart">
    <header class="p-card__header">
        <div>
            <h2 class="p-card__title"><?php echo esc_html($args['title']); ?></h2>
            <p class="p-mono p-muted" style="margin: 4px 0 0; font-size: var(--p-fs-sm);">
                <strong class="p-num" style="color: var(--p-color-ink);"><?php echo esc_html(parsyar_format_money(end($args['data']) * 1000000, $args['currency'])); ?></strong>
                · <?php esc_html_e('این ماه', 'parsyar'); ?>
            </p>
        </div>
        <div class="p-cluster">
            <button type="button" class="p-btn p-btn--ghost p-btn--sm" aria-pressed="true"><?php esc_html_e('ماهانه', 'parsyar'); ?></button>
            <button type="button" class="p-btn p-btn--ghost p-btn--sm" aria-pressed="false"><?php esc_html_e('هفتگی', 'parsyar'); ?></button>
            <button type="button" class="p-btn p-btn--ghost p-btn--sm" aria-pressed="false"><?php esc_html_e('روزانه', 'parsyar'); ?></button>
        </div>
    </header>
    <div class="p-chart">
        <svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>" preserveAspectRatio="none" role="img" aria-label="<?php esc_attr_e('نمودار درآمد', 'parsyar'); ?>" style="width: 100%; height: auto;">
            <defs>
                <linearGradient id="parsyar-chart-fill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%"   stop-color="var(--p-color-ink)" stop-opacity=".15"/>
                    <stop offset="100%" stop-color="var(--p-color-ink)" stop-opacity="0"/>
                </linearGradient>
            </defs>
            <?php for ($i = 0; $i <= $grid_lines; $i++):
                $y = $pad + (($h - 2 * $pad) / $grid_lines) * $i; ?>
                <line x1="<?php echo (int) $pad; ?>" y1="<?php echo (int) $y; ?>" x2="<?php echo (int) ($w - $pad); ?>" y2="<?php echo (int) $y; ?>" stroke="var(--p-color-line-soft)" stroke-width="1" />
            <?php endfor; ?>
            <path d="<?php echo esc_attr($area_d); ?>" fill="url(#parsyar-chart-fill)" />
            <path d="<?php echo esc_attr($path_d); ?>" fill="none" stroke="var(--p-color-ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
            <?php foreach ($points as $p): ?>
                <circle cx="<?php echo (int) $p[0]; ?>" cy="<?php echo (int) $p[1]; ?>" r="3" fill="var(--p-color-bg)" stroke="var(--p-color-ink)" stroke-width="2" />
            <?php endforeach; ?>
        </svg>
    </div>
</div>

<style>
.p-chart { padding: var(--p-s-3) 0 0; }
</style>
