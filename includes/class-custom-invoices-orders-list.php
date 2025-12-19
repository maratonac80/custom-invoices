<?php
/**
 * ADMIN LISTA NARUDŽBI ZA CUSTOM INVOICES
 *
 * - Podstranica "Narudžbe" ispod "Customer Invoice"
 * - Tablica s paginacijom + search + filter statusa + filter "bez računa"
 *
 * VAŽNO:
 * - Ne koristimo direktan SQL nad wp_posts/wp_postmeta jer to puca na HPOS.
 * - Koristimo wc_get_orders() koji radi i na HPOS i na legacy storage.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Orders_List {

    public static function init() {
        // Nema add_action('admin_menu', ...) ovdje.
    }

    public static function render_orders_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Nemate dopuštenje za pregled ove stranice.', 'custom-invoices' ) );
        }

        if ( ! function_exists( 'wc_get_orders' ) ) {
            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Narudžbe', 'custom-invoices' ); ?></h1>
                <p><?php esc_html_e( 'WooCommerce nije aktivan. Lista narudžbi nije dostupna.', 'custom-invoices' ); ?></p>
            </div>
            <?php
            return;
        }

        // Koliko po stranici
        $per_page = 20;

        // Trenutna stranica
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $paged < 1 ) {
            $paged = 1;
        }

        // Search (ID, broj narudžbe, ime/prezime, e-mail) – Woo nativno podržava pretragu,
        // ali nije savršena za sve meta kombinacije, pa radimo "best effort".
        $search = isset( $_GET['ci_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ci_search'] ) ) : '';

        // Filter statusa (slug, npr. processing), ili 'all'
        $status_filter = isset( $_GET['ci_status'] ) ? sanitize_text_field( wp_unslash( $_GET['ci_status'] ) ) : 'all';

        // Filter "samo bez računa"
        $only_without_invoice = ! empty( $_GET['ci_no_invoice'] ) ? (bool) absint( $_GET['ci_no_invoice'] ) : false;

        // Woo statusi za dropdown i label
        $statuses = wc_get_order_statuses(); // 'wc-processing' => 'Processing'

        /**
         * wc_get_orders args
         */
        $args = array(
            'limit'   => $per_page,
            'page'    => $paged,
            'orderby' => 'date',
            'order'   => 'DESC',
            'return'  => 'objects',
        );

        // Status filter
        if ( $status_filter && $status_filter !== 'all' ) {
            $args['status'] = array( $status_filter ); // e.g. 'processing'
        } else {
            // svi statusi
            $all = array();
            foreach ( array_keys( $statuses ) as $k ) {
                $all[] = str_replace( 'wc-', '', $k );
            }
            $args['status'] = array_unique( $all );
        }

        // Search
        if ( $search !== '' ) {
            if ( is_numeric( $search ) ) {
                // Ako je broj, pokušaj direktno ID
                $args['include'] = array( (int) $search );
            } else {
                // Woo search (ime/email/order number) – ovisi o verziji Woo, ali pomaže
                $args['search'] = '*' . $search . '*';
            }
        }

        // Dohvati narudžbe
        $orders = wc_get_orders( $args );

        /**
         * Ako je uključen filter "bez računa", filtriramo u PHP-u (radi i za HPOS i legacy)
         */
        if ( $only_without_invoice && ! empty( $orders ) ) {
            $orders = array_values( array_filter( $orders, function( $order ) {
                if ( ! $order || ! is_a( $order, 'WC_Order' ) ) {
                    return false;
                }
                $order_id = $order->get_id();
                $attachment_ids_str = $order->get_meta( '_custom_invoice_attachment_id', true );
                $attachment_ids     = array_filter( array_map( 'trim', explode( ',', (string) $attachment_ids_str ) ) );
                return empty( $attachment_ids );
            } ) );
        }

        /**
         * Total count (za paginaciju)
         * wc_get_orders ima 'paginate' => true za točan total, ali onda vraća objekt.
         * Radimo drugi poziv za count – stabilno i jasno.
         */
        $count_args = $args;
        $count_args['limit']    = 1;
        $count_args['page']     = 1;
        $count_args['return']   = 'ids';
        $count_args['paginate'] = true;

        // Za count NE koristimo include kad je numeric search, jer bi total bio 1 i paginacija besmislena.
        // Ali u toj situaciji realno i želiš samo tu narudžbu.
        $count_result = wc_get_orders( $count_args );
        $total_orders = 0;

        if ( is_object( $count_result ) && isset( $count_result->total ) ) {
            $total_orders = (int) $count_result->total;
        } elseif ( is_array( $count_result ) ) {
            // fallback
            $total_orders = count( $count_result );
        }

        // Ako filtriramo "bez računa" u PHP-u, total nije točan – ali barem paginacija ostaje konzistentna po status/searchu.
        // (Za perfekciju bi radili meta_query, ali HPOS meta query zna biti spor i ovisi o verziji.)
        $total_pages = $total_orders > 0 ? (int) ceil( $total_orders / $per_page ) : 1;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Narudžbe (Custom Invoices)', 'custom-invoices' ); ?></h1>

            <p>
                <?php esc_html_e( 'Ovdje su WooCommerce narudžbe s informacijom je li račun uploadan / poslan ili ne.', 'custom-invoices' ); ?>
            </p>

            <form method="get" style="margin-bottom:12px;">
                <input type="hidden" name="page" value="custom-invoices-orders" />
                <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
                    <div>
                        <input
                            type="text"
                            name="ci_search"
                            value="<?php echo esc_attr( $search ); ?>"
                            placeholder="<?php esc_attr_e( 'Pretraži po broju narudžbe, imenu kupca ili e-mailu...', 'custom-invoices' ); ?>"
                            class="regular-text"
                            style="min-width:260px;"
                        />
                    </div>

                    <div>
                        <select name="ci_status">
                            <option value="all"><?php esc_html_e( 'Svi statusi', 'custom-invoices' ); ?></option>
                            <?php foreach ( $statuses as $status_key => $status_label ) :
                                $slug = str_replace( 'wc-', '', $status_key );
                                ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status_filter, $slug ); ?>>
                                    <?php echo esc_html( $status_label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display:flex;align-items:center;gap:4px;">
                            <input type="checkbox" name="ci_no_invoice" value="1" <?php checked( $only_without_invoice, true ); ?> />
                            <span><?php esc_html_e( 'Samo narudžbe bez računa', 'custom-invoices' ); ?></span>
                        </label>
                    </div>

                    <div>
                        <button class="button button-secondary" type="submit">
                            <?php esc_html_e( 'Filtriraj', 'custom-invoices' ); ?>
                        </button>

                        <?php if ( $search !== '' || ( $status_filter && $status_filter !== 'all' ) || $only_without_invoice ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-invoices-orders' ) ); ?>" class="button">
                                <?php esc_html_e( 'Resetiraj filtere', 'custom-invoices' ); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'ID narudžbe', 'custom-invoices' ); ?></th>
                        <th><?php esc_html_e( 'Kupac', 'custom-invoices' ); ?></th>
                        <th><?php esc_html_e( 'Status narudžbe', 'custom-invoices' ); ?></th>
                        <th><?php esc_html_e( 'Račun', 'custom-invoices' ); ?></th>
                        <th><?php esc_html_e( 'Akcije', 'custom-invoices' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $orders ) ) : ?>
                    <tr>
                        <td colspan="5"><?php esc_html_e( 'Nema narudžbi za prikaz.', 'custom-invoices' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $orders as $order ) :
                        /** @var WC_Order $order */
                        $order_id   = $order->get_id();
                        $order_link = method_exists( $order, 'get_edit_order_url' )
                            ? $order->get_edit_order_url()
                            : get_edit_post_link( $order_id );

                        $customer_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
                        if ( $customer_name === '' ) {
                            $customer_name = __( '(bez imena)', 'custom-invoices' );
                        }

                        $status_slug  = $order->get_status(); // 'processing'
                        $status_key   = 'wc-' . $status_slug;
                        $status_label = isset( $statuses[ $status_key ] ) ? $statuses[ $status_key ] : ucfirst( $status_slug );

                        $attachment_ids_str = $order->get_meta( '_custom_invoice_attachment_id', true );
                        $attachment_ids     = array_filter( array_map( 'trim', explode( ',', (string) $attachment_ids_str ) ) );
                        $has_invoice        = ! empty( $attachment_ids );
                        ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url( $order_link ); ?>">
                                    #<?php echo esc_html( $order->get_order_number() ); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html( $customer_name ); ?></td>
                            <td>
                                <span class="order-status status-<?php echo esc_attr( $status_slug ); ?>">
                                    <?php echo esc_html( $status_label ); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ( $has_invoice ) : ?>
                                    <span style="color:#1a7f37;font-weight:600;"><?php esc_html_e( 'POSLAN', 'custom-invoices' ); ?></span>
                                <?php else : ?>
                                    <span style="color:#d63638;font-weight:600;"><?php esc_html_e( 'NEMA / NIJE POSLAN', 'custom-invoices' ); ?></span>
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
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>

            <?php if ( $total_pages > 1 ) :
                $base_url = admin_url( 'admin.php?page=custom-invoices-orders' );
                $base_args = array();

                if ( $search !== '' ) {
                    $base_args['ci_search'] = $search;
                }
                if ( $status_filter !== '' && $status_filter !== 'all' ) {
                    $base_args['ci_status'] = $status_filter;
                }
                if ( $only_without_invoice ) {
                    $base_args['ci_no_invoice'] = 1;
                }

                $base_url = add_query_arg( $base_args, $base_url );
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
            <?php endif; ?>
        </div>
        <?php
    }
}
