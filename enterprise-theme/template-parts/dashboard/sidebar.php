<?php
/**
 * ParsYar — Dashboard Sidebar (Left Rail)
 * 19 pillars + groups
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

// Pillars definition (grouped for the sidebar)
$pillars = [
    'main' => [
        ['id' => 'dashboard',  'icon' => 'home',          'label' => __('خانه', 'parsyar'),         'url' => home_url('/app')],
        ['id' => 'contacts',   'icon' => 'users',         'label' => __('مخاطبین', 'parsyar'),      'url' => home_url('/app/contacts'),  'count' => 1248],
        ['id' => 'deals',      'icon' => 'briefcase',     'label' => __('معاملات', 'parsyar'),      'url' => home_url('/app/deals'),     'count' => 87],
        ['id' => 'leads',      'icon' => 'target',        'label' => __('سرنخ‌ها', 'parsyar'),      'url' => home_url('/app/leads'),     'count' => 213],
        ['id' => 'activities', 'icon' => 'zap',           'label' => __('فعالیت‌ها', 'parsyar'),     'url' => home_url('/app/activities')],
        ['id' => 'calendar',   'icon' => 'calendar',      'label' => __('تقویم', 'parsyar'),        'url' => home_url('/app/calendar')],
        ['id' => 'inbox',      'icon' => 'inbox',         'label' => __('صندوق', 'parsyar'),        'url' => home_url('/app/inbox'),     'count' => 12],
    ],
    'business' => [
        ['id' => 'sales',       'icon' => 'shopping-cart', 'label' => __('فروش', 'parsyar'),          'url' => home_url('/app/sales')],
        ['id' => 'inventory',   'icon' => 'package',       'label' => __('انبار', 'parsyar'),         'url' => home_url('/app/inventory')],
        ['id' => 'marketing',   'icon' => 'megaphone',     'label' => __('بازاریابی', 'parsyar'),     'url' => home_url('/app/marketing')],
        ['id' => 'automation',  'icon' => 'workflow',      'label' => __('اتوماسیون', 'parsyar'),     'url' => home_url('/app/automation')],
        ['id' => 'reports',     'icon' => 'bar-chart',     'label' => __('گزارش‌ها', 'parsyar'),      'url' => home_url('/app/reports')],
    ],
    'ops' => [
        ['id' => 'hr',         'icon' => 'user-check',    'label' => __('منابع انسانی', 'parsyar'),  'url' => home_url('/app/hr')],
        ['id' => 'accounting', 'icon' => 'calculator',    'label' => __('حسابداری', 'parsyar'),      'url' => home_url('/app/accounting')],
        ['id' => 'support',    'icon' => 'life-buoy',     'label' => __('پشتیبانی', 'parsyar'),      'url' => home_url('/app/support'),  'count' => 4],
        ['id' => 'projects',   'icon' => 'folder',        'label' => __('پروژه‌ها', 'parsyar'),       'url' => home_url('/app/projects')],
        ['id' => 'documents',  'icon' => 'file-text',     'label' => __('مستندات', 'parsyar'),       'url' => home_url('/app/documents')],
    ],
    'system' => [
        ['id' => 'integrations', 'icon' => 'plug',        'label' => __('یکپارچگی', 'parsyar'),      'url' => home_url('/app/integrations')],
        ['id' => 'settings',     'icon' => 'settings',    'label' => __('تنظیمات', 'parsyar'),       'url' => home_url('/app/settings')],
    ],
];

$current_url = trailingslashit((string) ($_SERVER['REQUEST_URI'] ?? ''));

// Icon library (inline SVGs, 1.5px stroke, monochrome)
$icons = [
    'home'          => '<path d="M3 9.5L12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1z"/>',
    'users'         => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'briefcase'     => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
    'target'        => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    'zap'           => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
    'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
    'inbox'         => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    'shopping-cart' => '<circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>',
    'package'       => '<path d="M16.5 9.4l-9-5.19"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>',
    'megaphone'     => '<path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
    'workflow'      => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><path d="M10 7h4M10 17h4M7 10v4M17 10v4"/>',
    'bar-chart'     => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6"  y1="20" x2="6"  y2="16"/>',
    'user-check'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/>',
    'calculator'    => '<rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><circle cx="8" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="16" cy="12" r="1"/><circle cx="8" cy="16" r="1"/><circle cx="12" cy="16" r="1"/><circle cx="16" cy="16" r="1"/>',
    'life-buoy'     => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/><line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/><line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/><line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/><line x1="4.93" y1="19.07" x2="9.17" y2="14.83"/>',
    'folder'        => '<path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>',
    'file-text'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
    'plug'          => '<path d="M9 2v6"/><path d="M15 2v6"/><path d="M5 8h14l-1 8a4 4 0 0 1-4 4h-4a4 4 0 0 1-4-4z"/><path d="M12 18v4"/>',
    'settings'      => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    'chevron-right' => '<polyline points="9 18 15 12 9 6"/>',
    'chevron-left'  => '<polyline points="15 18 9 12 15 6"/>',
];

$group_labels = [
    'main'     => __('اصلی', 'parsyar'),
    'business' => __('کسب‌وکار', 'parsyar'),
    'ops'      => __('عملیات', 'parsyar'),
    'system'   => __('سامانه', 'parsyar'),
];
?>
<aside class="p-rail" id="parsyar-rail" aria-label="<?php esc_attr_e('منوی اصلی', 'parsyar'); ?>">
    <div class="p-rail__brand">
        <a href="<?php echo esc_url(home_url('/app')); ?>" class="p-rail__brand-mark" aria-label="ParsYar">P</a>
        <div class="p-rail__brand-text">
            <span class="p-rail__brand-name">ParsYar</span>
            <span class="p-rail__brand-sub"><?php esc_html_e('پلتفرم سازمانی', 'parsyar'); ?></span>
        </div>
    </div>

    <nav class="p-rail__nav" aria-label="<?php esc_attr_e('پیمایش', 'parsyar'); ?>">
        <?php foreach ($pillars as $group_id => $items): ?>
            <div class="p-rail__group">
                <h4 class="p-rail__group-title"><?php echo esc_html($group_labels[$group_id] ?? ''); ?></h4>
                <ul class="p-rail__list">
                    <?php foreach ($items as $item):
                        $is_active = (false !== strpos($current_url, '/' . $item['id']));
                        if ('dashboard' === $item['id'] && (trailingslashit(home_url('/app')) === $current_url || '/app' === $current_url || '/app/' === $current_url)) {
                            $is_active = true;
                        }
                    ?>
                        <li>
                            <a href="<?php echo esc_url($item['url']); ?>"
                               class="p-rail__item<?php echo $is_active ? ' is-active' : ''; ?>"
                               data-pillar="<?php echo esc_attr($item['id']); ?>"
                               <?php echo $is_active ? 'aria-current="page"' : ''; ?>>
                                <svg class="p-rail__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <?php echo $icons[$item['icon']] ?? ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG path ?>
                                </svg>
                                <span class="p-rail__item-label"><?php echo esc_html($item['label']); ?></span>
                                <?php if (!empty($item['count'])): ?>
                                    <span class="p-rail__item-count"><?php echo esc_html(number_format_i18n((int) $item['count'])); ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </nav>

    <div class="p-rail__footer">
        <button type="button" class="p-btn p-btn--ghost p-btn--icon" data-action="toggle-rail" aria-label="<?php esc_attr_e('جمع کردن منو', 'parsyar'); ?>">
            <svg class="p-btn__icon p-icon-mirror" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $icons['chevron-left']; ?></svg>
        </button>
        <span class="p-rail__footer-text p-muted"><?php esc_html_e('ParsYar v' . PARSYAR_THEME_VERSION); ?></span>
    </div>
</aside>
