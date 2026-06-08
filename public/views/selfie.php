<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ABSENSI_PUBLIC_ASSETS' ) ) :
    define( 'ABSENSI_PUBLIC_ASSETS', true ); ?>
<link rel="stylesheet" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/app.css' ); ?>">
<script type="module" src="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/siswa.js' ); ?>"></script>
<?php endif; ?>

<div x-data="absensiSiswa" class="absensi-selfie-wrap">
  <div class="absensi-selfie-container">

    <!-- Header -->
    <div class="absensi-pub-header">
      <div class="absensi-pub-header-icon">
        <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/></svg>
      </div>
      <div>
        <h1 class="absensi-pub-title"><?php esc_html_e( 'Absensi Mandiri', 'absensi-sekolah' ); ?></h1>
        <p class="absensi-pub-subtitle"><?php esc_html_e( 'Selfie + GPS', 'absensi-sekolah' ); ?></p>
      </div>
    </div>

    <div class="absensi-selfie-card">

      <!-- Blok HTTPS -->
      <template x-if="!isHttps">
        <div class="absensi-alert absensi-alert-danger" style="margin-bottom:16px;" role="alert">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
          <?php esc_html_e( 'Absen butuh koneksi aman (HTTPS). Hubungi administrator.', 'absensi-sekolah' ); ?>
        </div>
      </template>

      <!-- Sesi Switcher -->
      <div x-show="step !== 'result'" style="display:flex;background:#F1F5F9;border-radius:10px;padding:3px;gap:3px;margin-bottom:14px;">
        <button type="button" @click="sesi = 'masuk'"
                :class="sesi === 'masuk' ? 'sesi-active' : 'sesi-inactive'"
                class="sesi-btn">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
          <?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?>
        </button>
        <button type="button" @click="sesi = 'pulang'"
                :class="sesi === 'pulang' ? 'sesi-active' : 'sesi-inactive'"
                class="sesi-btn">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15"/></svg>
          <?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?>
        </button>
      </div>

      <!-- GPS Status Chip -->
      <div x-show="step !== 'result'" style="margin-bottom:14px;">
        <div class="absensi-gps-chip"
             :class="{
               'gps-ok':    gpsStatus === 'ok',
               'gps-weak':  gpsStatus === 'weak',
               'gps-error': gpsStatus === 'error',
               'gps-wait':  gpsStatus === 'waiting'
             }">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
          <span x-show="gpsStatus === 'waiting'"><?php esc_html_e( 'Mendeteksi lokasi…', 'absensi-sekolah' ); ?></span>
          <span x-show="gpsStatus === 'ok'" x-text="'GPS OK ' + gpsAccuracyLabel"></span>
          <span x-show="gpsStatus === 'weak'"><?php esc_html_e( 'Sinyal GPS lemah, tunggu…', 'absensi-sekolah' ); ?> <span x-text="gpsAccuracyLabel"></span></span>
          <span x-show="gpsStatus === 'error'" x-text="gpsError ?? '<?php echo esc_js( __( 'GPS tidak tersedia', 'absensi-sekolah' ) ); ?>'"></span>
        </div>
      </div>

      <!-- Error Message -->
      <div x-show="errorMsg" x-cloak class="absensi-alert absensi-alert-danger" style="margin-bottom:14px;" aria-live="assertive">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <span x-text="errorMsg"></span>
      </div>

      <!-- ── STEP: idle ── -->
      <template x-if="step === 'idle'">
        <div>
          <div class="absensi-camera-placeholder-box">
            <svg width="44" height="44" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
            <p><?php esc_html_e( 'Kamera Belum Aktif', 'absensi-sekolah' ); ?></p>
          </div>
          <button type="button" @click="startCamera()" :disabled="!isHttps"
                  class="absensi-btn absensi-btn-primary" style="margin-top:16px;">
            <svg width="19" height="19" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
            <?php esc_html_e( 'Buka Kamera', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </template>

      <!-- ── STEP: camera ── -->
      <template x-if="step === 'camera'">
        <div>
          <div class="absensi-camera-container">
            <video x-ref="video" autoplay playsinline muted class="absensi-camera-view"></video>
            <div class="absensi-camera-overlay">
              <div class="absensi-camera-frame"></div>
              <p class="absensi-camera-hint"><?php esc_html_e( 'Posisikan wajah dalam area oval', 'absensi-sekolah' ); ?></p>
            </div>
          </div>
          <canvas x-ref="canvas" style="display:none;"></canvas>
          <button type="button" @click="capturePhoto()"
                  :disabled="gpsStatus !== 'ok'"
                  class="absensi-btn absensi-btn-success" style="font-size:16px;min-height:54px;margin-top:4px;">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M3 9a2 2 0 012-2h1.5a2 2 0 001.7-.95l.6-1.1A2 2 0 0110.5 4h3a2 2 0 011.7.95l.6 1.1A2 2 0 0017.5 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/></svg>
            <span x-text="gpsStatus !== 'ok' ? '<?php echo esc_js( __( 'Tunggu GPS Siap…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Ambil Foto', 'absensi-sekolah' ) ); ?>'"></span>
          </button>
        </div>
      </template>

      <!-- ── STEP: preview ── -->
      <template x-if="step === 'preview'">
        <div>
          <div class="absensi-camera-container">
            <img :src="photoUrl" class="absensi-camera-view" alt="<?php esc_attr_e( 'Preview Selfie', 'absensi-sekolah' ); ?>">
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:14px;">
            <button type="button" @click="retakePhoto()" class="absensi-btn absensi-btn-secondary">
              <?php esc_html_e( 'Ulangi', 'absensi-sekolah' ); ?>
            </button>
            <button type="button" @click="submit()" :disabled="!canSubmit"
                    class="absensi-btn absensi-btn-primary">
              <?php esc_html_e( 'Kirim Absen', 'absensi-sekolah' ); ?>
            </button>
          </div>
        </div>
      </template>

      <!-- ── STEP: submitting ── -->
      <template x-if="step === 'submitting'">
        <div style="text-align:center;padding:40px 16px;">
          <div class="absensi-spinner-lg" style="margin:0 auto 14px;"></div>
          <p style="font-size:14px;font-weight:600;color:#374151;margin:0 0 4px;"><?php esc_html_e( 'Mengirim absen…', 'absensi-sekolah' ); ?></p>
          <p style="font-size:12px;color:#9ca3af;margin:0;"><?php esc_html_e( 'Harap tunggu, jangan tutup halaman ini.', 'absensi-sekolah' ); ?></p>
        </div>
      </template>

      <!-- ── STEP: result ── -->
      <template x-if="step === 'result'">
        <div aria-live="polite">
          <!-- Sukses -->
          <template x-if="result?.success">
            <div class="absensi-result-card absensi-result-success">
              <div class="absensi-result-icon absensi-result-icon-success">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              </div>
              <h2 class="absensi-result-title"><?php esc_html_e( 'Absen Berhasil!', 'absensi-sekolah' ); ?></h2>
              <div class="absensi-result-meta">
                <span class="absensi-result-badge"
                      :class="result.sesi === 'masuk' ? 'badge-masuk' : 'badge-pulang'"
                      x-text="result.sesi === 'masuk' ? '⬆ <?php echo esc_js( __( 'Masuk', 'absensi-sekolah' ) ); ?>' : '⬇ <?php echo esc_js( __( 'Pulang', 'absensi-sekolah' ) ); ?>'">
                </span>
                <span class="absensi-result-badge badge-time" x-text="result.jam"></span>
                <span x-show="result.status"
                      class="absensi-result-badge"
                      :class="result.status === 'telat' ? 'badge-telat' : 'badge-hadir'"
                      x-text="result.status === 'telat' ? '⏰ <?php echo esc_js( __( 'Terlambat', 'absensi-sekolah' ) ); ?>' : '✓ <?php echo esc_js( __( 'Tepat Waktu', 'absensi-sekolah' ) ); ?>'">
                </span>
              </div>
              <p x-show="result.jarak_meter" style="font-size:12.5px;color:#6b7280;margin:8px 0 0;"
                 x-text="'<?php echo esc_js( __( 'Jarak dari sekolah: ', 'absensi-sekolah' ) ); ?>' + Math.round(result.jarak_meter) + ' m'"></p>
            </div>
          </template>
          <!-- Gagal / error di step result -->
          <template x-if="!result?.success && result">
            <div class="absensi-result-card absensi-result-danger">
              <div class="absensi-result-icon absensi-result-icon-danger">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </div>
              <h2 class="absensi-result-title"><?php esc_html_e( 'Absen Ditolak', 'absensi-sekolah' ); ?></h2>
              <p style="font-size:13.5px;color:#374151;margin:8px 0 0;" x-text="result.message ?? ''"></p>
            </div>
          </template>
          <button type="button" @click="reset()" class="absensi-btn absensi-btn-secondary" style="margin-top:16px;">
            <?php esc_html_e( 'Absen Lagi', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </template>

      <p x-show="step !== 'result'" class="absensi-info-note">
        <?php esc_html_e( 'Pastikan browser memiliki izin kamera dan lokasi.', 'absensi-sekolah' ); ?><br>
        <?php printf(
            esc_html__( 'Absen diterima dalam radius %s meter dari sekolah.', 'absensi-sekolah' ),
            '<strong>' . esc_html( get_option( 'absensi_radius', 100 ) ) . '</strong>'
        ); ?>
      </p>
    </div>
  </div>
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
.absensi-selfie-wrap{font-family:'Plus Jakarta Sans',sans-serif;background:#f4f6fb;min-height:100vh;padding:20px 16px;display:flex;align-items:flex-start;justify-content:center;}
.absensi-selfie-container{max-width:420px;width:100%;}
.absensi-pub-header{display:flex;align-items:center;gap:14px;margin-bottom:20px;padding:16px;background:white;border:1px solid #e5e7eb;border-radius:12px;}
.absensi-pub-header-icon{width:44px;height:44px;border-radius:10px;background:#EFF6FF;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.absensi-pub-title{font-size:17px;font-weight:700;color:#111827;margin:0 0 2px;}
.absensi-pub-subtitle{font-size:12.5px;color:#6b7280;margin:0;}
.absensi-selfie-card{background:white;border:1px solid #e5e7eb;border-radius:14px;padding:20px;}
.sesi-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:6px;padding:7px 12px;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;font-family:inherit;transition:all .15s;min-height:38px;}
.sesi-active{background:white;color:#2563EB;box-shadow:0 1px 4px rgba(0,0,0,.1);}
.sesi-inactive{background:transparent;color:#6b7280;}
.absensi-alert{display:flex;align-items:flex-start;gap:9px;padding:11px 14px;border-radius:8px;font-size:13px;font-weight:600;}
.absensi-alert-success{background:#F0FDF4;color:#16A34A;border:1px solid #bbf7d0;}
.absensi-alert-danger{background:#FEF2F2;color:#dc2626;border:1px solid #fecaca;}
.absensi-gps-chip{display:inline-flex;align-items:center;gap:7px;padding:5px 13px;border-radius:999px;font-size:12.5px;font-weight:600;}
.gps-ok{background:#F0FDF4;color:#16A34A;border:1px solid #bbf7d0;}
.gps-weak{background:#FFFBEB;color:#D97706;border:1px solid #fde68a;}
.gps-error{background:#FEF2F2;color:#dc2626;border:1px solid #fecaca;}
.gps-wait{background:#f3f4f6;color:#6b7280;border:1px solid #e5e7eb;}
.absensi-camera-placeholder-box{display:flex;flex-direction:column;align-items:center;justify-content:center;background:#111827;border-radius:12px;aspect-ratio:3/4;color:#6b7280;text-align:center;gap:10px;font-size:14px;font-weight:600;}
.absensi-camera-container{position:relative;width:100%;aspect-ratio:3/4;background:#111827;border-radius:12px;overflow:hidden;margin-bottom:4px;}
.absensi-camera-view{position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;transform:scaleX(-1);}
.absensi-camera-overlay{position:absolute;inset:0;pointer-events:none;display:flex;flex-direction:column;align-items:center;justify-content:center;}
.absensi-camera-frame{width:60%;height:60%;border:2px dashed rgba(255,255,255,.65);border-radius:50%;box-shadow:0 0 0 9999px rgba(0,0,0,.35);}
.absensi-camera-hint{position:absolute;bottom:20px;background:rgba(17,24,39,.75);color:white;padding:5px 14px;border-radius:999px;font-size:12px;font-weight:600;margin:0;}
.absensi-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;width:100%;border-radius:9px;font-weight:700;cursor:pointer;border:none;font-family:inherit;transition:background .12s;min-height:48px;font-size:15px;padding:0 16px;}
.absensi-btn-primary{background:#2563EB;color:white;}
.absensi-btn-primary:hover:not(:disabled){background:#1D4ED8;}
.absensi-btn-primary:disabled{opacity:.55;cursor:not-allowed;}
.absensi-btn-success{background:#16A34A;color:white;}
.absensi-btn-success:hover:not(:disabled){background:#15803d;}
.absensi-btn-success:disabled{opacity:.5;cursor:not-allowed;}
.absensi-btn-secondary{background:white;color:#374151;border:1px solid #d1d5db;}
.absensi-btn-secondary:hover{background:#f9fafb;}
.absensi-result-card{text-align:center;padding:28px 20px;border-radius:12px;margin-bottom:4px;}
.absensi-result-success{background:#F0FDF4;border:1px solid #bbf7d0;}
.absensi-result-danger{background:#FEF2F2;border:1px solid #fecaca;}
.absensi-result-icon{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;}
.absensi-result-icon-success{background:#DCFCE7;color:#16A34A;}
.absensi-result-icon-danger{background:#FEE2E2;color:#DC2626;}
.absensi-result-title{font-size:18px;font-weight:700;color:#111827;margin:0 0 10px;}
.absensi-result-meta{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;}
.absensi-result-badge{display:inline-flex;align-items:center;padding:4px 12px;border-radius:999px;font-size:12.5px;font-weight:700;}
.badge-masuk{background:#DBEAFE;color:#2563EB;}
.badge-pulang{background:#CFFAFE;color:#0891B2;}
.badge-hadir{background:#DCFCE7;color:#16A34A;}
.badge-telat{background:#FEF3C7;color:#D97706;}
.badge-time{background:#F1F5F9;color:#475569;}
.absensi-spinner-lg{width:44px;height:44px;border:3px solid #E2E8F0;border-top-color:#2563EB;border-radius:50%;animation:spin .8s linear infinite;}
.absensi-info-note{font-size:12px;color:#9ca3af;text-align:center;margin:16px 0 0;line-height:1.6;}
@keyframes spin{to{transform:rotate(360deg);}}
[x-cloak]{display:none!important;}
</style>
