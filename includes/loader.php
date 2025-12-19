<?php
/**
 * LOADER PLUGINA
 *
 * OVAJ FILE:
 * - centralno učitava sve klase i helper funkcije plugina,
 * - inicijalizira svaki modul (admin ekran za e-mail, meta box za narudžbe,
 *   slanje e-maila, "Moji računi" endpoint, lista narudžbi),
 * - na activation hooku postavlja default opcije i zakazuje flush rewrite rules,
 * - na update plugina također zakazuje flush rewrite rules,
 * - na deactivation hooku radi flush_rewrite_rules().
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Updater (učitava se ODMAH)
require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/class-custom-invoices-updater.php';
Custom_Invoices_Updater::init();

class Custom_Invoices_Loader {

    /**
     * INIT – normalno učitavanje plugina
     */
    public static function init() {

        // Textdomain
        load_plugin_textdomain(
            'custom-invoices',
            false,
            dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ) . '/languages'
        );

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

        /**
         * FLUSH REWRITE RULES NAKON UPDATE-a PLUGINA
         * (update NE zove activation hook!)
         */
        add_action( 'upgrader_process_complete', array( __CLASS__, 'maybe_flush_after_update' ), 10, 2 );
    }

    /**
     * ACTIVATION
     */
    public static function activate( $network_wide = false ) {

        // Activation hook se izvršava prije init(), zato ručno includamo helper
        require_once CUSTOM_INVOICES_PLUGIN_PATH . 'includes/functions-helpers.php';

        // Postavi defaultne opcije
        custom_invoices_set_default_options();

        // Zakazujemo flush (ne direktno, jer endpoint još nije registriran)
        update_option( 'custom_invoices_flush_rewrite', '1', false );
    }

    /**
     * DEACTIVATION
     */
    public static function deactivate( $network_wide = false ) {
        flush_rewrite_rules();
    }

    /**
     * Nakon UPDATE-a plugina – zakazujemo flush rewrite rules
     */
    public static function maybe_flush_after_update( $upgrader, $hook_extra ) {

        if ( empty( $hook_extra['type'] ) || $hook_extra['type'] !== 'plugin' ) {
            return;
        }

        if ( empty( $hook_extra['plugins'] ) || ! is_array( $hook_extra['plugins'] ) ) {
            return;
        }

        $me = plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE );

        if ( in_array( $me, $hook_extra['plugins'], true ) ) {
            update_option( 'custom_invoices_flush_rewrite', '1', false );
        }
    }
}
