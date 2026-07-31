<?php
/**
 * Admin page for Mobile App (React Native) — v1.8.0.
 *
 * @package Enterprise\Admin
 */

declare(strict_types=1);

namespace Enterprise\Admin;

defined('ABSPATH') || exit;

use Enterprise\Modules\Mobile\Device;
use Enterprise\Modules\Mobile\MobileModule;

final class MobilePage
{
    public static function render(): void
    {
        if (!current_user_can('manage_enterprise')) {
            wp_die(esc_html__('دسترسی غیرمجاز.', 'enterprise-core'));
        }

        global $wpdb;
        $devices_ios     = self::safeCount('mobile_devices', "platform = 'ios'");
        $devices_android = self::safeCount('mobile_devices', "platform = 'android'");
        $devices_active  = self::safeCount('mobile_devices', 'is_active = 1');
        $devices_total   = self::safeCount('mobile_devices', '1=1');

        $min_version = (string) get_option(MobileModule::OPTION_VERSION_MIN, '1.0.0');
        $has_fcm  = (string) get_option(MobileModule::OPTION_FCM_KEY) !== '';
        $has_apns = (string) get_option(MobileModule::OPTION_APNS_KEYID) !== '';
        $apns_team = (string) get_option(MobileModule::OPTION_APNS_TEAM, '');
        $apns_bundle = (string) get_option(MobileModule::OPTION_APNS_BUNDLE, '');

        $maintenance = (bool) get_option('parsyar_maintenance_mode', false);
        $rest_url = rest_url('enterprise/v1/mobile/');
        ?>
        <div class="wrap" dir="rtl">
            <h1 class="wp-heading-inline">اپ موبایل (React Native)</h1>
            <p class="description">فاز ۱.۸.۰ · iOS + Android · Magic Link · JWT · Biometric · Push (FCM/APNs)</p>
            <hr class="wp-header-end" />

            <div class="card" style="max-width:none;padding:0;">
                <h2 class="title" style="margin:16px 20px 8px;">دستگاه‌های فعال</h2>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;padding:0 20px 20px;">
                    <div class="stats-card"><div class="num"><?php echo (int) $devices_total; ?></div><div class="lbl">کل deviceها</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $devices_active; ?></div><div class="lbl">Active</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $devices_ios; ?></div><div class="lbl">iOS</div></div>
                    <div class="stats-card"><div class="num"><?php echo (int) $devices_android; ?></div><div class="lbl">Android</div></div>
                </div>
            </div>

            <div class="card" style="max-width:none;padding:0;margin-top:16px;">
                <h2 class="title" style="margin:16px 20px 8px;">پیکربندی</h2>
                <form method="post" action="options.php">
                    <?php settings_fields('parsyar_mobile_group'); ?>
                    <table class="form-table" dir="rtl">
                        <tr>
                            <th scope="row"><label for="parsyar_mobile_min_app_version">حداقل نسخه اپ</label></th>
                            <td><input name="parsyar_mobile_min_app_version" id="parsyar_mobile_min_app_version" type="text" class="regular-text ltr" value="<?php echo esc_attr($min_version); ?>" placeholder="1.0.0" />
                                <p class="description">اگر device نسخه‌ای پایین‌تر داشته باشد، API /mobile/info پاسخ maintenance می‌دهد.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="parsyar_mobile_fcm_server_key">FCM Server Key</label></th>
                            <td><input name="parsyar_mobile_fcm_server_key" id="parsyar_mobile_fcm_server_key" type="text" class="regular-text ltr" value="<?php echo esc_attr((string) get_option(MobileModule::OPTION_FCM_KEY, '')); ?>" placeholder="AAAA..." />
                                <p class="description">برای ارسال Push به Android. دریافت از Firebase Console.</p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="parsyar_mobile_apns_key_id">APNs Key ID</label></th>
                            <td><input name="parsyar_mobile_apns_key_id" id="parsyar_mobile_apns_key_id" type="text" class="regular-text ltr" value="<?php echo esc_attr((string) get_option(MobileModule::OPTION_APNS_KEYID, '')); ?>" placeholder="ABC123XYZ" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="parsyar_mobile_apns_team_id">APNs Team ID</label></th>
                            <td><input name="parsyar_mobile_apns_team_id" id="parsyar_mobile_apns_team_id" type="text" class="regular-text ltr" value="<?php echo esc_attr($apns_team); ?>" placeholder="DEADBEEF12" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="parsyar_mobile_apns_bundle_id">APNs Bundle ID</label></th>
                            <td><input name="parsyar_mobile_apns_bundle_id" id="parsyar_mobile_apns_bundle_id" type="text" class="regular-text ltr" value="<?php echo esc_attr($apns_bundle); ?>" placeholder="ir.parsyar.app" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="parsyar_maintenance_mode">حالت تعمیر و نگهداری</label></th>
                            <td><label><input type="checkbox" name="parsyar_maintenance_mode" id="parsyar_maintenance_mode" value="1" <?php checked($maintenance); ?> /> غیرفعال کردن API موبایل</label></td>
                        </tr>
                    </table>
                    <?php submit_button('ذخیره پیکربندی'); ?>
                </form>
            </div>

            <div class="card" style="max-width:none;padding:0;margin-top:16px;">
                <h2 class="title" style="margin:16px 20px 8px;">Endpointهای REST</h2>
                <table class="widefat" style="border:none;">
                    <tbody>
                        <tr><td style="width:280px;"><code>GET /mobile/info</code></td><td>وضعیت اپ + حداقل نسخه + feature flags</td></tr>
                        <tr><td><code>POST /mobile/devices/register</code></td><td>ثبت FCM/APNs token (نیازمند JWT)</td></tr>
                        <tr><td><code>POST /mobile/devices/heartbeat</code></td><td>اعلام فعال بودن device</td></tr>
                        <tr><td><code>DELETE /mobile/devices/delete</code></td><td>حذف device هنگام logout</td></tr>
                        <tr><td><code>POST /mobile/notifications/test</code></td><td>تست ارسال Push (admin)</td></tr>
                    </tbody>
                </table>
                <p style="padding:0 20px 20px;">پایهٔ REST: <code><?php echo esc_html($rest_url); ?></code></p>
            </div>
        </div>
        <?php
    }

    /**
     * ثبت option ها در settings API.
     */
    public static function registerSettings(): void
    {
        register_setting('parsyar_mobile_group', MobileModule::OPTION_VERSION_MIN);
        register_setting('parsyar_mobile_group', MobileModule::OPTION_FCM_KEY);
        register_setting('parsyar_mobile_group', MobileModule::OPTION_APNS_KEYID);
        register_setting('parsyar_mobile_group', MobileModule::OPTION_APNS_TEAM);
        register_setting('parsyar_mobile_group', MobileModule::OPTION_APNS_BUNDLE);
        register_setting('parsyar_mobile_group', 'parsyar_maintenance_mode', [
            'type' => 'boolean',
            'sanitize_callback' => static function ($v) { return $v ? 1 : 0; },
        ]);
    }

    private static function safeCount(string $table, string $where): int
    {
        global $wpdb;
        $t = $wpdb->prefix . 'parsyar_' . $table;
        $exists = (int) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
        if ($exists === 0) {
            return 0;
        }
        return (int) ($wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE {$where}") ?: 0);
    }
}
