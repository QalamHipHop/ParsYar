<?php
/**
 * Pipeline Mini — horizontal bar showing deal stages summary
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'stages' => [],
]);

if (empty($args['stages'])) {
    $args['stages'] = [
        ['name' => __('سرنخ', 'parsyar'),         'count' => 142, 'amount' => 1250000000],
        ['name' => __('واجد شرایط', 'parsyar'),   'count' => 87,  'amount' => 980000000],
        ['name' => __('پیشنهاد', 'parsyar'),       'count' => 54,  'amount' => 720000000],
        ['name' => __('مذاکره', 'parsyar'),        'count' => 32,  'amount' => 410000000],
        ['name' => __('برنده', 'parsyar'),          'count' => 18,  'amount' => 285000000],
    ];
}

$max_amount = max(array_column($args['stages'], 'amount'));
$max_amount = $max_amount > 0 ? $max_amount : 1;
?>
<div class="p-card" data-component="pipeline-mini">
    <header class="p-card__header">
        <h2 class="p-card__title"><?php esc_html_e('خلاصه خط فروش', 'parsyar'); ?></h2>
        <a href="<?php echo esc_url(home_url('/app/deals')); ?>" class="p-btn p-btn--ghost p-btn--sm"><?php esc_html_e('باز کردن', 'parsyar'); ?></a>
    </header>
    <div class="p-pipeline p-stack p-stack--sm">
        <?php foreach ($args['stages'] as $stage):
            $pct = round(((float) $stage['amount'] / (float) $max_amount) * 100);
        ?>
            <div class="p-pipeline__row">
                <div class="p-pipeline__head">
                    <span class="p-pipeline__name"><?php echo esc_html($stage['name']); ?></span>
                    <span class="p-pipeline__meta p-num">
                        <span class="p-badge p-badge--outline"><?php echo esc_html(number_format_i18n((int) $stage['count'])); ?></span>
                        <strong><?php echo esc_html(parsyar_format_money($stage['amount'])); ?></strong>
                    </span>
                </div>
                <div class="p-progress" role="progressbar" aria-valuenow="<?php echo esc_attr($pct); ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="p-progress__bar" style="width: <?php echo esc_attr($pct); ?>%;"></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.p-pipeline__row { display: block; }
.p-pipeline__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-block-end: var(--p-s-1);
    font-size: var(--p-fs-sm);
}
.p-pipeline__name { font-weight: var(--p-fw-medium); }
.p-pipeline__meta { display: flex; align-items: center; gap: var(--p-s-2); color: var(--p-color-ink-3); }
</style>
