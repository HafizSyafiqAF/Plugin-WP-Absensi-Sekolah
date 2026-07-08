<?php
/**
 * View DEPRECATED — shortcode lama [absensi_selfie] (model login/role pra-pivot).
 *
 * TAK dipakai kiosk v2. Kiosk publik Absensi Siswa = shortcode [absensi_siswa]
 * → public/views/siswa.php (input nomor induk + selfie + GPS, tanpa login).
 * File ini dipertahankan sebagai notice (BE Shortcodes meng-include tanpa guard);
 * markup gate-login + komponen lama sudah dibuang.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="absensi-kiosk">
  <div class="kiosk-card" style="max-width:480px;">
    <div class="kiosk-head">
      <h1 class="kiosk-title"><?php esc_html_e( 'Absensi Siswa', 'absensi-sekolah' ); ?></h1>
      <p class="kiosk-sub"><?php esc_html_e( 'Halaman ini sudah tidak digunakan. Absensi siswa kini lewat halaman kiosk Absensi Siswa (tanpa login).', 'absensi-sekolah' ); ?></p>
    </div>
  </div>
</div>
