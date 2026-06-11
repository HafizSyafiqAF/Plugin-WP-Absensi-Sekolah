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

        register_rest_route( self::NAMESPACE, '/absen/rfid/enroll', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_enroll' ],
            'permission_callback' => [ $this, 'can_enroll' ],
            'args'                => $this->enroll_args(),
        ] );

        register_rest_route( self::NAMESPACE, '/absen/rfid/resolve', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_resolve' ],
            'permission_callback' => [ $this, 'is_guru_or_admin' ],
            'args'                => [
                'uid' => [ 'required' => true, 'type' => 'string', 'maxLength' => 50 ],
            ],
        ] );
    }

    // ─── Handler Selfie + GPS ─────────────────────────────────────────────────

    public function handle_selfie( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $ssl = $this->ssl_error();
        if ( $ssl ) {
            return $ssl;
        }

        $siswa = $this->get_siswa_by_user( get_current_user_id() );
        if ( ! $siswa ) {
            return $this->error( 'siswa_tidak_ditemukan', 'Akun siswa tidak terdaftar.', 404 );
        }

        // Validasi GPS
        $lat = (float) $req->get_param( 'lat' );
        $lng = (float) $req->get_param( 'lng' );

        // Rentang koordinat siswa harus valid sebelum haversine (cegah hitung sampah)
        if ( ! GeoHelper::is_valid( $lat, $lng ) ) {
            return $this->error( 'koordinat_invalid', 'Koordinat GPS tidak valid.', 422 );
        }

        $radius_setting = (int) get_option( 'absensi_radius', 100 );
        $sekolah_lat    = (float) get_option( 'absensi_lat' );
        $sekolah_lng    = (float) get_option( 'absensi_lng' );

        // Koordinat sekolah belum dikonfigurasi → jangan beri "diluar_radius" yang menyesatkan
        if ( ! GeoHelper::is_valid( $sekolah_lat, $sekolah_lng ) || ( 0.0 === $sekolah_lat && 0.0 === $sekolah_lng ) ) {
            return $this->error( 'sekolah_belum_diatur', 'Koordinat sekolah belum dikonfigurasi.', 503 );
        }

        $jarak = GeoHelper::haversine( $lat, $lng, $sekolah_lat, $sekolah_lng );

        // Akurasi GPS: tolak reading terlalu tidak akurat (cegah spoof/sinyal buruk).
        // accuracy <= 0 dianggap tidak dikirim → lewati (backward-compatible).
        $akurasi     = (float) $req->get_param( 'accuracy' );
        $akurasi_max = (int) get_option( 'absensi_akurasi_max', 100 );
        if ( $akurasi > 0 && $akurasi > $akurasi_max ) {
            return $this->error(
                'akurasi_rendah',
                sprintf(
                    'Akurasi GPS %.0f m melebihi batas %d m (jarak ke sekolah %.0f m). Cari sinyal lebih baik.',
                    $akurasi, $akurasi_max, $jarak
                ),
                422
            );
        }

        if ( $jarak > $radius_setting ) {
            return $this->error(
                'diluar_radius',
                sprintf( 'Lokasi Anda %.0f meter dari sekolah (batas %d m).', $jarak, $radius_setting ),
                403
            );
        }

        // Tentukan sesi (masuk/pulang): param eksplisit, atau auto by kondisi rekap hari ini
        $today    = current_time( 'Y-m-d' );
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, waktu_masuk, waktu_keluar FROM {$wpdb->prefix}absensi_rekap WHERE siswa_id = %d AND tanggal = %s",
            $siswa->id, $today
        ) );

        $sesi = $req->get_param( 'sesi' );
        if ( ! in_array( $sesi, [ 'masuk', 'pulang' ], true ) ) {
            // Auto-suggest: belum ada baris → masuk; sudah masuk belum keluar → pulang; selain itu masuk
            $sesi = ! $existing ? 'masuk' : ( empty( $existing->waktu_keluar ) ? 'pulang' : 'masuk' );
        }

        // ── Sesi PULANG: UPDATE waktu_keluar baris hari ini (selaras RFID tap-2) ──
        if ( 'pulang' === $sesi ) {
            if ( ! $existing ) {
                return $this->error( 'belum_absen_masuk', 'Belum ada absen masuk hari ini.', 409 );
            }
            if ( ! empty( $existing->waktu_keluar ) ) {
                return $this->error( 'sudah_absen_keluar', 'Anda sudah absen pulang hari ini.', 409 );
            }
            $wpdb->update(
                $wpdb->prefix . 'absensi_rekap',
                SanitizeHelper::rekap( [
                    'waktu_keluar'  => current_time( 'mysql' ),
                    'metode_keluar' => 'selfie',
                ] ),
                [ 'id' => (int) $existing->id ]
            );
            do_action( 'absensi_absen_keluar', $siswa );
            return new \WP_REST_Response( [
                'success' => true,
                'sesi'    => 'pulang',
                'jarak'   => round( $jarak ),
                'message' => 'Absen pulang berhasil!',
            ], 200 );
        }

        // ── Sesi MASUK: insert baris baru ──
        if ( $existing ) {
            return $this->error( 'sudah_absen', 'Anda sudah absen masuk hari ini.', 409 );
        }

        // Simpan foto selfie (base64) – hanya sesi masuk (skema 1 foto/hari)
        $foto_base64 = $req->get_param( 'foto' );
        $foto_path   = '';
        if ( $foto_base64 ) {
            $foto_path = FileHelper::save_selfie( $foto_base64, $siswa->id );
            if ( is_wp_error( $foto_path ) ) {
                return $this->error( 'foto_gagal', $foto_path->get_error_message() );
            }
        }

        // Tentukan status masuk (hadir/telat) – konsisten timezone WP
        $status = $this->tentukan_status_masuk( (int) $siswa->kelas_id );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'absensi_rekap',
            SanitizeHelper::rekap( [
                'siswa_id'     => $siswa->id,
                'kelas_id'     => $siswa->kelas_id,
                'tanggal'      => $today,
                'waktu_masuk'  => current_time( 'mysql' ),
                'status'       => $status,
                'mode'         => 'selfie',
                'metode_masuk' => 'selfie',
                'lat'          => $lat,
                'lng'          => $lng,
                'jarak_meter'  => (int) round( $jarak ),
                'foto_path'    => $foto_path,
            ] )
        );

        if ( ! $inserted ) {
            return $this->error( 'db_error', 'Gagal menyimpan absensi.', 500 );
        }

        do_action( 'absensi_absen_masuk', $siswa, $status );

        return new \WP_REST_Response( [
            'success' => true,
            'sesi'    => 'masuk',
            'status'  => $status,
            'jarak'   => round( $jarak ),
            'message' => $status === 'hadir' ? 'Absen berhasil!' : 'Absen diterima, namun Anda terlambat.',
        ], 201 );
    }

    // ─── Handler RFID ─────────────────────────────────────────────────────────

    public function handle_rfid( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $ssl = $this->ssl_error();
        if ( $ssl ) {
            return $ssl;
        }

        // Sanitasi UID – strip karakter non-alphanumerik
        $uid = SanitizeHelper::rfid_uid( $req->get_param( 'rfid_uid' ) );
        if ( empty( $uid ) ) {
            return $this->error( 'uid_kosong', 'UID RFID tidak valid.' );
        }

        // Anti double-tap: tolak UID sama dalam window (detik). 0 = nonaktif.
        $window = (int) get_option( 'absensi_rfid_debounce', 3 );
        if ( $window > 0 ) {
            $tap_key = 'absensi_rfid_tap_' . md5( $uid );
            if ( false !== get_transient( $tap_key ) ) {
                return $this->error( 'double_tap', 'Kartu baru saja di-tap, tunggu sebentar.', 429 );
            }
            set_transient( $tap_key, time(), $window );
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
                SanitizeHelper::rekap( [
                    'waktu_keluar'  => current_time( 'mysql' ),
                    'metode_keluar' => 'rfid',
                ] ),
                [ 'id' => (int) $existing->id ]
            );
            do_action( 'absensi_absen_keluar', $siswa );
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

        // Tap pertama = catat masuk. Status hadir/telat konsisten timezone WP.
        $status = $this->tentukan_status_masuk( (int) $siswa->kelas_id );

        $wpdb->insert(
            $wpdb->prefix . 'absensi_rekap',
            SanitizeHelper::rekap( [
                'siswa_id'    => $siswa->id,
                'kelas_id'    => $siswa->kelas_id,
                'tanggal'     => $today,
                'waktu_masuk'  => current_time( 'mysql' ),
                'status'       => $status,
                'mode'         => 'rfid',
                'metode_masuk' => 'rfid',
                'guru_id'      => get_current_user_id(),
            ] )
        );

        do_action( 'absensi_absen_masuk', $siswa, $status );

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

    // ─── Enroll Kartu RFID (tap untuk daftar) ─────────────────────────────────

    public function handle_enroll( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'absensi_siswa';

        $siswa_id = absint( $req->get_param( 'siswa_id' ) );
        $uid      = SanitizeHelper::rfid_uid( $req->get_param( 'rfid_uid' ) );
        $replace  = filter_var( $req->get_param( 'replace' ), FILTER_VALIDATE_BOOLEAN );

        if ( empty( $uid ) ) {
            return $this->error( 'uid_kosong', 'UID RFID tidak valid.', 422 );
        }

        // Siswa target harus ada
        $siswa = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, nama, rfid_uid FROM {$table} WHERE id = %d LIMIT 1",
            $siswa_id
        ) );
        if ( ! $siswa ) {
            return $this->error( 'siswa_tidak_ditemukan', 'Siswa tidak ditemukan.', 404 );
        }

        // UID sudah dipakai siswa lain → 409 + nama pemilik
        $pemilik = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, nama FROM {$table} WHERE rfid_uid = %s AND id <> %d LIMIT 1",
            $uid, $siswa_id
        ) );
        if ( $pemilik ) {
            return $this->error( 'kartu_terpakai', "Kartu sudah dipakai oleh {$pemilik->nama}.", 409 );
        }

        // Siswa sudah punya kartu berbeda → wajib replace=true
        $punya_kartu = ! empty( $siswa->rfid_uid ) && $siswa->rfid_uid !== $uid;
        if ( $punya_kartu && ! $replace ) {
            return $this->error( 'sudah_punya_kartu', "{$siswa->nama} sudah punya kartu. Kirim replace=true untuk mengganti.", 409 );
        }

        $wpdb->update(
            $table,
            [ 'rfid_uid' => $uid ],
            [ 'id' => $siswa_id ],
            [ '%s' ],
            [ '%d' ]
        );

        return new \WP_REST_Response( [
            'success'    => true,
            'siswa_id'   => $siswa_id,
            'siswa'      => $siswa->nama,
            'uid_masked' => $this->mask_uid( $uid ),
            'replaced'   => $punya_kartu,
            'message'    => "Kartu terdaftar untuk {$siswa->nama}.",
        ], 200 );
    }

    /**
     * Resolve UID → identitas siswa pemilik kartu (feedback layar guru sebelum tap/enroll).
     * Read-only, TIDAK menyimpan apa pun. Cap is_guru_or_admin.
     * GET /absen/rfid/resolve?uid=...
     */
    public function handle_resolve( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $uid = SanitizeHelper::rfid_uid( $req->get_param( 'uid' ) );
        if ( empty( $uid ) ) {
            return $this->error( 'uid_kosong', 'UID RFID tidak valid.', 422 );
        }

        $siswa = $wpdb->get_row( $wpdb->prepare(
            "SELECT s.id, s.nama, s.nis, s.kelas_id, k.nama_kelas
               FROM {$wpdb->prefix}absensi_siswa s
               LEFT JOIN {$wpdb->prefix}absensi_kelas k ON k.id = s.kelas_id
              WHERE s.rfid_uid = %s LIMIT 1",
            $uid
        ) );
        if ( ! $siswa ) {
            return $this->error( 'uid_tidak_terdaftar', 'Kartu belum terdaftar ke siswa mana pun.', 404 );
        }

        return new \WP_REST_Response( [
            'found'      => true,
            'siswa_id'   => (int) $siswa->id,
            'nama'       => $siswa->nama,
            'nis'        => $siswa->nis,
            'kelas_id'   => (int) $siswa->kelas_id,
            'nama_kelas' => $siswa->nama_kelas,
            'uid_masked' => $this->mask_uid( $uid ),
        ] );
    }

    /** Mask UID: sisakan 4 karakter terakhir (privasi), mis. ••••A3F2. */
    private function mask_uid( string $uid ): string {
        return '••••' . substr( $uid, -4 );
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    private function get_siswa_by_user( int $user_id ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_siswa WHERE user_id = %d LIMIT 1",
            $user_id
        ) ) ?: null;
    }

    /**
     * Tolak absen jika koneksi bukan HTTPS (kamera & Geolocation API butuh SSL,
     * plan §5). Default enforce; bisa dimatikan untuk dev lokal lewat filter:
     *   add_filter( 'absensi_enforce_ssl', '__return_false' );
     *
     * @return \WP_REST_Response|null  403 bila ditolak, null bila lolos.
     */
    private function ssl_error(): ?\WP_REST_Response {
        if ( is_ssl() ) {
            return null;
        }
        if ( ! apply_filters( 'absensi_enforce_ssl', true ) ) {
            return null;
        }
        return $this->error( 'butuh_https', 'Absen memerlukan koneksi aman (HTTPS).', 403 );
    }

    /**
     * Tentukan status masuk (hadir/telat) berbasis timezone WordPress.
     *
     * FIX timezone: kode lama campur time() (epoch UTC) dengan
     * strtotime( current_time('Y-m-d').' '.jam ) yang di-parse PHP sebagai UTC
     * (WP memaksa default tz = UTC) → batas telat meleset sebesar offset WP
     * (mis. +7 jam di Asia/Jakarta), siswa telat bisa ke-cap "hadir".
     * Sekarang "now" dan "batas" sama-sama dihitung di wp_timezone().
     */
    private function tentukan_status_masuk( int $kelas_id = 0 ): string {
        global $wpdb;
        $tz  = wp_timezone();
        $now = new \DateTimeImmutable( 'now', $tz );

        // Jam masuk dari jadwal kelas (hari ini); fallback ke option global
        $jam_masuk = '';
        if ( $kelas_id > 0 ) {
            $hari      = (int) $now->format( 'N' ); // 1=Senin .. 7=Minggu
            $jam_masuk = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT jam_masuk FROM {$wpdb->prefix}absensi_jadwal WHERE kelas_id = %d AND hari = %d LIMIT 1",
                $kelas_id, $hari
            ) );
        }
        if ( '' === $jam_masuk ) {
            $jam_masuk = (string) get_option( 'absensi_jam_masuk', '07:00' );
        }

        // Normalisasi ke H:i:s (jadwal = TIME 'HH:MM:SS', option = 'HH:MM')
        if ( preg_match( '/^\d{1,2}:\d{2}$/', $jam_masuk ) ) {
            $jam_masuk .= ':00';
        }

        $telat_menit = (int) get_option( 'absensi_telat_menit', 15 );
        $batas = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $now->format( 'Y-m-d' ) . ' ' . $jam_masuk, $tz );
        if ( false === $batas ) {
            return 'hadir'; // format jam invalid → jangan tuduh telat
        }
        $batas = $batas->modify( "+{$telat_menit} minutes" );

        return $now > $batas ? 'telat' : 'hadir';
    }

    private function error( string $code, string $message, int $status = 400 ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }

    public function is_logged_in(): bool {
        return is_user_logged_in();
    }

    public function is_guru_or_admin(): bool {
        $user = wp_get_current_user();
        return ! empty( array_intersect( $user->roles, [ 'administrator', 'guru', 'absensi_admin' ] ) );
    }

    public function can_enroll(): bool {
        return current_user_can( 'absensi_enroll_rfid' );
    }

    // ─── Args Validasi ────────────────────────────────────────────────────────

    private function selfie_args(): array {
        return [
            'lat'  => [ 'required' => true,  'type' => 'number' ],
            'lng'  => [ 'required' => true,  'type' => 'number' ],
            'foto'     => [ 'required' => false, 'type' => 'string' ],
            'sesi'     => [ 'required' => false, 'type' => 'string', 'enum' => [ 'masuk', 'pulang' ] ],
            'accuracy' => [ 'required' => false, 'type' => 'number' ],
        ];
    }

    private function rfid_args(): array {
        return [
            'rfid_uid' => [ 'required' => true, 'type' => 'string', 'maxLength' => 50 ],
        ];
    }

    private function enroll_args(): array {
        return [
            'siswa_id' => [ 'required' => true,  'type' => 'integer' ],
            'rfid_uid' => [ 'required' => true,  'type' => 'string', 'maxLength' => 50 ],
            'replace'  => [ 'required' => false, 'type' => 'boolean', 'default' => false ],
        ];
    }
}
