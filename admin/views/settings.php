<?php
/**
 * Admin view — Pengaturan.  (design.md §9)
 *
 * Konfigurasi sistem (lokasi/GPS, jam kerja, RFID, retensi, WhatsApp). Konten DI DALAM
 * wp-admin: bungkus `.absensi-app`, tanpa sidebar/topbar plugin (design.md §3).
 * Endpoint: GET /settings (prefill), PUT /settings (simpan). Token WA tak di-prefill.
 *
 * Dibangun bertahap per item TODO-FE. Item ini = 5 card + prefill.
 * Map picker + Simpan (PUT) + 422 = item berikutnya.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="settingsManager">
    <div class="absensi-page">

      <!-- Header halaman (design.md §3.1 / §9): judul + Simpan -->
      <div class="absensi-page__head">
        <h1 class="absensi-page__title"><?php esc_html_e( 'Pengaturan', 'absensi-sekolah' ); ?></h1>
        <div class="absensi-page__actions">
          <button type="button" class="btn btn--primary" @click="save()"
                  :class="saving ? 'is-loading' : ''" :disabled="saving || loading">
            <span class="btn__spin" x-show="saving" x-cloak aria-hidden="true"></span>
            <span class="btn__label"><?php esc_html_e( 'Simpan', 'absensi-sekolah' ); ?></span>
          </button>
        </div>
      </div>

      <!-- Error prefill (fallback AbsensiAdmin.settings gagal) -->
      <div x-show="error" x-cloak class="alert alert--danger" style="align-items:center;">
        <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
        <span style="flex:1;"><?php esc_html_e( 'Gagal memuat pengaturan.', 'absensi-sekolah' ); ?></span>
        <button type="button" class="btn btn--outline btn--sm" @click="loadSettings()"><?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?></button>
      </div>

      <div class="settings-grid">

        <!-- Card: Lokasi & GPS -->
        <div class="card settings-card--wide">
          <div class="card__head"><div class="card-head-ic">
            <span class="card-chip card-chip--primary" x-html="$icon( 'map-pin', 18 )" aria-hidden="true"></span>
            <h3 class="card__title"><?php esc_html_e( 'Lokasi & GPS', 'absensi-sekolah' ); ?></h3>
          </div></div>
          <p class="t-caption u-muted" style="margin:-8px 0 12px;"><?php esc_html_e( 'Titik sekolah + radius toleransi absen selfie.', 'absensi-sekolah' ); ?></p>
          <div class="settings-row">
            <div class="field">
              <label class="field__label" for="s-lat"><?php esc_html_e( 'Latitude', 'absensi-sekolah' ); ?></label>
              <input id="s-lat" type="number" step="any" class="input" x-model="form.lat"
                     :class="fieldErr.absensi_lat ? 'input--error' : ''" @input="fieldErr.absensi_lat = false">
            </div>
            <div class="field">
              <label class="field__label" for="s-lng"><?php esc_html_e( 'Longitude', 'absensi-sekolah' ); ?></label>
              <input id="s-lng" type="number" step="any" class="input" x-model="form.lng"
                     :class="fieldErr.absensi_lng ? 'input--error' : ''" @input="fieldErr.absensi_lng = false">
            </div>
          </div>

          <!-- Map picker (design.md §9): isi dari GPS perangkat + verifikasi di peta.
               Peta interaktif penuh (Leaflet) butuh enqueue BE — belum tersedia. -->
          <div class="settings-map">
            <button type="button" class="btn btn--outline btn--sm" @click="useMyLocation()"
                    :class="locating ? 'is-loading' : ''" :disabled="locating">
              <span class="btn__spin" x-show="locating" x-cloak aria-hidden="true"></span>
              <span class="btn__label" style="display:inline-flex;align-items:center;gap:6px;">
                <span x-html="$icon( 'map-pin', 16 )" aria-hidden="true"></span>
                <?php esc_html_e( 'Gunakan Lokasi Saat Ini', 'absensi-sekolah' ); ?>
              </span>
            </button>
            <a x-show="mapUrl" x-cloak :href="mapUrl" target="_blank" rel="noopener" class="btn btn--ghost btn--sm">
              <span x-html="$icon( 'map-pin', 16 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Lihat di Peta', 'absensi-sekolah' ); ?>
            </a>
          </div>
          <div class="settings-row">
            <div class="field">
              <label class="field__label" for="s-radius"><?php esc_html_e( 'Radius (m)', 'absensi-sekolah' ); ?></label>
              <input id="s-radius" type="number" min="25" max="500" class="input"
                     :class="fieldErr.absensi_radius ? 'input--error' : ''" x-model="form.radius"
                     @input="fieldErr.absensi_radius = false">
              <span class="field__label" style="font-weight:400;"><?php esc_html_e( 'Maks 500 m (server membatasi 25–500).', 'absensi-sekolah' ); ?></span>
            </div>
            <div class="field">
              <label class="field__label" for="s-akurasi"><?php esc_html_e( 'Akurasi GPS Maks (m)', 'absensi-sekolah' ); ?></label>
              <input id="s-akurasi" type="number" min="1" class="input" x-model="form.akurasi_max">
            </div>
          </div>
        </div>

        <!-- Card: Jam Kerja -->
        <div class="card settings-card--wide">
          <div class="card__head"><div class="card-head-ic">
            <span class="card-chip card-chip--info" x-html="$icon( 'clock', 18 )" aria-hidden="true"></span>
            <h3 class="card__title"><?php esc_html_e( 'Jam Kerja', 'absensi-sekolah' ); ?></h3>
          </div></div>
          <p class="t-caption u-muted" style="margin:-8px 0 12px;"><?php esc_html_e( 'Jam masuk/keluar + toleransi telat.', 'absensi-sekolah' ); ?></p>
          <div class="settings-row settings-row--3">
            <div class="field">
              <label class="field__label" for="s-masuk"><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></label>
              <input id="s-masuk" type="time" class="input" x-model="form.jam_masuk"
                     :class="fieldErr.absensi_jam_masuk ? 'input--error' : ''" @input="fieldErr.absensi_jam_masuk = false">
            </div>
            <div class="field">
              <label class="field__label" for="s-keluar"><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?></label>
              <input id="s-keluar" type="time" class="input" x-model="form.jam_keluar"
                     :class="fieldErr.absensi_jam_keluar ? 'input--error' : ''" @input="fieldErr.absensi_jam_keluar = false">
            </div>
            <div class="field">
              <label class="field__label" for="s-telat"><?php esc_html_e( 'Toleransi Telat (menit)', 'absensi-sekolah' ); ?></label>
              <input id="s-telat" type="number" min="0" class="input" x-model="form.telat_menit">
            </div>
          </div>
        </div>

        <!-- Card: RFID -->
        <div class="card">
          <div class="card__head"><div class="card-head-ic">
            <span class="card-chip card-chip--purple" x-html="$icon( 'credit-card', 18 )" aria-hidden="true"></span>
            <h3 class="card__title"><?php esc_html_e( 'RFID', 'absensi-sekolah' ); ?></h3>
          </div></div>
          <p class="t-caption u-muted" style="margin:-8px 0 12px;"><?php esc_html_e( 'Jeda anti double-tap kartu.', 'absensi-sekolah' ); ?></p>
          <div class="field" style="max-width:280px;">
            <label class="field__label" for="s-debounce"><?php esc_html_e( 'Debounce Anti Double-Tap (detik)', 'absensi-sekolah' ); ?></label>
            <input id="s-debounce" type="number" min="0" class="input" x-model="form.rfid_debounce">
          </div>
        </div>

        <!-- Card: Retensi -->
        <div class="card">
          <div class="card__head"><div class="card-head-ic">
            <span class="card-chip card-chip--warning" x-html="$icon( 'rotate-ccw', 18 )" aria-hidden="true"></span>
            <h3 class="card__title"><?php esc_html_e( 'Retensi', 'absensi-sekolah' ); ?></h3>
          </div></div>
          <p class="t-caption u-muted" style="margin:-8px 0 12px;"><?php esc_html_e( 'Umur simpan foto selfie sebelum dihapus otomatis.', 'absensi-sekolah' ); ?></p>
          <div class="field" style="max-width:280px;">
            <label class="field__label" for="s-retensi"><?php esc_html_e( 'Retensi Foto Selfie (hari)', 'absensi-sekolah' ); ?></label>
            <input id="s-retensi" type="number" min="1" class="input" x-model="form.retensi_hari">
          </div>
        </div>

        <!-- Card: WhatsApp (luar MVP) -->
        <div class="card settings-card--muted settings-card--wide">
          <div class="card__head" style="justify-content:flex-start;gap:10px;">
            <span class="card-chip card-chip--muted" x-html="$icon( 'send', 18 )" aria-hidden="true"></span>
            <h3 class="card__title"><?php esc_html_e( 'WhatsApp', 'absensi-sekolah' ); ?></h3>
            <span class="badge badge--izin"><?php esc_html_e( 'Belum aktif — luar MVP', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="settings-row">
            <div class="field">
              <label class="field__label" for="s-wa-gateway"><?php esc_html_e( 'Gateway URL', 'absensi-sekolah' ); ?></label>
              <input id="s-wa-gateway" type="text" class="input" x-model="form.wa_gateway" autocomplete="off">
            </div>
            <div class="field">
              <label class="field__label" for="s-wa-token"><?php esc_html_e( 'Token', 'absensi-sekolah' ); ?></label>
              <input id="s-wa-token" type="password" class="input" x-model="form.wa_token" autocomplete="new-password"
                     :placeholder="hasToken ? '••••••••' : ''">
              <span class="field__label" style="font-weight:400;"><?php esc_html_e( 'Tak ditampilkan demi keamanan. Isi hanya bila ingin mengganti.', 'absensi-sekolah' ); ?></span>
            </div>
          </div>
        </div>

      </div>

    </div>
  </div>
</div>
