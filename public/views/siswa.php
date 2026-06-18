<?php
defined( 'ABSPATH' ) || exit;
$radius  = esc_html( get_option( 'absensi_radius', 100 ) );
$jam_msk = esc_html( get_option( 'absensi_jam_masuk', '07:00' ) );
$jam_klr = esc_html( get_option( 'absensi_jam_keluar', '15:00' ) );
?>

<div x-data="absensiSiswa" x-cloak class="sab-wrap">
  <!-- Blob lokal — position:absolute agar pasti ada di belakang glass card -->
  <div class="sab-blob sab-blob--1" aria-hidden="true"></div>
  <div class="sab-blob sab-blob--2" aria-hidden="true"></div>
  <div class="sab-blob sab-blob--3" aria-hidden="true"></div>

  <!-- ── HTTPS notice ── -->
  <template x-if="!isHttps">
    <div class="sab-notice sab-notice--danger" role="alert" aria-live="assertive">
      <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
      <?php esc_html_e( 'Absen memerlukan koneksi aman (HTTPS). Hubungi administrator.', 'absensi-sekolah' ); ?>
    </div>
  </template>

  <!-- ══════════════════════════════
       SINGLE CARD
  ══════════════════════════════ -->
  <div class="sab-card">

    <!-- ── Header ── -->
    <div x-show="step !== 'result'"
         class="sab-hdr">
      <div class="sab-hdr__orb sab-hdr__orb--a" aria-hidden="true"></div>
      <div class="sab-hdr__orb sab-hdr__orb--b" aria-hidden="true"></div>

      <!-- Title row -->
      <div class="sab-hdr__row">
        <div class="sab-hdr__icon" aria-hidden="true">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
        </div>
        <div class="sab-hdr__meta">
          <h1 class="sab-hdr__title"><?php esc_html_e( 'Absensi Mandiri', 'absensi-sekolah' ); ?></h1>
          <p class="sab-hdr__date"><?php echo esc_html( wp_date( 'l, j F Y' ) ); ?></p>
        </div>
        <div class="sab-hdr__jam" aria-label="<?php esc_attr_e( 'Jam masuk dan keluar', 'absensi-sekolah' ); ?>">
          <?php echo $jam_msk; ?><span aria-hidden="true"> – </span><?php echo $jam_klr; ?>
        </div>
      </div>

      <!-- Sesi switcher inside header -->
      <div class="sab-sesi" role="group" aria-label="<?php esc_attr_e( 'Pilih sesi', 'absensi-sekolah' ); ?>">
        <button type="button"
                class="sab-sesi__btn"
                :class="sesi === 'masuk' ? 'sab-sesi__btn--on' : ''"
                @click="sesi = 'masuk'"
                :aria-pressed="sesi === 'masuk'">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
          <?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?>
        </button>
        <button type="button"
                class="sab-sesi__btn"
                :class="sesi === 'pulang' ? 'sab-sesi__btn--on sab-sesi__btn--pulang' : ''"
                @click="sesi = 'pulang'"
                :aria-pressed="sesi === 'pulang'">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15"/></svg>
          <?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?>
        </button>
      </div>
    </div><!-- /.sab-hdr -->

    <!-- ── GPS status ── -->
    <div x-show="step !== 'result'"
         class="sab-gps"
         :class="{
           'sab-gps--ok':   gpsStatus === 'ok',
           'sab-gps--warn': gpsStatus === 'weak',
           'sab-gps--err':  gpsStatus === 'error',
           'sab-gps--wait': gpsStatus === 'waiting'
         }"
         role="status" aria-live="polite">
      <span class="sab-gps__dot" aria-hidden="true"></span>
      <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
      <span x-show="gpsStatus === 'waiting'"><?php esc_html_e( 'Mendeteksi lokasi GPS…', 'absensi-sekolah' ); ?></span>
      <span x-show="gpsStatus === 'ok'" x-text="'GPS siap · ' + gpsAccuracyLabel"></span>
      <span x-show="gpsStatus === 'weak'"><?php esc_html_e( 'Sinyal lemah, tunggu…', 'absensi-sekolah' ); ?> <span x-text="gpsAccuracyLabel"></span></span>
      <span x-show="gpsStatus === 'error'" x-text="gpsError || '<?php echo esc_js( __( 'GPS tidak tersedia', 'absensi-sekolah' ) ); ?>'"></span>
    </div>

    <!-- ── Error message ── -->
    <div x-show="errorMsg" x-cloak class="sab-notice sab-notice--danger sab-notice--incard" role="alert" aria-live="assertive">
      <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
      <span x-text="errorMsg"></span>
    </div>

    <!-- ── Body ── -->
    <div class="sab-body">

      <!-- IDLE -->
      <template x-if="step === 'idle'">
        <div class="sab-step">
          <div class="sab-vf sab-vf--idle" aria-hidden="true">
            <div class="sab-vf__corner sab-vf__corner--tl"></div>
            <div class="sab-vf__corner sab-vf__corner--tr"></div>
            <div class="sab-vf__corner sab-vf__corner--bl"></div>
            <div class="sab-vf__corner sab-vf__corner--br"></div>
            <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" style="opacity:.22;color:white;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
            <p class="sab-vf__label"><?php esc_html_e( 'Kamera belum aktif', 'absensi-sekolah' ); ?></p>
          </div>
          <button type="button" class="sab-btn sab-btn--primary" :disabled="!isHttps" @click="startCamera()">
            <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z"/></svg>
            <?php esc_html_e( 'Buka Kamera', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </template>

      <!-- CAMERA — x-show (bukan x-if) agar $refs.video selalu ada di DOM
           sehingga startCamera() bisa assign srcObject tanpa race condition -->
      <div class="sab-step" x-show="step === 'camera'">
        <div class="sab-vf sab-vf--live">
          <video x-ref="video"
                 x-effect="if(stream){$el.srcObject=stream;$el.play().catch(function(){});}"
                 autoplay playsinline muted class="sab-video" aria-label="<?php esc_attr_e( 'Live kamera selfie', 'absensi-sekolah' ); ?>"></video>
          <div class="sab-vf__corner sab-vf__corner--tl" aria-hidden="true"></div>
          <div class="sab-vf__corner sab-vf__corner--tr" aria-hidden="true"></div>
          <div class="sab-vf__corner sab-vf__corner--bl" aria-hidden="true"></div>
          <div class="sab-vf__corner sab-vf__corner--br" aria-hidden="true"></div>
          <div class="sab-oval" aria-hidden="true"></div>
          <div class="sab-vf__tip" aria-live="polite">
            <span x-text="gpsStatus !== 'ok' ? '<?php echo esc_js( __( 'Tunggu GPS siap…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Posisikan wajah dalam oval', 'absensi-sekolah' ) ); ?>'"></span>
          </div>
        </div>
        <canvas x-ref="canvas" style="display:none;" aria-hidden="true"></canvas>
        <div class="sab-shutter-row">
          <div class="sab-shutter-row__side"></div>
          <button type="button"
                  class="sab-shutter"
                  :class="gpsStatus !== 'ok' ? 'sab-shutter--disabled' : ''"
                  :disabled="gpsStatus !== 'ok'"
                  @click="capturePhoto()"
                  :aria-label="gpsStatus !== 'ok' ? '<?php echo esc_js( __( 'Tunggu GPS siap', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Ambil foto', 'absensi-sekolah' ) ); ?>'">
            <span class="sab-shutter__ring" aria-hidden="true">
              <span class="sab-shutter__dot"></span>
            </span>
          </button>
          <div class="sab-shutter-row__side"></div>
        </div>
      </div>

      <!-- PREVIEW -->
      <template x-if="step === 'preview'">
        <div class="sab-step">
          <div class="sab-vf sab-vf--preview">
            <img :src="photoUrl" class="sab-preview-img" alt="<?php esc_attr_e( 'Preview foto selfie', 'absensi-sekolah' ); ?>">
            <div class="sab-vf__corner sab-vf__corner--tl" aria-hidden="true"></div>
            <div class="sab-vf__corner sab-vf__corner--tr" aria-hidden="true"></div>
            <div class="sab-vf__corner sab-vf__corner--bl" aria-hidden="true"></div>
            <div class="sab-vf__corner sab-vf__corner--br" aria-hidden="true"></div>
          </div>
          <div class="sab-row">
            <button type="button" class="sab-btn sab-btn--ghost" @click="retakePhoto()">
              <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              <?php esc_html_e( 'Ulangi', 'absensi-sekolah' ); ?>
            </button>
            <button type="button" class="sab-btn sab-btn--primary" :disabled="!canSubmit" @click="submit()">
              <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5"/></svg>
              <?php esc_html_e( 'Kirim Absen', 'absensi-sekolah' ); ?>
            </button>
          </div>
        </div>
      </template>

      <!-- SUBMITTING -->
      <template x-if="step === 'submitting'">
        <div class="sab-loading" aria-live="polite" aria-busy="true">
          <div class="sab-spinner" role="status"><span class="sab-sr"><?php esc_html_e( 'Mengirim…', 'absensi-sekolah' ); ?></span></div>
          <p class="sab-loading__title"><?php esc_html_e( 'Mengirim absensi…', 'absensi-sekolah' ); ?></p>
          <p class="sab-loading__sub"><?php esc_html_e( 'Harap tunggu, jangan tutup halaman.', 'absensi-sekolah' ); ?></p>
        </div>
      </template>

      <!-- RESULT -->
      <template x-if="step === 'result'">
        <div aria-live="assertive" role="status">

          <!-- Sukses -->
          <template x-if="result && result.success">
            <div class="sab-result sab-result--ok">
              <div class="sab-result__icon sab-result__icon--ok" aria-hidden="true">
                <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
              </div>
              <h2 class="sab-result__title"><?php esc_html_e( 'Absen Berhasil', 'absensi-sekolah' ); ?></h2>
              <p class="sab-result__sub"><?php esc_html_e( 'Kehadiran kamu telah tercatat.', 'absensi-sekolah' ); ?></p>
              <div class="sab-result__grid">
                <div class="sab-result__cell">
                  <span class="sab-result__lbl"><?php esc_html_e( 'Sesi', 'absensi-sekolah' ); ?></span>
                  <span class="sab-result__val"
                        :class="result.sesi === 'masuk' ? 'sab-result__val--blue' : 'sab-result__val--cyan'"
                        x-text="result.sesi === 'masuk' ? '<?php echo esc_js( __( 'Masuk', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Pulang', 'absensi-sekolah' ) ); ?>'">
                  </span>
                </div>
                <div class="sab-result__cell">
                  <span class="sab-result__lbl"><?php esc_html_e( 'Waktu', 'absensi-sekolah' ); ?></span>
                  <span class="sab-result__val" x-text="result.jam"></span>
                </div>
                <div x-show="result.status" class="sab-result__cell">
                  <span class="sab-result__lbl"><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></span>
                  <span class="sab-result__val"
                        :class="result.status === 'telat' ? 'sab-result__val--warn' : 'sab-result__val--green'"
                        x-text="result.status === 'telat' ? '<?php echo esc_js( __( 'Terlambat', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tepat Waktu', 'absensi-sekolah' ) ); ?>'">
                  </span>
                </div>
                <div x-show="result.jarak_meter" class="sab-result__cell">
                  <span class="sab-result__lbl"><?php esc_html_e( 'Jarak', 'absensi-sekolah' ); ?></span>
                  <span class="sab-result__val sab-result__val--muted" x-text="Math.round(result.jarak_meter) + ' m'"></span>
                </div>
              </div>
              <button type="button" class="sab-btn sab-btn--ghost" style="margin-top:20px;" @click="reset()">
                <?php esc_html_e( 'Absen Lagi', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </template>

          <!-- Gagal -->
          <template x-if="result && !result.success">
            <div class="sab-result sab-result--fail">
              <div class="sab-result__icon sab-result__icon--fail" aria-hidden="true">
                <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
              </div>
              <h2 class="sab-result__title"><?php esc_html_e( 'Absen Ditolak', 'absensi-sekolah' ); ?></h2>
              <p class="sab-result__msg" x-text="result.message || ''"></p>
              <button type="button" class="sab-btn sab-btn--ghost" style="margin-top:20px;" @click="reset()">
                <?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </template>

        </div>
      </template>

    </div><!-- /.sab-body -->

    <!-- Footer note -->
    <p x-show="step !== 'result'" class="sab-foot">
      <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
      <?php printf( esc_html__( 'Radius %s meter dari titik sekolah', 'absensi-sekolah' ), '<strong>' . $radius . '</strong>' ); ?>
    </p>

  </div><!-- /.sab-card -->
</div><!-- /.sab-wrap -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

[x-cloak]{display:none!important;}
html,body{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed !important;min-height:100vh;}
#page,#main,#primary,#content,.site-content,.entry-content,.wp-block-group,
#wpcontent,#wpbody-content,#wpbody{background:transparent !important;}

/* ── Wrap ── */
.sab-wrap *,.sab-wrap *::before,.sab-wrap *::after{box-sizing:border-box;}
.sab-wrap{
  font-family:'Plus Jakarta Sans',-apple-system,sans-serif;
  max-width:680px;
  margin:0 auto;
  padding:16px 0 40px;
  position:relative;
}

/* ── Blob lokal (pasti tampil di belakang glass card) ── */
.sab-blob{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
.sab-blob--1{width:360px;height:360px;top:-130px;left:-160px;background:radial-gradient(circle,rgba(129,140,248,.60) 0%,rgba(99,102,241,.28) 65%,transparent 100%);}
.sab-blob--2{width:320px;height:320px;bottom:-100px;right:-130px;background:radial-gradient(circle,rgba(244,114,182,.55) 0%,rgba(219,39,119,.25) 65%,transparent 100%);}
.sab-blob--3{width:280px;height:280px;top:45%;right:-70px;background:radial-gradient(circle,rgba(103,232,249,.55) 0%,rgba(6,182,212,.25) 65%,transparent 100%);}
.sab-card,.sab-notice{position:relative;z-index:1;}

/* ── Notice ── */
.sab-notice{
  display:flex;align-items:flex-start;gap:9px;
  padding:12px 14px;margin-bottom:10px;
  border-radius:12px;font-size:13px;font-weight:600;line-height:1.5;
}
.sab-notice--danger{background:#FEF2F2;color:#DC2626;border:1px solid #FECACA;}
.sab-notice--incard{margin:0 16px 12px;}

/* ── Card (glassmorphism) ── */
.sab-card{
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(32px) saturate(180%);
  -webkit-backdrop-filter:blur(32px) saturate(180%);
  border:1px solid rgba(255,255,255,.75);
  border-radius:24px;
  overflow:hidden;
  box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);
}

/* ── Header ── */
.sab-hdr{
  padding:22px 20px 18px;
  position:relative;
  overflow:hidden;
  background:rgba(255,255,255,.22);
  border-bottom:1px solid rgba(0,0,0,.05);
}
.sab-hdr__orb{position:absolute;border-radius:50%;pointer-events:none;}
.sab-hdr__orb--a{width:180px;height:180px;top:-70px;right:-50px;background:radial-gradient(circle,rgba(37,99,235,.10) 0%,transparent 70%);filter:blur(38px);}
.sab-hdr__orb--b{width:110px;height:110px;bottom:-45px;left:-25px;background:radial-gradient(circle,rgba(124,58,237,.09) 0%,transparent 70%);filter:blur(30px);}

.sab-hdr__row{display:flex;align-items:center;gap:11px;margin-bottom:18px;position:relative;}
.sab-hdr__icon{
  width:40px;height:40px;flex-shrink:0;
  background:#DBEAFE;
  color:#2563EB;
  border-radius:12px;
  display:flex;align-items:center;justify-content:center;
}
.sab-hdr__meta{flex:1;min-width:0;}
.sab-hdr__title{font-size:16px;font-weight:800;color:#1E293B;margin:0 0 1px;letter-spacing:-.2px;}
.sab-hdr__date{font-size:11px;color:#64748B;margin:0;font-weight:500;}
.sab-hdr__jam{
  flex-shrink:0;
  font-size:11px;font-weight:700;color:#2563EB;
  background:#DBEAFE;
  border:1.5px solid rgba(37,99,235,.18);
  padding:4px 10px;border-radius:999px;
  white-space:nowrap;
}

/* Sesi tabs */
.sab-sesi{
  display:flex;gap:6px;
  background:rgba(255,255,255,.55);
  border:1px solid rgba(255,255,255,.8);
  box-shadow:inset 0 1px 1px rgba(255,255,255,.7);
  border-radius:12px;padding:4px;
  position:relative;
}
.sab-sesi__btn{
  flex:1;display:flex;align-items:center;justify-content:center;gap:6px;
  padding:9px 6px;border-radius:9px;border:none;
  font-family:inherit;font-size:13px;font-weight:700;
  color:#64748B;background:transparent;
  cursor:pointer;min-height:42px;transition:all .18s;
}
.sab-sesi__btn:hover:not(.sab-sesi__btn--on){color:#1E293B;background:rgba(255,255,255,.6);}
.sab-sesi__btn--on{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:white;box-shadow:3px 3px 8px rgba(37,99,235,.3),-1px -1px 4px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.2);}
.sab-sesi__btn--pulang.sab-sesi__btn--on{background:linear-gradient(145deg,#3b82f6,#1d4ed8);}

/* ── GPS ── */
.sab-gps{
  display:flex;align-items:center;gap:7px;
  padding:10px 20px;
  font-size:12px;font-weight:600;
  border-bottom:1px solid rgba(0,0,0,.06);
  color:#94A3B8;
  transition:color .25s,background .25s;
}
.sab-gps__dot{width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0;}
.sab-gps--ok{color:#16A34A;background:rgba(240,253,244,.7);}
.sab-gps--wait{color:#2563EB;background:rgba(219,234,254,.7);}
.sab-gps--wait .sab-gps__dot{animation:sab-pulse 1.1s ease-in-out infinite;}
.sab-gps--warn{color:#D97706;background:rgba(255,251,235,.7);}
.sab-gps--err{color:#DC2626;background:rgba(254,242,242,.7);}

/* ── Body ── */
.sab-body{padding:16px 20px 0;}

/* Viewfinder */
.sab-vf{
  position:relative;border-radius:16px;overflow:hidden;
  width:100%;aspect-ratio:3/4;max-height:300px;
  margin-bottom:12px;
}
.sab-vf--idle{
  background:linear-gradient(145deg,#0F172A,#1E293B);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;
}
.sab-vf--live{background:#0F172A;}
.sab-vf--preview{background:#0F172A;}
.sab-vf__label{font-size:13px;font-weight:600;color:rgba(255,255,255,.3);margin:0;}

/* Corner brackets */
.sab-vf__corner{
  position:absolute;width:20px;height:20px;
  border-color:rgba(255,255,255,.5);border-style:solid;
  z-index:2;pointer-events:none;
}
.sab-vf__corner--tl{top:10px;left:10px;border-width:2px 0 0 2px;border-radius:3px 0 0 0;}
.sab-vf__corner--tr{top:10px;right:10px;border-width:2px 2px 0 0;border-radius:0 3px 0 0;}
.sab-vf__corner--bl{bottom:10px;left:10px;border-width:0 0 2px 2px;border-radius:0 0 0 3px;}
.sab-vf__corner--br{bottom:10px;right:10px;border-width:0 2px 2px 0;border-radius:0 0 3px 0;}

.sab-video{width:100%;height:100%;object-fit:cover;display:block;transform:scaleX(-1);}
.sab-oval{
  position:absolute;top:50%;left:50%;transform:translate(-50%,-55%);
  width:180px;height:230px;
  border:2px solid rgba(255,255,255,.55);border-radius:50%;
  box-shadow:0 0 0 2000px rgba(0,0,0,.35);
  pointer-events:none;z-index:2;
}
.sab-vf__tip{
  position:absolute;bottom:0;left:0;right:0;z-index:3;
  padding:20px 12px 10px;
  background:linear-gradient(transparent,rgba(0,0,0,.6));
  color:white;font-size:11.5px;font-weight:600;text-align:center;
}

.sab-preview-img{width:100%;height:100%;object-fit:cover;display:block;transform:scaleX(-1);}

/* Shutter */
.sab-shutter-row{display:flex;align-items:center;justify-content:space-between;padding:4px 0 12px;}
.sab-shutter-row__side{width:52px;}
.sab-shutter{
  width:64px;height:64px;border-radius:50%;
  background:none;border:none;padding:0;cursor:pointer;
  transition:transform .15s;
}
.sab-shutter:hover:not(:disabled){transform:scale(1.06);}
.sab-shutter:active:not(:disabled){transform:scale(.94);}
.sab-shutter:disabled{opacity:.45;cursor:not-allowed;}
.sab-shutter__ring{
  display:flex;align-items:center;justify-content:center;
  width:64px;height:64px;border-radius:50%;
  border:3px solid white;
  box-shadow:0 0 0 1px rgba(255,255,255,.15),0 4px 14px rgba(0,0,0,.2);
}
.sab-shutter__dot{
  width:50px;height:50px;border-radius:50%;
  background:white;
  box-shadow:0 2px 6px rgba(0,0,0,.12);
}
.sab-shutter--disabled .sab-shutter__dot{animation:sab-pulse 1.1s ease-in-out infinite;}

/* Rows */
.sab-row{display:flex;gap:10px;}

/* Buttons */
.sab-btn{
  display:flex;align-items:center;justify-content:center;gap:7px;
  flex:1;padding:13px 16px;border-radius:12px;
  font-family:inherit;font-size:13.5px;font-weight:700;
  border:none;cursor:pointer;min-height:48px;
  transition:all .17s;
}
.sab-btn--primary{
  background:linear-gradient(145deg,#2563EB,#1D4ED8);
  color:white;
  box-shadow:4px 6px 18px rgba(37,99,235,.38),-1px -1px 6px rgba(255,255,255,.3),inset 0 1px 1px rgba(255,255,255,.22);
}
.sab-btn--primary:hover:not(:disabled){background:linear-gradient(145deg,#1D4ED8,#1e40af);transform:translateY(-2px);box-shadow:5px 8px 20px rgba(37,99,235,.45),-2px -2px 8px rgba(255,255,255,.4),inset 0 1px 1px rgba(255,255,255,.3);}
.sab-btn--primary:disabled{opacity:.42;cursor:not-allowed;transform:none;box-shadow:none;}
.sab-btn--ghost{
  background:rgba(255,255,255,.45);color:#475569;
  border:1.5px solid rgba(255,255,255,.6);
  backdrop-filter:blur(8px);
}
.sab-btn--ghost:hover{background:rgba(255,255,255,.65);color:#1E293B;}

/* Loading */
.sab-loading{display:flex;flex-direction:column;align-items:center;padding:52px 16px;text-align:center;}
.sab-spinner{
  width:44px;height:44px;border-radius:50%;
  border:3px solid #DBEAFE;border-top-color:#2563EB;
  animation:sab-spin .75s linear infinite;
  margin-bottom:18px;
}
.sab-loading__title{font-size:15px;font-weight:800;color:#1E293B;margin:0 0 5px;}
.sab-loading__sub{font-size:12px;color:#94A3B8;margin:0;}

/* Result */
.sab-result{text-align:center;padding:32px 16px 20px;}
.sab-result__icon{
  width:68px;height:68px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 16px;
}
.sab-result__icon--ok{background:#DCFCE7;color:#16A34A;}
.sab-result__icon--fail{background:#FEE2E2;color:#DC2626;}
.sab-result__title{font-size:20px;font-weight:800;color:#0F172A;margin:0 0 4px;letter-spacing:-.3px;}
.sab-result__sub{font-size:13px;color:#64748B;margin:0 0 20px;}
.sab-result__msg{font-size:13px;color:#94A3B8;margin:8px 0 0;}
.sab-result__grid{
  display:grid;grid-template-columns:1fr 1fr;gap:1px;
  background:rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.7);border-radius:14px;overflow:hidden;
  text-align:left;
}
.sab-result__cell{background:rgba(255,255,255,.5);padding:12px 14px;display:flex;flex-direction:column;gap:3px;}
.sab-result__lbl{font-size:10px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.07em;}
.sab-result__val{font-size:15px;font-weight:800;color:#0F172A;}
.sab-result__val--blue{color:#2563EB;}
.sab-result__val--cyan{color:#0369A1;}
.sab-result__val--green{color:#16A34A;}
.sab-result__val--warn{color:#D97706;}
.sab-result__val--muted{color:#64748B;font-weight:600;}

/* Footer */
.sab-foot{
  display:flex;align-items:center;justify-content:center;gap:5px;
  font-size:11px;color:#64748B;margin:0;
  padding:14px 20px 18px;
  font-weight:500;
}
.sab-foot strong{color:#1E293B;}

.sab-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0);}

@keyframes sab-spin{to{transform:rotate(360deg);}}
@keyframes sab-pulse{0%,100%{opacity:1;}50%{opacity:.3;}}
</style>
