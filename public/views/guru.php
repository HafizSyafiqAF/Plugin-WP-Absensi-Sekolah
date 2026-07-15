<?php
/**
 * View kiosk — Absensi Guru (RFID).  (design.md §11)
 *
 * Fullscreen gelap. Guru tap kartu SISWA → scanner HID "mengetik" UID + Enter ke
 * input tersembunyi → POST /absen/rfid → panggung hasil BESAR (nama + sesi +
 * status) → kembali otomatis ke layar idle.
 *
 * SESI OTOMATIS: `sesi` tidak dikirim, jadi server yang menentukan —
 * scan 1 = Masuk, scan 2 = Pulang (AbsensiEndpoint: sesi kosong → auto).
 * Halaman ini login-gated di backend (cap `absensi_rfid`), jadi guru yang belum
 * login TIDAK pernah sampai ke sini — WordPress mengalihkannya ke wp-login.
 *
 * Config: AbsensiConfig (restUrl, nonce, rfidDebounce).
 */
defined( 'ABSPATH' ) || exit;

// Identitas guru yang login (halaman ini login-gated cap `absensi_rfid`, jadi selalu ada).
// Tap kartu tercatat atas nama SISWA, bukan guru — tapi guru perlu tahu ia login sebagai
// siapa (device kadang dipinjam/ketuker) + jalan bersih untuk keluar/ganti akun.
$guru_cur   = wp_get_current_user();
$guru_nama  = $guru_cur && $guru_cur->exists() ? ( $guru_cur->display_name ?: $guru_cur->user_login ) : '';
// Logout balik ke kiosk → gate mengalihkan ke wp-login (harus login lagi untuk absen).
$logout_url = wp_logout_url( get_permalink() ?: home_url( '/' ) );
?>
<div class="absensi-kiosk kiosk-guru kiosk-guru2" x-data="kioskGuru" x-cloak
     @click="focusInput()"><?php // klik di mana pun → rebut fokus ke input UID (target scanner) ?>

	<!-- Identitas guru login + Keluar (pojok kiri atas) -->
	<?php if ( '' !== $guru_nama ) : ?>
	<div class="kioskg2-user">
		<span class="kioskg2-user__ico" x-html="$icon( 'user-check', 15 )" aria-hidden="true"></span>
		<span class="kioskg2-user__name">
			<span class="kioskg2-user__label"><?php esc_html_e( 'Login sebagai', 'absensi-sekolah' ); ?></span>
			<?php echo esc_html( $guru_nama ); ?>
		</span>
		<a class="kioskg2-user__out" href="<?php echo esc_url( $logout_url ); ?>"
		   @click.stop="if ( ! confirm( '<?php echo esc_js( __( 'Keluar dari akun ini? Perlu login lagi untuk absen.', 'absensi-sekolah' ) ); ?>' ) ) $event.preventDefault()">
			<span x-html="$icon( 'log-out', 14 )" aria-hidden="true"></span>
			<?php esc_html_e( 'Keluar', 'absensi-sekolah' ); ?>
		</a>
	</div>
	<?php endif; ?>

	<!-- Bisukan/aktifkan beep -->
	<button type="button" class="kioskg2-mute" @click.stop="toggleMute()"
	        :aria-pressed="muted ? 'true' : 'false'"
	        :aria-label="muted
	          ? '<?php echo esc_js( __( 'Aktifkan bunyi', 'absensi-sekolah' ) ); ?>'
	          : '<?php echo esc_js( __( 'Bisukan bunyi', 'absensi-sekolah' ) ); ?>'">
		<span x-html="$icon( muted ? 'volume-x' : 'volume-2', 18 )" aria-hidden="true"></span>
	</button>

	<!-- ── Layar IDLE: jam + tanggal + ajakan tempel kartu ── -->
	<div class="kioskg2-idle" x-show="! fb" x-cloak>
		<p class="kioskg2-clock u-num" x-text="jam"></p>
		<p class="kioskg2-date" x-text="tanggal"></p>

		<div class="kioskg2-scan">
			<span class="kioskg2-scan__ico" x-html="$icon( 'scan-line', 30 )" aria-hidden="true"></span>
		</div>

		<p class="kioskg2-scan__title"><?php esc_html_e( 'Tempelkan Kartu RFID', 'absensi-sekolah' ); ?></p>
		<p class="kioskg2-scan__hint"><?php ; ?></p>

		<!-- Input UID: target scanner HID; bisa juga diketik manual -->
		<label class="u-sr" for="kg-uid"><?php esc_html_e( 'UID kartu RFID', 'absensi-sekolah' ); ?></label>
		<input id="kg-uid" x-ref="rfid" type="text" class="kioskg2-input"
		       x-model="uid" @keydown.enter.prevent="onEnter()" @blur="onBlur($event)"
		       autocomplete="off" spellcheck="false"
		       placeholder="<?php esc_attr_e( 'atau ketik kode…', 'absensi-sekolah' ); ?>">
	</div>

	<!-- ── Panggung HASIL (sukses): avatar + nama + sesi + status + jam ── -->
	<div class="kioskg2-stage" x-show="fb && fb.ok" x-cloak role="status" aria-live="assertive">
		<template x-if="fb && fb.ok">
			<div class="kioskg2-stage__in">
				<span class="kioskg2-avatar" :class="'is-' + fb.tone" x-text="inisial(fb.nama)" aria-hidden="true"></span>
				<p class="kioskg2-name" x-text="fb.nama"></p>

				<p class="kioskg2-action" :class="'is-' + fb.tone" x-text="fb.aksi"></p>
				<p class="kioskg2-status" :class="'is-' + fb.tone" x-show="fb.status" x-text="fb.status"></p>

				<p class="kioskg2-time u-num" x-text="fb.jam"></p>
				<p class="kioskg2-countdown">
					<span x-html="$icon( 'rotate-ccw', 13 )" aria-hidden="true"></span>
					<?php esc_html_e( 'Kembali otomatis dalam', 'absensi-sekolah' ); ?>
					<span class="u-num" x-text="sisaDetik"></span> <?php esc_html_e( 'detik', 'absensi-sekolah' ); ?>
				</p>
			</div>
		</template>
	</div>

	<!-- ── Panggung HASIL (ditolak): pesan asli dari server ── -->
	<div class="kioskg2-stage" x-show="fb && ! fb.ok" x-cloak role="alert" aria-live="assertive">
		<template x-if="fb && ! fb.ok">
			<div class="kioskg2-stage__in">
				<span class="kioskg2-avatar is-danger" x-html="$icon( 'x-circle', 30 )" aria-hidden="true"></span>
				<p class="kioskg2-action is-danger" x-text="fb.statusLabel"></p>
				<p class="kioskg2-msg" x-text="fb.message"></p>

				<!-- Sesi WP habis → tombol login ulang (tap diabaikan sampai login) -->
				<a class="kioskg2-login" x-show="needLogin" x-cloak :href="loginUrl">
					<?php esc_html_e( 'Login Ulang', 'absensi-sekolah' ); ?>
				</a>

				<p class="kioskg2-countdown" x-show="! needLogin">
					<span x-html="$icon( 'rotate-ccw', 13 )" aria-hidden="true"></span>
					<?php esc_html_e( 'Kembali otomatis dalam', 'absensi-sekolah' ); ?>
					<span class="u-num" x-text="sisaDetik"></span> <?php esc_html_e( 'detik', 'absensi-sekolah' ); ?>
				</p>
			</div>
		</template>
	</div>

</div>
