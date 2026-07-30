<?php
/**
 * AuditRepository — low-level DB access for py_audit_log.
 *
 * @package ParsYar\Core\Audit
 */

declare(strict_types=1);

namespace ParsYar\Core\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Persistence layer for the audit log. Intentionally write-once:
 * no update() or delete() methods are exposed.
 */
final class AuditRepository {

	public function insert(
		string $object_api,
		?int $record_id,
		string $action,
		array $diff,
		?string $prev_hash,
		string $entry_hash,
		?int $actor_id
	): int {
		global $wpdb;

		$wpdb->insert(
			PARS_YAR_DB_PREFIX . 'audit_log',
			[
				'actor_id'    => $actor_id,
				'object_api'  => $object_api,
				'record_id'   => $record_id,
				'action'      => $action,
				'diff_json'   => wp_json_encode( $diff ),
				'prev_hash'   => $prev_hash,
				'entry_hash'  => $entry_hash,
			],
			[ '%d', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	public function get_last_hash(): ?string {
		global $wpdb;
		$table = PARS_YAR_DB_PREFIX . 'audit_log';
		$hash  = $wpdb->get_var( "SELECT entry_hash FROM {$table} ORDER BY id DESC LIMIT 1" );
		return $hash ? (string) $hash : null;
	}

	/**
	 * @return array<int, object>
	 */
	public function get_all_ordered(): array {
		global $wpdb;
		$table = PARS_YAR_DB_PREFIX . 'audit_log';
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );
	}

	/**
	 * @return array<int, object>
	 */
	public function get_for_record( string $object_api, int $record_id, int $limit = 100 ): array {
		global $wpdb;
		$table = PARS_YAR_DB_PREFIX . 'audit_log';
		$limit = max( 1, min( 500, $limit ) );
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE object_api = %s AND record_id = %d ORDER BY id DESC LIMIT %d",
				$object_api,
				$record_id,
				$limit
			)
		);
	}
}
