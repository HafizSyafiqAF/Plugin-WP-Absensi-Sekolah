<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ABSENSI_ADMIN_ASSETS' ) ) :
    define( 'ABSENSI_ADMIN_ASSETS', true ); ?>
<link rel="stylesheet" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/app.css' ); ?>">
<script type="module" src="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/admin.js' ); ?>"></script>
<?php endif;
global $wpdb;
$kelas_list = $wpdb->get_results( "SELECT id, nama_kelas FROM {$wpdb->prefix}absensi_kelas ORDER BY nama_kelas" );
?>
<div class="wrap absensi-admin-wrap"
     x-data="{
       siswaList: [],
       loading: false,
       error: null,
       search: '',
       filterKelas: '',
       showModal: false,
       editData: null,
       saving: false,
       saveError: null,

       get filteredList() {
         let list = this.siswaList;
         if (this.search.trim()) {
           const q = this.search.toLowerCase();
           list = list.filter(s => (s.nama ?? '').toLowerCase().includes(q) || (s.nis ?? '').toLowerCase().includes(q));
         }
         if (this.filterKelas) {
           list = list.filter(s => String(s.kelas_id) === String(this.filterKelas));
         }
         return list;
       },

       async loadSiswa() {
         this.loading = true; this.error = null;
         try {
           const cfg = window.AbsensiAdmin ?? {};
           const res = await fetch((cfg.restUrl ?? '/wp-json/absensi/v1/') + 'siswa', {
             headers: { 'X-WP-Nonce': cfg.nonce ?? '' }
           });
           if (!res.ok) throw new Error('Gagal memuat data siswa.');
           const data = await res.json();
           this.siswaList = Array.isArray(data) ? data : (data.data ?? []);
         } catch (e) { this.error = e.message; }
         finally { this.loading = false; }
       },

       openAdd() {
         this.editData  = { id: null, nama: '', nis: '', kelas_id: '' };
         this.saveError = null;
         this.showModal = true;
       },

       openEdit(s) {
         this.editData  = { id: s.id, nama: s.nama, nis: s.nis, kelas_id: s.kelas_id ?? '' };
         this.saveError = null;
         this.showModal = true;
       },

       async save() {
         if (!this.editData) return;
         this.saving = true; this.saveError = null;
         try {
           const cfg   = window.AbsensiAdmin ?? {};
           const isNew = !this.editData.id;
           const url   = (cfg.restUrl ?? '/wp-json/absensi/v1/') + (isNew ? 'siswa' : 'siswa/' + this.editData.id);
           const res = await fetch(url, {
             method: isNew ? 'POST' : 'PUT',
             headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce ?? '' },
             body: JSON.stringify({ nama: this.editData.nama ?? '', nis: this.editData.nis ?? '', kelas_id: parseInt(this.editData.kelas_id) || 0 }),
           });
           if (!res.ok) { const d = await res.json(); throw new Error(d.message ?? 'Gagal menyimpan.'); }
           this.showModal = false;
           this.loadSiswa();
         } catch (e) { this.saveError = e.message; }
         finally { this.saving = false; }
       },

       async deleteSiswa(id, nama) {
         if (!confirm('Hapus ' + nama + '? Tindakan tidak dapat dibatalkan.')) return;
         const cfg = window.AbsensiAdmin ?? {};
         const res = await fetch((cfg.restUrl ?? '/wp-json/absensi/v1/') + 'siswa/' + id, {
           method: 'DELETE', headers: { 'X-WP-Nonce': cfg.nonce ?? '' },
         });
         if (res.ok) this.loadSiswa();
       },

       inisial(nama) { return (nama ?? '?').split(' ').slice(0,2).map(w => w[0]).join('').toUpperCase(); },
       openWali(id, nama) {
         window.dispatchEvent(new CustomEvent('open-wali-linker', { detail: { siswaId: id, siswaName: nama } }));
       },
       init() { this.loadSiswa(); },
     }">

  <hr class="wp-header-end" style="margin:0;">

  <!-- Page Header -->
  <div class="absensi-page-header">
    <div>
      <h1 class="absensi-page-title"><?php esc_html_e( 'Manajemen Siswa', 'absensi-sekolah' ); ?></h1>
      <p class="absensi-page-subtitle"><?php esc_html_e( 'Kelola data siswa, kelas, dan kartu RFID', 'absensi-sekolah' ); ?></p>
    </div>
    <button type="button" @click="openAdd()" class="absensi-btn absensi-btn-primary">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
      <?php esc_html_e( 'Tambah Siswa', 'absensi-sekolah' ); ?>
    </button>
  </div>

  <!-- Filter bar -->
  <div class="absensi-filter-bar" style="margin-bottom:20px;">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
      <div style="position:relative;flex:1;min-width:200px;">
        <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
        </svg>
        <input type="search" x-model="search"
               placeholder="<?php esc_attr_e( 'Cari nama atau NIS…', 'absensi-sekolah' ); ?>"
               class="absensi-input" style="padding-left:34px;">
      </div>
      <select x-model="filterKelas" class="absensi-input" style="min-width:150px;flex:0 0 auto;">
        <option value=""><?php esc_html_e( 'Semua Kelas', 'absensi-sekolah' ); ?></option>
        <?php foreach ( $kelas_list as $k ) : ?>
          <option value="<?php echo esc_attr( $k->id ); ?>"><?php echo esc_html( $k->nama_kelas ); ?></option>
        <?php endforeach; ?>
      </select>
      <button type="button" @click="search=''; filterKelas=''" x-show="search || filterKelas"
              class="absensi-btn absensi-btn-ghost" style="flex:0 0 auto;">
        <?php esc_html_e( '✕ Reset', 'absensi-sekolah' ); ?>
      </button>
    </div>
  </div>

  <!-- Loading -->
  <div x-show="loading" style="text-align:center;padding:56px;color:#9ca3af;">
    <div style="display:inline-block;width:24px;height:24px;border:2px solid #e5e7eb;border-top-color:#2563EB;border-radius:50%;animation:spin .7s linear infinite;margin-bottom:10px;"></div>
    <p style="margin:0;font-size:13px;"><?php esc_html_e( 'Memuat data…', 'absensi-sekolah' ); ?></p>
  </div>

  <!-- Error -->
  <div x-show="!loading && error" class="absensi-alert absensi-alert-danger" aria-live="polite">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
    <span x-text="error"></span>
  </div>

  <!-- Table -->
  <div x-show="!loading" style="background:white;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
    <table class="absensi-table">
      <thead>
        <tr>
          <th><?php esc_html_e( 'Nama', 'absensi-sekolah' ); ?></th>
          <th><?php esc_html_e( 'NIS', 'absensi-sekolah' ); ?></th>
          <th><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></th>
          <th><?php esc_html_e( 'Kartu RFID', 'absensi-sekolah' ); ?></th>
          <th style="width:160px;"></th>
        </tr>
      </thead>
      <tbody>
        <template x-for="(s, i) in filteredList" :key="s.id">
          <tr>
            <td>
              <div style="display:flex;align-items:center;gap:9px;">
                <div class="absensi-avatar" x-text="inisial(s.nama)"></div>
                <span style="font-weight:600;color:#111827;" x-text="s.nama"></span>
              </div>
            </td>
            <td style="font-family:monospace;color:#6b7280;font-size:13px;" x-text="s.nis"></td>
            <td>
              <span x-show="s.nama_kelas" class="absensi-badge absensi-badge-primary" x-text="s.nama_kelas"></span>
              <span x-show="!s.nama_kelas" style="color:#d1d5db;">—</span>
            </td>
            <td>
              <span x-show="s.rfid_uid"
                    style="display:inline-block;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;font-family:monospace;background:#f3f4f6;color:#111827;letter-spacing:.05em;border:1px solid #e5e7eb;"
                    x-text="s.rfid_uid"></span>
              <span x-show="!s.rfid_uid" style="font-size:12px;color:#9ca3af;font-style:italic;"><?php esc_html_e( 'Belum terdaftar', 'absensi-sekolah' ); ?></span>
            </td>
            <td style="text-align:right;white-space:nowrap;">
              <button type="button" @click="openWali(s.id, s.nama)" class="absensi-btn absensi-btn-secondary absensi-btn-sm" style="margin-right:5px;">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                <?php esc_html_e( 'Wali', 'absensi-sekolah' ); ?>
              </button>
              <button type="button" @click="openEdit(s)" class="absensi-btn absensi-btn-secondary absensi-btn-sm" style="margin-right:5px;">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                <?php esc_html_e( 'Edit', 'absensi-sekolah' ); ?>
              </button>
              <button type="button" @click="deleteSiswa(s.id, s.nama)" class="absensi-btn absensi-btn-danger absensi-btn-sm">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                <?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?>
              </button>
            </td>
          </tr>
        </template>

        <!-- Empty -->
        <tr x-show="filteredList.length === 0 && !loading">
          <td colspan="5" style="padding:56px;text-align:center;color:#9ca3af;">
            <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;color:#d1d5db;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
            <p style="margin:0;font-size:14px;font-weight:600;color:#374151;"
               x-text="search || filterKelas ? '<?php echo esc_js( __( 'Tidak Ada Hasil', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Belum Ada Siswa', 'absensi-sekolah' ) ); ?>'"></p>
            <p style="margin:4px 0 0;font-size:12px;"
               x-text="search || filterKelas ? '<?php echo esc_js( __( 'Coba ubah kata kunci pencarian.', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Klik Tambah Siswa untuk mulai.', 'absensi-sekolah' ) ); ?>'"></p>
          </td>
        </tr>
      </tbody>
    </table>

    <div x-show="filteredList.length > 0"
         style="padding:10px 16px;border-top:1px solid #f3f4f6;font-size:12px;color:#9ca3af;display:flex;justify-content:space-between;">
      <span x-text="filteredList.length + ' <?php echo esc_js( __( 'siswa', 'absensi-sekolah' ) ); ?>'"></span>
      <span x-show="siswaList.length !== filteredList.length"
            x-text="'<?php echo esc_js( __( 'dari', 'absensi-sekolah' ) ); ?> ' + siswaList.length + ' <?php echo esc_js( __( 'total', 'absensi-sekolah' ) ); ?>'"></span>
    </div>
  </div>

  <!-- Modal -->
  <div x-show="showModal" x-cloak class="absensi-modal-overlay" @keydown.escape.window="showModal = false">
    <div class="absensi-modal-box" @click.stop>
      <div class="absensi-modal-header">
        <h2 class="absensi-modal-title"
            x-text="editData?.id ? '<?php echo esc_js( __( 'Edit Siswa', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tambah Siswa Baru', 'absensi-sekolah' ) ); ?>'"></h2>
        <button type="button" @click="showModal = false" class="absensi-modal-close" aria-label="Tutup">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <div style="display:flex;flex-direction:column;gap:14px;">
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'Nama Lengkap', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
          <input type="text" x-model="editData.nama" required
                 placeholder="<?php esc_attr_e( 'Nama lengkap siswa', 'absensi-sekolah' ); ?>"
                 class="absensi-input">
        </div>
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'NIS', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
          <input type="text" x-model="editData.nis" required
                 placeholder="<?php esc_attr_e( 'Nomor Induk Siswa', 'absensi-sekolah' ); ?>"
                 class="absensi-input" style="font-family:monospace;">
        </div>
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?> <span style="color:#DC2626;">*</span></label>
          <select x-model="editData.kelas_id" class="absensi-input">
            <option value=""><?php esc_html_e( '— Pilih Kelas —', 'absensi-sekolah' ); ?></option>
            <?php foreach ( $kelas_list as $k ) : ?>
              <option value="<?php echo esc_attr( $k->id ); ?>"><?php echo esc_html( $k->nama_kelas ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div x-show="saveError" class="absensi-alert absensi-alert-danger" style="margin-top:12px;" aria-live="polite">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <span x-text="saveError"></span>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px;">
        <button type="button" @click="showModal = false" class="absensi-btn absensi-btn-secondary" style="flex:1;">
          <?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?>
        </button>
        <button type="button" @click="save()" :disabled="saving" class="absensi-btn absensi-btn-primary" style="flex:1;">
          <div x-show="saving" style="width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;"></div>
          <span x-text="saving ? '<?php echo esc_js( __( 'Menyimpan…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Simpan', 'absensi-sekolah' ) ); ?>'"></span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ── Modal WaliLinker ── -->
<div x-data="waliLinker" x-cloak>
  <div x-show="open" class="absensi-modal-overlay" @keydown.escape.window="close()">
    <div class="absensi-modal-box" style="max-width:520px;max-height:85vh;overflow-y:auto;" @click.stop>

      <!-- Header -->
      <div class="absensi-modal-header">
        <div>
          <h2 class="absensi-modal-title"><?php esc_html_e( 'Orang Tua / Wali', 'absensi-sekolah' ); ?></h2>
          <p style="font-size:12.5px;color:#6b7280;margin:3px 0 0;" x-text="siswaName"></p>
        </div>
        <button type="button" @click="close()" class="absensi-modal-close" aria-label="Tutup">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <!-- Wali Terhubung -->
      <div style="margin-bottom:20px;">
        <p style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748B;margin:0 0 10px;"><?php esc_html_e( 'Terhubung Saat Ini', 'absensi-sekolah' ); ?></p>

        <div x-show="loadingWali" style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">
          <?php esc_html_e( 'Memuat…', 'absensi-sekolah' ); ?>
        </div>

        <div x-show="!loadingWali && walis.length === 0" style="text-align:center;padding:20px;color:#9ca3af;font-size:13px;">
          <?php esc_html_e( 'Belum ada orang tua terhubung.', 'absensi-sekolah' ); ?>
        </div>

        <div x-show="!loadingWali && walis.length > 0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">
          <template x-for="w in walis" :key="w.id">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 14px;border-bottom:1px solid #f3f4f6;">
              <div>
                <p style="font-size:13.5px;font-weight:600;color:#111827;margin:0 0 2px;" x-text="w.wali_nama"></p>
                <p style="font-size:11.5px;color:#9ca3af;margin:0;font-family:monospace;" x-text="w.wali_login ?? ''"></p>
              </div>
              <button type="button" @click="removeWali(w.id, w.wali_nama)"
                      style="display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;background:#FEF2F2;color:#DC2626;border:1px solid #fecaca;font-size:12px;font-weight:600;cursor:pointer;">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                <?php esc_html_e( 'Lepas', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- Tambah Orang Tua -->
      <div>
        <p style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#64748B;margin:0 0 10px;"><?php esc_html_e( 'Tambah Orang Tua', 'absensi-sekolah' ); ?></p>

        <div style="position:relative;">
          <svg style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#9ca3af;" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" x-model="search" @input.debounce.400ms="searchUsers()"
                 placeholder="<?php esc_attr_e( 'Cari nama user orang tua…', 'absensi-sekolah' ); ?>"
                 style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px 8px 34px;font-size:13.5px;min-height:40px;font-family:inherit;outline:none;transition:border-color .15s;"
                 @focus="$el.style.borderColor='#2563EB'"
                 @blur="$el.style.borderColor='#d1d5db'">
        </div>

        <p x-show="searching" style="font-size:12.5px;color:#6b7280;text-align:center;margin:8px 0 0;"><?php esc_html_e( 'Mencari…', 'absensi-sekolah' ); ?></p>
        <p x-show="!searching && search.length >= 2 && results.length === 0" style="font-size:12.5px;color:#9ca3af;text-align:center;margin:8px 0 0;"><?php esc_html_e( 'Tidak ada user orang tua ditemukan.', 'absensi-sekolah' ); ?></p>

        <div x-show="results.length > 0" style="border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-top:8px;">
          <template x-for="u in results" :key="u.id">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #f3f4f6;">
              <div>
                <p style="font-size:13.5px;font-weight:600;color:#111827;margin:0 0 2px;" x-text="u.name"></p>
                <p style="font-size:11.5px;color:#9ca3af;margin:0;font-family:monospace;" x-text="u.slug ?? ''"></p>
              </div>
              <button type="button" @click="addWali(u)" :disabled="addingId === u.id"
                      style="display:flex;align-items:center;gap:5px;padding:4px 10px;border-radius:6px;background:#2563EB;color:white;border:none;font-size:12px;font-weight:600;cursor:pointer;opacity:1;"
                      :style="addingId === u.id ? 'opacity:.6;cursor:not-allowed;' : ''">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                <span x-text="addingId === u.id ? '<?php echo esc_js( __( 'Menambahkan…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Hubungkan', 'absensi-sekolah' ) ); ?>'"></span>
              </button>
            </div>
          </template>
        </div>

        <div x-show="error" x-cloak style="margin-top:10px;padding:10px 13px;border-radius:8px;background:#FEF2F2;border:1px solid #fecaca;color:#DC2626;font-size:13px;font-weight:600;" x-text="error" aria-live="polite"></div>
        <p style="font-size:11.5px;color:#94A3B8;margin:12px 0 0;line-height:1.5;">
          <?php esc_html_e( 'Hanya user dengan role "orang_tua" yang muncul di hasil pencarian.', 'absensi-sekolah' ); ?>
        </p>
      </div>

    </div>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-admin-wrap{font-family:'Plus Jakarta Sans',sans-serif!important;background:#F5F7FB;min-height:100vh;}
.absensi-page-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin:12px 0 24px;padding-bottom:16px;border-bottom:1px solid #e5e7eb;}
.absensi-page-title{font-size:19px;font-weight:700;color:#111827;margin:0 0 3px;}
.absensi-page-subtitle{font-size:13px;color:#6b7280;margin:0;}
.absensi-filter-bar{background:white;border:1px solid #e5e7eb;border-radius:10px;padding:14px 16px;}
.absensi-input-group{display:flex;flex-direction:column;gap:5px;}
.absensi-label{font-size:11.5px;font-weight:600;color:#374151;}
.absensi-input{border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:13.5px;min-height:40px;font-family:inherit;background:white;color:#111827;transition:border-color .15s,box-shadow .15s;outline:none;width:100%;box-sizing:border-box;}
.absensi-input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.absensi-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 15px;border-radius:8px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:38px;font-family:inherit;transition:background .12s,border-color .12s;text-decoration:none;}
.absensi-btn-sm{padding:5px 11px;font-size:12.5px;min-height:32px;}
.absensi-btn-primary{background:#2563EB;color:white;}
.absensi-btn-primary:hover:not(:disabled){background:#1D4ED8;}
.absensi-btn-primary:disabled{opacity:.45;cursor:not-allowed;}
.absensi-btn-secondary{background:white;color:#374151;border:1px solid #d1d5db;}
.absensi-btn-secondary:hover{background:#f9fafb;border-color:#9ca3af;}
.absensi-btn-ghost{background:transparent;color:#6b7280;border:none;}
.absensi-btn-ghost:hover{background:#f3f4f6;color:#111827;}
.absensi-btn-danger{background:#dc2626;color:white;}
.absensi-btn-danger:hover{background:#b91c1c;}
.absensi-avatar{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:50%;background:#EFF6FF;color:#2563EB;font-size:11px;font-weight:700;flex-shrink:0;}
.absensi-alert{display:flex;align-items:flex-start;gap:9px;padding:11px 14px;border-radius:8px;font-size:13px;}
.absensi-alert-danger{background:#FEF2F2;border:1px solid #fecaca;color:#dc2626;}
.absensi-table{width:100%;border-collapse:collapse;font-size:13.5px;}
.absensi-table thead tr{background:#f9fafb;border-bottom:1px solid #e5e7eb;}
.absensi-table th{text-align:left;padding:10px 14px;color:#6b7280;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.04em;}
.absensi-table td{padding:11px 14px;border-bottom:1px solid #f3f4f6;vertical-align:middle;}
.absensi-table tbody tr:hover td{background:#f9fafb;}
.absensi-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11.5px;font-weight:600;}
.absensi-badge-primary{background:#EFF6FF;color:#2563EB;}
.absensi-modal-overlay{position:fixed;inset:0;background:rgba(17,24,39,.5);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;}
.absensi-modal-box{background:white;border-radius:14px;padding:24px;width:100%;max-width:440px;box-shadow:0 20px 50px rgba(0,0,0,.15);}
.absensi-modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;}
.absensi-modal-title{font-size:16px;font-weight:700;color:#111827;margin:0;}
.absensi-modal-close{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:6px;background:transparent;border:none;cursor:pointer;color:#9ca3af;transition:background .12s,color .12s;}
.absensi-modal-close:hover{background:#f3f4f6;color:#374151;}
@keyframes spin{to{transform:rotate(360deg);}}
[x-cloak]{display:none!important;}
</style>
