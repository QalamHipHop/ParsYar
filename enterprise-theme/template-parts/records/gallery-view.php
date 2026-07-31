<?php
/**
 * Gallery View — card grid
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
        ['id' => 1, 'title' => __('شرکت پارس', 'parsyar'),       'subtitle' => '۱۲ مخاطب',   'tag' => 'مشتری'],
        ['id' => 2, 'title' => __('گروه صنعتی آریا', 'parsyar'),  'subtitle' => '۸ مخاطب',    'tag' => 'سرنخ'],
        ['id' => 3, 'title' => __('فروشگاه زنجیره‌ای شیراز', 'parsyar'), 'subtitle' => '۲۴ مخاطب', 'tag' => 'مشتری'],
        ['id' => 4, 'title' => __('استارتاپ کاوش', 'parsyar'),   'subtitle' => '۳ مخاطب',    'tag' => 'سرنخ'],
    ];
}
?>
<div class="p-grid p-grid--3 p-stagger" data-component="gallery-view">
    <?php foreach ($args['items'] as $item): ?>
        <article class="p-card p-card--hover p-gallery-item">
            <div class="p-gallery-item__cover" aria-hidden="true">
                <span class="p-avatar p-avatar--lg"><?php echo esc_html(mb_substr((string) $item['title'], 0, 1)); ?></span>
            </div>
            <header class="p-cluster p-cluster--between" style="margin-top: var(--p-s-3);">
                <h3 class="p-card__title" style="margin: 0; font-size: var(--p-fs-md);"><?php echo esc_html($item['title']); ?></h3>
                <?php if (!empty($item['tag'])): ?>
                    <span class="p-badge p-badge--outline"><?php echo esc_html($item['tag']); ?></span>
                <?php endif; ?>
            </header>
            <p class="p-muted" style="margin: var(--p-s-1) 0 0; font-size: var(--p-fs-sm);"><?php echo esc_html($item['subtitle']); ?></p>
        </article>
    <?php endforeach; ?>
</div>

<style>
.p-gallery-item__cover {
    aspect-ratio: 16/9;
    background: var(--p-color-surface-2);
    border-radius: var(--p-r-sm);
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
