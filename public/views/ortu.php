<?php
defined( 'ABSPATH' ) || exit;
?>
<div x-data="absensiOrtu" class="absensi-ortu-wrap">
  <div class="absensi-ortu-container">

    <!-- Header -->
    <div class="absensi-ortu-header">
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="absensi-ortu-header-icon">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
        </div>
        <div>
          <h1 class="absensi-ortu-title"><?php esc_html_e( 'Pantau Absensi Anak', 'absensi-sekolah' ); ?></h1>
          <p class="absensi-ortu-subtitle"><?php esc_html_e( 'Riwayat kehadiran harian', 'absensi-sekolah' ); ?></p>
        </div>
      </div>
      <button x-show="isLoggedIn" type="button" @click="logout()" class="absensi-ghost-btn">
        <?php esc_html_e( 'Keluar', 'absensi-sekolah' ); ?>
      </button>
    </div>

    <!-- Login -->
    <div x-show="!isLoggedIn" class="absensi-card" style="padding:28px 24px;text-align:center;">
      <div class="absensi-login-icon">
        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
      </div>
      <h2 style="font-size:18px;font-weight:700;color:#111827;margin:0 0 6px;"><?php esc_html_e( 'Masuk ke Portal Ortu', 'absensi-sekolah' ); ?></h2>
      <p style="font-size:13px;color:#6b7280;margin:0 0 22px;line-height:1.6;"><?php esc_html_e( 'Masukkan kode akses yang diberikan oleh sekolah.', 'absensi-sekolah' ); ?></p>

      <form @submit.prevent="login()" style="display:flex;flex-direction:column;gap:11px;">
        <input type="password" x-model="inputToken"
               placeholder="<?php esc_attr_e( 'Kode akses (contoh: X7K9P2M)', 'absensi-sekolah' ); ?>"
               class="absensi-input" style="text-align:center;font-size:17px;letter-spacing:.12em;font-weight:700;" required>
        <button type="submit" :disabled="loading" class="absensi-btn absensi-btn-primary">
          <span x-show="loading" class="absensi-spinner"></span>
          <span x-show="!loading"><?php esc_html_e( 'Buka Data', 'absensi-sekolah' ); ?></span>
        </button>
      </form>

      <div x-show="errorMsg" class="absensi-alert absensi-alert-danger" style="margin-top:14px;" aria-live="polite">
        <span x-text="errorMsg"></span>
      </div>
    </div>

    <!-- Main Content -->
    <div x-show="isLoggedIn" x-cloak>

      <!-- Pilih anak (multi anak) -->
      <div x-show="siswaList.length > 1" style="display:flex;gap:8px;overflow-x:auto;margin-bottom:16px;padding-bottom:2px;">
        <template x-for="(s, i) in siswaList" :key="s.id">
          <button type="button" @click="activeSiswaIndex = i"
                  class="absensi-child-tab" :class="activeSiswaIndex === i ? 'child-tab-active' : ''">
            <span x-text="s.nama"></span>
          </button>
        </template>
      </div>

      <!-- Profil + Summary bulan ini -->
      <div x-show="activeSiswa" class="absensi-card" style="padding:20px;margin-bottom:16px;">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:18px;">
          <div class="absensi-avatar" x-text="getInisial(activeSiswa?.nama)"></div>
          <div>
            <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 3px;" x-text="activeSiswa?.nama"></h3>
            <p style="font-size:12.5px;color:#6b7280;margin:0;">
              <?php esc_html_e( 'NIS:', 'absensi-sekolah' ); ?> <span style="font-family:monospace;" x-text="activeSiswa?.nis"></span>
              <span x-show="activeSiswa?.nama_kelas" x-text="' · ' + activeSiswa.nama_kelas"></span>
            </p>
          </div>
        </div>

        <!-- Mini stat bulan ini -->
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px;">
          <div class="absensi-stat-box stat-green">
            <span class="stat-num" x-text="activeSiswa?.rekap_bulan?.hadir||0"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Hadir', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="absensi-stat-box stat-orange">
            <span class="stat-num" x-text="activeSiswa?.rekap_bulan?.telat||0"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Telat', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="absensi-stat-box stat-cyan">
            <span class="stat-num" x-text="(activeSiswa?.rekap_bulan?.izin||0)+(activeSiswa?.rekap_bulan?.sakit||0)"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Izin/Sakit', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="absensi-stat-box stat-red">
            <span class="stat-num" x-text="activeSiswa?.rekap_bulan?.alpha||0"></span>
            <span class="stat-lbl"><?php esc_html_e( 'Alpha', 'absensi-sekolah' ); ?></span>
          </div>
        </div>
      </div>

      <!-- Timeline riwayat -->
      <h3 style="font-size:13.5px;font-weight:700;color:#374151;margin:0 0 12px;"><?php esc_html_e( 'Riwayat 7 Hari Terakhir', 'absensi-sekolah' ); ?></h3>

      <div style="display:flex;flex-direction:column;gap:10px;">
        <template x-for="r in (activeSiswa?.riwayat || [])" :key="r.tanggal">
          <div class="absensi-timeline-row">
            <div class="absensi-date-box">
              <span style="font-size:10px;font-weight:700;text-transform:uppercase;color:#6b7280;" x-text="formatDateShort(r.tanggal).split(' ')[0]"></span>
              <span style="font-size:18px;font-weight:700;color:#111827;line-height:1;" x-text="formatDateShort(r.tanggal).split(' ')[1]"></span>
            </div>
            <div style="flex:1;min-width:0;">
              <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span class="absensi-badge" :class="statusClass(r.status)" x-text="r.status" style="text-transform:capitalize;"></span>
              </div>
              <div style="display:flex;flex-wrap:wrap;gap:18px;">
                <div>
                  <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#9ca3af;"><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></span>
                  <p style="font-size:14px;font-weight:700;font-family:monospace;color:#111827;margin:2px 0 0;" x-show="r.waktu_masuk" x-text="r.waktu_masuk.slice(11,16)"></p>
                  <p style="font-size:13px;color:#d1d5db;margin:2px 0 0;" x-show="!r.waktu_masuk">—</p>
                </div>
                <div>
                  <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:#9ca3af;"><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></span>
                  <p style="font-size:14px;font-weight:700;font-family:monospace;color:#111827;margin:2px 0 0;" x-show="r.waktu_keluar" x-text="r.waktu_keluar.slice(11,16)"></p>
                  <p style="font-size:13px;color:#d1d5db;margin:2px 0 0;" x-show="!r.waktu_keluar">—</p>
                </div>
              </div>
            </div>
          </div>
        </template>

        <div x-show="(activeSiswa?.riwayat||[]).length === 0"
             style="text-align:center;padding:40px 20px;color:#9ca3af;border:2px dashed #e5e7eb;border-radius:12px;background:white;">
          <p style="font-size:13.5px;margin:0;"><?php esc_html_e( 'Belum ada data riwayat absensi.', 'absensi-sekolah' ); ?></p>
        </div>
      </div>
    </div>

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
.absensi-ghost-btn{background:transparent;border:1px solid #d1d5db;color:#6b7280;font-size:12.5px;font-weight:600;padding:6px 13px;border-radius:7px;cursor:pointer;transition:all .12s;font-family:inherit;}
.absensi-ghost-btn:hover{background:#f3f4f6;color:#374151;}
.absensi-card{background:white;border:1px solid #e5e7eb;border-radius:12px;}
.absensi-login-icon{width:52px;height:52px;border-radius:12px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;}
.absensi-input{border:1px solid #d1d5db;border-radius:9px;padding:10px 13px;font-size:14px;min-height:44px;font-family:inherit;outline:none;transition:all .15s;background:#f9fafb;width:100%;box-sizing:border-box;}
.absensi-input:focus{border-color:#2563EB;background:white;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.absensi-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;border-radius:9px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:background .12s;min-height:46px;font-size:14.5px;}
.absensi-btn-primary{background:#2563EB;color:white;}
.absensi-btn-primary:hover:not(:disabled){background:#1D4ED8;}
.absensi-btn-primary:disabled{opacity:.55;cursor:not-allowed;}
.absensi-spinner{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.35);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;}
.absensi-alert{display:flex;align-items:center;gap:9px;padding:11px 13px;border-radius:8px;font-size:13px;font-weight:600;}
.absensi-alert-danger{background:#FEF2F2;color:#dc2626;border:1px solid #fecaca;}
.absensi-child-tab{border:1px solid #e5e7eb;background:white;color:#374151;font-size:13px;font-weight:600;padding:8px 18px;border-radius:8px;cursor:pointer;transition:all .15s;white-space:nowrap;font-family:inherit;}
.child-tab-active{background:#2563EB;color:white;border-color:#2563EB;}
.absensi-avatar{width:48px;height:48px;border-radius:50%;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;flex-shrink:0;}
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
