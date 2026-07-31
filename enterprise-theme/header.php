<?php
/**
 * ParsYar Theme — Header
 *
 * @package ParsYar
 */

declare(strict_types=1);
defined('ABSPATH') || exit;

$is_dashboard = parsyar_is_dashboard();
$is_rtl       = is_rtl();
$lang_dir     = $is_rtl ? 'rtl' : 'ltr';
$lang         = substr((string) get_user_locale(), 0, 2);
?><!doctype html>
<html <?php language_attributes(); ?> dir="<?php echo esc_attr($lang_dir); ?>" data-theme="auto">
<head>
    <meta charset="<?php bloginfo('charset'); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <meta name="theme-color" content="#0A0A0A" media="(prefers-color-scheme: dark)" />
    <meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)" />
    <link rel="profile" href="https://gmpg.org/xfn/11" />
    <link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<?php if ($is_dashboard): ?>
    <div class="p-app<?php echo $is_rtl ? ' p-app--rtl' : ''; ?>" id="parsyar-app">
        <?php get_template_part('template-parts/dashboard/sidebar'); ?>

        <header class="p-topbar" role="banner">
            <button type="button" class="p-btn p-btn--ghost p-btn--icon p-show-mobile" data-action="toggle-rail" aria-label="<?php esc_attr_e('منو', 'parsyar'); ?>">
                <svg class="p-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <div class="p-topbar__search">
                <div class="p-input-group">
                    <span class="p-input-group__icon" aria-hidden="true">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="20" y1="20" x2="16.65" y2="16.65"/></svg>
                    </span>
                    <input type="search" class="p-input p-input--filled" placeholder="<?php esc_attr_e('جستجو در ParsYar... ( ⌘K )', 'parsyar'); ?>" aria-label="<?php esc_attr_e('جستجو', 'parsyar'); ?>" data-cmd-trigger />
                </div>
            </div>

            <div class="p-topbar__actions">
                <button type="button" class="p-btn p-btn--ghost p-btn--icon" data-action="toggle-theme" aria-label="<?php esc_attr_e('تغییر پوسته', 'parsyar'); ?>" title="<?php esc_attr_e('تغییر پوسته', 'parsyar'); ?>">
                    <svg class="p-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>

                <button type="button" class="p-btn p-btn--ghost p-btn--icon" data-action="open-notifications" aria-label="<?php esc_attr_e('اعلان‌ها', 'parsyar'); ?>" title="<?php esc_attr_e('اعلان‌ها', 'parsyar'); ?>">
                    <svg class="p-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                </button>

                <a href="<?php echo esc_url(admin_url('post-new.php')); ?>" class="p-btn p-btn--primary p-btn--sm p-hide-mobile">
                    <svg class="p-btn__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span><?php esc_html_e('ساخت سریع', 'parsyar'); ?></span>
                </a>

                <?php if (is_user_logged_in()): $u = wp_get_current_user(); ?>
                    <div class="p-topbar__user" data-action="open-user-menu" tabindex="0" role="button">
                        <span class="p-avatar p-avatar--sm" aria-hidden="true"><?php echo esc_html(mb_substr($u->display_name, 0, 1)); ?></span>
                        <span class="p-topbar__user-name p-hide-mobile"><?php echo esc_html($u->display_name); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <div class="p-rail-backdrop" data-action="close-rail" aria-hidden="true"></div>

        <main class="p-main" id="parsyar-main" tabindex="-1">
<?php else: ?>
    <a class="p-sr-only" href="#parsyar-main"><?php esc_html_e('پرش به محتوا', 'parsyar'); ?></a>

    <?php if (has_nav_menu('primary')): ?>
        <nav class="p-nav" role="navigation" aria-label="<?php esc_attr_e('منوی اصلی', 'parsyar'); ?>">
            <div class="p-nav__inner">
                <a href="<?php echo esc_url(home_url('/')); ?>" class="p-nav__brand"><?php bloginfo('name'); ?></a>
                <?php
                wp_nav_menu([
                    'theme_location' => 'primary',
                    'container'      => false,
                    'menu_class'     => 'p-nav__menu',
                    'depth'          => 1,
                ]);
                ?>
                <a href="<?php echo esc_url(home_url('/app')); ?>" class="p-btn p-btn--primary p-btn--sm"><?php esc_html_e('ورود به داشبورد', 'parsyar'); ?></a>
            </div>
        </nav>
    <?php endif; ?>

    <main class="p-main" id="parsyar-main" tabindex="-1">
<?php endif; ?>
