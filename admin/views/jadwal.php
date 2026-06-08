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

<div class="wrap absensi-admin-wrap" style="max-width:960px;" x-data="jadwalManager">
  <hr class="wp-header-end" style="margin:0;">

  <!-- Page Header -->
  <div class="absensi-page-header">
    <div>
      <h1 class="absensi-page-title"><?php esc_html_e( 'Jadwal Per Kelas', 'absensi-sekolah' ); ?></h1>
      <p class="absensi-page-subtitle"><?php esc_html_e( 'Konfigurasi waktu masuk dan pulang spesifik per kelas', 'absensi-sekolah' ); ?></p>
    </div>
    <button type="button" @click="openAdd()" class="absensi-btn absensi-btn-primary">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      <?php esc_html_e( 'Tambah Jadwal', 'absensi-sekolah' ); ?>
    </button>
  </div>

  <!-- Error -->
  <div x-show="error" x-cloak class="absensi-alert absensi-alert-danger" style="margin-bottom:16px;">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
    <span x-text="error"></span>
  </div>

  <!-- Table -->
  <div style="background:white;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <!-- Loading -->
    <div x-show="loading" x-cloak style="padding:48px;text-align:center;color:#9ca3af;">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" class="spin" style="margin:0 auto 8px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
      <?php esc_html_e( 'Memuat jadwal...', 'absensi-sekolah' ); ?>
    </div>

    <!-- Empty -->
    <div x-show="!loading && rows.length === 0" x-cloak style="display:flex;flex-direction:column;align-items:center;padding:56px;text-align:center;color:#9ca3af;">
      <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin-bottom:12px;color:#d1d5db;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
      <p style="font-size:14px;font-weight:600;color:#374151;margin:0 0 4px;"><?php esc_html_e( 'Belum Ada Jadwal', 'absensi-sekolah' ); ?></p>
      <p style="font-size:12px;margin:0;"><?php esc_html_e( 'Klik "Tambah Jadwal" untuk mulai mengatur jam per kelas.', 'absensi-sekolah' ); ?></p>
    </div>

    <!-- Table rows -->
    <table class="absensi-table" x-show="!loading && rows.length > 0" x-cloak>
      <thead>
        <tr>
          <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
          <th><?php esc_html_e( 'Hari', 'absensi-sekolah' ); ?></th>
          <th><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></th>
          <th><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?></th>
          <th style="width:140px;"></th>
        </tr>
      </thead>
      <tbody>
        <template x-for="row in rows" :key="row.id">
          <tr>
            <td style="font-weight:700;font-size:14px;color:#111827;" x-text="row.nama_kelas ?? kelasNama(row.kelas_id)"></td>
            <td>
              <span style="display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;background:#EFF6FF;color:#2563EB;"
                    x-text="HARI[row.hari] ?? row.hari"></span>
            </td>
            <td style="font-family:monospace;font-weight:700;font-size:13.5px;color:#111827;" x-text="row.jam_masuk?.slice(0,5)"></td>
            <td style="font-family:monospace;font-weight:700;font-size:13.5px;color:#111827;" x-text="row.jam_keluar?.slice(0,5)"></td>
            <td style="text-align:right;white-space:nowrap;">
              <button type="button" @click="openEdit(row)" class="absensi-btn absensi-btn-secondary absensi-btn-sm" style="margin-right:5px;">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                <?php esc_html_e( 'Edit', 'absensi-sekolah' ); ?>
              </button>
              <button type="button"
                      @click="del(row.id, (HARI[row.hari] ?? row.hari) + ' – ' + (row.nama_kelas ?? kelasNama(row.kelas_id)))"
                      class="absensi-btn absensi-btn-danger absensi-btn-sm">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                <?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?>
              </button>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>

  <!-- Modal Tambah / Edit -->
  <div x-show="showModal" x-cloak class="absensi-modal-overlay" @keydown.escape.window="showModal = false">
    <div class="absensi-modal-box" @click.stop>
      <div class="absensi-modal-header">
        <h2 class="absensi-modal-title" x-text="isEditing ? '<?php echo esc_js( __( 'Edit Jadwal', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tambah Jadwal', 'absensi-sekolah' ) ); ?>'"></h2>
        <button type="button" @click="showModal = false" class="absensi-modal-close" aria-label="Tutup">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div style="display:flex;flex-direction:column;gap:14px;">
        <!-- Kelas -->
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
          <select x-model="form.kelas_id" class="absensi-input" :disabled="isEditing">
            <option value=""><?php esc_html_e( '— Pilih Kelas —', 'absensi-sekolah' ); ?></option>
            <template x-for="k in kelasList" :key="k.id">
              <option :value="String(k.id)" x-text="k.nama_kelas"></option>
            </template>
          </select>
          <p x-show="kelasList.length === 0" style="font-size:12px;color:#9ca3af;margin:2px 0 0;"><?php esc_html_e( 'Belum ada kelas. Tambah kelas terlebih dahulu.', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- Hari -->
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'Hari', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
          <select x-model.number="form.hari" class="absensi-input">
            <template x-for="(nama, idx) in HARI" :key="idx">
              <option x-show="idx > 0" :value="idx" x-text="nama"></option>
            </template>
          </select>
        </div>

        <!-- Jam -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="absensi-input-group">
            <label class="absensi-label"><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
            <input type="time" x-model="form.jam_masuk" class="absensi-input" style="font-family:monospace;font-size:16px;font-weight:600;">
          </div>
          <div class="absensi-input-group">
            <label class="absensi-label"><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
            <input type="time" x-model="form.jam_keluar" class="absensi-input" style="font-family:monospace;font-size:16px;font-weight:600;">
          </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:6px;">
          <button type="button" @click="showModal = false" class="absensi-btn absensi-btn-secondary" style="flex:1;">
            <?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?>
          </button>
          <button type="button" @click="save()" :disabled="saving || !form.kelas_id" class="absensi-btn absensi-btn-primary" style="flex:1;">
            <span x-show="saving" class="spin-inline"></span>
            <span x-text="isEditing ? '<?php echo esc_js( __( 'Simpan Perubahan', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tambah Jadwal', 'absensi-sekolah' ) ); ?>'"></span>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-admin-wrap{font-family:'Plus Jakarta Sans',sans-serif!important;background:#F5F7FB;min-height:100vh;padding-bottom:24px;}
.absensi-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:12px 0 24px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;}
.absensi-page-title{font-size:19px;font-weight:700;color:#111827;margin:0 0 3px;}
.absensi-page-subtitle{font-size:13px;color:#6b7280;margin:0;}
.absensi-alert{display:flex;align-items:flex-start;gap:9px;padding:11px 14px;border-radius:8px;font-size:13px;font-weight:600;}
.absensi-alert-danger{background:#FEF2F2;border:1px solid #fecaca;color:#DC2626;}
.absensi-input-group{display:flex;flex-direction:column;gap:5px;}
.absensi-label{font-size:11.5px;font-weight:600;color:#374151;}
.absensi-input{border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13.5px;min-height:40px;font-family:inherit;background:white;color:#111827;outline:none;width:100%;box-sizing:border-box;transition:border-color .15s,box-shadow .15s;}
.absensi-input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.absensi-input:disabled{background:#f3f4f6;cursor:not-allowed;}
.absensi-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 15px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:38px;font-family:inherit;transition:background .12s,border-color .12s;text-decoration:none;}
.absensi-btn-sm{padding:5px 11px;font-size:12.5px;min-height:32px;}
.absensi-btn-primary{background:#2563EB;color:white;}
.absensi-btn-primary:hover:not(:disabled){background:#1D4ED8;}
.absensi-btn-primary:disabled{opacity:.6;cursor:not-allowed;}
.absensi-btn-secondary{background:white;color:#374151;border:1px solid #d1d5db;}
.absensi-btn-secondary:hover{background:#f9fafb;border-color:#9ca3af;}
.absensi-btn-danger{background:#dc2626;color:white;}
.absensi-btn-danger:hover{background:#b91c1c;}
.absensi-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.absensi-table thead tr{background:#f9fafb;border-bottom:1px solid #e5e7eb;}
.absensi-table th{text-align:left;padding:10px 14px;color:#6b7280;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;}
.absensi-table td{padding:11px 14px;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
.absensi-table tbody tr:hover td{background:#f9fafb;}
.absensi-modal-overlay{position:fixed;inset:0;background:rgba(17,24,39,.5);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;}
.absensi-modal-box{background:white;border-radius:14px;padding:24px;width:100%;max-width:440px;box-shadow:0 20px 50px rgba(0,0,0,.15);}
.absensi-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.absensi-modal-title{font-size:16px;font-weight:700;color:#111827;margin:0;}
.absensi-modal-close{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;background:transparent;border:none;cursor:pointer;color:#9ca3af;transition:background .12s,color .12s;}
.absensi-modal-close:hover{background:#f3f4f6;color:#374151;}
@keyframes spin{to{transform:rotate(360deg)}}
.spin{animation:spin 1s linear infinite;}
.spin-inline{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;}
[x-cloak]{display:none!important;}
</style>
