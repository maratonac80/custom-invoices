<?php
/**
 * ADMIN E-MAIL TEMPLATE UI + ADMIN MENI
 *
 * OVAJ FILE:
 * - dodaje admin meni "Customer Invoice" i podstranice:
 *   - "Narudžbe" (lista narudžbi – renderira je Custom_Invoices_Orders_List),
 *   - "E-mail predložak" (ovaj file),
 * - renderira admin ekran za uređivanje e-mail predloška:
 *   - način rada (default/custom HTML),
 *   - jezik (HR/EN) s gumbom "Primijeni jezik",
 *   - header/main/footer polja (shop name, intro, kontakt, footer, boje),
 *   - testni e-mail, reset na standardni predložak,
 *   - live preview iframe u adminu,
 * - obrađuje POST:
 *   - spremanje postavki (Save settings),
 *   - promjenu jezika (Apply language),
 *   - reset na zadane vrijednosti,
 * - sadrži JS za:
 *   - upozorenje na nespremljene promjene (beforeunload),
 *   - media uploader za logo,
 *   - generiranje HTML-a za preview,
 *   - AJAX slanje testnog maila.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Admin_Email_Template {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_media' ) );
        add_action( 'wp_ajax_custom_invoices_send_test_email', array( __CLASS__, 'ajax_send_test_email' ) );
    }

    public static function register_menu() {
        // GLAVNI MENI "Customer Invoice" – vodi na NARUDŽBE
        add_menu_page(
            __( 'Customer Invoice', 'custom-invoices' ),
            __( 'Customer Invoice', 'custom-invoices' ),
            'manage_woocommerce',
            'custom-invoices-orders', // glavni slug -> lista narudžbi
            array( 'Custom_Invoices_Orders_List', 'render_orders_page' ),
            'dashicons-email-alt',
            56
        );

        // Podmeni: Narudžbe (ista stranica, eksplicitno)
        add_submenu_page(
            'custom-invoices-orders',
            __( 'Narudžbe', 'custom-invoices' ),
            __( 'Narudžbe', 'custom-invoices' ),
            'manage_woocommerce',
            'custom-invoices-orders',
            array( 'Custom_Invoices_Orders_List', 'render_orders_page' )
        );

        // Podmeni: E-mail predložak
        add_submenu_page(
            'custom-invoices-orders',
            __( 'E-mail predložak', 'custom-invoices' ),
            __( 'E-mail predložak', 'custom-invoices' ),
            'manage_woocommerce',
            'custom-invoices-email-template',
            array( __CLASS__, 'render_email_template_page' )
        );
    }

    public static function enqueue_media( $hook ) {
        if ( strpos( $hook, 'custom-invoices-email-template' ) === false ) {
            return;
        }
        wp_enqueue_media();
    }

    public static function render_email_template_page() {

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        $lang  = get_option( 'custom_invoices_email_language', 'hr' );
        $is_hr = ( $lang === 'hr' );

        /* RESET */
        if (
            isset( $_POST['custom_invoices_do_reset'] ) &&
            $_POST['custom_invoices_do_reset'] === '1' &&
            isset( $_POST['custom_invoices_reset_template_nonce'] ) &&
            wp_verify_nonce( $_POST['custom_invoices_reset_template_nonce'], 'custom_invoices_reset_template' )
        ) {
            delete_option( 'custom_invoices_email_mode' );
            delete_option( 'custom_invoices_default_shop_name' );
            delete_option( 'custom_invoices_default_header_bg_color' );
            delete_option( 'custom_invoices_default_logo_url' );
            delete_option( 'custom_invoices_default_content_intro' );
            delete_option( 'custom_invoices_default_contact_email' );
            delete_option( 'custom_invoices_default_contact_phone' );
            delete_option( 'custom_invoices_help_title' );
            delete_option( 'custom_invoices_help_line' );
            delete_option( 'custom_invoices_primary_color' );
            delete_option( 'custom_invoices_footer_company' );
            delete_option( 'custom_invoices_footer_address' );
            delete_option( 'custom_invoices_footer_tax_id' );
            delete_option( 'custom_invoices_email_template_html' );

            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html(
                $is_hr
                    ? 'Predložak je vraćen na standardne vrijednosti.'
                    : 'Template has been reset to the default values.'
            ) . '</p></div>';
        }

        // PROMJENA JEZIKA – poseban gumb odmah pored selecta
        if (
            isset( $_POST['custom_invoices_change_language'] ) &&
            isset( $_POST['custom_invoices_email_language'] )
        ) {
            $new_lang = sanitize_text_field( wp_unslash( $_POST['custom_invoices_email_language'] ) );
            if ( ! in_array( $new_lang, array( 'hr', 'en' ), true ) ) {
                $new_lang = 'hr';
            }
            update_option( 'custom_invoices_email_language', $new_lang );
            $lang  = $new_lang;
            $is_hr = ( $lang === 'hr' );
        }

        // SAVE SETTINGS
        if (
            isset( $_POST['custom_invoices_save_settings'] ) &&
            isset( $_POST['custom_invoices_email_template_nonce'] ) &&
            wp_verify_nonce( $_POST['custom_invoices_email_template_nonce'], 'custom_invoices_save_email_template' )
        ) {
            if ( isset( $_POST['custom_invoices_email_language'] ) ) {
                $new_lang = sanitize_text_field( wp_unslash( $_POST['custom_invoices_email_language'] ) );
                if ( ! in_array( $new_lang, array( 'hr', 'en' ), true ) ) {
                    $new_lang = 'hr';
                }
                update_option( 'custom_invoices_email_language', $new_lang );
                $lang  = $new_lang;
                $is_hr = ( $lang === 'hr' );
            }

            $mode = isset( $_POST['custom_invoices_email_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_invoices_email_mode'] ) ) : 'default';
            if ( ! in_array( $mode, array( 'default', 'custom_html' ), true ) ) {
                $mode = 'default';
            }
            update_option( 'custom_invoices_email_mode', $mode );

            // HEADER
            $default_shop_name = isset( $_POST['custom_invoices_default_shop_name'] ) ? wp_unslash( $_POST['custom_invoices_default_shop_name'] ) : '';
            $header_bg_color   = isset( $_POST['custom_invoices_default_header_bg_color'] ) ? wp_unslash( $_POST['custom_invoices_default_header_bg_color'] ) : '';
            $logo_url          = isset( $_POST['custom_invoices_default_logo_url'] ) ? esc_url_raw( wp_unslash( $_POST['custom_invoices_default_logo_url'] ) ) : '';
// NOVO: glavni naslov maila i podnaslov u headeru
$email_title   = isset( $_POST['custom_invoices_email_title'] ) ? wp_unslash( $_POST['custom_invoices_email_title'] ) : '';
$hero_subtitle = isset( $_POST['custom_invoices_hero_subtitle'] ) ? wp_unslash( $_POST['custom_invoices_hero_subtitle'] ) : '';

            // MAIN
            $content_intro     = isset( $_POST['custom_invoices_default_content_intro'] ) ? wp_unslash( $_POST['custom_invoices_default_content_intro'] ) : '';
            $contact_email     = isset( $_POST['custom_invoices_default_contact_email'] ) ? wp_unslash( $_POST['custom_invoices_default_contact_email'] ) : '';
            $contact_phone     = isset( $_POST['custom_invoices_default_contact_phone'] ) ? wp_unslash( $_POST['custom_invoices_default_contact_phone'] ) : '';
            $help_title        = isset( $_POST['custom_invoices_help_title'] ) ? wp_unslash( $_POST['custom_invoices_help_title'] ) : '';
            $help_line         = isset( $_POST['custom_invoices_help_line'] )  ? wp_unslash( $_POST['custom_invoices_help_line'] )  : '';
            $primary_color     = isset( $_POST['custom_invoices_primary_color'] ) ? wp_unslash( $_POST['custom_invoices_primary_color'] ) : '';

            // FOOTER
            $footer_company    = isset( $_POST['custom_invoices_footer_company'] ) ? wp_unslash( $_POST['custom_invoices_footer_company'] ) : '';
            $footer_address    = isset( $_POST['custom_invoices_footer_address'] ) ? wp_unslash( $_POST['custom_invoices_footer_address'] ) : '';
            $footer_tax_id     = isset( $_POST['custom_invoices_footer_tax_id'] ) ? wp_unslash( $_POST['custom_invoices_footer_tax_id'] ) : '';

            // TEST EMAIL
            $test_email = isset( $_POST['custom_invoices_test_email'] )
                ? sanitize_email( wp_unslash( $_POST['custom_invoices_test_email'] ) )
                : '';

            update_option( 'custom_invoices_default_shop_name', $default_shop_name );
            update_option( 'custom_invoices_default_header_bg_color', $header_bg_color );
            update_option( 'custom_invoices_default_logo_url', $logo_url );
	    // NOVO: spremi naslov i podnaslov
update_option( 'custom_invoices_email_title', sanitize_text_field( $email_title ) );
update_option( 'custom_invoices_hero_subtitle', sanitize_text_field( $hero_subtitle ) );

            update_option( 'custom_invoices_default_content_intro', $content_intro );
            update_option( 'custom_invoices_default_contact_email', $contact_email );
            update_option( 'custom_invoices_default_contact_phone', $contact_phone );
            update_option( 'custom_invoices_help_title', $help_title );
            update_option( 'custom_invoices_help_line',  $help_line );
            update_option( 'custom_invoices_primary_color', $primary_color );

            update_option( 'custom_invoices_footer_company', $footer_company );
            update_option( 'custom_invoices_footer_address', $footer_address );
            update_option( 'custom_invoices_footer_tax_id', $footer_tax_id );

            if ( $test_email && is_email( $test_email ) ) {
                update_option( 'custom_invoices_test_email', $test_email );
            }

            if ( isset( $_POST['custom_invoices_email_template_html'] ) ) {
                $raw_html = wp_unslash( $_POST['custom_invoices_email_template_html'] );
                update_option( 'custom_invoices_email_template_html', $raw_html );
            }

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $is_hr
                ? 'Postavke e-mail predloška su spremljene.'
                : 'E-mail template settings have been saved.'
            ) . '</p></div>';
        }

        /* ---------- DEFAULT VRIJEDNOSTI ---------- */

        $default_shop_name_template = get_bloginfo( 'name' );
        $default_header_bg_template = '#005ea1';
        $default_logo_template      = trailingslashit( CUSTOM_INVOICES_PLUGIN_URL ) . 'images/custom-invoice-logo.png';

        $lang  = get_option( 'custom_invoices_email_language', 'hr' );
        $is_hr = ( $lang === 'hr' );

        $default_intro_text = $is_hr
            ? 'U prilogu se nalaze Vaši računi. Račune možete preuzeti i putem poveznica u nastavku.'
            : 'Attached you will find your invoices. You can also download them using the links below.';

        $default_contact_email_template = 'yourmail@yourmail.com';
        $default_contact_phone_template = '+385 00 000 000';

        $default_help_title_template = $is_hr ? 'Trebate pomoć?' : 'Need help?';
        $default_help_line_template  = $is_hr ? 'Kontaktirajte nas na:' : 'Contact us at:';
        $default_primary_color_template = '#2E658B';

        $default_footer_company_template = 'Your Company d.o.o.';
        $default_footer_address_template = $is_hr
            ? 'Ulica 1, 10000 Zagreb, Hrvatska'
            : 'Street 1, 10000 Zagreb, Croatia';
        $default_footer_tax_id_template  = $is_hr
            ? 'OIB: 31111111111'
            : 'VAT: 31111111111';

        $stored_shop_name = get_option( 'custom_invoices_default_shop_name', '' );
        $stored_header_bg = get_option( 'custom_invoices_default_header_bg_color', '' );
        $stored_logo_url  = get_option( 'custom_invoices_default_logo_url', '' );

        $stored_intro        = get_option( 'custom_invoices_default_content_intro', '' );
        $stored_contact_mail = get_option( 'custom_invoices_default_contact_email', '' );
        $stored_contact_tel  = get_option( 'custom_invoices_default_contact_phone', '' );
        $stored_help_title   = get_option( 'custom_invoices_help_title', '' );
        $stored_help_line    = get_option( 'custom_invoices_help_line', '' );
        $stored_primary_col  = get_option( 'custom_invoices_primary_color', '' );

        $stored_footer_company = get_option( 'custom_invoices_footer_company', '' );
        $stored_footer_address = get_option( 'custom_invoices_footer_address', '' );
        $stored_footer_tax_id  = get_option( 'custom_invoices_footer_tax_id', '' );
	// NOVO: custom naslov maila i hero podnaslov
$stored_email_title   = get_option( 'custom_invoices_email_title', '' );
$stored_hero_subtitle = get_option( 'custom_invoices_hero_subtitle', '' );

        $saved_test_email   = get_option( 'custom_invoices_test_email', '' );
        $current_user       = wp_get_current_user();
        $test_email_default = $saved_test_email
            ? $saved_test_email
            : ( ( $current_user && ! empty( $current_user->user_email ) )
                ? $current_user->user_email
                : 'test@example.com'
            );

        $default_shop_name = $stored_shop_name !== '' ? $stored_shop_name : $default_shop_name_template;
        $default_header_bg = $stored_header_bg !== '' ? $stored_header_bg : $default_header_bg_template;
        $default_logo_url  = $stored_logo_url  !== '' ? $stored_logo_url  : $default_logo_template;

        $default_intro         = $stored_intro        !== '' ? $stored_intro        : $default_intro_text;
        $default_contact_email = $stored_contact_mail !== '' ? $stored_contact_mail : $default_contact_email_template;
        $default_contact_phone = $stored_contact_tel  !== '' ? $stored_contact_tel  : $default_contact_phone_template;
        $default_help_title    = $stored_help_title   !== '' ? $stored_help_title   : $default_help_title_template;
        $default_help_line     = $stored_help_line    !== '' ? $stored_help_line    : $default_help_line_template;
        $default_primary_col   = $stored_primary_col  !== '' ? $stored_primary_col  : $default_primary_color_template;

        $footer_company = $stored_footer_company !== '' ? $stored_footer_company : $default_footer_company_template;
        $footer_address = $stored_footer_address !== '' ? $stored_footer_address : $default_footer_address_template;
        $footer_tax_id  = $stored_footer_tax_id  !== '' ? $stored_footer_tax_id  : $default_footer_tax_id_template;

        $mode           = get_option( 'custom_invoices_email_mode', 'default' );
        $saved_template = get_option( 'custom_invoices_email_template_html', '' );

        $site_name = get_bloginfo( 'name' );
        $copyright_example = sprintf(
            $is_hr
                ? 'Copyright © %d %s, Sva prava pridržana.'
                : 'Copyright © %d %s, All rights reserved.',
            date( 'Y' ),
            $site_name
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( $is_hr ? 'E-mail predložak za račune' : 'Invoice e-mail template' ); ?></h1>

            <form method="post" id="custom-invoices-email-form">
                <?php wp_nonce_field( 'custom_invoices_save_email_template', 'custom_invoices_email_template_nonce' ); ?>

                <input type="hidden" name="custom_invoices_do_reset" id="custom_invoices_do_reset" value="0">
                <?php wp_nonce_field( 'custom_invoices_reset_template', 'custom_invoices_reset_template_nonce' ); ?>

                <h2><?php echo esc_html( $is_hr ? 'Način rada' : 'Mode' ); ?></h2>

                <div id="custom-invoices-mode-switch" class="custom-invoices-mode-switch">
                    <label class="custom-invoices-mode-card" data-mode="default">
                        <input type="radio" name="custom_invoices_email_mode" value="default" <?php checked( $mode, 'default' ); ?> style="display:none;">
                        <div class="custom-invoices-mode-inner">
                            <div class="custom-invoices-mode-title">
                                <?php echo esc_html( $is_hr ? 'Koristi gotov predložak (HEADER + MAIN + FOOTER)' : 'Use built-in template (HEADER + MAIN + FOOTER)' ); ?>
                            </div>
                            <div class="custom-invoices-mode-desc">
                                <?php echo esc_html( $is_hr
                                    ? 'Gotov dizajn s prilagodljivim poljima i primarnom bojom teksta.'
                                    : 'Stock design with editable fields and a primary text color.'
                                ); ?>
                            </div>
                        </div>
                    </label>

                    <label class="custom-invoices-mode-card" data-mode="custom_html">
                        <input type="radio" name="custom_invoices_email_mode" value="custom_html" <?php checked( $mode, 'custom_html' ); ?> style="display:none;">
                        <div class="custom-invoices-mode-inner">
                            <div class="custom-invoices-mode-title">
                                <?php echo esc_html( $is_hr ? 'Koristi vlastiti HTML predložak' : 'Use custom HTML template' ); ?>
                            </div>
                            <div class="custom-invoices-mode-desc">
                                <?php echo esc_html( $is_hr
                                    ? 'Potpuno vlastiti HTML (HEADER/MAIN/FOOTER i boje se ignoriraju).'
                                    : 'Fully custom HTML (built-in HEADER/MAIN/FOOTER and colors are ignored).'
                                ); ?>
                            </div>
                        </div>
                    </label>
                </div>

                <style>
                    .custom-invoices-mode-switch{display:flex;flex-wrap:wrap;gap:12px;margin:10px 0 10px;}
                    .custom-invoices-mode-card{flex:1 1 260px;cursor:pointer;}
                    .custom-invoices-mode-inner{border:1px solid #ccd0d4;border-radius:6px;padding:12px 14px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,0.02);transition:all .15s;}
                    .custom-invoices-mode-card.custom-invoices-mode-active .custom-invoices-mode-inner{border-color:#2271b1;box-shadow:0 0 0 1px #2271b1;background:#f0f6fc;}
                    .custom-invoices-mode-title{font-weight:600;margin-bottom:4px;color:#1d2327;}
                    .custom-invoices-mode-desc{font-size:12px;color:#555d66;}
                    .custom-invoices-language-box{border:1px solid #2271b1;background:#f0f6ff;border-radius:4px;padding:8px 10px;margin:10px 0 20px;display:flex;align-items:center;gap:8px;}
                    .custom-invoices-language-label{font-weight:600;color:#1d2327;}
                    .custom-invoices-language-box select{min-width:120px;}
                    .custom-invoices-language-note{font-size:11px;color:#555d66;margin-left:8px;}
                    .custom-invoices-reset-box{margin:10px 0 0 0;}
                </style>

                <div class="custom-invoices-language-box">
                    <span class="custom-invoices-language-label"><?php echo esc_html( $is_hr ? 'Jezik e-maila:' : 'E-mail language:' ); ?></span>
                    <select name="custom_invoices_email_language">
                        <option value="hr" <?php selected( $lang, 'hr' ); ?>>Hrvatski</option>
                        <option value="en" <?php selected( $lang, 'en' ); ?>>English</option>
                    </select>
                    <button type="submit" name="custom_invoices_change_language" class="button">
                        <?php echo esc_html( $is_hr ? 'Primijeni jezik' : 'Apply language' ); ?>
                    </button>
                    <span class="custom-invoices-language-note">
                        <?php echo esc_html( $is_hr
                            ? 'Mijenja tekstove u e-mailu (naslovi, poruke).'
                            : 'Changes textual content in the e-mail (headings, messages).'
                        ); ?>
                    </span>
                </div>

                <div class="custom-invoices-reset-box">
                    <button type="button" id="custom-invoices-reset-btn" class="button button-secondary" style="border-color:#d63638;color:#d63638;">
                        <?php echo esc_html( $is_hr ? 'Resetiraj na standardni predložak' : 'Reset to standard template' ); ?>
                    </button>
                </div>

                <hr>

                <div id="custom-invoices-default-fields" style="<?php echo ( $mode === 'default' ) ? '' : 'display:none;'; ?>">

                    <!-- HEADER -->
                    <h2 style="margin-top:20px;"><?php echo esc_html( $is_hr ? 'HEADER (plavi dio + logo)' : 'HEADER (blue area + logo)' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_invoices_default_shop_name"><?php echo esc_html( $is_hr ? 'Shop Name / naziv tvrtke' : 'Shop Name / Company name' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_default_shop_name" name="custom_invoices_default_shop_name" class="regular-text" value="<?php echo esc_attr( $default_shop_name ); ?>"></td>
                        </tr>
    <!-- NOVO: glavni naslov e-maila -->
    <tr>
        <th>
            <label for="custom_invoices_email_title">
                <?php echo esc_html( $is_hr ? 'Naslov e-maila (glavni naslov)' : 'Main e-mail title' ); ?>
            </label>
        </th>
        <td>
            <input type="text"
                   id="custom_invoices_email_title"
                   name="custom_invoices_email_title"
                   class="regular-text"
                   value="<?php echo esc_attr( $stored_email_title ); ?>">
            <p class="description">
                <?php echo esc_html( $is_hr
                    ? 'Ako je prazno, koristi se zadani naslov ovisno o jeziku (npr. "Vaši računi" / "Your invoices").'
                    : 'If empty, a default title will be used depending on the language (e.g. "Vaši računi" / "Your invoices").'
                ); ?>
            </p>
        </td>
    </tr>

    <!-- NOVO: podnaslov u plavom headeru -->
    <tr>
        <th>
            <label for="custom_invoices_hero_subtitle">
                <?php echo esc_html( $is_hr ? 'Podnaslov u headeru (plavi dio)' : 'Header subtitle (blue area)' ); ?>
            </label>
        </th>
        <td>
            <input type="text"
                   id="custom_invoices_hero_subtitle"
                   name="custom_invoices_hero_subtitle"
                   class="regular-text"
                   value="<?php echo esc_attr( $stored_hero_subtitle ); ?>">
            <p class="description">
                <?php echo esc_html( $is_hr
                    ? 'Ako je prazno, koristi se zadani tekst ovisno o jeziku. Može sadržavati %s za broj narudžbe.'
                    : 'If empty, a default text will be used depending on the language. You can include %s for the order number.'
                ); ?>
            </p>
        </td>
    </tr>
                        <tr>
                            <th><label for="custom_invoices_default_header_bg_color"><?php echo esc_html( $is_hr ? 'Boja headera (HEX)' : 'Header background color (HEX)' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_default_header_bg_color" name="custom_invoices_default_header_bg_color" class="regular-text" value="<?php echo esc_attr( $default_header_bg ); ?>" placeholder="#005ea1"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html( $is_hr ? 'Logo slika' : 'Logo image' ); ?></th>
                            <td>
                                <div style="margin-bottom:8px;">
                                    <img id="custom-invoices-logo-preview" src="<?php echo esc_url( $default_logo_url ); ?>" alt="Logo preview" style="max-width:200px;height:auto;display:block;border:1px solid #ccd0d4;padding:4px;background:#fff;">
                                </div>
                                <input type="hidden" id="custom_invoices_default_logo_url" name="custom_invoices_default_logo_url" value="<?php echo esc_attr( $default_logo_url ); ?>">
                                <button type="button" class="button" id="custom-invoices-logo-upload"><?php echo esc_html( $is_hr ? 'Upload / Promijeni logo' : 'Upload / Change logo' ); ?></button>
                                <button type="button" class="button" id="custom-invoices-logo-remove" style="margin-left:4px;"><?php echo esc_html( $is_hr ? 'Ukloni logo' : 'Remove logo' ); ?></button>
                            </td>
                        </tr>
                    </table>

                    <!-- MAIN -->
                    <h2 style="margin-top:25px;"><?php echo esc_html( $is_hr ? 'MAIN (tekst i kontakt)' : 'MAIN (text and contact)' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_invoices_default_content_intro"><?php echo esc_html( $is_hr ? 'Uvodni paragraf' : 'Intro paragraph' ); ?></label></th>
                            <td><textarea id="custom_invoices_default_content_intro" name="custom_invoices_default_content_intro" rows="3" class="large-text"><?php echo esc_textarea( $default_intro ); ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="custom_invoices_default_contact_email"><?php echo esc_html( $is_hr ? 'Kontakt e-mail' : 'Contact e-mail' ); ?></label></th>
                            <td><input type="email" id="custom_invoices_default_contact_email" name="custom_invoices_default_contact_email" class="regular-text" value="<?php echo esc_attr( $default_contact_email ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_invoices_default_contact_phone"><?php echo esc_html( $is_hr ? 'Kontakt telefon' : 'Contact phone' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_default_contact_phone" name="custom_invoices_default_contact_phone" class="regular-text" value="<?php echo esc_attr( $default_contact_phone ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_invoices_help_title"><?php echo esc_html( $is_hr ? 'Naslov pomoći' : 'Help title' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_help_title" name="custom_invoices_help_title" class="regular-text" value="<?php echo esc_attr( $default_help_title ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_invoices_help_line"><?php echo esc_html( $is_hr ? 'Podnaslov pomoći' : 'Help intro line' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_help_line" name="custom_invoices_help_line" class="regular-text" value="<?php echo esc_attr( $default_help_line ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_invoices_primary_color"><?php echo esc_html( $is_hr ? 'Primarna boja teksta (HEX)' : 'Primary text color (HEX)' ); ?></label></th>
                            <td>
                                <input type="text" id="custom_invoices_primary_color" name="custom_invoices_primary_color" class="regular-text" value="<?php echo esc_attr( $default_primary_col ); ?>" placeholder="#2E658B">
                                <p class="description">
                                    <?php echo esc_html( $is_hr
                                        ? 'Ova boja se primjenjuje na pozdrav, naslov "Računi", naslov pomoći, te linkove e-maila i telefona.'
                                        : 'This color is used for greeting, "Invoices" heading, help heading, and e-mail/phone links.'
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <!-- FOOTER -->
                    <h2 style="margin-top:25px;"><?php echo esc_html( $is_hr ? 'FOOTER (sivi dio)' : 'FOOTER (grey area)' ); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label for="custom_invoices_footer_company"><?php echo esc_html( $is_hr ? 'Naziv tvrtke' : 'Company name' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_footer_company" name="custom_invoices_footer_company" class="regular-text" value="<?php echo esc_attr( $footer_company ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_invoices_footer_address"><?php echo esc_html( $is_hr ? 'Adresa' : 'Address' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_footer_address" name="custom_invoices_footer_address" class="regular-text" value="<?php echo esc_attr( $footer_address ); ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="custom_invoices_footer_tax_id"><?php echo esc_html( $is_hr ? 'OIB / VAT' : 'Tax ID' ); ?></label></th>
                            <td><input type="text" id="custom_invoices_footer_tax_id" name="custom_invoices_footer_tax_id" class="regular-text" value="<?php echo esc_attr( $footer_tax_id ); ?>"></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html( $is_hr ? 'Copyright (info)' : 'Copyright (info)' ); ?></th>
                            <td>
                                <code><?php echo esc_html( $copyright_example ); ?></code>
                                <p class="description">
                                    <?php echo esc_html( $is_hr
                                        ? 'Godina se automatski osvježava svake godine, na temelju server datuma.'
                                        : 'The year is generated automatically each year based on the server date.'
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div id="custom-invoices-custom-html" style="<?php echo ( $mode === 'custom_html' ) ? '' : 'display:none;'; ?>">
                    <h2><?php echo esc_html( $is_hr ? 'Vlastiti HTML predložak' : 'Custom HTML template' ); ?></h2>
                    <textarea name="custom_invoices_email_template_html" id="custom_invoices_email_template_html" rows="20" style="width:100%;font-family:monospace;"><?php echo esc_textarea( $saved_template ); ?></textarea>
                </div>

                <h2><?php echo esc_html( $is_hr ? 'Testni e-mail' : 'Test e-mail' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="custom_invoices_test_email"><?php echo esc_html( $is_hr ? 'E-mail za test' : 'Test e-mail address' ); ?></label></th>
                        <td><input type="email" id="custom_invoices_test_email" name="custom_invoices_test_email" class="regular-text" value="<?php echo esc_attr( $test_email_default ); ?>"></td>
                    </tr>
                </table>

                <p>
                    <button type="submit" name="custom_invoices_save_settings" class="button button-primary"><?php echo esc_html( $is_hr ? 'Spremi postavke' : 'Save settings' ); ?></button>
                    <button type="button" class="button" id="custom-invoices-preview-btn" style="margin-left:8px;"><?php echo esc_html( $is_hr ? 'Prikaži predložak' : 'Show preview' ); ?></button>
                    <button type="button" class="button" id="custom-invoices-send-test-btn" style="margin-left:8px;"><?php echo esc_html( $is_hr ? 'Pošalji testni mail' : 'Send test mail' ); ?></button>
                    <span id="custom-invoices-test-status" style="margin-left:10px;font-size:12px;"></span>
                </p>
            </form>

            <hr>

            <h2><?php echo esc_html( $is_hr ? 'Preview e-mail predloška' : 'E-mail template preview' ); ?></h2>
            <iframe id="custom-invoices-preview-frame" style="width:100%;max-width:800px;height:600px;border:1px solid #ccd0d4;background:#fff;"></iframe>
        </div>

        <script>
        (function($){

            var isDirty = false;

            function markDirty(){ isDirty = true; }
            function clearDirty(){ isDirty = false; }

            $(document).on('change keyup input', '#custom-invoices-email-form :input:not([type="hidden"])', function(){
                isDirty = true;
            });

            $('#custom-invoices-email-form').on('submit', function(){
                clearDirty();
            });

            window.addEventListener('beforeunload', function(e){
                if (!isDirty) return;
                var msg = '<?php echo esc_js( $is_hr ? 'Imate nespremljene promjene u predlošku. Jeste li sigurni da želite otići bez spremanja?' : 'You have unsaved changes in the template. Are you sure you want to leave without saving?' ); ?>';
                (e || window.event).returnValue = msg;
                return msg;
            });

            function customInvoicesUpdateModeCards(){
                var currentMode = $('input[name="custom_invoices_email_mode"]:checked').val() || 'default';
                $('.custom-invoices-mode-card').removeClass('custom-invoices-mode-active');
                $('.custom-invoices-mode-card[data-mode="'+currentMode+'"]').addClass('custom-invoices-mode-active');
                if (currentMode === 'default') {
                    $('#custom-invoices-default-fields').show();
                    $('#custom-invoices-custom-html').hide();
                } else {
                    $('#custom-invoices-default-fields').hide();
                    $('#custom-invoices-custom-html').show();
                }
            }

            $(document).on('click', '.custom-invoices-mode-card', function(){
                var mode = $(this).data('mode');
                $('input[name="custom_invoices_email_mode"][value="'+mode+'"]').prop('checked', true).trigger('change');
            });

            $(document).on('change', 'input[name="custom_invoices_email_mode"]', function(){
                customInvoicesUpdateModeCards();
                markDirty();
                customInvoicesRenderPreview();
            });

            var logoFrame;
            $('#custom-invoices-logo-upload').on('click', function(e){
                e.preventDefault();
                if (logoFrame) { logoFrame.open(); return; }
                logoFrame = wp.media({
                    title: '<?php echo esc_js( $is_hr ? 'Odaberi logo' : 'Select logo' ); ?>',
                    button: { text: '<?php echo esc_js( $is_hr ? 'Postavi logo' : 'Set logo' ); ?>' },
                    multiple:false
                });
                logoFrame.on('select', function(){
                    var attachment = logoFrame.state().get('selection').first().toJSON();
                    $('#custom_invoices_default_logo_url').val(attachment.url);
                    $('#custom-invoices-logo-preview').attr('src', attachment.url);
                    markDirty();
                    customInvoicesRenderPreview();
                });
                logoFrame.open();
            });

            $('#custom-invoices-logo-remove').on('click', function(e){
                e.preventDefault();
                $('#custom_invoices_default_logo_url').val('');
                $('#custom-invoices-logo-preview').attr('src', '<?php echo esc_js( $default_logo_template ); ?>');
                markDirty();
                customInvoicesRenderPreview();
            });

            $('#custom-invoices-reset-btn').on('click', function(e){
                e.preventDefault();
                if (confirm('<?php echo esc_js( $is_hr
                    ? 'Vratiti sve postavke e-mail predloška na standardni dizajn? Ova radnja ne može se poništiti.'
                    : 'Reset all e-mail template settings to the default design? This action cannot be undone.'
                ); ?>')) {
                    $('#custom_invoices_do_reset').val('1');
                    $('#custom-invoices-email-form').submit();
                }
            });

            function customInvoicesRenderPreview(){
                var mode   = $('input[name="custom_invoices_email_mode"]:checked').val() || 'default';
                var iframe = document.getElementById('custom-invoices-preview-frame');
                if (!iframe) return;
                var doc    = iframe.contentDocument || iframe.contentWindow.document;

                var demoOrderNumber = '1234';
                var demoBillingName = '<?php echo esc_js( $is_hr ? 'Ivana Ivić' : 'John Doe' ); ?>';
                var demoOrderDate   = '<?php echo esc_js( $is_hr ? '15.12.2025.' : 'Dec 15, 2025' ); ?>';
                var demoHomeUrl     = '<?php echo esc_js( home_url() ); ?>';

                var demoLinksList   =
                    '<table width="100%" border="0" cellspacing="0" cellpadding="0" style="margin-bottom:6px;">' +
                    '<tr>' +
                    '<td width="20" style="vertical-align:middle;padding-right:5px;font-size:16px;line-height:1;">📄</td>' +
                    '<td style="vertical-align:middle;">' +
                    '<a href="#" style="color:#005ea1;text-decoration:none;font-size:13px;"><?php echo $is_hr ? 'Preuzmi: ' : 'Download: '; ?><span style="font-weight:600;">faktura-1234.pdf</span></a>' +
                    '</td>' +
                    '</tr>' +
                    '</table>';

                var html = '';

                if (mode === 'custom_html') {
                    var tpl = $('#custom_invoices_email_template_html').val() || '';
                    tpl = tpl.replace(/{{order_number}}/g, demoOrderNumber)
                             .replace(/{{billing_name}}/g, demoBillingName)
                             .replace(/{{order_date}}/g, demoOrderDate)
                             .replace(/{{home_url}}/g, demoHomeUrl)
                             .replace(/{{links_list}}/g, demoLinksList);
                    html = tpl || '<p style="padding:10px;font-family:Arial;">(Custom HTML predložak je prazan.)</p>';
                } else {
                    var shopName   = $('#custom_invoices_default_shop_name').val()        || '<?php echo esc_js( $default_shop_name_template ); ?>';
                    var headerBg   = $('#custom_invoices_default_header_bg_color').val() || '<?php echo esc_js( $default_header_bg_template ); ?>';
                    var logoUrl    = $('#custom_invoices_default_logo_url').val()        || '<?php echo esc_js( $default_logo_template ); ?>';
                    var intro      = $('#custom_invoices_default_content_intro').val()  || '<?php echo esc_js( $default_intro_text ); ?>';
                    var emailV     = $('#custom_invoices_default_contact_email').val()  || '<?php echo esc_js( $default_contact_email_template ); ?>';
                    var phone      = $('#custom_invoices_default_contact_phone').val()  || '<?php echo esc_js( $default_contact_phone_template ); ?>';
                    var helpTitleV = $('#custom_invoices_help_title').val()              || '<?php echo esc_js( $default_help_title_template ); ?>';
                    var helpLineV  = $('#custom_invoices_help_line').val()               || '<?php echo esc_js( $default_help_line_template ); ?>';
                    var primaryCol = $('#custom_invoices_primary_color').val()           || '<?php echo esc_js( $default_primary_color_template ); ?>';

                    var company    = $('#custom_invoices_footer_company').val()          || '<?php echo esc_js( $default_footer_company_template ); ?>';
                    var address    = $('#custom_invoices_footer_address').val()          || '<?php echo esc_js( $default_footer_address_template ); ?>';
                    var taxId      = $('#custom_invoices_footer_tax_id').val()           || '<?php echo esc_js( $default_footer_tax_id_template ); ?>';

                    if (!/^#[0-9a-fA-F]{6}$/.test(headerBg)) {
                        headerBg = '<?php echo esc_js( $default_header_bg_template ); ?>';
                    }
                    if (!/^#[0-9a-fA-F]{6}$/.test(primaryCol)) {
                        primaryCol = '<?php echo esc_js( $default_primary_color_template ); ?>';
                    }

                    var companyLine = company + ' | ' + address + ' | ' + taxId;

                    var footerCopy  = '<?php
                        $site_name = get_bloginfo( 'name' );
                        echo esc_js(
                            sprintf(
                                $is_hr
                                    ? 'Copyright © %d %s, Sva prava pridržana.'
                                    : 'Copyright © %d %s, All rights reserved.',
                                date( 'Y' ),
                                $site_name
                            )
                        );
                    ?>';

                    html = ''
                    + '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Preview</title>'
                    + '<style>body{margin:0;padding:0;background:#f4f6f8;font-family:Arial,sans-serif;color:#7A7A7A;}table{border-collapse:collapse;}a{color:#005ea1;text-decoration:none;}.box-rounded{background:#f8fafc;border:1px solid #e6eef6;border-radius:14px;padding:16px;margin-bottom:26px;}.heading-uppercase{font-weight:700;text-transform:uppercase;margin:0 0 10px 0;}</style>'
                    + '</head><body>'
                    + '<table width="100%" style="background:#f4f6f8;padding:0 0 20px 0;"><tr><td align="center">'
                    + '<table width="600" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 6px 20px rgba(20,30,40,0.06);">'
                    + '<tr><td style="padding:18px 24px;background:'+headerBg+';color:#fff;">'
                    + '<h1 style="margin:0;font-size:22px;color:#fff;">'+shopName+'</h1>'
                    + '<p style="margin:8px 0 0 0;font-size:14px;color:rgba(255,255,255,0.95);"><?php echo esc_js( $is_hr ? 'Šaljemo Vam račun po Vašoj narudžbi #' : 'We are sending you the invoice for your order #' ); ?>'+demoOrderNumber+'</p>'
                    + '</td></tr>'
                    + '<tr><td style="padding:30px 24px 20px 24px;background:#fff;">'
                    + '<table width="100%"><tr>'
                    + '<td align="left"><a href="'+demoHomeUrl+'" target="_blank"><img src="'+logoUrl+'" alt="'+shopName+'" width="160" style="display:block;max-width:160px;height:auto;"></a></td>'
                    + '</tr></table></td></tr>'
                    + '<tr><td style="padding:10px 24px 22px 24px;font-size:15px;line-height:1.6;">'
                    + '<p style="margin:0 0 10px 0;font-weight:600;color:'+primaryCol+';"><?php echo esc_js( $is_hr ? 'Poštovani ' : 'Dear ' ); ?>'+demoBillingName+',</p>'
                    + '<p style="margin:6px 0 14px 0;">'+intro+'</p>'
                    + '<div class="box-rounded">'
                    + '<p class="heading-uppercase" style="font-size:14px;color:'+primaryCol+';"><?php echo esc_js( $is_hr ? 'Računi' : 'Invoices' ); ?></p>'
                    + demoLinksList
                    + '</div>'
                    + '<p class="heading-uppercase" style="margin:18px 0 8px 0;color:'+primaryCol+';">'+helpTitleV+'</p>'
                    + '<p style="margin:0 0 8px 0;font-size:14px;">'+helpLineV+'</p>'
                    + '<table width="100%"><tr><td style="padding-bottom:6px;">'
                    + '<table><tr><td width="25" style="font-size:16px;">✉️</td><td><a href="mailto:'+emailV+'" style="color:'+primaryCol+';font-weight:600;">'+emailV+'</a></td></tr></table>'
                    + '</td></tr><tr><td>'
                    + '<table><tr><td width="25" style="font-size:16px;">📞</td><td><a href="tel:'+phone.replace(/\\s+/g,"")+'" style="color:'+primaryCol+';font-weight:600;">'+phone+'</a></td></tr></table>'
                    + '</td></tr></table>'
                    + '</td></tr>'
                    + '<tr><td style="background:#f8fafc;padding:18px 24px;font-size:12px;text-align:center;color:#6b7280;">'
                    + '<p style="margin:6px 0 4px 0;"><span style="font-size:9px;color:#7A7A7A;">'+companyLine+'</span></p>'
                    + '<p style="margin:4px 0 8px 0;"><span style="font-size:9px;color:#7A7A7A;"><em>'+footerCopy+'</em></span></p>'
                    + '</td></tr></table></td></tr></table></body></html>';
                }

                doc.open();
                doc.write(html);
                doc.close();
            }

            $('#custom-invoices-preview-btn').on('click', function(e){
                e.preventDefault();
                customInvoicesRenderPreview();
            });

            function customInvoicesSendTestMail() {
                var statusEl = $('#custom-invoices-test-status');
                var email    = $('#custom_invoices_test_email').val();

                if (!email) {
                    statusEl.text('<?php echo esc_js( $is_hr ? 'Unesite e-mail adresu za test.' : 'Please enter a test e-mail address.' ); ?>').css('color','red');
                    return;
                }

                statusEl.text('<?php echo esc_js( $is_hr ? 'Šaljem testni e-mail...' : 'Sending test e-mail...' ); ?>').css('color','#2271b1');

                $.post(ajaxurl, {
                    action: 'custom_invoices_send_test_email',
                    email: email,
                    security: '<?php echo wp_create_nonce( 'custom_invoices_send_test_email_nonce' ); ?>'
                }, function(resp){
                    if (resp && resp.success) {
                        statusEl.text(resp.data).css('color','green');
                    } else {
                        var msg = resp && resp.data ? resp.data : '<?php echo esc_js( $is_hr ? 'Došlo je do pogreške pri slanju testnog e-maila.' : 'An error occurred while sending the test e-mail.' ); ?>';
                        statusEl.text(msg).css('color','red');
                    }
                }).fail(function(){
                    statusEl.text('<?php echo esc_js( $is_hr ? 'Došlo je do pogreške pri slanju testnog e-maila.' : 'An error occurred while sending the test e-mail.' ); ?>').css('color','red');
                });
            }

            $('#custom-invoices-send-test-btn').on('click', function(e){
                e.preventDefault();

                if (isDirty) {
                    var msg = '<?php echo esc_js( $is_hr
                        ? "Napravili ste izmjene u predlošku koje još nisu spremljene.\n\nŽelite li spremiti predložak i zatim odmah poslati testni e-mail?"
                        : "You have unsaved changes in the template.\n\nDo you want to save the template and then immediately send the test e-mail?"
                    ); ?>';

                    if (confirm(msg)) {
                        try { sessionStorage.setItem('custom_invoices_send_test_after_save', '1'); } catch(e){}
                        $('#custom-invoices-email-form')[0].submit();
                        return;
                    } else {
                        return;
                    }
                }

                customInvoicesSendTestMail();
            });

            $(window).on('load', function(){
                customInvoicesUpdateModeCards();
                customInvoicesRenderPreview();
                clearDirty();

                try {
                    var shouldSendAfterSave = sessionStorage.getItem('custom_invoices_send_test_after_save');
                    if (shouldSendAfterSave === '1') {
                        sessionStorage.removeItem('custom_invoices_send_test_after_save');
                        customInvoicesSendTestMail();
                    }
                } catch(e){}
            });

        })(jQuery);
        </script>
        <?php
    }

    public static function ajax_send_test_email() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( __( 'Nemate dopuštenje za ovu akciju.', 'custom-invoices' ) );
        }

        if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'custom_invoices_send_test_email_nonce' ) ) {
            wp_send_json_error( __( 'Sigurnosna provjera nije prošla (nonce).', 'custom-invoices' ) );
        }

        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( __( 'Neispravna e-mail adresa za test.', 'custom-invoices' ) );
        }

        $order = null;
        if ( class_exists( 'WC_Order' ) ) {
            $orders = wc_get_orders( array( 'limit' => 1 ) );
            if ( $orders ) {
                $order = $orders[0];
            }
        }

        if ( ! $order instanceof WC_Order ) {
            $content = '<p>' . esc_html__( 'Ovo je testni e-mail predloška računa.', 'custom-invoices' ) . '</p>';

            add_filter( 'wp_mail_content_type', 'custom_invoices_mail_content_type_html' );
            $subject = __( 'Testni e-mail predloška računa', 'custom-invoices' );
            $sent    = wp_mail( $email, $subject, $content );
            remove_filter( 'wp_mail_content_type', 'custom_invoices_mail_content_type_html' );

            if ( $sent ) {
                wp_send_json_success( __( 'Testni e-mail je poslan.', 'custom-invoices' ) );
            } else {
                wp_send_json_error( __( 'Testni e-mail nije moguće poslati (wp_mail je vratio false).', 'custom-invoices' ) );
            }
        }

        $attachments      = array();
        $links_list_items = '<p style="font-size:13px;">[' . esc_html__( 'Ovdje bi u stvarnom mailu bili linkovi na PDF račune.', 'custom-invoices' ) . ']</p>';

        if ( ! function_exists( 'custom_invoices_get_email_html' ) ) {
            wp_send_json_error( __( 'Nije definirana funkcija za generiranje e-mail HTML-a.', 'custom-invoices' ) );
        }

        $email_content = custom_invoices_get_email_html( $order, $attachments, $links_list_items );
        if ( empty( $email_content ) ) {
            $email_content = '<p>' . esc_html__( 'Ovo je testni e-mail predloška računa.', 'custom-invoices' ) . '</p>';
        }

        add_filter( 'wp_mail_content_type', 'custom_invoices_mail_content_type_html' );
        $lang    = get_option( 'custom_invoices_email_language', 'hr' );
        $subject = ( $lang === 'en' )
            ? 'Test invoice email template'
            : 'Testni e-mail predloška računa';

        $sent = wp_mail( $email, $subject, $email_content );
        remove_filter( 'wp_mail_content_type', 'custom_invoices_mail_content_type_html' );

        if ( $sent ) {
            wp_send_json_success( __( 'Testni e-mail je poslan.', 'custom-invoices' ) );
        } else {
            wp_send_json_error( __( 'Testni e-mail nije moguće poslati (wp_mail je vratio false).', 'custom-invoices' ) );
        }
    }
}