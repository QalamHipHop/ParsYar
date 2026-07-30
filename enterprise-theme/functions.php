<?php
/**
 * Enterprise Theme — Headless Dashboard
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_action('wp_enqueue_scripts', static function (): void {
    $themeDir = get_stylesheet_directory();
    $themeUri = get_stylesheet_directory_uri();

    $manifestPath = $themeDir . '/build/asset-manifest.json';
    if (file_exists($manifestPath)) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true) ?: [];
        $entry    = $manifest['entrypoints'] ?? ['main.js', 'main.css'];
        foreach ($entry as $f) {
            if (substr($f, -3) === '.js') {
                wp_enqueue_script('enterprise-app', $themeUri . '/build/' . $f, [], null, true);
                wp_localize_script('enterprise-app', 'EnterpriseConfig', [
                    'restUrl' => esc_url_raw(rest_url('enterprise/v1')),
                    'nonce'   => wp_create_nonce('wp_rest'),
                ]);
            } elseif (substr($f, -4) === '.css') {
                wp_enqueue_style('enterprise-app', $themeUri . '/build/' . $f, [], null);
            }
        }
    }
});

add_filter('template_include', static function ($template): string {
    if (get_query_var('enterprise_route') !== '') {
        return locate_template(['enterprise/index.php']) ?: $template;
    }
    return $template;
});
