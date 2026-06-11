<?php
defined( 'ABSPATH' ) || exit;
?>
<div x-data="absensiSiswa" x-cloak class="absensi-app">
  <div class="page-wrap">

    <!-- Header -->
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding:16px 18px;background:var(--c-surface);border:1px solid var(--c-border);border-radius:var(--r-xl);box-shadow:var(--shadow-sm);">
      <div style="width:46px;height:46px;border-radius:var(--r-md);background:var(--c-primary-soft);color:var(--c-primary);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
        <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/></svg>
      </div>
      <div>
        <h1 style="font-size:17px;font-weight:800;color:var(--c-text);margin:0 0 2px;"><?php esc_html_e( 'Absensi Mandiri', 'absensi-sekolah' ); ?></h1>
        <p style="font-size:12px;color:var(--c-text-muted);margin:0;"><?php esc_html_e( 'Selfie + GPS', 'absensi-sekolah' ); ?></p>
      </div>
    </div>

    <div class="card" style="padding:18px;">

      <!-- HTTPS warning -->
      <template x-if="!isHttps">
        <div class="alert alert-danger" style="margin-bottom:14px;" role="alert" aria-live="assertive">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
          <span><?php esc_html_e( 'Absen butuh koneksi aman (HTTPS). Hubungi administrator.', 'absensi-sekolah' ); ?></span>
        </div>
      </template>

      <!-- Sesi Switcher (Masuk / Pulang) -->
      <div x-show="step !== 'result'" style="margin-bottom:14px;">
        <div class="seg-group" role="group" aria-label="<?php esc_attr_e( 'Pilih sesi absen', 'absensi-sekolah' ); ?>">
          <button type="button"
                  class="seg-btn"
                  :class="sesi === 'masuk' ? 'seg-active-primary' : ''"
                  @click="sesi = 'masuk'"
                  :aria-pressed="sesi === 'masuk'">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
            <?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?>
          </button>
          <button type="button"
                  class="seg-btn"
                  :class="sesi === 'pulang' ? 'seg-active-info' : ''"
                  @click="sesi = 'pulang'"
                  :aria-pressed="sesi === 'pulang'">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15"/></svg>
            <?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>

      <!-- GPS Status Chip -->
      <div x-show="step !== 'result'" style="margin-bottom:14px;" role="status" aria-live="polite">
        <div class="gps-chip"
             :class="{
               'gps-chip-ok':      gpsStatus === 'ok',
               'gps-chip-warn':    gpsStatus === 'weak',
               'gps-chip-error':   gpsStatus === 'error',
               'gps-chip-loading': gpsStatus === 'waiting'
             }">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
          <span x-show="gpsStatus === 'waiting'"><?php esc_html_e( 'Mendeteksi lokasi…', 'absensi-sekolah' ); ?></span>
          <span x-show="gpsStatus === 'ok'" x-text="'GPS OK ' + gpsAccuracyLabel"></span>
          <span x-show="gpsStatus === 'weak'"><?php esc_html_e( 'Sinyal lemah, tunggu…', 'absensi-sekolah' ); ?> <span x-text="gpsAccuracyLabel"></span></span>
          <span x-show="gpsStatus === 'error'" x-text="gpsError || '<?php echo esc_js( __( 'GPS tidak tersedia', 'absensi-sekolah' ) ); ?>'"></span>
        </div>
      </div>

      <!-- Error Message -->
      <div x-show="errorMsg" x-cloak class="alert alert-danger" style="margin-bottom:14px;" role="alert" aria-live="assertive">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
        <span x-text="errorMsg"></span>
      </div>

      <!-- ── STEP: idle ── -->
      <template x-if="step === 'idle'">
        <div>
          <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;background:#0F172A;border-radius:var(--r-xl);aspect-ratio:3/4;max-height:340px;color:var(--c-text-faint);text-align:center;gap:10px;">
            <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" style="opacity:.45;" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
            <p style="font-size:13px;font-weight:600;margin:0;color:rgba(255,255,255,.45);"><?php esc_html_e( 'Kamera belum aktif', 'absensi-sekolah' ); ?></p>
          </div>
          <button type="button"
                  class="btn btn-primary"
                  style="margin-top:14px;"
                  :disabled="!isHttps"
                  @click="startCamera()">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
            <?php esc_html_e( 'Buka Kamera', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </template>

      <!-- ── STEP: camera ── -->
      <template x-if="step === 'camera'">
        <div>
          <div class="camera-wrap">
            <video x-ref="video" autoplay playsinline muted class="camera-video" aria-label="<?php esc_attr_e( 'Live kamera selfie', 'absensi-sekolah' ); ?>"></video>
            <div class="camera-overlay">
              <div class="face-guide" aria-hidden="true"></div>
            </div>
            <p style="position:absolute;bottom:14px;left:0;right:0;text-align:center;color:white;font-size:11.5px;font-weight:600;background:rgba(0,0,0,.5);margin:0;padding:5px 12px;" aria-live="polite">
              <span x-text="gpsStatus !== 'ok' ? '<?php echo esc_js( __( 'Menunggu GPS siap…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Posisikan wajah dalam oval', 'absensi-sekolah' ) ); ?>'"></span>
            </p>
          </div>
          <canvas x-ref="canvas" style="display:none;" aria-hidden="true"></canvas>
          <button type="button"
                  class="btn btn-success"
                  style="margin-top:12px;font-size:16px;min-height:54px;"
                  :disabled="gpsStatus !== 'ok'"
                  @click="capturePhoto()">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M3 9a2 2 0 012-2h1.5a2 2 0 001.7-.95l.6-1.1A2 2 0 0110.5 4h3a2 2 0 011.7.95l.6 1.1A2 2 0 0017.5 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" stroke-linejoin="round" stroke-linecap="round"/></svg>
            <span x-text="gpsStatus !== 'ok' ? '<?php echo esc_js( __( 'Tunggu GPS Siap…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Ambil Foto', 'absensi-sekolah' ) ); ?>'"></span>
          </button>
        </div>
      </template>

      <!-- ── STEP: preview ── -->
      <template x-if="step === 'preview'">
        <div>
          <div class="capture-preview">
            <img :src="photoUrl" alt="<?php esc_attr_e( 'Preview foto selfie', 'absensi-sekolah' ); ?>">
            <div class="capture-actions">
              <button type="button" class="btn btn-secondary btn-auto" style="flex:1;min-height:40px;font-size:14px;" @click="retakePhoto()">
                <?php esc_html_e( 'Ulangi', 'absensi-sekolah' ); ?>
              </button>
              <button type="button"
                      class="btn btn-primary btn-auto"
                      style="flex:1;min-height:40px;font-size:14px;"
                      :disabled="!canSubmit"
                      @click="submit()">
                <?php esc_html_e( 'Kirim Absen', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- ── STEP: submitting ── -->
      <template x-if="step === 'submitting'">
        <div style="text-align:center;padding:44px 16px;" aria-live="polite" aria-busy="true">
          <div class="spinner spinner-primary spinner-lg" style="margin:0 auto 16px;" role="status"><span style="position:absolute;width:1px;height:1px;overflow:hidden;"><?php esc_html_e( 'Mengirim…', 'absensi-sekolah' ); ?></span></div>
          <p style="font-size:14px;font-weight:700;color:var(--c-text);margin:0 0 4px;"><?php esc_html_e( 'Mengirim absensi…', 'absensi-sekolah' ); ?></p>
          <p style="font-size:12px;color:var(--c-text-muted);margin:0;"><?php esc_html_e( 'Harap tunggu, jangan tutup halaman.', 'absensi-sekolah' ); ?></p>
        </div>
      </template>

      <!-- ── STEP: result ── -->
      <template x-if="step === 'result'">
        <div aria-live="assertive" role="status">

          <!-- Berhasil -->
          <template x-if="result && result.success">
            <div class="result-card" style="background:var(--c-success-soft);border:1.5px solid var(--c-success-mid);">
              <div class="result-icon result-icon-success" aria-hidden="true">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              </div>
              <h2 style="font-size:19px;font-weight:800;color:var(--c-text);margin:0 0 12px;"><?php esc_html_e( 'Absen Berhasil!', 'absensi-sekolah' ); ?></h2>
              <div style="display:flex;flex-wrap:wrap;gap:7px;justify-content:center;margin-bottom:8px;">
                <span class="badge"
                      :class="result.sesi === 'masuk' ? 'badge-primary' : 'badge-info'"
                      x-text="result.sesi === 'masuk' ? '⬆ <?php echo esc_js( __( 'Masuk', 'absensi-sekolah' ) ); ?>' : '⬇ <?php echo esc_js( __( 'Pulang', 'absensi-sekolah' ) ); ?>'">
                </span>
                <span class="badge badge-neutral" x-text="result.jam"></span>
                <span x-show="result.status"
                      class="badge"
                      :class="result.status === 'telat' ? 'badge-warning' : 'badge-success'"
                      x-text="result.status === 'telat' ? '⏰ <?php echo esc_js( __( 'Terlambat', 'absensi-sekolah' ) ); ?>' : '✓ <?php echo esc_js( __( 'Tepat Waktu', 'absensi-sekolah' ) ); ?>'">
                </span>
              </div>
              <p x-show="result.jarak_meter"
                 style="font-size:12.5px;color:var(--c-text-muted);margin:4px 0 0;"
                 x-text="'<?php echo esc_js( __( 'Jarak dari sekolah: ', 'absensi-sekolah' ) ); ?>' + Math.round(result.jarak_meter) + ' m'">
              </p>
            </div>
          </template>

          <!-- Gagal -->
          <template x-if="result && !result.success">
            <div class="result-card" style="background:var(--c-danger-soft);border:1.5px solid var(--c-danger-mid);">
              <div class="result-icon result-icon-danger" aria-hidden="true">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </div>
              <h2 style="font-size:19px;font-weight:800;color:var(--c-text);margin:0 0 8px;"><?php esc_html_e( 'Absen Ditolak', 'absensi-sekolah' ); ?></h2>
              <p style="font-size:13.5px;color:var(--c-text-muted);margin:0;" x-text="result.message || ''"></p>
            </div>
          </template>

          <button type="button" class="btn btn-secondary" style="margin-top:14px;font-size:14px;min-height:44px;" @click="reset()">
            <?php esc_html_e( 'Absen Lagi', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </template>

      <!-- Info note -->
      <p x-show="step !== 'result'" style="font-size:11.5px;color:var(--c-text-faint);text-align:center;margin:16px 0 0;line-height:1.65;">
        <?php esc_html_e( 'Pastikan browser memiliki izin kamera dan lokasi.', 'absensi-sekolah' ); ?><br>
        <?php printf(
            esc_html__( 'Absen diterima dalam radius %s meter dari sekolah.', 'absensi-sekolah' ),
            '<strong>' . esc_html( get_option( 'absensi_radius', 100 ) ) . '</strong>'
        ); ?>
      </p>

    </div><!-- /.card -->
  </div><!-- /.page-wrap -->
</div><!-- /.absensi-app -->
