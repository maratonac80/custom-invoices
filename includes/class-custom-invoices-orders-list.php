<?php
/**
 * ADMIN LISTA NARUDŽBI ZA CUSTOM INVOICES
 *
 * - Podstranica "Narudžbe" ispod "Customer Invoice"
 * - Tablica s paginacijom + search + filter statusa:
 *   - ID narudžbe
 *   - Ime i prezime kupca
 *   - Status narudžbe (Woo status)
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

        global $wpdb;

        // Koliko narudžbi po stranici
        $per_page = 20;

        // Trenutna stranica (paged parametar iz URL-a)
        $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        if ( $paged < 1 ) {
            $paged = 1;
        }

        // Search (broj narudžbe, ID, ime/prezime, e-mail)
        $search = isset( $_GET['ci_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ci_search'] ) ) : '';

        // Filter statusa narudžbe (wc status slug, npr. 'processing')
        $status_filter = isset( $_GET['ci_status'] ) ? sanitize_text_field( wp_unslash( $_GET['ci_status'] ) ) : 'all';

        // WooCommerce statusi (key => label)
        $statuses = wc_get_order_statuses();
        // npr. 'wc-processing' => 'Processing'

        /**
         * 1) Priprema SQL upita za ID-ove narudžbi
         */
        $posts_table    = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;

        $where   = array();
        $joins   = array();
        $params  = array();

        // Osnovni where: shop_order i da nije auto-draft
        $where[]  = "{$posts_table}.post_type = %s";
        $params[] = 'shop_order';

        $where[]  = "{$posts_table}.post_status NOT IN ('auto-draft')";
        // bez parametara jer je literal

        // Filter statusa (ako nije "all")
        if ( $status_filter && $status_filter !== 'all' ) {
            // post_status u bazi je 'wc-processing', 'wc-completed', ...
            $post_status = 'wc-' . $status_filter;
            $where[]     = "{$posts_table}.post_status = %s";
            $params[]    = $post_status;
        }

        // Search
        if ( $search !== '' ) {
            // Pridružujemo postmeta za billing podatke
            $joins[] = "LEFT JOIN {$postmeta_table} AS pm_billing_first ON (pm_billing_first.post_id = {$posts_table}.ID AND pm_billing_first.meta_key = '_billing_first_name')";
            $joins[] = "LEFT JOIN {$postmeta_table} AS pm_billing_last  ON (pm_billing_last.post_id  = {$posts_table}.ID AND pm_billing_last.meta_key  = '_billing_last_name')";
            $joins[] = "LEFT JOIN {$postmeta_table} AS pm_billing_email ON (pm_billing_email.post_id = {$posts_table}.ID AND pm_billing_email.meta_key = '_billing_email')";

            $like = '%' . $wpdb->esc_like( $search ) . '%';

            if ( is_numeric( $search ) ) {
                // Brojka → pokušaj na ID ili post_title (order number)
                $where[]  = "( {$posts_table}.ID = %d OR {$posts_table}.post_title LIKE %s OR pm_billing_first.meta_value LIKE %s OR pm_billing_last.meta_value LIKE %s OR pm_billing_email.meta_value LIKE %s )";
                $params[] = (int) $search;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            } else {
                // Tekst → ime, prezime, email, broj u titleu
                $where[]  = "( {$posts_table}.post_title LIKE %s OR pm_billing_first.meta_value LIKE %s OR pm_billing_last.meta_value LIKE %s OR pm_billing_email.meta_value LIKE %s )";
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }
        }

        $joins_sql = implode( ' ', array_unique( $joins ) );
        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        /**
         * 2) Ukupan broj narudžbi za paginaciju
         */
        $count_sql = "
            SELECT COUNT(DISTINCT {$posts_table}.ID)
            FROM {$posts_table}
            {$joins_sql}
            {$where_sql}
        ";

        $total_orders = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );
        $total_pages  = $total_orders > 0 ? ceil( $total_orders / $per_page ) : 1;

        if ( $paged > $total_pages ) {
            $paged = max( 1, $total_pages );
        }

        $offset = ( $paged - 1 ) * $per_page;

        /**
         * 3) Dohvat ID-ova narudžbi za prikazanu stranicu
         */
        $select_sql = "
            SELECT DISTINCT {$posts_table}.ID
            FROM {$posts_table}
            {$joins_sql}
            {$where_sql}
            ORDER BY {$posts_table}.post_date DESC
            LIMIT %d OFFSET %d
        ";

        $params_with_limit   = $params;
        $params_with_limit[] = $per_page;
        $params_with_limit[] = $offset;

        $order_ids = $wpdb->get_col( $wpdb->prepare( $select_sql, $params_with_limit ) );

        $orders = array();
        if ( ! empty( $order_ids ) ) {
            foreach ( $order_ids as $oid ) {
                $order = wc_get_order( $oid );
                if ( $order ) {
                    $orders[] = $order;
                }
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Narudžbe (Custom Invoices)', 'custom-invoices' ); ?></h1>

            <p>
                <?php esc_html_e( 'Ovdje su WooCommerce narudžbe s informacijom je li račun uploadan / poslan ili ne.', 'custom-invoices' ); ?>
            </p>

            <!-- Filteri: search + status -->
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
                            <?php
                            foreach ( $statuses as $status_key => $status_label ) {
                                // status_key = 'wc-processing' → status_slug = 'processing'
                                $status_slug = str_replace( 'wc-', '', $status_key );
                                ?>
                                <option value="<?php echo esc_attr( $status_slug ); ?>" <?php selected( $status_filter, $status_slug ); ?>>
                                    <?php echo esc_html( $status_label ); ?>
                                </option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <button class="button button-secondary" type="submit">
                            <?php esc_html_e( 'Filtriraj', 'custom-invoices' ); ?>
                        </button>
                        <?php if ( $search !== '' || ( $status_filter && $status_filter !== 'all' ) ) : ?>
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
                    <?php
                    if ( empty( $orders ) ) :
                        ?>
                        <tr>
                            <td colspan="5"><?php esc_html_e( 'Nema narudžbi za prikaz.', 'custom-invoices' ); ?></td>
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

                            // Status narudžbe (slug + label)
                            $status_slug  = $order->get_status(); // npr. 'processing'
                            $status_key   = 'wc-' . $status_slug;
                            $status_label = isset( $statuses[ $status_key ] ) ? $statuses[ $status_key ] : ucfirst( $status_slug );

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
                                    <span class="order-status status-<?php echo esc_attr( $status_slug ); ?>">
                                        <?php echo esc_html( $status_label ); ?>
                                    </span>
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

                // Zadrži search i status u query stringu
                $base_args = array();
                if ( $search !== '' ) {
                    $base_args['ci_search'] = $search;
                }
                if ( $status_filter !== '' && $status_filter !== 'all' ) {
                    $base_args['ci_status'] = $status_filter;
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
                <?php
            endif;
            ?>
        </div>
        <?php
    }
}