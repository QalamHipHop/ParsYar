<?php
/**
 * Kanban View — drag-drop pipeline
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$args = wp_parse_args($args ?? [], [
    'columns' => [],
    'endpoint' => '',
    'card_title_key' => 'title',
    'card_meta_keys' => [],
]);

$cols = $args['columns'];
?>
<div class="p-kanban" data-component="kanban-view" data-endpoint="<?php echo esc_attr($args['endpoint']); ?>" role="list">
    <?php foreach ($cols as $col):
        $cards = $col['cards'] ?? [];
        $total = array_sum(array_column($cards, 'amount'));
    ?>
        <div class="p-kanban__col" data-stage-id="<?php echo esc_attr((string) ($col['id'] ?? '')); ?>" data-wip-limit="<?php echo esc_attr((string) ($col['wip'] ?? 0)); ?>" role="listitem">
            <header class="p-kanban__col-header">
                <h3 class="p-kanban__col-title">
                    <?php echo esc_html($col['name']); ?>
                    <span class="p-kanban__col-count"><?php echo count($cards); ?></span>
                </h3>
                <span class="p-kanban__col-total p-num"><?php echo esc_html(parsyar_format_money($total)); ?></span>
            </header>
            <div class="p-kanban__col-body" data-kanban-dropzone>
                <?php foreach ($cards as $card): ?>
                    <article class="p-kanban__card" draggable="true" data-card-id="<?php echo esc_attr((string) ($card['id'] ?? '')); ?>" data-kanban-draggable>
                        <h4 class="p-kanban__card-title"><?php echo esc_html((string) ($card[$args['card_title_key']] ?? '')); ?></h4>
                        <?php if (!empty($card['subtitle'])): ?>
                            <p class="p-muted" style="margin: 0; font-size: var(--p-fs-xs);"><?php echo esc_html((string) $card['subtitle']); ?></p>
                        <?php endif; ?>
                        <div class="p-kanban__card-meta">
                            <?php if (!empty($card['amount'])): ?>
                                <span class="p-kanban__card-amount"><?php echo esc_html(parsyar_format_money($card['amount'])); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($card['assignee'])): ?>
                                <span class="p-avatar p-avatar--xs" aria-hidden="true"><?php echo esc_html(mb_substr((string) $card['assignee'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
