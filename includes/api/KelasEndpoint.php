<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/kelas
 * CRUD master data kelas (nama, tingkat, guru wali).
 * Pola mengikuti SiswaEndpoint: query $wpdb langsung, cap can_manage.
 */
class KelasEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        // GET  /kelas  – list semua kelas (+ nama guru + jumlah siswa)
        // POST /kelas  – tambah kelas
        register_rest_route( self::NAMESPACE, '/kelas', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_kelas' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_kelas' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->kelas_args( true ),
            ],
        ] );

        // GET    /kelas/{id}  – detail
        // PUT    /kelas/{id}  – update
        // DELETE /kelas/{id}  – hapus
        register_rest_route( self::NAMESPACE, '/kelas/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_kelas' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_kelas' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->kelas_args( false ),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_kelas' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
        ] );
    }

    public function list_kelas( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT k.*, COUNT(s.id) AS jumlah_siswa
               FROM {$wpdb->prefix}absensi_kelas k
               LEFT JOIN {$wpdb->prefix}absensi_siswa s ON s.kelas_id = k.id
               GROUP BY k.id
               ORDER BY k.tingkat ASC, k.nama_kelas ASC"
        );
        foreach ( $rows as $r ) {
            $r->jumlah_siswa = (int) $r->jumlah_siswa;
            $r->guru_nama    = $r->guru_id ? ( get_userdata( (int) $r->guru_id )->display_name ?? null ) : null;
        }
        return new \WP_REST_Response( $rows );
    }

    public function get_kelas( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $kelas = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_kelas WHERE id = %d",
            (int) $req->get_param( 'id' )
        ) );
        if ( ! $kelas ) {
            return $this->error( 'kelas_tidak_ada', 'Kelas tidak ditemukan.', 404 );
        }
        $kelas->guru_nama = $kelas->guru_id ? ( get_userdata( (int) $kelas->guru_id )->display_name ?? null ) : null;
        return new \WP_REST_Response( $kelas );
    }

    public function create_kelas( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::kelas( $req->get_params() );

        if ( empty( $data['nama_kelas'] ) ) {
            return $this->error( 'nama_wajib', 'Nama kelas wajib diisi.', 422 );
        }
        if ( ! empty( $data['guru_id'] ) && ! get_userdata( $data['guru_id'] ) ) {
            return $this->error( 'guru_invalid', 'Guru wali tidak ditemukan.', 422 );
        }

        $wpdb->insert( $wpdb->prefix . 'absensi_kelas', $data );
        return new \WP_REST_Response( [ 'id' => (int) $wpdb->insert_id ], 201 );
    }

    public function update_kelas( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id    = (int) $req->get_param( 'id' );
        $table = $wpdb->prefix . 'absensi_kelas';

        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $id ) ) ) {
            return $this->error( 'kelas_tidak_ada', 'Kelas tidak ditemukan.', 404 );
        }

        $data = SanitizeHelper::kelas( $req->get_params() );
        if ( empty( $data ) ) {
            return $this->error( 'tak_ada_perubahan', 'Tidak ada field yang diubah.', 422 );
        }
        if ( array_key_exists( 'nama_kelas', $data ) && '' === $data['nama_kelas'] ) {
            return $this->error( 'nama_wajib', 'Nama kelas tidak boleh kosong.', 422 );
        }
        if ( ! empty( $data['guru_id'] ) && ! get_userdata( $data['guru_id'] ) ) {
            return $this->error( 'guru_invalid', 'Guru wali tidak ditemukan.', 422 );
        }

        $wpdb->update( $table, $data, [ 'id' => $id ] );
        return new \WP_REST_Response( [ 'updated' => true ] );
    }

    public function delete_kelas( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );

        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_kelas WHERE id = %d", $id ) ) ) {
            return $this->error( 'kelas_tidak_ada', 'Kelas tidak ditemukan.', 404 );
        }

        // Cegah hapus kelas yang masih punya siswa (anti-orphan).
        $jumlah = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}absensi_siswa WHERE kelas_id = %d", $id
        ) );
        if ( $jumlah > 0 ) {
            return $this->error( 'kelas_ada_siswa', "Kelas masih memiliki $jumlah siswa. Pindahkan dulu sebelum hapus.", 409 );
        }

        $wpdb->delete( $wpdb->prefix . 'absensi_kelas', [ 'id' => $id ], [ '%d' ] );
        return new \WP_REST_Response( [ 'deleted' => true ] );
    }

    public function can_manage(): bool {
        $user = wp_get_current_user();
        return ! empty( array_intersect( $user->roles, [ 'administrator', 'absensi_admin', 'guru' ] ) );
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }

    private function kelas_args( bool $required ): array {
        return [
            'nama_kelas' => [ 'required' => $required, 'type' => 'string',  'maxLength' => 100 ],
            'tingkat'    => [ 'required' => false,     'type' => 'integer' ],
            'guru_id'    => [ 'required' => false,     'type' => 'integer' ],
        ];
    }
}
