<?php
/**
 * LOADER PLUGINA
 *
 * OVAJ FILE:
 * - centralno učitava sve klase i helper funkcije plugina,
 * - inicijalizira svaki modul (admin ekran za e-mail, meta box za narudžbe, slanje e-maila, "Moji računi" endpoint, lista narudžbi),
 * - na activation hooku podešava početne opcije i rewrite endpoint,
 * - na deactivation hooku radi flush_rewrite_rules().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Loader {

    public static function init() {

        // Textdomain
        load_plugin_textdomain( 'custom-invoices', false, dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ) . '/languages' );

        // Helpers
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/functions-helpers.php';

        // Moduli
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/class-custom-invoices-admin-email-template.php';
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/class-custom-invoices-order-metabox.php';
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/class-custom-invoices-email-sender.php';
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/class-custom-invoices-account-endpoint.php';
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/class-custom-invoices-orders-list.php';

        // Inicijalizacija modula
        Custom_Invoices_Admin_Email_Template::init();
        Custom_Invoices_Order_Metabox::init();
        Custom_Invoices_Email_Sender::init();
        Custom_Invoices_Account_Endpoint::init();
        Custom_Invoices_Orders_List::init();

        // Admin notice ako WooCommerce nije aktivan
        add_action( 'admin_notices', 'custom_invoices_admin_notice_wc_missing' );

        // Admin notice za HPOS bez compatibility moda
        add_action( 'admin_notices', 'custom_invoices_admin_notice_hpos_compat' );
    }

    public static function activate( $network_wide = false ) {

        // Activation hook se izvršava prije init(), zato ručno includamo što nam treba.
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/functions-helpers.php';

        // Postavi defaultne opcije
        custom_invoices_set_default_options();

        // Ako WooCommerce endpoint za "Moji računi" treba rewrite rules
        if ( function_exists( 'custom_invoices_is_woocommerce_active' ) && custom_invoices_is_woocommerce_active() ) {
            // Include the endpoint class and register endpoint prije flush-a
            require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/class-custom-invoices-account-endpoint.php';
            Custom_Invoices_Account_Endpoint::register_endpoint();
            flush_rewrite_rules();
        }
    }

    public static function deactivate( $network_wide = false ) {
        flush_rewrite_rules();
    }
}