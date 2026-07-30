<?php
/**
 * RecordRepository — create / read / update records with field values.
 *
 * @package ParsYar\ObjectEngine
 */

declare(strict_types=1);

namespace ParsYar\ObjectEngine;

use ParsYar\Core\Audit\AuditService;
use ParsYar\Core\Sanitizer\FieldSanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD layer for py_records + py_record_values.
 */
final class RecordRepository {

	public function __construct(
		private readonly SchemaManager $schema,
		private readonly FieldSanitizer $sanitizer,
		private readonly AuditService $audit
	) {}

	/**
	 * Create a new record of an object.
	 *
	 * @param string               $object_api API name of the object.
	 * @param array<string, mixed> $values     map of field_api_name => value.
	 * @return int New record id, or 0 on failure.
	 */
	public function create( string $object_api, array $values ): int {
		global $wpdb;

		$object_id = $this->schema->get_object_id( $object_api );
		if ( $object_id <= 0 ) {
			return 0;
		}

		$fields = $this->schema->get_fields( $object_id );
		$clean  = $this->sanitizer->validate_and_coerce( $fields, $values );

		if ( ! empty( $clean['errors'] ) ) {
			return 0;
		}

		$wpdb->insert(
			PARS_YAR_DB_PREFIX . 'records',
			[
				'object_id'  => $object_id,
				'owner_id'   => get_current_user_id() ?: null,
				'status'     => 'active',
				'created_by' => get_current_user_id() ?: null,
				'updated_by' => get_current_user_id() ?: null,
			],
			[ '%d', '%d', '%s', '%d', '%d' ]
		);

		$record_id = (int) $wpdb->insert_id;
		if ( $record_id <= 0 ) {
			return 0;
		}

		$this->persist_values( $record_id, $fields, $clean['values'] );
		$this->audit->record( $object_api, $record_id, 'create', null, $clean['values'] );

		return $record_id;
	}

	/**
	 * Get a single record with its values, decoded into a flat assoc array.
	 *
	 * @return array<string, mixed>|null
	 */
	public function get( string $object_api, int $record_id ): ?array {
		global $wpdb;

		$object_id = $this->schema->get_object_id( $object_api );
		if ( $object_id <= 0 ) {
			return null;
		}

		$record = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . PARS_YAR_DB_PREFIX . "records WHERE id = %d AND object_id = %d",
				$record_id,
				$object_id
			)
		);
		if ( ! $record ) {
			return null;
		}

		return $this->hydrate( (int) $record->id, $this->schema->get_fields( $object_id ) );
	}

	/**
	 * @param array<string, string> $orderby  column => 'ASC'|'DESC'
	 * @return array<int, array<string, mixed>>
	 */
	public function list( string $object_api, int $limit = 50, int $offset = 0, array $orderby = [ 'id' => 'DESC' ] ): array {
		global $wpdb;

		$object_id = $this->schema->get_object_id( $object_api );
		if ( $object_id <= 0 ) {
			return [];
		}

		$order_sql = 'id DESC';
		foreach ( $orderby as $col => $dir ) {
			$dir        = strtoupper( $dir ) === 'ASC' ? 'ASC' : 'DESC';
			$col        = preg_replace( '/[^a-zA-Z0-9_]/', '', (string) $col ) ?: 'id';
			$order_sql  = "{$col} {$dir}";
			break;
		}

		$limit  = max( 1, min( 200, $limit ) );
		$offset = max( 0, $offset );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM " . PARS_YAR_DB_PREFIX . "records
				 WHERE object_id = %d ORDER BY {$order_sql} LIMIT %d OFFSET %d",
				$object_id,
				$limit,
				$offset
			)
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$hydrated = $this->hydrate( (int) $row->id, $this->schema->get_fields( $object_id ) );
			if ( $hydrated ) {
				$out[] = $hydrated;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, object> $fields
	 * @return array<string, mixed>
	 */
	private function hydrate( int $record_id, array $fields ): array {
		global $wpdb;

		$field_ids = array_map( static fn( $f ) => (int) $f->id, $fields );
		if ( empty( $field_ids ) ) {
			return [ 'id' => $record_id ];
		}

		$placeholders = implode( ',', array_fill( 0, count( $field_ids ), '%d' ) );
		$params       = array_merge( [ $record_id ], $field_ids );

		$prepared = $wpdb->prepare(
			"SELECT field_id, value_longtext, value_double, value_datetime
			 FROM " . PARS_YAR_DB_PREFIX . "record_values
			 WHERE record_id = %d AND field_id IN ({$placeholders})",
			$params
		);

		$rows  = (array) $wpdb->get_results( $prepared );
		$by_id = [];
		foreach ( $rows as $row ) {
			$by_id[ (int) $row->field_id ] = $row;
		}

		$out = [ 'id' => $record_id ];
		foreach ( $fields as $f ) {
			$api = (string) $f->api_name;
			if ( ! isset( $by_id[ (int) $f->id ] ) ) {
				$out[ $api ] = null;
				continue;
			}
			$r = $by_id[ (int) $f->id ];
			$out[ $api ] = match ( $f->field_type ) {
				'number'   => $r->value_double !== null ? (float) $r->value_double : null,
				'date'     => $r->value_datetime,
				default    => $r->value_longtext,
			};
		}
		return $out;
	}

	/**
	 * @param array<int, object>     $fields
	 * @param array<string, mixed>   $values  already-sanitized values keyed by field id (int) or api name.
	 */
	private function persist_values( int $record_id, array $fields, array $values ): void {
		global $wpdb;

		foreach ( $fields as $f ) {
			$key   = (string) $f->api_name;
			$value = $values[ $key ] ?? null;
			if ( null === $value || '' === $value ) {
				continue;
			}

			$row = [
				'record_id'      => $record_id,
				'field_id'       => (int) $f->id,
				'value_longtext' => null,
				'value_double'   => null,
				'value_datetime' => null,
			];

			switch ( $f->field_type ) {
				case 'number':
					$row['value_double'] = (float) $value;
					break;
				case 'date':
					$row['value_datetime'] = $value;
					break;
				default:
					$row['value_longtext'] = (string) $value;
			}

			$wpdb->replace( PARS_YAR_DB_PREFIX . 'record_values', $row, [ '%d', '%d', '%s', '%f', '%s' ] );
		}
	}
}
