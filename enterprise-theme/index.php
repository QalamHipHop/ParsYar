<?php
/**
 * Fallback template — redirects to the SPA page.
 *
 * @package ParsYar\Theme
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
wp_safe_redirect( home_url( '/pars-yar-app' ) );
exit;
