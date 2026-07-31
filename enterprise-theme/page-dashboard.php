<?php
/**
 * Template Name: داشبورد
 *
 * The dashboard wrapper — loads the React app inside the in-theme layout.
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

// Optional: redirect non-logged-in users to wp-login.php
if (!is_user_logged_in() && !defined('PARSYAR_ALLOW_PUBLIC_DASH')) {
    auth_redirect();
}

get_header();
?>
<div class="p-page p-page--full p-page-enter">
    <?php while (have_posts()): the_post(); ?>

        <?php if (get_the_content()): ?>
            <div class="p-container p-container--narrow" style="margin-block-end: var(--p-s-5);">
                <?php the_content(); ?>
            </div>
        <?php endif; ?>

        <div id="parsyar-dashboard-root"
             data-page="<?php echo esc_attr((string) ($_GET['p'] ?? 'home')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>"
             data-pillar="<?php echo esc_attr((string) ($_GET['pillar'] ?? '')); // phpcs:ignore ?>"
             data-rest-url="<?php echo esc_url_raw(rest_url('parsyar/v1')); ?>"
             data-nonce="<?php echo esc_attr(wp_create_nonce('wp_rest')); ?>">

            <!-- Server-rendered fallback shell (progressive enhancement) -->
            <div class="p-grid p-grid--4 p-stagger" style="margin-block-end: var(--p-s-5);">
                <div class="p-card">
                    <p class="p-mono p-muted" style="font-size: var(--p-fs-2xs); text-transform: uppercase; letter-spacing: var(--p-ls-xwide);"><?php esc_html_e('مخاطبین فعال', 'parsyar'); ?></p>
                    <p class="p-num" style="font-size: var(--p-fs-2xl); font-weight: var(--p-fw-bold); margin: 0;">۱٬۲۴۸</p>
                </div>
                <div class="p-card">
                    <p class="p-mono p-muted" style="font-size: var(--p-fs-2xs); text-transform: uppercase; letter-spacing: var(--p-ls-xwide);"><?php esc_html_e('معاملات جاری', 'parsyar'); ?></p>
                    <p class="p-num" style="font-size: var(--p-fs-2xl); font-weight: var(--p-fw-bold); margin: 0;">۸۷</p>
                </div>
                <div class="p-card">
                    <p class="p-mono p-muted" style="font-size: var(--p-fs-2xs); text-transform: uppercase; letter-spacing: var(--p-ls-xwide);"><?php esc_html_e('درآمد ماه', 'parsyar'); ?></p>
                    <p class="p-num" style="font-size: var(--p-fs-2xl); font-weight: var(--p-fw-bold); margin: 0;"><?php echo esc_html(parsyar_format_money(124500000, 'IRT')); ?></p>
                </div>
                <div class="p-card">
                    <p class="p-mono p-muted" style="font-size: var(--p-fs-2xs); text-transform: uppercase; letter-spacing: var(--p-ls-xwide);"><?php esc_html_e('تیکت‌های باز', 'parsyar'); ?></p>
                    <p class="p-num" style="font-size: var(--p-fs-2xl); font-weight: var(--p-fw-bold); margin: 0;">۴</p>
                </div>
            </div>

            <div class="p-grid p-grid--3">
                <div class="p-card" style="grid-column: span 2;">
                    <header class="p-card__header">
                        <h2 class="p-card__title"><?php esc_html_e('خط فروش', 'parsyar'); ?></h2>
                        <a href="#" class="p-btn p-btn--ghost p-btn--sm"><?php esc_html_e('مشاهده همه', 'parsyar'); ?></a>
                    </header>
                    <canvas id="parsyar-revenue-chart" height="80" aria-label="<?php esc_attr_e('نمودار درآمد', 'parsyar'); ?>"></canvas>
                </div>
                <div class="p-card">
                    <header class="p-card__header">
                        <h2 class="p-card__title"><?php esc_html_e('فعالیت‌های اخیر', 'parsyar'); ?></h2>
                    </header>
                    <div class="p-stack p-stack--sm">
                        <?php
                        $activities = [
                            ['name' => 'علی محمدی',     'action' => __('یک مخاطب جدید اضافه کرد', 'parsyar'), 'time' => __('۲ دقیقه پیش', 'parsyar')],
                            ['name' => 'مریم رضایی',    'action' => __('یک معامله را برد', 'parsyar'),       'time' => __('۱ ساعت پیش', 'parsyar')],
                            ['name' => 'حسین کریمی',    'action' => __('یک فاکتور صادر کرد', 'parsyar'),     'time' => __('۳ ساعت پیش', 'parsyar')],
                            ['name' => 'زهرا احمدی',    'action' => __('یک تیکت پاسخ داد', 'parsyar'),       'time' => __('دیروز', 'parsyar')],
                        ];
                        foreach ($activities as $a): ?>
                            <div class="p-cluster p-cluster--baseline" style="gap: var(--p-s-3);">
                                <span class="p-avatar p-avatar--sm" aria-hidden="true"><?php echo esc_html(mb_substr($a['name'], 0, 1)); ?></span>
                                <div style="flex: 1; min-width: 0;">
                                    <p style="margin: 0; font-size: var(--p-fs-sm);"><strong><?php echo esc_html($a['name']); ?></strong> <?php echo esc_html($a['action']); ?></p>
                                    <p class="p-muted" style="margin: 0; font-size: var(--p-fs-xs);"><?php echo esc_html($a['time']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    <?php endwhile; ?>
</div>
<?php
get_footer();
