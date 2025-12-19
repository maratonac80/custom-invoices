<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Updater {

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
    }

    public static function check_for_updates( $transient ) {
        $plugin_slug = 'custom-invoices';
        $repo_url    = 'https://github.com/maratonac80/custom-invoices';
        $plugin_data = get_plugin_data( CUSTOM_INVOICES_PLUGIN_FILE );

        // Dohvati podatke o zadnjem release-u s GitHub-a
        $response = wp_remote_get( 'https://api.github.com/repos/maratonac80/custom-invoices/releases/latest' );
        if ( is_wp_error( $response ) ) {
            error_log( 'API error: ' . $response->get_error_message() );
            return $transient;
        }

        // Decodiraj podatke
        $release_data = json_decode( wp_remote_retrieve_body( $response ), true );
        $latest_version = isset( $release_data['tag_name'] ) ? $release_data['tag_name'] : '';

        // Provjera trenutne verzije i dohvat nove
        if ( version_compare( $plugin_data['Version'], $latest_version, '<' ) ) {
            $transient->response[ plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ] = (object) array(
                'slug'        => $plugin_slug,
                'new_version' => $latest_version,
                'url'         => $repo_url,
                'package'     => $repo_url . '/archive/refs/tags/' . $latest_version . '.zip',
            );
        } else {
            error_log( 'Plugin is already up-to-date.' );
        }

        return $transient;
    }

    public static function plugin_info( $res, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== 'custom-invoices' ) {
            return false;
        }

        $latest_version = 'v1.0.1'; // Zamijeniti sa API dohvatom ako je potrebno

        return (object) array(
            'name'        => 'Custom Invoices Plugin',
            'slug'        => 'custom-invoices',
            'version'     => $latest_version,
            'author'      => 'Zoran Filipović <https://peroneus.hr>',
            'description' => 'Plugin for custom invoice handling.',
            'homepage'    => 'https://github.com/maratonac80/custom-invoices',
            'sections'    => array(
                'description' => 'Easily manage invoices and update the plugin from WordPress admin.',
            ),
            'download_link' => 'https://github.com/maratonac80/custom-invoices/archive/refs/tags/' . $latest_version . '.zip',
        );
    }
}