<?php
/**
 * ObjectRegistry — built-in object definitions.
 *
 * @package ParsYar\ObjectEngine
 */

declare(strict_types=1);

namespace ParsYar\ObjectEngine;

defined( 'ABSPATH' ) || exit;

/**
 * Holds the canonical list of system-defined objects.
 * On every request, register_builtins() ensures they exist in the DB
 * (inserting only if missing — does not overwrite user edits).
 */
final class ObjectRegistry {

	/**
	 * Cached in-memory list of built-in object definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function builtins(): array {
		return [
			[
				'api_name'     => 'Contact',
				'label'        => 'مخاطب',
				'label_plural' => 'مخاطبین',
				'description'  => 'افراد حقیقی و حقوقی مرتبط با کسب‌وکار.',
				'fields'       => [
					[ 'api_name' => 'first_name',  'label' => 'نام',         'type' => 'text',     'required' => true ],
					[ 'api_name' => 'last_name',   'label' => 'نام خانوادگی', 'type' => 'text',     'required' => true ],
					[ 'api_name' => 'email',       'label' => 'ایمیل',       'type' => 'email',    'required' => false, 'unique' => true ],
					[ 'api_name' => 'phone',       'label' => 'تلفن',        'type' => 'phone',    'required' => false ],
					[ 'api_name' => 'company',     'label' => 'شرکت',        'type' => 'text',     'required' => false ],
					[ 'api_name' => 'national_id', 'label' => 'کد ملی',      'type' => 'text',     'required' => false, 'unique' => true ],
					[ 'api_name' => 'lifecycle',   'label' => 'چرخه حیات',   'type' => 'picklist', 'required' => false ],
				],
			],
			[
				'api_name'     => 'Lead',
				'label'        => 'سرنخ',
				'label_plural' => 'سرنخ‌ها',
				'description'  => 'مشتریان بالقوه قبل از تبدیل شدن به فرصت یا مخاطب.',
				'fields'       => [
					[ 'api_name' => 'full_name',  'label' => 'نام کامل',   'type' => 'text',     'required' => true ],
					[ 'api_name' => 'email',      'label' => 'ایمیل',      'type' => 'email',    'required' => false ],
					[ 'api_name' => 'phone',      'label' => 'تلفن',       'type' => 'phone',    'required' => false ],
					[ 'api_name' => 'source',     'label' => 'منبع',       'type' => 'picklist', 'required' => false ],
					[ 'api_name' => 'score',      'label' => 'امتیاز',     'type' => 'number',   'required' => false ],
					[ 'api_name' => 'status',     'label' => 'وضعیت',      'type' => 'picklist', 'required' => false ],
				],
			],
			[
				'api_name'     => 'Account',
				'label'        => 'حساب',
				'label_plural' => 'حساب‌ها',
				'description'  => 'حساب‌های سازمانی (شرکت‌ها و سازمان‌ها).',
				'fields'       => [
					[ 'api_name' => 'name',       'label' => 'نام حساب',  'type' => 'text',     'required' => true ],
					[ 'api_name' => 'industry',   'label' => 'صنعت',      'type' => 'picklist', 'required' => false ],
					[ 'api_name' => 'website',    'label' => 'وب‌سایت',   'type' => 'url',      'required' => false ],
					[ 'api_name' => 'tax_id',     'label' => 'شناسه مالیاتی', 'type' => 'text', 'required' => false, 'unique' => true ],
				],
			],
		];
	}

	/**
	 * Ensure all built-in objects exist in the database.
	 */
	public function register_builtins(): void {
		( new SchemaManager() )->register_builtin_objects();
	}
}
