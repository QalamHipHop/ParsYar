<?php
/**
 * Todo List
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
        ['done' => true,  'text' => 'پیگیری پیشنهاد شرکت پارس', 'time' => __('امروز', 'parsyar')],
        ['done' => false, 'text' => 'تماس با آقای رضایی درباره قرارداد', 'time' => __('امروز', 'parsyar')],
        ['done' => false, 'text' => 'بررسی فاکتور INV-2024-0142', 'time' => __('فردا', 'parsyar')],
        ['done' => false, 'text' => 'ارسال ایمیل یادآوری به ۵ سرنخ گرم', 'time' => __('این هفته', 'parsyar')],
    ];
}
?>
<div class="p-card" data-component="todo-list">
    <header class="p-card__header">
        <h2 class="p-card__title"><?php esc_html_e('وظایف امروز', 'parsyar'); ?></h2>
        <button type="button" class="p-btn p-btn--ghost p-btn--sm"><?php esc_html_e('افزودن', 'parsyar'); ?></button>
    </header>
    <ul class="p-todo" role="list">
        <?php foreach ($args['items'] as $i => $item): ?>
            <li class="p-todo__item<?php echo $item['done'] ? ' is-done' : ''; ?>">
                <label class="p-check">
                    <input type="checkbox" <?php checked($item['done']); ?> data-todo-index="<?php echo (int) $i; ?>" />
                    <span class="p-todo__text"><?php echo esc_html($item['text']); ?></span>
                </label>
                <span class="p-todo__time p-mono"><?php echo esc_html($item['time']); ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<style>
.p-todo { list-style: none; padding: 0; margin: 0; }
.p-todo__item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--p-s-3);
    padding: var(--p-s-2) 0;
    border-bottom: 1px solid var(--p-color-line-soft);
}
.p-todo__item:last-child { border-bottom: 0; }
.p-todo__text {
    flex: 1;
    font-size: var(--p-fs-sm);
    transition: text-decoration var(--p-dur-2) var(--p-ease-out), color var(--p-dur-2) var(--p-ease-out);
}
.p-todo__item.is-done .p-todo__text {
    text-decoration: line-through;
    color: var(--p-color-ink-4);
}
.p-todo__time { font-size: var(--p-fs-xs); color: var(--p-color-ink-3); }
</style>
