<?php
declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

/**
 * ثبت Rewrite Ruleها و Route‌های Front-end.
 */
final class Router
{
    public static function register(): void
    {
        add_rewrite_rule(
            '^enterprise/?$',
            'index.php?enterprise_route=dashboard',
            'top'
        );
        add_rewrite_rule(
            '^enterprise/([a-z0-9_-]+)/?$',
            'index.php?enterprise_route=$matches[1]',
            'top'
        );
        add_filter('query_vars', static function (array $vars): array {
            $vars[] = 'enterprise_route';
            return $vars;
        });
        add_action('template_redirect', [self::class, 'dispatch']);
    }

    public static function dispatch(): void
    {
        $route = (string) get_query_var('enterprise_route');
        if ($route === '') {
            return;
        }
        if (!is_user_logged_in() && !self::isPublicRoute($route)) {
            auth_redirect();
            return;
        }
        status_header(200);
        header('X-Enterprise-Route: ' . sanitize_key($route));
        echo self::renderShell($route);
        exit;
    }

    private static function isPublicRoute(string $route): bool
    {
        return false;
    }

    private static function renderShell(string $route): string
    {
        $themeDir = get_stylesheet_directory();
        $index    = $themeDir . '/enterprise/index.php';
        if (file_exists($index)) {
            ob_start();
            include $index;
            return (string) ob_get_clean();
        }
        return '<!doctype html><html><head><meta charset="utf-8"><title>Enterprise</title></head><body><div id="enterprise-root" data-route="' . esc_attr($route) . '"></div></body></html>';
    }
}
