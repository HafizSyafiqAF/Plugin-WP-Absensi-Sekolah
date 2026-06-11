<?php
defined( 'ABSPATH' ) || exit;
/**
 * Surface Orang Tua — view-only riwayat absensi anak.
 * Gate login + cap `absensi_view_child` sudah ditangani shortcode [absensi_ortu].
 * Data anak dari AbsensiConfig.anakList (server-derived); riwayat via GET /child/logs.
 *
 * Catatan: hindari karakter > / < di nilai atribut Alpine — output shortcode
 * dirender di konten page dan wptexturize memecah tag bila ada > di atribut.
 */
?>
<div x-data="absensiOrtu" x-cloak class="absensi-ortu-wrap">
  <div class="absensi-ortu-container">

    <!-- Header -->
    <div class="absensi-ortu-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="absensi-ortu-header-icon">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
        </div>
        <div>
          <h1 class="absensi-ortu-title"><?php esc_html_e( 'Pantau Absensi Anak', 'absensi-sekolah' ); ?></h1>
          <p class="absensi-ortu-subtitle"><?php esc_html_e( 'Riwayat kehadiran harian', 'absensi-sekolah' ); ?></p>
        </div>
      </div>
    </div>

    <!-- Belum ada anak ter-link -->
    <div x-show="!adaAnak" class="absensi-card" style="padding:40px 24px;text-align:center;">
      <div class="absensi-login-icon" aria-hidden="true">
        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
      </div>
      <h2 style="font-size:17px;font-weight:700;color:#111827;margin:0 0 6px;"><?php esc_html_e( 'Belum Ada Anak Terhubung', 'absensi-sekolah' ); ?></h2>
      <p style="font-size:13px;color:#6b7280;margin:0;line-height:1.6;"><?php esc_html_e( 'Akun Anda belum terhubung dengan data siswa. Hubungi pihak sekolah untuk menautkan akun.', 'absensi-sekolah' ); ?></p>
    </div>

    <!-- Konten utama -->
    <div x-show="adaAnak">

      <!-- Pilih anak (jika lebih dari satu) -->
      <div x-show="banyakAnak" role="tablist" aria-label="<?php esc_attr_e( 'Pilih anak', 'absensi-sekolah' ); ?>"
           style="display:flex;gap:8px;overflow-x:auto;margin-bottom:16px;padding-bottom:2px;">
        <template x-for="(anak, i) in anakList" :key="anak.siswa_id">
          <button type="button" role="tab" @click="selectAnak(i)"
                  class="absensi-child-tab" :class="selectedIndex === i ? 'child-tab-active' : ''"
                  :aria-selected="selectedIndex === i ? 'true' : 'false'">
            <span class="absensi-child-tab-avatar" x-text="inisial(anak.nama)" aria-hidden="true"></span>
            <span x-text="anak.nama"></span>
          </button>
        </template>
      </div>

      <!-- Profil + ringkasan bulan -->
      <div x-show="selectedAnak" class="absensi-card" style="padding:20px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
          <div class="absensi-avatar" x-text="inisial(selectedAnak?.nama)" aria-hidden="true"></div>
          <div>
            <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 3px;" x-text="selectedAnak?.nama"></h3>
            <p style="font-size:12.5px;color:#6b7280;margin:0;">
              <?php esc_html_e( 'NIS:', 'absensi-sekolah' ); ?> <span style="font-family:monospace;" x-text="selectedAnak?.nis"></span>
              <span x-show="selectedAnak?.nama_kelas" x-text="' · ' + (selectedAnak?.nama_kelas || '')"></span>
            </p>
          </div>
        </div>

        <!-- Navigasi bulan -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
          <button type="button" @click="prevBulan()" class="absensi-month-nav" aria-label="<?php esc_attr_e( 'Bulan sebelumnya', 'absensi-sekolah' ); ?>">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
          </button>
          <span style="font-size:14px;font-weight:700;color:#111827;" x-text="bulanLabel" aria-live="polite"></span>
          <button type="button" @click="nextBulan()" :disabled="isMaxBulan" class="absensi-month-nav" aria-label="<?php esc_attr_e( 'Bulan berikutnya', 'absensi-sekolah' ); ?>">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
          </button>
        </div>

        <!-- Ringkasan bulan -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;" aria-live="polite">
          <div class="absensi-stat-box stat-green">
            <span class="stat-num" x-text="summary.hadir"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Hadir', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="absensi-stat-box stat-orange">
            <span class="stat-num" x-text="summary.telat"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Telat', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="absensi-stat-box stat-cyan">
            <span class="stat-num" x-text="summary.izin_sakit"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Izin/Sakit', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="absensi-stat-box stat-red">
            <span class="stat-num" x-text="summary.alpha"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Alpha', 'absensi-sekolah' ); ?></span>
          </div>
        </div>
      </div>

      <!-- Error -->
      <div x-show="error" class="absensi-alert absensi-alert-danger" style="margin-bottom:14px;" role="alert" aria-live="assertive">
        <span x-text="error"></span>
      </div>

      <!-- Loading -->
      <div x-show="loading" style="text-align:center;padding:32px 0;" aria-live="polite" aria-busy="true">
        <span class="absensi-spinner absensi-spinner-dark" role="status"></span>
        <p style="font-size:12.5px;color:#9ca3af;margin:10px 0 0;"><?php esc_html_e( 'Memuat riwayat…', 'absensi-sekolah' ); ?></p>
      </div>

      <!-- Timeline riwayat -->
      <div x-show="!loading">
        <h3 style="font-size:13.5px;font-weight:700;color:#374151;margin:0 0 12px;"><?php esc_html_e( 'Riwayat Absensi', 'absensi-sekolah' ); ?></h3>

        <div style="display:flex;flex-direction:column;gap:10px;">
          <template x-for="r in timeline" :key="r.id">
            <div class="absensi-timeline-row">
              <div class="absensi-date-box" aria-hidden="true">
                <span style="font-size:10px;font-weight:700;text-transform:uppercase;color:#6b7280;" x-text="hariLabel(r.tanggal)"></span>
                <span style="font-size:18px;font-weight:700;color:#111827;line-height:1;" x-text="tanggalLabel(r.tanggal)"></span>
              </div>
              <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                  <span class="absensi-badge" :class="statusClass(r.status)" x-text="r.status" style="text-transform:capitalize;"></span>
                </div>
                <div style="display:flex;flex-wrap:wrap;gap:18px;">
                  <div>
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#9ca3af;"><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></span>
                    <p style="font-size:14px;font-weight:700;font-family:monospace;color:#111827;margin:2px 0 0;" x-show="r.waktu_masuk" x-text="jam(r.waktu_masuk)"></p>
                    <p style="font-size:13px;color:#d1d5db;margin:2px 0 0;" x-show="!r.waktu_masuk">—</p>
                  </div>
                  <div>
                    <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#9ca3af;"><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></span>
                    <p style="font-size:14px;font-weight:700;font-family:monospace;color:#111827;margin:2px 0 0;" x-show="r.waktu_keluar" x-text="jam(r.waktu_keluar)"></p>
                    <p style="font-size:13px;color:#d1d5db;margin:2px 0 0;" x-show="!r.waktu_keluar">—</p>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <div x-show="!adaTimeline"
               style="text-align:center;padding:40px 20px;color:#9ca3af;border:2px dashed #e5e7eb;border-radius:12px;background:white;">
            <p style="font-size:13.5px;margin:0;"><?php esc_html_e( 'Belum ada data riwayat absensi di bulan ini.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
      </div>

    </div><!-- /konten utama -->
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-ortu-wrap{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;padding:20px 16px;}
.absensi-ortu-container{max-width:540px;margin:0 auto;}
.absensi-ortu-header{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;background:white;border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;margin-bottom:16px;}
.absensi-ortu-header-icon{width:42px;height:42px;border-radius:10px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.absensi-ortu-title{font-size:16px;font-weight:700;color:#111827;margin:0 0 2px;}
.absensi-ortu-subtitle{font-size:12px;color:#6b7280;margin:0;}
.absensi-card{background:white;border:1px solid #e5e7eb;border-radius:12px;}
.absensi-login-icon{width:52px;height:52px;border-radius:12px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;}
.absensi-spinner{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.35);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;}
.absensi-spinner-dark{width:22px;height:22px;border-color:#e5e7eb;border-top-color:#2563EB;}
.absensi-alert{display:flex;align-items:center;gap:9px;padding:11px 13px;border-radius:8px;font-size:13px;font-weight:600;}
.absensi-alert-danger{background:#FEF2F2;color:#dc2626;border:1px solid #fecaca;}
.absensi-child-tab{display:inline-flex;align-items:center;gap:8px;border:1px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;padding:7px 14px 7px 8px;border-radius:8px;cursor:pointer;transition:all .15s;white-space:nowrap;font-family:inherit;min-height:44px;}
.child-tab-active{background:#2563EB;color:white;border-color:#2563EB;}
.absensi-child-tab-avatar{width:28px;height:28px;border-radius:50%;background:#EFF6FF;color:#2563EB;display:inline-flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;}
.child-tab-active .absensi-child-tab-avatar{background:rgba(255,255,255,.2);color:white;}
.absensi-avatar{width:48px;height:48px;border-radius:50%;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;flex-shrink:0;}
.absensi-month-nav{width:36px;height:36px;border-radius:8px;border:1px solid #e5e7eb;background:white;color:#374151;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:all .12s;}
.absensi-month-nav:hover:not(:disabled){background:#f3f4f6;}
.absensi-month-nav:disabled{opacity:.4;cursor:not-allowed;}
.absensi-stat-box{border-radius:10px;padding:12px 8px;text-align:center;border:1px solid transparent;}
.stat-green{background:#F0FDF4;border-color:#bbf7d0;}
.stat-orange{background:#FFFBEB;border-color:#fde68a;}
.stat-cyan{background:#ECFEFF;border-color:#a5f3fc;}
.stat-red{background:#FEF2F2;border-color:#fecaca;}
.stat-num{display:block;font-size:20px;font-weight:700;line-height:1;}
.stat-green .stat-num{color:#16A34A;}
.stat-orange .stat-num{color:#D97706;}
.stat-cyan .stat-num{color:#0891B2;}
.stat-red .stat-num{color:#dc2626;}
.stat-lbl{display:block;font-size:10.5px;font-weight:700;text-transform:uppercase;color:#9ca3af;margin-top:4px;}
.absensi-timeline-row{display:flex;gap:14px;background:white;border:1px solid #e5e7eb;border-radius:11px;padding:14px;}
.absensi-date-box{width:50px;height:50px;background:#f3f4f6;border-radius:9px;display:flex;flex-direction:column;align-items:center;justify-content:center;flex-shrink:0;gap:1px;}
.absensi-badge{display:inline-flex;align-items:center;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.badge-success{background:#DCFCE7;color:#16A34A;}
.badge-warning{background:#FEF3C7;color:#D97706;}
.badge-danger{background:#FEE2E2;color:#dc2626;}
.badge-info{background:#ECFEFF;color:#0891B2;}
.badge-neutral{background:#f3f4f6;color:#6b7280;}
@keyframes spin{to{transform:rotate(360deg);}}
[x-cloak]{display:none!important;}
</style>
