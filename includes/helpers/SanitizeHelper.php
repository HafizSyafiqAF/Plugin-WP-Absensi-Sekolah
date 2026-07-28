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
     * Sanitasi data user (skema v2: absensi_users) untuk INSERT/UPDATE.
     * Kolom: nomor_induk (≤30), nama (≤150), group_id (absint), rfid_uid (hex, kosong→null).
     */
    public static function users( array $data ): array {
        $clean = [];
        if ( isset( $data['nomor_induk'] ) ) $clean['nomor_induk'] = substr( sanitize_text_field( $data['nomor_induk'] ), 0, 30 );
        if ( isset( $data['nama'] ) )        $clean['nama']        = substr( sanitize_text_field( $data['nama'] ), 0, 150 );
        if ( isset( $data['group_id'] ) )    $clean['group_id']    = absint( $data['group_id'] );
        if ( isset( $data['rfid_uid'] ) )    $clean['rfid_uid']    = self::rfid_uid( $data['rfid_uid'] ) ?: null;
        return $clean;
    }

    /**
     * Sanitasi data group (skema v2: absensi_group) untuk INSERT/UPDATE.
     * Kolom: nama (≤100), tipe (string BEBAS tersanitasi ≤50; kosong → 'kelas').
     * (v2.1.0: tipe kustom — tak lagi whitelist kelas/guru/staff.)
     */
    public static function group( array $data ): array {
        $clean = [];
        if ( isset( $data['nama'] ) ) $clean['nama'] = substr( sanitize_text_field( $data['nama'] ), 0, 100 );
        if ( isset( $data['tipe'] ) ) {
            // Bebas diisi admin (mis. "Ekskul", "Panitia"); rapikan spasi, batasi 50 char.
            $tipe = trim( (string) preg_replace( '/\s+/', ' ', sanitize_text_field( (string) $data['tipe'] ) ) );
            $tipe = function_exists( 'mb_substr' ) ? mb_substr( $tipe, 0, 50 ) : substr( $tipe, 0, 50 );
            $clean['tipe'] = '' !== $tipe ? $tipe : 'kelas';
        }
        return $clean;
    }

    /**
     * Sanitasi data jadwal untuk INSERT/UPDATE.
     * Kolom: group_id, hari (1–7), jam_masuk/jam_keluar (TIME, dinormalisasi H:i:s).
     * Jam tak valid → '' (endpoint menolak dengan 422).
     */
    public static function jadwal( array $data ): array {
        $clean = [];
        if ( isset( $data['group_id'] ) )   $clean['group_id']   = absint( $data['group_id'] );
        if ( isset( $data['hari'] ) )       $clean['hari']       = absint( $data['hari'] );
        if ( isset( $data['jam_masuk'] ) )  $clean['jam_masuk']  = self::normalize_time( $data['jam_masuk'] );
        if ( isset( $data['jam_keluar'] ) ) $clean['jam_keluar'] = self::normalize_time( $data['jam_keluar'] );
        return $clean;
    }

    /**
     * Sanitasi data hari libur (v2.2.0) untuk INSERT/UPDATE.
     * Kolom: tanggal_mulai/tanggal_selesai (DATE 'Y-m-d'), keterangan (≤150).
     * Tanggal tak valid → '' (endpoint menolak dengan 422). Urutan mulai>selesai TIDAK dibetulkan
     * di sini — endpoint yang menolak, supaya admin sadar salah input (bukan diam-diam ditukar).
     */
    public static function libur( array $data ): array {
        $clean = [];
        if ( isset( $data['tanggal_mulai'] ) )   $clean['tanggal_mulai']   = self::normalize_date( $data['tanggal_mulai'] );
        if ( isset( $data['tanggal_selesai'] ) ) $clean['tanggal_selesai'] = self::normalize_date( $data['tanggal_selesai'] );
        if ( isset( $data['keterangan'] ) ) {
            $clean['keterangan'] = substr( sanitize_text_field( (string) $data['keterangan'] ), 0, 150 );
        }
        return $clean;
    }

    /**
     * Normalisasi tanggal ke format MySQL DATE 'Y-m-d'.
     * Terima 'Y-m-d'. Return '' jika tak valid (termasuk tanggal mustahil seperti 2026-02-31).
     */
    public static function normalize_date( $value ): string {
        $value = trim( (string) $value );
        if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
            return '';
        }
        // checkdate menolak 31 Feb / 30 Feb dll — regex saja tak cukup.
        if ( ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
            return '';
        }
        return sprintf( '%04d-%02d-%02d', (int) $m[1], (int) $m[2], (int) $m[3] );
    }

    /**
     * Normalisasi jam ke format MySQL TIME 'H:i:s'.
     * Terima 'H:i' atau 'H:i:s'. Return '' jika tak valid.
     */
    public static function normalize_time( $value ): string {
        $value = trim( (string) $value );
        if ( ! preg_match( '/^([01]?\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $m ) ) {
            return '';
        }
        return sprintf( '%02d:%02d:%02d', (int) $m[1], (int) $m[2], (int) ( $m[3] ?? 0 ) );
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
        // Skema v2: kunci relasi hanya user_id/group_id (eks siswa_id/kelas_id sudah dibuang).
        if ( isset( $data['user_id'] ) )      $clean['user_id']      = absint( $data['user_id'] );
        if ( isset( $data['group_id'] ) )     $clean['group_id']     = absint( $data['group_id'] );
        if ( isset( $data['tanggal'] ) )      $clean['tanggal']      = sanitize_text_field( $data['tanggal'] );
        if ( isset( $data['waktu_masuk'] ) )  $clean['waktu_masuk']  = sanitize_text_field( $data['waktu_masuk'] );
        if ( isset( $data['waktu_keluar'] ) ) $clean['waktu_keluar'] = sanitize_text_field( $data['waktu_keluar'] );
        if ( isset( $data['status'] ) )       $clean['status']       = in_array( $data['status'], $allowed_status, true ) ? $data['status'] : 'hadir';
        if ( isset( $data['mode'] ) )          $clean['mode']          = in_array( $data['mode'], $allowed_mode, true ) ? $data['mode'] : 'manual';
        if ( isset( $data['metode_masuk'] ) )  $clean['metode_masuk']  = in_array( $data['metode_masuk'], $allowed_mode, true ) ? $data['metode_masuk'] : 'manual';
        if ( isset( $data['metode_keluar'] ) ) $clean['metode_keluar'] = in_array( $data['metode_keluar'], $allowed_mode, true ) ? $data['metode_keluar'] : 'manual';
        if ( isset( $data['lat'] ) )          $clean['lat']          = (float) $data['lat'];
        if ( isset( $data['lng'] ) )          $clean['lng']          = (float) $data['lng'];
        if ( isset( $data['jarak_meter'] ) )  $clean['jarak_meter']  = absint( $data['jarak_meter'] );
        // Akurasi GPS (meter). <= 0 berarti klien tak mengirimkannya → simpan NULL, jangan 0
        // (0 akan terbaca sebagai "fix sempurna" oleh deteksi lokasi janggal).
        if ( isset( $data['akurasi'] ) )      $clean['akurasi']      = ( (float) $data['akurasi'] > 0 ) ? round( (float) $data['akurasi'], 2 ) : null;
        if ( isset( $data['flag_lokasi'] ) )  $clean['flag_lokasi']  = in_array( $data['flag_lokasi'], [ 'koordinat_identik' ], true ) ? $data['flag_lokasi'] : null;
        if ( isset( $data['foto_path'] ) )    $clean['foto_path']    = sanitize_text_field( $data['foto_path'] );
        if ( isset( $data['catatan'] ) )      $clean['catatan']      = sanitize_textarea_field( $data['catatan'] );
        if ( isset( $data['guru_id'] ) )      $clean['guru_id']      = absint( $data['guru_id'] ) ?: null;
        // Izin/sakit + verifikasi bukti (kolom nullable; nilai invalid → null, tak dipaksa default).
        if ( isset( $data['izin_tipe'] ) )    $clean['izin_tipe']    = in_array( $data['izin_tipe'], [ 'izin', 'sakit' ], true ) ? $data['izin_tipe'] : null;
        if ( isset( $data['bukti_status'] ) ) $clean['bukti_status'] = in_array( $data['bukti_status'], [ 'menunggu', 'setuju', 'tolak' ], true ) ? $data['bukti_status'] : null;
        if ( isset( $data['bukti_path'] ) )   $clean['bukti_path']   = substr( sanitize_text_field( $data['bukti_path'] ), 0, 255 );
        return $clean;
    }
}
