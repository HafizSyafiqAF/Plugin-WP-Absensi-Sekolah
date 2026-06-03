<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\GeoHelper;
use Absensi\helpers\FileHelper;
use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/absen
 *
 * POST /absen/selfie  – Absen mandiri siswa (selfie + GPS)
 * POST /absen/rfid    – Absen RFID oleh guru
 * GET  /absen/status  – Cek status absen siswa hari ini
 */
class AbsensiEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/absen/selfie', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_selfie' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
            'args'                => $this->selfie_args(),
        ] );

        register_rest_route( self::NAMESPACE, '/absen/rfid', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_rfid' ],
            'permission_callback' => [ $this, 'is_guru_or_admin' ],
            'args'                => $this->rfid_args(),
        ] );

        register_rest_route( self::NAMESPACE, '/absen/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_status' ],
            'permission_callback' => [ $this, 'is_logged_in' ],
        ] );
    }

    // ─── Handler Selfie + GPS ─────────────────────────────────────────────────

    public function handle_selfie( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $siswa = $this->get_siswa_by_user( get_current_user_id() );
        if ( ! $siswa ) {
            return $this->error( 'siswa_tidak_ditemukan', 'Akun siswa tidak terdaftar.', 404 );
        }

        // Validasi GPS
        $lat = (float) $req->get_param( 'lat' );
        $lng = (float) $req->get_param( 'lng' );
        $radius_setting = (int) get_option( 'absensi_radius', 100 );
        $sekolah_lat    = (float) get_option( 'absensi_lat' );
        $sekolah_lng    = (float) get_option( 'absensi_lng' );

        $jarak = GeoHelper::haversine( $lat, $lng, $sekolah_lat, $sekolah_lng );
        if ( $jarak > $radius_setting ) {
            return $this->error(
                'diluar_radius',
                sprintf( 'Lokasi Anda %.0f meter dari sekolah (batas %d m).', $jarak, $radius_setting ),
                403
            );
        }

        // Simpan foto selfie (base64)
        $foto_base64 = $req->get_param( 'foto' );
        $foto_path   = '';
        if ( $foto_base64 ) {
            $foto_path = FileHelper::save_selfie( $foto_base64, $siswa->id );
            if ( is_wp_error( $foto_path ) ) {
                return $this->error( 'foto_gagal', $foto_path->get_error_message() );
            }
        }

        // Cek duplikasi hari ini
        $today   = current_time( 'Y-m-d' );
        $existing = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}absensi_rekap WHERE siswa_id = %d AND tanggal = %s",
            $siswa->id, $today
        ) );
        if ( $existing ) {
            return $this->error( 'sudah_absen', 'Anda sudah absen hari ini.', 409 );
        }

        // Tentukan status (hadir/telat)
        $jam_masuk_setting = get_option( 'absensi_jam_masuk', '07:00' );
        $telat_menit       = (int) get_option( 'absensi_telat_menit', 15 );
        $batas_telat       = strtotime( current_time( 'Y-m-d' ) . ' ' . $jam_masuk_setting ) + ( $telat_menit * 60 );
        $status            = time() > $batas_telat ? 'telat' : 'hadir';

        // Insert rekap
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'absensi_rekap',
            SanitizeHelper::rekap( [
                'siswa_id'    => $siswa->id,
                'kelas_id'    => $siswa->kelas_id,
                'tanggal'     => $today,
                'waktu_masuk' => current_time( 'mysql' ),
                'status'      => $status,
                'mode'        => 'selfie',
                'lat'         => $lat,
                'lng'         => $lng,
                'foto_path'   => $foto_path,
            ] )
        );

        if ( ! $inserted ) {
            return $this->error( 'db_error', 'Gagal menyimpan absensi.', 500 );
        }

        return new \WP_REST_Response( [
            'success' => true,
            'status'  => $status,
            'jarak'   => round( $jarak ),
            'message' => $status === 'hadir' ? 'Absen berhasil!' : 'Absen diterima, namun Anda terlambat.',
        ], 201 );
    }

    // ─── Handler RFID ─────────────────────────────────────────────────────────

    public function handle_rfid( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        // Sanitasi UID – strip karakter non-alphanumerik
        $uid = SanitizeHelper::rfid_uid( $req->get_param( 'rfid_uid' ) );
        if ( empty( $uid ) ) {
            return $this->error( 'uid_kosong', 'UID RFID tidak valid.' );
        }

        // Cari siswa berdasarkan UID
        $siswa = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_siswa WHERE rfid_uid = %s LIMIT 1",
            $uid
        ) );
        if ( ! $siswa ) {
            return $this->error( 'uid_tidak_terdaftar', "UID $uid tidak terdaftar.", 404 );
        }

        $today = current_time( 'Y-m-d' );
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, waktu_masuk FROM {$wpdb->prefix}absensi_rekap WHERE siswa_id = %d AND tanggal = %s",
            $siswa->id, $today
        ) );

        // Tap kedua = catat waktu keluar
        if ( $existing && empty( $existing->waktu_keluar ) ) {
            $wpdb->update(
                $wpdb->prefix . 'absensi_rekap',
                [ 'waktu_keluar' => current_time( 'mysql' ) ],
                [ 'id' => (int) $existing->id ],
                [ '%s' ],
                [ '%d' ]
            );
            return new \WP_REST_Response( [
                'success' => true,
                'action'  => 'keluar',
                'siswa'   => $siswa->nama,
                'message' => "Selamat siang, {$siswa->nama}! Waktu keluar dicatat.",
            ] );
        }

        if ( $existing ) {
            return $this->error( 'sudah_absen', "{$siswa->nama} sudah absen masuk dan keluar hari ini.", 409 );
        }

        // Tap pertama = catat masuk
        $jam_masuk_setting = get_option( 'absensi_jam_masuk', '07:00' );
        $telat_menit       = (int) get_option( 'absensi_telat_menit', 15 );
        $batas_telat       = strtotime( current_time( 'Y-m-d' ) . ' ' . $jam_masuk_setting ) + ( $telat_menit * 60 );
        $status            = time() > $batas_telat ? 'telat' : 'hadir';

        $wpdb->insert(
            $wpdb->prefix . 'absensi_rekap',
            SanitizeHelper::rekap( [
                'siswa_id'    => $siswa->id,
                'kelas_id'    => $siswa->kelas_id,
                'tanggal'     => $today,
                'waktu_masuk' => current_time( 'mysql' ),
                'status'      => $status,
                'mode'        => 'rfid',
                'guru_id'     => get_current_user_id(),
            ] )
        );

        return new \WP_REST_Response( [
            'success' => true,
            'action'  => 'masuk',
            'status'  => $status,
            'siswa'   => $siswa->nama,
            'message' => "Selamat datang, {$siswa->nama}!",
        ], 201 );
    }

    // ─── Status Absen Hari Ini ─────────────────────────────────────────────────

    public function get_status( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $siswa = $this->get_siswa_by_user( get_current_user_id() );
        if ( ! $siswa ) {
            return $this->error( 'siswa_tidak_ditemukan', 'Akun siswa tidak ditemukan.', 404 );
        }
        $today  = current_time( 'Y-m-d' );
        $rekap  = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_rekap WHERE siswa_id = %d AND tanggal = %s",
            $siswa->id, $today
        ) );
        return new \WP_REST_Response( [
            'sudah_absen' => (bool) $rekap,
            'rekap'       => $rekap,
        ] );
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function get_siswa_by_user( int $user_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_siswa WHERE user_id = %d LIMIT 1",
            $user_id
        ) ) ?: null;
    }

    private function error( string $code, string $message, int $status = 400 ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message ], $status );
    }

    public function is_logged_in(): bool {
        return is_user_logged_in();
    }

    public function is_guru_or_admin(): bool {
        $user = wp_get_current_user();
        return ! empty( array_intersect( $user->roles, [ 'administrator', 'guru', 'absensi_admin' ] ) );
    }

    // ─── Args Validasi ────────────────────────────────────────────────────────

    private function selfie_args(): array {
        return [
            'lat'  => [ 'required' => true,  'type' => 'number' ],
            'lng'  => [ 'required' => true,  'type' => 'number' ],
            'foto' => [ 'required' => false, 'type' => 'string' ],
        ];
    }

    private function rfid_args(): array {
        return [
            'rfid_uid' => [ 'required' => true, 'type' => 'string', 'maxLength' => 50 ],
        ];
    }
}
