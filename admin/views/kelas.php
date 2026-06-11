<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$table     = $wpdb->prefix . 'absensi_kelas';
$saved_msg = null;

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['_wpnonce_kelas'] ) ) {
    if ( ! wp_verify_nonce( $_POST['_wpnonce_kelas'], 'absensi_kelas_action' ) ) wp_die( 'Token keamanan tidak valid.' );
    $action     = sanitize_key( $_POST['action_type'] ?? '' );
    $nama_kelas = sanitize_text_field( $_POST['nama_kelas'] ?? '' );
    $tingkat    = absint( $_POST['tingkat']   ?? 0 );
    $guru_id    = absint( $_POST['guru_id']   ?? 0 ) ?: null;
    $kelas_id   = absint( $_POST['kelas_id']  ?? 0 );
    if ( in_array( $action, [ 'add', 'edit' ], true ) && $nama_kelas !== '' ) {
        if ( $action === 'add' )       $wpdb->insert( $table, compact( 'nama_kelas', 'tingkat', 'guru_id' ) );
        elseif ( $kelas_id )           $wpdb->update( $table, compact( 'nama_kelas', 'tingkat', 'guru_id' ), [ 'id' => $kelas_id ] );
        $saved_msg = 'saved';
    }
    if ( $action === 'delete' && $kelas_id ) {
        $wpdb->delete( $table, [ 'id' => $kelas_id ], [ '%d' ] );
        $saved_msg = 'deleted';
    }
}

$kelas_list = $wpdb->get_results(
    "SELECT k.*, u.display_name AS nama_guru,
            (SELECT COUNT(*) FROM {$wpdb->prefix}absensi_siswa s WHERE s.kelas_id = k.id) AS jumlah_siswa
     FROM {$table} k
     LEFT JOIN {$wpdb->users} u ON u.ID = k.guru_id
     ORDER BY k.tingkat ASC, k.nama_kelas ASC"
);

$guru_list = get_users( [
    'role__in' => [ 'administrator', 'guru', 'absensi_admin' ],
    'fields'   => [ 'ID', 'display_name' ],
] );

/* ── Kehadiran hari ini per kelas ── */
$today      = current_time( 'Y-m-d' );
$hadir_raw  = $wpdb->get_results( $wpdb->prepare(
    "SELECT kelas_id, COUNT(*) AS sudah_absen FROM {$wpdb->prefix}absensi_rekap WHERE tanggal = %s GROUP BY kelas_id",
    $today
) );
$hadir_map  = [];
foreach ( $hadir_raw as $row ) {
    $hadir_map[ (int) $row->kelas_id ] = (int) $row->sudah_absen;
}

/* ── JS data ── */
$_kl_kelas_data = wp_json_encode( array_map( function ( $k ) use ( $hadir_map ) {
    return [
        'id'           => (int) $k->id,
        'nama_kelas'   => $k->nama_kelas,
        'tingkat'      => (int) ( $k->tingkat ?? 0 ),
        'guru_id'      => (string) ( $k->guru_id ?? '' ),
        'nama_guru'    => $k->nama_guru ?? '',
        'jumlah_siswa' => (int) ( $k->jumlah_siswa ?? 0 ),
        'sudah_absen'  => $hadir_map[ (int) $k->id ] ?? 0,
    ];
}, $kelas_list ) );

$_kl_guru_opts = wp_json_encode( array_map( function ( $g ) {
    return [ 'id' => (string) $g->ID, 'nama' => $g->display_name ];
}, $guru_list ) );

$_kl_tingkat_opts = wp_json_encode( array_map( function ( $t ) {
    return [ 'id' => $t, 'nama' => 'Kelas ' . $t ];
}, range( 1, 12 ) ) );
?>
<script>
window._klKelasData   = <?php echo $_kl_kelas_data; ?>;
window._klGuruOpts    = <?php echo $_kl_guru_opts; ?>;
window._klTingkatOpts = <?php echo $_kl_tingkat_opts; ?>;
</script>

<div class="wrap kl-wrap"
     x-data="{
       kelasList:     (window._klKelasData   || []),
       guruOptions:   (window._klGuruOpts    || []),
       tingkatOptions:(window._klTingkatOpts || []),

       search:        '',
       filterTingkat: 0,
       page:          1,
       perPage:       10,
       showModal:     false,
       fieldErrors:   {},
       editMode:      false,
       editId:        0,
       editNama:      '',
       editTingkat:   0,
       editGuruId:    '',

       get filteredList() {
         let list = this.kelasList;
         if (this.filterTingkat) list = list.filter(k => k.tingkat === this.filterTingkat);
         if (this.search.trim()) {
           const q = this.search.toLowerCase();
           list = list.filter(k =>
             k.nama_kelas.toLowerCase().includes(q) ||
             (k.nama_guru || '').toLowerCase().includes(q)
           );
         }
         return list;
       },
       get uniqueTingkat() {
         return [...new Set(this.kelasList.filter(k=>k.tingkat>0).map(k=>k.tingkat))].sort((a,b)=>a-b);
       },
       countByTingkat(t) { return this.kelasList.filter(k=>k.tingkat===t).length; },
       get totalPages()    { return Math.max(1, Math.ceil(this.filteredList.length / this.perPage)); },
       get paginatedList() { return this.filteredList.slice((this.page-1)*this.perPage, this.page*this.perPage); },
       get pageRange() {
         const t=this.totalPages, c=this.page;
         if(t<=7) return Array.from({length:t},(_,i)=>i+1);
         const s=new Set([1,2,c-1,c,c+1,t-1,t].filter(p=>p>=1&&p<=t));
         const arr=[...s].sort((a,b)=>a-b);
         const res=[];let prev=0;
         for(const p of arr){if(p-prev>1)res.push('…');res.push(p);prev=p;}
         return res;
       },
       get totalKelas()  { return this.kelasList.length; },
       get denganWali()  { return this.kelasList.filter(k => k.guru_id).length; },
       get tanpaWali()   { return this.kelasList.filter(k => !k.guru_id).length; },

       get tingkatLabel() {
         const t = this.tingkatOptions.find(o => o.id === this.editTingkat);
         return t ? t.nama : '<?php echo esc_js( __( '— Pilih Tingkat —', 'absensi-sekolah' ) ); ?>';
       },
       get guruLabel() {
         const g = this.guruOptions.find(o => o.id === String(this.editGuruId));
         return g ? g.nama : '<?php echo esc_js( __( '— Pilih Wali Kelas —', 'absensi-sekolah' ) ); ?>';
       },

       tingkatColor(t) {
         if (t >= 10) return 'background:#F3E8FF;color:#7C3AED;';
         if (t >= 7)  return 'background:#EFF6FF;color:#2563EB;';
         if (t >= 1)  return 'background:#F0FDF4;color:#16A34A;';
         return 'background:rgba(0,0,0,.04);color:#64748B;';
       },
       inisial(nama) {
         return (nama || '?').split(' ').slice(0,2).map(w => w[0] || '').join('').toUpperCase() || '?';
       },

       openAdd() {
         this.editMode = false; this.editId = 0;
         this.editNama = ''; this.editTingkat = 0; this.editGuruId = '';
         this.fieldErrors = {};
         this.showModal = true;
       },
       openEdit(k) {
         this.editMode = true; this.editId = k.id;
         this.editNama = k.nama_kelas; this.editTingkat = k.tingkat || 0; this.editGuruId = k.guru_id || '';
         this.fieldErrors = {};
         this.showModal = true;
       },
       validateKelas() {
         const e = {};
         if (!this.editNama.trim()) e.nama = '<?php echo esc_js( __( 'Nama kelas wajib diisi.', 'absensi-sekolah' ) ); ?>';
         this.fieldErrors = e;
         return Object.keys(e).length === 0;
       },
       submitKelas() {
         if (!this.validateKelas()) return;
         this.$refs.addForm.submit();
       },
       deleteKelas(id, nama) {
         if (!confirm('<?php echo esc_js( __( 'Hapus kelas', 'absensi-sekolah' ) ); ?> \'' + nama + '\'? <?php echo esc_js( __( 'Tindakan tidak dapat dibatalkan.', 'absensi-sekolah' ) ); ?>')) return;
         this.$refs.deleteKelasId.value = id;
         this.$refs.deleteForm.submit();
       },
       init() {
         this.$watch('search',        () => { this.page = 1; });
         this.$watch('filterTingkat', () => { this.page = 1; });
       },
     }">

  <!-- Blobs -->
  <div class="kl-bg" aria-hidden="true">
    <div class="kl-blob kl-blob--1"></div>
    <div class="kl-blob kl-blob--2"></div>
    <div class="kl-blob kl-blob--3"></div>
  </div>

  <hr class="wp-header-end" style="margin:0;">

  <!-- ══ HERO ══ -->
  <div class="kl-hero">
    <!-- Decorative orbs -->
    <div class="kl-hero__orb kl-hero__orb--1" aria-hidden="true"></div>
    <div class="kl-hero__orb kl-hero__orb--2" aria-hidden="true"></div>
    <div class="kl-hero__left">
      <div class="kl-hero__eyebrow">
        <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
        <?php esc_html_e( 'Manajemen Kelas', 'absensi-sekolah' ); ?>
      </div>
      <h1 class="kl-hero__title"><?php esc_html_e( 'Daftar &', 'absensi-sekolah' ); ?> <span class="kl-hero__accent"><?php esc_html_e( 'Kelola Kelas', 'absensi-sekolah' ); ?></span></h1>
      <p class="kl-hero__sub"><?php esc_html_e( 'Kelola data kelas, tingkat, dan wali kelas untuk sistem absensi', 'absensi-sekolah' ); ?></p>
      <div class="kl-hero__chips">
        <span class="kl-chip kl-chip--glass">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
          <span x-text="totalKelas + ' <?php echo esc_js( __( 'Kelas', 'absensi-sekolah' ) ); ?>'"></span>
        </span>
        <span class="kl-chip kl-chip--green" x-show="denganWali > 0">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
          <span x-text="denganWali + ' <?php echo esc_js( __( 'Ada Wali', 'absensi-sekolah' ) ); ?>'"></span>
        </span>
        <span class="kl-chip kl-chip--orange" x-show="tanpaWali > 0">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01"/></svg>
          <span x-text="tanpaWali + ' <?php echo esc_js( __( 'Belum Wali', 'absensi-sekolah' ) ); ?>'"></span>
        </span>
      </div>
    </div>
    <div class="kl-hero__right">
      <!-- Mini donut — wali coverage -->
      <div class="kl-hero__donut-wrap" x-show="totalKelas > 0">
        <svg class="kl-hero__donut" viewBox="0 0 72 72" width="80" height="80">
          <circle cx="36" cy="36" r="28" fill="none" stroke="rgba(0,0,0,.05)" stroke-width="9"/>
          <circle cx="36" cy="36" r="28" fill="none" stroke="#16A34A" stroke-width="9"
            :stroke-dasharray="totalKelas > 0 ? (175.9 * denganWali / totalKelas) + ' 175.9' : '0 175.9'"
            stroke-dashoffset="44" transform="rotate(-90 36 36)" stroke-linecap="round"/>
          <text x="36" y="33" text-anchor="middle" font-family="'Plus Jakarta Sans',sans-serif" font-size="11" font-weight="800" fill="#1E293B"
            :textContent="totalKelas > 0 ? Math.round(denganWali/totalKelas*100)+'%' : '—'"></text>
          <text x="36" y="44" text-anchor="middle" font-family="'Plus Jakarta Sans',sans-serif" font-size="5" font-weight="700" fill="#64748B" letter-spacing="0.5">WALI</text>
        </svg>
        <p class="kl-hero__donut-label"><?php esc_html_e( 'Coverage', 'absensi-sekolah' ); ?></p>
      </div>
      <button type="button" @click="openAdd()" class="kl-btn kl-btn--primary kl-btn--lg">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
        <?php esc_html_e( 'Tambah Kelas', 'absensi-sekolah' ); ?>
      </button>
    </div>
  </div>

  <!-- ══ NOTICES ══ -->
  <?php if ( $saved_msg === 'saved' ) : ?>
  <div class="kl-notice kl-notice--success" role="alert">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <?php esc_html_e( 'Data kelas berhasil disimpan.', 'absensi-sekolah' ); ?>
  </div>
  <?php elseif ( $saved_msg === 'deleted' ) : ?>
  <div class="kl-notice kl-notice--warning" role="alert">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
    <?php esc_html_e( 'Kelas berhasil dihapus.', 'absensi-sekolah' ); ?>
  </div>
  <?php endif; ?>

  <!-- ══ STAT CARDS ══ -->
  <div class="kl-cards">
    <div class="kl-card kl-card--blue">
      <div class="kl-card__top">
        <div class="kl-card__icon" style="background:rgba(37,99,235,.12);color:#2563EB;">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814"/></svg>
        </div>
        <span class="kl-card__label"><?php esc_html_e( 'Total Kelas', 'absensi-sekolah' ); ?></span>
      </div>
      <div class="kl-card__value" x-text="totalKelas" style="color:#2563EB;"></div>
      <div class="kl-card__bar-wrap">
        <div class="kl-card__bar" style="background:rgba(37,99,235,.12);"><div class="kl-card__bar-fill" style="width:100%;background:#2563EB;"></div></div>
      </div>
      <p class="kl-card__sub"><?php esc_html_e( 'kelas terdaftar', 'absensi-sekolah' ); ?></p>
    </div>
    <div class="kl-card kl-card--green">
      <div class="kl-card__top">
        <div class="kl-card__icon" style="background:rgba(22,163,74,.12);color:#16A34A;">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
        </div>
        <span class="kl-card__label"><?php esc_html_e( 'Ada Wali Kelas', 'absensi-sekolah' ); ?></span>
      </div>
      <div class="kl-card__value" x-text="denganWali" style="color:#16A34A;"></div>
      <div class="kl-card__bar-wrap">
        <div class="kl-card__bar" style="background:rgba(22,163,74,.1);">
          <div class="kl-card__bar-fill" style="background:#16A34A;"
               :style="'width:' + (totalKelas > 0 ? Math.round(denganWali/totalKelas*100) : 0) + '%;background:#16A34A;'"></div>
        </div>
        <span class="kl-card__bar-pct" style="color:#16A34A;"
              x-text="totalKelas > 0 ? Math.round(denganWali/totalKelas*100)+'%' : '0%'"></span>
      </div>
      <p class="kl-card__sub"><?php esc_html_e( 'sudah ada wali', 'absensi-sekolah' ); ?></p>
    </div>
    <div class="kl-card kl-card--amber">
      <div class="kl-card__top">
        <div class="kl-card__icon" style="background:rgba(217,119,6,.12);color:#D97706;">
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <span class="kl-card__label"><?php esc_html_e( 'Belum Ada Wali', 'absensi-sekolah' ); ?></span>
      </div>
      <div class="kl-card__value" x-text="tanpaWali" style="color:#D97706;"></div>
      <div class="kl-card__bar-wrap">
        <div class="kl-card__bar" style="background:rgba(217,119,6,.1);">
          <div class="kl-card__bar-fill" style="background:#D97706;"
               :style="'width:' + (totalKelas > 0 ? Math.round(tanpaWali/totalKelas*100) : 0) + '%;background:#D97706;'"></div>
        </div>
        <span class="kl-card__bar-pct" style="color:#D97706;"
              x-text="totalKelas > 0 ? Math.round(tanpaWali/totalKelas*100)+'%' : '0%'"></span>
      </div>
      <p class="kl-card__sub"><?php esc_html_e( 'perlu ditugaskan', 'absensi-sekolah' ); ?></p>
    </div>
  </div>

  <!-- ══ TABLE PANEL ══ -->
  <div class="kl-panel">

    <!-- Panel head + inline search -->
    <div class="kl-panel__head">
      <div class="kl-panel__head-left">
        <div class="kl-panel__head-icon">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
        </div>
        <div>
          <p class="kl-panel__title"><?php esc_html_e( 'Daftar Kelas', 'absensi-sekolah' ); ?></p>
          <p class="kl-panel__sub"
             x-text="(search||filterTingkat) ? filteredList.length+' <?php echo esc_js( __( 'dari', 'absensi-sekolah' ) ); ?> '+kelasList.length+' <?php echo esc_js( __( 'kelas', 'absensi-sekolah' ) ); ?>' : kelasList.length+' <?php echo esc_js( __( 'kelas terdaftar', 'absensi-sekolah' ) ); ?>'"></p>
        </div>
      </div>
      <div class="kl-inline-filter">
        <div class="kl-filter-field" style="min-width:200px;">
          <svg class="kl-filter-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" x-model="search"
                 placeholder="<?php esc_attr_e( 'Cari kelas atau wali…', 'absensi-sekolah' ); ?>"
                 class="kl-input kl-input--pill kl-input--sm">
        </div>
        <button type="button" @click="search=''" x-show="search"
                class="kl-btn kl-btn--ghost kl-btn--sm" style="padding:5px 8px;flex-shrink:0;" title="Reset">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>
    </div>

    <!-- Tingkat filter tabs -->
    <div class="kl-tabs-row" x-show="uniqueTingkat.length > 1">
      <button type="button"
              class="kl-tab" :class="filterTingkat===0 ? 'kl-tab--active' : ''"
              @click="filterTingkat=0">
        <?php esc_html_e( 'Semua', 'absensi-sekolah' ); ?>
        <span class="kl-tab-count" x-text="kelasList.length"></span>
      </button>
      <template x-for="t in uniqueTingkat" :key="t">
        <button type="button"
                class="kl-tab" :class="filterTingkat===t ? 'kl-tab--active' : ''"
                @click="filterTingkat=t">
          <span x-text="'<?php echo esc_js( __( 'Kelas', 'absensi-sekolah' ) ); ?> ' + t"></span>
          <span class="kl-tab-count" x-text="countByTingkat(t)"></span>
        </button>
      </template>
    </div>

    <!-- Table -->
    <div class="kl-tbl-scroll">
      <table class="kl-tbl">
        <thead>
          <tr>
            <th class="kl-th--num">#</th>
            <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Tingkat', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Wali Kelas', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Siswa', 'absensi-sekolah' ); ?></th>
            <th><?php esc_html_e( 'Kehadiran Hari Ini', 'absensi-sekolah' ); ?></th>
            <th style="width:140px;"></th>
          </tr>
        </thead>
        <tbody>
          <template x-for="(k, i) in paginatedList" :key="k.id">
            <tr>
              <td class="kl-td--num" x-text="(page-1)*perPage + i + 1"></td>
              <td>
                <div class="kl-kelas-cell">
                  <div class="kl-kelas-avatar"
                       x-text="inisial(k.nama_kelas)"
                       :style="`background:hsl(${(k.id*61)%360},60%,92%);color:hsl(${(k.id*61)%360},50%,35%);`"></div>
                  <p class="kl-kelas-name" x-text="k.nama_kelas"></p>
                </div>
              </td>
              <td>
                <span x-show="k.tingkat > 0" class="kl-badge" :style="tingkatColor(k.tingkat)" x-text="'Kelas ' + k.tingkat"></span>
                <span x-show="!k.tingkat" class="kl-dash">—</span>
              </td>
              <td>
                <div x-show="k.nama_guru" class="kl-guru-cell">
                  <div class="kl-guru-avatar" x-text="inisial(k.nama_guru)"></div>
                  <span class="kl-guru-name" x-text="k.nama_guru"></span>
                </div>
                <span x-show="!k.nama_guru" class="kl-dash"><?php esc_html_e( 'Belum ditugaskan', 'absensi-sekolah' ); ?></span>
              </td>
              <td>
                <span class="kl-siswa-count" x-text="k.jumlah_siswa + ' <?php echo esc_js( __( 'siswa', 'absensi-sekolah' ) ); ?>'"></span>
              </td>
              <td>
                <div x-show="k.jumlah_siswa > 0" class="kl-hadir-cell">
                  <span class="kl-hadir-badge"
                        :class="k.sudah_absen===0
                          ? 'kl-hadir-badge--none'
                          : (k.sudah_absen/k.jumlah_siswa >= 0.8
                              ? 'kl-hadir-badge--good'
                              : (k.sudah_absen/k.jumlah_siswa >= 0.5
                                  ? 'kl-hadir-badge--mid'
                                  : 'kl-hadir-badge--low'))">
                    <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    <span x-text="k.sudah_absen > 0 ? k.sudah_absen+'/'+k.jumlah_siswa : '<?php echo esc_js( __( 'Belum ada', 'absensi-sekolah' ) ); ?>'"></span>
                  </span>
                  <div x-show="k.sudah_absen > 0" class="kl-hadir-bar">
                    <div class="kl-hadir-fill"
                         :class="k.sudah_absen/k.jumlah_siswa >= 0.8 ? 'kl-hadir-fill--good' : (k.sudah_absen/k.jumlah_siswa >= 0.5 ? 'kl-hadir-fill--mid' : 'kl-hadir-fill--low')"
                         :style="'width:'+Math.round(k.sudah_absen/k.jumlah_siswa*100)+'%'"></div>
                  </div>
                </div>
                <span x-show="!k.jumlah_siswa" class="kl-dash">—</span>
              </td>
              <td>
                <div class="kl-actions">
                  <button type="button" @click="openEdit(k)" class="kl-btn kl-btn--secondary kl-btn--sm">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                    <?php esc_html_e( 'Edit', 'absensi-sekolah' ); ?>
                  </button>
                  <button type="button" @click="deleteKelas(k.id, k.nama_kelas)" class="kl-btn kl-btn--danger kl-btn--sm">
                    <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    <?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?>
                  </button>
                </div>
              </td>
            </tr>
          </template>

          <!-- Empty -->
          <tr x-show="filteredList.length === 0">
            <td colspan="7">
              <div class="kl-empty">
                <div class="kl-empty__ico">
                  <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
                </div>
                <p class="kl-empty__title"
                   x-text="search ? '<?php echo esc_js( __( 'Tidak Ada Hasil', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Belum Ada Kelas', 'absensi-sekolah' ) ); ?>'"></p>
                <p class="kl-empty__sub"
                   x-text="search ? '<?php echo esc_js( __( 'Coba ubah kata kunci pencarian.', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Klik Tambah Kelas untuk mulai.', 'absensi-sekolah' ) ); ?>'"></p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Footer + Pagination -->
    <div x-show="filteredList.length > 0" class="kl-tbl-foot">
      <span class="kl-tbl-foot__info"
            x-text="filteredList.length <= perPage
              ? filteredList.length + ' <?php echo esc_js( __( 'kelas ditampilkan', 'absensi-sekolah' ) ); ?>'
              : ((page-1)*perPage+1) + '–' + Math.min(page*perPage, filteredList.length) + ' <?php echo esc_js( __( 'dari', 'absensi-sekolah' ) ); ?> ' + filteredList.length + ' <?php echo esc_js( __( 'kelas', 'absensi-sekolah' ) ); ?>'">
      </span>
      <div x-show="totalPages > 1" class="kl-pagination">
        <button class="kl-page-btn kl-page-btn--arrow" :disabled="page === 1" @click="page--" aria-label="<?php esc_attr_e( 'Sebelumnya', 'absensi-sekolah' ); ?>">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
        </button>
        <template x-for="(p, i) in pageRange" :key="i">
          <span x-show="p === '…'" class="kl-page-ellipsis">…</span>
          <button x-show="p !== '…'" type="button" class="kl-page-btn"
                  :class="p === page ? 'kl-page-btn--active' : ''"
                  @click="page = p" x-text="p"></button>
        </template>
        <button class="kl-page-btn kl-page-btn--arrow" :disabled="page === totalPages" @click="page++" aria-label="<?php esc_attr_e( 'Berikutnya', 'absensi-sekolah' ); ?>">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
        </button>
      </div>
    </div>

  </div><!-- /kl-panel -->

  <!-- ══ MODAL TAMBAH / EDIT ══ -->
  <div x-show="showModal" x-cloak class="kl-overlay" @keydown.escape.window="showModal = false">
    <div class="kl-modal" @click.stop>

      <div class="kl-modal__head">
        <div class="kl-modal__head-left">
          <div class="kl-modal__head-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814"/></svg>
          </div>
          <h2 class="kl-modal__title"
              x-text="editMode ? '<?php echo esc_js( __( 'Edit Kelas', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tambah Kelas Baru', 'absensi-sekolah' ) ); ?>'"></h2>
        </div>
        <button type="button" @click="showModal = false" class="kl-modal__close" aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form x-ref="addForm" method="post" class="kl-modal__form">
        <?php wp_nonce_field( 'absensi_kelas_action', '_wpnonce_kelas' ); ?>
        <input type="hidden" name="action_type" :value="editMode ? 'edit' : 'add'">
        <input type="hidden" name="kelas_id"   :value="editId">
        <input type="hidden" name="tingkat"    :value="editTingkat">
        <input type="hidden" name="guru_id"    :value="editGuruId">

        <div class="kl-modal__body">

          <!-- Nama Kelas -->
          <div class="kl-field">
            <label class="kl-label" for="kl-nama"><?php esc_html_e( 'Nama Kelas', 'absensi-sekolah' ); ?> <span class="kl-req">*</span></label>
            <input type="text" id="kl-nama" name="nama_kelas" x-model="editNama" @input="delete fieldErrors.nama"
                   placeholder="<?php esc_attr_e( 'Contoh: X IPA 1', 'absensi-sekolah' ); ?>"
                   class="kl-input" :class="fieldErrors.nama ? 'kl-input--error' : ''">
            <p x-show="fieldErrors.nama" x-text="fieldErrors.nama" class="kl-field-error" aria-live="polite"></p>
          </div>

          <!-- Tingkat -->
          <div class="kl-field">
            <label class="kl-label"><?php esc_html_e( 'Tingkat', 'absensi-sekolah' ); ?></label>
            <div x-data="{ open: false }" style="position:relative;" @click.outside="open=false">
              <button type="button" @click="open=!open" class="kl-input kl-select-btn">
                <span x-text="tingkatLabel" :style="!editTingkat ? 'color:#94A3B8' : 'color:#1E293B'"></span>
                <svg :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .2s;flex-shrink:0;color:#64748B;" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
              <div x-show="open" x-transition.opacity.duration.150ms class="kl-dropdown">
                <button type="button" @click="editTingkat=0; open=false" class="kl-dropdown__item" :class="{'kl-dropdown__item--active':!editTingkat}">
                  <?php esc_html_e( '— Pilih Tingkat —', 'absensi-sekolah' ); ?>
                </button>
                <template x-for="o in tingkatOptions" :key="o.id">
                  <button type="button" @click="editTingkat=o.id; open=false"
                          class="kl-dropdown__item" :class="{'kl-dropdown__item--active':editTingkat===o.id}"
                          x-text="o.nama"></button>
                </template>
              </div>
            </div>
          </div>

          <!-- Wali Kelas -->
          <div class="kl-field">
            <label class="kl-label"><?php esc_html_e( 'Wali Kelas', 'absensi-sekolah' ); ?></label>
            <div x-data="{ open: false }" style="position:relative;" @click.outside="open=false">
              <button type="button" @click="open=!open" class="kl-input kl-select-btn">
                <span x-text="guruLabel" :style="!editGuruId ? 'color:#94A3B8' : 'color:#1E293B'"></span>
                <svg :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .2s;flex-shrink:0;color:#64748B;" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
              </button>
              <div x-show="open" x-transition.opacity.duration.150ms class="kl-dropdown" style="max-height:220px;overflow-y:auto;">
                <button type="button" @click="editGuruId=''; open=false" class="kl-dropdown__item" :class="{'kl-dropdown__item--active':!editGuruId}">
                  <?php esc_html_e( '— Pilih Wali Kelas —', 'absensi-sekolah' ); ?>
                </button>
                <template x-for="g in guruOptions" :key="g.id">
                  <button type="button" @click="editGuruId=g.id; open=false"
                          class="kl-dropdown__item" :class="{'kl-dropdown__item--active':editGuruId===g.id}"
                          x-text="g.nama"></button>
                </template>
              </div>
            </div>
          </div>

        </div><!-- /kl-modal__body -->

        <div class="kl-modal__foot">
          <button type="button" @click="showModal = false" class="kl-btn kl-btn--secondary" style="flex:1;">
            <?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?>
          </button>
          <button type="button" @click="submitKelas()" class="kl-btn kl-btn--primary" style="flex:1;">
            <span x-text="editMode ? '<?php echo esc_js( __( 'Simpan Perubahan', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tambah Kelas', 'absensi-sekolah' ) ); ?>'"></span>
          </button>
        </div>
      </form>

    </div>
  </div>

  <!-- Hidden delete form — harus di dalam x-data agar x-ref terbaca Alpine -->
  <form x-ref="deleteForm" method="post" style="display:none;">
    <?php wp_nonce_field( 'absensi_kelas_action', '_wpnonce_kelas' ); ?>
    <input type="hidden" name="action_type" value="delete">
    <input type="hidden" name="kelas_id"   x-ref="deleteKelasId" value="">
  </form>

</div><!-- /kl-wrap -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap');

.kl-wrap *,.kl-wrap *::before,.kl-wrap *::after{box-sizing:border-box;}
.kl-wrap{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif!important;min-height:100vh;padding-bottom:48px;position:relative;z-index:0;}

body.wp-admin{background:#EAF0F6!important;}
#wpcontent,#wpbody-content,#wpbody{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed!important;}

/* ── BG BLOBS ── */
.kl-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}
.kl-blob{position:absolute;border-radius:50%;filter:blur(140px);opacity:1;}
.kl-blob--1{width:750px;height:750px;top:-180px;left:-120px;background:radial-gradient(circle,rgba(129,140,248,.55) 0%,rgba(99,102,241,.25) 65%,transparent 100%);}
.kl-blob--2{width:700px;height:700px;bottom:-150px;right:-80px;background:radial-gradient(circle,rgba(244,114,182,.50) 0%,rgba(219,39,119,.22) 65%,transparent 100%);}
.kl-blob--3{width:600px;height:600px;top:25%;right:10%;background:radial-gradient(circle,rgba(103,232,249,.52) 0%,rgba(6,182,212,.22) 65%,transparent 100%);}
.kl-hero,.kl-panel,.kl-cards{position:relative;z-index:1;}

/* ══ HERO ══ */
.kl-hero{background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border:1px solid rgba(255,255,255,.75);border-radius:24px;padding:28px 32px;margin:14px 0 16px;display:flex;align-items:center;justify-content:space-between;gap:24px;overflow:hidden;position:relative;z-index:1;box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);}
.kl-hero::after{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(37,99,235,.012) 1px,transparent 1px);background-size:22px 22px;pointer-events:none;}
/* Decorative floating orbs */
.kl-hero__orb{position:absolute;border-radius:50%;pointer-events:none;z-index:0;}
.kl-hero__orb--1{width:200px;height:200px;top:-70px;right:170px;background:radial-gradient(circle,rgba(37,99,235,.10) 0%,transparent 70%);filter:blur(35px);}
.kl-hero__orb--2{width:150px;height:150px;bottom:-55px;right:80px;background:radial-gradient(circle,rgba(124,58,237,.09) 0%,transparent 70%);filter:blur(28px);}
.kl-hero__left{flex:1;min-width:0;position:relative;z-index:1;}
.kl-hero__eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;color:#2563EB;background:#DBEAFE;padding:5px 11px;border-radius:8px;letter-spacing:.02em;text-transform:uppercase;margin:0 0 12px;border:1px solid rgba(37,99,235,.1);}
.kl-hero__title{font-size:22px;font-weight:800;color:#1E293B;margin:0 0 6px;line-height:1.25;}
.kl-hero__accent{background:linear-gradient(135deg,#2563EB 0%,#7C3AED 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.kl-hero__sub{font-size:13.5px;color:#64748B;margin:0 0 16px;}
.kl-hero__chips{display:flex;flex-wrap:wrap;gap:8px;}
.kl-chip{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:600;padding:5px 13px;border-radius:20px;}
.kl-chip--glass{background:rgba(255,255,255,.6);color:#334155;border:1px solid rgba(255,255,255,.8);}
.kl-chip--green{background:rgba(22,163,74,.1);color:#16A34A;border:1px solid rgba(22,163,74,.15);}
.kl-chip--orange{background:rgba(217,119,6,.1);color:#D97706;border:1px solid rgba(217,119,6,.15);}
.kl-hero__right{display:flex;flex-direction:column;align-items:center;gap:14px;flex-shrink:0;position:relative;z-index:1;}
.kl-hero__donut-wrap{display:flex;flex-direction:column;align-items:center;gap:4px;}
.kl-hero__donut{filter:drop-shadow(0 3px 8px rgba(163,177,198,.3));}
.kl-hero__donut-label{font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.08em;margin:0;}

/* ── NOTICES ── */
.kl-notice{display:flex;align-items:center;gap:10px;padding:13px 18px;border-radius:16px;font-size:13px;font-weight:600;margin:0 0 16px;backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid;position:relative;z-index:1;}
.kl-notice--success{background:rgba(240,253,244,.85);border-color:rgba(22,163,74,.2);color:#15803D;}
.kl-notice--warning{background:rgba(255,251,235,.85);border-color:rgba(217,119,6,.2);color:#B45309;}

/* ══ STAT CARDS ══ */
.kl-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:16px;}
@media(max-width:680px){.kl-cards{grid-template-columns:1fr;}}
.kl-card{background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border-radius:20px;padding:20px 22px 18px;border:1px solid rgba(255,255,255,.75);box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden;}
.kl-card::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(79,70,229,.01) 1px,transparent 1px);background-size:18px 18px;pointer-events:none;}
/* Per-card accent glow */
.kl-card--blue::after{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.12) 0%,transparent 70%);pointer-events:none;}
.kl-card--green::after{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:radial-gradient(circle,rgba(22,163,74,.12) 0%,transparent 70%);pointer-events:none;}
.kl-card--amber::after{content:'';position:absolute;top:-30px;right:-30px;width:100px;height:100px;border-radius:50%;background:radial-gradient(circle,rgba(217,119,6,.12) 0%,transparent 70%);pointer-events:none;}
.kl-card:hover{transform:translateY(-3px);box-shadow:10px 10px 30px rgba(163,177,198,.3),-10px -10px 30px rgba(255,255,255,.9),inset 0 1px 1px rgba(255,255,255,.8);}
.kl-card__top{display:flex;align-items:center;gap:10px;margin-bottom:14px;}
.kl-card__icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 1px rgba(255,255,255,.5),0 2px 6px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.03);}
.kl-card__label{font-size:12px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;}
.kl-card__value{font-size:36px;font-weight:800;line-height:1;margin:0 0 10px;letter-spacing:-.02em;font-family:'Plus Jakarta Sans',sans-serif;}
.kl-card__bar-wrap{display:flex;align-items:center;gap:8px;margin-bottom:8px;}
.kl-card__bar{flex:1;height:5px;background:rgba(0,0,0,.06);border-radius:99px;overflow:hidden;}
.kl-card__bar-fill{height:100%;border-radius:99px;transition:width .8s cubic-bezier(.4,0,.2,1);}
.kl-card__bar-pct{font-size:11px;font-weight:700;min-width:32px;text-align:right;font-family:'JetBrains Mono',monospace;}
.kl-card__sub{margin:0;font-size:11.5px;color:#64748B;}

/* ── PANEL ── */
.kl-panel{background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border-radius:24px;border:1px solid rgba(255,255,255,.75);box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);overflow:hidden;margin-bottom:16px;}
.kl-panel__head{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid rgba(0,0,0,.05);gap:10px;flex-wrap:wrap;background:rgba(255,255,255,.3);}
.kl-panel__head-left{display:flex;align-items:center;gap:10px;}
.kl-panel__head-icon{width:30px;height:30px;background:#DBEAFE;color:#2563EB;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.kl-panel__title{font-size:13.5px;font-weight:700;color:#1E293B;margin:0;}
.kl-panel__sub{font-size:11.5px;color:#64748B;margin:2px 0 0;}
.kl-inline-filter{display:flex;align-items:center;gap:8px;}
.kl-filter-field{position:relative;}
.kl-filter-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;pointer-events:none;}

/* ── INPUT / SELECT ── */
.kl-input{width:100%;box-sizing:border-box;background:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.8);border-radius:12px;padding:9px 12px;font-size:13.5px;min-height:42px;font-family:'Plus Jakarta Sans',sans-serif;color:#1E293B;outline:none;transition:border-color .15s,box-shadow .15s,background .15s;box-shadow:inset 2px 2px 5px rgba(163,177,198,.15),inset -2px -2px 5px rgba(255,255,255,.6);}
.kl-input:focus{border-color:rgba(37,99,235,.4);box-shadow:0 0 0 3px rgba(37,99,235,.08),inset 2px 2px 5px rgba(163,177,198,.1),inset -2px -2px 5px rgba(255,255,255,.7);background:rgba(255,255,255,.85);}
.kl-input.kl-input--pill{border-radius:999px !important;padding-left:36px;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.88);box-shadow:inset 4px 4px 10px rgba(163,177,198,.35),inset -4px -4px 10px rgba(255,255,255,.85);}
.kl-input.kl-input--pill:focus{background:rgba(255,255,255,.78);border-color:rgba(37,99,235,.25);box-shadow:inset 3px 3px 7px rgba(163,177,198,.25),inset -3px -3px 7px rgba(255,255,255,.8),0 0 0 3px rgba(37,99,235,.07);}
.kl-input--sm{min-height:36px !important;font-size:12.5px !important;padding-top:7px !important;padding-bottom:7px !important;}
.kl-select-btn{display:flex;align-items:center;justify-content:space-between;gap:8px;cursor:pointer;text-align:left;-webkit-appearance:none;appearance:none;}

/* modal input override — pill + neumorphic */
.kl-modal .kl-input{border-radius:999px !important;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.88);box-shadow:inset 4px 4px 10px rgba(163,177,198,.35),inset -4px -4px 10px rgba(255,255,255,.85);padding-left:18px;}
.kl-modal .kl-input:focus{background:rgba(255,255,255,.82);border-color:rgba(37,99,235,.28);box-shadow:0 0 0 3px rgba(37,99,235,.07),inset 3px 3px 7px rgba(163,177,198,.22),inset -3px -3px 7px rgba(255,255,255,.88);}

/* ── CUSTOM DROPDOWN ── */
.kl-dropdown{position:absolute;top:calc(100% + 6px);left:0;right:0;min-width:140px;background:rgba(255,255,255,.88);backdrop-filter:blur(20px) saturate(150%);-webkit-backdrop-filter:blur(20px) saturate(150%);border-radius:16px;border:1.5px solid rgba(255,255,255,.92);box-shadow:0 10px 36px rgba(15,23,42,.1),4px 4px 16px rgba(163,177,198,.18),-4px -4px 14px rgba(255,255,255,.7);overflow:hidden;z-index:1000;padding:6px;}
.kl-dropdown__item{display:flex;align-items:center;width:100%;padding:9px 14px;font-size:13px;font-weight:500;color:#334155;background:transparent;border:none;cursor:pointer;border-radius:10px;text-align:left;font-family:'Plus Jakarta Sans',sans-serif;transition:background .1s,color .1s;white-space:nowrap;}
.kl-dropdown__item:hover{background:rgba(37,99,235,.07);color:#1E293B;}
.kl-dropdown__item--active{background:rgba(37,99,235,.1);color:#2563EB;font-weight:600;}

/* ── TABLE ── */
.kl-tbl-scroll{overflow-x:auto;scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.08) transparent;}
.kl-tbl-scroll::-webkit-scrollbar{height:5px;}
.kl-tbl-scroll::-webkit-scrollbar-thumb{background:rgba(0,0,0,.08);border-radius:99px;}
.kl-tbl{width:100%;border-collapse:collapse;font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;}
.kl-tbl thead tr{background:rgba(0,0,0,.008);}
.kl-tbl th{text-align:left;padding:12px 16px;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#475569;border-bottom:1.5px solid rgba(0,0,0,.04);white-space:nowrap;}
.kl-th--num{width:42px;}
.kl-tbl td{padding:13px 16px;border-bottom:1.1px solid rgba(0,0,0,.03);color:#334155;vertical-align:middle;transition:background .15s ease,color .15s ease;}
.kl-tbl tbody tr{transition:transform .15s ease,background .15s ease;}
.kl-tbl tbody tr:last-child td{border-bottom:none;}
.kl-tbl tbody tr:hover{transform:translateY(-0.5px);background:rgba(37,99,235,.025);}
.kl-tbl tbody tr:hover td{color:#0F172A;}
.kl-td--num{color:#94A3B8;font-size:11px;font-family:'JetBrains Mono',monospace;font-weight:700;}
.kl-kelas-cell{display:flex;align-items:center;gap:11px;}
.kl-kelas-avatar{width:38px;height:38px;border-radius:11px;font-size:12.5px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 1px rgba(255,255,255,.5),0 2px 6px rgba(0,0,0,.05);border:1px solid rgba(0,0,0,.02);}
.kl-kelas-name{margin:0;font-weight:700;font-size:13.5px;color:#1E293B;}
.kl-badge{display:inline-flex;align-items:center;padding:3px 11px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.01em;}
.kl-guru-cell{display:flex;align-items:center;gap:8px;}
.kl-guru-avatar{width:28px;height:28px;border-radius:50%;background:rgba(37,99,235,.1);color:#2563EB;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:inset 0 1px 1px rgba(255,255,255,.4),0 1px 3px rgba(37,99,235,.1);}
.kl-guru-name{font-size:13px;color:#334155;font-weight:500;}
.kl-dash{color:#CBD5E1;font-size:12.5px;font-style:italic;}
.kl-siswa-count{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;background:rgba(255,255,255,.6);border:1px solid rgba(0,0,0,.05);font-size:11.5px;font-weight:600;color:#475569;font-family:'JetBrains Mono',monospace;box-shadow:inset 0 1px 1px rgba(255,255,255,.5);}
.kl-actions{display:flex;align-items:center;justify-content:flex-end;gap:5px;}
/* ── Tingkat tabs ── */
.kl-tabs-row{display:flex;align-items:center;gap:5px;padding:10px 18px;border-bottom:1px solid rgba(0,0,0,.04);background:rgba(255,255,255,.18);flex-wrap:wrap;}
.kl-tab{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:999px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid rgba(255,255,255,.82);background:rgba(255,255,255,.52);color:#64748B;transition:all .15s;box-shadow:2px 2px 6px rgba(163,177,198,.15),-1px -1px 4px rgba(255,255,255,.7);}
.kl-tab:hover:not(.kl-tab--active){background:rgba(255,255,255,.78);color:#334155;transform:translateY(-1px);}
.kl-tab--active{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:#fff;border-color:transparent;box-shadow:3px 3px 10px rgba(37,99,235,.3),-1px -1px 4px rgba(255,255,255,.35),inset 0 1px 0 rgba(255,255,255,.22);}
.kl-tab-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;padding:0 4px;border-radius:999px;font-size:10px;font-weight:700;}
.kl-tab--active .kl-tab-count{background:rgba(255,255,255,.25);color:#fff;}
.kl-tab:not(.kl-tab--active) .kl-tab-count{background:rgba(0,0,0,.07);color:#64748B;}
/* ── Kehadiran hari ini ── */
.kl-hadir-cell{display:flex;flex-direction:column;gap:4px;}
.kl-hadir-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 8px;border-radius:999px;font-size:11px;font-weight:700;font-family:'JetBrains Mono',monospace;width:fit-content;}
.kl-hadir-badge--none{background:rgba(0,0,0,.04);color:#94A3B8;}
.kl-hadir-badge--good{background:rgba(22,163,74,.1);color:#16A34A;}
.kl-hadir-badge--mid{background:rgba(217,119,6,.1);color:#D97706;}
.kl-hadir-badge--low{background:rgba(220,38,38,.1);color:#DC2626;}
.kl-hadir-bar{width:60px;height:4px;background:rgba(0,0,0,.06);border-radius:999px;overflow:hidden;}
.kl-hadir-fill{height:100%;border-radius:999px;transition:width .4s ease;}
.kl-hadir-fill--good{background:#16A34A;}
.kl-hadir-fill--mid{background:#D97706;}
.kl-hadir-fill--low{background:#DC2626;}
.kl-tbl-foot{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:11px 18px;border-top:1px solid rgba(0,0,0,.04);font-size:12px;color:#64748B;background:rgba(255,255,255,.15);}
.kl-tbl-foot__info{font-size:12px;color:#64748B;}
.kl-pagination{display:flex;align-items:center;gap:4px;}
.kl-page-btn{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 6px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1.5px solid rgba(255,255,255,.80);background:rgba(255,255,255,.55);color:#475569;transition:all .15s;box-shadow:2px 2px 5px rgba(163,177,198,.15),-1px -1px 3px rgba(255,255,255,.7);}
.kl-page-btn:hover:not(:disabled):not(.kl-page-btn--active){background:rgba(255,255,255,.85);color:#1E293B;transform:translateY(-1px);}
.kl-page-btn--active{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:#fff;border-color:transparent;box-shadow:3px 3px 8px rgba(37,99,235,.3),inset 0 1px 0 rgba(255,255,255,.2);}
.kl-page-btn--arrow{border-radius:8px;}
.kl-page-btn:disabled{opacity:.35;cursor:not-allowed;transform:none;}
.kl-page-ellipsis{display:inline-flex;align-items:center;justify-content:center;min-width:24px;height:30px;font-size:12px;color:#94A3B8;letter-spacing:.05em;}
.kl-empty{display:flex;flex-direction:column;align-items:center;gap:8px;padding:56px 24px;text-align:center;}
.kl-empty__ico{width:56px;height:56px;background:rgba(0,0,0,.02);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#94A3B8;margin-bottom:4px;border:1.5px dashed rgba(0,0,0,.04);}
.kl-empty__title{margin:0;font-size:13px;font-weight:700;color:#475569;}
.kl-empty__sub{margin:0;font-size:12px;color:#64748B;}

/* ── BUTTONS ── */
.kl-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 22px;border-radius:999px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:42px;font-family:'Plus Jakarta Sans',sans-serif;transition:transform .15s,box-shadow .15s,background .15s;text-decoration:none;white-space:nowrap;letter-spacing:.01em;}
.kl-btn--sm{padding:5px 14px;font-size:12px;min-height:32px;}
.kl-btn--lg{padding:12px 28px;font-size:14px;min-height:48px;}
.kl-btn--primary{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:white;border:none;box-shadow:5px 5px 14px rgba(37,99,235,.35),-2px -2px 8px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.28);}
.kl-btn--primary:hover:not(:disabled){box-shadow:7px 7px 20px rgba(37,99,235,.45),-3px -3px 10px rgba(255,255,255,.6),inset 0 1px 1px rgba(255,255,255,.3);transform:translateY(-2px);}
.kl-btn--primary:active:not(:disabled){box-shadow:inset 4px 4px 10px rgba(15,23,42,.25),inset -2px -2px 6px rgba(255,255,255,.12);transform:translateY(0);}
.kl-btn--primary:disabled{opacity:.45;cursor:not-allowed;}
.kl-btn--secondary{background:rgba(255,255,255,.65);backdrop-filter:blur(12px) saturate(130%);color:#475569;border:1.5px solid rgba(255,255,255,.88);box-shadow:4px 4px 12px rgba(163,177,198,.28),-3px -3px 9px rgba(255,255,255,.82);}
.kl-btn--secondary:hover{background:rgba(255,255,255,.88);color:#1E293B;box-shadow:6px 6px 16px rgba(163,177,198,.32),-4px -4px 12px rgba(255,255,255,.9);transform:translateY(-1.5px);}
.kl-btn--secondary:active{box-shadow:inset 3px 3px 8px rgba(163,177,198,.22),inset -2px -2px 6px rgba(255,255,255,.78);transform:translateY(0);}
.kl-btn--ghost{background:transparent;color:#64748B;border:1px solid transparent;}
.kl-btn--ghost:hover{background:rgba(255,255,255,.5);color:#334155;box-shadow:2px 2px 6px rgba(163,177,198,.18),-1px -1px 4px rgba(255,255,255,.6);border-color:rgba(255,255,255,.7);}
.kl-btn--danger{background:linear-gradient(145deg,#ef4444,#b91c1c);color:white;box-shadow:4px 4px 12px rgba(220,38,38,.3),-1px -1px 5px rgba(255,255,255,.35),inset 0 1px 1px rgba(255,255,255,.2);}
.kl-btn--danger:hover{box-shadow:5px 5px 16px rgba(220,38,38,.4),-2px -2px 7px rgba(255,255,255,.4);transform:translateY(-1.5px);}
.kl-btn--danger:active{box-shadow:inset 3px 3px 8px rgba(100,0,0,.25),inset -1px -1px 4px rgba(255,255,255,.1);transform:translateY(0);}

/* ── MODAL ── */
.kl-overlay{position:fixed;inset:0;background:rgba(15,23,42,.28);backdrop-filter:blur(8px) saturate(110%);-webkit-backdrop-filter:blur(8px) saturate(110%);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;}
.kl-modal{background:rgba(255,255,255,.80);backdrop-filter:blur(24px) saturate(150%);-webkit-backdrop-filter:blur(24px) saturate(150%);border-radius:24px;width:100%;max-width:440px;border:1.5px solid rgba(255,255,255,.90);box-shadow:0 24px 60px rgba(15,23,42,.1),6px 6px 20px rgba(163,177,198,.2),-6px -6px 20px rgba(255,255,255,.7),inset 0 1px 0 rgba(255,255,255,.95);}
.kl-modal__form{display:flex;flex-direction:column;}
.kl-modal__head{display:flex;align-items:center;justify-content:space-between;padding:20px 24px 16px;border-bottom:1px solid rgba(163,177,198,.16);}
.kl-modal__head-left{display:flex;align-items:center;gap:12px;}
.kl-modal__head-icon{width:36px;height:36px;background:#DBEAFE;color:#2563EB;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.kl-modal__title{font-size:15px;font-weight:700;color:#0F172A;margin:0;}
.kl-modal__close{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:8px;background:rgba(255,255,255,.6);border:1px solid rgba(163,177,198,.2);cursor:pointer;color:#94A3B8;transition:all .12s;}
.kl-modal__close:hover{background:rgba(255,255,255,.9);color:#475569;}
.kl-modal__body{padding:20px 24px;display:flex;flex-direction:column;gap:16px;}
.kl-modal__foot{display:flex;gap:10px;padding:14px 24px 22px;border-top:1px solid rgba(163,177,198,.16);}
.kl-field{display:flex;flex-direction:column;gap:7px;}
.kl-label{font-size:12.5px;font-weight:600;color:#334155;}
.kl-req{color:#DC2626;}
.kl-input--error{border-color:rgba(220,38,38,.45) !important;box-shadow:0 0 0 3px rgba(220,38,38,.07),inset 2px 2px 5px rgba(220,38,38,.08),inset -2px -2px 5px rgba(255,255,255,.85) !important;}
.kl-field-error{margin:0;font-size:11.5px;font-weight:600;color:#DC2626;padding-left:14px;}

[x-cloak]{display:none!important;}
</style>
