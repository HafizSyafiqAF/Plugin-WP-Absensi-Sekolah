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
<?php
global $wpdb;
$kelas_list = $wpdb->get_results( "SELECT id, nama_kelas FROM {$wpdb->prefix}absensi_kelas ORDER BY nama_kelas" );
?>
<div class="wrap absensi-admin-wrap">
  <hr class="wp-header-end" style="margin:0;">

  <!-- Page Header -->
  <div class="absensi-page-header">
    <div>
      <h1 class="absensi-page-title"><?php esc_html_e( 'Laporan Absensi', 'absensi-sekolah' ); ?></h1>
      <p class="absensi-page-subtitle"><?php esc_html_e( 'Filter dan ekspor data kehadiran siswa', 'absensi-sekolah' ); ?></p>
    </div>
  </div>

  <!-- Filter Bar -->
  <div x-data="filterBar" class="absensi-filter-bar" style="margin-bottom:20px;">
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">
      <div class="absensi-input-group">
        <label class="absensi-label"><?php esc_html_e( 'Dari Tanggal', 'absensi-sekolah' ); ?></label>
        <input type="date" x-model="filter.dateFrom" class="absensi-input">
      </div>
      <div class="absensi-input-group">
        <label class="absensi-label"><?php esc_html_e( 'Sampai Tanggal', 'absensi-sekolah' ); ?></label>
        <input type="date" x-model="filter.dateTo" class="absensi-input">
      </div>
      <div class="absensi-input-group">
        <label class="absensi-label"><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></label>
        <select x-model="filter.kelas" class="absensi-input" style="min-width:150px;">
          <option value=""><?php esc_html_e( 'Semua Kelas', 'absensi-sekolah' ); ?></option>
          <?php foreach ( $kelas_list as $k ) : ?>
            <option value="<?php echo esc_attr( $k->id ); ?>"><?php echo esc_html( $k->nama_kelas ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;margin-left:auto;align-items:flex-end;">
        <button type="button" @click="reset()" class="absensi-btn absensi-btn-secondary">
          <?php esc_html_e( 'Reset', 'absensi-sekolah' ); ?>
        </button>
        <button type="button" @click="apply()" class="absensi-btn absensi-btn-primary">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <?php esc_html_e( 'Terapkan', 'absensi-sekolah' ); ?>
        </button>
      </div>
    </div>
    <!-- Preset -->
    <div style="display:flex;gap:6px;margin-top:10px;flex-wrap:wrap;align-items:center;">
      <span style="font-size:11.5px;color:#9ca3af;font-weight:600;text-transform:uppercase;letter-spacing:.04em;"><?php esc_html_e( 'Preset:', 'absensi-sekolah' ); ?></span>
      <button type="button" @click="presetHariIni()" class="absensi-preset-btn"><?php esc_html_e( 'Hari Ini', 'absensi-sekolah' ); ?></button>
      <button type="button" @click="presetMingguIni()" class="absensi-preset-btn"><?php esc_html_e( 'Minggu Ini', 'absensi-sekolah' ); ?></button>
      <button type="button" @click="presetBulanIni()" class="absensi-preset-btn"><?php esc_html_e( 'Bulan Ini', 'absensi-sekolah' ); ?></button>
    </div>
  </div>

  <!-- Rekap Table + Summary -->
  <div x-data="rekapTable">
    <!-- Summary Cards -->
    <div class="no-print" style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;">
      <?php
      $cards = [
          [ 'key' => 'hadir',      'label' => __( 'Hadir',      'absensi-sekolah' ), 'color' => '#16A34A', 'bg' => '#DCFCE7' ],
          [ 'key' => 'telat',      'label' => __( 'Telat',      'absensi-sekolah' ), 'color' => '#D97706', 'bg' => '#FEF3C7' ],
          [ 'key' => 'izin_sakit', 'label' => __( 'Izin/Sakit', 'absensi-sekolah' ), 'color' => '#0891B2', 'bg' => '#CFFAFE' ],
          [ 'key' => 'alpha',      'label' => __( 'Alpha',      'absensi-sekolah' ), 'color' => '#DC2626', 'bg' => '#FEE2E2' ],
      ];
      foreach ( $cards as $c ) : ?>
      <div style="background:white;border:1px solid #e5e7eb;border-top:3px solid <?php echo esc_attr( $c['color'] ); ?>;border-radius:10px;padding:14px 16px;">
        <p style="margin:0 0 4px;font-size:26px;font-weight:700;color:<?php echo esc_attr( $c['color'] ); ?>;font-variant-numeric:tabular-nums;line-height:1;" x-text="summary.<?php echo esc_attr( $c['key'] ); ?>">—</p>
        <p style="margin:0;font-size:11px;color:<?php echo esc_attr( $c['color'] ); ?>;font-weight:700;text-transform:uppercase;letter-spacing:.04em;"><?php echo esc_html( $c['label'] ); ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="no-print" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;flex-wrap:wrap;gap:8px;">
      <p style="margin:0;font-size:13px;color:#9ca3af;">
        <?php esc_html_e( 'Data sesuai filter yang dipilih.', 'absensi-sekolah' ); ?>
        <span x-show="rows.length > 0" x-text="'(' + rows.length + ' baris)'" style="font-weight:600;color:#2563EB;"></span>
      </p>
      <!-- Export Menu -->
      <div style="position:relative;" x-data="{ open: false }" @keydown.escape.window="open = false">
        <button type="button" @click="open = !open" :disabled="rows.length === 0"
                class="absensi-btn absensi-btn-secondary" :style="rows.length===0?'opacity:.4;cursor:not-allowed;':''">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
          <?php esc_html_e( 'Ekspor', 'absensi-sekolah' ); ?>
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" :style="open?'transform:rotate(180deg);transition:.2s':''"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
        </button>
        <div x-show="open" x-cloak @click.outside="open=false" class="absensi-dropdown">
          <button type="button" @click="exportServer('xlsx');open=false" class="absensi-dropdown-item">
            <span style="color:#16A34A;font-weight:700;font-size:11px;background:#DCFCE7;padding:1px 5px;border-radius:4px;">XLS</span>
            <?php esc_html_e( 'Excel (.xlsx)', 'absensi-sekolah' ); ?>
          </button>
          <button type="button" @click="exportCSV();open=false" :disabled="exporting" class="absensi-dropdown-item">
            <span style="color:#0891B2;font-weight:700;font-size:11px;background:#CFFAFE;padding:1px 5px;border-radius:4px;">CSV</span>
            <span x-text="exporting ? 'Mengekspor…' : 'CSV'"></span>
          </button>
          <button type="button" @click="exportServer('pdf');open=false" class="absensi-dropdown-item">
            <span style="color:#DC2626;font-weight:700;font-size:11px;background:#FEE2E2;padding:1px 5px;border-radius:4px;">PDF</span>
            <?php esc_html_e( 'PDF Resmi', 'absensi-sekolah' ); ?>
          </button>
          <div style="height:1px;background:#f3f4f6;margin:3px 0;"></div>
          <button type="button" @click="printLaporan();open=false" class="absensi-dropdown-item" style="color:#6b7280;">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
            <?php esc_html_e( 'Cetak', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div x-show="loading" style="text-align:center;padding:56px;color:#9ca3af;font-size:13px;">
      <div style="display:inline-block;width:24px;height:24px;border:2px solid #e5e7eb;border-top-color:#2563EB;border-radius:50%;animation:spin .7s linear infinite;margin-bottom:10px;"></div>
      <p style="margin:0;"><?php esc_html_e( 'Memuat data…', 'absensi-sekolah' ); ?></p>
    </div>

    <!-- Error -->
    <div x-show="!loading && error" style="background:#FEF2F2;border:1px solid #fecaca;border-radius:8px;padding:12px 14px;color:#dc2626;font-size:13px;display:flex;gap:8px;align-items:center;" aria-live="polite">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      <span x-text="error"></span>
    </div>

    <!-- Tabel -->
    <div x-show="!loading && !error" style="background:white;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
      <div style="overflow-x:auto;">
        <table class="absensi-table">
          <thead>
            <tr>
              <th><?php esc_html_e( 'Tanggal', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Siswa', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></th>
              <th><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
            </tr>
          </thead>
          <tbody>
            <template x-for="(row, i) in rows" :key="row.id ?? i">
              <tr>
                <td style="font-family:monospace;font-size:12.5px;color:#9ca3af;white-space:nowrap;" x-text="row.tanggal"></td>
                <td>
                  <span style="font-weight:600;color:#111827;" x-text="row.nama ?? '—'"></span>
                  <span style="font-size:12px;color:#9ca3af;margin-left:5px;font-family:monospace;" x-text="row.nis ?? ''"></span>
                </td>
                <td style="color:#6b7280;font-size:13px;" x-text="row.nama_kelas ?? '—'"></td>
                <td>
                  <template x-if="row.waktu_masuk">
                    <div style="display:flex;flex-direction:column;gap:2px;">
                      <span style="font-family:monospace;font-weight:700;font-size:13.5px;color:#111827;" x-text="fmtTime(row.waktu_masuk)"></span>
                      <span x-show="row.metode_masuk" x-text="row.metode_masuk"
                            style="font-size:11px;padding:1px 6px;border-radius:4px;background:#EFF6FF;color:#2563EB;text-transform:capitalize;width:fit-content;font-weight:600;"></span>
                    </div>
                  </template>
                  <template x-if="!row.waktu_masuk"><span style="color:#d1d5db;">—</span></template>
                </td>
                <td>
                  <template x-if="row.waktu_keluar">
                    <div style="display:flex;flex-direction:column;gap:2px;">
                      <span style="font-family:monospace;font-weight:600;font-size:13.5px;color:#6b7280;" x-text="fmtTime(row.waktu_keluar)"></span>
                      <span x-show="row.metode_keluar" x-text="row.metode_keluar"
                            style="font-size:11px;padding:1px 6px;border-radius:4px;background:#CFFAFE;color:#0891B2;text-transform:capitalize;width:fit-content;font-weight:600;"></span>
                    </div>
                  </template>
                  <template x-if="!row.waktu_keluar"><span style="color:#d1d5db;">—</span></template>
                </td>
                <td>
                  <span :class="statusClass(row.status)" x-text="row.status ?? '—'"
                        style="display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;text-transform:capitalize;"></span>
                </td>
              </tr>
            </template>
            <tr x-show="rows.length === 0 && !loading">
              <td colspan="6" style="padding:56px;text-align:center;color:#9ca3af;">
                <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;color:#d1d5db;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/></svg>
                <p style="margin:0;font-size:14px;font-weight:600;color:#374151;"><?php esc_html_e( 'Tidak Ada Data', 'absensi-sekolah' ); ?></p>
                <p style="margin:4px 0 0;font-size:12px;"><?php esc_html_e( 'Coba ubah filter atau pilih rentang tanggal lain.', 'absensi-sekolah' ); ?></p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div x-show="rows.length > 0" style="padding:10px 16px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;text-align:right;">
        <span x-text="rows.length + ' <?php echo esc_js( __( 'baris ditampilkan', 'absensi-sekolah' ) ); ?>'"></span>
      </div>
    </div>
  </div><!-- /rekapTable -->
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-admin-wrap{font-family:'Plus Jakarta Sans',sans-serif!important;}
.absensi-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:12px 0 24px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;}
.absensi-page-title{font-size:19px;font-weight:700;color:#111827;margin:0 0 3px;}
.absensi-page-subtitle{font-size:13px;color:#6b7280;margin:0;}
.absensi-filter-bar{background:white;border:1px solid #e5e7eb;border-radius:10px;padding:16px 18px;}
.absensi-input-group{display:flex;flex-direction:column;gap:5px;}
.absensi-label{font-size:11.5px;font-weight:600;color:#374151;}
.absensi-input{border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13.5px;min-height:40px;font-family:inherit;background:white;color:#111827;transition:border-color .15s,box-shadow .15s;outline:none;}
.absensi-input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.absensi-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 15px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:38px;font-family:inherit;transition:background .12s;text-decoration:none;}
.absensi-btn-primary{background:#2563EB;color:white;}
.absensi-btn-primary:hover{background:#1D4ED8;}
.absensi-btn-secondary{background:white;color:#374151;border:1px solid #d1d5db;}
.absensi-btn-secondary:hover{background:#f9fafb;border-color:#9ca3af;}
.absensi-preset-btn{padding:4px 12px;border:1px solid #d1d5db;border-radius:6px;background:white;font-size:12.5px;cursor:pointer;min-height:30px;color:#374151;font-family:inherit;font-weight:500;transition:all .12s;}
.absensi-preset-btn:hover{background:#EFF6FF;border-color:#2563EB;color:#2563EB;}
.absensi-dropdown{position:absolute;right:0;top:calc(100% + 6px);background:white;border:1px solid #e5e7eb;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.08);z-index:50;min-width:180px;overflow:hidden;}
.absensi-dropdown-item{display:flex;align-items:center;gap:9px;width:100%;padding:9px 14px;border:none;background:none;cursor:pointer;font-size:13.5px;font-family:inherit;text-align:left;color:#111827;transition:background .1s;}
.absensi-dropdown-item:hover{background:#f9fafb;}
.absensi-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.absensi-table thead tr{background:#f9fafb;border-bottom:1px solid #e5e7eb;}
.absensi-table th{text-align:left;padding:10px 14px;color:#6b7280;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;}
.absensi-table td{padding:11px 14px;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
.absensi-table tbody tr:hover td{background:#f9fafb;}
.status-hadir{background:#DCFCE7;color:#16A34A;}
.status-telat{background:#FEF3C7;color:#D97706;}
.status-alpha{background:#FEE2E2;color:#DC2626;}
.status-izin{background:#CFFAFE;color:#0891B2;}
.status-sakit{background:#CFFAFE;color:#0891B2;}
@keyframes spin{to{transform:rotate(360deg);}}
[x-cloak]{display:none!important;}
@media print{
#adminmenuwrap,#adminmenuback,#adminmenuwrap,#wpadminbar,#wpfooter,
#wpbody-content .notice,.notice,.update-nag,
[x-data="filterBar"],.no-print{display:none!important;}
#wpcontent,#wpbody{margin:0!important;padding:0!important;float:none!important;}
body,.wrap{margin:0!important;font-size:12px;}
.absensi-page-header{border-bottom:2px solid #000;margin-bottom:12px;padding-bottom:8px;}
.absensi-page-title{font-size:16px!important;}
.absensi-page-subtitle{font-size:11px!important;}
.absensi-table{width:100%!important;border-collapse:collapse!important;}
.absensi-table th,.absensi-table td{border:1px solid #999!important;padding:5px 8px!important;font-size:11px!important;}
.absensi-table thead tr{background:#eee!important;}
.absensi-table tbody tr:hover td{background:none!important;}
[style*="border-radius"]{border-radius:0!important;}
}
</style>
