<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/jadwal
 * CRUD jam masuk/keluar per kelas per hari (1=Senin .. 7=Minggu).
 * Dipakai oleh logika telat-by-jadwal (AbsensiEndpoint::tentukan_status_masuk).
 * Pola mengikuti SiswaEndpoint/KelasEndpoint: query $wpdb langsung, cap can_manage.
 */
class JadwalEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/jadwal', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_jadwal' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_jadwal' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->jadwal_args( true ),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/jadwal/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_jadwal' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_jadwal' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->jadwal_args( false ),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_jadwal' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
        ] );
    }

    public function list_jadwal( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $kelas_id = absint( $req->get_param( 'kelas_id' ) );
        $where    = $kelas_id ? $wpdb->prepare( 'WHERE j.kelas_id = %d', $kelas_id ) : '';
        $rows = $wpdb->get_results(
            "SELECT j.*, k.nama_kelas
               FROM {$wpdb->prefix}absensi_jadwal j
               LEFT JOIN {$wpdb->prefix}absensi_kelas k ON k.id = j.kelas_id
               $where
               ORDER BY j.kelas_id ASC, j.hari ASC"
        );
        return new \WP_REST_Response( $rows );
    }

    public function get_jadwal( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_jadwal WHERE id = %d",
            (int) $req->get_param( 'id' )
        ) );
        return $row
            ? new \WP_REST_Response( $row )
            : $this->error( 'jadwal_tidak_ada', 'Jadwal tidak ditemukan.', 404 );
    }

    public function create_jadwal( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::jadwal( $req->get_params() );

        $err = $this->validate( $data, true );
        if ( $err ) {
            return $err;
        }

        // Satu jadwal per (kelas, hari) — cegah duplikat (telat-by-jadwal ambil 1 baris).
        $dup = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}absensi_jadwal WHERE kelas_id = %d AND hari = %d",
            $data['kelas_id'], $data['hari']
        ) );
        if ( $dup ) {
            return $this->error( 'jadwal_duplikat', 'Jadwal untuk kelas & hari ini sudah ada.', 409 );
        }

        $wpdb->insert( $wpdb->prefix . 'absensi_jadwal', $data );
        return new \WP_REST_Response( [ 'id' => (int) $wpdb->insert_id ], 201 );
    }

    public function update_jadwal( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id    = (int) $req->get_param( 'id' );
        $table = $wpdb->prefix . 'absensi_jadwal';

        $current = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
        if ( ! $current ) {
            return $this->error( 'jadwal_tidak_ada', 'Jadwal tidak ditemukan.', 404 );
        }

        $data = SanitizeHelper::jadwal( $req->get_params() );
        if ( empty( $data ) ) {
            return $this->error( 'tak_ada_perubahan', 'Tidak ada field yang diubah.', 422 );
        }

        $err = $this->validate( $data, false );
        if ( $err ) {
            return $err;
        }

        // Cek duplikat (kelas,hari) terhadap baris LAIN, pakai nilai gabungan lama+baru.
        $kelas_id = $data['kelas_id'] ?? (int) $current->kelas_id;
        $hari     = $data['hari']     ?? (int) $current->hari;
        $dup = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM $table WHERE kelas_id = %d AND hari = %d AND id != %d",
            $kelas_id, $hari, $id
        ) );
        if ( $dup ) {
            return $this->error( 'jadwal_duplikat', 'Jadwal untuk kelas & hari ini sudah ada.', 409 );
        }

        $wpdb->update( $table, $data, [ 'id' => $id ] );
        return new \WP_REST_Response( [ 'updated' => true ] );
    }

    public function delete_jadwal( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_jadwal WHERE id = %d", $id ) ) ) {
            return $this->error( 'jadwal_tidak_ada', 'Jadwal tidak ditemukan.', 404 );
        }
        $wpdb->delete( $wpdb->prefix . 'absensi_jadwal', [ 'id' => $id ], [ '%d' ] );
        return new \WP_REST_Response( [ 'deleted' => true ] );
    }

    public function can_manage(): bool {
        $user = wp_get_current_user();
        return ! empty( array_intersect( $user->roles, [ 'administrator', 'absensi_admin', 'guru' ] ) );
    }

    /**
     * Validasi field jadwal. $create = true mewajibkan semua field.
     * Return WP_REST_Response error (422) atau null kalau valid.
     */
    private function validate( array $data, bool $create ): ?\WP_REST_Response {
        global $wpdb;

        // Kelas wajib & harus ada
        if ( $create || isset( $data['kelas_id'] ) ) {
            $kelas_id = $data['kelas_id'] ?? 0;
            if ( ! $kelas_id ) {
                return $this->error( 'kelas_wajib', 'kelas_id wajib diisi.', 422 );
            }
            if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_kelas WHERE id = %d", $kelas_id ) ) ) {
                return $this->error( 'kelas_invalid', 'Kelas tidak ditemukan.', 422 );
            }
        }

        // Hari 1–7
        if ( $create || isset( $data['hari'] ) ) {
            $hari = $data['hari'] ?? 0;
            if ( $hari < 1 || $hari > 7 ) {
                return $this->error( 'hari_invalid', 'Hari harus 1 (Senin) sampai 7 (Minggu).', 422 );
            }
        }

        // Jam valid (normalize_time → '' kalau gagal)
        foreach ( [ 'jam_masuk', 'jam_keluar' ] as $f ) {
            if ( $create || isset( $data[ $f ] ) ) {
                if ( empty( $data[ $f ] ) ) {
                    return $this->error( 'jam_invalid', "Format $f harus HH:MM (mis. 07:00).", 422 );
                }
            }
        }

        // Urutan: jam_keluar > jam_masuk (kalau keduanya ada di payload ini)
        if ( isset( $data['jam_masuk'], $data['jam_keluar'] ) && $data['jam_masuk'] && $data['jam_keluar']
            && $data['jam_keluar'] <= $data['jam_masuk'] ) {
            return $this->error( 'jam_urutan', 'jam_keluar harus lebih besar dari jam_masuk.', 422 );
        }

        return null;
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }

    private function jadwal_args( bool $required ): array {
        return [
            'kelas_id'   => [ 'required' => $required, 'type' => 'integer' ],
            'hari'       => [ 'required' => $required, 'type' => 'integer' ],
            'jam_masuk'  => [ 'required' => $required, 'type' => 'string' ],
            'jam_keluar' => [ 'required' => $required, 'type' => 'string' ],
        ];
    }
}
