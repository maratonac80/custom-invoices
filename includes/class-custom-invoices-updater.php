<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Updater {

    const GITHUB_API_URL = 'https://api.github.com/repos/maratonac80/custom-invoices/releases/latest';
    const FALLBACK_VERSION = 'v1.0.9';

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_directory_name' ), 10, 4 );
    }

    private static function get_latest_version_from_github() {
        $args = array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Custom-Invoices-Plugin-Updater',
            ),
        );

        $response = wp_remote_get( self::GITHUB_API_URL, $args );
        if ( is_wp_error( $response ) ) {
            error_log( 'Custom Invoices Updater - Failed to fetch latest version from GitHub API' );
            return false;
        }

        $release_data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'Custom Invoices Updater - Failed to decode GitHub API response: ' . json_last_error_msg() );
            return false;
        }

        if ( ! is_array( $release_data ) ) {
            error_log( 'Custom Invoices Updater - GitHub API response is not an array' );
            return false;
        }

        if ( ! isset( $release_data['tag_name'] ) || empty( $release_data['tag_name'] ) ) {
            error_log( 'Custom Invoices Updater - Invalid or missing tag_name in GitHub API response' );
            return false;
        }

        return $release_data['tag_name'];
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
                'plugin'      => plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ),
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
            $latest_version = self::FALLBACK_VERSION;
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

    /**
     * Fix the directory name when updating from GitHub archive.
     * GitHub archives have directory names like 'custom-invoices-1.0.7'
     * but WordPress expects 'custom-invoices'.
     *
     * @param string      $source        File source location.
     * @param string      $remote_source Remote file source location.
     * @param WP_Upgrader $upgrader      WP_Upgrader instance.
     * @param array       $hook_extra    Extra arguments passed to hooked filters.
     * @return string|WP_Error
     */
    public static function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        global $wp_filesystem;

        // Ensure WordPress filesystem is available
        if ( ! $wp_filesystem ) {
            error_log( 'Custom Invoices Updater - WordPress filesystem not available during update' );
            return $source;
        }

        // Only process our plugin updates
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ) {
            return $source;
        }

        // Get the expected directory name
        $plugin_folder = dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) );

        // Validate plugin folder name
        if ( empty( $plugin_folder ) || $plugin_folder === '.' ) {
            return $source;
        }

        // Check if source already has correct name
        if ( basename( $source ) === $plugin_folder ) {
            return $source;
        }

        // Rename the directory
        $new_source = trailingslashit( $remote_source ) . $plugin_folder;
        if ( $wp_filesystem->move( $source, $new_source ) ) {
            return $new_source;
        }

        return new WP_Error( 'rename_failed', __( 'Could not rename plugin directory.' ) );
    }
}