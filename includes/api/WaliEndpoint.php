<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

/**
 * REST Endpoint: /wp-json/absensi/v1/wali
 * Kelola relasi wali (orang tua) ↔ siswa (1 ortu : N anak).
 * Dipakai WaliLinker FE di admin/views/siswa.php (R4). Cap can_manage.
 *
 * GET    /wali?wali_user_id=&siswa_id=  – list relasi (+ nama)
 * POST   /wali  {wali_user_id, siswa_id} – hubungkan
 * DELETE /wali/{id}                      – putuskan relasi
 */
class WaliEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/wali', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_wali' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'assign' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => [
                    'wali_user_id' => [ 'required' => true, 'type' => 'integer' ],
                    'siswa_id'     => [ 'required' => true, 'type' => 'integer' ],
                ],
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/wali/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'unassign' ],
            'permission_callback' => [ $this, 'can_manage' ],
        ] );
    }

    public function list_wali( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $where = [];
        if ( $w = absint( $req->get_param( 'wali_user_id' ) ) ) {
            $where[] = $wpdb->prepare( 'w.wali_user_id = %d', $w );
        }
        if ( $s = absint( $req->get_param( 'siswa_id' ) ) ) {
            $where[] = $wpdb->prepare( 'w.siswa_id = %d', $s );
        }
        $sql = "SELECT w.*, s.nama AS siswa_nama, s.nis, s.kelas_id, k.nama_kelas
                  FROM {$wpdb->prefix}absensi_wali w
                  LEFT JOIN {$wpdb->prefix}absensi_siswa s ON s.id = w.siswa_id
                  LEFT JOIN {$wpdb->prefix}absensi_kelas k ON k.id = s.kelas_id";
        if ( $where ) {
            $sql .= ' WHERE ' . implode( ' AND ', $where );
        }
        $sql .= ' ORDER BY w.wali_user_id ASC, s.nama ASC';

        $rows = $wpdb->get_results( $sql );
        foreach ( $rows as $r ) {
            $u = get_userdata( (int) $r->wali_user_id );
            $r->wali_nama  = $u ? $u->display_name : null;
            $r->wali_login = $u ? $u->user_login : null;
        }
        return new \WP_REST_Response( $rows );
    }

    public function assign( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $wali  = absint( $req->get_param( 'wali_user_id' ) );
        $siswa = absint( $req->get_param( 'siswa_id' ) );

        $user = $wali ? get_userdata( $wali ) : false;
        if ( ! $user ) {
            return $this->error( 'wali_invalid', 'User wali tidak ditemukan.', 422 );
        }
        $s = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, nama FROM {$wpdb->prefix}absensi_siswa WHERE id = %d", $siswa
        ) );
        if ( ! $s ) {
            return $this->error( 'siswa_tidak_ditemukan', 'Siswa tidak ditemukan.', 404 );
        }

        $dup = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}absensi_wali WHERE wali_user_id = %d AND siswa_id = %d",
            $wali, $siswa
        ) );
        if ( $dup ) {
            return $this->error( 'sudah_terhubung', 'Relasi wali & siswa ini sudah ada.', 409 );
        }

        $wpdb->insert( $wpdb->prefix . 'absensi_wali', [ 'wali_user_id' => $wali, 'siswa_id' => $siswa ] );

        return new \WP_REST_Response( [
            'id'           => (int) $wpdb->insert_id,
            'wali_user_id' => $wali,
            'wali_nama'    => $user->display_name,
            'siswa_id'     => $siswa,
            'siswa_nama'   => $s->nama,
            'message'      => "{$user->display_name} kini wali dari {$s->nama}.",
        ], 201 );
    }

    public function unassign( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_wali WHERE id = %d", $id ) ) ) {
            return $this->error( 'relasi_tidak_ada', 'Relasi tidak ditemukan.', 404 );
        }
        $wpdb->delete( $wpdb->prefix . 'absensi_wali', [ 'id' => $id ], [ '%d' ] );
        return new \WP_REST_Response( [ 'deleted' => true ] );
    }

    public function can_manage(): bool {
        $user = wp_get_current_user();
        return ! empty( array_intersect( $user->roles, [ 'administrator', 'absensi_admin', 'guru' ] ) );
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }
}
