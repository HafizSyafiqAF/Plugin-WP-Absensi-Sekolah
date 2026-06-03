<?php
namespace Absensi\helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitasi terpusat untuk semua input sebelum masuk DB.
 * WAJIB dipakai di setiap wpdb->insert / wpdb->update.
 *
 * CATATAN KEAMANAN:
 * - Semua nilai di-escape via wpdb->prepare() atau format array wpdb.
 * - Jangan pernah embed raw user input ke dalam query string SQL.
 * - Fungsi-fungsi di sini hanya membersihkan tipe data, bukan pengganti prepare().
 */
class SanitizeHelper {

    /**
     * Sanitasi data siswa untuk INSERT/UPDATE.
     */
    public static function siswa( array $data ): array {
        $clean = [];
        if ( isset( $data['nis'] ) )      $clean['nis']      = substr( sanitize_text_field( $data['nis'] ), 0, 20 );
        if ( isset( $data['nama'] ) )     $clean['nama']     = substr( sanitize_text_field( $data['nama'] ), 0, 150 );
        if ( isset( $data['kelas_id'] ) ) $clean['kelas_id'] = absint( $data['kelas_id'] );
        if ( isset( $data['user_id'] ) )  $clean['user_id']  = absint( $data['user_id'] ) ?: null;
        return $clean;
    }

    /**
     * Sanitasi UID RFID – hanya hex dan strip leading/trailing whitespace.
     * RFID HID keyboard sering mengirim newline di akhir.
     */
    public static function rfid_uid( ?string $uid ): string {
        if ( null === $uid ) {
            return '';
        }
        // Trim + buang karakter di luar hex (termasuk CR/LF dari HID)
        return strtoupper( preg_replace( '/[^A-Fa-f0-9]/', '', trim( $uid ) ) );
    }

    /**
     * Sanitasi data rekap absensi.
     * Format array wpdb: [ kolom => value ] – tipe di-handle oleh wpdb.
     */
    public static function rekap( array $data ): array {
        $allowed_status = [ 'hadir', 'telat', 'izin', 'sakit', 'alpha' ];
        $allowed_mode   = [ 'selfie', 'rfid', 'manual' ];

        $clean = [];
        if ( isset( $data['siswa_id'] ) )     $clean['siswa_id']     = absint( $data['siswa_id'] );
        if ( isset( $data['kelas_id'] ) )     $clean['kelas_id']     = absint( $data['kelas_id'] );
        if ( isset( $data['tanggal'] ) )      $clean['tanggal']      = sanitize_text_field( $data['tanggal'] );
        if ( isset( $data['waktu_masuk'] ) )  $clean['waktu_masuk']  = sanitize_text_field( $data['waktu_masuk'] );
        if ( isset( $data['waktu_keluar'] ) ) $clean['waktu_keluar'] = sanitize_text_field( $data['waktu_keluar'] );
        if ( isset( $data['status'] ) )       $clean['status']       = in_array( $data['status'], $allowed_status, true ) ? $data['status'] : 'hadir';
        if ( isset( $data['mode'] ) )         $clean['mode']         = in_array( $data['mode'], $allowed_mode, true ) ? $data['mode'] : 'manual';
        if ( isset( $data['lat'] ) )          $clean['lat']          = (float) $data['lat'];
        if ( isset( $data['lng'] ) )          $clean['lng']          = (float) $data['lng'];
        if ( isset( $data['foto_path'] ) )    $clean['foto_path']    = sanitize_text_field( $data['foto_path'] );
        if ( isset( $data['catatan'] ) )      $clean['catatan']      = sanitize_textarea_field( $data['catatan'] );
        if ( isset( $data['guru_id'] ) )      $clean['guru_id']      = absint( $data['guru_id'] ) ?: null;
        return $clean;
    }
}
