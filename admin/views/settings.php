<?php
/**
 * Admin view — Pengaturan Sistem.  (design.md §9)
 *
 * Konfigurasi operasional: lokasi sekolah + radius, jadwal global, konfigurasi teknis.
 * Konten DI DALAM wp-admin: bungkus `.absensi-app` (design.md §3).
 * Endpoint: GET /settings (prefill) + PUT /settings (simpan) — keduanya sudah ada.
 *
 * CATATAN: kartu WhatsApp TAK ditampilkan (notifikasi WA di luar MVP / dicabut).
 * Nilai `absensi_wa_gateway` tetap dibawa apa adanya oleh state form → tak terhapus
 * saat menyimpan.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="settingsManager">
    <div class="absensi-page">

      <!-- Header -->
      <div class="absensi-page__head">
        <div class="page-title-wrap">
          <h1 class="absensi-page__title"><?php esc_html_e( 'Pengaturan Sistem', 'absensi-sekolah' ); ?></h1>
          <p class="page-subtitle"><?php esc_html_e( 'Konfigurasi operasional, lokasi sekolah, dan kebijakan teknis absensi.', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- Error muat -->
      <div x-show="error" x-cloak class="alert alert--danger" style="align-items:center;">
        <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
        <span style="flex:1;"><?php esc_html_e( 'Gagal memuat pengaturan.', 'absensi-sekolah' ); ?></span>
        <button type="button" class="btn btn--outline btn--sm" @click="loadSettings()"><?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?></button>
      </div>

      <form @submit.prevent="save()">

        <!-- Baris 1: Lokasi Sekolah (lebar) + Jadwal Default -->
        <div class="set-grid">

          <!-- ── Lokasi Sekolah ── -->
          <div class="card set-card">
            <div class="card__head">
              <div class="card-head-ic">
                <span class="card-chip card-chip--primary" x-html="$icon( 'map-pin', 18 )" aria-hidden="true"></span>
                <h2 class="card__title"><?php esc_html_e( 'Lokasi Sekolah', 'absensi-sekolah' ); ?></h2>
              </div>
              <button type="button" class="btn btn--primary btn--sm btn--pill" @click="useMyLocation()"
                      :class="locating ? 'is-loading' : ''" :disabled="locating">
                <span class="btn__spin" x-show="locating" x-cloak aria-hidden="true"></span>
                <span class="btn__label" style="display:inline-flex;align-items:center;gap:8px;">
                  <span x-html="$icon( 'map-pin', 16 )" aria-hidden="true"></span>
                  <?php esc_html_e( 'Gunakan Lokasi Saat Ini', 'absensi-sekolah' ); ?>
                </span>
              </button>
            </div>

            <div class="set-loc">
              <!-- Koordinat + radius -->
              <div class="set-loc__fields">
                <div class="field">
                  <label class="field__label" for="sf-lat"><?php esc_html_e( 'Latitude', 'absensi-sekolah' ); ?></label>
                  <input id="sf-lat" type="number" step="any" class="input" :class="fieldErr.absensi_lat ? 'input--error' : ''"
                         x-model="form.lat" placeholder="-6.208800">
                </div>
                <div class="field">
                  <label class="field__label" for="sf-lng"><?php esc_html_e( 'Longitude', 'absensi-sekolah' ); ?></label>
                  <input id="sf-lng" type="number" step="any" class="input" :class="fieldErr.absensi_lng ? 'input--error' : ''"
                         x-model="form.lng" placeholder="106.845600">
                </div>
                <div class="field">
                  <label class="field__label" for="sf-radius"><?php esc_html_e( 'Radius Kehadiran (Meter)', 'absensi-sekolah' ); ?></label>
                  <div class="input-suffix">
                    <input id="sf-radius" type="number" min="1" class="input" :class="fieldErr.absensi_radius ? 'input--error' : ''"
                           x-model="form.radius">
                    <span class="input-suffix__unit"><?php esc_html_e( 'm', 'absensi-sekolah' ); ?></span>
                  </div>
                </div>
              </div>

              <!-- Pratinjau peta (OpenStreetMap embed — tanpa API key). Keterangan
                   koordinat mengambang DI DALAM peta (ikut acuan desain). -->
              <div class="set-map">
                <template x-if="mapUrl">
                  <iframe class="set-map__frame" loading="lazy" title="<?php esc_attr_e( 'Pratinjau lokasi sekolah', 'absensi-sekolah' ); ?>"
                          :src="'https://www.openstreetmap.org/export/embed.html?bbox=' +
                                (Number(form.lng) - 0.0045) + '%2C' + (Number(form.lat) - 0.0016) + '%2C' +
                                (Number(form.lng) + 0.0045) + '%2C' + (Number(form.lat) + 0.0016) +
                                '&amp;layer=mapnik&amp;marker=' + form.lat + '%2C' + form.lng"></iframe>
                </template>
                <div class="set-map__empty" x-show="! mapUrl" x-cloak>
                  <span x-html="$icon( 'map-pin', 24 )" aria-hidden="true"></span>
                  <span><?php esc_html_e( 'Isi koordinat untuk melihat peta.', 'absensi-sekolah' ); ?></span>
                </div>
                <div class="set-map__caption" x-show="mapUrl" x-cloak>
                  <span class="set-map__coord u-num" x-text="(form.lat || '—') + ', ' + (form.lng || '—')"></span>
                  <a :href="mapUrl" target="_blank" rel="noopener"><?php esc_html_e( 'Buka peta', 'absensi-sekolah' ); ?></a>
                </div>
              </div>
            </div>
          </div>

          <!-- ── Jadwal Default ──
               Dulu dinamai "Jadwal Global" — menyesatkan: kesannya menimpa semua grup, padahal
               ini cuma CADANGAN untuk grup yang belum punya jadwal sendiri (Absensi → Jadwal).
               Grup yang punya jadwal sendiri TIDAK memakai jam di kartu ini. -->
          <div class="card set-card">
            <div class="card__head">
              <div class="card-head-ic">
                <span class="card-chip card-chip--muted" x-html="$icon( 'clock', 18 )" aria-hidden="true"></span>
                <div>
                  <h2 class="card__title"><?php esc_html_e( 'Jadwal Default', 'absensi-sekolah' ); ?></h2>
                  <p class="card__sub"><?php esc_html_e( 'Dipakai grup yang belum punya jadwal sendiri (Senin–Jumat).', 'absensi-sekolah' ); ?></p>
                </div>
              </div>
            </div>

            <div class="field">
              <label class="field__label" for="sf-masuk"><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></label>
              <div class="input-group">
                <span class="input-group__icon" x-html="$icon( 'log-in', 18 )" aria-hidden="true"></span>
                <input id="sf-masuk" type="time" class="input" :class="fieldErr.absensi_jam_masuk ? 'input--error' : ''"
                       x-model="form.jam_masuk">
              </div>
            </div>

            <div class="field" style="margin-top:14px;">
              <label class="field__label" for="sf-keluar"><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?></label>
              <div class="input-group">
                <span class="input-group__icon" x-html="$icon( 'log-out', 18 )" aria-hidden="true"></span>
                <input id="sf-keluar" type="time" class="input" :class="fieldErr.absensi_jam_keluar ? 'input--error' : ''"
                       x-model="form.jam_keluar">
              </div>
              <!-- Jam terbalik = jebakan senyap: mesin alpha menganggap hari sudah selesai sebelum
                   dimulai (semua ditandai Alpha) + gate pulang bolong. Ditolak juga oleh BE (422). -->
              <p class="field__error" x-show="jamTerbalik" x-cloak>
                <?php esc_html_e( 'Jam pulang harus lebih malam dari jam masuk. Shift lintas hari (mis. masuk 22:00 pulang 06:00) belum didukung.', 'absensi-sekolah' ); ?>
              </p>
            </div>

            <!-- Toleransi telat: slider 0–60 menit (option absensi_telat_menit).
                 BEDA dari jam di atas: toleransi berlaku untuk SEMUA grup — dihitung dari jam masuk
                 jadwal grup masing-masing, bukan dari jam default di kartu ini. -->
            <div class="set-tol">
              <div class="set-tol__head">
                <label class="set-tol__label" for="sf-telat"><?php esc_html_e( 'Toleransi Telat', 'absensi-sekolah' ); ?></label>
                <span class="set-tol__val">
                  <span class="u-num" x-text="form.telat_menit"></span> <?php esc_html_e( 'Menit', 'absensi-sekolah' ); ?>
                </span>
              </div>
              <input id="sf-telat" type="range" class="set-tol__range" min="0" max="60" step="1"
                     x-model="form.telat_menit"
                     aria-label="<?php esc_attr_e( 'Toleransi telat dalam menit', 'absensi-sekolah' ); ?>">
              <p class="set-tol__hint">
                <?php esc_html_e( 'Berlaku untuk SEMUA grup — dihitung dari jam masuk jadwal grup masing-masing. Absen setelah jam masuk + toleransi = Telat.', 'absensi-sekolah' ); ?>
              </p>
            </div>
          </div>
        </div>

        <!-- ── Konfigurasi Teknis (3 kolom) ── -->
        <div class="card set-card set-card--wide">
          <div class="card__head">
            <div class="card-head-ic">
              <span class="card-chip card-chip--dark" x-html="$icon( 'settings', 18 )" aria-hidden="true"></span>
              <h2 class="card__title"><?php esc_html_e( 'Konfigurasi Teknis', 'absensi-sekolah' ); ?></h2>
            </div>
          </div>

          <div class="set-tech">
            <!-- Akurasi GPS Max -->
            <div class="set-tech__item">
              <div class="set-tech__head">
                <span class="set-tech__icon" x-html="$icon( 'map-pin', 16 )" aria-hidden="true"></span>
                <span class="set-tech__title"><?php esc_html_e( 'Akurasi GPS Max', 'absensi-sekolah' ); ?></span>
              </div>
              <p class="set-tech__desc"><?php esc_html_e( 'Membatasi pencatatan lokasi di bawah ambang akurasi tertentu.', 'absensi-sekolah' ); ?></p>
              <div class="set-tech__field">
                <label class="u-hidden" for="sf-akurasi"><?php esc_html_e( 'Akurasi GPS maksimum', 'absensi-sekolah' ); ?></label>
                <input id="sf-akurasi" type="number" min="1" class="input input--narrow"
                       :class="fieldErr.absensi_akurasi_max ? 'input--error' : ''" x-model="form.akurasi_max">
                <span class="set-tech__unit"><?php esc_html_e( 'Meter', 'absensi-sekolah' ); ?></span>
              </div>
            </div>

            <!-- Debounce RFID -->
            <div class="set-tech__item">
              <div class="set-tech__head">
                <span class="set-tech__icon" x-html="$icon( 'credit-card', 16 )" aria-hidden="true"></span>
                <span class="set-tech__title"><?php esc_html_e( 'Debounce RFID', 'absensi-sekolah' ); ?></span>
              </div>
              <p class="set-tech__desc"><?php esc_html_e( 'Waktu tunggu minimal antar tap kartu untuk menghindari double scan.', 'absensi-sekolah' ); ?></p>
              <div class="set-tech__field">
                <label class="u-hidden" for="sf-debounce"><?php esc_html_e( 'Debounce RFID', 'absensi-sekolah' ); ?></label>
                <input id="sf-debounce" type="number" min="0" class="input input--narrow"
                       :class="fieldErr.absensi_rfid_debounce ? 'input--error' : ''" x-model="form.rfid_debounce">
                <span class="set-tech__unit"><?php esc_html_e( 'Detik', 'absensi-sekolah' ); ?></span>
              </div>
            </div>

            <!-- Retensi Foto -->
            <div class="set-tech__item">
              <div class="set-tech__head">
                <span class="set-tech__icon" x-html="$icon( 'camera', 16 )" aria-hidden="true"></span>
                <span class="set-tech__title"><?php esc_html_e( 'Retensi Foto', 'absensi-sekolah' ); ?></span>
              </div>
              <p class="set-tech__desc"><?php esc_html_e( 'Lama penyimpanan bukti foto absensi selfie sebelum dihapus otomatis.', 'absensi-sekolah' ); ?></p>
              <div class="set-tech__field">
                <label class="u-hidden" for="sf-retensi"><?php esc_html_e( 'Retensi foto', 'absensi-sekolah' ); ?></label>
                <select id="sf-retensi" class="select" x-model="form.retensi_hari">
                  <option value="30"><?php esc_html_e( '30 Hari', 'absensi-sekolah' ); ?></option>
                  <option value="60"><?php esc_html_e( '60 Hari', 'absensi-sekolah' ); ?></option>
                  <option value="90"><?php esc_html_e( '90 Hari', 'absensi-sekolah' ); ?></option>
                  <option value="180"><?php esc_html_e( '180 Hari', 'absensi-sekolah' ); ?></option>
                  <option value="365"><?php esc_html_e( '365 Hari', 'absensi-sekolah' ); ?></option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Aksi simpan (kanan bawah) -->
        <div class="set-actions">
          <button type="submit" class="btn btn--primary btn--lg btn--pill" :class="saving ? 'is-loading' : ''" :disabled="saving || loading">
            <span class="btn__spin" x-show="saving" x-cloak aria-hidden="true"></span>
            <span class="btn__label" style="display:inline-flex;align-items:center;gap:10px;">
              <span x-html="$icon( 'save', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Simpan Perubahan', 'absensi-sekolah' ); ?>
            </span>
          </button>
        </div>
      </form>

    </div>
  </div>
</div>
