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
        // Menu utama
        add_menu_page(
            __( 'Absensi Sekolah', 'absensi-sekolah' ),
            __( 'Absensi', 'absensi-sekolah' ),
            'read',
            'absensi-dashboard',
            [ $this, 'page_dashboard' ],
            'dashicons-id-alt',
            30
        );

        // Sub-menu
        $submenus = [
            [ 'absensi-dashboard',  __( 'Dashboard',    'absensi-sekolah' ), 'read',            [ $this, 'page_dashboard'  ] ],
            [ 'absensi-siswa',      __( 'Siswa',        'absensi-sekolah' ), 'manage_options',  [ $this, 'page_siswa'      ] ],
            [ 'absensi-kelas',      __( 'Kelas',        'absensi-sekolah' ), 'manage_options',  [ $this, 'page_kelas'      ] ],
            [ 'absensi-rfid',       __( 'Absen RFID',   'absensi-sekolah' ), 'edit_posts',      [ $this, 'page_rfid'       ] ],
            [ 'absensi-laporan',    __( 'Laporan',      'absensi-sekolah' ), 'read',            [ $this, 'page_laporan'    ] ],
            [ 'absensi-settings',   __( 'Pengaturan',   'absensi-sekolah' ), 'manage_options',  [ $this, 'page_settings'   ] ],
        ];

        foreach ( $submenus as [ $slug, $title, $cap, $cb ] ) {
            add_submenu_page( 'absensi-dashboard', $title, $title, $cap, $slug, $cb );
        }
    }

    // ─── Callback Halaman ─────────────────────────────────────────────────────

    public function page_dashboard(): void  { $this->render( 'dashboard' ); }
    public function page_siswa(): void      { $this->render( 'siswa' ); }
    public function page_kelas(): void      { $this->render( 'kelas' ); }
    public function page_rfid(): void       { $this->render( 'rfid' ); }
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
