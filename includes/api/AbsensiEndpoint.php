<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\GeoHelper;
use Absensi\helpers\FileHelper;
use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/absen — kiosk publik (tanpa login).
 *
 * POST /absen/selfie  – Absen mandiri by nomor_induk (selfie + GPS)
 * POST /absen/rfid    – Absen tap kartu RFID (kiosk)
 * GET  /absen/status  – Cek status absen hari ini by nomor_induk
 */
class AbsensiEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        // Kiosk publik (tanpa login): permission terbuka; anti-abuse via rate-limit
        // per nomor_induk di handler. Identitas dari nomor_induk, bukan user WP.
        register_rest_route( self::NAMESPACE, '/absen/selfie', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_selfie' ],
            'permission_callback' => '__return_true',
            'args'                => $this->selfie_args(),
        ] );

        // Kiosk RFID publik (perangkat guru, tanpa login): permission terbuka,
        // anti double-tap via debounce transient di handler.
        register_rest_route( self::NAMESPACE, '/absen/rfid', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_rfid' ],
            'permission_callback' => '__return_true',
            'args'                => $this->rfid_args(),
        ] );

        // Cek status hari ini, kiosk publik via nomor_induk (tanpa login).
        // Respons SENGAJA hanya field presensi non-sensitif (tanpa lat/lng/foto/bukti).
        register_rest_route( self::NAMESPACE, '/absen/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_status' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'nomor_induk' => [ 'required' => true, 'type' => 'string', 'maxLength' => 30 ],
            ],
        ] );

        // Route lama enroll/resolve/izin/set-status DIBUANG (pivot kiosk):
        // - enroll/resolve: duplikat POST /users/{id}/rfid (UsersEndpoint).
        // - izin + konfirmasi: luar MVP; model lama butuh login/role yang sudah dihapus.
        //   Saat masuk roadmap: pengajuan kiosk by nomor_induk, approve wp-admin.
    }

    // ─── Handler Selfie + GPS ─────────────────────────────────────────────────

    public function handle_selfie( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $ssl = $this->ssl_error();
        if ( $ssl ) {
            return $ssl;
        }

        // Kiosk publik (tanpa login): identitas dari nomor_induk.
        $nomor = sanitize_text_field( (string) $req->get_param( 'nomor_induk' ) );
        if ( '' === $nomor ) {
            return $this->error( 'nomor_kosong', 'Nomor induk wajib diisi.', 422 );
        }

        // Rate-limit anti-abuse per nomor_induk (endpoint tanpa auth). 0 = nonaktif.
        $rl = (int) get_option( 'absensi_selfie_rl_detik', 5 );
        if ( $rl > 0 ) {
            $rl_key = 'absensi_selfie_rl_' . md5( $nomor );
            if ( false !== get_transient( $rl_key ) ) {
                return $this->error( 'terlalu_cepat', 'Terlalu cepat. Tunggu sebentar lalu coba lagi.', 429 );
            }
            set_transient( $rl_key, 1, $rl );
        }

        $user = $this->get_user_by_nomor( $nomor );
        if ( ! $user ) {
            return $this->error( 'nomor_tidak_terdaftar', 'Nomor induk tidak terdaftar.', 404 );
        }

        // Validasi GPS
        $lat = (float) $req->get_param( 'lat' );
        $lng = (float) $req->get_param( 'lng' );

        // Rentang koordinat user harus valid sebelum haversine (cegah hitung sampah)
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
            "SELECT id, waktu_masuk, waktu_keluar FROM {$wpdb->prefix}absensi_rekap WHERE user_id = %d AND tanggal = %s",
            $user->id, $today
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
            do_action( 'absensi_absen_keluar', $user );
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
            $foto_path = FileHelper::save_selfie( $foto_base64, (int) $user->id );
            if ( is_wp_error( $foto_path ) ) {
                return $this->error( 'foto_gagal', $foto_path->get_error_message() );
            }
        }

        // Tentukan status masuk (hadir/telat) – konsisten timezone WP
        $status = $this->tentukan_status_masuk( (int) $user->group_id );

        $inserted = $wpdb->insert(
            $wpdb->prefix . 'absensi_rekap',
            SanitizeHelper::rekap( [
                'user_id'      => $user->id,
                'group_id'     => $user->group_id,
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

        do_action( 'absensi_absen_masuk', $user, $status );

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

        // Cari user (siswa/guru/staff) berdasarkan UID kartu
        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_users WHERE rfid_uid = %s LIMIT 1",
            $uid
        ) );
        if ( ! $user ) {
            return $this->error( 'uid_tidak_terdaftar', "UID $uid tidak terdaftar.", 404 );
        }

        $today = current_time( 'Y-m-d' );
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, waktu_masuk, waktu_keluar FROM {$wpdb->prefix}absensi_rekap WHERE user_id = %d AND tanggal = %s",
            $user->id, $today
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
            do_action( 'absensi_absen_keluar', $user );
            return new \WP_REST_Response( [
                'success' => true,
                'action'  => 'keluar',
                'siswa'   => $user->nama,
                'message' => "Selamat siang, {$user->nama}! Waktu keluar dicatat.",
            ] );
        }

        if ( $existing ) {
            return $this->error( 'sudah_absen', "{$user->nama} sudah absen masuk dan keluar hari ini.", 409 );
        }

        // Tap pertama = catat masuk. Status hadir/telat konsisten timezone WP.
        $status = $this->tentukan_status_masuk( (int) $user->group_id );

        $wpdb->insert(
            $wpdb->prefix . 'absensi_rekap',
            SanitizeHelper::rekap( [
                'user_id'      => $user->id,
                'group_id'     => $user->group_id,
                'tanggal'      => $today,
                'waktu_masuk'  => current_time( 'mysql' ),
                'status'       => $status,
                'mode'         => 'rfid',
                'metode_masuk' => 'rfid',
            ] )
        );

        do_action( 'absensi_absen_masuk', $user, $status );

        return new \WP_REST_Response( [
            'success' => true,
            'action'  => 'masuk',
            'status'  => $status,
            'siswa'   => $user->nama,
            'message' => "Selamat datang, {$user->nama}!",
        ], 201 );
    }

    // ─── Status Absen Hari Ini ─────────────────────────────────────────────────

    public function get_status( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        // Kiosk publik: identitas dari nomor_induk (bukan user WP).
        $nomor = sanitize_text_field( (string) $req->get_param( 'nomor_induk' ) );
        if ( '' === $nomor ) {
            return $this->error( 'nomor_kosong', 'Nomor induk wajib diisi.', 422 );
        }

        $user = $this->get_user_by_nomor( $nomor );
        if ( ! $user ) {
            return $this->error( 'nomor_tidak_terdaftar', 'Nomor induk tidak terdaftar.', 404 );
        }

        // Endpoint publik → balas HANYA field presensi non-sensitif.
        // JANGAN bocorkan lat/lng/jarak/foto_path/bukti_path/catatan (privasi: bisa
        // di-enumerasi siapa pun yang menebak nomor_induk).
        $today = current_time( 'Y-m-d' );
        $rekap = $wpdb->get_row( $wpdb->prepare(
            "SELECT status, waktu_masuk, waktu_keluar, izin_tipe, bukti_status
               FROM {$wpdb->prefix}absensi_rekap WHERE user_id = %d AND tanggal = %s",
            $user->id, $today
        ) );

        return new \WP_REST_Response( [
            'sudah_absen' => (bool) $rekap,
            'nama'        => $user->nama,
            'tanggal'     => $today,
            'rekap'       => $rekap,
        ] );
    }

    // ─── Helper ───────────────────────────────────────────────────────────────

    /**
     * Cari user (siswa/guru/staff) by nomor_induk untuk endpoint kiosk publik.
     * Return objek (id, nama, group_id) atau null bila tak ada. Prepared.
     */
    private function get_user_by_nomor( string $nomor ): ?object {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT id, nama, group_id FROM {$wpdb->prefix}absensi_users WHERE nomor_induk = %s LIMIT 1",
            $nomor
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
    private function tentukan_status_masuk( int $group_id = 0 ): string {
        global $wpdb;
        $tz  = wp_timezone();
        $now = new \DateTimeImmutable( 'now', $tz );

        // Jam masuk dari jadwal group (hari ini); fallback ke option global
        $jam_masuk = '';
        if ( $group_id > 0 ) {
            $hari      = (int) $now->format( 'N' ); // 1=Senin .. 7=Minggu
            $jam_masuk = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT jam_masuk FROM {$wpdb->prefix}absensi_jadwal WHERE group_id = %d AND hari = %d LIMIT 1",
                $group_id, $hari
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

    // ─── Args Validasi ────────────────────────────────────────────────────────

    private function selfie_args(): array {
        return [
            'nomor_induk' => [ 'required' => true,  'type' => 'string', 'maxLength' => 30 ],
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

}
