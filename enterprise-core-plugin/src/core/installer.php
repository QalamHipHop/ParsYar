<?php
/**
 * Installer — schema bootstrap, roles, and demo seeding.
 *
 * @package ParsYar\Core
 */

declare(strict_types=1);

namespace ParsYar\Core;

use ParsYar\Core\ObjectEngine\SchemaManager;
use ParsYar\Core\Audit\AuditRepository;
use ParsYar\Core\Demo\DemoSeeder;

defined( 'ABSPATH' ) || exit;

/**
 * Handles activation, deactivation, and first-run provisioning.
 */
final class Installer {

	/**
	 * Option key used to track schema version.
	 */
	public const DB_VERSION_OPTION = 'pars_yar_db_version';

	/**
	 * Current schema version. Bump when migrations are added.
	 */
	public const DB_VERSION = '0.1.0';

	public static function activate(): void {
		self::ensure_capabilities();
		self::install_schema();
		self::seed_demo_if_empty();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	public static function deactivate(): void {
		// Intentionally NOT dropping tables. Deactivation is reversible.
		// Tables are dropped only on uninstall.php.
		flush_rewrite_rules();
	}

	/**
	 * Create custom database tables. Idempotent — uses dbDelta.
	 */
	public static function install_schema(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = PARS_YAR_DB_PREFIX;

		$sql = [];

		$sql[] = "CREATE TABLE {$prefix}objects (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			api_name VARCHAR(64) NOT NULL,
			label VARCHAR(128) NOT NULL,
			label_plural VARCHAR(128) NOT NULL,
			description TEXT NULL,
			is_system TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY api_name (api_name)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}object_fields (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT(20) UNSIGNED NOT NULL,
			api_name VARCHAR(64) NOT NULL,
			label VARCHAR(128) NOT NULL,
			field_type VARCHAR(32) NOT NULL,
			is_required TINYINT(1) NOT NULL DEFAULT 0,
			is_unique TINYINT(1) NOT NULL DEFAULT 0,
			ref_object_id BIGINT(20) UNSIGNED NULL,
			default_value TEXT NULL,
			sort_order INT(11) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY object_field (object_id, api_name),
			KEY object_id (object_id),
			KEY ref_object_id (ref_object_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}records (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			object_id BIGINT(20) UNSIGNED NOT NULL,
			owner_id BIGINT(20) UNSIGNED NULL,
			status VARCHAR(32) NOT NULL DEFAULT 'active',
			created_by BIGINT(20) UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_by BIGINT(20) UNSIGNED NULL,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY object_id (object_id),
			KEY owner_id (owner_id),
			KEY status (status)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}record_values (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			record_id BIGINT(20) UNSIGNED NOT NULL,
			field_id BIGINT(20) UNSIGNED NOT NULL,
			value_longtext LONGTEXT NULL,
			value_double DECIMAL(20,6) NULL,
			value_datetime DATETIME NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY record_field (record_id, field_id),
			KEY field_id (field_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}audit_log (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
			actor_id BIGINT(20) UNSIGNED NULL,
			object_api VARCHAR(64) NOT NULL,
			record_id BIGINT(20) UNSIGNED NULL,
			action VARCHAR(32) NOT NULL,
			diff_json LONGTEXT NULL,
			prev_hash CHAR(64) NULL,
			entry_hash CHAR(64) NOT NULL,
			PRIMARY KEY  (id),
			KEY record (object_api, record_id),
			KEY occurred_at (occurred_at)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}

	/**
	 * Grant custom capabilities to administrator.
	 */
	public static function ensure_capabilities(): void {
		$role = get_role( 'administrator' );
		if ( ! $role ) {
			return;
		}

		$caps = [
			'manage_pars_yar',
			'edit_pars_yar_objects',
			'delete_pars_yar_objects',
			'view_pars_yar_audit',
		];

		foreach ( $caps as $cap ) {
			$role->add_cap( $cap );
		}
	}

	/**
	 * Seed demo data on first activation.
	 */
	public static function seed_demo_if_empty(): void {
		if ( get_option( 'pars_yar_seeded' ) ) {
			return;
		}

		( new SchemaManager() )->register_builtin_objects();
		( new DemoSeeder() )->run();

		update_option( 'pars_yar_seeded', 1 );
	}
}
