<?php
namespace Absensi\helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Helper untuk menyimpan file upload (selfie siswa).
 */
class FileHelper {

    /**
     * Simpan selfie base64 ke folder uploads WP.
     * Return: path relatif dari ABSPATH, atau WP_Error.
     *
     * @param string $base64   Data URI atau raw base64 JPEG/PNG.
     * @param int    $siswa_id ID siswa untuk penamaan file.
     */
    public static function save_selfie( string $base64, int $siswa_id ): string|\WP_Error {
        // Strip data URI prefix jika ada: "data:image/jpeg;base64,..."
        if ( str_contains( $base64, ',' ) ) {
            [ , $base64 ] = explode( ',', $base64, 2 );
        }

        $binary = base64_decode( $base64, strict: true );
        if ( false === $binary ) {
            return new \WP_Error( 'base64_invalid', 'Data foto tidak valid.' );
        }

        // Validasi magic bytes (JPEG: FFD8FF, PNG: 89504E47)
        $magic = bin2hex( substr( $binary, 0, 4 ) );
        if ( ! str_starts_with( $magic, 'ffd8ff' ) && ! str_starts_with( $magic, '89504e47' ) ) {
            return new \WP_Error( 'foto_bukan_gambar', 'File harus berupa JPEG atau PNG.' );
        }

        // Batas ukuran 5 MB
        if ( strlen( $binary ) > 5 * 1024 * 1024 ) {
            return new \WP_Error( 'foto_terlalu_besar', 'Ukuran foto maksimal 5 MB.' );
        }

        // Validasi gambar SUNGGUHAN (anti-polyglot: magic byte bisa dipalsukan).
        // getimagesizefromstring gagal kalau bukan struktur gambar valid.
        $info = @getimagesizefromstring( $binary );
        if ( false === $info || empty( $info[2] ) ) {
            return new \WP_Error( 'foto_korup', 'File bukan gambar yang valid.' );
        }
        $allowed = [ IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png' ];
        if ( ! isset( $allowed[ $info[2] ] ) ) {
            return new \WP_Error( 'foto_tipe_ditolak', 'Hanya JPEG atau PNG yang diizinkan.' );
        }
        $ext = $allowed[ $info[2] ]; // ekstensi otoritatif dari tipe gambar nyata

        $upload_dir = wp_upload_dir();
        $base       = $upload_dir['basedir'] . '/absensi-selfie';
        $folder     = $base . '/' . gmdate( 'Y/m' );
        wp_mkdir_p( $folder );

        // Pasang guard folder (idempotent): tolak eksekusi script + listing.
        self::protect_dir( $base );

        // Nama file random (anti-enumerasi/tebak URL). Tetap diawali siswa_id untuk audit.
        $token    = bin2hex( random_bytes( 16 ) );
        $filename = sprintf( 'selfie-%d-%s.%s', $siswa_id, $token, $ext );
        $filepath = $folder . '/' . $filename;

        if ( false === file_put_contents( $filepath, $binary ) ) {
            return new \WP_Error( 'tulis_file_gagal', 'Gagal menyimpan foto ke server.' );
        }

        // Verifikasi akhir: ekstensi cocok dengan tipe asli (WP filetype sniffing).
        if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
            require_once ABSPATH . 'wp-includes/functions.php';
        }
        $check = wp_check_filetype_and_ext( $filepath, $filename );
        if ( empty( $check['type'] ) || ! in_array( $check['type'], [ 'image/jpeg', 'image/png' ], true ) ) {
            @unlink( $filepath );
            return new \WP_Error( 'foto_tipe_ditolak', 'Tipe file tidak cocok dengan ekstensi.' );
        }

        // Return path relatif dari basedir untuk disimpan di DB
        return str_replace( $upload_dir['basedir'] . '/', '', $filepath );
    }

    /**
     * Pasang penjaga folder upload: index.php (anti listing) + .htaccess
     * (tolak eksekusi script & directory index). Idempotent, hanya tulis jika belum ada.
     */
    private static function protect_dir( string $dir ): void {
        wp_mkdir_p( $dir );

        $index = $dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }

        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            $rules  = "# Folder upload selfie: hanya gambar, tolak eksekusi script.\n";
            $rules .= "<FilesMatch \"\\.(?i:php|phtml|php[3-7]|phps|pl|py|cgi|asp|aspx|sh|shtml)$\">\n";
            $rules .= "    <IfModule mod_authz_core.c>\n        Require all denied\n    </IfModule>\n";
            $rules .= "    <IfModule !mod_authz_core.c>\n        Order allow,deny\n        Deny from all\n    </IfModule>\n";
            $rules .= "</FilesMatch>\n";
            $rules .= "Options -Indexes -ExecCGI\n";
            file_put_contents( $htaccess, $rules );
        }
    }

    /**
     * Konversi path relatif DB ke URL publik.
     */
    public static function selfie_url( string $path ): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/' . ltrim( $path, '/' );
    }
}
