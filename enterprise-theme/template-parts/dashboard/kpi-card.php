<?php
/**
 * KPI Card — single metric display
 *
 * Available args:
 *  - label    string
 *  - value    string|int
 *  - delta    string  (e.g. "+12%")
 *  - trend    string  ('up'|'down'|'flat')
 *  - href     string  (optional)
 *  - icon     string  (svg path)
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'label' => '',
    'value' => '',
    'delta' => '',
    'trend' => 'flat',
    'href'  => '',
    'icon'  => '',
]);

$trend_arrow = ['up' => '↑', 'down' => '↓', 'flat' => '→'][$args['trend']] ?? '→';
$wrapper_tag = $args['href'] ? 'a' : 'div';
$wrapper_attr = $args['href'] ? sprintf('href="%s"', esc_url($args['href'])) : '';
?>
<<?php echo esc_attr($wrapper_tag); ?> <?php echo $wrapper_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="p-card p-card--hover p-kpi" data-trend="<?php echo esc_attr($args['trend']); ?>">
    <div class="p-cluster p-cluster--between" style="margin-block-end: var(--p-s-2);">
        <span class="p-mono p-muted" style="font-size: var(--p-fs-2xs); text-transform: uppercase; letter-spacing: var(--p-ls-xwide);">
            <?php echo esc_html($args['label']); ?>
        </span>
        <?php if ($args['icon']): ?>
            <svg class="p-rail__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--p-color-ink-4); width: 16px; height: 16px;">
                <?php echo $args['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            </svg>
        <?php endif; ?>
    </div>
    <p class="p-num" style="font-size: var(--p-fs-3xl); font-weight: var(--p-fw-bold); margin: 0; line-height: 1;"><?php echo esc_html($args['value']); ?></p>
    <?php if ($args['delta']): ?>
        <p class="p-mono" style="margin: var(--p-s-2) 0 0; font-size: var(--p-fs-xs); color: var(--p-color-ink-3);">
            <span><?php echo esc_html($trend_arrow); ?></span>
            <span><?php echo esc_html($args['delta']); ?></span>
        </p>
    <?php endif; ?>
</<?php echo esc_attr($wrapper_tag); ?>>
