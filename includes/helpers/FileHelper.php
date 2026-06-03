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

        $ext = str_starts_with( $magic, 'ffd8ff' ) ? 'jpg' : 'png';

        $upload_dir = wp_upload_dir();
        $folder     = $upload_dir['basedir'] . '/absensi-selfie/' . gmdate( 'Y/m' );
        wp_mkdir_p( $folder );

        $filename = sprintf( 'selfie-%d-%s.%s', $siswa_id, gmdate( 'Ymd-His' ), $ext );
        $filepath = $folder . '/' . $filename;

        if ( false === file_put_contents( $filepath, $binary ) ) {
            return new \WP_Error( 'tulis_file_gagal', 'Gagal menyimpan foto ke server.' );
        }

        // Return path relatif dari basedir untuk disimpan di DB
        return str_replace( $upload_dir['basedir'] . '/', '', $filepath );
    }

    /**
     * Konversi path relatif DB ke URL publik.
     */
    public static function selfie_url( string $path ): string {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/' . ltrim( $path, '/' );
    }
}
