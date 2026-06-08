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
<?php
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
<div class="wrap st-wrap"
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

  <hr class="wp-header-end" style="margin:0;">

  <!-- Header -->
  <div class="st-header">
    <div class="st-header__left">
      <h1 class="st-header__title"><?php esc_html_e( 'Pengaturan Sistem', 'absensi-sekolah' ); ?></h1>
      <p class="st-header__sub"><?php esc_html_e( 'Lokasi, jadwal, perangkat, dan integrasi WhatsApp', 'absensi-sekolah' ); ?></p>
    </div>
  </div>

  <!-- Toast sukses -->
  <div x-show="saved" x-cloak class="st-toast st-toast--ok">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
    </svg>
    <?php esc_html_e( 'Pengaturan berhasil disimpan.', 'absensi-sekolah' ); ?>
  </div>

  <div class="st-grid">

    <!-- ── Kolom kiri ── -->
    <div class="st-col">

      <!-- Lokasi & Radius -->
      <div class="st-card">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--blue">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
            </svg>
          </div>
          <div>
            <h2 class="st-card__title"><?php esc_html_e( 'Lokasi & Radius', 'absensi-sekolah' ); ?></h2>
            <p class="st-card__desc"><?php esc_html_e( 'Titik koordinat sekolah dan area valid absensi GPS.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>

        <!-- Peta -->
        <div class="st-field">
          <label class="st-label"><?php esc_html_e( 'Pin Lokasi Sekolah', 'absensi-sekolah' ); ?></label>
          <div x-data="settingsMap"
               data-lat="<?php echo esc_attr( $lat ); ?>"
               data-lng="<?php echo esc_attr( $lng ); ?>">
            <div id="absensi-map" class="st-map"></div>
            <button type="button" @click="refresh()" class="st-map__refresh">
              <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              <?php esc_html_e( 'Refresh peta', 'absensi-sekolah' ); ?>
            </button>
          </div>
        </div>

        <!-- Lat / Lng -->
        <div class="st-row-2">
          <div class="st-field">
            <label class="st-label">Latitude</label>
            <input type="number" step="any" name="absensi_lat"
                   x-model="fields.absensi_lat"
                   class="st-input" required>
            <span x-show="errors.absensi_lat" class="st-err" x-text="errors.absensi_lat"></span>
          </div>
          <div class="st-field">
            <label class="st-label">Longitude</label>
            <input type="number" step="any" name="absensi_lng"
                   x-model="fields.absensi_lng"
                   class="st-input" required>
            <span x-show="errors.absensi_lng" class="st-err" x-text="errors.absensi_lng"></span>
          </div>
        </div>

        <!-- Radius slider -->
        <div class="st-field">
          <label class="st-label"><?php esc_html_e( 'Radius Valid Absensi', 'absensi-sekolah' ); ?></label>
          <div class="st-slider-row">
            <input type="range" min="25" max="500" step="5" x-model.number="radius" class="st-slider">
            <span class="st-slider-val" x-text="radius + ' m'"></span>
          </div>
          <p class="st-hint"><?php esc_html_e( 'Jarak maksimal siswa bisa melakukan absensi selfie.', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- Akurasi GPS -->
        <div class="st-field">
          <label class="st-label" for="absensi_akurasi_max"><?php esc_html_e( 'Akurasi GPS Minimal (Meter)', 'absensi-sekolah' ); ?></label>
          <input type="number" id="absensi_akurasi_max" name="absensi_akurasi_max" min="10" max="1000"
                 x-model.number="fields.absensi_akurasi_max"
                 class="st-input st-input--short">
          <span x-show="errors.absensi_akurasi_max" class="st-err" x-text="errors.absensi_akurasi_max"></span>
          <p class="st-hint"><?php esc_html_e( 'Absen ditolak jika akurasi GPS melebihi nilai ini.', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- WhatsApp -->
      <div class="st-card">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--green">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z"/>
            </svg>
          </div>
          <div>
            <h2 class="st-card__title">
              <?php esc_html_e( 'Gateway WhatsApp', 'absensi-sekolah' ); ?>
              <span class="st-badge"><?php esc_html_e( 'Opsional', 'absensi-sekolah' ); ?></span>
            </h2>
            <p class="st-card__desc"><?php esc_html_e( 'Biarkan kosong jika tidak digunakan.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>

        <div class="st-field">
          <label class="st-label" for="absensi_wa_gateway"><?php esc_html_e( 'URL Endpoint API', 'absensi-sekolah' ); ?></label>
          <input type="url" id="absensi_wa_gateway" name="absensi_wa_gateway"
                 x-model="fields.absensi_wa_gateway"
                 class="st-input" placeholder="https://api.gateway.com/send-message">
        </div>

        <div class="st-field">
          <label class="st-label" for="absensi_wa_token"><?php esc_html_e( 'Token API / Authorization', 'absensi-sekolah' ); ?></label>
          <input type="password" id="absensi_wa_token" name="absensi_wa_token"
                 x-model="fields.absensi_wa_token"
                 class="st-input" autocomplete="new-password"
                 placeholder="<?php esc_attr_e( 'Token bearer dari penyedia API…', 'absensi-sekolah' ); ?>">
        </div>
      </div>
    </div>

    <!-- ── Kolom kanan ── -->
    <div class="st-col">

      <!-- Jadwal Standar -->
      <div class="st-card">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--orange">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
          </div>
          <div>
            <h2 class="st-card__title"><?php esc_html_e( 'Jadwal Standar', 'absensi-sekolah' ); ?></h2>
            <p class="st-card__desc"><?php esc_html_e( 'Jam baku sekolah (bisa ditimpa per kelas).', 'absensi-sekolah' ); ?></p>
          </div>
        </div>

        <div class="st-row-2">
          <div class="st-field">
            <label class="st-label" for="absensi_jam_masuk"><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></label>
            <input type="time" id="absensi_jam_masuk" name="absensi_jam_masuk"
                   x-model="fields.absensi_jam_masuk"
                   class="st-input st-input--time">
            <span x-show="errors.absensi_jam_masuk" class="st-err" x-text="errors.absensi_jam_masuk"></span>
          </div>
          <div class="st-field">
            <label class="st-label" for="absensi_jam_keluar"><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?></label>
            <input type="time" id="absensi_jam_keluar" name="absensi_jam_keluar"
                   x-model="fields.absensi_jam_keluar"
                   class="st-input st-input--time">
            <span x-show="errors.absensi_jam_keluar" class="st-err" x-text="errors.absensi_jam_keluar"></span>
          </div>
        </div>

        <div class="st-field">
          <label class="st-label" for="absensi_telat_menit"><?php esc_html_e( 'Toleransi Terlambat (Menit)', 'absensi-sekolah' ); ?></label>
          <input type="number" id="absensi_telat_menit" name="absensi_telat_menit" min="0" max="240"
                 x-model.number="fields.absensi_telat_menit"
                 class="st-input st-input--short">
          <p class="st-hint"><?php esc_html_e( 'Tenggang waktu sebelum status berubah menjadi Telat.', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- RFID & Data -->
      <div class="st-card">
        <div class="st-card__head">
          <div class="st-card__icon st-card__icon--purple">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 8.25h15m-16.5 7.5h15m-1.8-13.5l-3.9 19.5m-2.1-19.5l-3.9 19.5"/>
            </svg>
          </div>
          <div>
            <h2 class="st-card__title"><?php esc_html_e( 'RFID & Penyimpanan', 'absensi-sekolah' ); ?></h2>
            <p class="st-card__desc"><?php esc_html_e( 'Konfigurasi scanner kartu dan retensi file.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>

        <div class="st-field">
          <label class="st-label" for="absensi_rfid_debounce"><?php esc_html_e( 'Anti Double-Tap (Detik)', 'absensi-sekolah' ); ?></label>
          <input type="number" id="absensi_rfid_debounce" name="absensi_rfid_debounce" min="0" max="60"
                 x-model.number="fields.absensi_rfid_debounce"
                 class="st-input st-input--short">
          <span x-show="errors.absensi_rfid_debounce" class="st-err" x-text="errors.absensi_rfid_debounce"></span>
          <p class="st-hint"><?php esc_html_e( 'Mengabaikan tap berulang dari kartu yang sama dalam durasi ini.', 'absensi-sekolah' ); ?></p>
        </div>

        <div class="st-field">
          <label class="st-label" for="absensi_retensi_hari"><?php esc_html_e( 'Lama Penyimpanan Foto (Hari)', 'absensi-sekolah' ); ?></label>
          <input type="number" id="absensi_retensi_hari" name="absensi_retensi_hari" min="0" max="3650"
                 x-model.number="fields.absensi_retensi_hari"
                 class="st-input st-input--short">
          <p class="st-hint"><?php esc_html_e( 'Foto selfie lebih lama dari ini akan otomatis dihapus.', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- Tombol simpan -->
      <div class="st-actions">
        <button type="button" @click="save()" :disabled="saving"
                class="st-btn-save">
          <span x-show="saving" class="st-spinner"></span>
          <svg x-show="!saving" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
          </svg>
          <span x-text="saving
            ? '<?php echo esc_js( __( 'Menyimpan…', 'absensi-sekolah' ) ); ?>'
            : '<?php echo esc_js( __( 'Simpan Semua Perubahan', 'absensi-sekolah' ) ); ?>'">
          </span>
        </button>
      </div>

    </div><!-- /kolom kanan -->
  </div><!-- /st-grid -->
</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

/* ── wrapper ── */
.st-wrap{font-family:'Plus Jakarta Sans',sans-serif!important;background:#F5F7FB;min-height:100vh;padding-bottom:48px;}

/* ── header ── */
.st-header{display:flex;align-items:center;justify-content:space-between;padding:20px 0 18px;border-bottom:1px solid #E2E8F0;margin-bottom:24px;}
.st-header__title{font-size:22px;font-weight:800;color:#0F172A;margin:0 0 3px;letter-spacing:-.01em;}
.st-header__sub{font-size:13.5px;color:#64748B;margin:0;font-weight:500;}

/* ── toast ── */
.st-toast{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:12px;font-size:14px;font-weight:600;margin-bottom:20px;}
.st-toast--ok{background:#D1FAE5;color:#059669;border:1px solid #6EE7B7;}
[x-cloak]{display:none!important;}

/* ── 2-kolom grid ── */
.st-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;}
@media(max-width:900px){.st-grid{grid-template-columns:1fr;}}
.st-col{display:flex;flex-direction:column;gap:20px;}

/* ── card ── */
.st-card{background:#fff;border:1px solid #E2E8F0;border-radius:16px;box-shadow:0 2px 8px rgba(15,23,42,.04);padding:20px;display:flex;flex-direction:column;gap:18px;}
.st-card__head{display:flex;align-items:flex-start;gap:14px;padding-bottom:16px;border-bottom:1px solid #F1F5F9;}
.st-card__icon{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.st-card__icon--blue{background:#DBEAFE;color:#2563EB;}
.st-card__icon--green{background:#DCFCE7;color:#16A34A;}
.st-card__icon--orange{background:#FEF3C7;color:#D97706;}
.st-card__icon--purple{background:#EDE9FE;color:#7C3AED;}
.st-card__title{font-size:14.5px;font-weight:700;color:#0F172A;margin:0 0 2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;}
.st-card__desc{font-size:12.5px;color:#64748B;margin:0;line-height:1.4;}

/* ── badge ── */
.st-badge{font-size:10px;background:#F1F5F9;color:#64748B;padding:2px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:.05em;font-weight:700;}

/* ── field ── */
.st-field{display:flex;flex-direction:column;gap:5px;}
.st-row-2{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.st-label{font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:#64748B;}
.st-input{border:1.5px solid #E2E8F0;border-radius:10px;padding:10px 14px;font-size:14px;min-height:44px;font-family:inherit;outline:none;transition:border .15s,box-shadow .15s;background:#F8FAFC;color:#0F172A;width:100%;box-sizing:border-box;}
.st-input:focus{border-color:#2563EB;background:#fff;box-shadow:0 0 0 3px rgba(37,99,235,.15);}
.st-input--short{max-width:200px;}
.st-input--time{font-family:monospace;font-size:16px;font-weight:600;letter-spacing:.05em;}
.st-err{font-size:11.5px;color:#DC2626;font-weight:600;}
.st-hint{font-size:11.5px;color:#94A3B8;margin:0;line-height:1.5;}

/* ── slider ── */
.st-slider-row{display:flex;align-items:center;gap:14px;}
.st-slider{flex:1;accent-color:#2563EB;cursor:pointer;height:6px;}
.st-slider-val{font-size:14px;font-weight:700;color:#2563EB;background:#DBEAFE;padding:4px 14px;border-radius:8px;min-width:68px;text-align:center;white-space:nowrap;}

/* ── peta ── */
.st-map{height:260px;border-radius:12px;border:1px solid #CBD5E1;overflow:hidden;box-shadow:inset 0 2px 4px rgba(0,0,0,.03);}
.st-map__refresh{margin-top:6px;font-size:11.5px;color:#64748B;background:none;border:none;cursor:pointer;padding:0;display:inline-flex;align-items:center;gap:4px;font-family:inherit;transition:color .15s;}
.st-map__refresh:hover{color:#2563EB;}

/* ── actions ── */
.st-actions{display:flex;justify-content:flex-end;}
.st-btn-save{display:inline-flex;align-items:center;justify-content:center;gap:8px;background:#2563EB;color:#fff;border:none;border-radius:10px;padding:12px 28px;font-size:14.5px;font-weight:700;cursor:pointer;font-family:inherit;min-height:44px;box-shadow:0 2px 8px rgba(37,99,235,.25);transition:all .15s;}
.st-btn-save:hover:not(:disabled){background:#1D4ED8;transform:translateY(-1px);box-shadow:0 4px 12px rgba(37,99,235,.35);}
.st-btn-save:disabled{opacity:.6;cursor:not-allowed;transform:none;}
@keyframes spin{to{transform:rotate(360deg)}}
.st-spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;}
</style>
