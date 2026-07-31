<?php
/**
 * List View — table of records
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'columns' => [],
    'rows'    => [],
    'actions' => ['edit', 'delete'],
    'selectable' => true,
    'endpoint' => '',
]);

$cols = $args['columns'];
?>
<div class="p-table-wrap" data-component="list-view" data-endpoint="<?php echo esc_attr($args['endpoint']); ?>">
    <table class="p-table">
        <thead>
            <tr>
                <?php if ($args['selectable']): ?>
                    <th style="width: 36px;">
                        <label class="p-check">
                            <input type="checkbox" data-bulk-select-all aria-label="<?php esc_attr_e('انتخاب همه', 'parsyar'); ?>" />
                        </label>
                    </th>
                <?php endif; ?>
                <?php foreach ($cols as $col): ?>
                    <th style="<?php echo isset($col['width']) ? 'width: ' . esc_attr($col['width']) . ';' : ''; ?>">
                        <?php echo esc_html($col['label']); ?>
                    </th>
                <?php endforeach; ?>
                <?php if (!empty($args['actions'])): ?>
                    <th style="width: 80px; text-align: end;"><?php esc_html_e('عملیات', 'parsyar'); ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody class="p-stagger">
            <?php if (empty($args['rows'])): ?>
                <tr>
                    <td colspan="<?php echo count($cols) + ($args['selectable'] ? 1 : 0) + (!empty($args['actions']) ? 1 : 0); ?>">
                        <div class="p-empty" style="padding: var(--p-s-6) var(--p-s-3);">
                            <p class="p-empty__msg"><?php esc_html_e('هیچ رکوردی برای نمایش وجود ندارد.', 'parsyar'); ?></p>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($args['rows'] as $row): ?>
                    <tr data-id="<?php echo esc_attr((string) ($row['id'] ?? '')); ?>">
                        <?php if ($args['selectable']): ?>
                            <td>
                                <label class="p-check">
                                    <input type="checkbox" data-bulk-select value="<?php echo esc_attr((string) ($row['id'] ?? '')); ?>" />
                                </label>
                            </td>
                        <?php endif; ?>
                        <?php foreach ($cols as $col):
                            $key = $col['key'] ?? '';
                            $val = $row[$key] ?? '';
                            $align = isset($col['align']) ? 'text-align: ' . esc_attr($col['align']) . ';' : '';
                        ?>
                            <td style="<?php echo esc_attr($align); ?>">
                                <?php if (isset($col['render']) && is_callable($col['render'])): ?>
                                    <?php echo $col['render']($val, $row); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php elseif (is_array($val) || is_object($val)): ?>
                                    <span class="p-mono p-muted"><?php echo esc_html(wp_json_encode($val)); ?></span>
                                <?php else: ?>
                                    <?php echo esc_html((string) $val); ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if (!empty($args['actions'])): ?>
                            <td>
                                <div class="p-table__actions p-cluster p-cluster--end">
                                    <?php if (in_array('view', $args['actions'], true)): ?>
                                        <button type="button" class="p-btn p-btn--ghost p-btn--icon p-btn--sm" data-action="view" aria-label="<?php esc_attr_e('مشاهده', 'parsyar'); ?>">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (in_array('edit', $args['actions'], true)): ?>
                                        <button type="button" class="p-btn p-btn--ghost p-btn--icon p-btn--sm" data-action="edit" aria-label="<?php esc_attr_e('ویرایش', 'parsyar'); ?>">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                        </button>
                                    <?php endif; ?>
                                    <?php if (in_array('delete', $args['actions'], true)): ?>
                                        <button type="button" class="p-btn p-btn--ghost p-btn--icon p-btn--sm" data-action="delete" aria-label="<?php esc_attr_e('حذف', 'parsyar'); ?>">
                                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M5 6V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
