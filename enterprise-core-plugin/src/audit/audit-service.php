<?php
/**
 * AuditService — append-only change log with hash chain.
 *
 * @package ParsYar\Core\Audit
 */

declare(strict_types=1);

namespace ParsYar\Core\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Appends immutable audit entries to py_audit_log.
 *
 * Each entry's hash includes the previous entry's hash, forming a tamper-evident
 * chain (suitable for سامانه مؤدیان / financial regulator inspection).
 */
final class AuditService {

	public function __construct( private readonly AuditRepository $repo ) {}

	/**
	 * Record a change.
	 *
	 * @param string                $object_api  Object API name.
	 * @param int|null              $record_id   Record id, or null for schema-level events.
	 * @param string                $action      create|update|delete|custom.
	 * @param array<string, mixed>|null $before   Previous state (for update/delete).
	 * @param array<string, mixed>|null $after    New state (for create/update).
	 * @return int Inserted audit id.
	 */
	public function record( string $object_api, ?int $record_id, string $action, ?array $before, ?array $after ): int {
		$prev_hash = $this->repo->get_last_hash();
		$diff      = [
			'before' => $before,
			'after'  => $after,
		];

		$payload = wp_json_encode(
			[
				'ts'         => gmdate( 'Y-m-d\TH:i:s.u\Z' ),
				'actor'      => get_current_user_id() ?: 0,
				'object_api' => $object_api,
				'record_id'  => $record_id,
				'action'     => $action,
				'diff'       => $diff,
				'prev_hash'  => $prev_hash,
			]
		);

		$entry_hash = hash_hmac( 'sha256', (string) $payload, (string) PARS_YAR_AUDIT_HASH_KEY );

		return $this->repo->insert(
			$object_api,
			$record_id,
			$action,
			$diff,
			$prev_hash,
			$entry_hash,
			get_current_user_id() ?: null
		);
	}

	/**
	 * Verify the hash chain integrity.
	 *
	 * @return array{ok: bool, broken_at: int|null, total: int}
	 */
	public function verify_chain(): array {
		$entries = $this->repo->get_all_ordered();
		$prev    = null;
		$total   = count( $entries );

		foreach ( $entries as $entry ) {
			$payload = wp_json_encode(
				[
					'ts'         => $entry->occurred_at,
					'actor'      => (int) $entry->actor_id,
					'object_api' => $entry->object_api,
					'record_id'  => $entry->record_id ? (int) $entry->record_id : null,
					'action'     => $entry->action,
					'diff'       => json_decode( (string) $entry->diff_json, true ),
					'prev_hash'  => $entry->prev_hash,
				]
			);

			$expected = hash_hmac( 'sha256', (string) $payload, (string) PARS_YAR_AUDIT_HASH_KEY );

			if ( $expected !== $entry->entry_hash ) {
				return [ 'ok' => false, 'broken_at' => (int) $entry->id, 'total' => $total ];
			}
			if ( ( $entry->prev_hash ?: null ) !== $prev ) {
				return [ 'ok' => false, 'broken_at' => (int) $entry->id, 'total' => $total ];
			}
			$prev = $entry->entry_hash;
		}

		return [ 'ok' => true, 'broken_at' => null, 'total' => $total ];
	}
}
