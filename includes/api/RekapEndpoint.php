<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\SanitizeHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/rekap
 * Koreksi status kehadiran oleh admin (v2.2.0) — mis. Alpha → Izin/Sakit.
 *
 * Baris alpha bersifat VIRTUAL (dihitung KehadiranHelper, tak ada di DB), jadi endpoint ini
 * bekerja secara UPSERT lewat kunci alami (user_id, tanggal) — bukan lewat id baris:
 *   - baris rekap sudah ada  → UPDATE status/catatan
 *   - belum ada (alpha)      → INSERT baris manual
 * FE tak perlu bercabang: selalu kirim user_id + tanggal.
 *
 * DELETE hanya untuk baris buatan admin (`mode = manual`) → mengembalikan baris ke perhitungan
 * otomatis (alpha lagi). Rekap hasil selfie/RFID TIDAK boleh dihapus lewat sini (bukti kehadiran).
 * Admin-only (cap manage_options).
 */
class RekapEndpoint {

    const NAMESPACE = 'absensi/v1';

    /** Status yang boleh di-set admin (subset ENUM rekap). */
    const STATUS_VALID = [ 'hadir', 'telat', 'izin', 'sakit', 'alpha' ];

    public function register_routes(): void {
        // POST /rekap/status — upsert status by (user_id, tanggal)
        register_rest_route( self::NAMESPACE, '/rekap/status', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'set_status' ],
            'permission_callback' => [ $this, 'can_manage' ],
            'args'                => [
                'user_id' => [ 'required' => true,  'type' => 'integer' ],
                'tanggal' => [ 'required' => true,  'type' => 'string' ],
                'status'  => [ 'required' => true,  'type' => 'string', 'enum' => self::STATUS_VALID ],
                'catatan' => [ 'required' => false, 'type' => 'string', 'maxLength' => 500 ],
            ],
        ] );

        // DELETE /rekap/{id} — batalkan penyesuaian manual (kembali ke perhitungan otomatis)
        register_rest_route( self::NAMESPACE, '/rekap/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [ $this, 'delete_rekap' ],
            'permission_callback' => [ $this, 'can_manage' ],
        ] );
    }

    public function set_status( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        $user_id = absint( $req->get_param( 'user_id' ) );
        $tanggal = SanitizeHelper::normalize_date( $req->get_param( 'tanggal' ) );
        $status  = (string) $req->get_param( 'status' );
        $catatan = sanitize_textarea_field( (string) $req->get_param( 'catatan' ) );

        if ( '' === $tanggal ) {
            return $this->error( 'tanggal_invalid', 'Tanggal tidak valid (format YYYY-MM-DD).', 422 );
        }
        if ( ! in_array( $status, self::STATUS_VALID, true ) ) {
            return $this->error( 'status_invalid', 'Status tidak dikenal.', 422 );
        }

        $user = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, group_id FROM {$wpdb->prefix}absensi_users WHERE id = %d",
            $user_id
        ) );
        if ( ! $user ) {
            return $this->error( 'user_tidak_ditemukan', 'User tidak ditemukan.', 404 );
        }

        $rekap = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}absensi_rekap WHERE user_id = %d AND tanggal = %s",
            $user_id, $tanggal
        ) );

        if ( $rekap ) {
            // Koreksi baris yang sudah ada — jam masuk/keluar & bukti selfie TIDAK diutak-atik,
            // yang berubah hanya status + catatan (audit kehadiran tetap utuh).
            $wpdb->update(
                $wpdb->prefix . 'absensi_rekap',
                [ 'status' => $status, 'catatan' => $catatan ],
                [ 'id' => (int) $rekap->id ],
                [ '%s', '%s' ],
                [ '%d' ]
            );
            return new \WP_REST_Response( [
                'id'      => (int) $rekap->id,
                'created' => false,
                'status'  => $status,
            ] );
        }

        // Belum ada baris (alpha virtual) → tulis baris manual.
        $data = SanitizeHelper::rekap( [
            'user_id'  => $user_id,
            'group_id' => (int) $user->group_id,
            'status'   => $status,
            'mode'     => 'manual',
        ] );
        $data['tanggal'] = $tanggal;
        $data['catatan'] = $catatan;

        if ( ! $wpdb->insert( $wpdb->prefix . 'absensi_rekap', $data ) ) {
            return $this->error( 'gagal_simpan', 'Gagal menyimpan status.', 500 );
        }
        return new \WP_REST_Response( [
            'id'      => (int) $wpdb->insert_id,
            'created' => true,
            'status'  => $status,
        ], 201 );
    }

    /**
     * Hapus baris rekap MANUAL (buatan admin) → baris kembali dihitung otomatis (alpha lagi).
     * Baris hasil selfie/RFID ditolak: itu bukti kehadiran, bukan penyesuaian.
     */
    public function delete_rekap( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;
        $id = (int) $req->get_param( 'id' );

        $rekap = $wpdb->get_row( $wpdb->prepare(
            "SELECT id, mode FROM {$wpdb->prefix}absensi_rekap WHERE id = %d",
            $id
        ) );
        if ( ! $rekap ) {
            return $this->error( 'rekap_tidak_ada', 'Baris rekap tidak ditemukan.', 404 );
        }
        if ( 'manual' !== $rekap->mode ) {
            return $this->error(
                'bukan_manual',
                'Hanya penyesuaian manual yang bisa dibatalkan. Absensi selfie/RFID adalah bukti kehadiran.',
                409
            );
        }

        $wpdb->delete( $wpdb->prefix . 'absensi_rekap', [ 'id' => $id ], [ '%d' ] );
        return new \WP_REST_Response( [ 'deleted' => true ] );
    }

    /** Admin-only. */
    public function can_manage(): bool {
        return current_user_can( 'manage_options' );
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }
}
