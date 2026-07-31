<?php
/**
 * Activity Feed
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'limit' => 10,
    'items' => [],
]);

if (empty($args['items'])) {
    $args['items'] = [
        ['actor' => 'علی محمدی',   'verb' => __('یک مخاطب جدید اضافه کرد', 'parsyar'),  'object' => 'شرکت پارس',         'time' => __('۲ دقیقه پیش', 'parsyar'), 'icon' => 'user'],
        ['actor' => 'مریم رضایی',  'verb' => __('یک معامله را برد', 'parsyar'),         'object' => 'قرارداد ۵۰۰ میلیون', 'time' => __('۱ ساعت پیش', 'parsyar'), 'icon' => 'check'],
        ['actor' => 'حسین کریمی',  'verb' => __('یک فاکتور صادر کرد', 'parsyar'),       'object' => 'INV-2024-0142',     'time' => __('۳ ساعت پیش', 'parsyar'), 'icon' => 'file'],
        ['actor' => 'زهرا احمدی',  'verb' => __('یک تیکت پاسخ داد', 'parsyar'),         'object' => '#۲۴۱',              'time' => __('دیروز', 'parsyar'),      'icon' => 'chat'],
        ['actor' => 'رضا نوری',    'verb' => __('یک سفارش ثبت کرد', 'parsyar'),         'object' => 'ORD-7892',          'time' => __('۲ روز پیش', 'parsyar'),  'icon' => 'cart'],
    ];
}

$icon_paths = [
    'user'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'check' => '<polyline points="20 6 9 17 4 12"/>',
    'file'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    'chat'  => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    'cart'  => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
    'mail'  => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
    'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>',
];
?>
<div class="p-card" data-component="activity-feed">
    <header class="p-card__header">
        <h2 class="p-card__title"><?php esc_html_e('فعالیت‌های اخیر', 'parsyar'); ?></h2>
        <a href="<?php echo esc_url(home_url('/app/activities')); ?>" class="p-btn p-btn--ghost p-btn--sm"><?php esc_html_e('مشاهده همه', 'parsyar'); ?></a>
    </header>
    <div class="p-stack p-stack--sm p-stagger">
        <?php foreach (array_slice($args['items'], 0, (int) $args['limit']) as $item):
            $ic = $icon_paths[$item['icon']] ?? $icon_paths['user'];
        ?>
            <div class="p-activity">
                <span class="p-activity__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><?php echo $ic; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></svg>
                </span>
                <div class="p-activity__body">
                    <p>
                        <strong><?php echo esc_html($item['actor']); ?></strong>
                        <?php echo esc_html($item['verb']); ?>
                        <a href="#" class="p-mono"><?php echo esc_html($item['object']); ?></a>
                    </p>
                    <p class="p-muted" style="margin: 0; font-size: var(--p-fs-xs);"><?php echo esc_html($item['time']); ?></p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.p-activity {
    display: flex;
    align-items: flex-start;
    gap: var(--p-s-3);
    padding-block: var(--p-s-2);
}
.p-activity__icon {
    width: 32px; height: 32px;
    background: var(--p-color-surface-2);
    color: var(--p-color-ink-2);
    border-radius: var(--p-r-pill);
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.p-activity__icon svg { width: 16px; height: 16px; }
.p-activity__body { flex: 1; min-width: 0; }
.p-activity__body p { margin: 0; font-size: var(--p-fs-sm); }
</style>
