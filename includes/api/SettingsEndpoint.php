<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

/**
 * REST Endpoint: /wp-json/absensi/v1/settings
 *
 * GET  /settings  – baca semua pengaturan (admin)
 * PUT  /settings  – simpan pengaturan (partial), tervalidasi (admin)
 *
 * Storage = wp_options individual `absensi_*` (kontrak K5). Token WA hanya
 * dikembalikan ke admin via GET, TIDAK di-inject ke localize.
 */
class SettingsEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/settings', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_settings' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE, // PUT/POST/PATCH
                'callback'            => [ $this, 'update_settings' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
        ] );
    }

    public function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    /** Map option key → tipe validasi. */
    private function fields(): array {
        return [
            'absensi_lat'           => 'lat',
            'absensi_lng'           => 'lng',
            'absensi_radius'        => 'radius',
            'absensi_jam_masuk'     => 'time',
            'absensi_jam_keluar'    => 'time',
            'absensi_telat_menit'   => 'telat',
            'absensi_akurasi_max'   => 'akurasi',
            'absensi_rfid_debounce' => 'debounce',
            'absensi_retensi_hari'  => 'retensi',
            'absensi_wa_gateway'    => 'url',
            'absensi_wa_token'      => 'text',
        ];
    }

    public function get_settings(): \WP_REST_Response {
        return new \WP_REST_Response( $this->current() );
    }

    private function current(): array {
        return [
            'absensi_lat'           => (float) get_option( 'absensi_lat', 0 ),
            'absensi_lng'           => (float) get_option( 'absensi_lng', 0 ),
            'absensi_radius'        => (int) get_option( 'absensi_radius', 100 ),
            'absensi_jam_masuk'     => (string) get_option( 'absensi_jam_masuk', '07:00' ),
            'absensi_jam_keluar'    => (string) get_option( 'absensi_jam_keluar', '15:00' ),
            'absensi_telat_menit'   => (int) get_option( 'absensi_telat_menit', 15 ),
            'absensi_akurasi_max'   => (int) get_option( 'absensi_akurasi_max', 100 ),
            'absensi_rfid_debounce' => (int) get_option( 'absensi_rfid_debounce', 3 ),
            'absensi_retensi_hari'  => (int) get_option( 'absensi_retensi_hari', 90 ),
            'absensi_wa_gateway'    => (string) get_option( 'absensi_wa_gateway', '' ),
            'absensi_wa_token'      => (string) get_option( 'absensi_wa_token', '' ),
        ];
    }

    public function update_settings( \WP_REST_Request $req ): \WP_REST_Response {
        $params  = $req->get_params();
        $errors  = [];
        $bersih  = [];   // nilai lolos sanitasi — BELUM ditulis

        foreach ( $this->fields() as $key => $type ) {
            if ( ! array_key_exists( $key, $params ) ) {
                continue; // partial update
            }
            [ $ok, $val, $msg ] = $this->sanitize_field( $type, $params[ $key ] );
            if ( ! $ok ) {
                $errors[ $key ] = $msg;
                continue;
            }
            $bersih[ $key ] = $val;
        }

        $errors += $this->validasi_silang( $bersih );

        // ATOMIK: satu field invalid → TIDAK ADA yang ditulis. Dulu update_option dipanggil di
        // dalam loop, jadi field yang valid terlanjur tersimpan meski request dibalas 422 —
        // pengaturan bisa berakhir setengah jadi.
        if ( $errors ) {
            return new \WP_REST_Response( [
                'code'    => 'validasi_gagal',
                'message' => 'Sebagian field tidak valid.',
                'errors'  => $errors,                 // tetap top-level (kompat FE)
                'data'    => [ 'status' => 422, 'errors' => $errors ], // bentuk standar {code,message,data:{status}}
            ], 422 );
        }

        foreach ( $bersih as $key => $val ) {
            update_option( $key, $val );
        }

        return new \WP_REST_Response( [
            'success'  => true,
            'updated'  => $bersih,
            'settings' => $this->current(),
        ] );
    }

    /**
     * Validasi antar-field (tak bisa dilakukan per field sendiri-sendiri).
     *
     * Jam pulang HARUS lebih malam dari jam masuk. Jam terbalik bukan sekadar aneh: mesin alpha
     * memakai jam pulang sebagai penanda "hari sudah selesai", jadi jam pulang < jam masuk membuat
     * semua orang ditandai Alpha SEBELUM sekolah dimulai, dan gate pulang jadi bolong.
     * Shift lintas hari (masuk 22:00, pulang 06:00) belum didukung model data (rekap = 1 baris per
     * tanggal, sesi pulang dicari pada tanggal yang sama) → sengaja ditolak, bukan diam-diam rusak.
     * Aturan ini sama dengan JadwalEndpoint (422 `jam_urutan`) supaya dua halaman tak beda aturan.
     *
     * Update parsial: field yang tak dikirim diambil dari nilai tersimpan.
     */
    private function validasi_silang( array $bersih ): array {
        $masuk  = $bersih['absensi_jam_masuk']  ?? (string) get_option( 'absensi_jam_masuk', '07:00' );
        $keluar = $bersih['absensi_jam_keluar'] ?? (string) get_option( 'absensi_jam_keluar', '15:00' );

        // Hanya berlaku bila salah satunya memang sedang diubah.
        if ( ! isset( $bersih['absensi_jam_masuk'] ) && ! isset( $bersih['absensi_jam_keluar'] ) ) {
            return [];
        }
        if ( '' === $masuk || '' === $keluar || $keluar > $masuk ) {
            return [];
        }

        $pesan = 'Jam pulang harus lebih malam dari jam masuk (shift lintas hari belum didukung).';
        return [
            'absensi_jam_keluar' => $pesan,
            'absensi_jam_masuk'  => $pesan,   // tandai keduanya: admin tak perlu menebak yang mana
        ];
    }

    /** @return array{0:bool,1:mixed,2:string} [ok, value, message] */
    private function sanitize_field( string $type, $raw ): array {
        switch ( $type ) {
            case 'lat':
                $v = (float) $raw;
                return ( $v >= -90 && $v <= 90 ) ? [ true, $v, '' ] : [ false, null, 'Latitude harus -90..90.' ];
            case 'lng':
                $v = (float) $raw;
                return ( $v >= -180 && $v <= 180 ) ? [ true, $v, '' ] : [ false, null, 'Longitude harus -180..180.' ];
            case 'radius':
                return [ true, max( 25, min( 500, (int) $raw ) ), '' ]; // clamp 25..500
            case 'time':
                $s = sanitize_text_field( (string) $raw );
                return preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $s ) ? [ true, $s, '' ] : [ false, null, 'Format jam HH:MM (24 jam).' ];
            case 'telat':
                return [ true, max( 0, min( 240, (int) $raw ) ), '' ];
            case 'akurasi':
                return [ true, max( 1, min( 1000, (int) $raw ) ), '' ];
            case 'debounce':
                // Minimal 1 detik: 0 = anti double-tap MATI. Scanner RFID kerap mengirim UID dua
                // kali dalam hitungan milidetik → tap sekali bisa tercatat masuk + pulang.
                return [ true, max( 1, min( 60, (int) $raw ) ), '' ];
            case 'retensi':
                return [ true, max( 0, min( 3650, (int) $raw ) ), '' ];
            case 'url':
                return [ true, esc_url_raw( (string) $raw ), '' ];
            case 'text':
                return [ true, sanitize_text_field( (string) $raw ), '' ];
        }
        return [ false, null, 'Tipe tidak dikenal.' ];
    }
}
