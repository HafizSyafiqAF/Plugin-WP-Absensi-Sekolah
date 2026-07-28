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

    /**
     * Anti-enumerasi endpoint publik (`/absen/status`, `/absen/selfie`).
     *
     * Nomor induk sekolah berurutan (…044, …045, …050), dan kedua endpoint membedakan
     * "nomor ada" (200) dari "nomor tak ada" (404) → itu oracle: skrip bisa menyapu satu
     * rentang nomor lalu memanen daftar nama siswa + siapa yang hari ini belum masuk.
     *
     * Dua lapis:
     *   1. RL_MAX  — batas kasar hit/menit per IP. Dibuat longgar karena SATU kiosk dipakai
     *      seluruh sekolah (semua siswa keluar dari IP yang sama saat jam masuk) — batas ketat
     *      justru me-DoS sekolahnya sendiri. Patokan: ~0,6 x jumlah siswa per menit, sebab
     *      kedatangan menumpuk di belasan menit sebelum bel dan tiap absen = cek nama + kirim.
     *      600 menampung sekolah ~1.000 siswa; sesuaikan lewat filter untuk sekolah lebih besar.
     *   2. MISS_MAX — nomor asing BERUNTUN per IP. Ini penjaga sesungguhnya: penyapu rentang
     *      hampir selalu 404 → terkunci di awal, sedangkan kiosk asli tak pernah kena karena
     *      satu nomor benar me-reset hitungan DAN tebakan yang saling memperpanjang dianggap
     *      satu orang yang masih mengetik (lihat tandai_nomor_asing()).
     * Semua filterable — deployment padat / tes bisa menyetel.
     */
    const RL_WINDOW   = 60;   // detik, jendela hitung
    const RL_MAX      = 600;  // hit per jendela per IP (~1.000 siswa; lihat catatan di atas)
    const MISS_MAX    = 5;    // nomor asing beruntun sebelum dikunci
    const MISS_LOCK   = 600;  // detik dikunci (10 menit)

    public function register_routes(): void {
        // Kiosk publik (tanpa login): permission terbuka; anti-abuse via rate-limit
        // per nomor_induk di handler. Identitas dari nomor_induk, bukan user WP.
        register_rest_route( self::NAMESPACE, '/absen/selfie', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_selfie' ],
            'permission_callback' => '__return_true',
            'args'                => $this->selfie_args(),
        ] );

        // Kiosk RFID (perangkat guru): WAJIB login + cap absensi_rfid (role guru / admin).
        // Page /absensi/guru sudah login-gated; endpoint ikut auth agar tak bisa di-hit anon.
        // Anti double-tap via debounce transient di handler tetap.
        register_rest_route( self::NAMESPACE, '/absen/rfid', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_rfid' ],
            'permission_callback' => [ $this, 'can_absen_rfid' ],
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

    /**
     * Permission kiosk RFID: wajib login + cap `absensi_rfid` (role guru / administrator).
     * Cookie-auth REST memverifikasi nonce `wp_rest` (header X-WP-Nonce) secara otomatis.
     */
    public function can_absen_rfid(): bool {
        return is_user_logged_in() && current_user_can( \Absensi\Installer::CAP_RFID );
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

        // Gerbang per-IP (anti-enumerasi). Selfie juga membedakan 404 vs 403 → oracle yang
        // sama seperti /absen/status, jadi dijaga gerbang yang sama.
        $gerbang = $this->gerbang_publik();
        if ( $gerbang ) {
            return $gerbang;
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
            return $this->tandai_nomor_asing( $nomor );   // 404, atau 429 bila sudah kebanyakan nebak
        }
        $this->reset_nomor_asing();

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
            // Gate jam: tolak pulang sebelum jam keluar jadwal (tak update baris).
            $gate = $this->gate_jam( (int) $user->group_id, 'pulang' );
            if ( $gate ) {
                return $gate;
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

        // Gate jam: tolak masuk sebelum jam masuk jadwal (tak insert baris).
        $gate = $this->gate_jam( (int) $user->group_id, 'masuk' );
        if ( $gate ) {
            return $gate;
        }

        // Foto selfie WAJIB untuk absen masuk (kebijakan kiosk: bukti kehadiran).
        // Enforce di server (boundary) walau FE juga blokir submit tanpa foto.
        $foto_base64 = $req->get_param( 'foto' );
        if ( empty( $foto_base64 ) ) {
            return $this->error( 'foto_wajib', 'Foto selfie wajib untuk absen masuk.', 422 );
        }
        // Simpan foto selfie (base64) – hanya sesi masuk (skema 1 foto/hari)
        $foto_path = FileHelper::save_selfie( $foto_base64, (int) $user->id, $nomor, 'masuk' );
        if ( is_wp_error( $foto_path ) ) {
            return $this->error( 'foto_gagal', $foto_path->get_error_message() );
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
                'akurasi'      => $akurasi,
                'flag_lokasi'  => $this->deteksi_lokasi_janggal( (int) $user->id, $lat, $lng, $akurasi ),
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
        // Debounce hanya di-ARM setelah rekap SUKSES (lihat arm_debounce di bawah); tap yang
        // DITOLAK (gate jam / 404 / 409) tak arm → retap kasih alasan asli, bukan double_tap
        // yang menutupi (mis. "Belum waktunya absen masuk" ketimbang "Kartu baru saja di-tap").
        $window  = (int) get_option( 'absensi_rfid_debounce', 3 );
        $tap_key = '';
        if ( $window > 0 ) {
            $tap_key = 'absensi_rfid_tap_' . md5( $uid );
            if ( false !== get_transient( $tap_key ) ) {
                return $this->error( 'double_tap', 'Kartu baru saja di-tap, tunggu sebentar.', 429 );
            }
        }
        $arm_debounce = function () use ( &$tap_key, $window ) {
            if ( '' !== $tap_key ) {
                set_transient( $tap_key, time(), $window );
            }
        };

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

        // Tentukan sesi: EKSPLISIT dari kiosk (toggle Masuk/Pulang) atau AUTO by kondisi rekap.
        // Auto: belum ada baris → masuk; sudah masuk belum keluar → pulang; selain itu masuk.
        // (Selaras selfie; `sesi` kosong = backward-compatible dengan perilaku tap lama.)
        $sesi = $req->get_param( 'sesi' );
        if ( ! in_array( $sesi, [ 'masuk', 'pulang' ], true ) ) {
            $sesi = ! $existing ? 'masuk' : ( empty( $existing->waktu_keluar ) ? 'pulang' : 'masuk' );
        }

        // ── Sesi PULANG: catat waktu keluar (update baris hari ini) ──
        if ( 'pulang' === $sesi ) {
            if ( ! $existing ) {
                return $this->error( 'belum_absen_masuk', "{$user->nama} belum absen masuk hari ini.", 409 );
            }
            if ( ! empty( $existing->waktu_keluar ) ) {
                return $this->error( 'sudah_absen_keluar', "{$user->nama} sudah absen pulang hari ini.", 409 );
            }
            // Gate jam: tolak pulang sebelum jam keluar jadwal (tak update baris).
            $gate = $this->gate_jam( (int) $user->group_id, 'pulang' );
            if ( $gate ) {
                return $gate;
            }
            $wpdb->update(
                $wpdb->prefix . 'absensi_rekap',
                SanitizeHelper::rekap( [
                    'waktu_keluar'  => current_time( 'mysql' ),
                    'metode_keluar' => 'rfid',
                ] ),
                [ 'id' => (int) $existing->id ]
            );
            $arm_debounce();
            do_action( 'absensi_absen_keluar', $user );
            return new \WP_REST_Response( [
                'success' => true,
                'action'  => 'keluar',
                'siswa'   => $user->nama,
                'message' => "Selamat siang, {$user->nama}! Waktu keluar dicatat.",
            ] );
        }

        // ── Sesi MASUK: insert baris baru ──
        if ( $existing ) {
            return $this->error( 'sudah_absen', "{$user->nama} sudah absen masuk hari ini.", 409 );
        }

        // Gate jam: tolak masuk sebelum jam masuk jadwal (tak insert baris).
        $gate = $this->gate_jam( (int) $user->group_id, 'masuk' );
        if ( $gate ) {
            return $gate;
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

        $arm_debounce();
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

        // Gerbang per-IP: endpoint ini membalas nama + status kehadiran hari ini, dan 404 vs 200
        // memberi tahu nomor mana yang terdaftar. Tanpa gerbang, satu skrip bisa memanen seluruh
        // daftar nama + siapa yang hari ini tidak masuk.
        $gerbang = $this->gerbang_publik();
        if ( $gerbang ) {
            return $gerbang;
        }

        $user = $this->get_user_by_nomor( $nomor );
        if ( ! $user ) {
            return $this->tandai_nomor_asing( $nomor );   // 404, atau 429 bila sudah kebanyakan nebak
        }
        $this->reset_nomor_asing();

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

    /**
     * Tandai absen yang koordinatnya patut ditinjau manusia. TIDAK menolak absen.
     *
     * Fix satelit sungguhan selalu bergetar beberapa meter, jadi dua pembacaan mustahil sama
     * persis sampai desimal ke-7 (~1 cm). Kalau tetap identik di hari yang berbeda, kemungkinan
     * besar koordinatnya diketik (aplikasi fake GPS), bukan dibaca satelit.
     *
     * Saringan akurasi WAJIB ada supaya siswa jujur tak tertuduh: di dalam gedung ponsel jatuh
     * ke penentuan posisi berbasis WiFi, yang mengembalikan koordinat terdaftar router — satu
     * titik TETAP yang memang identik tiap hari. Pembacaan seperti itu akurasinya lebar
     * (20-100 m), jadi hanya pembacaan ber-akurasi sempit yang layak dicurigai.
     *
     * Sengaja mengembalikan penanda, bukan error: menolak otomatis berarti siswa bersinyal
     * buruk gagal absen, dan itu kerugian yang lebih besar daripada satu-dua pencurang lolos.
     * Foto selfie-nya sudah tersimpan, jadi wali kelas tinggal memeriksa baris bertanda.
     *
     * @return string|null Kode penanda, atau null bila wajar.
     */
    private function deteksi_lokasi_janggal( int $user_id, float $lat, float $lng, float $akurasi ): ?string {
        global $wpdb;

        // Ambang "ini benar-benar dari satelit". Di atas ini anggap posisi WiFi/menara → lewati.
        $ambang = (float) apply_filters( 'absensi_akurasi_satelit', 15.0 );
        if ( $akurasi <= 0 || $akurasi > $ambang ) {
            return null;   // akurasi tak dikirim, atau terlalu lebar untuk disimpulkan
        }

        // Cocokkan pada 7 desimal — presisi kolom DECIMAL(10,7), setara ~1 cm.
        $sama = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}absensi_rekap
              WHERE user_id = %d
                AND tanggal < %s
                AND lat IS NOT NULL
                AND akurasi IS NOT NULL AND akurasi <= %f
                AND ROUND(lat, 7) = ROUND(%f, 7)
                AND ROUND(lng, 7) = ROUND(%f, 7)",
            $user_id, current_time( 'Y-m-d' ), $ambang, $lat, $lng
        ) );

        return $sama > 0 ? 'koordinat_identik' : null;
    }

    // ─── Anti-enumerasi endpoint publik ───────────────────────────────────────

    /**
     * Identitas pemanggil untuk rate-limit. Di-hash: dipakai sebagai bagian key transient,
     * dan IP mentah tak perlu disimpan.
     *
     * Di belakang proxy/CDN `REMOTE_ADDR` = IP proxy → seluruh sekolah terlihat satu IP.
     * Filter `absensi_client_ip` disediakan untuk deployment semacam itu. Sengaja TIDAK
     * membaca `X-Forwarded-For` secara default: header itu bisa dipalsukan penyerang, dan
     * memercayainya membuat rate-limit ini bisa dilewati cukup dengan mengarang header.
     */
    private function client_ip(): string {
        $ip = apply_filters( 'absensi_client_ip', (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
        return md5( (string) $ip );
    }

    /**
     * Gerbang endpoint publik: tolak bila IP sedang dikunci atau melewati batas hit/menit.
     * @return \WP_REST_Response|null  429 bila ditolak, null bila lolos.
     */
    private function gerbang_publik(): ?\WP_REST_Response {
        $ip = $this->client_ip();

        // Sedang dihukum karena menebak-nebak nomor.
        if ( false !== get_transient( 'absensi_pub_lock_' . $ip ) ) {
            return $this->error( 'terlalu_banyak_percobaan', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.', 429 );
        }

        $max = (int) apply_filters( 'absensi_publik_rl_max', self::RL_MAX );
        if ( $max <= 0 ) {
            return null; // rate-limit dimatikan (dev/tes)
        }

        // Jendela tetap: simpan waktu mulai sendiri, jadi TTL transient yang ter-refresh
        // tiap set_transient() tak diam-diam memanjangkan hukuman.
        $key = 'absensi_pub_rl_' . $ip;
        $box = get_transient( $key );
        if ( ! is_array( $box ) || ( time() - (int) $box['mulai'] ) >= self::RL_WINDOW ) {
            $box = [ 'mulai' => time(), 'n' => 0 ];
        }
        $box['n']++;
        set_transient( $key, $box, self::RL_WINDOW );

        if ( $box['n'] > $max ) {
            return $this->error( 'terlalu_cepat', 'Terlalu banyak permintaan. Tunggu sebentar.', 429 );
        }
        return null;
    }

    /**
     * Catat satu nomor asing (404) dari IP ini. Setelah MISS_MAX beruntun → IP dikunci.
     *
     * Kiosk mencari nama sambil orang mengetik, jadi satu orang menghasilkan RENTETAN 404
     * sebelum nomornya utuh ("2", "20", "202", …). Tanpa penanganan khusus, satu pengetik
     * lambat bisa menembus MISS_MAX dan mengunci IP — yang berarti SELURUH sekolah (semua
     * lewat IP yang sama) tak bisa absen selama MISS_LOCK. NIP 12 digit hampir pasti kena.
     *
     * Aturannya: tebakan yang MEMPERPANJANG tebakan sebelumnya dianggap orang yang sama masih
     * mengetik → hitungan tidak naik, hanya tebakan terakhirnya diperbarui. Penyapu rentang tak
     * terbantu: untuk pindah ke kandidat berikutnya ia harus "mundur" (1000001 → 1000002), dan
     * begitu mundur tebakannya bukan perpanjangan lagi sehingga tetap dihitung. Satu rantai
     * perpanjangan hanya bisa berujung pada satu nomor.
     *
     * @param string $nomor Nomor yang barusan dicoba ('' = tak diketahui → selalu dihitung).
     * @return \WP_REST_Response  429 bila kena kunci, atau 404 biasa.
     */
    private function tandai_nomor_asing( string $nomor = '' ): \WP_REST_Response {
        $ip  = $this->client_ip();
        $max = (int) apply_filters( 'absensi_publik_miss_max', self::MISS_MAX );

        if ( $max > 0 ) {
            $key = 'absensi_pub_miss_' . $ip;
            $box = get_transient( $key );
            // Transient versi lama menyimpan int telanjang — terima supaya upgrade tak error.
            if ( ! is_array( $box ) ) {
                $box = [ 'n' => (int) $box, 'last' => '' ];
            }

            $lanjutan = ( '' !== $nomor && '' !== $box['last'] && $nomor !== $box['last']
                && str_starts_with( $nomor, (string) $box['last'] ) );

            if ( ! $lanjutan ) {
                $box['n']++;
            }
            $box['last'] = $nomor;

            if ( $box['n'] >= $max ) {
                set_transient( 'absensi_pub_lock_' . $ip, 1, (int) apply_filters( 'absensi_publik_lock_detik', self::MISS_LOCK ) );
                delete_transient( $key );
                return $this->error( 'terlalu_banyak_percobaan', 'Terlalu banyak percobaan. Coba lagi beberapa menit lagi.', 429 );
            }
            set_transient( $key, $box, self::MISS_LOCK );
        }
        return $this->error( 'nomor_tidak_terdaftar', 'Nomor induk tidak terdaftar.', 404 );
    }

    /** Nomor benar → hitungan tebakan beruntun IP ini dinolkan (kiosk asli tak pernah kena kunci). */
    private function reset_nomor_asing(): void {
        delete_transient( 'absensi_pub_miss_' . $this->client_ip() );
    }

    private function error( string $code, string $message, int $status = 400, array $extra = [] ): \WP_REST_Response {
        return new \WP_REST_Response(
            array_merge( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $extra ),
            $status
        );
    }

    /**
     * Ambil jam jadwal (`jam_masuk`/`jam_keluar`) group untuk hari ini, fallback option global.
     * Return 'H:i:s' (normalisasi) atau '' bila tak ada. $kolom whitelisted (aman interpolasi).
     */
    private function jam_jadwal( int $group_id, string $kolom ): string {
        global $wpdb;
        if ( ! in_array( $kolom, [ 'jam_masuk', 'jam_keluar' ], true ) ) {
            return '';
        }
        $tz  = wp_timezone();
        $now = new \DateTimeImmutable( 'now', $tz );

        $jam = '';
        if ( $group_id > 0 ) {
            $hari = (int) $now->format( 'N' ); // 1=Senin .. 7=Minggu
            $jam  = (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT {$kolom} FROM {$wpdb->prefix}absensi_jadwal WHERE group_id = %d AND hari = %d LIMIT 1",
                $group_id, $hari
            ) );
        }
        if ( '' === $jam ) {
            $jam = 'jam_masuk' === $kolom
                ? (string) get_option( 'absensi_jam_masuk', '07:00' )
                : (string) get_option( 'absensi_jam_keluar', '15:00' );
        }
        if ( preg_match( '/^\d{1,2}:\d{2}$/', $jam ) ) {
            $jam .= ':00';
        }
        return $jam;
    }

    /**
     * Gate jam jadwal: tolak absen di luar jam.
     *   sesi 'masuk'  → tolak bila now < (jam_masuk - grace)  → 403 belum_waktu_masuk
     *   sesi 'pulang' → tolak bila now < jam_keluar           → 403 belum_waktu_pulang
     * Grace masuk dari option `absensi_dini_menit` (default 0 = strict). Timezone WP.
     *
     * @return \WP_REST_Response|null  error bila ditolak, null bila lolos / jam invalid.
     */
    private function gate_jam( int $group_id, string $sesi ): ?\WP_REST_Response {
        $tz    = wp_timezone();
        $now   = new \DateTimeImmutable( 'now', $tz );
        $kolom = 'pulang' === $sesi ? 'jam_keluar' : 'jam_masuk';
        $jam   = $this->jam_jadwal( $group_id, $kolom );

        $batas = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $now->format( 'Y-m-d' ) . ' ' . $jam, $tz );
        if ( false === $batas ) {
            return null; // jam invalid → jangan blokir (konsisten tentukan_status_masuk)
        }
        $jam_hi = substr( $jam, 0, 5 );

        if ( 'pulang' === $sesi ) {
            if ( $now < $batas ) {
                return $this->error(
                    'belum_waktu_pulang',
                    sprintf( 'Belum waktunya absen pulang. Jadwal pulang jam %s.', $jam_hi ),
                    403,
                    [ 'jam_keluar' => $jam_hi, 'sekarang' => $now->format( 'H:i' ) ]
                );
            }
            return null;
        }

        // Sesi masuk: boleh mulai grace menit sebelum jam masuk.
        $grace = (int) get_option( 'absensi_dini_menit', 0 );
        $buka  = $grace > 0 ? $batas->modify( "-{$grace} minutes" ) : $batas;
        if ( $now < $buka ) {
            return $this->error(
                'belum_waktu_masuk',
                sprintf( 'Belum waktunya absen masuk. Jadwal masuk jam %s.', $jam_hi ),
                403,
                [ 'jam_masuk' => $jam_hi, 'sekarang' => $now->format( 'H:i' ) ]
            );
        }
        return null;
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
            'rfid_uid' => [ 'required' => true,  'type' => 'string', 'maxLength' => 50 ],
            'sesi'     => [ 'required' => false, 'type' => 'string', 'enum' => [ 'masuk', 'pulang' ] ],
        ];
    }

}
