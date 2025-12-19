<?php
/*
 Plugin Name: Custom Invoices
 Plugin URI: https://github.com/maratonac80/custom-invoices
 Description: Plugin for custom invoice handling.
 Version: v1.0.5
 Author: Zoran Filipović
 Author URI: https://peroneus.hr
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