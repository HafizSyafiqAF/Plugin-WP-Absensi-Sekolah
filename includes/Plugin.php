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
        add_action( 'rest_api_init', [ new api\UsersEndpoint(),    'register_routes' ] );
        add_action( 'rest_api_init', [ new api\GroupEndpoint(),    'register_routes' ] );
        add_action( 'rest_api_init', [ new api\JadwalEndpoint(),   'register_routes' ] );
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

        // Alpine.js (CDN) wajib atribut `defer` — sisipkan ke tag <script>-nya.
        add_filter( 'script_loader_tag', [ $this, 'defer_alpine_tag' ], 10, 2 );
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
        ] );

        // Stack FE: Alpine + Tailwind via CDN. Alpine load setelah config (dep handle).
        $this->enqueue_frontend_cdn( 'absensi-public', false );
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
            [], // stack Alpine (CDN) — FE konfirmasi tanpa jQuery
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

        // Stack FE admin: Alpine + Tailwind via CDN (Tailwind preflight dimatikan
        // agar tak merusak tampilan wp-admin). Alpine load setelah config.
        $this->enqueue_frontend_cdn( 'absensi-admin', true );
    }

    /**
     * Enqueue Alpine.js + Tailwind CSS dari CDN (stack FE, no build).
     *
     * - Alpine di-depend ke $after_handle (handle yang sudah di-localize) supaya
     *   AbsensiConfig/AbsensiAdmin tersedia SEBELUM Alpine init. `defer` ditambah
     *   via filter script_loader_tag (Alpine mewajibkannya).
     * - Tailwind Play CDN dimuat di <head>. Di admin, preflight dimatikan agar
     *   reset CSS-nya tak merusak chrome wp-admin.
     * - Handle unik → WP otomatis dedup (1× per halaman walau banyak shortcode).
     * - Semua source bisa di-override FE/ops via filter.
     *
     * @param string $after_handle Handle script yang sudah di-enqueue+localize.
     * @param bool   $is_admin     true = konteks wp-admin.
     */
    private function enqueue_frontend_cdn( string $after_handle, bool $is_admin ): void {
        if ( ! apply_filters( 'absensi_enqueue_cdn', true, $is_admin ) ) {
            return; // ops bisa matikan (mis. self-host / produksi build sendiri)
        }

        // Tailwind Play CDN (head). Filterable; kosong = lewati.
        $tailwind = apply_filters( 'absensi_tailwind_src', 'https://cdn.tailwindcss.com', $is_admin );
        if ( $tailwind ) {
            wp_enqueue_script( 'absensi-tailwind', $tailwind, [], null, false );
            if ( $is_admin ) {
                // Matikan preflight agar reset Tailwind tak menimpa wp-admin.
                wp_add_inline_script(
                    'absensi-tailwind',
                    'window.tailwind=window.tailwind||{};window.tailwind.config={corePlugins:{preflight:false}};',
                    'after'
                );
            }
        }

        // Alpine.js v3 (footer, defer via filter), load SETELAH $after_handle.
        $alpine = apply_filters(
            'absensi_alpine_src',
            'https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js',
            $is_admin
        );
        if ( $alpine ) {
            wp_enqueue_script( 'absensi-alpine', $alpine, [ $after_handle ], null, true );
        }
    }

    /** Tambahkan atribut `defer` ke tag <script> Alpine (wajib untuk Alpine). */
    public function defer_alpine_tag( string $tag, string $handle ): string {
        if ( 'absensi-alpine' === $handle && ! str_contains( $tag, ' defer' ) ) {
            $tag = str_replace( ' src=', ' defer src=', $tag );
        }
        return $tag;
    }
}
