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

        // Notifikasi WA dicabut (pivot: model wali/akun dibuang). Titik colok bila
        // dihidupkan lagi: action absensi_absen_masuk/keluar tetap di-fire endpoint;
        // penerima nanti = kolom no_wa di absensi_users (bukan user-meta akun wali).

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

        // Sembunyikan admin bar WP (garis hitam atas) untuk role guru — kiosk bersih.
        add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar_for_guru' ] );

        // Role `guru` tak boleh masuk wp-admin — lempar ke kiosk. (admin_init hanya di konteks admin.)
        // Dua hook: admin_init menangkap page ber-cap `read` (mis. dashboard/profil); menu.php
        // wp_die lebih dulu untuk page ber-cap tinggi (mis. Settings) → tangkap juga di
        // admin_page_access_denied (fired sebelum wp_die) agar guru tetap di-redirect, bukan lihat error.
        add_action( 'admin_init', [ $this, 'block_admin_for_guru' ] );
        add_action( 'admin_page_access_denied', [ $this, 'block_admin_for_guru' ] );

        // Gerbang login page kiosk RFID guru (/absensi/guru) — bukan publik.
        add_action( 'template_redirect', [ $this, 'gate_kiosk_guru' ] );

        // Sembunyikan page guru dari daftar page publik (nav/menu) utk user tanpa cap.
        add_filter( 'get_pages', [ $this, 'hide_guru_page_public' ], 10, 2 );

        // Shortcode & aset frontend
        ( new class\Shortcodes() )->register();
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_public_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        // Alpine.js (CDN) wajib atribut `defer` — sisipkan ke tag <script>-nya.
        add_filter( 'script_loader_tag', [ $this, 'defer_alpine_tag' ], 10, 2 );
    }

    /**
     * Gerbang akses page kiosk RFID guru (`/absensi/guru`).
     *
     * Page ini TIDAK publik: hanya user ber-cap `absensi_rfid` (role `guru` / administrator).
     * - Belum login → `auth_redirect()`: WP arahkan ke wp-login, balik otomatis via `redirect_to`.
     * - Login tapi tak berhak (mis. subscriber) → 403.
     * Identitas absen tetap dari `rfid_uid` siswa; login = gerbang akses device saja.
     *
     * Id page dari option `absensi_pages['guru']` (di-set Installer::seed_pages).
     */
    public function gate_kiosk_guru(): void {
        $pages   = (array) get_option( 'absensi_pages', [] );
        $guru_id = isset( $pages['guru'] ) ? (int) $pages['guru'] : 0;
        if ( ! $guru_id || ! is_page( $guru_id ) ) {
            return; // bukan page kiosk guru
        }
        if ( current_user_can( Installer::CAP_RFID ) ) {
            return; // berhak → lanjut render
        }
        if ( ! is_user_logged_in() ) {
            auth_redirect(); // exit: redirect ke wp-login.php?redirect_to=<url ini>
        }
        // Login tapi tak punya cap.
        wp_die(
            esc_html__( 'Anda tidak memiliki akses ke halaman absensi guru.', 'absensi-sekolah' ),
            esc_html__( 'Akses ditolak', 'absensi-sekolah' ),
            [ 'response' => 403 ]
        );
    }

    /**
     * Blokir akses wp-admin untuk role `guru`.
     *
     * Guru = operator kiosk RFID, tak butuh dashboard. Buka `/wp-admin` → dilempar ke page kiosk
     * guru (`/absensi/guru`). Dikecualikan: administrator (punya `manage_options`), request AJAX/cron.
     * Target redirect page kiosk (bukan wp-admin) → tak ada loop.
     */
    public function block_admin_for_guru(): void {
        if ( wp_doing_ajax() || wp_doing_cron() ) {
            return; // biarkan admin-ajax/cron jalan
        }
        if ( ! $this->is_guru_only_user() ) {
            return; // admin / anon / role lain → tak diblok di sini
        }
        $pages   = (array) get_option( 'absensi_pages', [] );
        $guru_id = isset( $pages['guru'] ) ? (int) $pages['guru'] : 0;
        $target  = $guru_id ? get_permalink( $guru_id ) : '';
        wp_safe_redirect( $target ?: home_url( '/' ) );
        exit;
    }

    /**
     * Sembunyikan admin bar WP (garis hitam atas) untuk role `guru` di frontend/kiosk.
     * Administrator tetap punya admin bar. Filter `show_admin_bar`.
     */
    public function hide_admin_bar_for_guru( $show ) {
        return $this->is_guru_only_user() ? false : $show;
    }

    /**
     * True bila user login = role `guru` (punya cap gate kiosk) TANPA hak admin.
     * Dipakai untuk memisahkan guru dari administrator (yang bebas akses wp-admin).
     */
    private function is_guru_only_user(): bool {
        return is_user_logged_in()
            && current_user_can( Installer::CAP_RFID )
            && ! current_user_can( 'manage_options' );
    }

    /**
     * Sembunyikan page kiosk guru dari daftar page publik (nav/menu via `get_pages`/`wp_list_pages`).
     *
     * Hanya di frontend & hanya untuk user TANPA cap `absensi_rfid` (publik). User berhak (guru/admin)
     * tetap lihat. Admin context (dropdown Pages, editor) TIDAK difilter (guard `is_admin()`).
     *
     * ⚠️ Filter ini berlaku untuk `get_pages()`/`wp_list_pages()`/`wp_page_menu()`. Block theme
     * (`core/page-list`/`core/navigation`) mungkin tak lewat `get_pages` → butuh penyesuaian FE
     * (lihat TODO-FE item 3).
     *
     * @param array $pages Daftar WP_Post.
     * @param array $args  Argumen get_pages (tak dipakai).
     * @return array
     */
    public function hide_guru_page_public( $pages, $args = [] ) {
        if ( is_admin() || current_user_can( Installer::CAP_RFID ) ) {
            return $pages; // admin-area atau user berhak → jangan sembunyikan
        }
        $guru_id = (int) ( ( (array) get_option( 'absensi_pages', [] ) )['guru'] ?? 0 );
        if ( ! $guru_id || empty( $pages ) ) {
            return $pages;
        }
        $filtered = array_filter(
            (array) $pages,
            static function ( $p ) use ( $guru_id ) {
                $id = is_object( $p ) ? (int) ( $p->ID ?? 0 ) : (int) ( $p['ID'] ?? 0 );
                return $id !== $guru_id;
            }
        );
        return array_values( $filtered );
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
