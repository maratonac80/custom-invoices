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

        $response = wp_remote_get( $repo_url . '/releases/latest' );
        if ( is_wp_error( $response ) ) {
            return $transient;
        }

        $latest_version = json_decode( wp_remote_retrieve_body( $response ), true )['tag_name'];
        if ( version_compare( $plugin_data['Version'], $latest_version, '<' ) ) {
            $transient->response[ plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ] = (object) array(
                'slug'        => $plugin_slug,
                'new_version' => $latest_version,
                'url'         => $repo_url,
                'package'     => $repo_url . '/archive/refs/tags/' . $latest_version . '.zip',
            );
        }
        return $transient;
    }

    public static function plugin_info( $res, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== 'custom-invoices' ) {
            return false;
        }

        return (object) array(
            'name'        => 'Custom Invoices Plugin',
            'slug'        => 'custom-invoices',
            'version'     => '1.0.0',
            'author'      => 'Zoran Filipović <https://peroneus.hr>',
            'description' => 'Plugin for custom invoice handling.',
            'homepage'    => 'https://github.com/maratonac80/custom-invoices',
            'sections'    => array(
                'description' => 'Easily manage invoices and update the plugin from WordPress admin.',
            ),
            'download_link' => 'https://github.com/maratonac80/custom-invoices/archive/refs/tags/v1.0.0.zip',
        );
    }
}