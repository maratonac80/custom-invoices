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

        // Klasični orders ekran – glavni lijevi stupac (normal)
        add_meta_box(
            'custom_invoices_order_invoices',
            __( 'Računi kupca (PDF)', 'custom-invoices' ),
            array( __CLASS__, 'render_box' ),
            'shop_order',
            'normal', // Promena: Ovo obezbeđuje levi glavni stupac
            'high'
        );

        // HPOS ekran – ostavimo u normal contextu
        add_meta_box(
            'custom_invoices_order_invoices_hpos',
            __( 'Računi kupca (PDF)', 'custom-invoices' ),
            array( __CLASS__, 'render_box' ),
            'woocommerce_page_wc-orders',
            'normal', // Promena: Ovo obezbeđuje levi glavni stupac
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

        <script>
        jQuery(function($){
            function updateHiddenInput() {
                var ids = [];
                $('#invoice-list li[data-id]').each(function(){ ids.push($(this).data('id')); });
                $('#custom_invoice_attachment_id').val(ids.join(','));
                if (!ids.length) {
                    if (!$('#invoice-list .empty-msg').length) {
                        $('#invoice-list').html('<li class="empty-msg" style="padding:10px;color:#777;"><?php echo esc_js( __( 'Još nema dodanih računa.', 'custom-invoices' ) ); ?></li>');
                    }
                } else {
                    $('#invoice-list .empty-msg').remove();
                }
            }

            $('#upload-invoice-btn').on('click', function(e){
                e.preventDefault();
                var frame;
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: '<?php echo esc_js( __( 'Odaberi račune', 'custom-invoices' ) ); ?>',
                    multiple: true,
                    library: { type: 'application/pdf' },
                    button: { text: '<?php echo esc_js( __( 'Dodaj na narudžbu', 'custom-invoices' ) ); ?>' }
                });
                frame.on('select', function(){
                    var selection = frame.state().get('selection');
                    selection.map(function(attachment){
                        attachment = attachment.toJSON();
                        var current_ids = $('#custom_invoice_attachment_id').val().split(',');
                        if ($.inArray(String(attachment.id), current_ids) === -1) {
                            $('#invoice-list').append(
                                '<li style="padding:8px;border-bottom:1px solid:#eee;display:flex;justify-content:space-between;align-items:center;" data-id="'+attachment.id+'">' +
                                '<a href="'+attachment.url+'" target="_blank" style="text-decoration:none;font-weight:500;">📄 '+attachment.filename+'</a>' +
                                '<a href="#" class="remove-single-invoice" style="color:red;text-decoration:none;margin-left:10px;">&times;</a>' +
                                '</li>'
                            );
                        }
                    });
                    updateHiddenInput();
                    $('#email-sending-status').html('<span style="color:#d63638;">⚠️ <?php echo esc_js( __( 'Klikni "Ažuriraj" narudžbu prije slanja emaila!', 'custom-invoices' ) ); ?></span>');
                });
                frame.open();
            });

            $('body').on('click', '.remove-single-invoice', function(e){
                e.preventDefault();
                $(this).closest('li').remove();
                updateHiddenInput();
            });

            $('#send-invoice-email-btn').on('click', function(e){
                e.preventDefault();
                var order_id = <?php echo (int) $order_id; ?>;
                var nonce    = '<?php echo wp_create_nonce( 'send_invoice_email_nonce' ); ?>';
                var status   = $('#email-sending-status');
                if (confirm('<?php echo esc_js( __( 'Poslati email s računima (prilagođeni dizajn) kupcu?', 'custom-invoices' ) ); ?>')) {
                    status.html('<span style="color:blue;"><?php echo esc_js( __( 'Slanje... ⏳', 'custom-invoices' ) ); ?></span>');
                    $(this).prop('disabled', true);
                    $.post(ajaxurl, { action:'send_custom_invoice_email', order_id:order_id, security:nonce }, function(resp){
                        if (resp && resp.success) {
                            status.html('<span style="color:green;font-weight:bold;">✅ '+resp.data+'</span>');
                        } else {
                            status.html('<span style="color:red;">❌ <?php echo esc_js( __( 'Greška:', 'custom-invoices' ) ); ?> '+(resp && resp.data ? resp.data : '')+'</span>');
                        }
                        $('#send-invoice-email-btn').prop('disabled', false);
                    }).fail(function(){
                        status.html('<span style="color:red;">❌ <?php echo esc_js( __( 'Dogodila se sistemska greška.', 'custom-invoices' ) ); ?></span>');
                        $('#send-invoice-email-btn').prop('disabled', false);
                    });
                }
            });
        });
        </script>
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