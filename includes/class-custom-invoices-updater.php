<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Updater {

    /**
     * GitHub latest published release (for version check only)
     */
    const GITHUB_API_URL = 'https://api.github.com/repos/maratonac80/custom-invoices/releases/latest';

    /**
     * ZIP hosted on YOUR server (public, direct download)
     * MUST contain folder: custom-invoices/
     */
    const PACKAGE_URL = 'https://fizioshop.com/test/custom-invoices/custom-invoices.zip';

    const FALLBACK_VERSION = '1.1.1';

    /**
     * Normalize version (strip v / V)
     */
    private static function normalize_version( $version ) {
        return ltrim( (string) $version, 'vV' );
    }

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_directory_name' ), 10, 4 );
    }

    /**
     * Get latest version number from GitHub release
     */
    private static function get_latest_version_from_github() {
        $response = wp_remote_get(
            self::GITHUB_API_URL,
            array(
                'timeout' => 15,
                'headers' => array(
                    'User-Agent' => 'Custom-Invoices-Plugin-Updater',
                    'Accept'     => 'application/vnd.github+json',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            error_log( 'CI Updater: GitHub request failed' );
            return false;
        }

        if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
            error_log( 'CI Updater: GitHub HTTP error' );
            return false;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
            error_log( 'CI Updater: Invalid GitHub response' );
            return false;
        }

        return self::normalize_version( $data['tag_name'] );
    }

    public static function check_for_updates( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( CUSTOM_INVOICES_PLUGIN_FILE );

        $current_version = self::normalize_version(
            isset( $plugin_data['Version'] ) ? $plugin_data['Version'] : ''
        );

        $latest_version = self::get_latest_version_from_github();
        if ( ! $latest_version ) {
            return $transient;
        }

        if ( version_compare( $current_version, $latest_version, '<' ) ) {
            $transient->response[ plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ] = (object) array(
                // slug mora odgovarati INSTALIRANOM folderu
                'slug'        => dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ),
                'plugin'      => plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ),
                'new_version' => $latest_version,
                'url'         => 'https://github.com/maratonac80/custom-invoices',
                'package'     => self::PACKAGE_URL,
            );
        }

        return $transient;
    }

    public static function plugin_info( $res, $action, $args ) {
        $installed_slug = dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) );

        if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== $installed_slug ) {
            return $res;
        }

        $latest_version = self::get_latest_version_from_github();
        if ( ! $latest_version ) {
            $latest_version = self::FALLBACK_VERSION;
        }

        return (object) array(
            'name'          => 'Custom Invoices Plugin',
            'slug'          => $installed_slug,
            'version'       => $latest_version,
            'author'        => 'Zoran Filipović <https://peroneus.hr>',
            'homepage'      => 'https://github.com/maratonac80/custom-invoices',
            'description'   => 'Plugin for custom invoice handling.',
            'download_link' => self::PACKAGE_URL,
        );
    }

    /**
     * Rename unpacked folder to match installed plugin folder
     */
    public static function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        global $wp_filesystem;

        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ) {
            return $source;
        }

        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem ) {
            return $source;
        }

        $plugin_folder = dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) );
        if ( basename( $source ) === $plugin_folder ) {
            return $source;
        }

        $parent     = trailingslashit( dirname( $source ) );
        $new_source = $parent . $plugin_folder;

        if ( $wp_filesystem->exists( $new_source ) ) {
            $wp_filesystem->delete( $new_source, true );
        }

        if ( $wp_filesystem->move( $source, $new_source, true ) ) {
            return $new_source;
        }

        return $source;
    }
}
