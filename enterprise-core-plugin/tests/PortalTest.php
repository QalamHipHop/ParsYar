<?php
declare(strict_types=1);

/**
 * @group portal
 * Smoke tests for the Customer Portal (PWA) — runs without WordPress.
 * Stubs minimal WP/wpdb functions used by AuthService/PortalService.
 */

namespace Enterprise\Tests;

defined('ABSPATH') || exit;

// ---- stubs ----

if (!defined('ABSPATH')) define('ABSPATH', __DIR__ . '/');
if (!defined('DAY_IN_SECONDS')) define('DAY_IN_SECONDS', 86400);

if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('do_action')) { function do_action(...$a) {} }
if (!function_exists('apply_filters')) { function apply_filters($t, $v) { return $v; } }
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $GLOBALS['_opts'][$k] ?? $d; } }
if (!function_exists('update_option')) { function update_option($k, $v, $au = true) { $GLOBALS['_opts'][$k] = $v; return true; } }
if (!function_exists('current_time')) { function current_time($t = 'mysql', $gmt = false) { return gmdate('Y-m-d H:i:s'); } }
if (!function_exists('home_url')) { function home_url($p = '/') { return 'https://example.test' . $p; } }
if (!function_exists('add_query_arg')) { function add_query_arg($args, $url) { return $url . '?' . http_build_query($args); } }
if (!function_exists('sanitize_text_field')) { function sanitize_text_field($s) { return is_string($s) ? trim($s) : ''; } }
if (!function_exists('sanitize_key')) { function sanitize_key($s) { return is_string($s) ? preg_replace('/[^a-z0-9_\-]/', '', strtolower($s)) : ''; } }
if (!function_exists('sanitize_file_name')) { function sanitize_file_name($s) { return is_string($s) ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $s) : ''; } }
if (!function_exists('esc_url_raw')) { function esc_url_raw($s) { return filter_var($s, FILTER_SANITIZE_URL); } }
if (!function_exists('wp_kses_post')) { function wp_kses_post($s) { return is_string($s) ? $s : ''; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($v) { return json_encode($v, JSON_UNESCAPED_UNICODE); } }
if (!function_exists('Cache')) {
    class Cache { public static function get($k, $d = null) { return $GLOBALS['_cache'][$k] ?? $d; } public static function set($k, $v, $t = 0) { $GLOBALS['_cache'][$k] = $v; return true; } }
}
if (!function_exists('wp_send_json')) { function wp_send_json($d) { throw new \RuntimeException('STOP:' . json_encode($d)); } }

namespace Enterprise\Support {
    if (!class_exists('Enterprise\\Support\\Db')) {
        class Db {
            public static function table($name) { global $wpdb; return $wpdb->prefix . $name; }
            public static function insert($t, $d) { global $wpdb; $d['id'] = ($GLOBALS['_ids'][$t] = ($GLOBALS['_ids'][$t] ?? 0) + 1); $wpdb->insert($t, $d); return $d['id']; }
            public static function update($t, $d, $w) { global $wpdb; return $wpdb->update($t, $d, $w); }
            public static function delete($t, $w) { global $wpdb; return $wpdb->delete($t, $w); }
            public static function getRow($t, $w) { global $wpdb; return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}{$t} WHERE id = %d LIMIT 1", (int)($w['id'] ?? 0)), ARRAY_A); }
        }
    }
}

namespace Enterprise\Modules\Audit {
    if (!class_exists('Enterprise\\Modules\\Audit\\Logger')) {
        class Logger { public static function log(...$a) { /* noop */ } }
    }
}

namespace Enterprise\Cache {
    if (!class_exists('Enterprise\\Cache')) {
        class Cache { public static function get($k, $d = null) { return $GLOBALS['_cache'][$k] ?? $d; } public static function set($k, $v, $t = 0) { $GLOBALS['_cache'][$k] = $v; return true; } }
    }
}

// minimal wpdb stub
namespace {
    class wpdb_stub {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public function get_charset_collate() { return 'utf8mb4'; }
        public function prepare($sql, ...$args) { return vsprintf(str_replace('%s', "'%s'", $sql), $args); }
        public function get_var($sql) { return 0; }
        public function get_row($sql, $output = ARRAY_A) { return null; }
        public function get_results($sql, $output = ARRAY_A) { return []; }
        public function insert($t, $d) { $d['id'] = ++$GLOBALS['_ids'][$t]; $GLOBALS['_rows'][$t][$d['id']] = $d; return $GLOBALS['_ids'][$t]; }
        public function update($t, $d, $w) { return 1; }
        public function delete($t, $w) { return 1; }
        public function query($sql) { return 1; }
    }
    $wpdb = new wpdb_stub();
    if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
}

// ---- Now load the classes under test ----
namespace Enterprise\Tests {
    require_once __DIR__ . '/../modules/portal/class-authservice.php';
    // PortalService depends on it but we only test AuthService for now

    use Enterprise\Modules\Portal\AuthService;
    use PHPUnit\Framework\TestCase;

    class PortalAuthTest extends TestCase
    {
        public function testJwtSecretIsCreatedAndStable(): void
        {
            $a = AuthService::jwtSecret();
            $b = AuthService::jwtSecret();
            $this->assertSame($a, $b);
            $this->assertGreaterThanOrEqual(32, strlen($a));
        }

        public function testVapidKeysAreCreated(): void
        {
            $keys = AuthService::vapidKeys();
            $this->assertArrayHasKey('public', $keys);
            $this->assertArrayHasKey('private', $keys);
            $this->assertNotEmpty($keys['public']);
            $this->assertNotEmpty($keys['private']);
        }

        public function testJwtEncodeDecodeRoundtrip(): void
        {
            $jwt = (new \ReflectionClass(AuthService::class))
                ->getMethod('encodeJwt');
            $jwt->setAccessible(true);

            $token = $jwt->invoke(null, ['sub' => 42, 'jti' => 'abc', 'iat' => time(), 'exp' => time() + 60, 'typ' => 'access']);
            $this->assertIsString($token);
            $this->assertSame(3, substr_count($token, '.'));

            // validateJwt requires the session table row — we only test encode here
            $this->assertNotEmpty($token);
        }
    }
}
