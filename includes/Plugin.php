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

        // Alpine.js (CDN) wajib atribut `defer` — sisipkan ke tag <script>-nya.
        add_filter( 'script_loader_tag', [ $this, 'defer_alpine_tag' ], 10, 2 );

        // Sembunyikan link page surface (guru/ortu) dari navigasi bila user tak punya
        // cap — siswa cuma lihat "Absensi Siswa". Gate isi tetap di shortcode.
        // - wp_list_pages_excludes / wp_nav_menu_objects: tema klasik (wp_page_menu / menu custom).
        // - get_pages: tema blok (FSE) — Navigation block render lewat core/page-list yang
        //   ambil data via get_pages() (mis. Twenty Twenty-Five). Tanpa ini, nav blok bocor.
        add_filter( 'wp_list_pages_excludes', [ $this, 'filter_list_pages_excludes' ] );
        add_filter( 'wp_nav_menu_objects', [ $this, 'filter_nav_menu_objects' ], 10, 2 );
        add_filter( 'get_pages', [ $this, 'filter_get_pages' ], 10, 2 );
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

        // Stack FE: Alpine + Tailwind via CDN. Alpine load setelah config (dep handle).
        $this->enqueue_frontend_cdn( 'absensi-public', false );
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

    /**
     * Map page surface (dari option absensi_pages) → cap wajib.
     * null = cukup login (siswa). Hanya page yang ID-nya ada yang dimasukkan.
     *
     * @return array<int,?string> [ page_id => cap|null ]
     */
    private function surface_page_caps(): array {
        $pages = (array) get_option( 'absensi_pages', [] );
        // Strict per-role: tiap surface butuh cap khusus → user cuma lihat link
        // yang sesuai rolenya. siswa=submit_self, guru=submit_rfid, ortu=view_child.
        // Admin punya semua cap → lihat ketiganya. Guest tak punya cap → tak lihat apa pun.
        $map   = [
            'siswa' => 'absensi_submit_self',
            'guru'  => 'absensi_submit_rfid',
            'ortu'  => 'absensi_view_child',
        ];
        $out = [];
        foreach ( $map as $key => $cap ) {
            if ( ! empty( $pages[ $key ] ) ) {
                $out[ (int) $pages[ $key ] ] = $cap;
            }
        }
        return $out;
    }

    /**
     * ID page surface yang harus DISEMBUNYIKAN dari navigasi untuk user saat ini.
     * Page ber-cap yang tak dimiliki user → sembunyi. Page siswa (cap null) selalu
     * tampil (login flow tetap bisa diakses). Gate isi tetap di shortcode.
     *
     * @return int[]
     */
    private function hidden_surface_page_ids(): array {
        $hidden = [];
        foreach ( $this->surface_page_caps() as $id => $cap ) {
            if ( null !== $cap && ! current_user_can( $cap ) ) {
                $hidden[] = $id;
            }
        }
        return $hidden;
    }

    /** Exclude page surface tak-berhak dari wp_list_pages / wp_page_menu (auto menu tema). */
    public function filter_list_pages_excludes( array $exclude ): array {
        return array_merge( $exclude, $this->hidden_surface_page_ids() );
    }

    /** Buang item page surface tak-berhak dari menu navigasi custom (wp_nav_menu). */
    public function filter_nav_menu_objects( array $items, $args ): array {
        $hidden = $this->hidden_surface_page_ids();
        if ( ! $hidden ) {
            return $items;
        }
        return array_filter( $items, static function ( $it ) use ( $hidden ) {
            return ! ( isset( $it->object, $it->object_id )
                && 'page' === $it->object
                && in_array( (int) $it->object_id, $hidden, true ) );
        } );
    }

    /**
     * Buang page surface tak-berhak dari hasil get_pages().
     * Dipakai tema blok (FSE): core/page-list di dalam Navigation block ambil
     * daftar page via get_pages(), tak lewat wp_nav_menu/wp_list_pages.
     *
     * @param mixed $pages Array WP_Post hasil get_pages (bisa non-array di edge case).
     * @param array $args  Args get_pages (tak dipakai).
     * @return mixed
     */
    public function filter_get_pages( $pages, $args = [] ) {
        $hidden = $this->hidden_surface_page_ids();
        if ( ! $hidden || ! is_array( $pages ) ) {
            return $pages;
        }
        return array_values( array_filter( $pages, static function ( $p ) use ( $hidden ) {
            return ! ( isset( $p->ID ) && in_array( (int) $p->ID, $hidden, true ) );
        } ) );
    }
}
