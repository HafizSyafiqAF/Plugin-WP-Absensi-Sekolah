<?php
namespace Absensi\class;

defined( 'ABSPATH' ) || exit;

/**
 * Daftarkan shortcode untuk halaman frontend siswa.
 *
 * Penggunaan di halaman WordPress:
 *   [absensi_selfie]   → Halaman absen selfie + GPS untuk siswa
 *   [absensi_status]   → Widget status absen hari ini
 */
class Shortcodes {

    public function register(): void {
        add_shortcode( 'absensi_selfie',  [ $this, 'render_selfie'  ] );
        add_shortcode( 'absensi_status',  [ $this, 'render_status'  ] );
    }

    public function render_selfie( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return '<p>' . wp_kses_post( sprintf(
                __( 'Silakan <a href="%s">login</a> untuk menggunakan fitur absensi.', 'absensi-sekolah' ),
                esc_url( wp_login_url( get_permalink() ) )
            ) ) . '</p>';
        }
        ob_start();
        include ABSENSI_PLUGIN_DIR . 'public/views/selfie.php';
        return ob_get_clean();
    }

    public function render_status( array $atts ): string {
        if ( ! is_user_logged_in() ) {
            return '';
        }
        ob_start();
        include ABSENSI_PLUGIN_DIR . 'public/views/status.php';
        return ob_get_clean();
    }
}
