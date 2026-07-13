<?php
namespace Absensi\api;

defined( 'ABSPATH' ) || exit;

use Absensi\helpers\FileHelper;
use Absensi\helpers\KehadiranHelper;

/**
 * REST Endpoint: /wp-json/absensi/v1/laporan
 * Rekap & export absensi (JSON, trigger download Excel/PDF via admin).
 * Skema v2 (users/group). Admin-only (cap manage_options); tanpa scope ortu.
 *
 * ALPHA (v2.2.0): baris "tidak hadir" TIDAK ada di tabel rekap (rekap hanya lahir saat orang
 * tap/selfie). Alpha dihitung KehadiranHelper saat endpoint dipanggil lalu digabung ke hasil —
 * berlaku untuk daftar, summary, DAN export. Baris alpha bertanda `virtual: true` (id = null).
 */
class LaporanEndpoint {

    const NAMESPACE = 'absensi/v1';

    /** Batas baris export (cegah OOM/timeout pada rentang sangat besar). */
    const EXPORT_MAX_ROWS = 50000;

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
            'args'                => $this->summary_args(),
        ] );

        register_rest_route( self::NAMESPACE, '/laporan/export', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [ $this, 'export_laporan' ],
            'permission_callback' => [ $this, 'can_export' ],
            'args'                => [
                'format'   => [ 'type' => 'string', 'enum' => [ 'csv', 'xlsx', 'pdf' ], 'default' => 'csv' ],
                'dari'     => [ 'type' => 'string' ],
                'sampai'   => [ 'type' => 'string' ],
                'preset'   => [ 'type' => 'string', 'enum' => [ 'harian', 'mingguan', 'bulanan' ] ],
                'group_id' => [ 'type' => 'integer' ],
                'tipe'     => [ 'type' => 'string', 'maxLength' => 50 ],
            ],
        ] );
    }

    /**
     * Resolusi rentang tanggal untuk laporan/summary/export.
     * Prioritas: `dari`+`sampai` eksplisit > `preset` > default hari ini.
     * Preset: `harian` (hari ini), `mingguan` (Senin–Minggu pekan ini, ISO),
     * `bulanan` (tgl 1–akhir bulan ini). Pakai wp_timezone() agar konsisten.
     * @return array{0:string,1:string} [dari, sampai] (Y-m-d)
     */
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
                return [
                    $now->modify( 'monday this week' )->format( 'Y-m-d' ),
                    $now->modify( 'sunday this week' )->format( 'Y-m-d' ),
                ];
            case 'bulanan':
                return [ $now->format( 'Y-m-01' ), $now->format( 'Y-m-t' ) ];
            case 'harian':
                return [ $now->format( 'Y-m-d' ), $now->format( 'Y-m-d' ) ];
        }

        // Tanpa preset: pakai param yang ada (jika sebagian), fallback hari ini.
        $today = $now->format( 'Y-m-d' );
        return [ $dari ?: $today, $sampai ?: $today ];
    }

    /**
     * Export laporan ke file download (CSV native / XLSX PhpSpreadsheet / PDF Dompdf).
     * Stream langsung + Content-Disposition: attachment. Cap manage_options.
     * XLSX/PDF butuh `composer install` (vendor/) → 503 bila library tak tersedia.
     */
    public function export_laporan( \WP_REST_Request $req ): \WP_REST_Response {
        $format            = sanitize_text_field( $req->get_param( 'format' ) ) ?: 'csv';
        [ $dari, $sampai ] = $this->resolve_range( $req );
        $group             = absint( $req->get_param( 'group_id' ) );
        $tipe              = trim( sanitize_text_field( (string) $req->get_param( 'tipe' ) ) );

        $rows = $this->query_rows( $dari, $sampai, $group, $tipe );
        $base = "laporan-absensi-{$dari}_sd_{$sampai}";

        switch ( $format ) {
            case 'csv':
                return $this->stream( $this->build_csv( $rows ), 'text/csv; charset=utf-8', "{$base}.csv" );

            case 'xlsx':
                if ( ! class_exists( '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet' ) ) {
                    return $this->error( 'export_unavailable', 'Export Excel butuh PhpSpreadsheet (jalankan composer install).', 503 );
                }
                return $this->stream(
                    $this->build_xlsx( $rows ),
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    "{$base}.xlsx"
                );

            case 'pdf':
                if ( ! class_exists( '\\Dompdf\\Dompdf' ) ) {
                    return $this->error( 'export_unavailable', 'Export PDF butuh Dompdf (jalankan composer install).', 503 );
                }
                return $this->stream( $this->build_pdf( $rows, $dari, $sampai ), 'application/pdf', "{$base}.pdf" );
        }

        return $this->error( 'format_invalid', 'Format harus csv, xlsx, atau pdf.', 422 );
    }

    /** Header kolom export (urut). */
    private function export_columns(): array {
        return [ 'Tanggal', 'Nomor Induk', 'Nama', 'Group', 'Status', 'Waktu Masuk', 'Waktu Keluar', 'Metode Masuk', 'Metode Keluar', 'Jarak (m)' ];
    }

    /** Satu baris rekap → array nilai sesuai export_columns(). */
    private function row_values( object $r ): array {
        return [
            $r->tanggal,
            $r->nomor_induk,
            $r->nama,
            $r->nama_group,
            $r->status,
            $r->waktu_masuk,
            $r->waktu_keluar,
            $r->metode_masuk,
            $r->metode_keluar,
            null === $r->jarak_meter ? '' : (string) $r->jarak_meter,
        ];
    }

    /**
     * Ambil baris laporan untuk export (tanpa paginasi, dibatasi EXPORT_MAX_ROWS).
     * Return array of row objects.
     */
    private function query_rows( string $dari, string $sampai, int $group_id, string $tipe = '' ): array {
        global $wpdb;

        $where_parts = [ $wpdb->prepare( 'r.tanggal BETWEEN %s AND %s', $dari, $sampai ) ];
        if ( $group_id ) {
            $where_parts[] = $wpdb->prepare( 'r.group_id = %d', $group_id );
        }
        if ( '' !== $tipe ) {
            $where_parts[] = $this->tipe_where( $tipe, 'r.group_id' );
        }
        $where = 'WHERE ' . implode( ' AND ', $where_parts );

        $rows = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, u.nama, u.nomor_induk, g.nama AS nama_group
               FROM {$wpdb->prefix}absensi_rekap r
               LEFT JOIN {$wpdb->prefix}absensi_users u ON u.id = r.user_id
               LEFT JOIN {$wpdb->prefix}absensi_group g ON g.id = r.group_id
               $where
               LIMIT %d",
            self::EXPORT_MAX_ROWS
        ) );

        // Export WAJIB ikut memuat alpha — kalau tidak, file yang dikirim ke wali kelas hanya
        // berisi yang hadir, dan yang bolos hilang dari laporan.
        $rows = array_merge( $rows, KehadiranHelper::alpha_rows( $dari, $sampai, $group_id, $tipe ) );
        return array_slice( $this->urutkan( $rows ), 0, self::EXPORT_MAX_ROWS );
    }

    /** Bangun isi CSV (UTF-8 BOM agar Excel kenali). */
    private function build_csv( array $rows ): string {
        $fh = fopen( 'php://temp', 'r+' );
        fputcsv( $fh, $this->export_columns() );
        foreach ( $rows as $r ) {
            fputcsv( $fh, $this->row_values( $r ) );
        }
        rewind( $fh );
        $csv = stream_get_contents( $fh );
        fclose( $fh );
        return "\xEF\xBB\xBF" . $csv; // BOM
    }

    /** Bangun XLSX via PhpSpreadsheet (return binary). Hanya dipanggil bila library ada. */
    private function build_xlsx( array $rows ): string {
        $ss    = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle( 'Laporan Absensi' );
        $sheet->fromArray( $this->export_columns(), null, 'A1' );
        $row_i = 2;
        foreach ( $rows as $r ) {
            $sheet->fromArray( $this->row_values( $r ), null, 'A' . $row_i++ );
        }
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx( $ss );
        ob_start();
        $writer->save( 'php://output' );
        return (string) ob_get_clean();
    }

    /** Bangun PDF via Dompdf (return binary). Hanya dipanggil bila library ada. */
    private function build_pdf( array $rows, string $dari, string $sampai ): string {
        $head = '';
        foreach ( $this->export_columns() as $c ) {
            $head .= '<th>' . esc_html( $c ) . '</th>';
        }
        $body = '';
        foreach ( $rows as $r ) {
            $body .= '<tr>';
            foreach ( $this->row_values( $r ) as $v ) {
                $body .= '<td>' . esc_html( (string) $v ) . '</td>';
            }
            $body .= '</tr>';
        }
        $html = '<html><head><meta charset="utf-8"><style>'
            . 'body{font-family:sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}'
            . 'th,td{border:1px solid #999;padding:3px;text-align:left}th{background:#eee}h2{font-size:14px}'
            . '</style></head><body>'
            . '<h2>Laporan Absensi ' . esc_html( $dari ) . ' s/d ' . esc_html( $sampai ) . '</h2>'
            . '<table><thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>'
            . '</body></html>';

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'landscape' );
        $dompdf->render();
        return (string) $dompdf->output();
    }

    /**
     * Kirim konten sebagai download. Mengeluarkan header + body lalu menghentikan
     * eksekusi (REST tak boleh membungkus JSON). Return value hanya formalitas tipe.
     */
    private function stream( string $content, string $mime, string $filename ): \WP_REST_Response {
        if ( ! headers_sent() ) {
            header( 'Content-Type: ' . $mime );
            header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
            header( 'Content-Length: ' . strlen( $content ) );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'Cache-Control: no-store, no-cache, must-revalidate' );
        }
        echo $content; // phpcs:ignore -- binary/CSV stream, sudah di-escape di build_*
        exit;
    }

    public function get_laporan( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        [ $tanggal_mulai, $tanggal_akhir ] = $this->resolve_range( $req );
        $group_id = absint( $req->get_param( 'group_id' ) );
        $tipe     = trim( sanitize_text_field( (string) $req->get_param( 'tipe' ) ) );
        $per_page = min( absint( $req->get_param( 'per_page' ) ?: 50 ), 200 );
        $page     = max( 1, absint( $req->get_param( 'page' ) ?: 1 ) );
        $offset   = ( $page - 1 ) * $per_page;

        $where_parts = [
            $wpdb->prepare( 'r.tanggal BETWEEN %s AND %s', $tanggal_mulai, $tanggal_akhir ),
        ];
        if ( $group_id ) {
            $where_parts[] = $wpdb->prepare( 'r.group_id = %d', $group_id );
        }
        if ( '' !== $tipe ) {
            $where_parts[] = $this->tipe_where( $tipe, 'r.group_id' );
        }
        $where = 'WHERE ' . implode( ' AND ', $where_parts );

        // Baris rekap NYATA diambil tanpa LIMIT: alpha virtual (KehadiranHelper) harus digabung
        // dulu, baru dipaginasi — kalau LIMIT di SQL, halaman 2 akan melewatkan baris alpha.
        // Dibatasi EXPORT_MAX_ROWS sebagai rem memori.
        $rows = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT r.*, u.nama, u.nomor_induk, g.nama AS nama_group
               FROM {$wpdb->prefix}absensi_rekap r
               LEFT JOIN {$wpdb->prefix}absensi_users u ON u.id = r.user_id
               LEFT JOIN {$wpdb->prefix}absensi_group g ON g.id = r.group_id
               $where
               LIMIT %d",
            self::EXPORT_MAX_ROWS
        ) );

        // izin_tipe + bukti_status ikut via r.*; tambah URL publik bukti.
        foreach ( $rows as $r ) {
            $r->virtual   = false;
            $r->bukti_url = empty( $r->bukti_path ) ? null : FileHelper::file_url( $r->bukti_path );
        }

        // Yang tak punya rekap pada hari aktif → alpha (dihitung, tak ditulis ke DB).
        $rows  = array_merge( $rows, KehadiranHelper::alpha_rows( $tanggal_mulai, $tanggal_akhir, $group_id, $tipe ) );
        $rows  = $this->urutkan( $rows );
        $total = count( $rows );

        return new \WP_REST_Response( [
            'data'       => array_slice( $rows, $offset, $per_page ),
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $per_page,
            'total_page' => (int) ceil( $total / $per_page ),
        ] );
    }

    /** Urut baris laporan: tanggal terbaru dulu, lalu nama A→Z (sama dgn ORDER BY lama). */
    private function urutkan( array $rows ): array {
        usort( $rows, static function ( $a, $b ) {
            $c = strcmp( (string) $b->tanggal, (string) $a->tanggal ); // DESC
            return 0 !== $c ? $c : strcmp( (string) $a->nama, (string) $b->nama );
        } );
        return $rows;
    }

    public function get_summary( \WP_REST_Request $req ): \WP_REST_Response {
        global $wpdb;

        [ $dari, $sampai ] = $this->resolve_range( $req );
        $group_id = absint( $req->get_param( 'group_id' ) );
        $tipe     = trim( sanitize_text_field( (string) $req->get_param( 'tipe' ) ) );

        // WHERE dirakit dari fragmen yang sudah di-prepare.
        $where_parts = [
            $wpdb->prepare( 'tanggal BETWEEN %s AND %s', $dari, $sampai ),
        ];
        if ( $group_id ) {
            $where_parts[] = $wpdb->prepare( 'group_id = %d', $group_id );
        }
        if ( '' !== $tipe ) {
            $where_parts[] = $this->tipe_where( $tipe, 'group_id' ); // query tanpa alias
        }
        $where = 'WHERE ' . implode( ' AND ', $where_parts );

        $summary = $wpdb->get_results(
            "SELECT status, COUNT(*) AS jumlah
               FROM {$wpdb->prefix}absensi_rekap
               $where
               GROUP BY status",
            OBJECT_K
        );

        $hadir = (int) ( $summary['hadir']->jumlah ?? 0 );
        $telat = (int) ( $summary['telat']->jumlah ?? 0 );
        $izin  = (int) ( $summary['izin']->jumlah  ?? 0 );
        $sakit = (int) ( $summary['sakit']->jumlah ?? 0 );
        // Alpha = yang tersimpan di rekap (mis. di-set manual admin) + yang dihitung dari
        // hari aktif tanpa rekap. Tanpa penjumlahan ini, "Alpha" selamanya 0.
        $alpha = (int) ( $summary['alpha']->jumlah ?? 0 )
            + KehadiranHelper::hitung_alpha( $dari, $sampai, $group_id, $tipe );

        return new \WP_REST_Response( [
            'dari'     => $dari,
            'sampai'   => $sampai,
            'group_id' => $group_id ?: null,
            'tanggal'  => $dari === $sampai ? $dari : null, // kompat: field lama saat 1 hari
            'hadir'    => $hadir,
            'telat'    => $telat,
            'izin'     => $izin,
            'sakit'    => $sakit,
            'alpha'    => $alpha,
            'total'    => $hadir + $telat + $izin + $sakit + $alpha,
        ] );
    }

    /** Admin-only (lihat laporan). */
    public function can_view(): bool {
        return current_user_can( 'manage_options' );
    }

    /** Admin-only (export). */
    public function can_export(): bool {
        return current_user_can( 'manage_options' );
    }

    private function error( string $code, string $message, int $status ): \WP_REST_Response {
        return new \WP_REST_Response( [ 'code' => $code, 'message' => $message, 'data' => [ 'status' => $status ] ], $status );
    }

    private function laporan_args(): array {
        return [
            'dari'     => [ 'type' => 'string' ],
            'sampai'   => [ 'type' => 'string' ],
            'preset'   => [ 'type' => 'string', 'enum' => [ 'harian', 'mingguan', 'bulanan' ] ],
            'group_id' => [ 'type' => 'integer' ],
            'tipe'     => [ 'type' => 'string', 'maxLength' => 50 ],
            'per_page' => [ 'type' => 'integer', 'default' => 50 ],
            'page'     => [ 'type' => 'integer', 'default' => 1 ],
        ];
    }

    private function summary_args(): array {
        return [
            'dari'     => [ 'type' => 'string' ],
            'sampai'   => [ 'type' => 'string' ],
            'preset'   => [ 'type' => 'string', 'enum' => [ 'harian', 'mingguan', 'bulanan' ] ],
            'group_id' => [ 'type' => 'integer' ],
            'tipe'     => [ 'type' => 'string', 'maxLength' => 50 ],
        ];
    }

    /**
     * Fragmen WHERE untuk filter tipe group. `tipe` milik `absensi_group`, sedangkan rekap hanya
     * menyimpan `group_id` → dipakai subquery supaya bisa dipasang di query yang JOIN maupun yang
     * tidak (summary query tabel rekap saja). Kolom di-whitelist pemanggil (literal, bukan input user).
     *
     * @param string $tipe  Tipe group (string bebas, sudah disanitasi pemanggil).
     * @param string $kolom `r.group_id` (query ber-alias) atau `group_id` (tanpa alias).
     */
    private function tipe_where( string $tipe, string $kolom ): string {
        global $wpdb;
        return $wpdb->prepare(
            "{$kolom} IN ( SELECT id FROM {$wpdb->prefix}absensi_group WHERE tipe = %s )",
            $tipe
        );
    }
}
