<?php
/**
 * REST API router.
 *
 * @package ParsYar\Rest
 */

declare(strict_types=1);

namespace ParsYar\Rest;

use ParsYar\Core\ObjectEngine\RecordRepository;
use ParsYar\Core\ObjectEngine\SchemaManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

final class RestRouter {

	public function __construct(
		private readonly SchemaManager $schema,
		private readonly RecordRepository $records
	) {}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		$namespace = 'pars-yar/v1';

		register_rest_route( $namespace, '/objects', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_objects' ],
			'permission_callback' => [ $this, 'can_read' ],
		] );

		register_rest_route( $namespace, '/objects/(?P<object>[a-zA-Z0-9_]+)/records', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'list_records' ],
				'permission_callback' => [ $this, 'can_read' ],
				'args'                => [
					'limit'  => [ 'default' => 50, 'type' => 'integer' ],
					'offset' => [ 'default' => 0,  'type' => 'integer' ],
				],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'create_record' ],
				'permission_callback' => [ $this, 'can_write' ],
			],
		] );

		register_rest_route( $namespace, '/objects/(?P<object>[a-zA-Z0-9_]+)/records/(?P<id>\d+)', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_record' ],
				'permission_callback' => [ $this, 'can_read' ],
			],
		] );
	}

	public function can_read(): bool {
		return current_user_can( 'manage_pars_yar' ) || current_user_can( 'manage_options' );
	}

	public function can_write(): bool {
		return current_user_can( 'manage_pars_yar' ) || current_user_can( 'manage_options' );
	}

	public function list_objects( WP_REST_Request $request ): WP_REST_Response {
		$objects = $this->schema->list_objects();
		$out     = [];
		foreach ( $objects as $o ) {
			$out[] = [
				'id'          => (int) $o->id,
				'api_name'    => (string) $o->api_name,
				'label'       => (string) $o->label,
				'label_plural'=> (string) $o->label_plural,
				'fields'      => array_map( static function ( $f ) {
					return [
						'id'         => (int) $f->id,
						'api_name'   => (string) $f->api_name,
						'label'      => (string) $f->label,
						'field_type' => (string) $f->field_type,
						'required'   => (bool) $f->is_required,
						'unique'     => (bool) $f->is_unique,
					];
				}, $this->schema->get_fields( (int) $o->id ) ),
			];
		}
		return new WP_REST_Response( $out );
	}

	public function list_records( WP_REST_Request $request ): WP_REST_Response {
		$object = (string) $request->get_param( 'object' );
		$limit  = (int) $request->get_param( 'limit' );
		$offset = (int) $request->get_param( 'offset' );

		$rows = $this->records->list( $object, $limit, $offset );
		return new WP_REST_Response( [ 'items' => $rows, 'count' => count( $rows ) ] );
	}

	public function get_record( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$object = (string) $request->get_param( 'object' );
		$id     = (int) $request->get_param( 'id' );
		$row    = $this->records->get( $object, $id );
		if ( null === $row ) {
			return new WP_Error( 'pars_yar_not_found', 'رکورد یافت نشد.', [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $row );
	}

	public function create_record( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$object = (string) $request->get_param( 'object' );
		$values = (array) $request->get_json_params();

		$id = $this->records->create( $object, $values );
		if ( $id <= 0 ) {
			return new WP_Error( 'pars_yar_create_failed', 'ساخت رکورد ناموفق بود.', [ 'status' => 400 ] );
		}
		return new WP_REST_Response( [ 'id' => $id ], 201 );
	}
}
