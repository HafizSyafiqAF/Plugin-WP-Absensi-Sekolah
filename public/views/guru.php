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
    </div>

  </div>

  <!-- Petunjuk bawah -->
  <p class="kioskg-hint"><?php esc_html_e( 'Klik di mana saja jika scanner tidak merespons.', 'absensi-sekolah' ); ?></p>

  <!-- Input UID tersembunyi (HID keyboard): autofokus permanen, Enter → submit → clear → refocus. -->
  <input type="text" x-ref="rfid" class="kioskg-hidden-input" autocomplete="off"
         spellcheck="false" tabindex="-1"
         @keydown.enter.prevent="onEnter()"
         @blur="focusInput()"
         aria-label="<?php esc_attr_e( 'Input kartu RFID', 'absensi-sekolah' ); ?>">

</div>
