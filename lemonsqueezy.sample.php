<?php
/**
 * Lemon Squeezy config — RENAME this file to `lemonsqueezy.php` to go live.
 *
 * Steps (per plugin):
 *   1. In your Lemon Squeezy dashboard create a Product for this plugin and a
 *      paid variant. Turn ON "License keys" for the variant
 *      (Product → variant → License keys → Enable).
 *   2. Copy the numeric Product ID (Product → Share / the product URL, or via the
 *      API). Put it below as 'product_id' — this locks keys to THIS plugin.
 *   3. Copy the variant's Buy URL (Share → Checkout link) into 'buy_url'.
 *   4. Rename this file to `lemonsqueezy.php`.
 *
 * No API/secret key is needed — the License API (activate/validate/deactivate)
 * is public and only uses the customer's license key. The factory core then
 * shows a license box on the settings page and flips the Pro gate on activation.
 *
 * @package ZubFactory
 */

defined( 'ABSPATH' ) || exit;

return array(
	'product_id' => 0,                                       // Numeric Lemon Squeezy Product ID (locks keys to this plugin).
	'buy_url'    => 'https://printparty.lemonsqueezy.com/buy/XXXXXXXX', // Checkout link for the Pro variant.
);
