<?php
/**
 * Plugin Name: Enterprise Core Platform
 * Plugin URI:  https://parsYar.local
 * Description: هسته مرکزی پلتفرم سازمانی ParsYar — شامل Custom Object Engine، حسابداری دوطرفه، CRM/ERP/HRM، اتوماسیون و انطباق با سامانه مؤدیان ایران.
 * Version:     1.3.0
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
    public const VERSION = '1.3.0';
    public const DB_VERSION = '1.3.0';
    public const SLUG    = 'enterprise-core';
    public const NS      = 'enterprise/v1';

    /** @var array<string,string> مسیرهای PSR-4 برای autoload */
    public const NAMESPACES = [
        'Enterprise\\'                  => 'includes/',
        'Enterprise\\Core\\'            => 'includes/Core/',
        'Enterprise\\Modules\\'         => 'modules/',
        'Enterprise\\Api\\'             => 'api/',
        'Enterprise\\Admin\\'           => 'admin/',
        'Enterprise\\Db\\'              => 'db/',
        'Enterprise\\Rest\\'            => 'rest/',
        'Enterprise\\Frontend\\'        => 'frontend/',
        'Enterprise\\Support\\'         => 'includes/support/',
    ];

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
        if (!defined('ENTERPRISE_PLUGIN_BASENAME')) {
            define('ENTERPRISE_PLUGIN_BASENAME', plugin_basename(__FILE__));
        }
    }

    /**
     * Autoload PSR-4 برای چند namespace.
     * - کلاس‌های میراثی با پیش‌فرض `class-` سازگار هستند.
     * - کلاس‌های جدید می‌توانند بدون پیش‌وند باشند.
     */
    private function autoload(): void
    {
        spl_autoload_register(function (string $class): void {
            foreach (self::NAMESPACES as $prefix => $base) {
                if (strpos($class, $prefix) !== 0) {
                    continue;
                }
                $relative = substr($class, strlen($prefix));
                $parts    = explode('\\', $relative);
                $file     = array_pop($parts);
                $file_lc  = strtolower(str_replace('_', '-', $file));
                $dir      = strtolower(implode('/', $parts));

                // Try exact file first
                $candidates = [
                    ENTERPRISE_PLUGIN_DIR . $base . ($dir ? $dir . '/' : '') . 'class-' . $file_lc . '.php',
                    ENTERPRISE_PLUGIN_DIR . $base . ($dir ? $dir . '/' : '') . $file_lc . '.php',
                    ENTERPRISE_PLUGIN_DIR . $base . ($dir ? $dir . '/' : '') . $file . '.php',
                ];
                foreach ($candidates as $path) {
                    if (file_exists($path)) {
                        require_once $path;
                        return;
                    }
                }
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

        // Setup Wizard
        add_action('admin_init', [Admin\Wizard::class, 'register']);

        // Modules auto-boot
        \Enterprise\Modules\Audit\Logger::boot();
        \Enterprise\Modules\Workflow\Repository::boot();
        \Enterprise\Modules\Multitenant\Context::boot();

        add_action('enterprise_event', [Workflow\Dispatcher::class, 'handle'], 10, 2);
    }
}

Bootstrap::instance();
