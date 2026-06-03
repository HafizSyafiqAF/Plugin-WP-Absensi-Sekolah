<?php
namespace Absensi;

defined( 'ABSPATH' ) || exit;

/**
 * Kelas utama plugin – singleton bootstrap.
 * Tugasnya: load semua sub-modul dan daftarkan hooks global.
 */
final class Plugin {

    private static ?Plugin $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Dipanggil saat plugins_loaded. */
    public function boot(): void {
        // Custom Post Types & Tabel DB
        ( new class\PostTypes() )->register();

        // WordPress REST API endpoints
        add_action( 'rest_api_init', [ new api\AbsensiEndpoint(),  'register_routes' ] );
        add_action( 'rest_api_init', [ new api\SiswaEndpoint(),    'register_routes' ] );
        add_action( 'rest_api_init', [ new api\LaporanEndpoint(),  'register_routes' ] );

        // Admin dashboard
        if ( is_admin() ) {
            ( new Admin\Menu() )->register();
        }

        // Shortcode & aset frontend
        ( new class\Shortcodes() )->register();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function enqueue_public_assets(): void {
        wp_enqueue_style(
            'absensi-public',
            ABSENSI_PLUGIN_URL . 'public/css/public.css',
            [],
            ABSENSI_VERSION
        );
        wp_enqueue_script(
            'absensi-public',
            ABSENSI_PLUGIN_URL . 'public/js/public.js',
            [],
            ABSENSI_VERSION,
            true
        );
        wp_localize_script( 'absensi-public', 'AbsensiConfig', [
            'restUrl' => esc_url_raw( rest_url( 'absensi/v1/' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    public function enqueue_admin_assets( string $hook ): void {
        // Hanya load di halaman plugin sendiri
        if ( ! str_contains( $hook, 'absensi' ) ) {
            return;
        }
        wp_enqueue_style(
            'absensi-admin',
            ABSENSI_PLUGIN_URL . 'admin/css/admin.css',
            [],
            ABSENSI_VERSION
        );
        wp_enqueue_script(
            'absensi-admin',
            ABSENSI_PLUGIN_URL . 'admin/js/admin.js',
            [ 'jquery' ],
            ABSENSI_VERSION,
            true
        );
        wp_localize_script( 'absensi-admin', 'AbsensiAdmin', [
            'restUrl' => esc_url_raw( rest_url( 'absensi/v1/' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }
}
