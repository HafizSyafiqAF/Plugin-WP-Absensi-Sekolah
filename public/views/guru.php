<?php
/**
 * View kiosk publik — Absensi Guru (RFID).  (design.md §11)
 *
 * Fullscreen mandiri (tanpa chrome admin). Tap kartu → scanner "ketik" UID+Enter
 * → POST /absen/rfid → feedback BESAR (nama + status) → auto reset "Siap scan".
 * Identitas dari rfid_uid (kiosk publik, tanpa login). Config: AbsensiConfig.rfidDebounce.
 *
 * Item ini = kerangka: jam besar real-time + area feedback besar + status idle.
 * Autofokus input + kirim + tangani error = item berikutnya (lihat TODO-FE).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="absensi-kiosk kiosk-guru" x-data="kioskGuru" x-cloak
     @click="focusInput()"><?php // klik di mana pun (kiosk fullscreen) → refocus input UID ?>

  <!-- Toggle bunyi beep (opsional, operator) -->
  <button type="button" class="kioskg-mute btn btn--sm btn--outline" @click="toggleMute()"
          :aria-pressed="muted ? 'true' : 'false'"
          :aria-label="muted
            ? '<?php echo esc_js( __( 'Bunyikan beep', 'absensi-sekolah' ) ); ?>'
            : '<?php echo esc_js( __( 'Bisukan beep', 'absensi-sekolah' ) ); ?>'">
    <span x-html="$icon( muted ? 'volume-x' : 'volume-2', 18 )"></span>
  </button>

  <!-- Jam besar real-time (design.md §11: tabular-nums, tengah-atas) -->
  <div class="kioskg-topbar">
    <span class="kioskg-clock-icon" x-html="$icon( 'clock', 22 )" aria-hidden="true"></span>
    <span class="kiosk-clock" x-text="jam" aria-hidden="true">00:00:00</span>
  </div>

  <!-- Panggung feedback (diumumkan untuk audio kiosk) -->
  <div class="kioskg-stage" aria-live="assertive">

    <!-- Status idle: menunggu tap kartu -->
    <div class="kioskg-idle" x-show="! fb">
      <div class="kioskg-idle__pulse" aria-hidden="true">
        <span x-html="$icon( 'credit-card', 44 )"></span>
      </div>
      <p class="kioskg-idle__text"><?php esc_html_e( 'Siap scan kartu…', 'absensi-sekolah' ); ?></p>
      <p class="kioskg-idle__sub"><?php esc_html_e( 'Tempelkan kartu ke scanner', 'absensi-sekolah' ); ?></p>
    </div>

    <!-- Feedback besar: nama + status + pesan sambutan. Muncul fade/scale, tahan ~2.5 dtk → idle. -->
    <div class="kioskg-feedback" :class="fbClass" x-show="fb" x-cloak
         x-transition.opacity.scale.95.duration.250ms>
      <div class="kioskg-avatar" x-text="fbInitial" aria-hidden="true"></div>
      <p class="kiosk-feedback-name" x-show="fb && fb.nama" x-text="fb ? fb.nama : ''"></p>
      <span class="badge badge--lg" :class="fbBadgeClass" x-text="fb ? fb.statusLabel : ''"></span>
      <p class="kioskg-msg" x-text="fb ? fb.message : ''"></p>
      <!-- Sesi WP habis (401/403): tombol login ulang (redirect balik ke kiosk ini). -->
      <a x-show="needLogin" x-cloak class="btn btn--primary btn--lg kioskg-login" :href="loginUrl">
        <span x-html="$icon( 'log-in', 20 )" aria-hidden="true"></span>
        <?php esc_html_e( 'Login Ulang', 'absensi-sekolah' ); ?>
      </a>
    </div>

  </div>

  <!-- Toggle sesi: operator pilih Masuk / Pulang SEBELUM tap kartu. Default Masuk.
       Klik pill set sesi + refocus input (jaga fokus scanner HID). -->
  <div class="kioskg-sesi" role="group" aria-label="<?php esc_attr_e( 'Pilih sesi absen', 'absensi-sekolah' ); ?>">
    <div class="pill-tabs">
      <button type="button" class="pill" :class="sesi === 'masuk' ? 'is-active' : ''"
              @click="sesi = 'masuk'; focusInput()" :aria-pressed="sesi === 'masuk'">
        <?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?>
      </button>
      <button type="button" class="pill" :class="sesi === 'pulang' ? 'is-active' : ''"
              @click="sesi = 'pulang'; focusInput()" :aria-pressed="sesi === 'pulang'">
        <?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?>
      </button>
    </div>
  </div>

  <!-- Form UID: scanner RFID (HID) auto-ketik UID lalu Enter → submit; bisa juga
       ketik manual + Enter / tombol Kirim. Autofokus dijaga (scanner butuh fokus). -->
  <form class="kioskg-form" @submit.prevent="onEnter()">
    <label class="kioskg-form__label" for="kioskg-uid"><?php esc_html_e( 'UID Kartu', 'absensi-sekolah' ); ?></label>
    <div class="kioskg-form__row">
      <input id="kioskg-uid" type="text" x-ref="rfid" class="input kioskg-input"
             autocomplete="off" spellcheck="false"
             x-model.trim="uid"
             @blur="onBlur($event)"
             placeholder="<?php esc_attr_e( 'Tap kartu / ketik UID…', 'absensi-sekolah' ); ?>"
             aria-label="<?php esc_attr_e( 'Input kartu RFID', 'absensi-sekolah' ); ?>">
      <button type="submit" class="btn btn--primary btn--lg"><?php esc_html_e( 'Kirim', 'absensi-sekolah' ); ?></button>
    </div>
  </form>

  <!-- Petunjuk bawah -->
  <p class="kioskg-hint"><?php esc_html_e( 'Tap kartu ke scanner, atau ketik UID lalu Enter.', 'absensi-sekolah' ); ?></p>

</div>
