<?php
/**
 * Plugin Name:       Absensi Sekolah
 * Plugin URI:        https://github.com/your-repo/absensi-sekolah
 * Description:       Sistem absensi sekolah dengan selfie + GPS + RFID USB scanner untuk WordPress.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            P4 Magang
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       absensi-sekolah
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// ─── Konstanta Plugin ────────────────────────────────────────────────────────
define( 'ABSENSI_VERSION',     '1.0.0' );
define( 'ABSENSI_PLUGIN_FILE', __FILE__ );
define( 'ABSENSI_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ABSENSI_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'ABSENSI_PLUGIN_BASE', plugin_basename( __FILE__ ) );

// ─── Autoloader Sederhana ────────────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {
    $prefix = 'Absensi\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
    $file = ABSENSI_PLUGIN_DIR . 'includes/' . $relative . '.php';
    if ( file_exists( $file ) ) {
        require_once $file;
    }
} );

// ─── Aktivasi / Deaktivasi / Uninstall ──────────────────────────────────────
register_activation_hook(   __FILE__, [ 'Absensi\\Installer', 'activate'   ] );
register_deactivation_hook( __FILE__, [ 'Absensi\\Installer', 'deactivate' ] );

// ─── Boot Plugin ────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', function (): void {
    load_plugin_textdomain( 'absensi-sekolah', false, ABSENSI_PLUGIN_DIR . 'languages' );
    \Absensi\Plugin::get_instance()->boot();
} );
