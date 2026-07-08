<?php
namespace Absensi\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Daftarkan menu admin WordPress untuk plugin Absensi Sekolah.
 */
class Menu {

    public function register(): void {
        add_action( 'admin_menu', [ $this, 'add_menus' ] );
    }

    public function add_menus(): void {
        // Menu utama — admin-only (pivot: wp-admin cuma operator/administrator).
        add_menu_page(
            __( 'Absensi Sekolah', 'absensi-sekolah' ),
            __( 'Absensi', 'absensi-sekolah' ),
            'manage_options',
            'absensi-dashboard',
            [ $this, 'page_dashboard' ],
            'dashicons-id-alt',
            30
        );

        // Sub-menu — semua cap manage_options (tanpa role guru/siswa; admin-only).
        $submenus = [
            [ 'absensi-dashboard',  __( 'Dashboard',    'absensi-sekolah' ), 'manage_options',  [ $this, 'page_dashboard'  ] ],
            [ 'absensi-users',      __( 'Users',        'absensi-sekolah' ), 'manage_options',  [ $this, 'page_users'      ] ],
            [ 'absensi-group',      __( 'Group',        'absensi-sekolah' ), 'manage_options',  [ $this, 'page_group'      ] ],
            [ 'absensi-laporan',    __( 'Laporan',      'absensi-sekolah' ), 'manage_options',  [ $this, 'page_laporan'    ] ],
            [ 'absensi-settings',   __( 'Pengaturan',   'absensi-sekolah' ), 'manage_options',  [ $this, 'page_settings'   ] ],
        ];

        foreach ( $submenus as [ $slug, $title, $cap, $cb ] ) {
            add_submenu_page( 'absensi-dashboard', $title, $title, $cap, $slug, $cb );
        }
    }

    // ─── Callback Halaman ─────────────────────────────────────────────────────

    public function page_dashboard(): void  { $this->render( 'dashboard' ); }
    public function page_users(): void      { $this->render( 'users' ); }
    public function page_group(): void      { $this->render( 'group' ); }
    public function page_laporan(): void    { $this->render( 'laporan' ); }
    public function page_settings(): void   { $this->render( 'settings' ); }

    private function render( string $view ): void {
        $file = ABSENSI_PLUGIN_DIR . "admin/views/{$view}.php";
        if ( file_exists( $file ) ) {
            include $file;
        } else {
            echo '<div class="wrap"><h1>' . esc_html( $view ) . '</h1><p>View belum tersedia.</p></div>';
        }
    }
}
