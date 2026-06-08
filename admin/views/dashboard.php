<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ABSENSI_ADMIN_ASSETS' ) ) :
    define( 'ABSENSI_ADMIN_ASSETS', true ); ?>
<link rel="stylesheet" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/app.css' ); ?>">
<script type="module" src="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/admin.js' ); ?>"></script>
<?php endif; ?>
<script>
if (typeof AbsensiAdmin === 'undefined') {
    window.AbsensiAdmin = {
        restUrl: <?php echo wp_json_encode( rest_url( 'absensi/v1/' ) ); ?>,
        nonce:   <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>
    };
}
</script>
<?php global $wpdb;
$prefix  = $wpdb->prefix;
$today   = current_time( 'Y-m-d' );
$total_siswa = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}absensi_siswa" );

$rekap_hari_ini = $wpdb->get_row( $wpdb->prepare(
    "SELECT
        SUM(status='hadir')             AS hadir,
        SUM(status='telat')             AS telat,
        SUM(status IN('izin','sakit'))  AS izin_sakit,
        SUM(status='alpha')             AS alpha,
        COUNT(*)                        AS total
     FROM {$prefix}absensi_rekap
     WHERE tanggal = %s",
    $today
), ARRAY_A );

$hadir     = (int) ( $rekap_hari_ini['hadir']     ?? 0 );
$telat     = (int) ( $rekap_hari_ini['telat']     ?? 0 );
$izin_sakit= (int) ( $rekap_hari_ini['izin_sakit']?? 0 );
$alpha     = (int) ( $rekap_hari_ini['alpha']     ?? 0 );

$absen_terbaru = $wpdb->get_results( $wpdb->prepare(
    "SELECT r.*, s.nama, s.nis, k.nama_kelas
       FROM {$prefix}absensi_rekap   r
       JOIN {$prefix}absensi_siswa   s ON s.id = r.siswa_id
       LEFT JOIN {$prefix}absensi_kelas k ON k.id = r.kelas_id
      WHERE r.tanggal = %s
      ORDER BY COALESCE(r.waktu_keluar, r.waktu_masuk) DESC
      LIMIT 10",
    $today
) );
?>
<div class="wrap absensi-admin-wrap">
  <hr class="wp-header-end" style="margin:0;">

  <!-- Page Header -->
  <div class="absensi-page-header">
    <div>
      <h1 class="absensi-page-title">
        <?php esc_html_e( 'Dashboard Absensi', 'absensi-sekolah' ); ?>
      </h1>
      <p class="absensi-page-subtitle">
        <?php echo esc_html( wp_date( 'l, d F Y', strtotime( $today ) ) ); ?>
        &nbsp;&middot;&nbsp;
        <?php printf( esc_html__( '%d Siswa Terdaftar', 'absensi-sekolah' ), $total_siswa ); ?>
      </p>
    </div>
  </div>

  <!-- Summary Cards -->
  <div class="absensi-stat-grid">
    <?php
    $cards = [
        [
          'label'  => __( 'Total Siswa',  'absensi-sekolah' ),
          'value'  => $total_siswa,
          'icon'   => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
          'color'  => '#2563EB',
          'bg'     => '#EFF6FF',
          'border' => '#2563EB',
        ],
        [
          'label'  => __( 'Hadir',        'absensi-sekolah' ),
          'value'  => $hadir,
          'icon'   => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
          'color'  => '#16A34A',
          'bg'     => '#DCFCE7',
          'border' => '#16A34A',
        ],
        [
          'label'  => __( 'Telat',        'absensi-sekolah' ),
          'value'  => $telat,
          'icon'   => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
          'color'  => '#D97706',
          'bg'     => '#FEF3C7',
          'border' => '#D97706',
        ],
        [
          'label'  => __( 'Izin / Sakit', 'absensi-sekolah' ),
          'value'  => $izin_sakit,
          'icon'   => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>',
          'color'  => '#0891B2',
          'bg'     => '#CFFAFE',
          'border' => '#0891B2',
        ],
        [
          'label'  => __( 'Alpha',        'absensi-sekolah' ),
          'value'  => $alpha,
          'icon'   => '<svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
          'color'  => '#DC2626',
          'bg'     => '#FEE2E2',
          'border' => '#DC2626',
        ],
    ];
    foreach ( $cards as $c ) : ?>
    <div class="absensi-stat-card" style="border-top:3px solid <?php echo esc_attr( $c['border'] ); ?>;">
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
        <div style="width:38px;height:38px;border-radius:8px;background:<?php echo esc_attr( $c['bg'] ); ?>;color:<?php echo esc_attr( $c['color'] ); ?>;display:flex;align-items:center;justify-content:center;">
          <?php echo $c['icon']; // phpcs:ignore WordPress.Security.EscapeOutput ?>
        </div>
        <span style="font-size:11px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;"><?php echo esc_html( $c['label'] ); ?></span>
      </div>
      <p style="margin:0;font-size:32px;font-weight:700;color:<?php echo esc_attr( $c['color'] ); ?>;line-height:1;font-variant-numeric:tabular-nums;">
        <?php echo esc_html( $c['value'] ); ?>
      </p>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- Aktivitas Terbaru — x-data="dashboardTable" agar fmtTime sama dengan laporan -->
  <div x-data="dashboardTable" style="background:white;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">

    <div style="padding:16px 20px;border-bottom:1px solid #f3f4f6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
      <h2 style="margin:0;font-size:15px;font-weight:700;color:#111827;">
        <?php esc_html_e( 'Aktivitas Terbaru Hari Ini', 'absensi-sekolah' ); ?>
      </h2>
      <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-laporan' ) ); ?>"
         style="font-size:13px;font-weight:600;color:#2563EB;text-decoration:none;display:flex;align-items:center;gap:4px;">
        <?php esc_html_e( 'Lihat semua laporan', 'absensi-sekolah' ); ?>
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      </a>
    </div>

    <?php if ( empty( $absen_terbaru ) ) : ?>
      <div style="display:flex;flex-direction:column;align-items:center;padding:56px 24px;text-align:center;color:#9ca3af;">
        <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;color:#d1d5db;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        <p style="font-size:14px;font-weight:600;color:#374151;margin:0 0 4px;"><?php esc_html_e( 'Belum Ada Aktivitas', 'absensi-sekolah' ); ?></p>
        <p style="font-size:13px;margin:0;"><?php esc_html_e( 'Belum ada data absensi untuk hari ini.', 'absensi-sekolah' ); ?></p>
      </div>
    <?php else : ?>
      <div style="overflow-x:auto;">
        <table class="absensi-table">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Siswa', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ( $absen_terbaru as $row ) :
                $status_class = [
                    'hadir' => 'status-hadir',
                    'telat' => 'status-telat',
                    'alpha' => 'status-alpha',
                    'izin'  => 'status-izin',
                    'sakit' => 'status-sakit',
                ][ $row->status ] ?? '';
                $initials = implode( '', array_slice( array_map( fn($w) => strtoupper( $w[0] ?? '' ), explode( ' ', $row->nama ) ), 0, 2 ) );
                $mode_masuk  = $row->metode_masuk  ?? ( $row->mode ?? null );
                $mode_keluar = $row->metode_keluar ?? null;
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:9px;">
                  <div class="absensi-avatar"><?php echo esc_html( $initials ); ?></div>
                  <div>
                    <p style="margin:0;font-weight:600;font-size:13.5px;color:#111827;"><?php echo esc_html( $row->nama ); ?></p>
                    <p style="margin:0;font-size:12px;color:#9ca3af;font-family:monospace;"><?php echo esc_html( $row->nis ); ?></p>
                  </div>
                </div>
              </td>
              <td style="color:#6b7280;font-size:13px;"><?php echo esc_html( $row->nama_kelas ?? '—' ); ?></td>
              <td>
                <?php if ( $row->waktu_masuk ) : ?>
                  <div style="display:flex;flex-direction:column;gap:2px;">
                    <span style="font-family:monospace;font-weight:700;font-size:13.5px;color:#111827;"
                          x-text="fmtTime('<?php echo esc_attr( $row->waktu_masuk ); ?>')"><?php echo esc_html( substr( $row->waktu_masuk, 11, 5 ) ); ?></span>
                    <?php if ( $mode_masuk ) : ?>
                      <span style="font-size:11px;padding:1px 6px;border-radius:4px;background:#EFF6FF;color:#2563EB;text-transform:capitalize;width:fit-content;font-weight:600;"><?php echo esc_html( $mode_masuk ); ?></span>
                    <?php endif; ?>
                  </div>
                <?php else : ?>
                  <span style="color:#d1d5db;">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ( $row->waktu_keluar ) : ?>
                  <div style="display:flex;flex-direction:column;gap:2px;">
                    <span style="font-family:monospace;font-weight:600;font-size:13.5px;color:#6b7280;"
                          x-text="fmtTime('<?php echo esc_attr( $row->waktu_keluar ); ?>')"><?php echo esc_html( substr( $row->waktu_keluar, 11, 5 ) ); ?></span>
                    <?php if ( $mode_keluar ) : ?>
                      <span style="font-size:11px;padding:1px 6px;border-radius:4px;background:#CFFAFE;color:#0891B2;text-transform:capitalize;width:fit-content;font-weight:600;"><?php echo esc_html( $mode_keluar ); ?></span>
                    <?php endif; ?>
                  </div>
                <?php else : ?>
                  <span style="color:#d1d5db;">—</span>
                <?php endif; ?>
              </td>
              <td>
                <span class="absensi-badge <?php echo esc_attr( $status_class ); ?>" style="display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;text-transform:capitalize;">
                  <?php echo esc_html( ucfirst( $row->status ) ); ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div style="padding:10px 20px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;text-align:right;">
        <?php printf( esc_html__( '%d aktivitas terakhir', 'absensi-sekolah' ), count( $absen_terbaru ) ); ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-admin-wrap{font-family:'Plus Jakarta Sans',sans-serif!important;}
.absensi-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:12px 0 24px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;}
.absensi-page-title{font-size:19px;font-weight:700;color:#111827;margin:0 0 3px;}
.absensi-page-subtitle{font-size:13px;color:#6b7280;margin:0;}
.absensi-stat-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px;}
.absensi-stat-card{background:white;border:1px solid #e5e7eb;border-radius:10px;padding:16px;}
.absensi-avatar{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#EFF6FF;color:#2563EB;font-size:11px;font-weight:700;flex-shrink:0;}
.absensi-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.absensi-table thead tr{background:#f9fafb;border-bottom:1px solid #e5e7eb;}
.absensi-table th{text-align:left;padding:10px 16px;color:#6b7280;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;}
.absensi-table td{padding:11px 16px;border-bottom:1px solid #f3f4f6;color:#111827;vertical-align:middle;}
.absensi-table tbody tr:last-child td{border-bottom:none;}
.absensi-table tbody tr:hover td{background:#f9fafb;}
.absensi-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;white-space:nowrap;}
.status-hadir{background:#DCFCE7;color:#16A34A;}
.status-telat{background:#FEF3C7;color:#D97706;}
.status-alpha{background:#FEE2E2;color:#DC2626;}
.status-izin{background:#CFFAFE;color:#0891B2;}
.status-sakit{background:#CFFAFE;color:#0891B2;}
</style>
