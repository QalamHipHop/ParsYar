<?php
/**
 * SchemaManager — DB CRUD for object & field definitions.
 *
 * @package ParsYar\ObjectEngine
 */

declare(strict_types=1);

namespace ParsYar\ObjectEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Manages object/field metadata in py_objects and py_object_fields.
 * Uses wpdb with prepared statements throughout.
 */
final class SchemaManager {

	/**
	 * Register all built-in objects (no-op if already present).
	 */
	public function register_builtin_objects(): void {
		$registry = new ObjectRegistry();
		foreach ( $registry->builtins() as $object_def ) {
			$this->upsert_builtin_object( $object_def );
		}
	}

	/**
	 * Create or update a built-in object and its fields.
	 *
	 * @param array<string, mixed> $object_def
	 */
	private function upsert_builtin_object( array $object_def ): void {
		global $wpdb;

		$table_objects = PARS_YAR_DB_PREFIX . 'objects';
		$table_fields  = PARS_YAR_DB_PREFIX . 'object_fields';

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_objects} WHERE api_name = %s",
				$object_def['api_name']
			)
		);

		if ( $existing > 0 ) {
			$object_id = $existing;
			$wpdb->update(
				$table_objects,
				[
					'label'        => $object_def['label'],
					'label_plural' => $object_def['label_plural'],
					'description'  => $object_def['description'],
					'is_system'    => 1,
				],
				[ 'id' => $object_id ],
				[ '%s', '%s', '%s', '%d' ],
				[ '%d' ]
			);
		} else {
			$wpdb->insert(
				$table_objects,
				[
					'api_name'     => $object_def['api_name'],
					'label'        => $object_def['label'],
					'label_plural' => $object_def['label_plural'],
					'description'  => $object_def['description'],
					'is_system'    => 1,
				],
				[ '%s', '%s', '%s', '%s', '%d' ]
			);
			$object_id = (int) $wpdb->insert_id;
		}

		// Fields.
		$sort = 0;
		foreach ( $object_def['fields'] as $field_def ) {
			++$sort;
			$this->upsert_builtin_field( $object_id, $sort, $field_def );
		}
	}

	/**
	 * @param array<string, mixed> $field_def
	 */
	private function upsert_builtin_field( int $object_id, int $sort, array $field_def ): void {
		global $wpdb;

		$table = PARS_YAR_DB_PREFIX . 'object_fields';

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE object_id = %d AND api_name = %s",
				$object_id,
				$field_def['api_name']
			)
		);

		$data = [
			'label'       => $field_def['label'],
			'field_type'  => $field_def['type'],
			'is_required' => ! empty( $field_def['required'] ) ? 1 : 0,
			'is_unique'   => ! empty( $field_def['unique'] ) ? 1 : 0,
			'sort_order'  => $sort,
		];
		$format = [ '%s', '%s', '%d', '%d', '%d' ];

		if ( $existing > 0 ) {
			$wpdb->update( $table, $data, [ 'id' => $existing ], $format, [ '%d' ] );
		} else {
			$data['object_id'] = $object_id;
			$data['api_name']  = $field_def['api_name'];
			$wpdb->insert(
				$table,
				$data,
				array_merge( [ '%d', '%s' ], $format )
			);
		}
	}

	/**
	 * Get object id by API name.
	 */
	public function get_object_id( string $api_name ): int {
		global $wpdb;
		$table = PARS_YAR_DB_PREFIX . 'objects';
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE api_name = %s", $api_name )
		);
	}

	/**
	 * Get a list of all objects (id, api_name, label, label_plural).
	 *
	 * @return array<int, object>
	 */
	public function list_objects(): array {
		global $wpdb;
		$table = PARS_YAR_DB_PREFIX . 'objects';
		return (array) $wpdb->get_results( "SELECT id, api_name, label, label_plural FROM {$table} ORDER BY label ASC" );
	}

	/**
	 * Get fields for an object.
	 *
	 * @return array<int, object>
	 */
	public function get_fields( int $object_id ): array {
		global $wpdb;
		$table = PARS_YAR_DB_PREFIX . 'object_fields';
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, api_name, label, field_type, is_required, is_unique, ref_object_id, sort_order
				 FROM {$table} WHERE object_id = %d ORDER BY sort_order ASC, id ASC",
				$object_id
			)
		);
	}
}
