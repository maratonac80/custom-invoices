<?php
/**
 * SLANJE E-MAILOVA KUPCU SA RAČUNIMA
 *
 * OVAJ FILE:
 * - obrađuje AJAX zahtjev s narudžbe: `send_custom_invoice_email`,
 * - iz meta `_custom_invoice_attachment_id` uzima PDF račune,
 * - slaže attachment putanje + HTML listu linkova,
 * - poziva custom_invoices_get_email_html() (definiranu u customer-mail.php) da dobije HTML sadržaj,
 * - šalje wp_mail kupcu, dodaje napomenu u narudžbu.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Email_Sender {

    public static function init() {
        add_action( 'wp_ajax_send_custom_invoice_email', array( __CLASS__, 'ajax_send_order_email' ) );
    }

    public static function ajax_send_order_email() {
        if ( ! custom_invoices_is_woocommerce_active() ) {
            wp_send_json_error( __( 'WooCommerce nije aktivan.', 'custom-invoices' ) );
        }

        check_ajax_referer( 'send_invoice_email_nonce', 'security' );

        $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
        $order    = wc_get_order( $order_id );
        if ( ! $order ) {
            wp_send_json_error( __( 'Narudžba nije pronađena.', 'custom-invoices' ) );
        }

        $attachment_ids_str = $order->get_meta( '_custom_invoice_attachment_id' );
        $attachment_ids     = array_filter( explode( ',', $attachment_ids_str ) );

        if ( empty( $attachment_ids ) ) {
            wp_send_json_error( __( 'Nema dodanih računa.', 'custom-invoices' ) );
        }

        $attachments      = array();
        $links_list_items = '';

        foreach ( $attachment_ids as $att_id ) {
            $full_path = get_attached_file( $att_id );
            $file_url  = wp_get_attachment_url( $att_id );
            $filename  = basename( $full_path );
            if ( $full_path && file_exists( $full_path ) ) {
                $attachments[] = $full_path;
                $links_list_items .= '
                    <table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom:6px;">
                        <tr>
                            <td width="20" style="vertical-align:middle;padding-right:5px;font-size:16px;line-height:1;">📄</td>
                            <td style="vertical-align:middle;">
                                <a href="' . esc_url( $file_url ) . '" style="color:#005ea1;text-decoration:none;font-size:13px;">
                                    <span style="font-weight:600;">' . esc_html( $filename ) . '</span>
                                </a>
                            </td>
                        </tr>
                    </table>';
            }
        }

        if ( empty( $attachments ) ) {
            wp_send_json_error( __( 'Datoteke nisu pronađene na serveru.', 'custom-invoices' ) );
        }

        if ( ! function_exists( 'custom_invoices_get_email_html' ) ) {
            wp_send_json_error( __( 'Funkcija za generiranje e-mail HTML-a nije pronađena.', 'custom-invoices' ) );
        }

        $email_content = custom_invoices_get_email_html( $order, $attachments, $links_list_items );
        if ( empty( $email_content ) ) {
            wp_send_json_error( __( 'Ne mogu generirati e-mail sadržaj.', 'custom-invoices' ) );
        }

        add_filter( 'wp_mail_content_type', 'custom_invoices_mail_content_type_html' );

        $lang = get_option( 'custom_invoices_email_language', 'hr' );
        if ( isset( $GLOBALS['custom_invoices_last_email_subject'] ) ) {
            $subject = $GLOBALS['custom_invoices_last_email_subject'];
        } else {
            $subject = ( $lang === 'en' )
                ? sprintf( 'Your invoice for order #%s', $order->get_order_number() )
                : sprintf( 'Vaš račun za narudžbu #%s', $order->get_order_number() );
        }

        $sent = wp_mail( $order->get_billing_email(), $subject, $email_content, '', $attachments );
        remove_filter( 'wp_mail_content_type', 'custom_invoices_mail_content_type_html' );

        if ( $sent ) {
            $order->add_order_note( __( 'Prilagođeni email s računima poslan kupcu.', 'custom-invoices' ) );
            wp_send_json_success( __( 'Email je uspješno poslan!', 'custom-invoices' ) );
        } else {
            wp_send_json_error( __( 'Slanje emaila nije uspjelo.', 'custom-invoices' ) );
        }
    }
}