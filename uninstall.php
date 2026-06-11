<?php
/**
 * Uninstall Absensi Sekolah.
 *
 * Dijalankan WordPress saat plugin DIHAPUS dari menu Plugins (bukan deactivate).
 * Berjalan dalam konteks minimal: file utama plugin TIDAK dimuat, jadi autoloader
 * & class Absensi\Installer tidak tersedia. Karena itu daftar tabel/role/cap
 * di-hardcode di sini — JAGA TETAP SINKRON dengan includes/Installer.php.
 *
 * Aturan pembersihan:
 *  - SELALU: cabut capability dari administrator, hapus role custom, hapus
 *    semua option `absensi_*` (config — aman dibuang, dipulihkan saat re-install).
 *  - HANYA jika opsi `absensi_uninstall_remove_data` aktif (default: tidak):
 *    DROP tabel `absensi_*` + hapus folder selfie. Ini DATA pengguna → default
 *    aman = dipertahankan agar uninstall tak sengaja menghapus data.
 *
 * @package Absensi
 */

// Hanya boleh dipanggil oleh proses uninstall WordPress.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Hapus direktori beserta isinya secara rekursif.
 * Dibatasi pada folder selfie di dalam uploads (path dirakit dari wp_upload_dir).
 */
function absensi_uninstall_rmdir( string $dir ): void {
    if ( ! is_dir( $dir ) ) {
        return;
    }
    foreach ( (array) scandir( $dir ) as $item ) {
        if ( '.' === $item || '..' === $item ) {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if ( is_dir( $path ) ) {
            absensi_uninstall_rmdir( $path );
        } else {
            @unlink( $path );
        }
    }
    @rmdir( $dir );
}

/**
 * Bersihkan satu site (single-site atau satu blog dalam multisite).
 */
function absensi_uninstall_cleanup(): void {
    global $wpdb;

    // ── Sinkron dengan Installer::CAPS & Installer::role_definitions() ──
    $caps = [
        'absensi_submit_self',
        'absensi_submit_rfid',
        'absensi_enroll_rfid',
        'absensi_view_reports',
        'absensi_view_child',
    ];
    $roles = [ 'absensi_admin', 'guru', 'absensi_siswa', 'orang_tua' ];

    // ── 0. Bersihkan WP-Cron retensi (selalu) ──
    wp_clear_scheduled_hook( 'absensi_purge_selfie' );

    // ── 1. Role & capability (selalu) ──
    $admin = get_role( 'administrator' );
    if ( $admin ) {
        foreach ( $caps as $cap ) {
            $admin->remove_cap( $cap );
        }
    }
    foreach ( $roles as $slug ) {
        if ( get_role( $slug ) ) {
            remove_role( $slug );
        }
    }

    // ── 2. Baca guard "hapus data" SEBELUM option dibuang ──
    $remove_data = (bool) get_option( 'absensi_uninstall_remove_data', false );

    // ── 3. Data pengguna (tabel + selfie) hanya jika opt-in ──
    if ( $remove_data ) {
        // Urutan tak penting (tanpa FK), tapi child dulu agar rapi.
        $tables = [ 'absensi_rekap', 'absensi_jadwal', 'absensi_siswa', 'absensi_kelas' ];
        foreach ( $tables as $suffix ) {
            // Nama tabel tak bisa di-prepare; dirakit dari prefix + literal (tanpa input user).
            $table = $wpdb->prefix . $suffix;
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
        }

        // Hapus folder selfie di uploads.
        $upload = wp_upload_dir();
        if ( ! empty( $upload['basedir'] ) ) {
            absensi_uninstall_rmdir( trailingslashit( $upload['basedir'] ) . 'absensi-selfie' );
        }
    }

    // ── 4. Options absensi_* (selalu, termasuk absensi_db_version & guard) ──
    $like  = $wpdb->esc_like( 'absensi_' ) . '%';
    $names = $wpdb->get_col(
        $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
    );
    foreach ( (array) $names as $name ) {
        delete_option( $name );
    }
}

// ── Jalankan per-site (multisite) atau sekali (single-site) ──
if ( is_multisite() ) {
    $site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
    foreach ( $site_ids as $site_id ) {
        switch_to_blog( (int) $site_id );
        absensi_uninstall_cleanup();
        restore_current_blog();
    }
} else {
    absensi_uninstall_cleanup();
}
