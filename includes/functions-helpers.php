<?php
/**
 * HELPER FUNKCIJE ZA CIJELI PLUGIN
 *
 * OVAJ FILE:
 * - sadrži zajedničke pomoćne funkcije:
 *   - provjera je li WooCommerce aktivan,
 *   - migracija starih aimus_ci_ opcija na custom_invoices_ (pri aktivaciji),
 *   - postavljanje default opcija,
 *   - admin notice ako WooCommerce nije aktivan,
 *   - helper za wp_mail content_type (HTML).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Provjerava je li WooCommerce aktivan.
 */
function custom_invoices_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Migracija starih aimus_ci_ opcija i postavljanje default vrijednosti.
 * Poziva se u Custom_Invoices_Loader::activate().
 */
function custom_invoices_setup_default_options() {

    /**
     * PRIVREMENI / JEDNOKRATNI CLEANUP
     *
     * Ovaj blok briše sve postojeće custom_invoices_* opcije kako bi se
     * povukle nove generičke vrijednosti iz koda.
     *
     * Možeš ga ostaviti ili obrisati nakon što jednom odradiš aktivaciju
     * s ovim kodom.
     */
    $to_delete = array(
        'custom_invoices_email_language',
        'custom_invoices_footer_company',
        'custom_invoices_footer_address',
        'custom_invoices_footer_tax_id',
        'custom_invoices_primary_color',
        'custom_invoices_default_shop_name',
        'custom_invoices_default_header_bg_color',
        'custom_invoices_default_logo_url',
        'custom_invoices_default_content_intro',
        'custom_invoices_default_contact_email',
        'custom_invoices_default_contact_phone',
        'custom_invoices_help_title',
        'custom_invoices_help_line',
        'custom_invoices_email_mode',
        'custom_invoices_email_template_html',
        'custom_invoices_test_email',
    );

    foreach ( $to_delete as $opt ) {
        delete_option( $opt );
    }

    // 1) MIGRACIJA starih aimus_ci_ opcija, ako postoje
    $map = array(
        'custom_invoices_email_language'          => 'aimus_ci_email_language',
        'custom_invoices_footer_company'          => 'aimus_ci_footer_company',
        'custom_invoices_footer_address'          => 'aimus_ci_footer_address',
        'custom_invoices_footer_tax_id'           => 'aimus_ci_footer_tax_id',
        'custom_invoices_primary_color'           => 'aimus_ci_primary_color',
        'custom_invoices_default_shop_name'       => 'aimus_ci_default_shop_name',
        'custom_invoices_default_header_bg_color' => 'aimus_ci_default_header_bg_color',
        'custom_invoices_default_logo_url'        => 'aimus_ci_default_logo_url',
        'custom_invoices_default_content_intro'   => 'aimus_ci_default_content_intro',
        'custom_invoices_default_contact_email'   => 'aimus_ci_default_contact_email',
        'custom_invoices_default_contact_phone'   => 'aimus_ci_default_contact_phone',
        'custom_invoices_help_title'              => 'aimus_ci_help_title',
        'custom_invoices_help_line'               => 'aimus_ci_help_line',
        'custom_invoices_email_mode'              => 'aimus_ci_email_mode',
        'custom_invoices_email_template_html'     => 'aimus_ci_email_template_html',
        'custom_invoices_test_email'              => 'aimus_ci_test_email',
    );

    foreach ( $map as $new => $old ) {
        $new_val = get_option( $new, null );
        if ( $new_val === null ) {
            $old_val = get_option( $old, null );
            if ( $old_val !== null ) {
                update_option( $new, $old_val );
            }
        }
    }

    // 2) Defaulti za nove opcije (ako još nisu postavljeni)

    // Jezik – HR kao default
    if ( get_option( 'custom_invoices_email_language', '' ) === '' ) {
        update_option( 'custom_invoices_email_language', 'hr' );
    }

    // GENERIČKI PODACI ZA FOOTER
    if ( get_option( 'custom_invoices_footer_company', '' ) === '' ) {
        update_option( 'custom_invoices_footer_company', 'Your Company d.o.o.' );
    }
    if ( get_option( 'custom_invoices_footer_address', '' ) === '' ) {
        update_option( 'custom_invoices_footer_address', 'Ulica 1, 10000 Zagreb, Hrvatska' );
    }
    if ( get_option( 'custom_invoices_footer_tax_id', '' ) === '' ) {
        update_option( 'custom_invoices_footer_tax_id', 'OIB: 31111111111' );
    }

    // Primarna boja
    if ( get_option( 'custom_invoices_primary_color', '' ) === '' ) {
        update_option( 'custom_invoices_primary_color', '#2E658B' );
    }

    // Kontakt e-mail i telefon – generički
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
// DEBUG: ispiši sve meta vrijednosti za narudžbu 1234 jednom u log
add_action( 'init', function() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        return;
    }

    // promijeni 1234 u ID narudžbe koja SIGURNO ima uploadan račun
    $debug_order_id = 7629;

    if ( isset( $_GET['ci_meta_debug'] ) ) {
        $all_meta = get_post_meta( $debug_order_id );
        error_log( 'CI DEBUG META for order ' . $debug_order_id . ': ' . print_r( $all_meta, true ) );
        wp_die( 'CI meta debug done. Pogledaj debug.log.' );
    }
});