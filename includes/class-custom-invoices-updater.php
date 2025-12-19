<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Custom_Invoices_Updater {

    // Latest published release (NOT tags)
    const GITHUB_API_URL   = 'https://api.github.com/repos/maratonac80/custom-invoices/releases/latest';
    const FALLBACK_VERSION = '1.0.9';

    /**
     * Normalize version string by removing 'v' or 'V' prefix.
     */
    private static function normalize_version( $version ) {
        if ( empty( $version ) ) {
            return '';
        }
        return ltrim( (string) $version, 'vV' );
    }

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_directory_name' ), 10, 4 );
    }

    /**
     * Fetch latest GitHub release info and return:
     * - tag_raw: e.g. "v1.1.1" or "1.1.1"
     * - version: normalized e.g. "1.1.1"
     * - asset_url: browser_download_url for "custom-invoices.zip" if exists
     */
    private static function get_latest_release_from_github() {
        $args = array(
            'timeout' => 15,
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

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
            error_log( 'Custom Invoices Updater - GitHub API JSON decode failed: ' . json_last_error_msg() );
            return false;
        }

        if ( empty( $data['tag_name'] ) ) {
            error_log( 'Custom Invoices Updater - Missing tag_name in GitHub API response.' );
            return false;
        }

        $tag_raw  = (string) $data['tag_name'];
        $version  = self::normalize_version( $tag_raw );
        $asset_url = '';

        // Find release asset named exactly "custom-invoices.zip"
        if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
            foreach ( $data['assets'] as $asset ) {
                if (
                    ! empty( $asset['name'] )
                    && $asset['name'] === 'custom-invoices.zip'
                    && ! empty( $asset['browser_download_url'] )
                ) {
                    $asset_url = (string) $asset['browser_download_url'];
                    break;
                }
            }
        }

        return array(
            'tag_raw'   => $tag_raw,
            'version'   => $version,
            'asset_url' => $asset_url,
        );
    }

    public static function check_for_updates( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data( CUSTOM_INVOICES_PLUGIN_FILE );

        $current_version_raw = isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '';
        $current_version     = self::normalize_version( $current_version_raw );

        $release = self::get_latest_release_from_github();
        if ( ! $release || empty( $release['version'] ) ) {
            return $transient;
        }

        $latest_version = $release['version']; // numeric
        $tag_raw        = $release['tag_raw']; // may include v
        $repo_url       = 'https://github.com/maratonac80/custom-invoices';

        // Prefer release asset; fallback to codeload (more reliable than github.com archive redirects)
        $package = ! empty( $release['asset_url'] )
            ? $release['asset_url']
            : 'https://codeload.github.com/maratonac80/custom-invoices/zip/refs/tags/' . rawurlencode( $tag_raw );

        if ( $current_version && version_compare( $current_version, $latest_version, '<' ) ) {
            $transient->response[ plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ] = (object) array(
                'slug'        => dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ), // matches installed folder
                'plugin'      => plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ),
                'new_version' => $latest_version,
                'url'         => $repo_url,
                'package'     => $package,
            );
        }

        return $transient;
    }

    public static function plugin_info( $res, $action, $args ) {
        $installed_slug = dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) );

        // Don't interfere with other plugin info calls
        if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== $installed_slug ) {
            return $res;
        }

        $repo_url = 'https://github.com/maratonac80/custom-invoices';

        $release = self::get_latest_release_from_github();
        $latest_version = ( $release && ! empty( $release['version'] ) ) ? $release['version'] : self::FALLBACK_VERSION;

        $tag_raw  = ( $release && ! empty( $release['tag_raw'] ) ) ? $release['tag_raw'] : $latest_version;
        $package  = ( $release && ! empty( $release['asset_url'] ) )
            ? $release['asset_url']
            : 'https://codeload.github.com/maratonac80/custom-invoices/zip/refs/tags/' . rawurlencode( $tag_raw );

        return (object) array(
            'name'          => 'Custom Invoices Plugin',
            'slug'          => $installed_slug,
            'version'       => $latest_version,
            'author'        => 'Zoran Filipović <https://peroneus.hr>',
            'description'   => 'Plugin for custom invoice handling.',
            'homepage'      => $repo_url,
            'sections'      => array(
                'description' => 'Easily manage invoices and update the plugin from WordPress admin.',
            ),
            'download_link' => $package,
        );
    }

    /**
     * Fix the directory name when updating from GitHub archives/assets.
     * GitHub archives often unpack to "custom-invoices-1.1.0" (or similar),
     * but WP expects the installed folder name (e.g. "custom-invoice" or "custom-invoices").
     */
    public static function fix_directory_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
        global $wp_filesystem;

        // Only process our plugin updates
        if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ) {
            return $source;
        }

        // Ensure filesystem is available
        if ( ! $wp_filesystem ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if ( ! $wp_filesystem ) {
            error_log( 'Custom Invoices Updater - WP_Filesystem init failed' );
            return $source;
        }

        // Installed folder name (source of truth)
        $plugin_folder = dirname( plugin_basename( CUSTOM_INVOICES_PLUGIN_FILE ) ); // e.g. "custom-invoice" or "custom-invoices"
        if ( empty( $plugin_folder ) || $plugin_folder === '.' ) {
            return $source;
        }

        // Already correct
        if ( basename( $source ) === $plugin_folder ) {
            return $source;
        }

        // Move into the parent of $source (most reliable)
        $parent     = trailingslashit( dirname( $source ) );
        $new_source = $parent . $plugin_folder;

        // If target exists, remove it (old unpack leftovers)
        if ( $wp_filesystem->exists( $new_source ) ) {
            $wp_filesystem->delete( $new_source, true );
        }

        if ( $wp_filesystem->move( $source, $new_source, true ) ) {
            return $new_source;
        }

        return new WP_Error( 'rename_failed', __( 'Could not rename plugin directory.' ) );
    }
}
