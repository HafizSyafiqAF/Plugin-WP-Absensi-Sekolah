<?php
/**
 * View DEPRECATED — shortcode lama [absensi_status] (model login/role pra-pivot).
 *
 * TAK dipakai kiosk v2. Cek status hari ini kini ada sebagai widget di kiosk
 * Absensi Siswa (public/views/siswa.php) via GET /absen/status?nomor_induk=.
 * File ini dipertahankan sebagai notice (BE Shortcodes meng-include tanpa guard).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="absensi-kiosk">
  <div class="kiosk-card" style="max-width:480px;">
    <p class="kiosk-sub"><?php esc_html_e( 'Widget status lama tidak digunakan. Cek status absen ada di halaman kiosk Absensi Siswa.', 'absensi-sekolah' ); ?></p>
  </div>
</div>
