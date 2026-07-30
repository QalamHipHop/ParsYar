<?php
/**
 * FieldSanitizer — coerce and validate values based on field type.
 *
 * @package ParsYar\Core\Sanitizer
 */

declare(strict_types=1);

namespace ParsYar\Core\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Validates incoming field values against the object's field definitions
 * and coerces them into the proper storage representation.
 */
final class FieldSanitizer {

	/**
	 * @param array<int, object>    $fields Field rows from SchemaManager.
	 * @param array<string, mixed>  $input  Keyed by field api_name.
	 * @return array{values: array<string, mixed>, errors: array<string, string>}
	 */
	public function validate_and_coerce( array $fields, array $input ): array {
		$values = [];
		$errors = [];

		foreach ( $fields as $f ) {
			$api  = (string) $f->api_name;
			$type = (string) $f->field_type;
			$raw  = $input[ $api ] ?? null;

			if ( ( $raw === null || $raw === '' ) && ! empty( $f->is_required ) ) {
				$errors[ $api ] = sprintf( 'فیلد %s الزامی است.', $f->label );
				continue;
			}
			if ( $raw === null || $raw === '' ) {
				continue;
			}

			switch ( $type ) {
				case 'email':
					$sanitized = sanitize_email( (string) $raw );
					if ( $sanitized === '' || ! is_email( $sanitized ) ) {
						$errors[ $api ] = 'ایمیل نامعتبر است.';
					}
					$values[ $api ] = $sanitized;
					break;

				case 'phone':
					$digits = preg_replace( '/[^0-9+]/', '', (string) $raw ) ?? '';
					$values[ $api ] = $digits;
					break;

				case 'url':
					$values[ $api ] = esc_url_raw( (string) $raw );
					break;

				case 'number':
					if ( ! is_numeric( $raw ) ) {
						$errors[ $api ] = 'مقدار عددی نامعتبر است.';
						continue 2;
					}
					$values[ $api ] = (float) $raw;
					break;

				case 'date':
					$ts = strtotime( (string) $raw );
					if ( false === $ts ) {
						$errors[ $api ] = 'تاریخ نامعتبر است.';
						continue 2;
					}
					$values[ $api ] = gmdate( 'Y-m-d H:i:s', $ts );
					break;

				case 'picklist':
					$values[ $api ] = sanitize_text_field( (string) $raw );
					break;

				case 'text':
				default:
					$values[ $api ] = sanitize_text_field( (string) $raw );
					break;
			}
		}

		return [ 'values' => $values, 'errors' => $errors ];
	}
}
