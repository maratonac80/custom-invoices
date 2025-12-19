<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Updater {

    // GitHub: najnoviji release (tag npr. "1.1.0")
    const GITHUB_API_URL    = 'https://api.github.com/repos/maratonac80/custom-invoices/releases/latest';
    const FALLBACK_VERSION  = '1.0.9';

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
                'Accept'     => 'application/vnd.github+json',
            ),
        );

        $response = wp_remote_get( self::GITHUB_API_URL, $args );

        if ( is_wp_error( $response ) ) {
            error_log( 'Custom Invoices Updater - GitHub API request failed: ' . $response->get_error_message() );
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            error_log( 'Custom Invoices Updater - GitHub API HTTP error: ' . $code );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $release_data = json_decode( $body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            error_log( 'Custom Invoices Updater - Failed to decode GitHub API response: ' . json_last_error_msg() );
            return false;
        }

        if ( ! is_array( $release_data ) || empty( $release_data['tag_name'] ) ) {
            error_log( 'Custom Invoices Updater - Missing tag_name in GitHub API response.' );
            return false;
        }

        // Očekujemo numeric tag "1.1.0"
        return (string) $release_data['tag_name'];
    }

    public static function check_for_updates( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        $plugin_slug = 'custom-invoices';
        $repo_url    = 'https://github.com/maratonac80/custom-invoices';

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( CUSTOM_INVOICES_PLUGIN_FILE );

        // Trenutna verzija iz headera (npr. "1.0.9")
        $current_version = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '';

        $latest_version = self::get_latest_version_from_github();
        if ( ! $latest_version ) {
            return $transient;
        }

        if ( $current_version && version_compare( $current_version, $latest_version, '<' ) ) {
            $transient->response[ plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ] = (object) array(
                'slug'        => $plugin_slug,
                'plugin'      => plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ),
                'new_version' => $latest_version, // numeric
                'url'         => $repo_url,
                'package'     => $repo_url . '/archive/refs/tags/' . $latest_version . '.zip',
            );
        }

        return $transient;
    }

    public static function plugin_info( $res, $action, $args ) {
        // Ne smetaj drugim pluginima
        if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== 'custom-invoices' ) {
            return $res;
        }

        $repo_url = 'https://github.com/maratonac80/custom-invoices';

        $latest_version = self::get_latest_version_from_github();
        if ( ! $latest_version ) {
            $latest_version = self::FALLBACK_VERSION;
        }

        return (object) array(
            'name'          => 'Custom Invoices Plugin',
            'slug'          => 'custom-invoices',
            'version'       => $latest_version,
            'author'        => 'Zoran Filipović <https://peroneus.hr>',
            'description'   => 'Plugin for custom invoice handling.',
            'homepage'      => $repo_url,
            'sections'      => array(
                'description' => 'Easily manage invoices and update the plugin from WordPress admin.',
            ),
            'download_link' => $repo_url . '/archive/refs/tags/' . $latest_version . '.zip',
        );
    }

    /**
     * Fix directory name when updating from GitHub archive.
     * GitHub archives have directory names like 'custom-invoices-1.1.0'
     * but WordPress expects 'custom-invoices'.
     */
    public static function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        global $wp_filesystem;

        if ( ! $wp_filesystem ) {
            error_log( 'Custom Invoices Updater - WordPress filesystem not available during update' );
            return $source;
        }

        // Only process our plugin updates
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ) {
            return $source;
        }

        $plugin_folder = dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) );
        if ( empty( $plugin_folder ) || $plugin_folder === '.' ) {
            return $source;
        }

        // Already correct
        if ( basename( $source ) === $plugin_folder ) {
            return $source;
        }

        $new_source = trailingslashit( $remote_source ) . $plugin_folder;

        if ( $wp_filesystem->move( $source, $new_source ) ) {
            return $new_source;
        }

        return new WP_Error( 'rename_failed', __( 'Could not rename plugin directory.' ) );
    }
}
