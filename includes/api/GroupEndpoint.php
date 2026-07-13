<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/group
 * CRUD master data group (skema v2: nama + tipe kelas/guru/staff). Eks-KelasEndpoint.
 * Admin-only (cap manage_options). Tanpa tingkat/guru wali.
 */
class GroupEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        // GET /group – list (+ jumlah user); POST /group – tambah
        register_rest_route( self::NAMESPACE, '/group', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_group' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_group' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->group_args( true ),
            ],
        ] );

        // GET/PUT/DELETE /group/{id}
        register_rest_route( self::NAMESPACE, '/group/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_group' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_group' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->group_args( false ),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_group' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
        ] );
    }

    public function list_group( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT g.*, COUNT(u.id) AS jumlah_user
               FROM {$wpdb->prefix}absensi_group g
               LEFT JOIN {$wpdb->prefix}absensi_users u ON u.group_id = g.id
               GROUP BY g.id
               ORDER BY g.tipe ASC, g.nama ASC"
        );
        foreach ( $rows as $r ) {
            $r->jumlah_user = (int) $r->jumlah_user;
        }
        return new \WP_REST_Response( $rows );
    }

    public function get_group( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $group = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_group WHERE id = %d",
            (int) $req->get_param( 'id' )
        ) );
        return $group
            ? new \WP_REST_Response( $group )
            : $this->error( 'group_tidak_ada', 'Group tidak ditemukan.', 404 );
    }

    public function create_group( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::group( $req->get_params() );
        if ( empty( $data['nama'] ) ) {
            return $this->error( 'nama_wajib', 'Nama group wajib diisi.', 422 );
        }
        // Tipe WAJIB diisi — tak ada preset/default lagi. Dicek dari param MENTAH karena
        // SanitizeHelper masih punya fallback 'kelas' (dipakai auto-create group saat import).
        if ( '' === trim( (string) $req->get_param( 'tipe' ) ) ) {
            return $this->error( 'tipe_wajib', 'Tipe group wajib diisi.', 422 );
        }
        $wpdb->insert( $wpdb->prefix . 'absensi_group', $data );
        return new \WP_REST_Response( [ 'id' => (int) $wpdb->insert_id ], 201 );
    }

    public function update_group( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id    = (int) $req->get_param( 'id' );
        $table = $wpdb->prefix . 'absensi_group';

        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $id ) ) ) {
            return $this->error( 'group_tidak_ada', 'Group tidak ditemukan.', 404 );
        }

        $data = SanitizeHelper::group( $req->get_params() );
        if ( empty( $data ) ) {
            return $this->error( 'tak_ada_perubahan', 'Tidak ada field yang diubah.', 422 );
        }
        if ( array_key_exists( 'nama', $data ) && '' === $data['nama'] ) {
            return $this->error( 'nama_wajib', 'Nama group tidak boleh kosong.', 422 );
        }
        // Tipe wajib bila field-nya ikut dikirim (update parsial tanpa `tipe` tetap boleh).
        $tipe_param = $req->get_param( 'tipe' );
        if ( null !== $tipe_param && '' === trim( (string) $tipe_param ) ) {
            return $this->error( 'tipe_wajib', 'Tipe group tidak boleh kosong.', 422 );
        }

        $wpdb->update( $table, $data, [ 'id' => $id ] );
        return new \WP_REST_Response( [ 'updated' => true ] );
    }

    public function delete_group( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );

        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_group WHERE id = %d", $id ) ) ) {
            return $this->error( 'group_tidak_ada', 'Group tidak ditemukan.', 404 );
        }

        // Cegah hapus group yang masih punya user (anti-orphan).
        $jumlah = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}absensi_users WHERE group_id = %d", $id
        ) );
        if ( $jumlah > 0 ) {
            return $this->error( 'group_ada_user', "Group masih memiliki $jumlah user. Pindahkan dulu sebelum hapus.", 409 );
        }

        $wpdb->delete( $wpdb->prefix . 'absensi_group', [ 'id' => $id ], [ '%d' ] );
        return new \WP_REST_Response( [ 'deleted' => true ] );
    }

    /** Admin-only. */
    public function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }

    private function group_args( bool $required ): array {
        return [
            'nama' => [ 'required' => $required, 'type' => 'string', 'maxLength' => 100 ],
            'tipe' => [ 'required' => false,     'type' => 'string', 'maxLength' => 50 ],
        ];
    }
}
