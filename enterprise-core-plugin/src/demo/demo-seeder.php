<?php
/**
 * DemoSeeder — populates sample records on first activation.
 *
 * @package ParsYar\Core\Demo
 */

declare(strict_types=1);

namespace ParsYar\Core\Demo;

use ParsYar\Core\ObjectEngine\RecordRepository;

defined( 'ABSPATH' ) || exit;

final class DemoSeeder {

	public function __construct( private readonly RecordRepository $records = new RecordRepository(
		new \ParsYar\Core\ObjectEngine\SchemaManager(),
		new \ParsYar\Core\Sanitizer\FieldSanitizer(),
		new \ParsYar\Core\Audit\AuditService( new \ParsYar\Core\Audit\AuditRepository() )
	) ) {}

	public function run(): void {
		// Sample Contacts.
		$contacts = [
			[ 'first_name' => 'علی', 'last_name' => 'محمدی',   'email' => 'ali@example.ir',    'phone' => '+982112345678', 'company' => 'فن‌آوران پارس' ],
			[ 'first_name' => 'زهرا','last_name' => 'کریمی',   'email' => 'zahra@example.ir',  'phone' => '+982187654321', 'company' => 'گروه صنعتی آوا' ],
			[ 'first_name' => 'رضا', 'last_name' => 'نوری',    'email' => 'reza@example.ir',   'phone' => '+982133112233', 'company' => 'بازرگانی نور'  ],
		];
		foreach ( $contacts as $c ) {
			$this->records->create( 'Contact', $c );
		}

		// Sample Leads.
		$leads = [
			[ 'full_name' => 'مریم احمدی', 'email' => 'm.ahmadi@lead.ir',   'phone' => '+989121112233', 'source' => 'وب‌سایت', 'score' => 75, 'status' => 'new' ],
			[ 'full_name' => 'حسین مرادی', 'email' => 'h.moradi@lead.ir',   'phone' => '+989356667788', 'source' => 'ایمیل',   'score' => 40, 'status' => 'contacted' ],
		];
		foreach ( $leads as $l ) {
			$this->records->create( 'Lead', $l );
		}

		// Sample Accounts.
		$accounts = [
			[ 'name' => 'شرکت پارس', 'industry' => 'فناوری',  'website' => 'https://pars.ir',  'tax_id' => '12345678901' ],
			[ 'name' => 'گروه آوا',  'industry' => 'تولید',   'website' => 'https://ava.ir',   'tax_id' => '98765432109' ],
		];
		foreach ( $accounts as $a ) {
			$this->records->create( 'Account', $a );
		}
	}
}
