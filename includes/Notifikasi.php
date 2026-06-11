<?php
namespace Absensi;

defined( 'ABSPATH' ) || exit;

/**
 * Notifikasi WhatsApp ke wali (orang tua) setelah anak absen.
 *
 * Dipicu via action internal `absensi_absen_masuk` / `absensi_absen_keluar`
 * yang di-fire AbsensiEndpoint setelah insert/update rekap. Pengiriman ke
 * gateway WA bersifat non-blocking (fire-and-forget) agar tidak memperlambat
 * respon absen.
 *
 * Gateway = generik. Konfigurasi via option:
 *   - `absensi_wa_gateway` : URL endpoint gateway (POST). Kosong = nonaktif.
 *   - `absensi_wa_token`   : token/api-key gateway.
 *
 * Karena format tiap gateway beda, payload & argumen request bisa diubah lewat
 * filter (lihat di bawah). Default payload: { token, target, message } — pola
 * umum (Fonnte/Wablas-like). Integrator sesuaikan via filter bila perlu.
 *
 * HP wali diambil dari user meta (filterable `absensi_wali_phone`); default
 * coba meta `absensi_no_wa`, lalu `billing_phone`, lalu `no_hp`.
 */
class Notifikasi {

    /** Daftarkan handler action absen. */
    public static function init(): void {
        add_action( 'absensi_absen_masuk', [ __CLASS__, 'on_masuk' ], 10, 2 );
        add_action( 'absensi_absen_keluar', [ __CLASS__, 'on_keluar' ], 10, 1 );
    }

    /** @param object $siswa baris absensi_siswa. @param string $status hadir|telat. */
    public static function on_masuk( $siswa, string $status ): void {
        self::notify( $siswa, 'masuk', $status );
    }

    /** @param object $siswa baris absensi_siswa. */
    public static function on_keluar( $siswa ): void {
        self::notify( $siswa, 'keluar', '' );
    }

    /** Gateway dikonfigurasi → notif aktif. Bisa dipaksa via filter. */
    public static function enabled(): bool {
        $gateway = (string) get_option( 'absensi_wa_gateway', '' );
        return (bool) apply_filters( 'absensi_wa_enabled', $gateway !== '', $gateway );
    }

    /**
     * Kirim notif ke semua wali siswa.
     * @param object $siswa  baris absensi_siswa (butuh ->id, ->nama).
     * @param string $event  'masuk' | 'keluar'.
     * @param string $status 'hadir' | 'telat' | ''.
     * @return int jumlah pesan yang dikirim (dispatch).
     */
    public static function notify( $siswa, string $event, string $status = '' ): int {
        if ( ! self::enabled() || empty( $siswa->id ) ) {
            return 0;
        }

        $phones = self::recipients( (int) $siswa->id );
        if ( ! $phones ) {
            return 0;
        }

        $message = self::build_message( $siswa, $event, $status );
        $sent    = 0;
        foreach ( $phones as $phone ) {
            if ( self::send_wa( $phone, $message ) ) {
                $sent++;
            }
        }
        return $sent;
    }

    /**
     * Daftar nomor HP wali dari relasi absensi_wali → user meta.
     * @return string[] nomor ter-normalisasi (62…), unik, non-kosong.
     */
    public static function recipients( int $siswa_id ): array {
        global $wpdb;
        $wali_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT wali_user_id FROM {$wpdb->prefix}absensi_wali WHERE siswa_id = %d",
            $siswa_id
        ) );

        $phones = [];
        foreach ( $wali_ids as $uid ) {
            $raw = self::wali_phone( (int) $uid );
            $num = self::format_phone( $raw );
            if ( $num !== '' ) {
                $phones[ $num ] = true; // dedup
            }
        }
        // array_keys meng-cast key numerik jadi int → kembalikan sebagai string.
        return array_map( 'strval', array_keys( $phones ) );
    }

    /** Ambil HP mentah wali dari user meta (filterable). */
    public static function wali_phone( int $wali_user_id ): string {
        $phone = '';
        foreach ( [ 'absensi_no_wa', 'billing_phone', 'no_hp' ] as $key ) {
            $v = get_user_meta( $wali_user_id, $key, true );
            if ( is_string( $v ) && $v !== '' ) {
                $phone = $v;
                break;
            }
        }
        return (string) apply_filters( 'absensi_wali_phone', $phone, $wali_user_id );
    }

    /**
     * Normalisasi nomor Indonesia ke format 62 (tanpa +/spasi/strip).
     * '0812...' → '62812...'; '+62812' → '62812'; '812...' → '62812...'.
     * Return '' bila tak ada digit valid.
     */
    public static function format_phone( string $raw ): string {
        $d = preg_replace( '/\D+/', '', $raw );
        if ( $d === '' ) {
            return '';
        }
        if ( str_starts_with( $d, '0' ) ) {
            $d = '62' . substr( $d, 1 );
        } elseif ( ! str_starts_with( $d, '62' ) ) {
            $d = '62' . $d;
        }
        // Buang nomor terlalu pendek (sisa < 10 digit setelah 62 = invalid)
        return strlen( $d ) >= 10 ? $d : '';
    }

    /** Susun teks notifikasi (filterable). */
    public static function build_message( $siswa, string $event, string $status = '' ): string {
        $nama = isset( $siswa->nama ) ? (string) $siswa->nama : 'Siswa';
        $jam  = current_time( 'H:i' );
        $tgl  = date_i18n( 'd/m/Y' );

        if ( 'keluar' === $event ) {
            $msg = sprintf( '[Absensi Sekolah] %s tercatat PULANG pukul %s (%s).', $nama, $jam, $tgl );
        } else {
            $label = 'telat' === $status ? 'HADIR (TERLAMBAT)' : 'HADIR';
            $msg   = sprintf( '[Absensi Sekolah] %s tercatat %s pukul %s (%s).', $nama, $label, $jam, $tgl );
        }

        return (string) apply_filters( 'absensi_wa_message', $msg, $siswa, $event, $status );
    }

    /**
     * Kirim 1 pesan ke gateway WA. Non-blocking (fire-and-forget).
     * @return bool true bila request berhasil di-dispatch (bukan jaminan terkirim).
     */
    public static function send_wa( string $phone, string $message ): bool {
        $gateway = (string) get_option( 'absensi_wa_gateway', '' );
        if ( $gateway === '' || $phone === '' ) {
            return false;
        }
        $token = (string) get_option( 'absensi_wa_token', '' );

        // Payload default pola umum gateway (Fonnte/Wablas-like). Filter untuk sesuaikan.
        $payload = apply_filters( 'absensi_wa_payload', [
            'target'  => $phone,
            'message' => $message,
            'token'   => $token,
        ], $phone, $message );

        $args = apply_filters( 'absensi_wa_request_args', [
            'timeout'  => 5,
            'blocking' => false, // fire-and-forget → respon absen tetap cepat
            'headers'  => [ 'Authorization' => $token ],
            'body'     => $payload,
        ], $phone, $message );

        $res = wp_remote_post( $gateway, $args );
        return ! is_wp_error( $res );
    }
}
