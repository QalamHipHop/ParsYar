<?php
/**
 * Detail View — single record side panel
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'record' => [],
    'fields' => [],
    'tabs'   => ['activity', 'notes', 'emails', 'tasks', 'files'],
]);

$rec = $args['record'];
?>
<div class="p-detail" data-component="detail-view">
    <header class="p-detail__head">
        <div class="p-cluster" style="margin-block-end: var(--p-s-3);">
            <span class="p-avatar p-avatar--lg"><?php echo esc_html(mb_substr((string) ($rec['title'] ?? '?'), 0, 1)); ?></span>
            <div>
                <h2 style="margin: 0;"><?php echo esc_html((string) ($rec['title'] ?? '')); ?></h2>
                <p class="p-muted" style="margin: 0; font-size: var(--p-fs-sm);"><?php echo esc_html((string) ($rec['subtitle'] ?? '')); ?></p>
            </div>
        </div>
        <div class="p-cluster">
            <button type="button" class="p-btn p-btn--secondary p-btn--sm"><?php esc_html_e('ویرایش', 'parsyar'); ?></button>
            <button type="button" class="p-btn p-btn--primary p-btn--sm"><?php esc_html_e('اقدام', 'parsyar'); ?></button>
        </div>
    </header>

    <?php if (!empty($args['fields'])): ?>
        <dl class="p-detail__fields">
            <?php foreach ($args['fields'] as $f): ?>
                <div class="p-detail__field">
                    <dt><?php echo esc_html((string) ($f['label'] ?? '')); ?></dt>
                    <dd><?php echo esc_html((string) ($f['value'] ?? '—')); ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    <?php endif; ?>

    <div class="p-tabs" data-component="tabs">
        <ul class="p-tabs__list" role="tablist">
            <?php
            $tab_labels = [
                'activity' => __('فعالیت', 'parsyar'),
                'notes'    => __('یادداشت', 'parsyar'),
                'emails'   => __('ایمیل', 'parsyar'),
                'tasks'    => __('وظیفه', 'parsyar'),
                'files'    => __('فایل', 'parsyar'),
            ];
            foreach ($args['tabs'] as $i => $t):
                $sel = 0 === $i ? 'true' : 'false';
            ?>
                <li role="presentation">
                    <button type="button"
                            class="p-tab"
                            role="tab"
                            aria-selected="<?php echo esc_attr($sel); ?>"
                            id="tab-<?php echo esc_attr($t); ?>"
                            aria-controls="panel-<?php echo esc_attr($t); ?>">
                        <?php echo esc_html($tab_labels[$t] ?? $t); ?>
                    </button>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php foreach ($args['tabs'] as $i => $t): $hid = 0 !== $i; ?>
            <div class="p-tabs__panel"
                 role="tabpanel"
                 id="panel-<?php echo esc_attr($t); ?>"
                 aria-labelledby="tab-<?php echo esc_attr($t); ?>"
                 <?php echo $hid ? 'hidden' : ''; ?>>
                <p class="p-muted"><?php
                    /* translators: %s: tab name */
                    printf(esc_html__('محتوای تب %s در اینجا نمایش داده می‌شود.', 'parsyar'), esc_html($tab_labels[$t] ?? $t));
                ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
.p-detail__head {
    padding-block-end: var(--p-s-4);
    border-bottom: 1px solid var(--p-color-line);
    margin-block-end: var(--p-s-4);
}
.p-detail__fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--p-s-3);
    margin: 0 0 var(--p-s-4);
}
.p-detail__field dt {
    font-size: var(--p-fs-2xs);
    text-transform: uppercase;
    letter-spacing: var(--p-ls-xwide);
    color: var(--p-color-ink-4);
    margin: 0 0 2px;
}
.p-detail__field dd {
    margin: 0;
    font-size: var(--p-fs-sm);
    color: var(--p-color-ink);
}
</style>
