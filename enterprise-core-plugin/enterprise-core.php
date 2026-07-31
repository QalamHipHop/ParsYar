<?php
/**
 * Plugin Name: Enterprise Core Platform
 * Plugin URI:  https://parsYar.local
 * Description: هسته مرکزی پلتفرم سازمانی ParsYar — شامل Custom Object Engine، حسابداری دوطرفه، CRM/ERP/HRM، اتوماسیون و انطباق با سامانه مؤدیان ایران.
 * Version:     1.2.0
 * Author:      ParsYar Team
 * License:     GPL-2.0-or-later
 * Text Domain: enterprise-core
 * Requires PHP: 8.0
 */

declare(strict_types=1);

namespace Enterprise;

defined('ABSPATH') || exit;

final class Bootstrap
{
    public const VERSION = '1.2.0';
    public const SLUG    = 'enterprise-core';
    public const NS      = 'enterprise/v1';

    private static ?Bootstrap $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->defineConstants();
        $this->autoload();
        $this->registerHooks();
    }

    private function defineConstants(): void
    {
        if (!defined('ENTERPRISE_PLUGIN_FILE')) {
            define('ENTERPRISE_PLUGIN_FILE', __FILE__);
        }
        if (!defined('ENTERPRISE_PLUGIN_DIR')) {
            define('ENTERPRISE_PLUGIN_DIR', plugin_dir_path(__FILE__));
        }
        if (!defined('ENTERPRISE_PLUGIN_URL')) {
            define('ENTERPRISE_PLUGIN_URL', plugin_dir_url(__FILE__));
        }
    }

    private function autoload(): void
    {
        spl_autoload_register(function (string $class): void {
            if (strpos($class, 'Enterprise\\') !== 0) {
                return;
            }
            $relative = substr($class, strlen('Enterprise\\'));
            $parts    = explode('\\', $relative);
            $file     = array_pop($parts);
            array_unshift($parts, 'class-');
            $parts[]  = $file;
            $path     = ENTERPRISE_PLUGIN_DIR . 'includes/' . strtolower(implode('/', $parts)) . '.php';
            if (file_exists($path)) {
                require_once $path;
            }
        });
    }

    private function registerHooks(): void
    {
        register_activation_hook(__FILE__, [Installer::class, 'activate']);
        register_deactivation_hook(__FILE__, [Installer::class, 'deactivate']);

        add_action('init', [Router::class, 'register']);
        add_action('rest_api_init', [Api\RestRouter::class, 'register']);
        add_action('admin_menu', [Admin\Menu::class, 'register']);
        add_action('admin_init', [Admin\Setup::class, 'maybeRedirect']);

        add_action('enterprise_event', [Workflow\Dispatcher::class, 'handle'], 10, 2);

        \Enterprise\Modules\Audit\Logger::boot();
    }
}

Bootstrap::instance();
