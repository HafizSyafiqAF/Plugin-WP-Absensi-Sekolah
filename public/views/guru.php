<?php
/**
 * View kiosk — Absensi Guru (RFID).  (design.md §11)
 *
 * Satu kartu terang, serasi kiosk siswa. Guru tap kartu → scanner HID "mengetik"
 * UID + Enter ke input tersembunyi → POST /absen/rfid → panggung hasil (avatar +
 * nama + status + sapaan) → kembali otomatis ke layar idle.
 *
 * SESI OTOMATIS: `sesi` tidak dikirim, server yang menentukan (scan 1 = Masuk,
 * scan 2 = Pulang). Login-gated di backend (cap `absensi_rfid`).
 *
 * CATATAN DATA: /absen/rfid hanya memberi `siswa` (nama) + `status` + `message` —
 * TIDAK ada foto/NIP, jadi avatar memakai inisial (seperti kiosk siswa).
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
	<div class="kgv-card">

		<!-- Top bar: brand (kiri) · jam + bisukan (kanan) -->
		<div class="kgv-top">
			<div class="kgv-brand">
				<span class="kgv-brand__ico" x-html="$icon( 'scan-line', 20 )" aria-hidden="true"></span>
				<span class="kgv-brand__txt">
					<span class="kgv-brand__name"><?php esc_html_e( 'Absensi', 'absensi-sekolah' ); ?></span>
					<span class="kgv-brand__sub"><?php esc_html_e( 'Terminal RFID', 'absensi-sekolah' ); ?></span>
				</span>
			</div>
			<div class="kgv-top__right">
				<button type="button" class="kgv-mute" @click.stop="toggleMute()"
				        :aria-pressed="muted ? 'true' : 'false'"
				        :aria-label="muted
				          ? '<?php echo esc_js( __( 'Aktifkan bunyi', 'absensi-sekolah' ) ); ?>'
				          : '<?php echo esc_js( __( 'Bisukan bunyi', 'absensi-sekolah' ) ); ?>'">
					<span x-html="$icon( muted ? 'volume-x' : 'volume-2', 16 )" aria-hidden="true"></span>
				</button>
				<div class="kgv-clock" role="timer">
					<span class="kgv-clock__dot" aria-hidden="true"></span>
					<span class="kgv-clock__time u-num" x-text="jam"></span>
					<span class="kgv-clock__tz"><?php esc_html_e( 'WIB', 'absensi-sekolah' ); ?></span>
				</div>
			</div>
		</div>

		<!-- Identitas guru login + Keluar (device kadang ketuker → operator jelas + jalan keluar) -->
		<?php if ( '' !== $guru_nama ) : ?>
		<div class="kgv-user" x-data="{ konfirmasi: false }">
			<span class="kgv-user__ico" x-html="$icon( 'user-check', 15 )" aria-hidden="true"></span>
			<span class="kgv-user__name">
				<span class="kgv-user__label"><?php esc_html_e( 'Login sebagai', 'absensi-sekolah' ); ?></span>
				<?php echo esc_html( $guru_nama ); ?>
			</span>
			<!-- mousedown.prevent: input UID selalu direbut balik oleh onBlur; tanpa ini fokus
			     berpindah saat mousedown, layout bergeser, dan klik batal sebelum mouseup. -->
			<button type="button" class="kgv-user__out" @mousedown.prevent @click.stop="konfirmasi = true">
				<span x-html="$icon( 'log-out', 14 )" aria-hidden="true"></span>
				<?php esc_html_e( 'Keluar', 'absensi-sekolah' ); ?>
			</button>

			<!-- Popup konfirmasi keluar -->
			<div class="kgv-confirm" x-show="konfirmasi" x-cloak
			     @click.self.stop="konfirmasi = false" @keydown.escape.window="konfirmasi = false"
			     role="dialog" aria-modal="true" aria-labelledby="kg-out-title">
				<div class="kgv-confirm__box" @click.stop>
					<span class="kgv-confirm__ico" x-html="$icon( 'log-out', 26 )" aria-hidden="true"></span>
					<p class="kgv-confirm__title" id="kg-out-title"><?php esc_html_e( 'Keluar dari akun ini?', 'absensi-sekolah' ); ?></p>
					<p class="kgv-confirm__sub">
						<?php
						printf(
							/* translators: %s = nama guru */
							esc_html__( 'Anda login sebagai %s. Perlu login lagi untuk absen.', 'absensi-sekolah' ),
							'<strong>' . esc_html( $guru_nama ) . '</strong>'
						);
						?>
					</p>
					<div class="kgv-confirm__act">
						<button type="button" class="kgv-confirm__batal" @click.stop="konfirmasi = false"><?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?></button>
						<a class="kgv-confirm__ya" href="<?php echo esc_url( $logout_url ); ?>">
							<span x-html="$icon( 'log-out', 15 )" aria-hidden="true"></span>
							<?php esc_html_e( 'Keluar', 'absensi-sekolah' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<!-- ── IDLE: ikon scan berdenyut + ajakan tempel kartu ──
		     Disembunyikan saat hasil via .u-sr (BUKAN x-show/display:none) agar input UID di
		     dalamnya TETAP fokus & menerima tap → guru tap kartu berikutnya tanpa klik. -->
		<div class="kgv-idle" :class="fb ? 'u-sr' : ''" x-cloak>
			<div class="kgv-scan">
				<span class="kgv-scan__ico" x-html="$icon( 'scan-line', 34 )" aria-hidden="true"></span>
			</div>
			<p class="kgv-idle__title"><?php esc_html_e( 'Tempelkan Kartu RFID', 'absensi-sekolah' ); ?></p>
			<p class="kgv-idle__hint"><?php esc_html_e( 'Kartu akan terbaca otomatis oleh terminal', 'absensi-sekolah' ); ?></p>

			<!-- Input UID: target scanner HID; bisa juga diketik manual -->
			<label class="u-sr" for="kg-uid"><?php esc_html_e( 'UID kartu RFID', 'absensi-sekolah' ); ?></label>
			<input id="kg-uid" x-ref="rfid" type="text" class="kgv-input"
			       x-model="uid" @keydown.enter.prevent="onEnter()" @blur="onBlur($event)"
			       autocomplete="off" spellcheck="false"
			       placeholder="<?php esc_attr_e( 'atau ketik kode…', 'absensi-sekolah' ); ?>">
		</div>

		<!-- ── HASIL (sukses): avatar + nama + status + sapaan ── -->
		<div class="kgv-stage" x-show="fb && fb.ok" x-cloak role="status" aria-live="assertive">
			<template x-if="fb && fb.ok">
				<div class="kgv-stage__in">
					<div class="kgv-avatar-wrap" :class="'is-' + fb.tone">
						<span class="kgv-avatar" x-text="inisial(fb.nama)" aria-hidden="true"></span>
						<span class="kgv-avatar__badge" x-html="$icon( 'check', 18 )" aria-hidden="true"></span>
					</div>

					<p class="kgv-name" x-text="fb.nama"></p>

					<span class="kgv-pill" :class="'is-' + fb.tone">
						<span x-html="$icon( fb.aksi === 'PULANG' ? 'log-out' : 'user-check', 18 )" aria-hidden="true"></span>
						<span x-text="fb.statusLabel"></span>
					</span>

					<p class="kgv-msg" x-text="fb.message"></p>

					<p class="kgv-countdown">
						<span x-html="$icon( 'rotate-ccw', 13 )" aria-hidden="true"></span>
						<?php esc_html_e( 'Kembali otomatis dalam', 'absensi-sekolah' ); ?>
						<span class="u-num" x-text="sisaDetik"></span> <?php esc_html_e( 'detik', 'absensi-sekolah' ); ?>
					</p>
				</div>
			</template>
		</div>

		<!-- ── HASIL (ditolak): pesan asli dari server ── -->
		<div class="kgv-stage" x-show="fb && ! fb.ok" x-cloak role="alert" aria-live="assertive">
			<template x-if="fb && ! fb.ok">
				<div class="kgv-stage__in">
					<div class="kgv-avatar-wrap is-danger">
						<span class="kgv-avatar" x-html="$icon( 'x-circle', 34 )" aria-hidden="true"></span>
					</div>
					<span class="kgv-pill is-danger" x-text="fb.statusLabel"></span>
					<p class="kgv-msg" x-text="fb.message"></p>

					<!-- Sesi WP habis → tombol login ulang (tap diabaikan sampai login) -->
					<a class="kgv-login" x-show="needLogin" x-cloak :href="loginUrl">
						<span x-html="$icon( 'log-in', 16 )" aria-hidden="true"></span>
						<?php esc_html_e( 'Login Ulang', 'absensi-sekolah' ); ?>
					</a>

					<p class="kgv-countdown" x-show="! needLogin">
						<span x-html="$icon( 'rotate-ccw', 13 )" aria-hidden="true"></span>
						<?php esc_html_e( 'Kembali otomatis dalam', 'absensi-sekolah' ); ?>
						<span class="u-num" x-text="sisaDetik"></span> <?php esc_html_e( 'detik', 'absensi-sekolah' ); ?>
					</p>
				</div>
			</template>
		</div>

	</div>
</div>
