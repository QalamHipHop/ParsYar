<?php
/**
 * Admin menu and shell pages.
 *
 * @package ParsYar\Admin
 */

declare(strict_types=1);

namespace ParsYar\Admin;

defined( 'ABSPATH' ) || exit;

final class AdminMenu {

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function add_menu(): void {
		$cap = 'manage_pars_yar';
		add_menu_page(
			'پارس‌یار',
			'پارس‌یار',
			$cap,
			'pars-yar',
			[ $this, 'render_dashboard' ],
			'dashicons-chart-line',
			30
		);
		add_submenu_page( 'pars-yar', 'داشبورد',     'داشبورد',  $cap, 'pars-yar',         [ $this, 'render_dashboard' ] );
		add_submenu_page( 'pars-yar', 'مخاطبین',     'مخاطبین',  $cap, 'pars-yar-contacts', [ $this, 'render_contacts' ] );
		add_submenu_page( 'pars-yar', 'سرنخ‌ها',      'سرنخ‌ها',   $cap, 'pars-yar-leads',    [ $this, 'render_leads' ] );
		add_submenu_page( 'pars-yar', 'حساب‌ها',      'حساب‌ها',   $cap, 'pars-yar-accounts', [ $this, 'render_accounts' ] );
		add_submenu_page( 'pars-yar', 'گزارش حسابرسی', 'حسابرسی',  $cap, 'pars-yar-audit',   [ $this, 'render_audit' ] );
	}

	public function enqueue_assets( string $hook ): void {
		if ( strpos( $hook, 'pars-yar' ) === false ) {
			return;
		}
		wp_enqueue_style(
			'pars-yar-admin',
			PARS_YAR_URL . 'assets/admin.css',
			[],
			PARS_YAR_VERSION
		);
		wp_enqueue_script(
			'pars-yar-admin',
			PARS_YAR_URL . 'assets/admin.js',
			[ 'wp-api-fetch' ],
			PARS_YAR_VERSION,
			true
		);
		wp_localize_script( 'pars-yar-admin', 'parsYar', [
			'rest'  => esc_url_raw( rest_url( 'pars-yar/v1/' ) ),
			'nonce' => wp_create_nonce( 'wp_rest' ),
		] );
	}

	public function render_dashboard(): void  { $this->render_page( 'Dashboard' ); }
	public function render_contacts(): void   { $this->render_page( 'Contact' ); }
	public function render_leads(): void      { $this->render_page( 'Lead' ); }
	public function render_accounts(): void   { $this->render_page( 'Account' ); }
	public function render_audit(): void      { $this->render_page( 'Audit' ); }

	private function render_page( string $view ): void {
		echo '<div class="wrap pars-yar-wrap">';
		echo '<h1 class="pars-yar-h1">پارس‌یار — ' . esc_html( $view ) . '</h1>';
		echo '<div id="pars-yar-app" data-view="' . esc_attr( $view ) . '"></div>';
		echo '<noscript><p>این داشبورد به جاوااسکریپت نیاز دارد.</p></noscript>';
		echo '</div>';
	}
}
