<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/libur
 * CRUD hari libur (v2.2.0) — tanggal yang TIDAK dihitung kehadiran, jadi tak jadi alpha.
 *
 * Disimpan sebagai RENTANG (tanggal_mulai..tanggal_selesai). Libur sehari = mulai == selesai.
 * Rentang boleh tumpang tindih (libur tetap libur) → tak ada validasi anti-overlap.
 * Admin-only (cap manage_options); kiosk publik tak menyentuh endpoint ini.
 */
class LiburEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        // GET /libur – list (opsional filter rentang dari/sampai); POST /libur – tambah
        register_rest_route( self::NAMESPACE, '/libur', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'list_libur' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => [
                    'dari'   => [ 'required' => false, 'type' => 'string' ],
                    'sampai' => [ 'required' => false, 'type' => 'string' ],
                ],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_libur' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->libur_args( true ),
            ],
        ] );

        // GET/PUT/DELETE /libur/{id}
        register_rest_route( self::NAMESPACE, '/libur/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_libur' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_libur' ],
                'permission_callback' => [ $this, 'can_manage' ],
                'args'                => $this->libur_args( false ),
            ],
            [
                'methods'             => \WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'delete_libur' ],
                'permission_callback' => [ $this, 'can_manage' ],
            ],
        ] );
    }

    /**
     * GET /libur — daftar libur, terbaru dulu.
     * Filter opsional `dari`/`sampai`: ambil libur yang BERSINGGUNGAN dengan rentang itu
     * (bukan yang termuat seluruhnya) — libur semester panjang tetap kelihatan saat
     * melihat satu bulan di tengahnya.
     */
    public function list_libur( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $t = $wpdb->prefix . 'absensi_libur';

        $dari   = SanitizeHelper::normalize_date( $req->get_param( 'dari' ) );
        $sampai = SanitizeHelper::normalize_date( $req->get_param( 'sampai' ) );

        $where = '';
        if ( '' !== $dari && '' !== $sampai ) {
            // bersinggungan: mulai <= sampai AND selesai >= dari
            $where = $wpdb->prepare( 'WHERE tanggal_mulai <= %s AND tanggal_selesai >= %s', $sampai, $dari );
        } elseif ( '' !== $dari ) {
            $where = $wpdb->prepare( 'WHERE tanggal_selesai >= %s', $dari );
        } elseif ( '' !== $sampai ) {
            $where = $wpdb->prepare( 'WHERE tanggal_mulai <= %s', $sampai );
        }

        $rows = $wpdb->get_results( "SELECT * FROM {$t} {$where} ORDER BY tanggal_mulai DESC, id DESC" );
        return new \WP_REST_Response( $rows ?: [] );
    }

    public function get_libur( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_libur WHERE id = %d",
            (int) $req->get_param( 'id' )
        ) );
        return $row
            ? new \WP_REST_Response( $row )
            : $this->error( 'libur_tidak_ada', 'Hari libur tidak ditemukan.', 404 );
    }

    public function create_libur( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $data = SanitizeHelper::libur( $req->get_params() );

        // tanggal_selesai kosong → libur sehari (selesai = mulai).
        if ( empty( $data['tanggal_selesai'] ) ) {
            $data['tanggal_selesai'] = $data['tanggal_mulai'] ?? '';
        }

        $err = $this->validasi( $data, true );
        if ( $err ) {
            return $err;
        }

        if ( ! $wpdb->insert( $wpdb->prefix . 'absensi_libur', $data ) ) {
            return $this->error( 'gagal_simpan', 'Gagal menyimpan hari libur.', 500 );
        }
        return new \WP_REST_Response( [ 'id' => $wpdb->insert_id ], 201 );
    }

    public function update_libur( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id  = (int) $req->get_param( 'id' );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}absensi_libur WHERE id = %d", $id ) );
        if ( ! $row ) {
            return $this->error( 'libur_tidak_ada', 'Hari libur tidak ditemukan.', 404 );
        }

        $data = SanitizeHelper::libur( $req->get_params() );
        if ( empty( $data ) ) {
            return $this->error( 'tak_ada_perubahan', 'Tak ada field yang bisa diperbarui.', 422 );
        }

        // Validasi urutan dilakukan terhadap gabungan nilai lama + baru (update boleh parsial).
        $gabung = [
            'tanggal_mulai'   => $data['tanggal_mulai']   ?? $row->tanggal_mulai,
            'tanggal_selesai' => $data['tanggal_selesai'] ?? $row->tanggal_selesai,
        ];
        $err = $this->validasi( $gabung, false );
        if ( $err ) {
            return $err;
        }

        $wpdb->update( $wpdb->prefix . 'absensi_libur', $data, [ 'id' => $id ] );
        return new \WP_REST_Response( [ 'updated' => true ] );
    }

    public function delete_libur( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );
        if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}absensi_libur WHERE id = %d", $id ) ) ) {
            return $this->error( 'libur_tidak_ada', 'Hari libur tidak ditemukan.', 404 );
        }
        $wpdb->delete( $wpdb->prefix . 'absensi_libur', [ 'id' => $id ], [ '%d' ] );
        return new \WP_REST_Response( [ 'deleted' => true ] );
    }

    /**
     * Validasi tanggal. Sanitizer sudah mengubah tanggal tak masuk akal (mis. 2026-02-31) jadi ''.
     * @param bool $wajib true saat create (tanggal_mulai wajib ada).
     */
    private function validasi( array $data, bool $wajib ): ?\WP_REST_Response {
        $mulai   = (string) ( $data['tanggal_mulai'] ?? '' );
        $selesai = (string) ( $data['tanggal_selesai'] ?? '' );

        if ( $wajib && '' === $mulai ) {
            return $this->error( 'tanggal_invalid', 'tanggal_mulai wajib diisi (format YYYY-MM-DD).', 422 );
        }
        if ( isset( $data['tanggal_mulai'] ) && '' === $mulai ) {
            return $this->error( 'tanggal_invalid', 'tanggal_mulai tidak valid (format YYYY-MM-DD).', 422 );
        }
        if ( isset( $data['tanggal_selesai'] ) && '' === $selesai ) {
            return $this->error( 'tanggal_invalid', 'tanggal_selesai tidak valid (format YYYY-MM-DD).', 422 );
        }
        if ( '' !== $mulai && '' !== $selesai && $selesai < $mulai ) {
            return $this->error( 'rentang_terbalik', 'tanggal_selesai tidak boleh sebelum tanggal_mulai.', 422 );
        }
        return null;
    }

    /** Admin-only. */
    public function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }

    private function libur_args( bool $required ): array {
        return [
            'tanggal_mulai'   => [ 'required' => $required, 'type' => 'string' ],
            'tanggal_selesai' => [ 'required' => false,     'type' => 'string' ],
            'keterangan'      => [ 'required' => false,     'type' => 'string', 'maxLength' => 150 ],
        ];
    }
}
