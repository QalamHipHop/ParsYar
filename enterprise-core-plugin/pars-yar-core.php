<?php
/**
 * Plugin Name:       ParsYar Core Engine
 * Plugin URI:        https://github.com/QalamHipHop/ParsYar
 * Description:       Native Enterprise CRM/ERP engine — Custom Objects, Double-entry Accounting, Audit Trail, Workflow Automation.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Qalam
 * License:           MIT
 * Text Domain:       pars-yar
 *
 * @package ParsYar\Core
 */

declare(strict_types=1);

namespace ParsYar\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'PARS_YAR_VERSION', '0.1.0' );
define( 'PARS_YAR_FILE', __FILE__ );
define( 'PARS_YAR_DIR', plugin_dir_path( __FILE__ ) );
define( 'PARS_YAR_URL', plugin_dir_url( __FILE__ ) );
define( 'PARS_YAR_DB_PREFIX', 'py_' );
define( 'PARS_YAR_AUDIT_HASH_KEY', 'pars-yar-audit-salt-v1' );

/**
 * PSR-4-ish autoloader for the ParsYar namespace.
 */
spl_autoload_register(
	static function ( string $class ): void {
		if ( strpos( $class, 'ParsYar\\' ) !== 0 ) {
			return;
		}

		$relative   = substr( $class, strlen( 'ParsYar\\' ) );
		$parts      = explode( '\\', $relative );
		$first      = strtolower( str_replace( '_', '-', $parts[0] ) ); // module folder
		$file_parts = array_slice( $parts, 1 );
		$file_name  = strtolower( str_replace( '_', '-', array_pop( $file_parts ) ) ) . '.php';
		$sub_path   = '';

		foreach ( $file_parts as $segment ) {
			$sub_path .= '/' . strtolower( str_replace( '_', '-', $segment ) );
		}

		$candidate = PARS_YAR_DIR . 'src/' . $first . $sub_path . '/' . $file_name;

		if ( is_readable( $candidate ) ) {
			require_once $candidate;
		}
	}
);

/**
 * Bootstrap.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'init' ] );

		// Activation / deactivation hooks.
		register_activation_hook( PARS_YAR_FILE, [ Installer::class, 'activate' ] );
		register_deactivation_hook( PARS_YAR_FILE, [ Installer::class, 'deactivate' ] );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'pars-yar', false, dirname( plugin_basename( PARS_YAR_FILE ) ) . '/languages' );
	}

	public function init(): void {
		// REST routes.
		( new Rest\RestRouter() )->register();

		// Admin menu.
		if ( is_admin() ) {
			( new Admin\AdminMenu() )->register();
		}

		// Object Engine — register built-in objects on every load (idempotent).
		( new ObjectEngine\ObjectRegistry() )->register_builtins();

		// Workflow hooks (no-op until workflows exist; safe to call).
		do_action( 'pars_yar_init' );
	}
}

Plugin::instance();
