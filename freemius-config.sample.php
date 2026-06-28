<?php
/**
 * Freemius product config — RENAME this file to `freemius-config.php` to go live.
 *
 * Steps:
 *   1. Create the plugin as a product in your Freemius dashboard
 *      (https://dashboard.freemius.com) and add a paid "Pro" plan.
 *   2. Copy the Plugin ID and Public Key from Settings → Keys.
 *   3. Paste them below and rename this file to `freemius-config.php`.
 *   4. Vendor the SDK:  ../wp-plugin-factory/bin/add-freemius.sh   (run in the plugin dir)
 *
 * Until both `freemius-config.php` and `includes/freemius/start.php` exist, the
 * plugin runs free-only and none of this loads.
 *
 * @package ZubFactory
 */

defined( 'ABSPATH' ) || exit;

return array(
	'id'         => '0000000',                 // Freemius Plugin ID.
	'public_key' => 'pk_xxxxxxxxxxxxxxxxxxxxx', // Freemius Public Key.
	'plan'       => 'pro',                      // Slug of your paid plan.
);
