<?php
/**
 * ParsYar Enterprise Theme
 * Theme functions, hooks, asset loader
 *
 * @package ParsYar
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

if (!defined('PARSYAR_THEME_VERSION')) {
    define('PARSYAR_THEME_VERSION', '1.0.0');
}
if (!defined('PARSYAR_THEME_DIR')) {
    define('PARSYAR_THEME_DIR', get_stylesheet_directory());
}
if (!defined('PARSYAR_THEME_URI')) {
    define('PARSYAR_THEME_URI', get_stylesheet_directory_uri());
}

/* -----------------------------------------------------------------------------
 * THEME SETUP
 * -------------------------------------------------------------------------- */
add_action('after_setup_theme', static function (): void {
    load_theme_textdomain('parsyar', PARSYAR_THEME_DIR . '/languages');

    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
        'navigation-widgets',
    ]);
    add_theme_support('custom-logo', [
        'height'      => 64,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('wp-block-styles');
    add_theme_support('editor-styles');
    add_theme_support('customize-selective-refresh-widgets');

    // Register nav menus
    register_nav_menus([
        'primary'    => esc_html__('منوی اصلی', 'parsyar'),
        'footer'     => esc_html__('منوی فوتر', 'parsyar'),
        'dashboard'  => esc_html__('منوی داشبورد', 'parsyar'),
    ]);

    // Image sizes
    add_image_size('parsyar-avatar', 80, 80, true);
    add_image_size('parsyar-card',   600, 400, true);
    add_image_size('parsyar-hero',   1600, 800, true);
});

/* -----------------------------------------------------------------------------
 * ASSETS
 * -------------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', static function (): void {
    $uri = PARSYAR_THEME_URI;
    $dir = PARSYAR_THEME_DIR;
    $ver = PARSYAR_THEME_VERSION;

    // Vazirmatn (Persian webfont)
    wp_enqueue_style(
        'parsyar-font-vazirmatn',
        $uri . '/assets/fonts/vazirmatn.css',
        [],
        $ver
    );

    // Design tokens (must load first)
    wp_enqueue_style(
        'parsyar-tokens',
        $uri . '/assets/css/tokens.css',
        ['parsyar-font-vazirmatn'],
        $ver
    );

    // Core stylesheet bundle
    wp_enqueue_style(
        'parsyar',
        $uri . '/assets/css/parsyar.css',
        ['parsyar-tokens'],
        $ver
    );

    // RTL override
    if (is_rtl()) {
        wp_enqueue_style(
            'parsyar-rtl',
            $uri . '/assets/css/rtl.css',
            ['parsyar'],
            $ver
        );
    }

    // Print stylesheet
    wp_enqueue_style(
        'parsyar-print',
        $uri . '/assets/css/print.css',
        ['parsyar'],
        $ver,
        'print'
    );

    // Vendor (only on dashboard) — gracefully skip missing files
    if (parsyar_is_dashboard()) {
        $vendor_assets = [
            'parsyar-dayjs'         => 'vendor/dayjs/dayjs.min.js',
            'parsyar-dayjs-jalali'  => 'vendor/dayjs/dayjs-jalali.min.js',
            'parsyar-sortable'      => 'vendor/sortable.min.js',
            'parsyar-tippy'         => 'vendor/tippy.min.js',
            'parsyar-chart'         => 'vendor/chart.js/chart.umd.min.js',
        ];
        $deps = [];
        foreach ($vendor_assets as $handle => $rel) {
            $path = $dir . '/assets/' . $rel;
            if (file_exists($path)) {
                wp_enqueue_script($handle, $uri . '/assets/' . $rel, $deps, parsyar_get_asset_version($rel), true);
                $deps[] = $handle;
            }
        }
    }

    // Core JS
    wp_enqueue_script(
        'parsyar',
        $uri . '/assets/js/parsyar.js',
        ['jquery'],
        $ver,
        true
    );

    // Modules
    wp_enqueue_script(
        'parsyar-theme-controller',
        $uri . '/assets/js/theme-controller.js',
        ['parsyar'],
        $ver,
        true
    );

    if (parsyar_is_dashboard()) {
        wp_enqueue_script(
            'parsyar-command-palette',
            $uri . '/assets/js/command-palette.js',
            ['parsyar', 'parsyar-theme-controller'],
            $ver,
            true
        );
        wp_enqueue_script(
            'parsyar-data-table',
            $uri . '/assets/js/data-table.js',
            ['parsyar', 'parsyar-sortable'],
            $ver,
            true
        );
        wp_enqueue_script(
            'parsyar-charts',
            $uri . '/assets/js/charts.js',
            ['parsyar', 'parsyar-chart'],
            $ver,
            true
        );
        wp_enqueue_script(
            'parsyar-forms',
            $uri . '/assets/js/forms.js',
            ['parsyar'],
            $ver,
            true
        );
        wp_enqueue_script(
            'parsyar-notifications',
            $uri . '/assets/js/notifications.js',
            ['parsyar', 'parsyar-tippy'],
            $ver,
            true
        );
    }

    // Localize
    wp_localize_script('parsyar', 'ParsYarConfig', [
        'restUrl'   => esc_url_raw(rest_url('parsyar/v1')),
        'restNonce' => wp_create_nonce('wp_rest'),
        'siteUrl'   => esc_url_raw(site_url('/')),
        'ajaxUrl'   => esc_url_raw(admin_url('admin-ajax.php')),
        'isRTL'     => is_rtl(),
        'isDashboard' => parsyar_is_dashboard(),
        'userId'    => get_current_user_id(),
        'locale'    => get_user_locale(),
        'i18n'      => [
            'loading'    => esc_html__('در حال بارگذاری...', 'parsyar'),
            'error'      => esc_html__('خطایی رخ داد.', 'parsyar'),
            'saved'      => esc_html__('ذخیره شد.', 'parsyar'),
            'confirm'    => esc_html__('آیا مطمئن هستید؟', 'parsyar'),
            'search'     => esc_html__('جستجو...', 'parsyar'),
            'noResults'  => esc_html__('نتیجه‌ای یافت نشد.', 'parsyar'),
        ],
    ]);

    // Comment reply
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}, 20);

/* -----------------------------------------------------------------------------
 * HELPERS
 * -------------------------------------------------------------------------- */
if (!function_exists('parsyar_is_dashboard')) {
    /**
     * Detect if the current request is a dashboard context.
     */
    function parsyar_is_dashboard(): bool
    {
        if (is_admin()) {
            return false;
        }
        // Frontend dashboard page (set by plugin or by template)
        $dashboard_page_id = (int) get_option('parsyar_dashboard_page_id');
        if ($dashboard_page_id && is_page($dashboard_page_id)) {
            return true;
        }
        // Any page using the dashboard template
        if (is_page_template('page-dashboard.php')) {
            return true;
        }
        return false;
    }
}

if (!function_exists('parsyar_get_asset_version')) {
    function parsyar_get_asset_version(string $relative): string
    {
        $path = PARSYAR_THEME_DIR . '/assets/' . ltrim($relative, '/');
        return file_exists($path) ? (string) filemtime($path) : PARSYAR_THEME_VERSION;
    }
}

if (!function_exists('parsyar_format_money')) {
    /**
     * Format money using current currency. Default: IRT (Toman).
     *
     * @param int|float $amount
     */
    function parsyar_format_money($amount, string $currency = 'IRT'): string
    {
        $symbols = [
            'IRT' => 'تومان',
            'IRR' => 'ریال',
            'USD' => '$',
            'EUR' => '€',
            'AED' => 'د.إ',
            'TRY' => '₺',
        ];
        $formatted = number_format_i18n((float) $amount, 0);
        $symbol    = $symbols[$currency] ?? $currency;
        // RTL placement
        if (is_rtl()) {
            return $formatted . ' ' . $symbol;
        }
        return $symbol . ' ' . $formatted;
    }
}

if (!function_exists('parsyar_jalali_date')) {
    /**
     * Convert Gregorian date to Jalali string.
     */
    function parsyar_jalali_date(string $format = 'Y/m/d', ?string $gregorian = null): string
    {
        if (!class_exists('\\Enterprise\\Jalali')) {
            return $gregorian ?? '';
        }
        return \Enterprise\Jalali::format($format, $gregorian);
    }
}

if (!function_exists('parsyar_national_id_validate')) {
    function parsyar_national_id_validate(string $code): bool
    {
        if (!class_exists('\\Enterprise\\Validator')) {
            return strlen($code) === 10;
        }
        return \Enterprise\Validator::nationalId($code);
    }
}

/* -----------------------------------------------------------------------------
 * BODY / HTML CLASSES
 * -------------------------------------------------------------------------- */
add_filter('body_class', static function (array $classes): array {
    if (parsyar_is_dashboard()) {
        $classes[] = 'parsyar-dashboard';
        $classes[] = 'parsyar-app';
    } else {
        $classes[] = 'parsyar-frontend';
    }
    if (is_rtl()) {
        $classes[] = 'parsyar-rtl';
    } else {
        $classes[] = 'parsyar-ltr';
    }
    $theme_mode = get_user_meta(get_current_user_id(), 'parsyar_theme_mode', true) ?: 'light';
    $classes[]  = 'parsyar-theme-' . sanitize_html_class($theme_mode);
    return $classes;
});

add_filter('admin_body_class', static function (string $classes): string {
    return $classes . ' parsyar-admin';
});

/* -----------------------------------------------------------------------------
 * WIDGETS
 * -------------------------------------------------------------------------- */
add_action('widgets_init', static function (): void {
    register_sidebar([
        'name'          => esc_html__('سایدبار اصلی', 'parsyar'),
        'id'            => 'sidebar-primary',
        'description'   => esc_html__('سایدبار صفحات و نوشته‌ها', 'parsyar'),
        'before_widget' => '<section id="%1$s" class="p-widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="p-widget__title">',
        'after_title'   => '</h3>',
    ]);

    register_sidebar([
        'name'          => esc_html__('سایدبار داشبورد', 'parsyar'),
        'id'            => 'sidebar-dashboard',
        'description'   => esc_html__('سایدبار داخلی داشبورد', 'parsyar'),
        'before_widget' => '<section id="%1$s" class="p-widget p-widget--dashboard %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h4 class="p-widget__title">',
        'after_title'   => '</h4>',
    ]);

    register_sidebar([
        'name'          => esc_html__('فوتر', 'parsyar'),
        'id'            => 'footer-1',
        'description'   => esc_html__('ستون‌های فوتر', 'parsyar'),
        'before_widget' => '<div id="%1$s" class="p-footer__col %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="p-footer__title">',
        'after_title'   => '</h4>',
    ]);
});

/* -----------------------------------------------------------------------------
 * SCRIPTS: AJAX nonce for legacy endpoints
 * -------------------------------------------------------------------------- */
add_action('wp_ajax_parsyar_quick_search', static function (): void {
    check_ajax_referer('parsyar_ajax', 'nonce');
    $q = sanitize_text_field((string) ($_POST['q'] ?? ''));
    $results = apply_filters('parsyar_quick_search', [], $q);
    wp_send_json_success($results);
});
add_action('wp_ajax_nopriv_parsyar_quick_search', '__return_false');

/* -----------------------------------------------------------------------------
 * ADMIN: theme-mode preference
 * -------------------------------------------------------------------------- */
add_action('personal_options_update', static function ($user_id): void {
    if (!current_user_can('edit_user', $user_id)) {
        return;
    }
    $mode = sanitize_key($_POST['parsyar_theme_mode'] ?? 'light');
    if (in_array($mode, ['light', 'dark', 'auto'], true)) {
        update_user_meta($user_id, 'parsyar_theme_mode', $mode);
    }
});
add_action('show_user_profile', static function ($user): void {
    $mode = get_user_meta($user->ID, 'parsyar_theme_mode', true) ?: 'light';
    ?>
    <tr>
        <th scope="row"><label for="parsyar_theme_mode"><?php esc_html_e('ParsYar Theme Mode', 'parsyar'); ?></label></th>
        <td>
            <select name="parsyar_theme_mode" id="parsyar_theme_mode">
                <option value="light" <?php selected($mode, 'light'); ?>><?php esc_html_e('روشن', 'parsyar'); ?></option>
                <option value="dark"  <?php selected($mode, 'dark'); ?>><?php esc_html_e('تیره', 'parsyar'); ?></option>
                <option value="auto"  <?php selected($mode, 'auto'); ?>><?php esc_html_e('خودکار', 'parsyar'); ?></option>
            </select>
        </td>
    </tr>
    <?php
});
