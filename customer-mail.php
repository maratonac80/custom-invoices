<?php
/**
 * Helper za generiranje HTML e-maila za račune.
 *
 * OVAJ FILE:
 * - sadrži funkciju custom_invoices_get_email_html() koja vraća kompletan HTML e-maila,
 * - koristi opcije plugina (custom_invoices_*) za jezik, boje, tekstove i footer podatke,
 * - podržava dva moda:
 *   - "default" – gotov predložak s poljima iz admina,
 *   - "custom_html" – vlastiti HTML predložak s placeholderima {{order_number}}, {{billing_name}}, {{order_date}}, {{home_url}}, {{links_list}}.
 *
 * LOGIKA TEKSTOVA:
 * - Za ključne tekstove (naslov, uvodni paragraf, podnaslov) plugin ima
 *   predefinirane vrijednosti po jeziku (HR/EN).
 * - Ako je odgovarajuće admin polje PRAZNO -> koristi se predefinirani tekst za odabrani jezik.
 * - Ako admin upiše svoj tekst u polje -> koristi se taj tekst, bez obzira na jezik.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vrati HTML sadržaj e-maila za zadanu narudžbu.
 *
 * @param WC_Order $order
 * @param array    $attachments      Putanje do PDF priloga (nije nužno za sadržaj).
 * @param string   $links_list_items HTML tablice s linkovima na PDF-ove.
 *
 * @return string  HTML e-maila.
 */
function custom_invoices_get_email_html( $order, $attachments, $links_list_items ) {

    if ( ! $order instanceof WC_Order ) {
        return '';
    }

    // Odabir jezika iz opcije
    $lang = get_option( 'custom_invoices_email_language', 'hr' ); // hr | en

    $order_number = $order->get_order_number();
    $billing_name = $order->get_formatted_billing_full_name();
    $home_url     = home_url();

    // Ako se koristi custom HTML, možemo i dalje dati datum kao placeholder (ali ga ne prikazujemo u default templatu)
    $order_datetime = $order->get_date_created();
    if ( $order_datetime instanceof WC_DateTime ) {
        if ( $lang === 'en' ) {
            $order_date = $order_datetime->date_i18n( 'M j, Y' );
        } else {
            $order_date = $order_datetime->date_i18n( 'd.m.Y.' );
        }
    } else {
        $order_date = '';
    }

    // Mode: default vs custom HTML
    $mode            = get_option( 'custom_invoices_email_mode', 'default' ); // default | custom_html
    $custom_template = get_option( 'custom_invoices_email_template_html', '' );

    /* --------------------------------------------------
     * 1) Vlastiti HTML
     * -------------------------------------------------- */
    if ( $mode === 'custom_html' && ! empty( $custom_template ) ) {

        $replacements = array(
            '{{order_number}}' => esc_html( $order_number ),
            '{{billing_name}}' => esc_html( $billing_name ),
            '{{order_date}}'   => esc_html( $order_date ),
            '{{home_url}}'     => esc_url( $home_url ),
            '{{links_list}}'   => $links_list_items,
        );

        $html = strtr( $custom_template, $replacements );

        return $html;
    }

    /* --------------------------------------------------
     * 2) Gotov (default) predložak s poljima iz admina
     * -------------------------------------------------- */

    // HEADER — shop name
    $default_shop_name = get_bloginfo( 'name' );
    $shop_name = get_option(
        'custom_invoices_default_shop_name',
        $default_shop_name
    );

    // HEADER — boja pozadine
    $header_bg_color = get_option(
        'custom_invoices_default_header_bg_color',
        '#005ea1'
    );
    if ( empty( $header_bg_color ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $header_bg_color ) ) {
        $header_bg_color = '#005ea1';
    }

    // PRIMARNA BOJA TEKSTA (sve što je bilo "plavo")
    $primary_color = get_option( 'custom_invoices_primary_color', '#2E658B' );
    if ( empty( $primary_color ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $primary_color ) ) {
        $primary_color = '#2E658B';
    }

    // HEADER — logo
    // Default logo u pluginu: /images/custom-invoice-logo.png
    if ( defined( 'CUSTOM_INVOICES_PLUGIN_URL' ) ) {
        $default_logo = trailingslashit( CUSTOM_INVOICES_PLUGIN_URL ) . 'images/custom-invoice-logo.png';
    } else {
        $default_logo = plugins_url( 'images/custom-invoice-logo.png', __FILE__ );
    }

    $logo_url = get_option( 'custom_invoices_default_logo_url', $default_logo );
    if ( empty( $logo_url ) ) {
        $logo_url = $default_logo;
    }

    // MAIN — kontakt (generički)
    $contact_email = get_option( 'custom_invoices_default_contact_email', 'yourmail@yourmail.com' );
    $contact_phone = get_option( 'custom_invoices_default_contact_phone', '+385 00 000 000' );

    /* -----------------------------------------------
     *  MAIN — uvodni tekst / naslov / podnaslov
     *  - default po jeziku
     *  - override vrijednostima iz admin polja (ako nisu prazna)
     * ----------------------------------------------- */

    // 1) Uvodni paragraf (Intro paragraph)
    if ( $lang === 'en' ) {
        $default_intro_text = 'Attached you will find your invoices. You can also download them using the links below.';
    } else {
        $default_intro_text = 'U prilogu se nalaze Vaši računi. Račune možete preuzeti i putem poveznica u nastavku.';
    }
    $stored_intro   = get_option( 'custom_invoices_default_content_intro', '' );
    $content_intro  = $stored_intro !== '' ? $stored_intro : $default_intro_text;

    // 2) Naslov e-maila (HTML <title> i glavni naslov) – opcionalni override
    $stored_email_title = get_option( 'custom_invoices_email_title', '' );

    // 3) Podnaslov u headeru (hero subtitle) – opcionalni override
    $stored_hero_subtitle_tpl = get_option( 'custom_invoices_hero_subtitle', '' );

    // 4) Tekstovi pomoći (Help section) – već imaš override logiku
    $stored_help_title = get_option( 'custom_invoices_help_title', '' );
    $stored_help_line  = get_option( 'custom_invoices_help_line', '' );

    // Default vrijednosti po jeziku
    if ( $lang === 'en' ) {
        $default_email_title       = 'Your invoices';
        $default_hero_subtitle_tpl = 'We are sending you the invoice for your order #%s';
        $default_greeting_tpl      = 'Dear %s,';
        $default_box_title         = 'Invoices';
        $default_box_hint          = 'You can also find your invoices in your account, under the "My Invoices" tab.';
        $default_subject_tpl       = 'Your invoice for order #%s';
        $default_help_title        = 'Need help?';
        $default_help_line         = 'Contact us at:';
    } else {
        $default_email_title       = 'Vaši računi';
        $default_hero_subtitle_tpl = 'Šaljemo Vam račun po Vašoj narudžbi #%s';
        $default_greeting_tpl      = 'Poštovani %s,';
        $default_box_title         = 'Računi';
        $default_box_hint          = 'Račune možete pronaći i u svom korisničkom računu, u kartici "Moji računi".';
        $default_subject_tpl       = 'Vaš račun za narudžbu #%s';
        $default_help_title        = 'Trebate pomoć?';
        $default_help_line         = 'Kontaktirajte nas na:';
    }

    // Primjena override‑a
    $email_title       = $stored_email_title       !== '' ? $stored_email_title       : $default_email_title;
    $hero_subtitle_tpl = $stored_hero_subtitle_tpl !== '' ? $stored_hero_subtitle_tpl : $default_hero_subtitle_tpl;
    $greeting_tpl      = $default_greeting_tpl; // trenutno bez zasebnog override polja
    $box_title         = $default_box_title;
    $box_hint          = $default_box_hint;

    $help_title = $stored_help_title !== '' ? $stored_help_title : $default_help_title;
    $help_line  = $stored_help_line  !== '' ? $stored_help_line  : $default_help_line;

    // FOOTER — generička tvrtka / adresa / OIB/VAT
    if ( $lang === 'en' ) {
        $default_footer_company = 'Your Company d.o.o.';
        $default_footer_address = 'Street 1, 10000 Zagreb, Croatia';
        $default_footer_tax_id  = 'VAT: 31111111111';
    } else {
        $default_footer_company = 'Your Company d.o.o.';
        $default_footer_address = 'Ulica 1, 10000 Zagreb, Hrvatska';
        $default_footer_tax_id  = 'OIB: 31111111111';
    }

    $footer_company = get_option( 'custom_invoices_footer_company', $default_footer_company );
    $footer_address = get_option( 'custom_invoices_footer_address', $default_footer_address );
    $footer_tax_id  = get_option( 'custom_invoices_footer_tax_id',  $default_footer_tax_id );

    $company_line = trim( $footer_company . ' | ' . $footer_address . ' | ' . $footer_tax_id, ' |' );

    // FOOTER — copyright text po jeziku
    $site_name = get_bloginfo( 'name' );
    if ( $lang === 'en' ) {
        $footer_copy = sprintf( 'Copyright © %d %s, All rights reserved.', date( 'Y' ), $site_name );
    } else {
        $footer_copy = sprintf( 'Copyright © %d %s, Sva prava pridržana.', date( 'Y' ), $site_name );
    }

    // Subject template (za wp_mail)
    $subject_tpl = $default_subject_tpl;

    // Subject u global (čita ga Custom_Invoices_Email_Sender)
    $GLOBALS['custom_invoices_last_email_subject'] = sprintf( $subject_tpl, $order_number );

    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo ( $lang === 'en' ) ? 'en' : 'hr'; ?>">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,initial-scale=1">
      <title><?php echo esc_html( $email_title ); ?></title>
      <style>
        body { margin:0; padding:0; background-color:#f4f6f8; font-family:Arial, Helvetica, sans-serif; color:#7A7A7A; }
        table { border-collapse:collapse; }
        a { color:#005ea1; text-decoration:none; }
        .box-rounded { background:#f8fafc; border:1px solid #e6eef6; border-radius:14px; padding:16px; margin-bottom:26px; }
        .heading-uppercase { font-weight:700; text-transform:uppercase; margin:0 0 10px 0; }
      </style>
    </head>
    <body style="margin:0; padding:0; background-color:#f4f6f8;">
      <table width="100%" border="0" cellspacing="0" cellpadding="0" role="presentation" style="background-color:#f4f6f8; padding:0 0 20px 0;">
        <tr>
          <td align="center">
            <table width="600" border="0" cellspacing="0" cellpadding="0" role="presentation" style="width:600px; max-width:600px; background:#ffffff; border-radius:14px; overflow:hidden; box-shadow:0 6px 20px rgba(20,30,40,0.06); margin-top:0px;">
              
              <!-- HEADER (plavi dio) -->
              <tr>
                <td style="padding:18px 24px; background:<?php echo esc_attr( $header_bg_color ); ?>; color:#ffffff;">
                   <h1 style="margin:0; font-size:22px; line-height:1.2; font-weight:700; color:#ffffff;">
                     <?php echo esc_html( $shop_name ); ?>
                   </h1>
                   <p style="margin:8px 0 0 0; font-size:14px; color:rgba(255,255,255,0.95);">
                     <?php
                     printf(
                         esc_html( $hero_subtitle_tpl ),
                         esc_html( $order_number )
                     );
                     ?>
                   </p>
                </td>
              </tr>

              <!-- HEADER – samo logo -->
              <tr>
                <td style="padding:30px 24px 20px 24px; background:#ffffff;">
                  <table width="100%" border="0" cellspacing="0" cellpadding="0">
                    <tr>
                      <td align="left" style="vertical-align:middle;">
                        <a href="<?php echo esc_url( $home_url ); ?>" target="_blank">
                          <img src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( $shop_name ); ?>" width="160" style="display:block; max-width:160px; height:auto;">
                        </a>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>

              <!-- MAIN -->
              <tr>
                <td style="padding:10px 24px 22px 24px; color:#7A7A7A; font-size:15px; line-height:1.6;">
                  <!-- Greet -->
                  <p style="margin:0 0 10px 0; font-weight:600; color:<?php echo esc_attr( $primary_color ); ?>;">
                    <?php
                    printf(
                        esc_html( $greeting_tpl ),
                        esc_html( $billing_name )
                    );
                    ?>
                  </p>
                  <p style="margin:6px 0 14px 0;">
                    <?php echo esc_html( $content_intro ); ?>
                  </p>

                  <!-- BOX: Računi -->
                  <div class="box-rounded" style="font-size:15px; color:#7A7A7A;">
                     <p class="heading-uppercase" style="font-size:14px; color:<?php echo esc_attr( $primary_color ); ?>;"><?php echo esc_html( $box_title ); ?></p>
                     <?php echo $links_list_items; ?>
                     <p style="margin:10px 0 0 0; font-size:12px; color:#6b7280; padding-top:8px; border-top:1px solid #e6eef6;">
                        <em><?php echo esc_html( $box_hint ); ?></em>
                     </p>
                  </div>

                  <!-- MAIN – kontakt -->
                  <p class="heading-uppercase" style="margin:18px 0 8px 0; font-weight:700; color:<?php echo esc_attr( $primary_color ); ?>;">
                    <?php echo esc_html( $help_title ); ?>
                  </p>
                  
                  <p style="margin:0 0 8px 0; font-size:14px;">
                    <?php echo esc_html( $help_line ); ?>
                  </p>
                  
                  <table width="100%" border="0" cellspacing="0" cellpadding="0">
                     <tr>
                        <td style="padding-bottom:6px;">
                           <table border="0" cellspacing="0" cellpadding="0">
                               <tr>
                                   <td width="25" style="vertical-align:middle; font-size:16px;">✉️</td>
                                   <td style="vertical-align:middle;">
                                       <a href="mailto:<?php echo esc_attr( $contact_email ); ?>" style="color:<?php echo esc_attr( $primary_color ); ?>; font-weight:600; text-decoration:none;"><?php echo esc_html( $contact_email ); ?></a>
                                   </td>
                               </tr>
                           </table>
                        </td>
                     </tr>
                     <tr>
                        <td>
                           <table border="0" cellspacing="0" cellpadding="0">
                               <tr>
                                   <td width="25" style="vertical-align:middle; font-size:16px;">📞</td>
                                   <td style="vertical-align:middle;">
                                       <a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $contact_phone ) ); ?>" style="color:<?php echo esc_attr( $primary_color ); ?>; font-weight:600; text-decoration:none;"><?php echo esc_html( $contact_phone ); ?></a>
                                   </td>
                               </tr>
                           </table>
                        </td>
                     </tr>
                  </table>

                </td>
              </tr>

              <!-- FOOTER -->
              <tr>
                <td style="background:#f8fafc; padding:18px 24px; color:#6b7280; font-size:12px; text-align:center;">
                    <?php if ( $company_line ) : ?>
                        <p style="margin:6px 0 4px 0;">
                            <span style="font-size:9px; color:#7A7A7A;"><?php echo esc_html( $company_line ); ?></span>
                        </p>
                    <?php endif; ?>
                    <p style="margin:4px 0 8px 0;">
                        <span style="font-size:9px; color:#7A7A7A;">
                            <em><?php echo esc_html( $footer_copy ); ?></em>
                        </span>
                    </p>
                </td>
              </tr>

            </table> 
          </td>
        </tr>
      </table>
    </body>
    </html>
    <?php

    return ob_get_clean();
}