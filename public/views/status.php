<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ABSENSI_PUBLIC_ASSETS' ) ) :
    define( 'ABSENSI_PUBLIC_ASSETS', true ); ?>
<link rel="stylesheet" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/app.css' ); ?>">
<script type="module" src="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/public.js' ); ?>"></script>
<?php endif;
?>
<div x-data="absensiStatus" class="absensi-status-wrap">
  <div class="absensi-status-container">

    <!-- Header -->
    <div class="absensi-status-header">
      <div class="absensi-status-header-icon">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m5.231 13.481L15 17.25m-4.5-15H5.625c-.621 0-1.125.504-1.125 1.125v16.5c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
      </div>
      <div>
        <h1 class="absensi-status-title"><?php esc_html_e( 'Cek Status Kehadiran', 'absensi-sekolah' ); ?></h1>
        <p class="absensi-status-subtitle"><?php esc_html_e( 'Masukkan NIS untuk melihat status absen hari ini', 'absensi-sekolah' ); ?></p>
      </div>
    </div>

    <!-- Form Pencarian -->
    <div class="absensi-card" style="padding:22px;margin-bottom:20px;">
      <form @submit.prevent="cekStatus()" style="display:flex;gap:10px;flex-wrap:wrap;">
        <div style="flex:1;position:relative;min-width:200px;">
          <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="text" x-model="nis"
                 placeholder="<?php esc_attr_e( 'Nomor Induk Siswa (NIS)', 'absensi-sekolah' ); ?>"
                 class="absensi-input" style="padding-left:40px;" required>
        </div>
        <button type="submit" :disabled="loading" class="absensi-btn absensi-btn-primary" style="padding:0 22px;flex-shrink:0;">
          <span x-show="loading" class="absensi-spinner"></span>
          <span x-show="!loading"><?php esc_html_e( 'Cari', 'absensi-sekolah' ); ?></span>
        </button>
      </form>

      <div x-show="errorMsg" class="absensi-alert absensi-alert-danger" style="margin-top:13px;" aria-live="polite">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <span x-text="errorMsg"></span>
      </div>
    </div>

    <!-- Hasil -->
    <div x-show="result" x-cloak>

      <!-- Identitas Siswa -->
      <div class="absensi-card" style="padding:20px;margin-bottom:14px;">
        <div style="display:flex;align-items:center;gap:14px;">
          <div class="absensi-siswa-avatar" x-text="getInisial(result?.nama)"></div>
          <div>
            <h2 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 3px;" x-text="result?.nama"></h2>
            <p style="font-size:12.5px;color:#6b7280;margin:0;">
              <span style="font-family:monospace;" x-text="result?.nis"></span>
              <span x-show="result?.nama_kelas" x-text="' · ' + result.nama_kelas"></span>
            </p>
          </div>
          <span class="absensi-status-badge ml-auto" :class="statusBadge(result?.status)" x-text="result?.status || 'Belum Absen'" style="margin-left:auto;"></span>
        </div>
      </div>

      <!-- Jam Masuk & Keluar -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
        <!-- Masuk -->
        <div class="absensi-time-card">
          <div class="absensi-time-card-label">
            <div class="absensi-time-dot dot-blue"></div>
            <?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?>
          </div>
          <p class="absensi-time-value" x-text="result?.waktu_masuk ? result.waktu_masuk.slice(11,16) : '--:--'"></p>
          <span x-show="result?.metode_masuk" class="absensi-method-tag" x-text="result.metode_masuk"></span>
        </div>
        <!-- Keluar -->
        <div class="absensi-time-card">
          <div class="absensi-time-card-label">
            <div class="absensi-time-dot dot-cyan"></div>
            <?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?>
          </div>
          <p class="absensi-time-value" x-text="result?.waktu_keluar ? result.waktu_keluar.slice(11,16) : '--:--'"></p>
          <span x-show="result?.metode_keluar" class="absensi-method-tag" x-text="result.metode_keluar"></span>
        </div>
      </div>

    </div>

  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-status-wrap{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;padding:20px 16px;}
.absensi-status-container{max-width:520px;margin:0 auto;}
.absensi-status-header{display:flex;align-items:center;gap:13px;background:white;border:1px solid #e5e7eb;border-radius:12px;padding:15px 18px;margin-bottom:16px;}
.absensi-status-header-icon{width:42px;height:42px;border-radius:10px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.absensi-status-title{font-size:16px;font-weight:700;color:#111827;margin:0 0 2px;}
.absensi-status-subtitle{font-size:12px;color:#6b7280;margin:0;}
.absensi-card{background:white;border:1px solid #e5e7eb;border-radius:12px;}
.absensi-input{border:1px solid #d1d5db;border-radius:9px;padding:10px 12px;font-size:14px;min-height:44px;font-family:inherit;outline:none;transition:all .15s;background:#f9fafb;width:100%;box-sizing:border-box;}
.absensi-input:focus{border-color:#2563EB;background:white;box-shadow:0 0 0 3px rgba(37,99,235,.1);}
.absensi-btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;border-radius:9px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:background .12s;min-height:44px;font-size:14px;}
.absensi-btn-primary{background:#2563EB;color:white;}
.absensi-btn-primary:hover:not(:disabled){background:#1D4ED8;}
.absensi-btn-primary:disabled{opacity:.55;cursor:not-allowed;}
.absensi-spinner{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.35);border-top-color:white;border-radius:50%;animation:spin .7s linear infinite;}
.absensi-alert{display:flex;align-items:center;gap:9px;padding:11px 13px;border-radius:8px;font-size:13px;font-weight:600;}
.absensi-alert-danger{background:#FEF2F2;color:#dc2626;border:1px solid #fecaca;}
.absensi-siswa-avatar{width:48px;height:48px;border-radius:50%;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;flex-shrink:0;}
.absensi-status-badge{display:inline-flex;align-items:center;padding:4px 13px;border-radius:999px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.badge-success{background:#DCFCE7;color:#16A34A;}
.badge-warning{background:#FEF3C7;color:#D97706;}
.badge-danger{background:#FEE2E2;color:#dc2626;}
.badge-info{background:#ECFEFF;color:#0891B2;}
.badge-neutral{background:#f3f4f6;color:#6b7280;}
.absensi-time-card{background:white;border:1px solid #e5e7eb;border-radius:11px;padding:16px;}
.absensi-time-card-label{display:flex;align-items:center;gap:7px;font-size:11.5px;font-weight:700;text-transform:uppercase;color:#6b7280;margin-bottom:10px;letter-spacing:.04em;}
.absensi-time-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.dot-blue{background:#2563EB;}
.dot-cyan{background:#0891B2;}
.absensi-time-value{font-size:26px;font-weight:700;font-family:monospace;color:#111827;margin:0 0 8px;}
.absensi-method-tag{display:inline-block;padding:2px 9px;border-radius:6px;font-size:11px;font-weight:700;text-transform:capitalize;background:#EFF6FF;color:#2563EB;}
@keyframes spin{to{transform:rotate(360deg);}}
[x-cloak]{display:none!important;}
</style>
