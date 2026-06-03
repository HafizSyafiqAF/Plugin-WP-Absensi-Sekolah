<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

/**
 * REST Endpoint: /wp-json/absensi/v1/laporan
 * Rekap & export absensi (JSON, trigger download Excel/PDF via admin).
 */
class LaporanEndpoint {

    const NAMESPACE = 'absensi/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/laporan', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_laporan' ],
            'permission_callback' => [ $this, 'can_view' ],
            'args'                => $this->laporan_args(),
        ] );

        register_rest_route( self::NAMESPACE, '/laporan/summary', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'get_summary' ],
            'permission_callback' => [ $this, 'can_view' ],
        ] );
    }

    public function get_laporan( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $tanggal_mulai = sanitize_text_field( $req->get_param( 'dari' ) )   ?: current_time( 'Y-m-d' );
        $tanggal_akhir = sanitize_text_field( $req->get_param( 'sampai' ) ) ?: current_time( 'Y-m-d' );
        $kelas_id      = absint( $req->get_param( 'kelas_id' ) );
        $per_page      = min( absint( $req->get_param( 'per_page' ) ?: 50 ), 200 );
        $page          = max( 1, absint( $req->get_param( 'page' ) ?: 1 ) );
        $offset        = ( $page - 1 ) * $per_page;

        $where_parts = [
            $wpdb->prepare( 'r.tanggal BETWEEN %s AND %s', $tanggal_mulai, $tanggal_akhir ),
        ];
        if ( $kelas_id ) {
            $where_parts[] = $wpdb->prepare( 'r.kelas_id = %d', $kelas_id );
        }

        $where = 'WHERE ' . implode( ' AND ', $where_parts );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, s.nama, s.nis, k.nama_kelas
               FROM {$wpdb->prefix}absensi_rekap r
               LEFT JOIN {$wpdb->prefix}absensi_siswa s ON s.id = r.siswa_id
               LEFT JOIN {$wpdb->prefix}absensi_kelas k ON k.id = r.kelas_id
               $where
               ORDER BY r.tanggal DESC, s.nama ASC
               LIMIT %d OFFSET %d",
            $per_page, $offset
        ) );

        $total = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}absensi_rekap r $where"
        );

        return new \WP_REST_Response( [
            'data'       => $rows,
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_page' => (int) ceil( $total / $per_page ),
        ] );
    }

    public function get_summary( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $today = current_time( 'Y-m-d' );

        $summary = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as jumlah
                   FROM {$wpdb->prefix}absensi_rekap
                   WHERE tanggal = %s
                   GROUP BY status",
                $today
            ),
            OBJECT_K
        );

        return new \WP_REST_Response( [
            'tanggal' => $today,
            'hadir'   => (int) ( $summary['hadir']->jumlah  ?? 0 ),
            'telat'   => (int) ( $summary['telat']->jumlah  ?? 0 ),
            'izin'    => (int) ( $summary['izin']->jumlah   ?? 0 ),
            'sakit'   => (int) ( $summary['sakit']->jumlah  ?? 0 ),
            'alpha'   => (int) ( $summary['alpha']->jumlah  ?? 0 ),
        ] );
    }

    public function can_view(): bool {
        $user = wp_get_current_user();
        return ! empty( array_intersect( $user->roles, [ 'administrator', 'absensi_admin', 'guru', 'orang_tua' ] ) );
    }

    private function laporan_args(): array {
        return [
            'dari'     => [ 'type' => 'string' ],
            'sampai'   => [ 'type' => 'string' ],
            'kelas_id' => [ 'type' => 'integer' ],
            'per_page' => [ 'type' => 'integer', 'default' => 50 ],
            'page'     => [ 'type' => 'integer', 'default' => 1 ],
        ];
    }
}
