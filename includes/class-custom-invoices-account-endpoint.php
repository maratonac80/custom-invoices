<?php
/**
 * "MY INVOICES" ENDPOINT IN MY ACCOUNT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Account_Endpoint {

    const ENDPOINT     = 'my-invoices';
    const FLUSH_OPTION = 'custom_invoices_flush_rewrite';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register_endpoint' ) );

        // IMPORTANT: make Woo aware of the endpoint query var
        add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'add_query_vars' ) );

        add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'add_menu_item' ) );

        // Endpoint content
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render_endpoint' ) );

        // Flush rewrite rules once (after activate/update)
        add_action( 'wp_loaded', array( __CLASS__, 'maybe_flush_rewrite_rules' ), 20 );

        // Styles only where needed
        add_action( 'wp_footer', array( __CLASS__, 'add_custom_styles' ) );
    }

    public static function register_endpoint() {
        add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
    }

    public static function add_query_vars( $vars ) {
        $vars[ self::ENDPOINT ] = self::ENDPOINT;
        return $vars;
    }

    public static function add_menu_item( $items ) {
        $label = __( 'Invoices', 'custom-invoices' );
        $new   = array();

        foreach ( $items as $key => $val ) {
            // Insert before logout
            if ( 'customer-logout' === $key ) {
                $new[ self::ENDPOINT ] = $label;
            }
            $new[ $key ] = $val;
        }

        if ( ! isset( $new[ self::ENDPOINT ] ) ) {
            $new[ self::ENDPOINT ] = $label;
        }

        return $new;
    }

    public static function add_custom_styles() {
        // Only on My Account area to avoid polluting the whole site
        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }

        echo '<style>
            .woocommerce-orders-table th:last-child,
            .woocommerce-orders-table td:last-child {
                text-align: left;
                vertical-align: top;
            }
            .ci-invoice-item{margin:0 0 6px 0}
            .ci-invoice-link{display:inline-flex;align-items:center;gap:6px;text-decoration:none}
            .ci-invoice-link img{width:16px;height:16px}
        </style>';
    }

    public static function render_endpoint() {
        $current_user_id = get_current_user_id();
        if ( ! $current_user_id ) {
            echo '<p>' . esc_html__( 'You need to log in to view invoices.', 'custom-invoices' ) . '</p>';
            return;
        }

        if ( ! function_exists( 'wc_get_orders' ) ) {
            echo '<p>' . esc_html__( 'WooCommerce is not available.', 'custom-invoices' ) . '</p>';
            return;
        }

        $orders = wc_get_orders( array(
            'customer_id' => $current_user_id,
            'limit'       => 200,
            'orderby'     => 'date',
            'order'       => 'DESC',
            'status'      => array_keys( wc_get_order_statuses() ),
            'return'      => 'objects',
        ) );

        if ( empty( $orders ) ) {
            echo '<p>' . esc_html__( 'No invoices found.', 'custom-invoices' ) . '</p>';
            return;
        }

        // Icon inside your plugin
        $icon_url = plugins_url( 'images/pdf-icon.png', CUSTOM_INVOICES_PLUGIN_FILE );

        echo '<table class="woocommerce-orders-table shop_table shop_table_responsive my_account_orders">
            <thead>
                <tr>
                    <th>' . esc_html__( 'Order Number', 'custom-invoices' ) . '</th>
                    <th>' . esc_html__( 'Invoices', 'custom-invoices' ) . '</th>
                </tr>
            </thead>
            <tbody>';

        foreach ( $orders as $order ) {
            /** @var WC_Order $order */
            $attachment_ids_str = $order->get_meta( '_custom_invoice_attachment_id', true );
            $attachment_ids     = array_filter( array_map( 'trim', explode( ',', (string) $attachment_ids_str ) ) );

            echo '<tr>';
            echo '<td>#' . esc_html( $order->get_order_number() ) . '</td>';
            echo '<td>';

            if ( empty( $attachment_ids ) ) {
                echo esc_html__( 'No invoice.', 'custom-invoices' );
                echo '</td></tr>';
                continue;
            }

            foreach ( $attachment_ids as $att_id ) {
                $att_id  = absint( $att_id );
                $file_url = $att_id ? wp_get_attachment_url( $att_id ) : '';

                if ( ! $file_url ) {
                    echo '<div class="ci-invoice-item">' . esc_html__( 'Invalid or missing file.', 'custom-invoices' ) . '</div>';
                    continue;
                }

                $file_name = basename( $file_url );

                echo '<div class="ci-invoice-item">';
                echo '<a class="ci-invoice-link" href="' . esc_url( $file_url ) . '" target="_blank" rel="noopener">';
                echo '<img src="' . esc_url( $icon_url ) . '" alt="PDF" />';
                echo esc_html( $file_name );
                echo '</a>';
                echo '</div>';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
    }

    public static function maybe_flush_rewrite_rules() {
        if ( get_option( self::FLUSH_OPTION ) === '1' ) {
            flush_rewrite_rules();
            delete_option( self::FLUSH_OPTION );
        }
    }

    public static function schedule_flush() {
        update_option( self::FLUSH_OPTION, '1', false );
    }
}
