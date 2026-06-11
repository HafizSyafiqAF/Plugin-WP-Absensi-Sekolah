<?php
namespace Absensi\class;

defined( 'ABSPATH' ) || exit;

/**
 * Daftarkan shortcode untuk halaman frontend siswa.
 *
 * Penggunaan di halaman WordPress:
 *   [absensi_selfie]   → Halaman absen selfie + GPS untuk siswa
 *   [absensi_status]   → Widget status absen hari ini
 *   [absensi_siswa]    → Surface siswa (absen mandiri)        — view: public/views/siswa.php
 *   [absensi_guru]     → Surface guru (scan RFID)             — view: public/views/guru.php
 *   [absensi_ortu]     → Surface orang tua (riwayat anak)     — view: public/views/ortu.php
 *
 * Catatan boundary: shortcode di sini hanya WIRING (register + gate auth/cap +
 * include view). Markup ada di file view (public/views/*.php) = tanggung jawab FE.
 * Bila view belum ada → tampilkan notice placeholder, bukan error.
 */
class Shortcodes {

    public function register(): void {
        add_shortcode( 'absensi_selfie',  [ $this, 'render_selfie'  ] );
        add_shortcode( 'absensi_status',  [ $this, 'render_status'  ] );
        // Surface FE (Alpine + Tailwind CDN). View markup disediakan FE.
        add_shortcode( 'absensi_siswa', [ $this, 'render_siswa' ] );
        add_shortcode( 'absensi_guru',  [ $this, 'render_guru'  ] );
        add_shortcode( 'absensi_ortu',  [ $this, 'render_ortu'  ] );
    }

    public function render_siswa( array $atts ): string {
        return $this->render_surface( 'siswa.php', null ); // cukup login (siswa)
    }

    public function render_guru( array $atts ): string {
        return $this->render_surface( 'guru.php', 'absensi_submit_rfid' );
    }

    public function render_ortu( array $atts ): string {
        return $this->render_surface( 'ortu.php', 'absensi_view_child' );
    }

    /**
     * Render satu surface FE: gate login (+ cap opsional), lalu include view.
     * @param string      $view Nama file di public/views/.
     * @param string|null $cap  Capability wajib, atau null = cukup login.
     */
    private function render_surface( string $view, ?string $cap ): string {
        if ( ! is_user_logged_in() ) {
            return '<p>' . wp_kses_post( sprintf(
                __( 'Silakan <a href="%s">login</a> untuk menggunakan fitur absensi.', 'absensi-sekolah' ),
                esc_url( wp_login_url( get_permalink() ) )
            ) ) . '</p>';
        }
        if ( $cap && ! current_user_can( $cap ) ) {
            return '<p>' . esc_html__( 'Anda tidak memiliki akses ke halaman ini.', 'absensi-sekolah' ) . '</p>';
        }
        $file = ABSENSI_PLUGIN_DIR . 'public/views/' . $view;
        if ( ! file_exists( $file ) ) {
            // View belum disediakan FE — placeholder, bukan error fatal.
            return '<p>' . esc_html( sprintf(
                /* translators: %s: nama file view */
                __( 'Tampilan "%s" belum tersedia.', 'absensi-sekolah' ),
                $view
            ) ) . '</p>';
        }
        ob_start();
        include $file;
        return ob_get_clean();
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
