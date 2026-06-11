<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$kelas_list = $wpdb->get_results( "SELECT id, nama_kelas FROM {$wpdb->prefix}absensi_kelas ORDER BY nama_kelas" );
?>

<div class="wrap lp-wrap" id="absensi-laporan-app">

  <div class="lp-bg" aria-hidden="true">
    <div class="lp-blob lp-blob--1"></div>
    <div class="lp-blob lp-blob--2"></div>
    <div class="lp-blob lp-blob--3"></div>
  </div>

  <hr class="wp-header-end" style="margin:0;">

  <!-- ══ HERO CARD ══ -->
  <div class="lp-hero-card">
    <div class="lp-hero-dot-grid" aria-hidden="true"></div>
    <div class="lp-hero-body">
      <div class="lp-hero-left">
        <div class="lp-eyebrow">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6.75v6.75"/></svg>
          <?php esc_html_e( 'Laporan Absensi', 'absensi-sekolah' ); ?>
        </div>
        <h1 class="lp-hero-title">
          <?php esc_html_e( 'Rekap', 'absensi-sekolah' ); ?>
          <span class="lp-gradient-text"><?php esc_html_e( 'Kehadiran', 'absensi-sekolah' ); ?></span>
        </h1>
        <p class="lp-hero-sub"><?php esc_html_e( 'Filter rentang tanggal, kelas, dan ekspor laporan kehadiran siswa ke berbagai format.', 'absensi-sekolah' ); ?></p>
        <div class="lp-hero-chips">
          <span class="lp-chip lp-chip--glass">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
            <?php echo esc_html( wp_date( 'l, j F Y' ) ); ?>
          </span>
          <span class="lp-chip lp-chip--indigo">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            <?php echo esc_html( count( $kelas_list ) ); ?> <?php esc_html_e( 'kelas terdaftar', 'absensi-sekolah' ); ?>
          </span>
        </div>
      </div>
      <div class="lp-hero-right">
        <div class="lp-hero-deco">
          <svg width="88" height="88" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width=".7" style="opacity:.18;color:#4F46E5;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6.75v6.75"/>
          </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ FILTER PANEL ══ -->
  <div x-data="filterBar"
       data-kelas-map='<?php
         $m = new stdClass();
         foreach ( $kelas_list as $k ) { $id = (string) $k->id; $m->$id = $k->nama_kelas; }
         echo wp_json_encode( $m );
       ?>'
       class="lp-panel lp-filter-panel no-print">

    <div class="lp-filter-head">
      <div class="lp-filter-head-icon">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 01-.659 1.591l-5.432 5.432a2.25 2.25 0 00-.659 1.591v2.927a2.25 2.25 0 01-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 00-.659-1.591L3.659 7.409A2.25 2.25 0 013 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z"/></svg>
      </div>
      <div>
        <p class="lp-filter-title"><?php esc_html_e( 'Filter Laporan', 'absensi-sekolah' ); ?></p>
        <p class="lp-filter-sub"><?php esc_html_e( 'Tentukan rentang tanggal dan kelas yang ingin ditampilkan', 'absensi-sekolah' ); ?></p>
      </div>
    </div>

    <div class="lp-filter-body">
      <div class="lp-filter-grid">
        <div class="lp-input-group">
          <label class="lp-label">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
            <?php esc_html_e( 'Dari Tanggal', 'absensi-sekolah' ); ?>
          </label>
          <input type="date" x-model="filter.dateFrom" class="lp-input">
        </div>
        <div class="lp-input-group">
          <label class="lp-label">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
            <?php esc_html_e( 'Sampai Tanggal', 'absensi-sekolah' ); ?>
          </label>
          <input type="date" x-model="filter.dateTo" class="lp-input">
        </div>
        <div class="lp-input-group">
          <label class="lp-label">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
            <?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?>
          </label>
          <div style="position:relative;">
            <button type="button" @click="csOpen=!csOpen" @keydown.escape="csOpen=false"
                    class="lp-cselect" :class="csOpen ? 'lp-cselect--open' : ''">
              <span x-text="filter.kelas ? (csMap[filter.kelas] || filter.kelas) : '<?php echo esc_js( __( 'Semua Kelas', 'absensi-sekolah' ) ); ?>'"></span>
              <svg class="lp-cselect__chev" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
            </button>
            <div x-show="csOpen" x-cloak @click.outside="csOpen=false" class="lp-cselect-drop">
              <button type="button" @click="filter.kelas='';csOpen=false"
                      class="lp-cselect-opt" :class="filter.kelas==='' ? 'lp-cselect-opt--on':''">
                <?php esc_html_e( 'Semua Kelas', 'absensi-sekolah' ); ?>
              </button>
              <?php foreach ( $kelas_list as $k ) : ?>
              <button type="button"
                      @click="filter.kelas='<?php echo esc_js( (string) $k->id ); ?>';csOpen=false"
                      class="lp-cselect-opt"
                      :class="filter.kelas==='<?php echo esc_js( (string) $k->id ); ?>' ? 'lp-cselect-opt--on':''">
                <?php echo esc_html( $k->nama_kelas ); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="lp-filter-actions">
          <button type="button" @click="reset()" class="lp-btn lp-btn--ghost">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
            <?php esc_html_e( 'Reset', 'absensi-sekolah' ); ?>
          </button>
          <button type="button" @click="apply()" class="lp-btn lp-btn--primary">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 15.803a7.5 7.5 0 0010.607 10.607z"/></svg>
            <?php esc_html_e( 'Terapkan', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>

      <div class="lp-preset-row">
        <span class="lp-preset-label"><?php esc_html_e( 'Preset:', 'absensi-sekolah' ); ?></span>
        <button type="button" @click="presetHariIni()" class="lp-preset" :class="activePreset==='hariIni' ? 'lp-preset--on' : ''"><?php esc_html_e( 'Hari Ini', 'absensi-sekolah' ); ?></button>
        <button type="button" @click="presetMingguIni()" class="lp-preset" :class="activePreset==='mingguIni' ? 'lp-preset--on' : ''"><?php esc_html_e( 'Minggu Ini', 'absensi-sekolah' ); ?></button>
        <button type="button" @click="presetBulanIni()" class="lp-preset" :class="activePreset==='bulanIni' ? 'lp-preset--on' : ''"><?php esc_html_e( 'Bulan Ini', 'absensi-sekolah' ); ?></button>
      </div>
    </div>

  </div><!-- /filterBar -->

  <!-- ══ REKAP TABLE + SUMMARY ══ -->
  <div x-data="rekapTable">

    <!-- Summary Cards -->
    <div class="lp-summary-grid no-print">
      <?php
      $stat_cards = [
          [ 'key' => 'hadir',      'label' => __( 'Hadir',      'absensi-sekolah' ), 'icon_color' => '#16A34A', 'grad_from' => '#DCFCE7', 'grad_to' => '#BBF7D0', 'chip_bg' => 'rgba(220,252,231,.8)', 'chip_color' => '#16A34A',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
          ],
          [ 'key' => 'telat',      'label' => __( 'Telat',      'absensi-sekolah' ), 'icon_color' => '#D97706', 'grad_from' => '#FEF3C7', 'grad_to' => '#FDE68A', 'chip_bg' => 'rgba(254,243,199,.8)', 'chip_color' => '#D97706',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>',
          ],
          [ 'key' => 'izin_sakit', 'label' => __( 'Izin / Sakit','absensi-sekolah' ), 'icon_color' => '#0891B2', 'grad_from' => '#CFFAFE', 'grad_to' => '#A5F3FC', 'chip_bg' => 'rgba(207,250,254,.8)', 'chip_color' => '#0891B2',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/>',
          ],
          [ 'key' => 'alpha',      'label' => __( 'Alpha',      'absensi-sekolah' ), 'icon_color' => '#DC2626', 'grad_from' => '#FEE2E2', 'grad_to' => '#FECACA', 'chip_bg' => 'rgba(254,226,226,.8)', 'chip_color' => '#DC2626',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
          ],
      ];
      foreach ( $stat_cards as $sc ) : ?>
      <div class="lp-stat-card">
        <div class="lp-stat-card__icon" style="background:linear-gradient(145deg,<?php echo esc_attr( $sc['grad_from'] ); ?>,<?php echo esc_attr( $sc['grad_to'] ); ?>);">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:<?php echo esc_attr( $sc['icon_color'] ); ?>;">
            <?php echo $sc['icon']; // already escaped — only SVG path data ?>
          </svg>
        </div>
        <div class="lp-stat-card__body">
          <p class="lp-stat-card__num" style="color:<?php echo esc_attr( $sc['icon_color'] ); ?>;" x-text="summary.<?php echo esc_attr( $sc['key'] ); ?>">—</p>
          <p class="lp-stat-card__label" style="color:<?php echo esc_attr( $sc['icon_color'] ); ?>;"><?php echo esc_html( $sc['label'] ); ?></p>
        </div>
        <div class="lp-stat-card__chip" style="background:<?php echo esc_attr( $sc['chip_bg'] ); ?>;color:<?php echo esc_attr( $sc['chip_color'] ); ?>;">
          <span x-text="rows.length > 0 ? Math.round(summary.<?php echo esc_attr( $sc['key'] ); ?> / rows.length * 100) + '%' : '—'"></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="lp-toolbar no-print">
      <div class="lp-toolbar-left">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="color:#94A3B8;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5"/></svg>
        <span class="lp-toolbar-info">
          <?php esc_html_e( 'Data sesuai filter.', 'absensi-sekolah' ); ?>
          <span x-show="rows.length > 0" class="lp-toolbar-count" x-text="'(' + rows.length + ' <?php echo esc_js( __( 'baris', 'absensi-sekolah' ) ); ?>'+ ')'"></span>
        </span>
      </div>
      <div class="lp-toolbar-right">
        <!-- Export Dropdown -->
        <div style="position:relative;z-index:500;" x-data="{ open: false }" @keydown.escape.window="open = false">
          <button type="button" @click="open = !open" :disabled="rows.length === 0" class="lp-btn lp-btn--export" :class="rows.length===0 ? 'lp-btn--disabled' : ''">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            <?php esc_html_e( 'Ekspor', 'absensi-sekolah' ); ?>
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" :style="open ? 'transform:rotate(180deg);transition:.2s' : 'transition:.2s'"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
          </button>
          <div x-show="open" x-cloak @click.outside="open=false" class="lp-dropdown">
            <button type="button" @click="exportServer('xlsx');open=false" class="lp-dropdown-item">
              <span class="lp-export-badge lp-export-badge--green">XLS</span>
              <?php esc_html_e( 'Excel (.xlsx)', 'absensi-sekolah' ); ?>
            </button>
            <button type="button" @click="exportCSV();open=false" :disabled="exporting" class="lp-dropdown-item">
              <span class="lp-export-badge lp-export-badge--cyan">CSV</span>
              <span x-text="exporting ? '<?php echo esc_js( __( 'Mengekspor…', 'absensi-sekolah' ) ); ?>' : 'CSV'"></span>
            </button>
            <button type="button" @click="exportServer('pdf');open=false" class="lp-dropdown-item">
              <span class="lp-export-badge lp-export-badge--red">PDF</span>
              <?php esc_html_e( 'PDF Resmi', 'absensi-sekolah' ); ?>
            </button>
            <div class="lp-dropdown-sep"></div>
            <button type="button" @click="printLaporan();open=false" class="lp-dropdown-item lp-dropdown-item--muted">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z"/></svg>
              <?php esc_html_e( 'Cetak', 'absensi-sekolah' ); ?>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div x-show="loading" class="lp-state-box lp-state-box--loading">
      <div class="lp-spinner"></div>
      <p><?php esc_html_e( 'Memuat data…', 'absensi-sekolah' ); ?></p>
    </div>

    <!-- Error State -->
    <div x-show="!loading && error" class="lp-state-box lp-state-box--error" aria-live="polite">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
      <span x-text="error"></span>
    </div>

    <!-- Table Panel -->
    <div x-show="!loading && !error" class="lp-panel lp-table-panel">
      <div class="lp-table-scroll">
        <table class="lp-table">
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
            <template x-for="(row, i) in paginatedRows" :key="row.id ?? i">
              <tr class="lp-row">
                <td>
                  <span class="lp-cell-date" x-text="row.tanggal"></span>
                </td>
                <td>
                  <div class="lp-cell-siswa">
                    <div class="lp-cell-avatar" :style="`background:hsl(${parseInt(row.nis??'0')%360||210},46%,88%);color:hsl(${parseInt(row.nis??'0')%360||210},40%,36%)`">
                      <span x-text="(row.nama??'?').charAt(0).toUpperCase()"></span>
                    </div>
                    <div>
                      <span class="lp-cell-nama" x-text="row.nama ?? '—'"></span>
                      <span class="lp-cell-nis" x-text="row.nis ?? ''"></span>
                    </div>
                  </div>
                </td>
                <td>
                  <span class="lp-cell-kelas" x-text="row.nama_kelas ?? '—'"></span>
                </td>
                <td>
                  <template x-if="row.waktu_masuk">
                    <div class="lp-cell-time-wrap">
                      <span class="lp-cell-time lp-cell-time--masuk" x-text="fmtTime(row.waktu_masuk)"></span>
                      <span x-show="row.metode_masuk" x-text="row.metode_masuk" class="lp-mode-badge lp-mode-badge--masuk"></span>
                    </div>
                  </template>
                  <template x-if="!row.waktu_masuk"><span class="lp-cell-empty">—</span></template>
                </td>
                <td>
                  <template x-if="row.waktu_keluar">
                    <div class="lp-cell-time-wrap">
                      <span class="lp-cell-time lp-cell-time--pulang" x-text="fmtTime(row.waktu_keluar)"></span>
                      <span x-show="row.metode_keluar" x-text="row.metode_keluar" class="lp-mode-badge lp-mode-badge--pulang"></span>
                    </div>
                  </template>
                  <template x-if="!row.waktu_keluar"><span class="lp-cell-empty">—</span></template>
                </td>
                <td>
                  <span :class="statusClass(row.status)" x-text="row.status ?? '—'" class="lp-status-badge"></span>
                </td>
              </tr>
            </template>
            <tr x-show="rows.length === 0 && !loading">
              <td colspan="6" class="lp-table-empty">
                <div class="lp-table-empty-inner">
                  <div class="lp-empty-icon">
                    <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v11.25A2.25 2.25 0 006 16.5h2.25M3.75 3h-1.5m1.5 0h16.5m0 0h1.5m-1.5 0v11.25A2.25 2.25 0 0118 16.5h-2.25m-7.5 0h7.5m-7.5 0l-1 3m8.5-3l1 3m0 0l.5 1.5m-.5-1.5h-9.5m0 0l-.5 1.5M9 11.25v1.5M12 9v3.75m3-6.75v6.75"/></svg>
                  </div>
                  <p class="lp-empty-title"><?php esc_html_e( 'Tidak Ada Data', 'absensi-sekolah' ); ?></p>
                  <p class="lp-empty-sub"><?php esc_html_e( 'Coba ubah filter atau pilih rentang tanggal lain.', 'absensi-sekolah' ); ?></p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div x-show="rows.length > 0" class="lp-table-footer">
        <span class="lp-page-info"
              x-text="rows.length <= perPage
                ? rows.length + ' <?php echo esc_js( __( 'rekap', 'absensi-sekolah' ) ); ?>'
                : '<?php echo esc_js( __( 'Menampilkan', 'absensi-sekolah' ) ); ?> ' + ((page-1)*perPage+1) + '–' + Math.min(page*perPage, rows.length) + ' <?php echo esc_js( __( 'dari', 'absensi-sekolah' ) ); ?> ' + rows.length + ' <?php echo esc_js( __( 'rekap', 'absensi-sekolah' ) ); ?>'">
        </span>
        <div x-show="totalPages > 1" class="lp-page-controls">
          <button type="button" @click="page > 1 && page--" :disabled="page === 1"
                  class="lp-page-btn" aria-label="<?php esc_attr_e( 'Sebelumnya', 'absensi-sekolah' ); ?>">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
          </button>
          <template x-for="(p, idx) in pageRange" :key="idx">
            <button type="button"
                    @click="typeof p === 'number' && (page = p)"
                    :disabled="typeof p !== 'number'"
                    class="lp-page-btn"
                    :class="{ 'lp-page-btn--active': p === page, 'lp-page-btn--dots': typeof p !== 'number' }"
                    x-text="p"></button>
          </template>
          <button type="button" @click="page < totalPages && page++" :disabled="page === totalPages"
                  class="lp-page-btn" aria-label="<?php esc_attr_e( 'Selanjutnya', 'absensi-sekolah' ); ?>">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
          </button>
        </div>
      </div>
    </div>

  </div><!-- /rekapTable -->

</div><!-- /lp-wrap -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap');

.lp-wrap *,.lp-wrap *::before,.lp-wrap *::after{box-sizing:border-box;}
[x-cloak]{display:none!important;}
.lp-wrap{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif!important;min-height:100vh;padding-bottom:56px;position:relative;z-index:0;}
body.wp-admin{background:#EAF0F6!important;}
#wpcontent,#wpbody-content,#wpbody{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed!important;}

/* ── Blobs ── */
.lp-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}
.lp-blob{position:absolute;border-radius:50%;filter:blur(140px);opacity:1;}
.lp-blob--1{width:750px;height:750px;top:-180px;left:-120px;background:radial-gradient(circle,rgba(129,140,248,.55) 0%,rgba(99,102,241,.25) 65%,transparent 100%);}
.lp-blob--2{width:700px;height:700px;bottom:-150px;right:-80px;background:radial-gradient(circle,rgba(244,114,182,.50) 0%,rgba(219,39,119,.22) 65%,transparent 100%);}
.lp-blob--3{width:600px;height:600px;top:25%;right:10%;background:radial-gradient(circle,rgba(103,232,249,.52) 0%,rgba(6,182,212,.22) 65%,transparent 100%);}

/* ── Glass panel ── */
.lp-panel{position:relative;z-index:1;background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border-radius:24px;border:1px solid rgba(255,255,255,.75);box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);}

/* ── Hero Card ── */
.lp-hero-card{position:relative;z-index:1;background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border:1px solid rgba(255,255,255,.75);border-radius:24px;margin:14px 0 18px;overflow:hidden;box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);}
.lp-hero-dot-grid{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(37,99,235,.012) 1px,transparent 1px);background-size:22px 22px;pointer-events:none;}
.lp-hero-body{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:28px 32px;position:relative;z-index:1;}
.lp-hero-left{flex:1;}
.lp-hero-right{flex-shrink:0;display:flex;align-items:center;position:relative;z-index:1;}
/* decorative orbs inside hero */
.lp-hero-card::before{content:'';position:absolute;width:200px;height:200px;top:-70px;right:170px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.10) 0%,transparent 70%);filter:blur(35px);pointer-events:none;z-index:0;}
.lp-hero-card::after{content:'';position:absolute;width:150px;height:150px;bottom:-55px;right:80px;border-radius:50%;background:radial-gradient(circle,rgba(124,58,237,.09) 0%,transparent 70%);filter:blur(28px);pointer-events:none;z-index:0;}
.lp-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;color:#2563EB;background:#DBEAFE;padding:5px 11px;border-radius:8px;letter-spacing:.02em;text-transform:uppercase;margin:0 0 12px;border:1px solid rgba(37,99,235,.1);}
.lp-hero-title{font-size:clamp(22px,2.6vw,30px);font-weight:800;color:#1E293B;margin:0 0 8px;letter-spacing:-.5px;line-height:1.15;}
.lp-gradient-text{background:linear-gradient(135deg,#2563EB,#7C3AED);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.lp-hero-sub{font-size:13.5px;color:#64748B;margin:0 0 14px;line-height:1.55;max-width:520px;}
.lp-hero-chips{display:flex;flex-wrap:wrap;gap:7px;}
.lp-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:11.5px;font-weight:600;}
.lp-chip--glass{background:rgba(255,255,255,.6);color:#334155;border:1px solid rgba(255,255,255,.8);}
.lp-chip--indigo{background:rgba(238,242,255,.85);color:#2563EB;border:1px solid rgba(165,180,252,.35);}
.lp-hero-deco{width:100px;height:100px;display:flex;align-items:center;justify-content:center;}

/* ── Filter Panel ── */
.lp-filter-panel{margin-bottom:18px;overflow:visible;z-index:10;}
.lp-filter-head{display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid rgba(0,0,0,.05);background:rgba(255,255,255,.18);}
.lp-filter-head-icon{width:36px;height:36px;border-radius:11px;background:#DBEAFE;display:flex;align-items:center;justify-content:center;color:#2563EB;flex-shrink:0;}
.lp-filter-title{font-size:13.5px;font-weight:700;color:#1E293B;margin:0 0 2px;}
.lp-filter-sub{font-size:11.5px;color:#94A3B8;margin:0;}
.lp-filter-body{padding:18px 20px 16px;}
.lp-filter-grid{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}
.lp-filter-grid > .lp-input-group{flex:1;min-width:160px;}
.lp-filter-actions{display:flex;gap:8px;flex-shrink:0;align-items:flex-end;}
.lp-input-group{display:flex;flex-direction:column;gap:5px;}
.lp-label{display:flex;align-items:center;gap:5px;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;}
#absensi-laporan-app .lp-input{width:100%;background:rgba(255,255,255,.55)!important;border:1px solid rgba(255,255,255,.88)!important;border-radius:12px!important;padding:9px 14px!important;font-size:13.5px;min-height:42px;font-family:'Plus Jakarta Sans',sans-serif;color:#1E293B;outline:none!important;transition:border-color .15s,box-shadow .15s,background .15s;box-shadow:inset 4px 4px 10px rgba(163,177,198,.32),inset -4px -4px 10px rgba(255,255,255,.82)!important;appearance:none;-webkit-appearance:none;}
#absensi-laporan-app .lp-input:focus{border-color:rgba(37,99,235,.35)!important;box-shadow:0 0 0 3px rgba(37,99,235,.07),inset 3px 3px 7px rgba(163,177,198,.22),inset -3px -3px 7px rgba(255,255,255,.82)!important;background:rgba(255,255,255,.88)!important;}
/* Custom Select (kelas) */
#absensi-laporan-app .lp-cselect{width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.88);border-radius:12px;padding:9px 14px;font-size:13.5px;min-height:42px;font-family:'Plus Jakarta Sans',sans-serif;color:#1E293B;outline:none;cursor:pointer;text-align:left;transition:border-color .15s,box-shadow .15s,background .15s;box-shadow:inset 4px 4px 10px rgba(163,177,198,.32),inset -4px -4px 10px rgba(255,255,255,.82);}
#absensi-laporan-app .lp-cselect:hover,#absensi-laporan-app .lp-cselect--open{background:rgba(255,255,255,.85)!important;border-color:rgba(37,99,235,.3)!important;box-shadow:0 0 0 3px rgba(37,99,235,.06),inset 3px 3px 7px rgba(163,177,198,.2),inset -3px -3px 7px rgba(255,255,255,.82)!important;}
.lp-cselect__chev{flex-shrink:0;color:#94A3B8;transition:transform .18s;}
.lp-cselect--open .lp-cselect__chev{transform:rotate(180deg);}
.lp-cselect-drop{position:absolute;left:0;right:0;top:calc(100% + 6px);background:rgba(255,255,255,.92);backdrop-filter:blur(24px) saturate(180%);-webkit-backdrop-filter:blur(24px) saturate(180%);border:1px solid rgba(255,255,255,.94);border-radius:14px;box-shadow:8px 8px 24px rgba(163,177,198,.22),-4px -4px 12px rgba(255,255,255,.7);z-index:9999;overflow:hidden;max-height:240px;overflow-y:auto;scrollbar-width:thin;}
.lp-cselect-opt{display:block;width:100%;padding:10px 14px;border:none;background:none;cursor:pointer;font-size:13.5px;font-family:inherit;text-align:left;color:#1E293B;transition:background .1s;font-weight:500;}
.lp-cselect-opt:hover{background:rgba(238,242,255,.8);}
.lp-cselect-opt--on{background:#DBEAFE;color:#2563EB;font-weight:700;}
.lp-preset-row{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:12px;padding-top:12px;border-top:1px solid rgba(163,177,198,.1);}
.lp-preset-label{font-size:11px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.06em;margin-right:2px;}
.lp-preset{padding:5px 14px;border:1px solid rgba(163,177,198,.28);border-radius:999px;background:rgba(255,255,255,.5);font-size:12px;font-weight:600;cursor:pointer;min-height:30px;color:#475569;font-family:inherit;transition:all .14s;backdrop-filter:blur(6px);}
.lp-preset:hover{background:#DBEAFE;border-color:rgba(37,99,235,.3);color:#2563EB;}
.lp-preset--on{background:rgba(37,99,235,.1)!important;border-color:rgba(37,99,235,.55)!important;color:#2563EB!important;font-weight:700;box-shadow:0 0 0 3px rgba(37,99,235,.12);}

/* ── Buttons ── */
.lp-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 20px;border-radius:999px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:42px;font-family:inherit;transition:all .15s;white-space:nowrap;}
.lp-btn--primary{background:linear-gradient(145deg,#2563EB,#1D4ED8);color:white;box-shadow:4px 4px 14px rgba(37,99,235,.30),-2px -2px 8px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.2);}
.lp-btn--primary:hover{transform:translateY(-2px);box-shadow:6px 6px 20px rgba(37,99,235,.38),-2px -2px 8px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.2);}
.lp-btn--ghost{background:rgba(255,255,255,.65);backdrop-filter:blur(12px);border:1.5px solid rgba(255,255,255,.88);color:#475569;box-shadow:3px 3px 8px rgba(163,177,198,.2),-2px -2px 6px rgba(255,255,255,.8);}
.lp-btn--ghost:hover{background:rgba(255,255,255,.92);color:#1E293B;transform:translateY(-1px);}
.lp-btn--export{background:rgba(255,255,255,.65);backdrop-filter:blur(12px);border:1.5px solid rgba(255,255,255,.88);color:#334155;box-shadow:3px 3px 8px rgba(163,177,198,.2),-2px -2px 6px rgba(255,255,255,.8);}
.lp-btn--export:hover:not(:disabled){background:rgba(255,255,255,.88);color:#0F172A;}
.lp-btn--disabled{opacity:.4;cursor:not-allowed;}

/* ── Summary Cards ── */
.lp-summary-grid{position:relative;z-index:1;display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:16px;}
@media(max-width:900px){.lp-summary-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:500px){.lp-summary-grid{grid-template-columns:1fr 1fr;}}
.lp-stat-card{background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border:1px solid rgba(255,255,255,.75);border-radius:20px;padding:20px 18px 18px;position:relative;z-index:1;display:flex;flex-direction:column;gap:12px;box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);transition:transform .18s,box-shadow .18s;overflow:hidden;}
.lp-stat-card:hover{transform:translateY(-3px);box-shadow:10px 10px 30px rgba(163,177,198,.3),-10px -10px 30px rgba(255,255,255,.9),inset 0 1px 1px rgba(255,255,255,.8);}
.lp-stat-card__icon{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;box-shadow:2px 2px 8px rgba(163,177,198,.15),-1px -1px 4px rgba(255,255,255,.9);}
.lp-stat-card__body{flex:1;}
.lp-stat-card__num{font-size:36px;font-weight:800;line-height:1;margin:0 0 4px;font-variant-numeric:tabular-nums;letter-spacing:-1px;font-family:'Plus Jakarta Sans',sans-serif;}
.lp-stat-card__label{font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin:0;opacity:.75;}
.lp-stat-card__chip{align-self:flex-start;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;margin-top:-4px;}

/* ── Toolbar ── */
.lp-toolbar{position:relative;z-index:200;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:12px;}
.lp-toolbar-left{display:flex;align-items:center;gap:8px;font-size:13px;color:#64748B;}
.lp-toolbar-count{font-weight:700;color:#4F46E5;}
.lp-toolbar-right{display:flex;align-items:center;gap:8px;}

/* ── Dropdown ── */
.lp-dropdown{position:absolute;right:0;top:calc(100% + 8px);background:rgba(255,255,255,.88);backdrop-filter:blur(20px) saturate(150%);-webkit-backdrop-filter:blur(20px) saturate(150%);border:1px solid rgba(255,255,255,.9);border-radius:14px;box-shadow:8px 8px 24px rgba(163,177,198,.22),-4px -4px 12px rgba(255,255,255,.7);z-index:9999;min-width:190px;overflow:hidden;}
.lp-dropdown-item{display:flex;align-items:center;gap:10px;width:100%;padding:10px 14px;border:none;background:none;cursor:pointer;font-size:13.5px;font-family:inherit;text-align:left;color:#1E293B;transition:background .1s;font-weight:500;}
.lp-dropdown-item:hover{background:rgba(238,242,255,.7);}
.lp-dropdown-item--muted{color:#64748B;}
.lp-dropdown-item--muted:hover{background:rgba(241,245,249,.7);}
.lp-dropdown-sep{height:1px;background:rgba(163,177,198,.15);margin:3px 0;}
.lp-export-badge{font-size:10px;font-weight:800;padding:2px 7px;border-radius:5px;letter-spacing:.04em;flex-shrink:0;}
.lp-export-badge--green{background:rgba(220,252,231,.9);color:#16A34A;}
.lp-export-badge--cyan{background:rgba(207,250,254,.9);color:#0891B2;}
.lp-export-badge--red{background:rgba(254,226,226,.9);color:#DC2626;}

/* ── State boxes ── */
.lp-state-box{position:relative;z-index:1;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;padding:60px 24px;text-align:center;font-size:13px;border-radius:22px;margin-bottom:18px;}
.lp-state-box--loading{background:rgba(255,255,255,.35);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.65);color:#64748B;}
.lp-state-box--error{background:rgba(254,242,242,.75);backdrop-filter:blur(16px);border:1px solid rgba(252,165,165,.35);color:#DC2626;flex-direction:row;padding:14px 18px;}
.lp-spinner{width:26px;height:26px;border:2.5px solid rgba(163,177,198,.3);border-top-color:#2563EB;border-radius:50%;animation:lp-spin .7s linear infinite;}
@keyframes lp-spin{to{transform:rotate(360deg);}}

/* ── Table Panel ── */
.lp-table-panel{overflow:hidden;margin-bottom:0;}
.lp-table-scroll{overflow-x:auto;scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.07) transparent;}
.lp-table-scroll::-webkit-scrollbar{height:4px;}
.lp-table-scroll::-webkit-scrollbar-thumb{background:rgba(0,0,0,.07);border-radius:99px;}
.lp-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.lp-table thead tr{background:rgba(219,234,254,.5);border-bottom:1.5px solid rgba(147,197,253,.3);}
.lp-table th{text-align:left;padding:12px 16px;color:#2563EB;font-weight:700;font-size:10.5px;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;}
.lp-table td{padding:12px 16px;border-bottom:1px solid rgba(0,0,0,.05);vertical-align:middle;}
.lp-row:last-child td{border-bottom:none;}
.lp-row{transition:background .12s,transform .15s ease;}
.lp-row:hover td{background:rgba(255,255,255,.5);}
.lp-row:hover{transform:translateY(-0.5px);}
.lp-cell-date{font-family:'JetBrains Mono',monospace;font-size:12.5px;color:#94A3B8;letter-spacing:.02em;white-space:nowrap;}
.lp-cell-siswa{display:flex;align-items:center;gap:10px;}
.lp-cell-avatar{width:36px;height:36px;border-radius:11px;font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:2px 2px 5px rgba(163,177,198,.14);}
.lp-cell-nama{display:block;font-weight:700;color:#1E293B;font-size:13.5px;}
.lp-cell-nis{display:block;font-size:11.5px;color:#94A3B8;font-family:'JetBrains Mono',monospace;letter-spacing:.03em;}
.lp-cell-kelas{font-size:13px;color:#64748B;}
.lp-cell-time-wrap{display:flex;flex-direction:column;gap:3px;}
.lp-cell-time{font-family:'JetBrains Mono',monospace;font-size:13.5px;font-weight:700;letter-spacing:.03em;}
.lp-cell-time--masuk{color:#0F172A;}
.lp-cell-time--pulang{color:#64748B;}
.lp-mode-badge{display:inline-flex;align-items:center;padding:2px 7px;border-radius:5px;font-size:10.5px;font-weight:700;text-transform:capitalize;width:fit-content;letter-spacing:.02em;}
.lp-mode-badge--masuk{background:rgba(238,242,255,.9);color:#4F46E5;}
.lp-mode-badge--pulang{background:rgba(236,254,255,.9);color:#0891B2;}
.lp-cell-empty{color:#CBD5E1;font-size:16px;font-weight:300;}
.lp-status-badge{display:inline-flex;align-items:center;padding:3px 11px;border-radius:999px;font-size:11.5px;font-weight:600;text-transform:capitalize;letter-spacing:.02em;}

/* Status badge colors — must match statusClass() in admin.js */
.status-hadir{background:rgba(220,252,231,.9);color:#16A34A;}
.status-telat{background:rgba(254,243,199,.9);color:#D97706;}
.status-alpha{background:rgba(254,226,226,.9);color:#DC2626;}
.status-izin{background:rgba(207,250,254,.9);color:#0891B2;}
.status-sakit{background:rgba(207,250,254,.9);color:#0891B2;}

.lp-table-empty{padding:0!important;}
.lp-table-empty-inner{display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;padding:64px 24px;}
.lp-empty-icon{width:60px;height:60px;background:rgba(255,255,255,.55);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#CBD5E1;border:1.5px dashed rgba(203,213,225,.5);}
.lp-empty-title{font-size:15px;font-weight:700;color:#475569;margin:0;}
.lp-empty-sub{font-size:13px;color:#94A3B8;margin:0;line-height:1.5;}
.lp-table-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;padding:12px 18px;border-top:1px solid rgba(163,177,198,.1);font-size:12px;color:#94A3B8;background:rgba(255,255,255,.15);}
.lp-page-info{font-size:12px;color:#64748B;}
.lp-page-controls{display:flex;align-items:center;gap:5px;}
.lp-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 4px;border-radius:999px;font-size:12.5px;font-weight:600;cursor:pointer;border:1.5px solid rgba(255,255,255,.85);background:rgba(255,255,255,.6);color:#475569;box-shadow:3px 3px 8px rgba(163,177,198,.22),-2px -2px 6px rgba(255,255,255,.78);transition:all .15s;font-family:'Plus Jakarta Sans',sans-serif;}
.lp-page-btn:hover:not(:disabled):not(.lp-page-btn--active):not(.lp-page-btn--dots){background:rgba(255,255,255,.9);color:#1E293B;box-shadow:4px 4px 10px rgba(163,177,198,.28),-3px -3px 8px rgba(255,255,255,.85);transform:translateY(-1.5px);}
.lp-page-btn:active:not(:disabled):not(.lp-page-btn--dots){box-shadow:inset 2px 2px 6px rgba(163,177,198,.2),inset -1px -1px 4px rgba(255,255,255,.7);transform:translateY(0);}
.lp-page-btn:disabled:not(.lp-page-btn--active){opacity:.32;cursor:not-allowed;box-shadow:none;}
.lp-page-btn--active{background:linear-gradient(145deg,#2563EB,#1D4ED8);color:#fff;border-color:transparent;box-shadow:4px 4px 12px rgba(37,99,235,.35),-1px -1px 5px rgba(255,255,255,.4),inset 0 1px 1px rgba(255,255,255,.25);cursor:default;}
.lp-page-btn--dots{border-color:transparent;background:transparent;box-shadow:none;color:#94A3B8;cursor:default;font-weight:700;}

/* ── Print ── */
@media print{
#adminmenuwrap,#adminmenuback,#adminmenuwrap,#wpadminbar,#wpfooter,
#wpbody-content .notice,.notice,.update-nag,
[x-data="filterBar"],.no-print{display:none!important;}
#wpcontent,#wpbody{margin:0!important;padding:0!important;float:none!important;}
body,.wrap{margin:0!important;font-size:12px;}
.lp-bg{display:none!important;}
.lp-hero-card{border:1px solid #ccc!important;background:white!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border-radius:4px!important;margin-bottom:12px!important;}
.lp-hero-title{font-size:16px!important;}
.lp-hero-sub{font-size:11px!important;}
.lp-panel,.lp-table-panel{background:white!important;box-shadow:none!important;backdrop-filter:none!important;-webkit-backdrop-filter:none!important;border:none!important;border-radius:0!important;}
.lp-table{width:100%!important;border-collapse:collapse!important;}
.lp-table th,.lp-table td{border:1px solid #999!important;padding:5px 8px!important;font-size:11px!important;}
.lp-table thead tr{background:#eee!important;border-bottom:1px solid #999!important;}
.lp-row:hover td{background:none!important;}
.lp-status-badge{border:1px solid #999!important;padding:1px 5px!important;}
.lp-table-footer{font-size:11px!important;border-top:1px solid #ccc!important;padding:4px 8px!important;}
}
</style>
