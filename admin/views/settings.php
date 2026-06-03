<?php
defined( 'ABSPATH' ) || exit;

// Simpan settings
if ( isset( $_POST['absensi_settings_nonce'] ) && wp_verify_nonce( $_POST['absensi_settings_nonce'], 'absensi_save_settings' ) ) {
    $fields = [ 'absensi_lat', 'absensi_lng', 'absensi_radius', 'absensi_jam_masuk', 'absensi_jam_keluar', 'absensi_telat_menit', 'absensi_wa_gateway', 'absensi_wa_token' ];
    foreach ( $fields as $field ) {
        update_option( $field, sanitize_text_field( $_POST[ $field ] ?? '' ) );
    }
    echo '<div class="notice notice-success"><p>Pengaturan disimpan.</p></div>';
}
?>
<div class="wrap">
    <h1>⚙️ Pengaturan Absensi Sekolah</h1>
    <form method="post">
        <?php wp_nonce_field( 'absensi_save_settings', 'absensi_settings_nonce' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="absensi_lat">Latitude Sekolah</label></th>
                <td>
                    <input type="number" step="any" id="absensi_lat" name="absensi_lat"
                           value="<?php echo esc_attr( get_option( 'absensi_lat' ) ); ?>"
                           class="regular-text" placeholder="-7.123456" required>
                    <p class="description">Koordinat latitude sekolah (contoh: -7.250445)</p>
                </td>
            </tr>
            <tr>
                <th><label for="absensi_lng">Longitude Sekolah</label></th>
                <td>
                    <input type="number" step="any" id="absensi_lng" name="absensi_lng"
                           value="<?php echo esc_attr( get_option( 'absensi_lng' ) ); ?>"
                           class="regular-text" placeholder="107.123456" required>
                    <p class="description">Koordinat longitude sekolah</p>
                </td>
            </tr>
            <tr>
                <th><label for="absensi_radius">Radius Valid (meter)</label></th>
                <td>
                    <input type="number" min="10" max="1000" id="absensi_radius" name="absensi_radius"
                           value="<?php echo esc_attr( get_option( 'absensi_radius', 100 ) ); ?>"
                           class="small-text">
                    <span class="description"> meter</span>
                </td>
            </tr>
            <tr>
                <th><label for="absensi_jam_masuk">Jam Masuk</label></th>
                <td>
                    <input type="time" id="absensi_jam_masuk" name="absensi_jam_masuk"
                           value="<?php echo esc_attr( get_option( 'absensi_jam_masuk', '07:00' ) ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="absensi_jam_keluar">Jam Keluar</label></th>
                <td>
                    <input type="time" id="absensi_jam_keluar" name="absensi_jam_keluar"
                           value="<?php echo esc_attr( get_option( 'absensi_jam_keluar', '15:00' ) ); ?>">
                </td>
            </tr>
            <tr>
                <th><label for="absensi_telat_menit">Toleransi Terlambat</label></th>
                <td>
                    <input type="number" min="0" max="60" id="absensi_telat_menit" name="absensi_telat_menit"
                           value="<?php echo esc_attr( get_option( 'absensi_telat_menit', 15 ) ); ?>"
                           class="small-text">
                    <span class="description"> menit setelah jam masuk</span>
                </td>
            </tr>
            <tr>
                <th colspan="2"><h2 style="margin:0">WhatsApp Notifikasi (Opsional)</h2></th>
            </tr>
            <tr>
                <th><label for="absensi_wa_gateway">URL Gateway WA</label></th>
                <td>
                    <input type="url" id="absensi_wa_gateway" name="absensi_wa_gateway"
                           value="<?php echo esc_attr( get_option( 'absensi_wa_gateway' ) ); ?>"
                           class="regular-text" placeholder="https://api.fonnte.com/send">
                </td>
            </tr>
            <tr>
                <th><label for="absensi_wa_token">Token WA</label></th>
                <td>
                    <input type="password" id="absensi_wa_token" name="absensi_wa_token"
                           value="<?php echo esc_attr( get_option( 'absensi_wa_token' ) ); ?>"
                           class="regular-text">
                </td>
            </tr>
        </table>
        <?php submit_button( 'Simpan Pengaturan' ); ?>
    </form>
</div>
