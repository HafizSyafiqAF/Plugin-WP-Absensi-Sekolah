<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/siswa
 * CRUD master data siswa & mapping RFID UID.
 */
class SiswaEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        // GET  /siswa          – list semua siswa
        // POST /siswa          – tambah siswa
        register_rest_route( self::NAMESPACE, '/siswa', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_siswa' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_siswa' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->siswa_args( true ),
            ],
        ] );

        // GET    /siswa/{id}  – detail
        // PUT    /siswa/{id}  – update
        // DELETE /siswa/{id}  – hapus
        register_rest_route( self::NAMESPACE, '/siswa/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_siswa' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_siswa' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->siswa_args( false ),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_siswa' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
        ] );

        // POST /siswa/{id}/rfid – set / update RFID UID siswa
        register_rest_route( self::NAMESPACE, '/siswa/(?P<id>\d+)/rfid', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'set_rfid' ],
            'permission_callback' => [ $this, 'can_manage' ],
            'args'                => [
                'rfid_uid' => [ 'required' => true, 'type' => 'string', 'maxLength' => 50 ],
            ],
        ] );
    }

    public function list_siswa( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $kelas_id = absint( $req->get_param( 'kelas_id' ) );
        $where    = $kelas_id ? $wpdb->prepare( 'WHERE s.kelas_id = %d', $kelas_id ) : '';
        $rows     = $wpdb->get_results(
            "SELECT s.*, k.nama_kelas
               FROM {$wpdb->prefix}absensi_siswa s
               LEFT JOIN {$wpdb->prefix}absensi_kelas k ON k.id = s.kelas_id
               $where
               ORDER BY s.nama ASC"
        );
        return new \WP_REST_Response( $rows );
    }

    public function get_siswa( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $siswa = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_siswa WHERE id = %d",
            (int) $req->get_param( 'id' )
        ) );
        return $siswa
            ? new \WP_REST_Response( $siswa )
            : new \WP_REST_Response( [ 'message' => 'Tidak ditemukan.' ], 404 );
    }

    public function create_siswa( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::siswa( $req->get_params() );
        $wpdb->insert( $wpdb->prefix . 'absensi_siswa', $data );
        return new \WP_REST_Response( [ 'id' => $wpdb->insert_id ], 201 );
    }

    public function update_siswa( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::siswa( $req->get_params() );
        $wpdb->update(
            $wpdb->prefix . 'absensi_siswa',
            $data,
            [ 'id' => (int) $req->get_param( 'id' ) ]
        );
        return new \WP_REST_Response( [ 'updated' => true ] );
    }

    public function delete_siswa( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'absensi_siswa',
            [ 'id' => (int) $req->get_param( 'id' ) ],
            [ '%d' ]
        );
        return new \WP_REST_Response( [ 'deleted' => true ] );
    }

    public function set_rfid( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $uid = SanitizeHelper::rfid_uid( $req->get_param( 'rfid_uid' ) );

        // Cek duplikasi UID
        $conflict = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}absensi_siswa WHERE rfid_uid = %s AND id != %d",
            $uid, (int) $req->get_param( 'id' )
        ) );
        if ( $conflict ) {
            return new \WP_REST_Response( [ 'message' => 'UID sudah dipakai siswa lain.' ], 409 );
        }

        $wpdb->update(
            $wpdb->prefix . 'absensi_siswa',
            [ 'rfid_uid' => $uid ],
            [ 'id' => (int) $req->get_param( 'id' ) ],
            [ '%s' ],
            [ '%d' ]
        );
        return new \WP_REST_Response( [ 'rfid_uid' => $uid ] );
    }

    public function can_manage(): bool {
        $user = wp_get_current_user();
        return ! empty( array_intersect( $user->roles, [ 'administrator', 'absensi_admin', 'guru' ] ) );
    }

    private function siswa_args( bool $required ): array {
        return [
            'nis'      => [ 'required' => $required, 'type' => 'string', 'maxLength' => 20 ],
            'nama'     => [ 'required' => $required, 'type' => 'string', 'maxLength' => 150 ],
            'kelas_id' => [ 'required' => $required, 'type' => 'integer' ],
            'user_id'  => [ 'required' => false,     'type' => 'integer' ],
        ];
    }
}
