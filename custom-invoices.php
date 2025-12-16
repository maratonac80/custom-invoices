<?php
/**
 * Plugin Name: Custom Invoices
 * Description: Višestruki PDF upload za WooCommerce narudžbe, prilagođeni HTML e-mail i kartica "Moji računi" u WooCommerce -> Moj račun.
 * Author: (tvoj naziv)
 * Version: 3.0.0
 * Text Domain: custom-invoices
 * Domain Path: /languages
 * Requires Plugins: woocommerce
 *
 * OVAJ FILE:
 * - je Bootstrap plugina (jedini s plugin headerom),
 * - definira konstante za putanju i URL plugina,
 * - uključuje loader (includes/loader.php) i customer-mail.php,
 * - registrira activation/deactivation hookove,
 * - pokreće inicijalizaciju svih modula preko Custom_Invoices_Loader::init().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Konstante plugina
 */
define( 'CUSTOM_INVOICES_PLUGIN_FILE', __FILE__ );
define( 'CUSTOM_INVOICES_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'CUSTOM_INVOICES_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

/**
 * HTML/CSS za e-mail predložak (tvoj postojeći file).
 */
require_once CUSTOM_INVOICES_PLUGIN_PATH . 'customer-mail.php';

/**
 * Loader – centralno učitava sve module.
 */
require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/loader.php';

register_activation_hook( __FILE__, array( 'Custom_Invoices_Loader', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Custom_Invoices_Loader', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'Custom_Invoices_Loader', 'init' ) );