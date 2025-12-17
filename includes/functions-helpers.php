<?php
/**
 * HELPER FUNKCIJE ZA CUSTOM INVOICES
 *
 * - provjera je li WooCommerce aktivan,
 * - dohvaćanje defaultnih opcija,
 * - helperi za mail i logiranje,
 * - provjera HPOS/compat moda + admin notice-i.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provjera je li WooCommerce aktivan.
 *
 * @return bool
 */
function custom_invoices_is_woocommerce_active() {
    if ( class_exists( 'WooCommerce' ) ) {
        return true;
    }

    // Fallback – provjera preko active_plugins.
    $active_plugins = (array) get_option( 'active_plugins', array() );

    if ( is_multisite() ) {
        $network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
        $network_plugins = array_keys( $network_plugins );

        $active_plugins = array_merge( $active_plugins, $network_plugins );
    }

    foreach ( $active_plugins as $plugin ) {
        if ( false !== strpos( $plugin, 'woocommerce.php' ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Defaultne opcije plugina – postavljaju se na activation hooku.
 */
function custom_invoices_set_default_options() {

    if ( get_option( 'custom_invoices_default_subject', '' ) === '' ) {
        update_option( 'custom_invoices_default_subject', 'Vaš račun' );
    }

    if ( get_option( 'custom_invoices_default_heading', '' ) === '' ) {
        update_option( 'custom_invoices_default_heading', 'Poštovani, u privitku Vam šaljemo račun.' );
    }

    if ( get_option( 'custom_invoices_default_footer', '' ) === '' ) {
        update_option( 'custom_invoices_default_footer', 'Hvala na kupnji!' );
    }

    if ( get_option( 'custom_invoices_default_logo_id', '' ) === '' ) {
        update_option( 'custom_invoices_default_logo_id', '' );
    }

    if ( get_option( 'custom_invoices_default_contact_email', '' ) === '' ) {
        update_option( 'custom_invoices_default_contact_email', 'yourmail@yourmail.com' );
    }

    if ( get_option( 'custom_invoices_default_contact_phone', '' ) === '' ) {
        update_option( 'custom_invoices_default_contact_phone', '+385 00 000 000' );
    }
}

/**
 * Admin notice ako WooCommerce nije aktivan.
 */
function custom_invoices_admin_notice_wc_missing() {
    if ( ! custom_invoices_is_woocommerce_active() ) {
        echo '<div class="notice notice-error"><p><strong>Custom Invoices</strong> ' .
             esc_html__( 'zahtijeva da WooCommerce bude instaliran i aktivan.', 'custom-invoices' ) .
             '</p></div>';
    }
}

/**
 * Helper za wp_mail: HTML content type.
 */
function custom_invoices_mail_content_type_html() {
    return 'text/html';
}

/**
 * Provjera WooCommerce HPOS i compatibility moda.
 *
 * @return array {
 *   @type bool $hpos_enabled        Je li HPOS uključen.
 *   @type bool $compat_mode_enabled Je li uključen sync s klasičnim posts storageom.
 * }
 */
function custom_invoices_get_hpos_state() {
    $result = array(
        'hpos_enabled'        => false,
        'compat_mode_enabled' => true, // konzervativno pretpostavi da je OK
    );

    if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
        return $result;
    }

    $util = '\Automattic\WooCommerce\Utilities\OrderUtil';

    if ( method_exists( $util, 'custom_orders_table_usage_is_enabled' ) ) {
        $result['hpos_enabled'] = $util::custom_orders_table_usage_is_enabled();
    }

    if ( method_exists( $util, 'orders_table_is_sync_enabled' ) ) {
        $result['compat_mode_enabled'] = $util::orders_table_is_sync_enabled();
    }

    return $result;
}

/**
 * Admin notice: upozorenje za HPOS bez compatibility moda.
 */
function custom_invoices_admin_notice_hpos_compat() {

    // Samo u adminu i samo za korisnike koji mogu uređivati Woo postavke.
    if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    if ( ! function_exists( 'custom_invoices_get_hpos_state' ) ) {
        return;
    }

    $state = custom_invoices_get_hpos_state();

    // Ako HPOS nije uključen ili je sync već upaljen, nema upozorenja.
    if ( ! $state['hpos_enabled'] || $state['compat_mode_enabled'] ) {
        return;
    }

    $settings_url = admin_url( 'admin.php?page=wc-settings&tab=advanced&section=features' );

    echo '<div class="notice notice-warning">';
    echo '<p><strong>Custom Invoices</strong>: ';
    echo esc_html__( 'WooCommerce High-performance order storage (HPOS) je uključen bez compatibility mode opcije. ', 'custom-invoices' );
    echo esc_html__( 'Zbog toga lista narudžbi u Custom Invoices pluginu možda neće prikazivati sve narudžbe.', 'custom-invoices' );
    echo '</p>';
    echo '<p>';
    echo esc_html__( 'Preporuka: u WooCommerce → Settings → Advanced → Features uključite opciju "Compatibility mode (Synchronize orders between High-performance order storage and WordPress posts storage)".', 'custom-invoices' );
    echo '</p>';
    echo '<p><a href="' . esc_url( $settings_url ) . '" class="button button-primary">';
    echo esc_html__( 'Otvori WooCommerce Features postavke', 'custom-invoices' );
    echo '</a></p>';
    echo '</div>';
}