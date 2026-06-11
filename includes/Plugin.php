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
        // Migration runner: sinkronkan skema DB bila versi naik (tanpa perlu re-activate).
        Installer::maybe_upgrade();

        // Retensi foto: handler cron + jadwal harian (purge selfie lawas).
        Retensi::init();

        // Notifikasi WA ke wali setelah anak absen (hook absensi_absen_masuk/keluar).
        Notifikasi::init();

        // Custom Post Types & Tabel DB
        ( new class\PostTypes() )->register();

        // WordPress REST API endpoints
        add_action( 'rest_api_init', [ new api\AbsensiEndpoint(),  'register_routes' ] );
        add_action( 'rest_api_init', [ new api\SiswaEndpoint(),    'register_routes' ] );
        add_action( 'rest_api_init', [ new api\KelasEndpoint(),    'register_routes' ] );
        add_action( 'rest_api_init', [ new api\JadwalEndpoint(),   'register_routes' ] );
        add_action( 'rest_api_init', [ new api\WaliEndpoint(),     'register_routes' ] );
        add_action( 'rest_api_init', [ new api\ChildEndpoint(),    'register_routes' ] );
        add_action( 'rest_api_init', [ new api\LaporanEndpoint(),  'register_routes' ] );
        add_action( 'rest_api_init', [ new api\SettingsEndpoint(), 'register_routes' ] );

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
            'restUrl'      => esc_url_raw( rest_url( 'absensi/v1/' ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'rfidDebounce' => (int) get_option( 'absensi_rfid_debounce', 3 ),
            'akurasiMax'   => (int) get_option( 'absensi_akurasi_max', 100 ),
            'anakList'     => $this->anak_list_current_user(),
        ] );
    }

    /**
     * Daftar anak ter-link untuk user login (role orang_tua) → dipakai FE view ortu.
     * Kosong untuk non-ortu / belum login. Diturunkan dari absensi_wali (server).
     */
    private function anak_list_current_user(): array {
        $uid = get_current_user_id();
        if ( ! $uid ) {
            return [];
        }
        global $wpdb;
        return (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT s.id AS siswa_id, s.nama, s.nis, s.kelas_id, k.nama_kelas
               FROM {$wpdb->prefix}absensi_wali w
               JOIN {$wpdb->prefix}absensi_siswa s ON s.id = w.siswa_id
               LEFT JOIN {$wpdb->prefix}absensi_kelas k ON k.id = s.kelas_id
              WHERE w.wali_user_id = %d
              ORDER BY s.nama ASC",
            $uid
        ) );
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
            'restUrl'      => esc_url_raw( rest_url( 'absensi/v1/' ) ),
            'nonce'        => wp_create_nonce( 'wp_rest' ),
            'rfidDebounce' => (int) get_option( 'absensi_rfid_debounce', 3 ),
            // Pengaturan untuk prefill form admin (token WA TIDAK di-inject — sensitif)
            'settings'     => [
                'lat'          => (float) get_option( 'absensi_lat', 0 ),
                'lng'          => (float) get_option( 'absensi_lng', 0 ),
                'radius'       => (int) get_option( 'absensi_radius', 100 ),
                'jamMasuk'     => (string) get_option( 'absensi_jam_masuk', '07:00' ),
                'jamKeluar'    => (string) get_option( 'absensi_jam_keluar', '15:00' ),
                'telatMenit'   => (int) get_option( 'absensi_telat_menit', 15 ),
                'akurasiMax'   => (int) get_option( 'absensi_akurasi_max', 100 ),
                'rfidDebounce' => (int) get_option( 'absensi_rfid_debounce', 3 ),
                'retensiHari'  => (int) get_option( 'absensi_retensi_hari', 90 ),
                'waGateway'    => (string) get_option( 'absensi_wa_gateway', '' ),
            ],
        ] );
    }
}
