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
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
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

    public static function enqueue_scripts( $hook ) {
        // Učitaj skripte samo na stranicama narudžbi
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ) ) ) {
            return;
        }

        global $post;
        if ( ! $post || get_post_type( $post->ID ) !== 'shop_order' ) {
            return;
        }

        // Učitaj WordPress media uploader
        wp_enqueue_media();

        // Dodaj inline JavaScript
        add_action( 'admin_footer', array( __CLASS__, 'print_scripts' ) );
    }

    public static function print_scripts() {
        global $post;
        if ( ! $post || get_post_type( $post->ID ) !== 'shop_order' ) {
            return;
        }

        $order_id = $post->ID;
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var customInvoiceFrame;
            var attachmentIdsField = $('#custom_invoice_attachment_id');
            var invoiceList = $('#invoice-list');

            // === Upload Invoice Button ===
            $('#upload-invoice-btn').on('click', function(e) {
                e.preventDefault();

                if (customInvoiceFrame) {
                    customInvoiceFrame.open();
                    return;
                }

                customInvoiceFrame = wp.media({
                    title: '<?php echo esc_js( __( 'Odaberi PDF račune', 'custom-invoices' ) ); ?>',
                    button: {
                        text: '<?php echo esc_js( __( 'Koristi ove račune', 'custom-invoices' ) ); ?>'
                    },
                    library: {
                        type: 'application/pdf'
                    },
                    multiple: true
                });

                customInvoiceFrame.on('select', function() {
                    var selection = customInvoiceFrame.state().get('selection');
                    var currentIds = attachmentIdsField.val();
                    var idsArray = currentIds ? currentIds.split(',') : [];

                    selection.forEach(function(attachment) {
                        attachment = attachment.toJSON();
                        var attId = attachment.id.toString();
                        
                        if (idsArray.indexOf(attId) === -1) {
                            idsArray.push(attId);

                            // Remove empty message if exists
                            invoiceList.find('.empty-msg').remove();

                            // Add to list
                            var filename = attachment.filename || attachment.title;
                            var fileUrl = attachment.url;
                            var newItem = '<li style="padding:8px;border-bottom:1px solid #eee;display:flex;justify-content:space-between;align-items:center;" data-id="' + attId + '">' +
                                '<a href="' + fileUrl + '" target="_blank" style="text-decoration:none;font-weight:500;">📄 ' + filename + '</a>' +
                                '<a href="#" class="remove-single-invoice" style="color:red;text-decoration:none;margin-left:10px;">&times;</a>' +
                                '</li>';
                            invoiceList.append(newItem);
                        }
                    });

                    attachmentIdsField.val(idsArray.join(','));
                });

                customInvoiceFrame.open();
            });

            // === Remove Single Invoice ===
            invoiceList.on('click', '.remove-single-invoice', function(e) {
                e.preventDefault();
                var $li = $(this).closest('li');
                var attId = $li.data('id').toString();
                
                var currentIds = attachmentIdsField.val();
                var idsArray = currentIds ? currentIds.split(',') : [];
                idsArray = idsArray.filter(function(id) { return id !== attId; });
                
                attachmentIdsField.val(idsArray.join(','));
                $li.remove();

                // Add empty message if no invoices left
                if (invoiceList.children().length === 0) {
                    invoiceList.append('<li class="empty-msg" style="padding:10px;color:#777;"><?php echo esc_js( __( 'Još nema dodanih računa.', 'custom-invoices' ) ); ?></li>');
                }
            });

            // === Send Invoice Email Button ===
            $('#send-invoice-email-btn').on('click', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var $status = $('#email-sending-status');

                var currentIds = attachmentIdsField.val();
                if (!currentIds || currentIds === '') {
                    $status.html('<span style="color:red;"><?php echo esc_js( __( 'Najprije dodaj račune.', 'custom-invoices' ) ); ?></span>');
                    return;
                }

                $btn.prop('disabled', true).text('<?php echo esc_js( __( 'Šaljem...', 'custom-invoices' ) ); ?>');
                $status.html('<span style="color:#999;"><?php echo esc_js( __( 'Molimo pričekajte...', 'custom-invoices' ) ); ?></span>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'send_custom_invoice_email',
                        order_id: <?php echo (int) $order_id; ?>,
                        security: '<?php echo wp_create_nonce( 'send_invoice_email_nonce' ); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $status.html('<span style="color:green;font-weight:600;">' + response.data + '</span>');
                        } else {
                            $status.html('<span style="color:red;">' + response.data + '</span>');
                        }
                    },
                    error: function() {
                        $status.html('<span style="color:red;"><?php echo esc_js( __( 'Greška pri slanju emaila.', 'custom-invoices' ) ); ?></span>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-email-alt" style="line-height:1.3;"></span> <?php echo esc_js( __( 'Pošalji email (prilagođeni predložak)', 'custom-invoices' ) ); ?>');
                    }
                });
            });
        });
        </script>
        <?php
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