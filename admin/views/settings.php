<?php
defined( 'ABSPATH' ) || exit;
$lat          = get_option( 'absensi_lat', '-7.250445' );
$lng          = get_option( 'absensi_lng', '112.768845' );
$radius       = get_option( 'absensi_radius', '100' );
$jam_masuk    = get_option( 'absensi_jam_masuk', '07:00' );
$jam_keluar   = get_option( 'absensi_jam_keluar', '15:00' );
$telat_menit  = get_option( 'absensi_telat_menit', '15' );
$akurasi_max  = get_option( 'absensi_akurasi_max', '50' );
$rfid_deb     = get_option( 'absensi_rfid_debounce', '3' );
$retensi_hari = get_option( 'absensi_retensi_hari', '365' );
$wa_gateway   = get_option( 'absensi_wa_gateway', '' );
?>

<div class="wrap st-wrap" id="absensi-settings-app"
     x-data="settingsForm"
     data-lat="<?php echo esc_attr( $lat ); ?>"
     data-lng="<?php echo esc_attr( $lng ); ?>"
     data-radius="<?php echo esc_attr( $radius ); ?>"
     data-jam-masuk="<?php echo esc_attr( $jam_masuk ); ?>"
     data-jam-keluar="<?php echo esc_attr( $jam_keluar ); ?>"
     data-telat-menit="<?php echo esc_attr( $telat_menit ); ?>"
     data-akurasi-max="<?php echo esc_attr( $akurasi_max ); ?>"
     data-rfid-debounce="<?php echo esc_attr( $rfid_deb ); ?>"
     data-retensi-hari="<?php echo esc_attr( $retensi_hari ); ?>"
     data-wa-gateway="<?php echo esc_attr( $wa_gateway ); ?>">

  <!-- Background blobs -->
  <div class="st-bg" aria-hidden="true">
    <div class="st-blob st-blob--1"></div>
    <div class="st-blob st-blob--2"></div>
    <div class="st-blob st-blob--3"></div>
    <div class="st-blob st-blob--4"></div>
  </div>

  <hr class="wp-header-end" style="margin:0;">

  <!-- ══ HERO CARD ══ -->
  <div class="st-hero-card">
    <div class="st-hero-dot-grid" aria-hidden="true"></div>
    <div class="st-hero-body">
      <div class="st-hero-left">
        <div class="st-eyebrow">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          <?php esc_html_e( 'Konfigurasi Sistem', 'absensi-sekolah' ); ?>
        </div>
        <h1 class="st-hero-title">
          <?php esc_html_e( 'Pengaturan', 'absensi-sekolah' ); ?>
          <span class="st-gradient-text"><?php esc_html_e( 'Sistem', 'absensi-sekolah' ); ?></span>
        </h1>
        <p class="st-hero-sub"><?php esc_html_e( 'Lokasi GPS sekolah, jadwal absensi, konfigurasi perangkat, dan integrasi WhatsApp.', 'absensi-sekolah' ); ?></p>
        <div class="st-hero-chips">
          <span class="st-chip st-chip--glass">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
            Radius <span x-text="radius + ' m'" style="font-weight:800;margin-left:2px;"></span>
          </span>
          <span class="st-chip st-chip--glass">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span x-text="fields.absensi_jam_masuk + ' – ' + fields.absensi_jam_keluar" style="font-weight:700;"></span>
          </span>
          <span class="st-chip st-chip--green">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <?php esc_html_e( 'Aktif', 'absensi-sekolah' ); ?>
          </span>
        </div>
      </div>
      <div class="st-hero-right" aria-hidden="true">
        <div class="st-hero-gear">
          <svg width="110" height="110" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width=".55">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast sukses -->
  <div x-show="saved" x-cloak class="st-toast" aria-live="polite">
    <div class="st-toast__ico">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
    </div>
    <?php esc_html_e( 'Pengaturan berhasil disimpan.', 'absensi-sekolah' ); ?>
  </div>

  <!-- ══ MAIN GRID ══ -->
  <div class="st-grid">

    <!-- ── Kolom kiri ── -->
    <div class="st-col">

      <!-- ── Lokasi & Radius ── -->
      <div class="st-card st-card--map">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--blue">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
          </div>
          <div class="st-card__head-text">
            <h2 class="st-card__title"><?php esc_html_e( 'Lokasi & Radius', 'absensi-sekolah' ); ?></h2>
            <p class="st-card__desc"><?php esc_html_e( 'Titik koordinat sekolah dan jangkauan valid absensi GPS.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
        <div class="st-card__body">

          <!-- Peta -->
          <div class="st-field">
            <label class="st-label"><?php esc_html_e( 'Pin Lokasi Sekolah', 'absensi-sekolah' ); ?></label>
            <div x-data="settingsMap"
                 data-lat="<?php echo esc_attr( $lat ); ?>"
                 data-lng="<?php echo esc_attr( $lng ); ?>">
              <div id="absensi-map" class="st-map"></div>
              <button type="button" @click="refresh()" class="st-map-btn">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <?php esc_html_e( 'Refresh peta', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </div>

          <!-- Lat / Lng -->
          <div class="st-row-2">
            <div class="st-field">
              <label class="st-label">Latitude</label>
              <input type="number" step="any" name="absensi_lat" x-model="fields.absensi_lat" class="st-input" required>
              <span x-show="errors.absensi_lat" class="st-err" x-text="errors.absensi_lat"></span>
            </div>
            <div class="st-field">
              <label class="st-label">Longitude</label>
              <input type="number" step="any" name="absensi_lng" x-model="fields.absensi_lng" class="st-input" required>
              <span x-show="errors.absensi_lng" class="st-err" x-text="errors.absensi_lng"></span>
            </div>
          </div>

          <!-- Radius slider -->
          <div class="st-field">
            <div class="st-slider-header">
              <label class="st-label"><?php esc_html_e( 'Radius Valid Absensi', 'absensi-sekolah' ); ?></label>
              <span class="st-slider-badge" x-text="radius + ' m'"></span>
            </div>
            <div class="st-slider-wrap">
              <span class="st-slider-ico">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
              </span>
              <input type="range" min="25" max="500" step="5" x-model.number="radius" class="st-slider">
              <span class="st-slider-ico st-slider-ico--lg">
                <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
              </span>
            </div>
            <div class="st-slider-marks"><span>25 m</span><span>250 m</span><span>500 m</span></div>
            <p class="st-hint"><?php esc_html_e( 'Jarak maksimal siswa bisa melakukan absensi selfie.', 'absensi-sekolah' ); ?></p>
          </div>

          <!-- Akurasi GPS -->
          <div class="st-field">
            <label class="st-label" for="absensi_akurasi_max"><?php esc_html_e( 'Akurasi GPS Minimal (Meter)', 'absensi-sekolah' ); ?></label>
            <div class="st-input-addon">
              <input type="number" id="absensi_akurasi_max" name="absensi_akurasi_max" min="10" max="1000"
                     x-model.number="fields.absensi_akurasi_max" class="st-input st-input--num">
              <span class="st-addon-unit">meter</span>
            </div>
            <span x-show="errors.absensi_akurasi_max" class="st-err" x-text="errors.absensi_akurasi_max"></span>
            <p class="st-hint"><?php esc_html_e( 'Absen ditolak jika akurasi GPS melebihi nilai ini.', 'absensi-sekolah' ); ?></p>
          </div>

        </div><!-- /card body -->
      </div><!-- /lokasi card -->

      <!-- ── WhatsApp ── -->
      <div class="st-card">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--green">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/></svg>
          </div>
          <div class="st-card__head-text">
            <h2 class="st-card__title">
              <?php esc_html_e( 'Gateway WhatsApp', 'absensi-sekolah' ); ?>
              <span class="st-badge-opt"><?php esc_html_e( 'Opsional', 'absensi-sekolah' ); ?></span>
            </h2>
            <p class="st-card__desc"><?php esc_html_e( 'Biarkan kosong jika tidak digunakan.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
        <div class="st-card__body">

          <div class="st-field">
            <label class="st-label" for="absensi_wa_gateway"><?php esc_html_e( 'URL Endpoint API', 'absensi-sekolah' ); ?></label>
            <input type="url" id="absensi_wa_gateway" name="absensi_wa_gateway"
                   x-model="fields.absensi_wa_gateway" class="st-input"
                   placeholder="https://api.gateway.com/send-message">
          </div>

          <div class="st-field">
            <label class="st-label" for="absensi_wa_token"><?php esc_html_e( 'Token API / Authorization', 'absensi-sekolah' ); ?></label>
            <input type="password" id="absensi_wa_token" name="absensi_wa_token"
                   x-model="fields.absensi_wa_token" class="st-input"
                   autocomplete="new-password"
                   placeholder="<?php esc_attr_e( 'Token bearer dari penyedia API…', 'absensi-sekolah' ); ?>">
          </div>

        </div><!-- /card body -->
      </div><!-- /wa card -->

    </div><!-- /kolom kiri -->

    <!-- ── Kolom kanan ── -->
    <div class="st-col">

      <!-- ── Jadwal Standar ── -->
      <div class="st-card">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--orange">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
          <div class="st-card__head-text">
            <h2 class="st-card__title"><?php esc_html_e( 'Jadwal Standar', 'absensi-sekolah' ); ?></h2>
            <p class="st-card__desc"><?php esc_html_e( 'Jam baku sekolah (bisa ditimpa per kelas).', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
        <div class="st-card__body">

          <!-- Schedule preview mini-cards -->
          <div class="st-sched-row">
            <div class="st-sched-card st-sched-card--blue">
              <div class="st-sched-card__ico">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
              </div>
              <span class="st-sched-card__time" x-text="fields.absensi_jam_masuk"></span>
              <span class="st-sched-card__lbl"><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></span>
            </div>
            <div class="st-sched-arr">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </div>
            <div class="st-sched-card st-sched-card--orange">
              <div class="st-sched-card__ico">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
              </div>
              <span class="st-sched-card__time" x-text="'+' + fields.absensi_telat_menit + '\''"></span>
              <span class="st-sched-card__lbl"><?php esc_html_e( 'Telat', 'absensi-sekolah' ); ?></span>
            </div>
            <div class="st-sched-arr">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
            </div>
            <div class="st-sched-card st-sched-card--cyan">
              <div class="st-sched-card__ico">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15"/></svg>
              </div>
              <span class="st-sched-card__time" x-text="fields.absensi_jam_keluar"></span>
              <span class="st-sched-card__lbl"><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></span>
            </div>
          </div>

          <div class="st-row-2">
            <div class="st-field">
              <label class="st-label" for="absensi_jam_masuk"><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></label>
              <input type="time" id="absensi_jam_masuk" name="absensi_jam_masuk"
                     x-model="fields.absensi_jam_masuk" class="st-input st-input--time">
              <span x-show="errors.absensi_jam_masuk" class="st-err" x-text="errors.absensi_jam_masuk"></span>
            </div>
            <div class="st-field">
              <label class="st-label" for="absensi_jam_keluar"><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?></label>
              <input type="time" id="absensi_jam_keluar" name="absensi_jam_keluar"
                     x-model="fields.absensi_jam_keluar" class="st-input st-input--time">
              <span x-show="errors.absensi_jam_keluar" class="st-err" x-text="errors.absensi_jam_keluar"></span>
            </div>
          </div>

          <div class="st-field">
            <label class="st-label" for="absensi_telat_menit"><?php esc_html_e( 'Toleransi Terlambat (Menit)', 'absensi-sekolah' ); ?></label>
            <div class="st-input-addon">
              <input type="number" id="absensi_telat_menit" name="absensi_telat_menit" min="0" max="240"
                     x-model.number="fields.absensi_telat_menit" class="st-input st-input--num">
              <span class="st-addon-unit">menit</span>
            </div>
            <p class="st-hint"><?php esc_html_e( 'Tenggang waktu sebelum status berubah menjadi Telat.', 'absensi-sekolah' ); ?></p>
          </div>

        </div><!-- /card body -->
      </div><!-- /jadwal card -->

      <!-- ── RFID & Penyimpanan ── -->
      <div class="st-card">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--purple">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5"/></svg>
          </div>
          <div class="st-card__head-text">
            <h2 class="st-card__title"><?php esc_html_e( 'RFID & Penyimpanan', 'absensi-sekolah' ); ?></h2>
            <p class="st-card__desc"><?php esc_html_e( 'Konfigurasi scanner kartu dan retensi file selfie.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
        <div class="st-card__body">

          <div class="st-row-2">
            <div class="st-field">
              <label class="st-label" for="absensi_rfid_debounce"><?php esc_html_e( 'Anti Double-Tap', 'absensi-sekolah' ); ?></label>
              <div class="st-input-addon">
                <input type="number" id="absensi_rfid_debounce" name="absensi_rfid_debounce" min="0" max="60"
                       x-model.number="fields.absensi_rfid_debounce" class="st-input st-input--num">
                <span class="st-addon-unit">detik</span>
              </div>
              <span x-show="errors.absensi_rfid_debounce" class="st-err" x-text="errors.absensi_rfid_debounce"></span>
              <p class="st-hint"><?php esc_html_e( 'Abaikan tap berulang dalam durasi ini.', 'absensi-sekolah' ); ?></p>
            </div>
            <div class="st-field">
              <label class="st-label" for="absensi_retensi_hari"><?php esc_html_e( 'Simpan Foto', 'absensi-sekolah' ); ?></label>
              <div class="st-input-addon">
                <input type="number" id="absensi_retensi_hari" name="absensi_retensi_hari" min="0" max="3650"
                       x-model.number="fields.absensi_retensi_hari" class="st-input st-input--num">
                <span class="st-addon-unit">hari</span>
              </div>
              <p class="st-hint"><?php esc_html_e( 'Foto lebih lama otomatis dihapus.', 'absensi-sekolah' ); ?></p>
            </div>
          </div>

        </div><!-- /card body -->
      </div><!-- /rfid card -->

      <!-- ── Tombol Simpan ── -->
      <div class="st-save-bar">
        <div class="st-save-bar__note">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg>
          <?php esc_html_e( 'Perubahan aktif setelah disimpan.', 'absensi-sekolah' ); ?>
        </div>
        <button type="button" @click="save()" :disabled="saving" class="st-btn-save">
          <span x-show="saving" class="st-spinner" aria-hidden="true"></span>
          <svg x-show="!saving" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
          <span x-text="saving
            ? '<?php echo esc_js( __( 'Menyimpan…', 'absensi-sekolah' ) ); ?>'
            : '<?php echo esc_js( __( 'Simpan Perubahan', 'absensi-sekolah' ) ); ?>'">
          </span>
        </button>
      </div>

    </div><!-- /kolom kanan -->
  </div><!-- /st-grid -->
</div><!-- /st-wrap -->

<script>
(function() {
  /* ── 1. Fix Leaflet tile offset by triggering resize at multiple points ──────
     The Alpine settingsMap component has a ResizeObserver on #absensi-map.
     Dispatching 'resize' nudges it to call invalidateSize() once layout is stable. */
  function nudgeLeaflet() {
    window.dispatchEvent(new Event('resize'));
    // Also try to directly call invalidateSize via the Alpine component
    var mapEl = document.getElementById('absensi-map');
    if (mapEl && mapEl._leaflet_id) {
      try {
        var leafletMap = window.L && window.L.map && mapEl._leaflet_map;
        if (!leafletMap) {
          // Walk Leaflet's internal registryre
          for (var k in window) {
            if (window[k] && window[k]._container === mapEl) {
              window[k].invalidateSize({ animate: false, pan: false });
              break;
            }
          }
        } else {
          leafletMap.invalidateSize({ animate: false, pan: false });
        }
      } catch(e) {}
    }
  }

  [100, 300, 600, 1000, 1500, 2500].forEach(function(ms) {
    setTimeout(nudgeLeaflet, ms);
  });

  /* ── 2. Custom school map-pin marker icon ────────────────────────────────────
     We inject CSS that replaces the default Leaflet blue marker image with
     a beautiful custom SVG school pin rendered as a CSS data-URI. */
  var customMarkerCSS = [
    /* Hide Leaflet's default marker image */
    '.st-map .leaflet-marker-icon {',
    '  background: transparent !important;',
    '  border: none !important;',
    '  width: 36px !important;',
    '  height: 44px !important;',
    '  margin-left: -18px !important;',
    '  margin-top: -44px !important;',
    "  background-image: url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 36 44'%3E%3Cdefs%3E%3ClinearGradient id='pg' x1='0%25' y1='0%25' x2='100%25' y2='100%25'%3E%3Cstop offset='0%25' stop-color='%232563EB'/%3E%3Cstop offset='100%25' stop-color='%231D4ED8'/%3E%3C/linearGradient%3E%3Cfilter id='sh' x='-30%25' y='-30%25' width='160%25' height='160%25'%3E%3CfeDropShadow dx='0' dy='3' stdDeviation='3' flood-color='%232563EB' flood-opacity='0.35'/%3E%3C/filter%3E%3C/defs%3E%3Cg filter='url(%23sh)'%3E%3Cellipse cx='18' cy='40' rx='6' ry='3' fill='rgba(37,99,235,0.18)'/%3E%3Cpath d='M18 0C10.27 0 4 6.27 4 14c0 10.5 14 28 14 28S32 24.5 32 14C32 6.27 25.73 0 18 0z' fill='url(%23pg)'/%3E%3Ccircle cx='18' cy='14' r='9' fill='white' opacity='0.95'/%3E%3Cpath d='M18 8 L22 11 L22 12 L20 12 L20 19 L16 19 L16 15 L14 15 L14 19 L12 19 L12 12 L10 12 L10 11 Z' fill='%232563EB' opacity='0.9'/%3E%3C/g%3E%3C/svg%3E\") !important;",
    '  background-size: 36px 44px !important;',
    '  background-repeat: no-repeat !important;',
    '  background-position: center !important;',
    '  image-rendering: auto !important;',
    '}',
    /* Hide the default shadow image */
    '.st-map .leaflet-marker-shadow { display: none !important; }',
  ].join('\n');

  var styleEl = document.createElement('style');
  styleEl.textContent = customMarkerCSS;
  document.head.appendChild(styleEl);
})();
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap');

/* ── Reset & Base ── */
.st-wrap *,.st-wrap *::before,.st-wrap *::after{box-sizing:border-box;}
[x-cloak]{display:none!important;}

/* Background — konsisten dengan dashboard.php */
body.wp-admin{background:#EAF0F6!important;}
#wpcontent,#wpbody-content,#wpbody{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed!important;}

.st-wrap{font-family:'Plus Jakarta Sans',system-ui,sans-serif!important;min-height:100vh;padding-bottom:80px;position:relative;}

/* ── Blobs — kuat & saturated seperti dashboard ── */
.st-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}
.st-blob{position:absolute;border-radius:50%;filter:blur(140px);opacity:1;}
.st-blob--1{width:750px;height:750px;top:-180px;left:-120px;background:radial-gradient(circle,rgba(129,140,248,.55) 0%,rgba(99,102,241,.25) 65%,transparent 100%);}
.st-blob--2{width:700px;height:700px;bottom:-150px;right:-80px;background:radial-gradient(circle,rgba(244,114,182,.50) 0%,rgba(219,39,119,.22) 65%,transparent 100%);}
.st-blob--3{width:600px;height:600px;top:25%;right:8%;background:radial-gradient(circle,rgba(103,232,249,.52) 0%,rgba(6,182,212,.22) 65%,transparent 100%);}
.st-blob--4{width:500px;height:500px;top:55%;left:30%;background:radial-gradient(circle,rgba(251,191,36,.38) 0%,rgba(245,158,11,.14) 65%,transparent 100%);}

/* ── Hero card ── */
.st-hero-card{
  position:relative;z-index:1;
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(32px) saturate(180%);
  -webkit-backdrop-filter:blur(32px) saturate(180%);
  border:1px solid rgba(255,255,255,.75);
  border-radius:24px;
  box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);
  margin:12px 0 18px;overflow:hidden;
}
/* Decorative orbs inside hero */
.st-hero-card::before{content:'';position:absolute;width:220px;height:220px;top:-80px;right:180px;border-radius:50%;background:radial-gradient(circle,rgba(37,99,235,.10) 0%,transparent 70%);filter:blur(38px);pointer-events:none;z-index:0;}
.st-hero-card::after{content:'';position:absolute;width:160px;height:160px;bottom:-55px;right:60px;border-radius:50%;background:radial-gradient(circle,rgba(124,58,237,.09) 0%,transparent 70%);filter:blur(30px);pointer-events:none;z-index:0;}
.st-hero-dot-grid{position:absolute;inset:0;background-image:radial-gradient(rgba(37,99,235,.012) 1px,transparent 1px);background-size:24px 24px;pointer-events:none;}
.st-hero-body{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;padding:28px 32px;gap:16px;flex-wrap:wrap;}
.st-hero-left{flex:1;min-width:240px;}
.st-hero-right{flex-shrink:0;}
.st-hero-gear{opacity:.14;color:#4F46E5;filter:drop-shadow(0 0 16px rgba(99,102,241,.3));}

/* Eyebrow — match dashboard */
.st-eyebrow{display:inline-flex;align-items:center;gap:6px;background:#DBEAFE;border:1px solid rgba(37,99,235,.10);border-radius:8px;padding:5px 11px;font-size:10.5px;font-weight:700;color:#2563EB;letter-spacing:.02em;text-transform:uppercase;margin:0 0 12px;}
.st-hero-title{font-size:clamp(24px,3vw,30px);font-weight:800;color:#1E293B;margin:0 0 8px;letter-spacing:-.025em;line-height:1.12;}
.st-gradient-text{background:linear-gradient(135deg,#2563EB 0%,#7C3AED 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.st-hero-sub{font-size:13.5px;color:#64748B;margin:0 0 16px;line-height:1.55;max-width:460px;}
.st-hero-chips{display:flex;flex-wrap:wrap;gap:8px;}
.st-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 13px;border-radius:999px;font-size:11.5px;font-weight:600;}
.st-chip--glass{background:rgba(255,255,255,.6);border:1px solid rgba(255,255,255,.8);color:#334155;}
.st-chip--green{background:rgba(240,253,244,.88);color:#16A34A;border:1px solid rgba(134,239,172,.38);}

/* ── Toast ── */
.st-toast{position:relative;z-index:2;display:flex;align-items:center;gap:12px;padding:14px 20px;background:rgba(220,252,231,.92);border:1px solid rgba(134,239,172,.55);border-radius:16px;font-size:14px;font-weight:600;color:#16A34A;margin-bottom:20px;backdrop-filter:blur(24px) saturate(180%);-webkit-backdrop-filter:blur(24px) saturate(180%);box-shadow:0 4px 16px rgba(22,163,74,.1);}
.st-toast__ico{width:28px;height:28px;background:rgba(22,163,74,.15);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;}

/* ── Layout Grid ── */
.st-grid{position:relative;z-index:1;display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;}
@media(max-width:900px){.st-grid{grid-template-columns:1fr;}}
.st-col{display:flex;flex-direction:column;gap:16px;}

/* ── Glass cards ── */
.st-card{
  position:relative;
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(32px) saturate(180%);
  -webkit-backdrop-filter:blur(32px) saturate(180%);
  border:1px solid rgba(255,255,255,.75);
  border-radius:24px;
  box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);
  display:flex;flex-direction:column;overflow:hidden;
  transition:transform .18s,box-shadow .18s;
}
.st-card:hover{transform:translateY(-2px);box-shadow:10px 10px 30px rgba(163,177,198,.3),-10px -10px 30px rgba(255,255,255,.9),inset 0 1px 1px rgba(255,255,255,.8);}

.st-card__head{display:flex;align-items:center;gap:14px;padding:18px 20px;border-bottom:1px solid rgba(0,0,0,.05);background:rgba(255,255,255,.22);}
.st-card__icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.st-card__icon--blue{background:#DBEAFE;color:#2563EB;}
.st-card__icon--green{background:#DCFCE7;color:#16A34A;}
.st-card__icon--orange{background:#FEF3C7;color:#D97706;}
.st-card__icon--purple{background:#EDE9FE;color:#7C3AED;}
.st-card__head-text{flex:1;min-width:0;}
.st-card__title{font-size:13.5px;font-weight:700;color:#1E293B;margin:0 0 3px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;line-height:1.2;}
.st-card__desc{font-size:11.5px;color:#64748B;margin:0;line-height:1.4;}
.st-badge-opt{font-size:9.5px;background:rgba(255,255,255,.65);color:#94A3B8;padding:2px 7px;border-radius:5px;text-transform:uppercase;letter-spacing:.04em;font-weight:700;border:1px solid rgba(0,0,0,.05);}

/* Card body */
.st-card__body{padding:16px 20px 20px;display:flex;flex-direction:column;gap:14px;}

/* ── Fields ── */
.st-field{display:flex;flex-direction:column;gap:5px;}
.st-row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.st-label{font-size:10.5px!important;font-weight:800!important;text-transform:uppercase;letter-spacing:.07em;color:#64748B!important;display:block!important;margin-bottom:0!important;}
.st-err{font-size:11.5px;color:#DC2626;font-weight:600;}
.st-hint{font-size:11.5px;color:#94A3B8;margin:0;line-height:1.5;}

/* ── Glass inputs ── */
#absensi-settings-app .st-input{
  background:rgba(255,255,255,.55)!important;
  backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);
  border:1.5px solid rgba(255,255,255,.85)!important;
  border-radius:12px!important;
  padding:10px 14px!important;
  font-size:14px!important;min-height:44px;
  font-family:'Plus Jakarta Sans',sans-serif!important;
  outline:none!important;transition:all .16s;color:#1E293B!important;width:100%;
  box-shadow:inset 4px 4px 10px rgba(163,177,198,.30),inset -4px -4px 10px rgba(255,255,255,.82);
}
#absensi-settings-app .st-input:focus{
  background:rgba(255,255,255,.92)!important;
  border-color:rgba(37,99,235,.40)!important;
  box-shadow:0 0 0 3.5px rgba(37,99,235,.09),inset 3px 3px 7px rgba(163,177,198,.18),inset -2px -2px 5px rgba(255,255,255,.9)!important;
}
#absensi-settings-app .st-input--num{max-width:160px;}
#absensi-settings-app .st-input--time{font-size:17px!important;font-weight:800!important;color:#2563EB!important;text-align:center;letter-spacing:.02em;}

/* Input + unit addon */
.st-input-addon{display:flex;align-items:stretch;gap:0;}
.st-input-addon .st-input{border-radius:12px 0 0 12px!important;flex:1;}
.st-addon-unit{
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(6px);
  border:1.5px solid rgba(255,255,255,.85);
  border-left:none;
  border-radius:0 12px 12px 0;
  padding:0 14px;
  font-size:12px;font-weight:700;color:#64748B;
  min-height:44px;display:flex;align-items:center;white-space:nowrap;
  box-shadow:inset -3px -3px 7px rgba(255,255,255,.82),inset 2px 0 5px rgba(163,177,198,.12);
}

/* ── Slider ── */
.st-slider-header{display:flex;align-items:center;justify-content:space-between;}
.st-slider-badge{font-size:14px;font-weight:800;color:#2563EB;background:#DBEAFE;border:1.5px solid rgba(37,99,235,.18);padding:4px 14px;border-radius:999px;}
.st-slider-wrap{display:flex;align-items:center;gap:10px;margin-top:8px;}
.st-slider-ico{color:#CBD5E1;flex-shrink:0;display:flex;align-items:center;}
.st-slider-ico--lg{color:#94A3B8;}
#absensi-settings-app .st-slider{
  flex:1;-webkit-appearance:none;height:8px;
  background:linear-gradient(90deg,rgba(37,99,235,.22),rgba(163,177,198,.12));
  border-radius:999px;cursor:pointer;outline:none;border:none!important;
  box-shadow:inset 2px 2px 5px rgba(163,177,198,.22),inset -1px -1px 3px rgba(255,255,255,.82);
  padding:0!important;min-height:auto;
}
#absensi-settings-app .st-slider::-webkit-slider-thumb{
  -webkit-appearance:none;width:22px;height:22px;border-radius:50%;
  background:linear-gradient(145deg,#60A5FA,#2563EB);
  border:2.5px solid white;cursor:pointer;
  box-shadow:2px 3px 10px rgba(37,99,235,.40),inset 0 1px 1px rgba(255,255,255,.4);
}
#absensi-settings-app .st-slider::-moz-range-thumb{
  width:22px;height:22px;border-radius:50%;
  background:linear-gradient(145deg,#60A5FA,#2563EB);
  border:2.5px solid white;cursor:pointer;
  box-shadow:2px 3px 10px rgba(37,99,235,.40);
}
.st-slider-marks{display:flex;justify-content:space-between;font-size:10px;color:#94A3B8;margin-top:5px;}

/* ── Map ── */
/* The map container itself: explicit dimensions so Leaflet measures correctly */
.st-map{
  height:270px;
  border-radius:14px;
  border:1.5px solid rgba(255,255,255,.78);
  overflow:hidden;
  box-shadow:inset 2px 2px 8px rgba(163,177,198,.18),0 4px 12px rgba(163,177,198,.14);
  position:relative;
  z-index:0;
  width:100%;
}
/* The Leaflet container must fill the wrapper completely */
.st-map .leaflet-container{
  height:100%!important;
  width:100%!important;
  border-radius:12px;
}
.st-map .leaflet-pane{z-index:4;}
.st-map .leaflet-top,.st-map .leaflet-bottom{z-index:5;}

/* Map card: overflow & transform must be cleared so Leaflet panes position correctly.
   Glass effect is re-applied via ::before pseudo-element to avoid stacking context issues. */
.st-card--map{
  overflow:visible!important;
  transform:none!important;
  backdrop-filter:none!important;
  -webkit-backdrop-filter:none!important;
  background:transparent!important;
  border:none!important;
  box-shadow:none!important;
}
/* The glass look lives on a pseudo-element that covers the card but does NOT create
   a stacking context that would trap Leaflet's absolutely-positioned tile panes */
.st-card--map::before{
  content:'';
  position:absolute;
  inset:0;
  border-radius:24px;
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(32px) saturate(180%);
  -webkit-backdrop-filter:blur(32px) saturate(180%);
  border:1px solid rgba(255,255,255,.75);
  box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);
  pointer-events:none;
  z-index:-1;
}
.st-card--map:hover{transform:none!important;box-shadow:none!important;}
.st-card--map .st-card__head{border-radius:24px 24px 0 0;overflow:hidden;position:relative;z-index:1;}
.st-card--map .st-card__body{border-radius:0 0 24px 24px;overflow:visible;position:relative;z-index:1;}


.st-map-btn{margin-top:8px;font-size:11.5px;color:#64748B;background:rgba(255,255,255,.55);border:1.5px solid rgba(255,255,255,.82);border-radius:10px;cursor:pointer;padding:6px 14px;display:inline-flex;align-items:center;gap:6px;font-family:inherit;transition:all .15s;backdrop-filter:blur(8px);}
.st-map-btn:hover{background:#DBEAFE;color:#2563EB;border-color:rgba(37,99,235,.25);transform:translateY(-1px);}

/* ── Schedule mini-cards ── */
.st-sched-row{
  display:flex;align-items:center;gap:0;
  background:rgba(255,255,255,.42);
  border:1px solid rgba(255,255,255,.72);
  border-radius:18px;padding:14px 16px;
  backdrop-filter:blur(16px) saturate(160%);
  -webkit-backdrop-filter:blur(16px) saturate(160%);
  box-shadow:inset 0 1px 1px rgba(255,255,255,.8);
}
.st-sched-card{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 8px;border-radius:12px;text-align:center;transition:transform .15s;}
.st-sched-card:hover{transform:translateY(-2px);}
.st-sched-card--blue{background:rgba(219,234,254,.72);border:1px solid rgba(147,197,253,.4);}
.st-sched-card--orange{background:rgba(254,243,199,.72);border:1px solid rgba(253,211,77,.4);}
.st-sched-card--cyan{background:rgba(207,250,254,.72);border:1px solid rgba(103,232,249,.4);}
.st-sched-card__ico{width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;}
.st-sched-card--blue .st-sched-card__ico{background:rgba(37,99,235,.14);color:#2563EB;}
.st-sched-card--orange .st-sched-card__ico{background:rgba(245,158,11,.14);color:#D97706;}
.st-sched-card--cyan .st-sched-card__ico{background:rgba(6,182,212,.14);color:#0891B2;}
.st-sched-card__time{font-size:15px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums;font-family:'JetBrains Mono',monospace;}
.st-sched-card--blue .st-sched-card__time{color:#1D4ED8;}
.st-sched-card--orange .st-sched-card__time{color:#B45309;}
.st-sched-card--cyan .st-sched-card__time{color:#0E7490;}
.st-sched-card__lbl{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.65;}
.st-sched-arr{color:#CBD5E1;padding:0 8px;flex-shrink:0;display:flex;align-items:center;}

/* ── Save bar ── */
.st-save-bar{
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(32px) saturate(180%);
  -webkit-backdrop-filter:blur(32px) saturate(180%);
  border:1px solid rgba(255,255,255,.75);
  border-radius:20px;
  box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);
  padding:16px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;
}
.st-save-bar__note{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#64748B;}
.st-btn-save{
  display:inline-flex;align-items:center;justify-content:center;gap:9px;
  background:linear-gradient(145deg,#2563EB,#1D4ED8);
  color:#fff;border:none;border-radius:14px;
  padding:12px 28px;font-size:14.5px;font-weight:700;
  cursor:pointer;font-family:inherit;min-height:46px;
  box-shadow:4px 6px 18px rgba(37,99,235,.38),-1px -1px 6px rgba(255,255,255,.3),inset 0 1px 1px rgba(255,255,255,.22);
  transition:all .16s;white-space:nowrap;letter-spacing:.01em;
}
.st-btn-save:hover:not(:disabled){
  background:linear-gradient(145deg,#1D4ED8,#1e40af);
  transform:translateY(-2px);
  box-shadow:5px 10px 24px rgba(37,99,235,.44),-1px -1px 6px rgba(255,255,255,.3);
}
.st-btn-save:active:not(:disabled){transform:translateY(0);box-shadow:2px 2px 8px rgba(37,99,235,.3),inset 3px 3px 8px rgba(0,0,0,.1);}
.st-btn-save:disabled{opacity:.55;cursor:not-allowed;transform:none;}
@keyframes st-spin{to{transform:rotate(360deg)}}
.st-spinner{display:inline-block;width:15px;height:15px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:st-spin .7s linear infinite;flex-shrink:0;}
</style>
