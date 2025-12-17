<?php
/**
 * ORDER META BOX (UPLOAD PDF + "Pošalji email")
 *
 * OVAJ FILE:
 * - dodaje meta box u admin narudžbi za upload više PDF računa,
 * - sprema ID-ove attachmenta u meta `_custom_invoice_attachment_id`,
 * - ima JS za:
 *   - otvaranje WP media uploadera (samo PDF),
 *   - listanje i brisanje pojedinih računa,
 *   - AJAX poziv za slanje custom e-maila kupcu,
 * - obrađuje spremanje meta podataka kroz:
 *   - save_post (legacy),
 *   - woocommerce_process_shop_order_meta (HPOS).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Order_Metabox {

    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
        add_action( 'save_post', array( __CLASS__, 'save_meta_legacy' ) );
        add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'save_meta_hpos' ) );
    }

    public static function add_meta_box() {
        if ( ! custom_invoices_is_woocommerce_active() ) {
            return;
        }

        // Ovo dodaje meta box samo jednom
        add_meta_box(
            'custom_invoices_order_invoices',
            __( 'Računi kupca (PDF)', 'custom-invoices' ),
            array( __CLASS__, 'render_box' ),
            'shop_order',
            'normal',
            'high'
        );
    }

    public static function render_box( $post_or_order_object ) {
        if ( ! custom_invoices_is_woocommerce_active() ) {
            return;
        }

        $order = ( $post_or_order_object instanceof WP_Post )
            ? wc_get_order( $post_or_order_object->ID )
            : $post_or_order_object;

        if ( ! $order ) {
            return;
        }

        $attachment_ids_str = $order->get_meta( '_custom_invoice_attachment_id' );
        $attachment_ids     = array_filter( explode( ',', $attachment_ids_str ) );
        $order_id           = $order->get_id();

        // URL natrag na popis narudžbi u našem pluginu
        $back_url = menu_page_url( 'custom-invoices-orders', false );

        wp_nonce_field( 'save_invoice_nonce', 'invoice_nonce_field' );
        ?>
        <div id="custom-invoice-box" class="invoice-upload-wrapper">
            <p class="description" style="margin-top:0;margin-bottom:10px;">
                <?php esc_html_e( 'Učitaj PDF račune pa klikni "Pošalji email".', 'custom-invoices' ); ?>
            </p>

            <ul id="invoice-list" style="margin-bottom:15px;background:#fff;border:1px solid:#ddd;">
                <?php 
                if ( ! empty( $attachment_ids ) ) {
                    foreach ( $attachment_ids as $att_id ) {
                        $file_url = wp_get_attachment_url( $att_id );
                        $filename = basename( get_attached_file( $att_id ) );
                        if ( $file_url ) {
                            echo '<li style="padding:8px;border-bottom:1px solid:#eee;display:flex;justify-content:space-between;align-items:center;" data-id="' . esc_attr( $att_id ) . '">';
                            echo '<a href="' . esc_url( $file_url ) . '" target="_blank" style="text-decoration:none;font-weight:500;">📄 ' . esc_html( $filename ) . '</a>';
                            echo '<a href="#" class="remove-single-invoice" style="color:red;text-decoration:none;margin-left:10px;">&times;</a>';
                            echo '</li>';
                        }
                    }
                } else {
                    echo '<li class="empty-msg" style="padding:10px;color:#777;">' . esc_html__( 'Još nema dodanih računa.', 'custom-invoices' ) . '</li>';
                }
                ?>
            </ul>

            <input type="hidden" id="custom_invoice_attachment_id" name="custom_invoice_attachment_id" value="<?php echo esc_attr( implode( ',', $attachment_ids ) ); ?>">

            <button type="button" class="button" id="upload-invoice-btn" style="width:100%;margin-bottom:10px;">
                <?php esc_html_e( 'Dodaj račun(e)', 'custom-invoices' ); ?>
            </button>

            <hr>

            <div style="background:#fdfdfd;padding:10px;border:1px dashed #ccc;margin-top:10px;">
                <button type="button" class="button button-primary" id="send-invoice-email-btn" style="width:100%;">
                    <span class="dashicons dashicons-email-alt" style="line-height:1.3;"></span>
                    <?php esc_html_e( 'Pošalji email (prilagođeni predložak)', 'custom-invoices' ); ?>
                </button>
                <div id="email-sending-status" style="margin-top:5px;font-size:12px;text-align:center;"></div>

                <!-- Gumb za povratak ispod "Pošalji email" -->
                <button type="button"
                        class="button button-secondary"
                        style="width:100%;margin-top:10px;"
                        onclick="window.location.href='<?php echo esc_url( $back_url ); ?>';">
                    <?php esc_html_e( '← Natrag na popis narudžbi (Custom Invoices)', 'custom-invoices' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    public static function save_meta_legacy( $post_id ) {
        if ( get_post_type( $post_id ) !== 'shop_order' ) {
            return;
        }
        self::save_meta( $post_id );
    }

    public static function save_meta_hpos( $post_id ) {
        self::save_meta( $post_id );
    }

    protected static function save_meta( $post_id ) {
        if ( ! custom_invoices_is_woocommerce_active() ) {
            return;
        }

        if ( ! isset( $_POST['invoice_nonce_field'] ) || ! wp_verify_nonce( $_POST['invoice_nonce_field'], 'save_invoice_nonce' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        $order = wc_get_order( $post_id );
        if ( ! $order ) {
            return;
        }

        if ( isset( $_POST['custom_invoice_attachment_id'] ) ) {
            $order->update_meta_data(
                '_custom_invoice_attachment_id',
                sanitize_text_field( wp_unslash( $_POST['custom_invoice_attachment_id'] ) )
            );
            $order->save();
        }
    }
}