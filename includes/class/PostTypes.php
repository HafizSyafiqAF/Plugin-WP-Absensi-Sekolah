<?php
namespace Absensi\class;

defined( 'ABSPATH' ) || exit;

/**
 * Registrasi Custom Post Types jika diperlukan di masa depan.
 * Saat ini data disimpan di tabel custom ($wpdb), bukan CPT.
 * File ini disiapkan sebagai placeholder untuk integrasi tema / blok Gutenberg.
 */
class PostTypes {

    public function register(): void {
        // Placeholder – plugin menggunakan tabel custom, bukan CPT.
        // Tambahkan CPT di sini jika integrasi Gutenberg diperlukan.
        do_action( 'absensi_sekolah_loaded' );
    }
}
