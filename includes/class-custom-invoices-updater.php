<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Updater {

    const GITHUB_API_URL = 'https://api.github.com/repos/maratonac80/custom-invoices/releases/latest';

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
    }

    private static function get_latest_version_from_github() {
        $response = wp_remote_get( self::GITHUB_API_URL );
        if ( is_wp_error( $response ) ) {
            error_log( 'API error: ' . $response->get_error_message() );
            return false;
        }

        $release_data = json_decode( wp_remote_retrieve_body( $response ), true );
        return isset( $release_data['tag_name'] ) ? $release_data['tag_name'] : false;
    }

    public static function check_for_updates( $transient ) {
        $plugin_slug = 'custom-invoices';
        $repo_url    = 'https://github.com/maratonac80/custom-invoices';
        $plugin_data = get_plugin_data( CUSTOM_INVOICES_PLUGIN_FILE );

        // Dohvati podatke o zadnjem release-u s GitHub-a
        $latest_version = self::get_latest_version_from_github();
        if ( ! $latest_version ) {
            return $transient;
        }

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

        // Dohvati najnoviju verziju s GitHub-a
        $latest_version = self::get_latest_version_from_github();
        if ( ! $latest_version ) {
            $latest_version = 'v1.0.1'; // Fallback verzija
        }

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