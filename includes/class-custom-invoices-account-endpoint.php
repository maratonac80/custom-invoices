<?php
/**
 * "MY INVOICES" ENDPOINT IN MY ACCOUNT
 *
 * This file:
 * - Registers the rewrite endpoint `my-invoices` (init),
 * - Adds the "Invoices" entry in the WooCommerce "My Account" menu,
 * - Renders the table of orders with links to PDF invoices.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Account_Endpoint {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_endpoint' ) );
        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );
        add_action( 'woocommerce_account_my-invoices_endpoint', array( __CLASS__, 'render_endpoint' ) );
        add_action( 'wp_footer', array( __CLASS__, 'add_custom_styles' ) ); // Dodaje stilove u footer
    }

    public static function register_endpoint() {
        add_rewrite_endpoint( 'my-invoices', EP_ROOT | EP_PAGES );
    }

    public static function add_menu_item( $items ) {
        $new = array();

        foreach ( $items as $key => $label ) {
            if ( 'customer-logout' === $key ) {
                $new['my-invoices'] = __( 'Invoices', 'custom-invoices' );
            }
            $new[ $key ] = $label;
        }

        if ( ! isset( $new['my-invoices'] ) ) {
            $new['my-invoices'] = __( 'Invoices', 'custom-invoices' );
        }

        return $new;
    }

    public static function add_custom_styles() {
        // Dodaje CSS direktno u footer stranice
        echo '<style>
            .woocommerce-orders-table th:last-child,
            .woocommerce-orders-table td:last-child {
                text-align: right;
                vertical-align: middle;
            }
            .woocommerce-orders-table td:last-child a {
                display: inline-flex;
                align-items: center;
            }
            .woocommerce-orders-table td:last-child img {
                margin-right: 5px;
            }
        </style>';
    }

    public static function render_endpoint() {
        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            echo '<p>' . esc_html__( 'You need to log in to view invoices.', 'custom-invoices' ) . '</p>';
            return;
        }

        $orders = wc_get_orders( array(
            'customer' => $current_user_id,
            'limit'    => -1,
            'orderby'  => 'date',
            'order'    => 'DESC',
        ) );

        if ( empty( $orders ) ) {
            echo '<p>' . esc_html__( 'No invoices found.', 'custom-invoices' ) . '</p>';
            return;
        }
        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders">
            <thead>
                <tr>
                    <th>' . esc_html__( 'Order Number', 'custom-invoices' ) . '</th>
                    <th>' . esc_html__( 'Invoices', 'custom-invoices' ) . '</th>
                </tr>
            </thead>
            <tbody>';

        foreach ( $orders as $order ) {
            $attachment_ids_str = $order->get_meta( '_custom_invoice_attachment_id' );
            $attachment_ids = array_filter( explode( ',', $attachment_ids_str ) );

            if ( empty( $attachment_ids ) ) {
                echo '<tr><td>#' . esc_html( $order->get_order_number() ) . '</td><td>' . esc_html__( 'No invoices found.', 'custom-invoices' ) . '</td></tr>';
                continue;
            }

            echo '<tr><td>#' . esc_html( $order->get_order_number() ) . '</td><td>';
            foreach ( $attachment_ids as $att_id ) {
                $file_url = wp_get_attachment_url( trim( $att_id ) );
                if ( $file_url ) {
                    $file_name = basename( $file_url ); // Samo ime datoteke, npr. Racun_br_29_POSL2_01.pdf
                    $icon_url = 'https://aimus.eu/wp-content/plugins/custom-invoice/images/pdf-icon.png'; // Točan URL PDF ikone
                    
                    echo '<div style="margin-bottom:5px;">';
                    echo '<a href="' . esc_url( $file_url ) . '" target="_blank" style="margin-right: 5px; text-decoration: none;">';
                    echo '<img src="' . esc_url( $icon_url ) . '" alt="PDF Icon" style="width:16px; height:16px; margin-right:5px;">';
                    echo esc_html( $file_name );
                    echo '</a>';
                    echo '</div>';
                } else {
                    echo esc_html__( 'Invalid or missing file.', 'custom-invoices' ) . '<br>';
                }
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }
}