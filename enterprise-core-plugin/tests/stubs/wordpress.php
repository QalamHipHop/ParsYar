<?php
/**
 * WordPress function stubs for unit tests.
 * These minimal stubs cover the surface used by the ParsYar codebase.
 * They are NOT a full WordPress test suite — for integration tests
 * we use the WP test suite (see tests/integration/bootstrap.php).
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../wordpress/');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

if (!function_exists('get_option')) {
    function get_option(string $name, $default = false)
    {
        return $GLOBALS['_test_options'][$name] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $name, $value, $autoload = null): bool
    {
        $GLOBALS['_test_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option(string $name): bool
    {
        unset($GLOBALS['_test_options'][$name]);
        return true;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void
    {
        echo $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e(string $text, string $domain = 'default'): void
    {
        echo htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field(string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $echo = true): string
    {
        $field = '<input type="hidden" name="' . $name . '" value="test-nonce-' . md5($action) . '" />';
        if ($echo) {
            echo $field;
        }
        return $field;
    }
}

if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = '-1'): string
    {
        return 'test-nonce-' . md5($action);
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, ...$args): bool
    {
        return $GLOBALS['_test_current_user_can'] ?? true;
    }
}

if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(): object
    {
        return (object) ($GLOBALS['_test_user'] ?? ['ID' => 0, 'user_login' => 'guest']);
    }
}

if (!function_exists('get_userdata')) {
    function get_userdata(int $id): ?object
    {
        return (object) ['ID' => $id, 'user_login' => "user{$id}"];
    }
}

if (!function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return ($GLOBALS['_test_user']['ID'] ?? 0) > 0;
    }
}

if (!function_exists('wp_send_json')) {
    function wp_send_json($data, int $status = 200, int $options = 0): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data, $options);
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, int $status = 200): void
    {
        wp_send_json(['success' => true, 'data' => $data], $status);
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, int $status = 200): void
    {
        wp_send_json(['success' => false, 'data' => $data], $status);
    }
}

if (!function_exists('register_rest_route')) {
    function register_rest_route(string $namespace, string $route, array $args, bool $override = false): bool
    {
        $GLOBALS['_test_routes'][] = compact('namespace', 'route', 'args');
        return true;
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules(bool $hard = true): void
    {
        // no-op in tests
    }
}

if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path(string $file): string
    {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url(string $file): string
    {
        return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename(string $file): string
    {
        return basename(dirname($file)) . '/' . basename($file);
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = '', string $scheme = 'admin'): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('home_url')) {
    function home_url(string $path = '', ?string $scheme = null): string
    {
        return 'https://example.test/' . ltrim($path, '/');
    }
}

if (!function_exists('rest_url')) {
    function rest_url(string $path = ''): string
    {
        return 'https://example.test/wp-json/' . ltrim($path, '/');
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['_test_actions'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(string $hook, $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['_test_filters'][$hook][] = $callback;
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        foreach (($GLOBALS['_test_actions'][$hook] ?? []) as $cb) {
            $cb(...$args);
        }
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        foreach (($GLOBALS['_test_filters'][$hook] ?? []) as $cb) {
            $value = $cb($value, ...$args);
        }
        return $value;
    }
}

if (!function_exists('dbDelta')) {
    function dbDelta(string $sql): array
    {
        return [];
    }
}

if (!function_exists('get_role')) {
    function get_role(string $role): ?object
    {
        return (object) ['add_cap' => function (string $cap) { return true; }];
    }
}

if (!function_exists('wp_roles')) {
    function wp_roles(): object
    {
        return (object) ['get_role' => function (string $role) { return get_role($role); }];
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        return trim(strip_tags($str));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

if (!function_exists('wp_parse_args')) {
    function wp_parse_args(array $args, array $defaults): array
    {
        return array_merge($defaults, $args);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512): string|false
    {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof \WP_Error;
    }
}

if (!function_exists('date_i18n')) {
    function date_i18n(string $format, ?int $timestamp = null): string
    {
        return gmdate($format, $timestamp ?? time());
    }
}

if (!function_exists('wp_date')) {
    function wp_date(string $format, ?int $timestamp = null): string
    {
        return gmdate($format, $timestamp ?? time());
    }
}
