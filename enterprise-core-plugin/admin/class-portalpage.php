<?php
declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

/**
 * Admin page for the Customer Portal (PWA) — v1.7.0.
 * Shows health, configuration, and management links.
 */
final class PortalPage
{
    public static function render(): void
    {
        if (!current_user_can('manage_enterprise')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'enterprise-core'));
        }

        global $wpdb;
        $tokens   = (int) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'parsyar_portal_tokens')) === 0 ? 0 : (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}parsyar_portal_tokens");
        $sessions = (int) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->prefix . 'parsyar_portal_sessions')) === 0 ? 0 : (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}parsyar_portal_sessions");
        $tickets  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}parsyar_portal_tickets");
        $quotes   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}parsyar_quote_requests");
        $pushes   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}parsyar_push_subscriptions");
        $events   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}parsyar_portal_events");

        $vapid_pub = \Enterprise\Modules\Portal\AuthService::vapidPublicKey();
        $has_secret = (bool) get_option('enterprise_portal_jwt_secret');

        $portal_page_id = (int) get_option('enterprise_portal_page_id');
        $portal_url = $portal_page_id ? get_permalink($portal_page_id) : home_url('/portal');
        ?>
        <div class="wrap" dir="rtl">
            <h1 class="wp-heading-inline">پورتال مشتریان (PWA)</h1>
            <p class="description">فاز ۱.۷.۰ · Progressive Web App · Magic Link · JWT · آفلاین‌محور · Push</p>
            <hr class="wp-header-end" />

            <div class="card" style="max-width:none;padding:0;">
                <h2 class="title" style="margin:16px 20px 8px;">آمار لحظه‌ای</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;padding:0 20px 20px;">
                    <div class="stats-card"><div class="num"><?php echo (int) $tokens; ?></div><div class="lbl">توکن‌های Magic Link</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $sessions; ?></div><div class="lbl">سشن‌های فعال</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $tickets; ?></div><div class="lbl">تیکت‌ها</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $quotes; ?></div><div class="lbl">درخواست استعلام</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $pushes; ?></div><div class="lbl">اشتراک Push</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $events; ?></div><div class="lbl">رویدادهای کلاینت</div></div>
                </div>
            </div>

            <div class="card" style="max-width:none;padding:0;margin-top:16px;">
                <h2 class="title" style="margin:16px 20px 8px;">پیکربندی</h2>
                <table class="widefat" style="border:none;">
                    <tbody>
                        <tr><td style="width:240px;"><strong>آدرس پورتال</strong></td><td><a href="<?php echo esc_url($portal_url); ?>" target="_blank"><?php echo esc_html($portal_url); ?></a></td></tr>
                        <tr><td><strong>JWT Secret</strong></td><td><?php echo $has_secret ? ' تنظیم شده' : ' تنظیم نشده (در اولین درخواست ساخته می‌شود)'; ?></td></tr>
                        <tr><td><strong>VAPID Public Key</strong></td><td><code style="font-size:11px;word-break:break-all;direction:ltr;"><?php echo esc_html($vapid_pub); ?></code></td></tr>
                        <tr><td><strong>REST namespace</strong></td><td><code><?php echo esc_html(rest_url('enterprise/v1/portal/')); ?></code></td></tr>
                    </tbody>
                </table>
            </div>

            <div class="card" style="max-width:none;padding:0;margin-top:16px;">
                <h2 class="title" style="margin:16px 20px 8px;">مستندات سریع</h2>
                <div style="padding:0 20px 20px;line-height:1.9;">
                    <p><strong>Endpointهای اصلی:</strong></p>
                    <ul style="list-style:disc;padding-right:24px;">
                        <li><code>POST /portal/auth/magic-link</code> · درخواست لینک ورود</li>
                        <li><code>GET  /portal/auth/verify?token=...</code> · تأیید و صدور JWT</li>
                        <li><code>POST /portal/auth/refresh</code> · تازه‌سازی توکن</li>
                        <li><code>POST /portal/auth/logout</code> · خروج و ابطال سشن</li>
                        <li><code>GET  /portal/me</code> · پروفایل مخاطب لاگین‌شده</li>
                        <li><code>GET  /portal/invoices</code> · لیست فاکتورها</li>
                        <li><code>GET  /portal/orders</code> · لیست سفارش‌ها</li>
                        <li><code>GET  /portal/payments</code> · لیست پرداخت‌ها</li>
                        <li><code>GET/POST /portal/tickets</code> · تیکت‌ها</li>
                        <li><code>POST /portal/quotes/request</code> · درخواست استعلام قیمت</li>
                        <li><code>POST/DELETE /portal/push/subscribe</code> · مدیریت Push</li>
                        <li><code>GET  /portal/auth/vapid-public-key</code> · کلید عمومی VAPID</li>
                    </ul>
                    <p style="margin-top:12px;"><strong>Build frontend:</strong> <code style="direction:ltr;display:inline-block;">cd wp-content/themes/enterprise-theme/portal-pwa && npm install && npm run build</code></p>
                </div>
            </div>
        </div>
        <style>
        .stats-card { background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px; text-align:center; }
        .stats-card .num { font-size:28px; font-weight:700; color:#0f172a; line-height:1; }
        .stats-card .lbl { font-size:12px; color:#64748b; margin-top:6px; }
        </style>
        <?php
    }
}
