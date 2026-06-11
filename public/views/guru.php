<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$kelas_list = $wpdb->get_results( "SELECT id, nama_kelas FROM {$wpdb->prefix}absensi_kelas ORDER BY nama_kelas" );
$kelas_json = wp_json_encode( array_map( fn($k) => [ 'id' => $k->id, 'nama_kelas' => $k->nama_kelas ], $kelas_list ) );
?>
<div x-data="absensiGuru" :data-kelas-list="<?php echo esc_attr( $kelas_json ); ?>" class="absensi-guru-wrap">
  <div class="absensi-guru-container">

    <!-- Page Header -->
    <div class="absensi-guru-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="absensi-guru-header-icon">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
        </div>
        <div>
          <h1 class="absensi-guru-title"><?php esc_html_e( 'Absensi RFID', 'absensi-sekolah' ); ?></h1>
          <p class="absensi-guru-subtitle"><?php esc_html_e( 'Pilih kelas, lalu tempelkan kartu siswa ke scanner.', 'absensi-sekolah' ); ?></p>
        </div>
      </div>
      <!-- Counter hadir -->
      <div x-show="mode==='absen'" class="absensi-hadir-counter">
        <span style="font-size:12px;color:#6b7280;font-weight:600;"><?php esc_html_e( 'Hadir', 'absensi-sekolah' ); ?></span>
        <span style="font-size:22px;font-weight:700;color:#2563EB;line-height:1;" x-text="hadirCount">0</span>
      </div>
    </div>

    <!-- Toolbar: kelas + sesi + mode -->
    <div class="absensi-card" style="padding:20px;margin-bottom:18px;">
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
        <!-- Kelas -->
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></label>
          <select x-model="kelas" @change="saveDraft()" class="absensi-input" style="font-weight:700;color:#2563EB;">
            <option value=""><?php esc_html_e( '— Pilih Kelas —', 'absensi-sekolah' ); ?></option>
            <?php foreach ( $kelas_list as $k ) : ?>
              <option value="<?php echo esc_attr( $k->id ); ?>"><?php echo esc_html( $k->nama_kelas ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <!-- Sesi -->
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'Sesi', 'absensi-sekolah' ); ?></label>
          <div class="absensi-seg-group">
            <button type="button" @click="sesi='masuk'; saveDraft()" :class="sesi==='masuk' ? 'seg-active-primary' : ''" class="absensi-seg-btn"><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></button>
            <button type="button" @click="sesi='pulang'; saveDraft()" :class="sesi==='pulang' ? 'seg-active-info' : ''" class="absensi-seg-btn"><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></button>
          </div>
        </div>
        <!-- Mode -->
        <div class="absensi-input-group">
          <label class="absensi-label"><?php esc_html_e( 'Mode', 'absensi-sekolah' ); ?></label>
          <div class="absensi-seg-group">
            <button type="button" @click="mode='absen'; saveDraft()" :class="mode==='absen' ? 'seg-active-primary' : ''" class="absensi-seg-btn"><?php esc_html_e( 'Absen', 'absensi-sekolah' ); ?></button>
            <button type="button" @click="mode='enroll'; saveDraft()" :class="mode==='enroll' ? 'seg-active-success' : ''" class="absensi-seg-btn"><?php esc_html_e( 'Daftar Kartu', 'absensi-sekolah' ); ?></button>
          </div>
        </div>
      </div>
    </div>

    <!-- Input RFID hidden -->
    <input type="text" x-ref="rfidInput" x-init="focusInput()" autocomplete="off" tabindex="-1"
           style="position:fixed;left:-9999px;opacity:0;width:1px;height:1px;" aria-hidden="true">

    <!-- Warning: kelas belum dipilih -->
    <div x-show="!kelas" class="absensi-alert absensi-alert-warning" style="margin-bottom:18px;" aria-live="polite">
      <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
      <?php esc_html_e( 'Pilih kelas terlebih dahulu sebelum mulai memindai kartu.', 'absensi-sekolah' ); ?>
    </div>

    <!-- ── Mode Absen ── -->
    <div x-show="mode==='absen'" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:18px;">

      <!-- Scanner Pad -->
      <div class="absensi-card" style="display:flex;flex-direction:column;align-items:center;padding:32px 24px;text-align:center;">
        <div class="absensi-rfid-pad" @click="$refs.rfidInput.focus()">
          <div class="absensi-rfid-pulse"></div>
          <div class="absensi-rfid-core">
            <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
          </div>
        </div>
        <h3 style="font-size:15px;font-weight:700;color:#111827;margin:20px 0 6px;"><?php esc_html_e( 'Siap Memindai', 'absensi-sekolah' ); ?></h3>
        <p style="font-size:13px;color:#6b7280;margin:0 0 18px;line-height:1.5;"><?php esc_html_e( 'Pastikan kursor aktif, lalu tempelkan kartu RFID siswa ke scanner.', 'absensi-sekolah' ); ?></p>
        <button type="button" @click="$refs.rfidInput.focus()" class="absensi-btn-plain" style="width:100%;">
          <?php esc_html_e( 'Klik jika scanner tidak merespons', 'absensi-sekolah' ); ?>
        </button>
      </div>

      <!-- Log scan hari ini -->
      <div class="absensi-card" style="padding:0;overflow:hidden;display:flex;flex-direction:column;">
        <div style="padding:14px 18px;background:#f9fafb;border-bottom:1px solid #e5e7eb;">
          <h3 style="font-size:11.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin:0;"><?php esc_html_e( 'Log Pindaian Hari Ini', 'absensi-sekolah' ); ?></h3>
        </div>

        <div aria-live="assertive" style="padding:12px 16px 0;display:flex;flex-direction:column;gap:8px;">
          <template x-for="t in toasts" :key="t.id">
            <div class="absensi-toast" :class="t.ok ? 'toast-ok' : 'toast-err'">
              <div class="absensi-toast-dot" :class="t.ok ? 'dot-ok' : 'dot-err'"></div>
              <span x-text="t.message" style="font-weight:600;font-size:13px;"></span>
            </div>
          </template>
        </div>

        <div style="flex:1;overflow-y:auto;max-height:340px;padding:8px 16px 16px;">
          <template x-for="(r,i) in todayList" :key="i">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 0;border-bottom:1px solid #f3f4f6;">
              <div>
                <p style="font-size:13.5px;font-weight:700;color:#111827;margin:0 0 2px;" x-text="r.nama"></p>
                <p style="font-size:11.5px;color:#9ca3af;margin:0;font-family:monospace;" x-text="r.jam"></p>
              </div>
              <div style="display:flex;gap:5px;">
                <span class="absensi-badge" :class="r.sesi==='masuk' ? 'badge-primary' : 'badge-info'" x-text="r.sesi==='masuk' ? 'Masuk' : 'Pulang'"></span>
                <span class="absensi-badge" :class="r.status==='hadir'?'badge-success':(r.status==='telat'?'badge-warning':'badge-danger')" x-text="r.status"></span>
              </div>
            </div>
          </template>
          <div x-show="todayList.length===0" style="text-align:center;padding:48px 0;color:#9ca3af;">
            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 10px;display:block;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <p style="font-size:13px;margin:0;"><?php esc_html_e( 'Belum ada data pindaian.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Mode Enroll ── -->
    <div x-show="mode==='enroll'" class="absensi-card" style="max-width:480px;margin:0 auto;padding:28px;">
      <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 20px;text-align:center;"><?php esc_html_e( 'Daftarkan Kartu RFID', 'absensi-sekolah' ); ?></h3>

      <!-- Search Siswa -->
      <div x-show="!enrollTarget" style="display:flex;flex-direction:column;gap:14px;">
        <div style="position:relative;">
          <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;" width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" x-model="enrollSearch" @input.debounce.350ms="searchSiswa()"
                 placeholder="<?php esc_attr_e( 'Cari nama atau NIS siswa…', 'absensi-sekolah' ); ?>"
                 class="absensi-input" style="padding-left:40px;">
        </div>

        <div x-show="enrollResults.length > 0" style="border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">
          <template x-for="s in enrollResults" :key="s.id">
            <button type="button" @click="selectEnrollTarget(s)" class="absensi-enroll-item">
              <div style="text-align:left;">
                <p style="font-size:13.5px;font-weight:700;color:#111827;margin:0 0 2px;" x-text="s.nama"></p>
                <p style="font-size:11.5px;color:#6b7280;margin:0;font-family:monospace;" x-text="s.nis"></p>
              </div>
              <span x-show="s.rfid_uid" class="absensi-badge badge-primary" style="font-family:monospace;" x-text="s.rfid_uid"></span>
              <span x-show="!s.rfid_uid" class="absensi-badge badge-neutral"><?php esc_html_e( 'Belum', 'absensi-sekolah' ); ?></span>
            </button>
          </template>
        </div>
        <p x-show="enrollSearching" style="text-align:center;font-size:13px;color:#6b7280;margin:0;"><?php esc_html_e( 'Mencari…', 'absensi-sekolah' ); ?></p>
      </div>

      <!-- Tap Card -->
      <div x-show="enrollTarget" style="text-align:center;display:flex;flex-direction:column;gap:14px;">
        <div style="background:#f9fafb;padding:14px;border-radius:10px;border:1px solid #e5e7eb;">
          <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;margin:0 0 4px;"><?php esc_html_e( 'Mendaftarkan kartu untuk', 'absensi-sekolah' ); ?></p>
          <p style="font-size:17px;font-weight:700;color:#111827;margin:0 0 2px;" x-text="enrollTarget?.nama"></p>
          <p style="font-size:12.5px;color:#6b7280;margin:0;font-family:monospace;" x-text="enrollTarget?.nis"></p>
        </div>

        <div style="border:2px dashed #bbf7d0;background:#F0FDF4;border-radius:12px;padding:28px 20px;text-align:center;">
          <div style="width:48px;height:48px;border-radius:10px;background:#DCFCE7;color:#16A34A;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
            <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          </div>
          <p style="font-size:14px;font-weight:700;color:#16A34A;margin:0;"><?php esc_html_e( 'Tempelkan Kartu Sekarang', 'absensi-sekolah' ); ?></p>
          <p style="font-size:12.5px;color:#6b7280;margin:6px 0 0;"><?php esc_html_e( 'Pastikan kursor aktif di halaman ini.', 'absensi-sekolah' ); ?></p>
        </div>

        <button type="button" @click="enrollTarget=null" class="absensi-btn-plain" style="width:100%;margin-top:4px;">
          <?php esc_html_e( 'Batalkan', 'absensi-sekolah' ); ?>
        </button>
      </div>

      <div x-show="enrollStatus" style="margin-top:16px;padding:12px 14px;border-radius:8px;font-size:13px;font-weight:600;text-align:center;"
           :class="enrollStatus?.ok ? 'absensi-alert-success' : 'absensi-alert-danger'"
           x-text="enrollStatus?.message" aria-live="polite"></div>
    </div>

  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-guru-wrap{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;padding:20px 16px;}
.absensi-guru-container{max-width:880px;margin:0 auto;}
.absensi-guru-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:14px;background:white;border:1px solid #e5e7eb;border-radius:12px;padding:16px 20px;margin-bottom:18px;}
.absensi-guru-header-icon{width:44px;height:44px;border-radius:10px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.absensi-guru-title{font-size:17px;font-weight:700;color:#111827;margin:0 0 2px;}
.absensi-guru-subtitle{font-size:12.5px;color:#6b7280;margin:0;}
.absensi-hadir-counter{display:flex;flex-direction:column;align-items:center;gap:1px;background:#EFF6FF;border-radius:10px;padding:8px 18px;}
.absensi-card{background:white;border:1px solid #e5e7eb;border-radius:12px;}
.absensi-input-group{display:flex;flex-direction:column;gap:5px;}
.absensi-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;}
.absensi-input{border:1px solid #d1d5db;border-radius:8px;padding:9px 12px;font-size:13.5px;min-height:42px;font-family:inherit;outline:none;transition:all .15s;background:#f9fafb;width:100%;box-sizing:border-box;}
.absensi-input:focus{border-color:#2563EB;background:white;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.absensi-seg-group{display:flex;background:#f3f4f6;border-radius:8px;padding:3px;gap:3px;}
.absensi-seg-btn{flex:1;border:none;background:transparent;padding:9px 6px;font-size:13px;font-weight:700;color:#6b7280;border-radius:6px;cursor:pointer;transition:all .18s;}
.seg-active-primary{background:#2563EB;color:white;}
.seg-active-info{background:#0891B2;color:white;}
.seg-active-success{background:#16A34A;color:white;}
.absensi-alert{display:flex;align-items:center;gap:9px;padding:12px 14px;border-radius:8px;font-size:13px;font-weight:600;}
.absensi-alert-warning{background:#FFFBEB;color:#D97706;border:1px solid #fde68a;}
.absensi-alert-success{background:#F0FDF4;color:#16A34A;border:1px solid #bbf7d0;}
.absensi-alert-danger{background:#FEF2F2;color:#dc2626;border:1px solid #fecaca;}
.absensi-rfid-pad{position:relative;width:100%;height:160px;background:#f9fafb;border:2px dashed #d1d5db;border-radius:12px;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;overflow:hidden;margin-bottom:20px;}
.absensi-rfid-pad:hover{border-color:#2563EB;background:#EFF6FF;}
.absensi-rfid-pulse{position:absolute;width:90px;height:90px;background:#2563EB;border-radius:50%;opacity:0;animation:rfid-pulse 2s cubic-bezier(.4,0,.6,1) infinite;}
.absensi-rfid-core{position:relative;width:66px;height:66px;background:#2563EB;border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;z-index:2;}
.absensi-btn-plain{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:8px;font-size:13px;font-weight:600;border:1px solid #d1d5db;background:white;color:#374151;cursor:pointer;transition:background .12s;font-family:inherit;min-height:40px;}
.absensi-btn-plain:hover{background:#f9fafb;}
.absensi-toast{display:flex;align-items:center;gap:10px;padding:11px 14px;border-radius:8px;font-size:13px;background:white;border:1px solid #e5e7eb;margin-bottom:8px;animation:toast-in .25s ease;}
.toast-ok{border-left:3px solid #16A34A;}
.toast-err{border-left:3px solid #dc2626;}
.absensi-toast-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.dot-ok{background:#16A34A;}
.dot-err{background:#dc2626;}
.absensi-badge{display:inline-flex;align-items:center;padding:2px 9px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.badge-primary{background:#EFF6FF;color:#2563EB;}
.badge-info{background:#ECFEFF;color:#0891B2;}
.badge-success{background:#DCFCE7;color:#16A34A;}
.badge-warning{background:#FEF3C7;color:#D97706;}
.badge-danger{background:#FEE2E2;color:#dc2626;}
.badge-neutral{background:#f3f4f6;color:#6b7280;}
.absensi-enroll-item{display:flex;align-items:center;justify-content:space-between;width:100%;padding:12px 14px;border:none;background:transparent;border-bottom:1px solid #f3f4f6;cursor:pointer;transition:background .1s;}
.absensi-enroll-item:last-child{border-bottom:none;}
.absensi-enroll-item:hover{background:#f9fafb;}
@keyframes rfid-pulse{0%,100%{transform:scale(.8);opacity:.3}50%{transform:scale(1.9);opacity:0}}
@keyframes toast-in{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
[x-cloak]{display:none!important;}
</style>
