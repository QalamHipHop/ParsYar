<?php
/**
 * Birthdays widget — Persian calendar aware
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'items' => [],
]);

if (empty($args['items'])) {
    $args['items'] = [
        ['name' => 'علی محمدی',   'date' => parsyar_jalali_date('j F'),         'turns' => 32],
        ['name' => 'مریم رضایی',  'date' => parsyar_jalali_date('j F', '+1 day'), 'turns' => 28],
        ['name' => 'حسین کریمی',  'date' => parsyar_jalali_date('j F', '+3 day'), 'turns' => 45],
    ];
}
?>
<div class="p-card" data-component="birthdays">
    <header class="p-card__header">
        <h2 class="p-card__title"><?php esc_html_e('تولد این هفته', 'parsyar'); ?></h2>
        <span class="p-mono p-muted" style="font-size: var(--p-fs-xs);"><?php echo esc_html(parsyar_jalali_date('l j F Y')); ?></span>
    </header>
    <ul class="p-birthdays" role="list">
        <?php foreach ($args['items'] as $b): ?>
            <li class="p-birthdays__item">
                <span class="p-avatar p-avatar--sm" aria-hidden="true"><?php echo esc_html(mb_substr($b['name'], 0, 1)); ?></span>
                <div style="flex: 1; min-width: 0;">
                    <p style="margin: 0; font-size: var(--p-fs-sm); font-weight: var(--p-fw-medium);"><?php echo esc_html($b['name']); ?></p>
                    <p class="p-mono p-muted" style="margin: 0; font-size: var(--p-fs-xs);"><?php echo esc_html($b['date']); ?></p>
                </div>
                <?php if (!empty($b['turns'])): ?>
                    <span class="p-badge p-badge--outline"><?php printf(esc_html__('%s سال', 'parsyar'), esc_html(parsyar_format_number_fa((int) $b['turns']))); ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<style>
.p-birthdays { list-style: none; padding: 0; margin: 0; }
.p-birthdays__item {
    display: flex;
    align-items: center;
    gap: var(--p-s-3);
    padding: var(--p-s-2) 0;
    border-bottom: 1px solid var(--p-color-line-soft);
}
.p-birthdays__item:last-child { border-bottom: 0; }
</style>
