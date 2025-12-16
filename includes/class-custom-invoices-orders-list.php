<?php
/**
 * ADMIN LISTA NARUDŽBI ZA CUSTOM INVOICES
 *
 * - Podstranica "Narudžbe" ispod "Customer Invoice"
 * - Tablica s paginacijom:
 *   - ID narudžbe
 *   - Ime i prezime kupca
 *   - Status računa (POSLAN / NEMA / NIJE POSLAN)
 *   - Akcija (Pogledaj / Uploadaj račun)
 *
 * STATUS POSLAN:
 * - ako postoji meta ključ _custom_invoice_attachment_id (attachment ID PDF-a, ili više njih u nizu).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Orders_List {

    /**
     * init ostavljamo, ali BEZ registracije menija.
     * Meni (glavni + podmeni) registrira klasa Custom_Invoices_Admin_Email_Template.
     */
    public static function init() {
        // Nema add_action('admin_menu', ...) ovdje.
    }

    public static function render_orders_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        if ( ! class_exists( 'WC_Order' ) ) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Narudžbe', 'custom-invoices' ); ?></h1>
                <p><?php esc_html_e( 'WooCommerce nije aktivan. Lista narudžbi nije dostupna.', 'custom-invoices' ); ?></p>
            </div>
            <?php
            return;
        }

        // Koliko narudžbi po stranici
        $per_page = 20;

        // Trenutna stranica (paged parametar iz URL-a)
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $paged < 1 ) {
            $paged = 1;
        }

        // Offset za wc_get_orders
        $offset = ( $paged - 1 ) * $per_page;

        /**
         * 1) Ukupan broj narudžbi
         */
        global $wpdb;

        $posts_table = $wpdb->posts;
        $post_type   = 'shop_order';

        $total_orders = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(ID) FROM {$posts_table} WHERE post_type = %s AND post_status NOT IN ('auto-draft')",
                $post_type
            )
        );

        $total_pages = $total_orders > 0 ? ceil( $total_orders / $per_page ) : 1;

        /**
         * 2) Dohvat narudžbi za trenutnu stranicu
         */
        $args = array(
            'limit'   => $per_page,
            'offset'  => $offset,
            'orderby' => 'date',
            'order'   => 'DESC',
        );

        $orders = wc_get_orders( $args );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Narudžbe (Custom Invoices)', 'custom-invoices' ); ?></h1>

            <p>
                <?php esc_html_e( 'Ovdje su WooCommerce narudžbe s informacijom je li račun uploadan / poslan ili ne.', 'custom-invoices' ); ?>
            </p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID narudžbe', 'custom-invoices' ); ?></th>
                        <th><?php esc_html_e( 'Kupac', 'custom-invoices' ); ?></th>
                        <th><?php esc_html_e( 'Račun', 'custom-invoices' ); ?></th>
                        <th><?php esc_html_e( 'Akcije', 'custom-invoices' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ( empty( $orders ) ) :
                        ?>
                        <tr>
                            <td colspan="4"><?php esc_html_e( 'Nema narudžbi za prikaz.', 'custom-invoices' ); ?></td>
                        </tr>
                        <?php
                    else :
                        foreach ( $orders as $order ) :
                            /** @var WC_Order $order */
                            $order_id   = $order->get_id();
                            $order_link = get_edit_post_link( $order_id );

                            $billing_first_name = $order->get_billing_first_name();
                            $billing_last_name  = $order->get_billing_last_name();
                            $customer_name      = trim( $billing_first_name . ' ' . $billing_last_name );
                            if ( $customer_name === '' ) {
                                $customer_name = __( '(bez imena)', 'custom-invoices' );
                            }

                            /**
                             * PROVJERA JE LI RAČUN UPLOADAN / POSLAN
                             *
                             * Koristimo meta ključ _custom_invoice_attachment_id (comma-separated attachment IDs).
                             */
                            $attachment_ids_str = get_post_meta( $order_id, '_custom_invoice_attachment_id', true );
                            $attachment_ids     = array_filter( explode( ',', (string) $attachment_ids_str ) );
                            $has_invoice        = ! empty( $attachment_ids );
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo esc_url( $order_link ); ?>">
                                        #<?php echo esc_html( $order->get_order_number() ); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php echo esc_html( $customer_name ); ?>
                                </td>
                                <td>
                                    <?php if ( $has_invoice ) : ?>
                                        <span style="color:#1a7f37;font-weight:600;">
                                            <?php esc_html_e( 'POSLAN', 'custom-invoices' ); ?>
                                        </span>
                                    <?php else : ?>
                                        <span style="color:#d63638;font-weight:600;">
                                            <?php esc_html_e( 'NEMA / NIJE POSLAN', 'custom-invoices' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $has_invoice ) : ?>
                                        <a href="<?php echo esc_url( $order_link ); ?>" class="button button-secondary">
                                            <?php esc_html_e( 'Pogledaj narudžbu', 'custom-invoices' ); ?>
                                        </a>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $order_link . '#custom-invoice-box' ); ?>" class="button button-primary">
                                            <?php esc_html_e( 'Uploadaj račun', 'custom-invoices' ); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>

            <?php
            // PAGINACIJA – centrirana i s većim fontom
            if ( $total_pages > 1 ) :
                $base_url = admin_url( 'admin.php?page=custom-invoices-orders' );
                ?>
                <div class="tablenav bottom" style="text-align:center;margin-top:15px;">
                    <div class="tablenav-pages" style="display:inline-block;font-size:14px;">
                        <?php
                        echo paginate_links( array(
                            'base'      => esc_url_raw( add_query_arg( 'paged', '%#%', $base_url ) ),
                            'format'    => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total'     => $total_pages,
                            'current'   => $paged,
                        ) );
                        ?>
                    </div>
                </div>
                <?php
            endif;
            ?>
        </div>
        <?php
    }
}