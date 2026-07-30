<?php
/**
 * SPA template — minimal shell hosting the React mount point.
 *
 * @package ParsYar\Theme
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>پارس‌یار</title>
	<?php wp_head(); ?>
</head>
<body class="pars-yar-spa">
	<div id="pars-yar-root"></div>
	<noscript>
		<p style="padding:24px;text-align:center;">این داشبورد به جاوااسکریپت نیاز دارد.</p>
	</noscript>
	<?php wp_footer(); ?>
</body>
</html>
