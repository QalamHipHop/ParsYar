<?php
/**
 * Template Name: ParsYar Customer Portal
 * Description: Mounts the Customer Portal PWA (dist/index.html) inside WordPress.
 *              All REST calls are routed to /wp-json/enterprise/v1/portal/* automatically.
 *
 * @package Enterprise
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

get_header();

$dist_html = __DIR__ . '/portal-pwa/dist/index.html';
if (!file_exists($dist_html)) {
    echo '<div style="max-width:640px;margin:80px auto;padding:32px;font-family:Vazirmatn,system-ui,sans-serif;text-align:center;background:#fef3c7;border:1px solid #f59e0b;border-radius:12px;color:#92400e;">';
    echo '<h1 style="margin:0 0 12px;font-size:20px;">هشدار پورتال هنوز بیلد نشده است</h1>';
    echo '<p style="margin:0 0 8px;line-height:1.8;">برای فعال‌سازی پورتال مشتریان، ابتدا در مسیر <code>wp-content/themes/enterprise-theme/portal-pwa</code> دستور زیر را اجرا کنید:</p>';
    echo '<pre style="background:#fff;padding:12px;border-radius:8px;text-align:left;direction:ltr;font-size:13px;">npm install && npm run build</pre>';
    echo '<p style="margin:12px 0 0;font-size:13px;">سپس این صفحه را رفرش کنید.</p>';
    echo '</div>';
    get_footer();
    return;
}

$html = file_get_contents($dist_html);
if ($html === false) {
    echo '<p>خطا در بارگذاری پورتال.</p>';
    get_footer();
    return;
}

// Rewrite asset paths: /assets/foo.js → /wp-content/themes/enterprise-theme/portal-pwa/dist/assets/foo.js
$theme_uri = get_stylesheet_directory_uri() . '/portal-pwa/dist';
$html = preg_replace(
    '#(src|href)="/(?!/)([^"]+)"#i',
    '$1="' . $theme_uri . '/$2"',
    $html
);
// In case assets are already root-relative without leading slash
$html = str_replace('"/assets/', '"' . $theme_uri . '/assets/', $html);
$html = str_replace('"/favicon.svg"', '"' . $theme_uri . '/favicon.svg"', $html);
$html = str_replace('"/manifest.webmanifest"', '"' . $theme_uri . '/manifest.webmanifest"', $html);

// Inject ParsYar config for the SPA (base URL, nonce, locale, VAPID)
$vapid = function_exists('Enterprise\Modules\Portal\AuthService')
    ? \Enterprise\Modules\Portal\AuthService::vapidKeys()
    : ['public' => '', 'private' => ''];
$company_name = get_option('parsyar_company_name', get_bloginfo('name'));

$config = [
    'restUrl'       => esc_url_raw(rest_url('enterprise/v1/portal/')),
    'nonce'         => wp_create_nonce('wp_rest'),
    'siteName'      => get_bloginfo('name'),
    'locale'        => get_locale(),
    'homeUrl'       => esc_url_raw(home_url('/')),
    'vapidPublicKey' => $vapid['public'] ?? '',
    'company'       => [
        'name'         => $company_name,
        'supportEmail' => get_option('parsyar_support_email', get_option('admin_email')),
        'supportPhone' => get_option('parsyar_support_phone', ''),
    ],
    'user' => is_user_logged_in() ? [
        'id'   => get_current_user_id(),
        'name' => wp_get_current_user()->display_name,
    ] : null,
];
$inject = '<script>window.parsyarPortalConfig = ' . wp_json_encode($config, JSON_UNESCAPED_UNICODE) . ';</script>';

$html = preg_replace('/<head([^>]*)>/i', '<head$1>' . $inject, $html, 1);

echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

get_footer();
