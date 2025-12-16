<?php
/**
 * "MOJI RAČUNI" ENDPOINT U MOJ RAČUN
 *
 * OVAJ FILE:
 * - registrira rewrite endpoint `moji-racuni` (init),
 * - dodaje stavku menija "Moji računi" u WooCommerce Moj račun,
 * - ispisuje tablicu narudžbi s linkovima na PDF račune.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Account_Endpoint {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
        add_action( 'woocommerce_account_moji-racuni_endpoint', array( __CLASS__, 'render_endpoint' ) );
    }

    public static function register_endpoint() {
        add_rewrite_endpoint( 'moji-racuni', EP_ROOT | EP_PAGES );
    }

    public static function add_menu_item( $items ) {
        $new = array();

        foreach ( $items as $key => $label ) {
            if ( 'customer-logout' === $key ) {
                $new['moji-racuni'] = __( 'Moji računi', 'custom-invoices' );
            }
            $new[ $key ] = $label;
        }

        if ( ! isset( $new['moji-racuni'] ) ) {
            $new['moji-racuni'] = __( 'Moji računi', 'custom-invoices' );
        }

        return $new;
    }

    public static function render_endpoint() {
        if ( ! custom_invoices_is_woocommerce_active() ) {
            echo '<p>' . esc_html__( 'WooCommerce mora biti aktivan kako bi se prikazali računi.', 'custom-invoices' ) . '</p>';
            return;
        }

        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            echo '<p>' . esc_html__( 'Morate biti prijavljeni kako biste vidjeli svoje račune.', 'custom-invoices' ) . '</p>';
            return;
        }

        $orders = wc_get_orders( array(
            'customer' => $current_user_id,
            'limit'    => -1,
            'status'   => array_keys( wc_get_order_statuses() ),
            'orderby'  => 'date',
            'order'    => 'DESC',
        ) );

        $has_invoices = false;
        if ( $orders ) {
            foreach ( $orders as $order ) {
                $ids = $order->get_meta( '_custom_invoice_attachment_id' );
                if ( ! empty( $ids ) ) {
                    $has_invoices = true;
                    break;
                }
            }
        }

        if ( ! $orders || ! $has_invoices ) {
            echo '<p>' . esc_html__( 'Nema pronađenih računa.', 'custom-invoices' ) . '</p>';
            return;
        }

        echo '<h2>' . esc_html__( 'Moji računi', 'custom-invoices' ) . '</h2>';

        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders">
                <thead>
                    <tr>
                        <th>' . esc_html__( 'Narudžba', 'custom-invoices' ) . '</th>
                        <th>' . esc_html__( 'Datum', 'custom-invoices' ) . '</th>
                        <th>' . esc_html__( 'Ukupno', 'custom-invoices' ) . '</th>
                        <th>' . esc_html__( 'Računi', 'custom-invoices' ) . '</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ( $orders as $order ) {
            $attachment_ids_str = $order->get_meta( '_custom_invoice_attachment_id' );
            $attachment_ids     = array_filter( explode( ',', $attachment_ids_str ) );

            if ( ! empty( $attachment_ids ) ) {
                echo '<tr>
                        <td>#' . esc_html( $order->get_order_number() ) . '</td>
                        <td>' . esc_html( wc_format_datetime( $order->get_date_created() ) ) . '</td>
                        <td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td>
                        <td>';

                foreach ( $attachment_ids as $att_id ) {
                    $file_url = wp_get_attachment_url( $att_id );
                    if ( $file_url ) {
                        echo '<a href="' . esc_url( $file_url ) . '" class="button" target="_blank" style="display:block;margin-bottom:5px;font-size:12px;padding:5px 10px;">' . esc_html__( 'Preuzmi PDF', 'custom-invoices' ) . '</a>';
                    }
                }

                echo '</td></tr>';
            }
        }

        echo '</tbody></table>';
    }
}