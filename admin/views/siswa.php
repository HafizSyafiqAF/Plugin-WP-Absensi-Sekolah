<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$kelas_list = $wpdb->get_results( "SELECT id, nama_kelas FROM {$wpdb->prefix}absensi_kelas ORDER BY nama_kelas" );
?>

<?php
$_sw_kelas_opts = wp_json_encode( array_map( function( $k ) {
    return [ 'id' => (string) $k->id, 'nama' => $k->nama_kelas ];
}, (array) $kelas_list ) );
?>
<script>
window._swKelasOpts = <?php echo $_sw_kelas_opts; ?>;
window._swI18n = {
    semua_kelas: '<?php echo esc_js( __( 'Semua Kelas', 'absensi-sekolah' ) ); ?>',
    pilih_kelas: '<?php echo esc_js( __( '— Pilih Kelas —', 'absensi-sekolah' ) ); ?>',
    nama_wajib:  '<?php echo esc_js( __( 'Nama lengkap wajib diisi.', 'absensi-sekolah' ) ); ?>',
    nis_wajib:   '<?php echo esc_js( __( 'NIS wajib diisi.', 'absensi-sekolah' ) ); ?>',
    kelas_wajib: '<?php echo esc_js( __( 'Kelas wajib dipilih.', 'absensi-sekolah' ) ); ?>'
};
</script>

<div class="wrap sw-wrap" x-data="siswaManager">

  <!-- Blobs (background accent, sama dengan dashboard) -->
  <div class="sw-bg" aria-hidden="true">
    <div class="sw-blob sw-blob--1"></div>
    <div class="sw-blob sw-blob--2"></div>
    <div class="sw-blob sw-blob--3"></div>
  </div>

  <hr class="wp-header-end" style="margin:0;">

  <!-- ══ HERO ══ -->
  <div class="sw-hero">
    <!-- Decorative floating orbs -->
    <div class="sw-hero__orb sw-hero__orb--1" aria-hidden="true"></div>
    <div class="sw-hero__orb sw-hero__orb--2" aria-hidden="true"></div>
    <div class="sw-hero__left">
      <div class="sw-hero__eyebrow">
        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
        <?php esc_html_e( 'Manajemen Siswa', 'absensi-sekolah' ); ?>
      </div>
      <h1 class="sw-hero__title"><?php esc_html_e( 'Daftar &', 'absensi-sekolah' ); ?> <span class="sw-hero__accent"><?php esc_html_e( 'Kelola Siswa', 'absensi-sekolah' ); ?></span></h1>
      <p class="sw-hero__sub"><?php esc_html_e( 'Kelola data siswa, kelas, dan kartu RFID untuk sistem absensi', 'absensi-sekolah' ); ?></p>
      <div class="sw-hero__chips">
        <span class="sw-chip sw-chip--glass">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
          <span x-text="siswaList.length + ' <?php echo esc_js( __( 'Siswa', 'absensi-sekolah' ) ); ?>'"></span>
        </span>
        <span class="sw-chip sw-chip--blue" x-show="siswaList.filter(s=>s.rfid_uid).length > 0">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/></svg>
          <span x-text="siswaList.filter(s=>s.rfid_uid).length + ' <?php echo esc_js( __( 'Punya RFID', 'absensi-sekolah' ) ); ?>'"></span>
        </span>
        <span class="sw-chip sw-chip--orange" x-show="!loading && siswaList.filter(s=>!s.rfid_uid).length > 0">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01"/></svg>
          <span x-text="siswaList.filter(s=>!s.rfid_uid).length + ' <?php echo esc_js( __( 'Belum RFID', 'absensi-sekolah' ) ); ?>'"></span>
        </span>
      </div>
    </div>
    <div class="sw-hero__right">
      <!-- Mini RFID coverage donut -->
      <div class="sw-hero__donut-wrap" x-show="!loading && siswaList.length > 0">
        <svg class="sw-hero__donut" viewBox="0 0 72 72" width="80" height="80">
          <circle cx="36" cy="36" r="28" fill="none" stroke="rgba(0,0,0,.05)" stroke-width="9"/>
          <circle cx="36" cy="36" r="28" fill="none" stroke="#16A34A" stroke-width="9"
            :stroke-dasharray="siswaList.length > 0 ? (175.9 * siswaList.filter(s=>s.rfid_uid).length / siswaList.length) + ' 175.9' : '0 175.9'"
            stroke-dashoffset="44" transform="rotate(-90 36 36)" stroke-linecap="round"/>
          <text x="36" y="33" text-anchor="middle" font-family="'Plus Jakarta Sans',sans-serif" font-size="11" font-weight="800" fill="#1E293B"
            :textContent="siswaList.length > 0 ? Math.round(siswaList.filter(s=>s.rfid_uid).length/siswaList.length*100)+'%' : '—'"></text>
          <text x="36" y="44" text-anchor="middle" font-family="'Plus Jakarta Sans',sans-serif" font-size="5.5" font-weight="700" fill="#64748B" letter-spacing="0.8">RFID</text>
        </svg>
        <p class="sw-hero__donut-label"><?php esc_html_e( 'Coverage', 'absensi-sekolah' ); ?></p>
      </div>
      <button type="button" @click="openAdd()" class="sw-btn sw-btn--primary sw-btn--lg">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        <?php esc_html_e( 'Tambah Siswa', 'absensi-sekolah' ); ?>
      </button>
    </div>
  </div>

  <!-- ══ STAT CARDS ══ -->
  <div class="sw-cards">
    <div class="sw-card sw-card--blue">
      <div class="sw-card__top">
        <div class="sw-card__icon" style="background:rgba(37,99,235,.12);color:#2563EB;">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
        </div>
        <span class="sw-card__label"><?php esc_html_e( 'Total Siswa', 'absensi-sekolah' ); ?></span>
      </div>
      <div class="sw-card__value" x-text="loading ? '—' : siswaList.length" style="color:#2563EB;"></div>
      <div class="sw-card__bar-wrap">
        <div class="sw-card__bar" style="background:rgba(37,99,235,.12);"><div class="sw-card__bar-fill" style="width:100%;background:#2563EB;"></div></div>
      </div>
      <p class="sw-card__sub"><?php esc_html_e( 'siswa terdaftar', 'absensi-sekolah' ); ?></p>
    </div>
    <div class="sw-card sw-card--green">
      <div class="sw-card__top">
        <div class="sw-card__icon" style="background:rgba(22,163,74,.12);color:#16A34A;">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 7V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v2"/><line x1="12" y1="12" x2="12.01" y2="12"/></svg>
        </div>
        <span class="sw-card__label"><?php esc_html_e( 'Sudah RFID', 'absensi-sekolah' ); ?></span>
      </div>
      <div class="sw-card__value" x-text="loading ? '—' : siswaList.filter(s=>s.rfid_uid).length" style="color:#16A34A;"></div>
      <div class="sw-card__bar-wrap">
        <div class="sw-card__bar" style="background:rgba(22,163,74,.1);">
          <div class="sw-card__bar-fill" style="background:#16A34A;transition:width .8s ease;"
            :style="'width:' + (siswaList.length > 0 ? Math.round(siswaList.filter(s=>s.rfid_uid).length/siswaList.length*100) : 0) + '%;background:#16A34A;'"></div>
        </div>
        <span class="sw-card__bar-pct" style="color:#16A34A;"
              x-text="siswaList.length > 0 ? Math.round(siswaList.filter(s=>s.rfid_uid).length/siswaList.length*100)+'%' : '0%'"></span>
      </div>
      <p class="sw-card__sub"><?php esc_html_e( 'kartu terdaftar', 'absensi-sekolah' ); ?></p>
    </div>
    <div class="sw-card sw-card--amber">
      <div class="sw-card__top">
        <div class="sw-card__icon" style="background:rgba(217,119,6,.12);color:#D97706;">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <span class="sw-card__label"><?php esc_html_e( 'Belum RFID', 'absensi-sekolah' ); ?></span>
      </div>
      <div class="sw-card__value" x-text="loading ? '—' : siswaList.filter(s=>!s.rfid_uid).length" style="color:#D97706;"></div>
      <div class="sw-card__bar-wrap">
        <div class="sw-card__bar" style="background:rgba(217,119,6,.1);">
          <div class="sw-card__bar-fill" style="background:#D97706;transition:width .8s ease;"
            :style="'width:' + (siswaList.length > 0 ? Math.round(siswaList.filter(s=>!s.rfid_uid).length/siswaList.length*100) : 0) + '%;background:#D97706;'"></div>
        </div>
        <span class="sw-card__bar-pct" style="color:#D97706;"
              x-text="siswaList.length > 0 ? Math.round(siswaList.filter(s=>!s.rfid_uid).length/siswaList.length*100)+'%' : '0%'"></span>
      </div>
      <p class="sw-card__sub"><?php esc_html_e( 'perlu didaftarkan', 'absensi-sekolah' ); ?></p>
    </div>
  </div>

  <!-- ══ TABLE PANEL ══ -->
  <div class="sw-panel">

    <!-- Panel head dengan inline filter -->
    <div class="sw-panel__head sw-panel__head--filter">
      <div class="sw-panel__head-left">
        <div class="sw-panel__head-icon">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
        </div>
        <div>
          <p class="sw-panel__title"><?php esc_html_e( 'Daftar Siswa', 'absensi-sekolah' ); ?></p>
          <p class="sw-panel__sub"
             x-text="(search||filterKelas) ? filteredList.length+' <?php echo esc_js( __( 'dari', 'absensi-sekolah' ) ); ?> '+siswaList.length+' <?php echo esc_js( __( 'siswa', 'absensi-sekolah' ) ); ?>' : siswaList.length+' <?php echo esc_js( __( 'siswa terdaftar', 'absensi-sekolah' ) ); ?>'"></p>
        </div>
      </div>
      <div class="sw-inline-filter">
        <div class="sw-filter-field" style="min-width:180px;">
          <svg class="sw-filter-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" x-model="search"
                 placeholder="<?php esc_attr_e( 'Cari…', 'absensi-sekolah' ); ?>"
                 class="sw-input sw-input--pill sw-input--sm">
        </div>
        <div x-data="{ open: false }" style="min-width:148px;flex-shrink:0;position:relative;" @click.outside="open=false">
          <button type="button" @click="open=!open" class="sw-input sw-input--pill sw-input--sm sw-select-btn">
            <span x-text="filterKelasLabel" :style="!filterKelas ? 'color:#64748B' : 'color:#1E293B'"></span>
            <svg :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .2s;flex-shrink:0;color:#64748B;" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
          </button>
          <div x-show="open" x-transition.opacity.duration.150ms class="sw-dropdown">
            <button type="button" @click="filterKelas=''; open=false" class="sw-dropdown__item" :class="{'sw-dropdown__item--active':!filterKelas}">
              <?php esc_html_e( 'Semua Kelas', 'absensi-sekolah' ); ?>
            </button>
            <?php foreach ( $kelas_list as $k ) : ?>
            <button type="button" @click="filterKelas='<?php echo esc_attr( $k->id ); ?>'; open=false" class="sw-dropdown__item" :class="{'sw-dropdown__item--active':filterKelas=='<?php echo esc_attr( $k->id ); ?>'}">
              <?php echo esc_html( $k->nama_kelas ); ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
        <button type="button" @click="search=''; filterKelas=''" x-show="search || filterKelas"
                class="sw-btn sw-btn--ghost sw-btn--sm" style="padding:5px 8px;flex-shrink:0;" title="Reset filter">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Skeleton loading -->
    <div x-show="loading" class="sw-tbl-scroll">
      <table class="sw-tbl">
        <thead>
          <tr>
            <th class="sw-th--num">#</th>
            <th><?php esc_html_e( 'Siswa', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'NIS', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Kartu RFID', 'absensi-sekolah' ); ?></th>
            <th style="width:200px;"></th>
          </tr>
        </thead>
        <tbody>
          <?php for ( $i = 0; $i < 5; $i++ ) : ?>
          <tr>
            <td><div class="sw-skel" style="width:16px;height:11px;"></div></td>
            <td><div style="display:flex;align-items:center;gap:11px;">
              <div class="sw-skel" style="width:36px;height:36px;border-radius:10px;flex-shrink:0;"></div>
              <div class="sw-skel" style="width:<?php echo 100 + ( $i * 23 ) % 60; ?>px;height:13px;"></div>
            </div></td>
            <td><div class="sw-skel" style="width:70px;height:12px;"></div></td>
            <td><div class="sw-skel" style="width:52px;height:20px;border-radius:6px;"></div></td>
            <td><div class="sw-skel" style="width:86px;height:20px;border-radius:6px;"></div></td>
            <td><div style="display:flex;justify-content:flex-end;gap:5px;">
              <div class="sw-skel" style="width:50px;height:28px;border-radius:9px;"></div>
              <div class="sw-skel" style="width:44px;height:28px;border-radius:9px;"></div>
              <div class="sw-skel" style="width:50px;height:28px;border-radius:9px;"></div>
            </div></td>
          </tr>
          <?php endfor; ?>
        </tbody>
      </table>
    </div>

    <!-- Error -->
    <div x-show="!loading && error" class="sw-alert sw-alert--danger" aria-live="polite">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      <span x-text="error"></span>
    </div>

    <!-- Table -->
    <div x-show="!loading" class="sw-tbl-scroll">
      <table class="sw-tbl">
        <thead>
          <tr>
            <th class="sw-th--num">#</th>
            <th><?php esc_html_e( 'Siswa', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'NIS', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Kartu RFID', 'absensi-sekolah' ); ?></th>
            <th style="width:200px;"></th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(s, i) in paginatedList" :key="s.id">
            <tr>
              <td class="sw-td--num" x-text="(page-1)*perPage + i + 1"></td>
              <td>
                <div class="sw-siswa">
                  <div class="sw-avatar"
                       x-text="inisial(s.nama)"
                       :style="`background:hsl(${(s.id*47)%360},65%,92%);color:hsl(${(s.id*47)%360},50%,35%);`"></div>
                  <p class="sw-siswa__name" x-text="s.nama"></p>
                </div>
              </td>
              <td><span class="sw-mono" x-text="s.nis"></span></td>
              <td>
                <span x-show="s.nama_kelas" class="sw-kelas" x-text="s.nama_kelas"></span>
                <span x-show="!s.nama_kelas" class="sw-dash">—</span>
              </td>
              <td>
                <span x-show="s.rfid_uid" class="sw-rfid" x-text="s.rfid_uid"></span>
                <span x-show="!s.rfid_uid" class="sw-no-rfid"><?php esc_html_e( 'Belum terdaftar', 'absensi-sekolah' ); ?></span>
              </td>
              <td>
                <div class="sw-actions">
                  <button type="button" @click="openWali(s.id, s.nama)" class="sw-btn sw-btn--secondary sw-btn--sm">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <?php esc_html_e( 'Wali', 'absensi-sekolah' ); ?>
                  </button>
                  <button type="button" @click="openEdit(s)" class="sw-btn sw-btn--secondary sw-btn--sm">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                    <?php esc_html_e( 'Edit', 'absensi-sekolah' ); ?>
                  </button>
                  <button type="button" @click="deleteSiswa(s.id, s.nama)" class="sw-btn sw-btn--danger sw-btn--sm">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    <?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?>
                  </button>
                </div>
              </td>
            </tr>
          </template>

          <!-- Empty state -->
          <tr x-show="filteredList.length === 0 && !loading">
            <td colspan="6">
              <div class="sw-empty">
                <div class="sw-empty__ico">
                  <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                </div>
                <p class="sw-empty__title"
                   x-text="search || filterKelas ? '<?php echo esc_js( __( 'Tidak Ada Hasil', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Belum Ada Siswa', 'absensi-sekolah' ) ); ?>'"></p>
                <p class="sw-empty__sub"
                   x-text="search || filterKelas ? '<?php echo esc_js( __( 'Coba ubah kata kunci atau filter kelas.', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Klik Tambah Siswa untuk mulai mendaftarkan siswa.', 'absensi-sekolah' ) ); ?>'"></p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination footer -->
    <div x-show="!loading && filteredList.length > 0" class="sw-tbl-foot">
      <span class="sw-page-info"
            x-text="filteredList.length <= perPage
              ? filteredList.length + ' <?php echo esc_js( __( 'siswa', 'absensi-sekolah' ) ); ?>'
              : '<?php echo esc_js( __( 'Menampilkan', 'absensi-sekolah' ) ); ?> ' + ((page-1)*perPage+1) + '–' + Math.min(page*perPage, filteredList.length) + ' <?php echo esc_js( __( 'dari', 'absensi-sekolah' ) ); ?> ' + filteredList.length + ' <?php echo esc_js( __( 'siswa', 'absensi-sekolah' ) ); ?>'">
      </span>
      <div x-show="totalPages > 1" class="sw-page-controls">
        <button type="button" @click="page > 1 && page--" :disabled="page === 1"
                class="sw-page-btn" aria-label="<?php esc_attr_e( 'Sebelumnya', 'absensi-sekolah' ); ?>">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </button>
        <template x-for="(p, idx) in pageRange" :key="idx">
          <button type="button"
                  @click="typeof p === 'number' && (page = p)"
                  :disabled="typeof p !== 'number'"
                  class="sw-page-btn"
                  :class="{ 'sw-page-btn--active': p === page, 'sw-page-btn--dots': typeof p !== 'number' }"
                  x-text="p"></button>
        </template>
        <button type="button" @click="page < totalPages && page++" :disabled="page === totalPages"
                class="sw-page-btn" aria-label="<?php esc_attr_e( 'Selanjutnya', 'absensi-sekolah' ); ?>">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
        </button>
      </div>
    </div>

  </div><!-- /sw-panel table -->

  <!-- ══ Modal Tambah / Edit Siswa ══ -->
  <div x-show="showModal" x-cloak class="sw-overlay" @keydown.escape.window="showModal = false">
    <div class="sw-modal" @click.stop>
      <div class="sw-modal__head">
        <div class="sw-modal__head-left">
          <div class="sw-modal__head-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
          </div>
          <h2 class="sw-modal__title"
              x-text="editData?.id ? '<?php echo esc_js( __( 'Edit Siswa', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tambah Siswa Baru', 'absensi-sekolah' ) ); ?>'"></h2>
        </div>
        <button type="button" @click="showModal = false" class="sw-modal__close" aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="sw-modal__body">
        <div class="sw-field">
          <label class="sw-label"><?php esc_html_e( 'Nama Lengkap', 'absensi-sekolah' ); ?> <span class="sw-req">*</span></label>
          <input type="text" x-model="editData.nama" @input="delete fieldErrors.nama"
                 placeholder="<?php esc_attr_e( 'Nama lengkap siswa', 'absensi-sekolah' ); ?>"
                 class="sw-input" :class="fieldErrors.nama ? 'sw-input--error' : ''">
          <p x-show="fieldErrors.nama" x-text="fieldErrors.nama" class="sw-field-error" aria-live="polite"></p>
        </div>
        <div class="sw-field">
          <label class="sw-label"><?php esc_html_e( 'NIS', 'absensi-sekolah' ); ?> <span class="sw-req">*</span></label>
          <input type="text" x-model="editData.nis" @input="delete fieldErrors.nis"
                 placeholder="<?php esc_attr_e( 'Nomor Induk Siswa', 'absensi-sekolah' ); ?>"
                 class="sw-input sw-input--mono" :class="fieldErrors.nis ? 'sw-input--error' : ''">
          <p x-show="fieldErrors.nis" x-text="fieldErrors.nis" class="sw-field-error" aria-live="polite"></p>
        </div>
        <div class="sw-field">
          <label class="sw-label"><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?> <span class="sw-req">*</span></label>
          <div x-data="{ open: false }" style="position:relative;" @click.outside="open=false">
            <button type="button" @click="open=!open" class="sw-input sw-select-btn"
                    :class="fieldErrors.kelas_id ? 'sw-input--error' : ''">
              <span x-text="editKelasLabel" :style="!editData?.kelas_id ? 'color:#94A3B8' : 'color:#1E293B'"></span>
              <svg :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .2s;flex-shrink:0;color:#64748B;" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
            </button>
            <div x-show="open" x-transition.opacity.duration.150ms class="sw-dropdown">
              <button type="button" @click="editData.kelas_id=''; open=false" class="sw-dropdown__item" :class="{'sw-dropdown__item--active':!editData?.kelas_id}">
                <?php esc_html_e( '— Pilih Kelas —', 'absensi-sekolah' ); ?>
              </button>
              <?php foreach ( $kelas_list as $k ) : ?>
              <button type="button" @click="editData.kelas_id='<?php echo esc_attr( $k->id ); ?>'; delete fieldErrors.kelas_id; open=false" class="sw-dropdown__item" :class="{'sw-dropdown__item--active':editData?.kelas_id=='<?php echo esc_attr( $k->id ); ?>'}">
                <?php echo esc_html( $k->nama_kelas ); ?>
              </button>
              <?php endforeach; ?>
            </div>
          </div>
          <p x-show="fieldErrors.kelas_id" x-text="fieldErrors.kelas_id" class="sw-field-error" aria-live="polite"></p>
        </div>
        <div x-show="saveError" class="sw-alert sw-alert--danger" aria-live="polite">
          <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
          <span x-text="saveError"></span>
        </div>
      </div>
      <div class="sw-modal__foot">
        <button type="button" @click="showModal = false" class="sw-btn sw-btn--secondary" style="flex:1;">
          <?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?>
        </button>
        <button type="button" @click="save()" :disabled="saving" class="sw-btn sw-btn--primary" style="flex:1;">
          <div x-show="saving" class="sw-spinner sw-spinner--sm"></div>
          <span x-text="saving ? '<?php echo esc_js( __( 'Menyimpan…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Simpan', 'absensi-sekolah' ) ); ?>'"></span>
        </button>
      </div>
    </div>
  </div>

</div><!-- /sw-wrap -->

<!-- ── Modal WaliLinker ── -->
<div x-data="waliLinker" x-cloak>
  <div x-show="open" class="sw-overlay" @keydown.escape.window="close()">
    <div class="sw-modal" style="max-width:520px;max-height:85vh;overflow-y:auto;" @click.stop>
      <div class="sw-modal__head">
        <div class="sw-modal__head-left">
          <div class="sw-modal__head-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
          </div>
          <div>
            <h2 class="sw-modal__title"><?php esc_html_e( 'Orang Tua / Wali', 'absensi-sekolah' ); ?></h2>
            <p class="sw-modal__sub" x-text="siswaName"></p>
          </div>
        </div>
        <button type="button" @click="close()" class="sw-modal__close" aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="sw-modal__body">

        <!-- Wali terhubung -->
        <div>
          <p class="sw-sec-label"><?php esc_html_e( 'Terhubung Saat Ini', 'absensi-sekolah' ); ?></p>
          <div x-show="loadingWali" class="sw-hint sw-hint--center" style="padding:16px 0;"><?php esc_html_e( 'Memuat…', 'absensi-sekolah' ); ?></div>
          <div x-show="!loadingWali && walis.length === 0" class="sw-hint sw-hint--center" style="padding:16px 0;"><?php esc_html_e( 'Belum ada orang tua terhubung.', 'absensi-sekolah' ); ?></div>
          <div x-show="!loadingWali && walis.length > 0" class="sw-list">
            <template x-for="w in walis" :key="w.id">
              <div class="sw-list__item">
                <div>
                  <p class="sw-list__name" x-text="w.wali_nama"></p>
                  <p class="sw-list__meta" x-text="w.wali_login ?? ''"></p>
                </div>
                <button type="button" @click="removeWali(w.id, w.wali_nama)" class="sw-btn sw-btn--danger-soft sw-btn--sm">
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                  <?php esc_html_e( 'Lepas', 'absensi-sekolah' ); ?>
                </button>
              </div>
            </template>
          </div>
        </div>

        <!-- Tambah orang tua -->
        <div style="margin-top:20px;">
          <p class="sw-sec-label"><?php esc_html_e( 'Tambah Orang Tua', 'absensi-sekolah' ); ?></p>
          <div style="position:relative;">
            <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;pointer-events:none;" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            <input type="search" x-model="search" @input.debounce.400ms="searchUsers()"
                   placeholder="<?php esc_attr_e( 'Cari nama user orang tua…', 'absensi-sekolah' ); ?>"
                   class="sw-input sw-input--pill">
          </div>
          <p x-show="searching" class="sw-hint sw-hint--center" style="margin-top:8px;"><?php esc_html_e( 'Mencari…', 'absensi-sekolah' ); ?></p>
          <p x-show="!searching && search.length >= 2 && results.length === 0" class="sw-hint sw-hint--center" style="margin-top:8px;"><?php esc_html_e( 'Tidak ada user orang tua ditemukan.', 'absensi-sekolah' ); ?></p>
          <div x-show="results.length > 0" class="sw-list" style="margin-top:8px;">
            <template x-for="u in results" :key="u.id">
              <div class="sw-list__item">
                <div>
                  <p class="sw-list__name" x-text="u.name"></p>
                  <p class="sw-list__meta" x-text="u.slug ?? ''"></p>
                </div>
                <button type="button" @click="addWali(u)" :disabled="addingId === u.id"
                        class="sw-btn sw-btn--primary sw-btn--sm"
                        :style="addingId === u.id ? 'opacity:.6;cursor:not-allowed;' : ''">
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                  <span x-text="addingId === u.id ? '<?php echo esc_js( __( 'Menambahkan…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Hubungkan', 'absensi-sekolah' ) ); ?>'"></span>
                </button>
              </div>
            </template>
          </div>
          <div x-show="error" x-cloak class="sw-alert sw-alert--danger" style="margin-top:10px;" x-text="error" aria-live="polite"></div>
          <p class="sw-hint" style="margin-top:12px;"><?php esc_html_e( 'Hanya user dengan role "orang_tua" yang muncul di hasil pencarian.', 'absensi-sekolah' ); ?></p>
        </div>

      </div>
    </div>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap');

.sw-wrap*,.sw-wrap*::before,.sw-wrap*::after{box-sizing:border-box;}
.sw-wrap{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif!important;min-height:100vh;padding-bottom:48px;position:relative;z-index:0;}

body.wp-admin{background:#EAF0F6!important;}
#wpcontent,#wpbody-content,#wpbody{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed!important;}

.sw-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}
.sw-blob{position:absolute;border-radius:50%;filter:blur(140px);opacity:.85;}
.sw-blob--1{width:750px;height:750px;top:-180px;left:-120px;background:radial-gradient(circle,rgba(129,140,248,.45) 0%,rgba(99,102,241,.15) 65%,transparent 100%);}
.sw-blob--2{width:700px;height:700px;bottom:-150px;right:-80px;background:radial-gradient(circle,rgba(244,114,182,.40) 0%,rgba(219,39,119,.12) 65%,transparent 100%);}
.sw-blob--3{width:600px;height:600px;top:25%;right:10%;background:radial-gradient(circle,rgba(103,232,249,.42) 0%,rgba(6,182,212,.12) 65%,transparent 100%);}
.sw-hero,.sw-panel{position:relative;z-index:1;}

.sw-hero{background:rgba(255,255,255,.40);backdrop-filter:blur(24px) saturate(150%);-webkit-backdrop-filter:blur(24px) saturate(150%);border:1px solid rgba(255,255,255,.65);border-radius:24px;padding:28px 32px;margin:14px 0 16px;display:flex;align-items:center;justify-content:space-between;gap:24px;overflow:hidden;position:relative;z-index:1;box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);}
.sw-hero::after{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(37,99,235,.012) 1px,transparent 1px);background-size:22px 22px;pointer-events:none;}
/* Decorative floating orbs inside hero */
.sw-hero__orb{position:absolute;border-radius:50%;pointer-events:none;z-index:0;}
.sw-hero__orb--1{width:180px;height:180px;top:-60px;right:160px;background:radial-gradient(circle,rgba(37,99,235,.12) 0%,transparent 70%);filter:blur(30px);}
.sw-hero__orb--2{width:140px;height:140px;bottom:-50px;right:80px;background:radial-gradient(circle,rgba(124,58,237,.10) 0%,transparent 70%);filter:blur(25px);}
.sw-hero__left{flex:1;min-width:0;position:relative;z-index:1;}
.sw-hero__eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;color:#2563EB;background:#DBEAFE;padding:5px 11px;border-radius:8px;letter-spacing:.02em;text-transform:uppercase;margin:0 0 12px;border:1px solid rgba(37,99,235,.1);}
.sw-hero__title{font-size:22px;font-weight:800;color:#1E293B;margin:0 0 6px;line-height:1.25;}
.sw-hero__accent{background:linear-gradient(135deg,#2563EB 0%,#7C3AED 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.sw-hero__sub{font-size:13.5px;color:#64748B;margin:0 0 16px;}
.sw-hero__chips{display:flex;flex-wrap:wrap;gap:8px;}
.sw-chip{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;padding:5px 13px;border-radius:20px;}
.sw-chip--glass{background:rgba(255,255,255,.6);color:#334155;border:1px solid rgba(255,255,255,.8);}
.sw-chip--blue{background:rgba(37,99,235,.1);color:#2563EB;border:1px solid rgba(37,99,235,.15);}
.sw-chip--orange{background:rgba(217,119,6,.1);color:#D97706;border:1px solid rgba(217,119,6,.15);}
.sw-hero__right{display:flex;flex-direction:column;align-items:center;gap:14px;flex-shrink:0;position:relative;z-index:1;}
.sw-hero__donut-wrap{display:flex;flex-direction:column;align-items:center;gap:4px;}
.sw-hero__donut{filter:drop-shadow(0 3px 8px rgba(163,177,198,.3));}
.sw-hero__donut-label{font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.08em;margin:0;}

.sw-panel{background:rgba(255,255,255,.40);backdrop-filter:blur(24px) saturate(150%);-webkit-backdrop-filter:blur(24px) saturate(150%);border-radius:24px;border:1px solid rgba(255,255,255,.65);box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);overflow:hidden;margin-bottom:16px;}
.sw-panel__head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid rgba(0,0,0,.05);gap:10px;flex-wrap:wrap;background:rgba(255,255,255,.3);}
.sw-panel__head-left{display:flex;align-items:center;gap:10px;}
.sw-panel__head-icon{width:30px;height:30px;background:#DBEAFE;color:#2563EB;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sw-panel__title{font-size:13.5px;font-weight:700;color:#1E293B;margin:0;}

.sw-filter-body{display:flex;gap:12px;flex-wrap:wrap;padding:14px 18px;}
.sw-filter-field{position:relative;flex:1;min-width:200px;}
.sw-filter-field--select{min-width:160px;flex:0 0 auto;}
.sw-filter-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;pointer-events:none;}

.sw-input{width:100%;box-sizing:border-box;background:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.8);border-radius:12px;padding:9px 12px;font-size:13.5px;min-height:42px;font-family:'Plus Jakarta Sans',sans-serif;color:#1E293B;outline:none;transition:border-color .15s,box-shadow .15s,background .15s;box-shadow:inset 2px 2px 5px rgba(163,177,198,.15),inset -2px -2px 5px rgba(255,255,255,.6);}
.sw-input:focus{border-color:rgba(37,99,235,.4);box-shadow:0 0 0 3px rgba(37,99,235,.08),inset 2px 2px 5px rgba(163,177,198,.1),inset -2px -2px 5px rgba(255,255,255,.7);background:rgba(255,255,255,.85);}
.sw-input--icon{padding-left:34px;}
.sw-input.sw-input--pill{border-radius:999px !important;padding-left:38px;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.88);box-shadow:inset 4px 4px 10px rgba(163,177,198,.35),inset -4px -4px 10px rgba(255,255,255,.85);}
.sw-input.sw-input--pill:focus{background:rgba(255,255,255,.78);border-color:rgba(37,99,235,.25);box-shadow:inset 3px 3px 7px rgba(163,177,198,.25),inset -3px -3px 7px rgba(255,255,255,.8),0 0 0 3px rgba(37,99,235,.07);}
.sw-input.sw-input--select{appearance:none;-webkit-appearance:none;border-radius:999px !important;padding-left:16px;padding-right:40px;background-color:rgba(255,255,255,.55);background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='13' height='13' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;border:1px solid rgba(255,255,255,.88);box-shadow:inset 4px 4px 10px rgba(163,177,198,.35),inset -4px -4px 10px rgba(255,255,255,.85);cursor:pointer;}
.sw-input.sw-input--select:focus{background-color:rgba(255,255,255,.78);border-color:rgba(37,99,235,.25);box-shadow:inset 3px 3px 7px rgba(163,177,198,.25),inset -3px -3px 7px rgba(255,255,255,.8),0 0 0 3px rgba(37,99,235,.07);}
.sw-input--mono{font-family:'JetBrains Mono',monospace;letter-spacing:.03em;}

.sw-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 22px;border-radius:999px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:42px;font-family:'Plus Jakarta Sans',sans-serif;transition:transform .15s,box-shadow .15s,background .15s;text-decoration:none;white-space:nowrap;position:relative;letter-spacing:.01em;}
.sw-btn--sm{padding:5px 14px;font-size:12px;min-height:32px;border-radius:999px;}
.sw-btn--lg{padding:12px 28px;font-size:14px;min-height:48px;}

.sw-btn--primary{background:linear-gradient(145deg,#3b82f6 0%,#1d4ed8 100%);color:white;border:none;box-shadow:5px 5px 14px rgba(37,99,235,.35),-2px -2px 8px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.28);}
.sw-btn--primary:hover:not(:disabled){background:linear-gradient(145deg,#2563eb 0%,#1e40af 100%);box-shadow:7px 7px 20px rgba(37,99,235,.45),-3px -3px 10px rgba(255,255,255,.6),inset 0 1px 1px rgba(255,255,255,.3);transform:translateY(-2px);}
.sw-btn--primary:active:not(:disabled){box-shadow:inset 4px 4px 10px rgba(15,23,42,.25),inset -2px -2px 6px rgba(255,255,255,.12);transform:translateY(0);}
.sw-btn--primary:disabled{opacity:.45;cursor:not-allowed;transform:none;box-shadow:none;}

.sw-btn--secondary{background:rgba(255,255,255,.65);backdrop-filter:blur(12px) saturate(130%);-webkit-backdrop-filter:blur(12px) saturate(130%);color:#475569;border:1.5px solid rgba(255,255,255,.88);box-shadow:4px 4px 12px rgba(163,177,198,.28),-3px -3px 9px rgba(255,255,255,.82);}
.sw-btn--secondary:hover{background:rgba(255,255,255,.88);color:#1E293B;box-shadow:6px 6px 16px rgba(163,177,198,.32),-4px -4px 12px rgba(255,255,255,.9);transform:translateY(-1.5px);}
.sw-btn--secondary:active{box-shadow:inset 3px 3px 8px rgba(163,177,198,.22),inset -2px -2px 6px rgba(255,255,255,.78);transform:translateY(0);}

.sw-btn--ghost{background:transparent;color:#64748B;border:1px solid transparent;box-shadow:none;}
.sw-btn--ghost:hover{background:rgba(255,255,255,.5);color:#334155;box-shadow:2px 2px 6px rgba(163,177,198,.18),-1px -1px 4px rgba(255,255,255,.6);border-color:rgba(255,255,255,.7);}
.sw-btn--ghost:active{box-shadow:inset 2px 2px 5px rgba(163,177,198,.15),inset -1px -1px 4px rgba(255,255,255,.6);}

.sw-btn--danger{background:linear-gradient(145deg,#ef4444,#b91c1c);color:white;box-shadow:4px 4px 12px rgba(220,38,38,.3),-1px -1px 5px rgba(255,255,255,.35),inset 0 1px 1px rgba(255,255,255,.2);}
.sw-btn--danger:hover{background:linear-gradient(145deg,#dc2626,#991b1b);box-shadow:5px 5px 16px rgba(220,38,38,.4),-2px -2px 7px rgba(255,255,255,.4);transform:translateY(-1.5px);}
.sw-btn--danger:active{box-shadow:inset 3px 3px 8px rgba(100,0,0,.25),inset -1px -1px 4px rgba(255,255,255,.1);transform:translateY(0);}

.sw-btn--danger-soft{background:rgba(254,226,226,.75);backdrop-filter:blur(8px);color:#DC2626;border:1.5px solid rgba(220,38,38,.18);box-shadow:3px 3px 8px rgba(220,38,38,.1),-2px -2px 6px rgba(255,255,255,.75);}
.sw-btn--danger-soft:hover{background:rgba(254,202,202,.85);box-shadow:4px 4px 10px rgba(220,38,38,.15),-2px -2px 7px rgba(255,255,255,.8);transform:translateY(-1px);}
.sw-btn--danger-soft:active{box-shadow:inset 2px 2px 6px rgba(220,38,38,.12),inset -1px -1px 4px rgba(255,255,255,.7);transform:translateY(0);}

.sw-tbl-scroll{overflow-x:auto;scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.08) transparent;}
.sw-tbl-scroll::-webkit-scrollbar{width:5px;height:5px;}
.sw-tbl-scroll::-webkit-scrollbar-thumb{background:rgba(0,0,0,.08);border-radius:99px;}
.sw-tbl{width:100%;border-collapse:collapse;font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;}
.sw-tbl thead tr{background:rgba(0,0,0,.008);}
.sw-tbl th{text-align:left;padding:12px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#475569;border-bottom:1.5px solid rgba(0,0,0,.04);white-space:nowrap;}
.sw-th--num{width:42px;}
.sw-tbl td{padding:13px 16px;border-bottom:1.1px solid rgba(0,0,0,.03);color:#334155;vertical-align:middle;transition:background .15s ease;}
.sw-tbl tbody tr{transition:transform .15s ease,background .15s ease;}
.sw-tbl tbody tr:last-child td{border-bottom:none;}
.sw-tbl tbody tr:hover{transform:translateY(-0.5px);background:rgba(37,99,235,.025);}
.sw-tbl tbody tr:hover td{color:#0F172A;}
.sw-td--num{color:#94A3B8;font-size:11px;font-family:'JetBrains Mono',monospace;font-weight:700;}
.sw-siswa{display:flex;align-items:center;gap:11px;}
.sw-avatar{width:36px;height:36px;border-radius:10px;font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 1px rgba(255,255,255,.4),0 2px 5px rgba(0,0,0,.03);border:1px solid rgba(0,0,0,.02);}
.sw-siswa__name{margin:0;font-weight:600;font-size:13px;color:#1E293B;}
.sw-mono{font-family:'JetBrains Mono',monospace;font-size:12.5px;color:#64748B;}
.sw-kelas{display:inline-block;background:rgba(255,255,255,.6);color:#475569;font-size:11px;font-weight:600;padding:3px 8px;border-radius:6px;border:1px solid rgba(0,0,0,.05);box-shadow:0 1px 2px rgba(0,0,0,.02);}
.sw-dash{color:#CBD5E1;}
.sw-rfid{display:inline-block;font-family:'JetBrains Mono',monospace;font-size:11.5px;font-weight:700;padding:3px 10px;border-radius:6px;background:rgba(255,255,255,.6);color:#1E293B;letter-spacing:.05em;border:1px solid rgba(0,0,0,.06);box-shadow:inset 0 1px 1px rgba(255,255,255,.5),0 1px 2px rgba(0,0,0,.02);}
.sw-no-rfid{font-size:12px;color:#94A3B8;font-style:italic;}
.sw-actions{display:flex;align-items:center;justify-content:flex-end;gap:5px;}
.sw-tbl-foot{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-top:1px solid rgba(0,0,0,.04);font-size:11.5px;color:#64748B;background:rgba(255,255,255,.15);flex-wrap:wrap;gap:10px;}
.sw-page-info{font-size:12px;color:#64748B;}
.sw-page-controls{display:flex;align-items:center;gap:5px;}
.sw-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:32px;height:32px;padding:0 4px;border-radius:999px;font-size:12.5px;font-weight:600;cursor:pointer;border:1.5px solid rgba(255,255,255,.85);background:rgba(255,255,255,.6);color:#475569;box-shadow:3px 3px 8px rgba(163,177,198,.22),-2px -2px 6px rgba(255,255,255,.78);transition:all .15s;font-family:'Plus Jakarta Sans',sans-serif;}
.sw-page-btn:hover:not(:disabled):not(.sw-page-btn--active):not(.sw-page-btn--dots){background:rgba(255,255,255,.9);color:#1E293B;box-shadow:4px 4px 10px rgba(163,177,198,.28),-3px -3px 8px rgba(255,255,255,.85);transform:translateY(-1.5px);}
.sw-page-btn:active:not(:disabled):not(.sw-page-btn--dots){box-shadow:inset 2px 2px 6px rgba(163,177,198,.2),inset -1px -1px 4px rgba(255,255,255,.7);transform:translateY(0);}
.sw-page-btn:disabled:not(.sw-page-btn--active){opacity:.32;cursor:not-allowed;box-shadow:none;}
.sw-page-btn--active{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:white;border-color:transparent;box-shadow:4px 4px 12px rgba(37,99,235,.35),-1px -1px 5px rgba(255,255,255,.4),inset 0 1px 1px rgba(255,255,255,.25);cursor:default;}
.sw-page-btn--dots{border-color:transparent;background:transparent;box-shadow:none;color:#94A3B8;cursor:default;font-weight:700;letter-spacing:.05em;}
.sw-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:48px 24px;text-align:center;}
.sw-empty__ico{width:56px;height:56px;background:rgba(0,0,0,.02);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94A3B8;margin-bottom:4px;border:1.5px dashed rgba(0,0,0,.04);}
.sw-empty__title{margin:0;font-size:13px;font-weight:700;color:#475569;}
.sw-empty__sub{margin:0;font-size:12px;color:#64748B;}

.sw-state-loading{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:56px 24px;gap:12px;}
.sw-spinner{width:24px;height:24px;border:2.5px solid rgba(163,177,198,.3);border-top-color:#2563EB;border-radius:50%;animation:sw-spin .7s linear infinite;}
.sw-spinner--sm{width:14px;height:14px;border-width:2px;}
.sw-state-text{margin:0;font-size:13px;color:#64748B;}
.sw-alert{display:flex;align-items:flex-start;gap:9px;padding:12px 14px;border-radius:10px;font-size:13px;margin:14px 18px;}
.sw-alert--danger{background:rgba(254,226,226,.8);color:#dc2626;border:1px solid rgba(220,38,38,.12);}

.sw-overlay{position:fixed;inset:0;background:rgba(15,23,42,.28);backdrop-filter:blur(8px) saturate(110%);-webkit-backdrop-filter:blur(8px) saturate(110%);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;}
.sw-modal{background:rgba(255,255,255,.80);backdrop-filter:blur(24px) saturate(150%);-webkit-backdrop-filter:blur(24px) saturate(150%);border-radius:24px;width:100%;max-width:440px;border:1.5px solid rgba(255,255,255,.90);box-shadow:0 24px 60px rgba(15,23,42,.1),6px 6px 20px rgba(163,177,198,.2),-6px -6px 20px rgba(255,255,255,.7),inset 0 1px 0 rgba(255,255,255,.95);}
.sw-modal__head{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid rgba(163,177,198,.16);}
.sw-modal__head-left{display:flex;align-items:center;gap:12px;}
.sw-modal__head-icon{width:36px;height:36px;background:#DBEAFE;color:#2563EB;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.sw-modal__title{font-size:15px;font-weight:700;color:#0F172A;margin:0;}
.sw-modal__sub{font-size:12px;color:#64748B;margin:2px 0 0;}
.sw-modal__close{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.6);border:1px solid rgba(163,177,198,.2);cursor:pointer;color:#94A3B8;transition:all .12s;}
.sw-modal__close:hover{background:rgba(255,255,255,.9);color:#475569;}
.sw-modal__body{padding:20px 24px;display:flex;flex-direction:column;gap:16px;}
.sw-modal__foot{display:flex;gap:10px;padding:14px 24px 22px;border-top:1px solid rgba(163,177,198,.16);}
.sw-modal .sw-input{border-radius:999px !important;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.88);box-shadow:inset 4px 4px 10px rgba(163,177,198,.35),inset -4px -4px 10px rgba(255,255,255,.85);padding-left:18px;}
.sw-modal .sw-input:focus{background:rgba(255,255,255,.82);border-color:rgba(37,99,235,.28);box-shadow:0 0 0 3px rgba(37,99,235,.07),inset 3px 3px 7px rgba(163,177,198,.22),inset -3px -3px 7px rgba(255,255,255,.88);}
.sw-modal .sw-input--error{border-color:rgba(220,38,38,.45) !important;box-shadow:0 0 0 3px rgba(220,38,38,.07),inset 3px 3px 7px rgba(220,38,38,.08),inset -3px -3px 7px rgba(255,255,255,.85) !important;}
.sw-modal .sw-input--select{padding-left:18px;padding-right:42px;}
.sw-modal .sw-label{font-size:12.5px;font-weight:600;color:#334155;}
.sw-field-error{margin:0;font-size:11.5px;font-weight:600;color:#DC2626;padding-left:14px;}
.sw-modal .sw-field{gap:7px;}
.sw-modal .sw-btn--secondary{background:rgba(255,255,255,.6);color:#475569;border:1px solid rgba(163,177,198,.25);box-shadow:3px 3px 8px rgba(163,177,198,.2),-2px -2px 6px rgba(255,255,255,.7);}
.sw-modal .sw-btn--secondary:hover{background:rgba(255,255,255,.88);color:#1E293B;}
.sw-field{display:flex;flex-direction:column;gap:6px;}
.sw-label{font-size:11.5px;font-weight:700;color:#475569;letter-spacing:.01em;}
.sw-req{color:#DC2626;}
.sw-list{border:1px solid rgba(0,0,0,.05);border-radius:12px;overflow:hidden;background:rgba(255,255,255,.4);}
.sw-list__item{display:flex;align-items:center;justify-content:space-between;padding:11px 14px;border-bottom:1px solid rgba(0,0,0,.03);gap:10px;}
.sw-list__item:last-child{border-bottom:none;}
.sw-list__name{font-size:13.5px;font-weight:600;color:#1E293B;margin:0 0 2px;}
.sw-list__meta{font-size:11.5px;color:#9CA3AF;margin:0;font-family:'JetBrains Mono',monospace;}
.sw-sec-label{font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:#64748B;margin:0 0 10px;}
.sw-hint{font-size:11.5px;color:#94A3B8;margin:0;line-height:1.5;}
.sw-hint--center{text-align:center;}

/* ══ STAT CARDS ══ */
.sw-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px;}
@media(max-width:680px){.sw-cards{grid-template-columns:repeat(1,1fr);}}
.sw-card{background:rgba(255,255,255,.40);backdrop-filter:blur(24px) saturate(150%);-webkit-backdrop-filter:blur(24px) saturate(150%);border-radius:20px;padding:20px 22px 18px;border:1px solid rgba(255,255,255,.65);box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden;}
.sw-card::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(79,70,229,.01) 1px,transparent 1px);background-size:18px 18px;pointer-events:none;}
/* Accent glow top-left corner per card color */
.sw-card--blue::after{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.12) 0%,transparent 70%);pointer-events:none;}
.sw-card--green::after{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:radial-gradient(circle,rgba(22,163,74,.12) 0%,transparent 70%);pointer-events:none;}
.sw-card--amber::after{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:radial-gradient(circle,rgba(217,119,6,.12) 0%,transparent 70%);pointer-events:none;}
.sw-card:hover{transform:translateY(-3px);box-shadow:10px 10px 30px rgba(163,177,198,.3),-10px -10px 30px rgba(255,255,255,.9),inset 0 1px 1px rgba(255,255,255,.8);}
.sw-card__top{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.sw-card__icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 1px rgba(255,255,255,.5),0 2px 6px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03);}
.sw-card__label{font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;}
.sw-card__value{font-size:36px;font-weight:800;line-height:1;margin:0 0 10px;letter-spacing:-.02em;font-variant-numeric:tabular-nums;font-family:'Plus Jakarta Sans',sans-serif;}
/* Progress bar for RFID coverage */
.sw-card__bar-wrap{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.sw-card__bar{flex:1;height:5px;border-radius:99px;overflow:hidden;}
.sw-card__bar-fill{height:100%;border-radius:99px;transition:width .8s cubic-bezier(.4,0,.2,1);}
.sw-card__bar-pct{font-size:11px;font-weight:700;min-width:32px;text-align:right;font-family:'JetBrains Mono',monospace;}
.sw-card__sub{margin:0;font-size:11.5px;color:#64748B;}

/* ══ PANEL HEAD WITH INLINE FILTER ══ */
.sw-panel__sub{font-size:11.5px;color:#64748B;margin:2px 0 0;}
.sw-panel__head--filter{gap:12px;flex-wrap:wrap;align-items:center;}
.sw-inline-filter{display:flex;align-items:center;gap:8px;flex-wrap:nowrap;}
.sw-input--sm{min-height:36px !important;font-size:12.5px !important;padding-top:7px !important;padding-bottom:7px !important;}

/* ══ SKELETON LOADING ══ */
.sw-skel{display:block;border-radius:6px;background:linear-gradient(90deg,rgba(226,232,240,.6) 25%,rgba(241,245,249,.9) 50%,rgba(226,232,240,.6) 75%);background-size:200% 100%;animation:sw-shimmer 1.5s ease-in-out infinite;}
.sw-tbl tbody tr:has(.sw-skel) td{border-bottom:1px solid rgba(0,0,0,.025);}

/* ══ CUSTOM SELECT DROPDOWN ══ */
.sw-select-btn{display:flex;align-items:center;justify-content:space-between;gap:8px;cursor:pointer;text-align:left;-webkit-appearance:none;appearance:none;}
.sw-dropdown{position:absolute;top:calc(100% + 6px);left:0;right:0;min-width:140px;background:rgba(255,255,255,.88);backdrop-filter:blur(20px) saturate(150%);-webkit-backdrop-filter:blur(20px) saturate(150%);border-radius:16px;border:1.5px solid rgba(255,255,255,.92);box-shadow:0 10px 36px rgba(15,23,42,.1),4px 4px 16px rgba(163,177,198,.18),-4px -4px 14px rgba(255,255,255,.7);overflow:hidden;z-index:1000;padding:6px;}
.sw-dropdown__item{display:flex;align-items:center;width:100%;padding:9px 14px;font-size:13px;font-weight:500;color:#334155;background:transparent;border:none;cursor:pointer;border-radius:10px;text-align:left;font-family:'Plus Jakarta Sans',sans-serif;transition:background .1s,color .1s;white-space:nowrap;}
.sw-dropdown__item:hover{background:rgba(37,99,235,.07);color:#1E293B;}
.sw-dropdown__item--active{background:rgba(37,99,235,.1);color:#2563EB;font-weight:600;}

@keyframes sw-spin{to{transform:rotate(360deg);}}
@keyframes sw-shimmer{0%{background-position:200% 0;}100%{background-position:-200% 0;}}
[x-cloak]{display:none!important;}
</style>
