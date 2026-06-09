<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

/**
 * REST Endpoint: /wp-json/absensi/v1/child/logs
 * View-only riwayat absensi anak untuk ORANG TUA login.
 * Hanya anak ter-link via absensi_wali (tutup IDOR). Cap absensi_view_child.
 *
 * GET /child/logs?dari=&sampai=&preset=&siswa_id=
 */
class ChildEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/child/logs', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_logs' ],
            'permission_callback' => [ $this, 'can_view_child' ],
            'args'                => [
                'dari'     => [ 'type' => 'string' ],
                'sampai'   => [ 'type' => 'string' ],
                'preset'   => [ 'type' => 'string', 'enum' => [ 'harian', 'mingguan', 'bulanan' ] ],
                'siswa_id' => [ 'type' => 'integer' ],
            ],
        ] );
    }

    public function get_logs( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        // Anak ter-link untuk ortu login (TIDAK percaya input client untuk daftar anak).
        $anak = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare(
            "SELECT siswa_id FROM {$wpdb->prefix}absensi_wali WHERE wali_user_id = %d",
            get_current_user_id()
        ) ) );

        [ $dari, $sampai ] = $this->resolve_range( $req );

        if ( empty( $anak ) ) {
            return new \WP_REST_Response( [ 'data' => [], 'total' => 0, 'dari' => $dari, 'sampai' => $sampai, 'anak' => [] ] );
        }

        // Filter ke 1 anak spesifik — wajib termasuk anak ter-link (anti-IDOR).
        $target = absint( $req->get_param( 'siswa_id' ) );
        if ( $target ) {
            if ( ! in_array( $target, $anak, true ) ) {
                return $this->error( 'bukan_anak_anda', 'Siswa ini bukan anak Anda.', 403 );
            }
            $scope = [ $target ];
        } else {
            $scope = $anak;
        }

        $in = implode( ',', array_map( 'intval', $scope ) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.id, r.siswa_id, r.tanggal, r.waktu_masuk, r.waktu_keluar, r.status,
                    r.metode_masuk, r.metode_keluar, s.nama, s.nis, k.nama_kelas
               FROM {$wpdb->prefix}absensi_rekap r
               LEFT JOIN {$wpdb->prefix}absensi_siswa s ON s.id = r.siswa_id
               LEFT JOIN {$wpdb->prefix}absensi_kelas k ON k.id = r.kelas_id
              WHERE r.siswa_id IN ($in) AND r.tanggal BETWEEN %s AND %s
              ORDER BY r.tanggal DESC, s.nama ASC",
            $dari, $sampai
        ) );

        return new \WP_REST_Response( [
            'data'   => $rows,
            'total'  => count( $rows ),
            'dari'   => $dari,
            'sampai' => $sampai,
            'anak'   => $scope,
        ] );
    }

    public function can_view_child(): bool {
        return current_user_can( 'absensi_view_child' );
    }

    /** Resolusi rentang: dari+sampai > preset > hari ini. Sama dgn LaporanEndpoint. */
    private function resolve_range( \WP_REST_Request $req ): array {
        $dari   = sanitize_text_field( $req->get_param( 'dari' ) );
        $sampai = sanitize_text_field( $req->get_param( 'sampai' ) );
        if ( $dari && $sampai ) {
            return [ $dari, $sampai ];
        }
        $now    = new \DateTimeImmutable( 'now', wp_timezone() );
        $preset = sanitize_text_field( $req->get_param( 'preset' ) );
        switch ( $preset ) {
            case 'mingguan':
                return [ $now->modify( 'monday this week' )->format( 'Y-m-d' ), $now->modify( 'sunday this week' )->format( 'Y-m-d' ) ];
            case 'bulanan':
                return [ $now->format( 'Y-m-01' ), $now->format( 'Y-m-t' ) ];
            case 'harian':
                return [ $now->format( 'Y-m-d' ), $now->format( 'Y-m-d' ) ];
        }
        $today = $now->format( 'Y-m-d' );
        return [ $dari ?: $today, $sampai ?: $today ];
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }
}
