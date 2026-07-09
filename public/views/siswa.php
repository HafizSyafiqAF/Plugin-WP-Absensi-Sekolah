<?php
/**
 * View kiosk publik — Absensi Siswa (shell fullscreen).
 *
 * Di-include Shortcodes::render_siswa() via [absensi_siswa]. TANPA login
 * (keamanan absen di endpoint: nomor_induk + rate-limit). Layout fullscreen
 * mandiri (bukan dashboard); semua style di bawah `.absensi-kiosk` (design
 * system BE, scoped). Alpine + helper ($icon, api, absensiToast) dari public.js.
 *
 * ITEM INI = kerangka: header judul + kartu alur di tengah (max-width 480px via
 * .kiosk-card). Alur absen (input Nomor Induk, status GPS, indikator akurasi,
 * kamera selfie, toggle sesi, tombol Absen Sekarang, area hasil, cek status)
 * diisi item TODO-FE berikutnya di dalam `.kiosk-flow`.
 * Endpoint: POST /absen/selfie, GET /absen/status. Config: AbsensiConfig.
 *
 * Catatan: markup pra-pivot (form pengajuan ketidakhadiran ke endpoint yang kini
 * 404, font eksternal, CSS lokal non-design-system, submit tanpa nomor_induk)
 * sudah dibuang — ganti shell design system.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="absensi-kiosk" x-data="kioskSiswa">
	<div class="kiosk-card">
		<header class="kiosk-head">
			<span class="kiosk-brand" x-html="$icon('clipboard-check', 28)" aria-hidden="true"></span>
			<h1 class="kiosk-title"><?php esc_html_e( 'Absensi', 'absensi-sekolah' ); ?></h1>
			<p class="kiosk-sub"><?php esc_html_e( 'Selamat datang, silakan absen.', 'absensi-sekolah' ); ?></p>
		</header>

		<div class="kiosk-flow" :aria-busy="submitting ? 'true' : 'false'">
			<?php // Alur absen: input nomor induk, status GPS + akurasi, kamera selfie, toggle sesi, tombol Absen Sekarang, area hasil, widget cek status. ?>

			<div class="field">
				<label class="field__label" for="absensi-nomor-induk">
					<?php esc_html_e( 'Nomor Induk (NIS/NIP)', 'absensi-sekolah' ); ?>
				</label>
				<div class="input-group">
					<span class="input-group__icon" x-html="$icon('id-card')" aria-hidden="true"></span>
					<input id="absensi-nomor-induk"
					       type="text"
					       inputmode="numeric"
					       class="input"
					       maxlength="30"
					       x-model.trim="nomorInduk"
					       required
					       aria-required="true"
					       autocomplete="off"
					       placeholder="<?php esc_attr_e( 'Ketik nomor induk…', 'absensi-sekolah' ); ?>">
				</div>
			</div>

			<?php // Status GPS: mencari lokasi… / didapat (+akurasi) / ditolak (+coba lagi). ?>
			<div class="kiosk-gps"
			     role="status" aria-live="assertive"
			     :class="{
			       'kiosk-gps--waiting': gpsStatus === 'waiting',
			       'kiosk-gps--ok':      gpsStatus === 'ok',
			       'kiosk-gps--weak':    gpsStatus === 'weak',
			       'kiosk-gps--error':   gpsStatus === 'error'
			     }">
				<span x-show="gpsStatus === 'waiting'" class="spinner spinner--sm" aria-hidden="true"></span>
				<span x-show="gpsStatus !== 'waiting'" class="kiosk-gps__icon" x-html="$icon('map-pin', 18)" aria-hidden="true"></span>

				<span x-show="gpsStatus === 'waiting'"><?php esc_html_e( 'Mencari lokasi…', 'absensi-sekolah' ); ?></span>
				<span x-show="gpsStatus === 'ok'" x-text="'<?php echo esc_js( __( 'Lokasi didapat · Akurasi', 'absensi-sekolah' ) ); ?> ' + gpsAccuracyLabel"></span>
				<span x-show="gpsStatus === 'weak'" x-text="'<?php echo esc_js( __( 'Akurasi rendah', 'absensi-sekolah' ) ); ?> · ' + gpsAccuracyLabel + ' — <?php echo esc_js( __( 'cari sinyal lebih baik', 'absensi-sekolah' ) ); ?>'"></span>
				<span x-show="gpsStatus === 'error'" class="kiosk-gps__err">
					<span x-text="gpsError || '<?php echo esc_js( __( 'Izin lokasi ditolak', 'absensi-sekolah' ) ); ?>'"></span>
					<button type="button" class="btn btn--sm btn--outline" @click="startGps()">
						<span x-html="$icon('map-pin', 16)" aria-hidden="true"></span>
						<span class="btn__label"><?php esc_html_e( 'Aktifkan Lokasi', 'absensi-sekolah' ); ?></span>
					</button>
				</span>
			</div>

			<?php // Kamera selfie (opsional): Buka Kamera → live → Ambil Foto (base64) → preview → Ulang Foto. ?>
			<div class="kiosk-cam">
				<div x-show="cam === 'off'" class="kiosk-cam__frame kiosk-cam__frame--idle">
					<span class="kiosk-cam__ico" x-html="$icon('camera', 40)" aria-hidden="true"></span>
					<p class="kiosk-cam__hint" x-show="!camDenied"><?php esc_html_e( 'Foto selfie (wajib)', 'absensi-sekolah' ); ?></p>
					<p class="kiosk-cam__hint kiosk-cam__hint--warn" x-show="camDenied" role="alert">
						<?php esc_html_e( 'Izin kamera ditolak — aktifkan kamera, selfie wajib untuk absen.', 'absensi-sekolah' ); ?>
					</p>
				</div>

				<div x-show="cam === 'live'" class="kiosk-cam__frame">
					<video x-ref="video"
					       x-effect="if (stream) { $el.srcObject = stream; $el.play().catch(function () {}); }"
					       autoplay playsinline muted
					       class="kiosk-cam__video"
					       aria-label="<?php esc_attr_e( 'Pratinjau kamera selfie', 'absensi-sekolah' ); ?>"></video>
				</div>

				<div x-show="cam === 'preview'" class="kiosk-cam__frame">
					<img :src="photoUrl" class="kiosk-cam__img" alt="<?php esc_attr_e( 'Foto selfie', 'absensi-sekolah' ); ?>">
				</div>

				<canvas x-ref="canvas" hidden aria-hidden="true"></canvas>

				<button x-show="cam === 'off'" type="button" class="btn btn--outline btn--block"
				        :disabled="!isHttps" @click="startCamera()">
					<span x-html="$icon('camera', 18)" aria-hidden="true"></span>
					<span class="btn__label"><?php esc_html_e( 'Buka Kamera', 'absensi-sekolah' ); ?></span>
				</button>
				<button x-show="cam === 'live'" type="button" class="btn btn--primary btn--block" @click="capturePhoto()">
					<span x-html="$icon('camera', 18)" aria-hidden="true"></span>
					<span class="btn__label"><?php esc_html_e( 'Ambil Foto', 'absensi-sekolah' ); ?></span>
				</button>
				<button x-show="cam === 'preview'" type="button" class="btn btn--outline btn--block" @click="retakePhoto()">
					<span x-html="$icon('rotate-ccw', 18)" aria-hidden="true"></span>
					<span class="btn__label"><?php esc_html_e( 'Ulang Foto', 'absensi-sekolah' ); ?></span>
				</button>
			</div>

			<?php // Toggle sesi: Masuk / Pulang. Wajib terpilih (default Masuk), klik = set (tak bisa lepas ke kosong). ?>
			<div class="field">
				<span class="field__label"><?php esc_html_e( 'Sesi', 'absensi-sekolah' ); ?></span>
				<div class="pill-tabs" role="group" aria-label="<?php esc_attr_e( 'Pilih sesi absen', 'absensi-sekolah' ); ?>">
					<button type="button" class="pill" :class="sesi === 'masuk' ? 'is-active' : ''"
					        @click="sesi = 'masuk'" :aria-pressed="sesi === 'masuk'">
						<?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?>
					</button>
					<button type="button" class="pill" :class="sesi === 'pulang' ? 'is-active' : ''"
					        @click="sesi = 'pulang'" :aria-pressed="sesi === 'pulang'">
						<?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?>
					</button>
				</div>
			</div>

			<?php // Tombol Absen Sekarang: Primary lg, disabled sampai GPS siap; loading "Mengirim…". ?>
			<button type="button"
			        class="btn btn--primary btn--lg btn--block"
			        :class="submitting ? 'is-loading' : ''"
			        :disabled="!canSubmit"
			        @click="submit()">
				<span x-show="submitting" class="btn__spin" aria-hidden="true"></span>
				<span x-show="!submitting" x-html="$icon('send', 18)" aria-hidden="true"></span>
				<span class="btn__label"
				      x-text="submitting
				        ? '<?php echo esc_js( __( 'Mengirim…', 'absensi-sekolah' ) ); ?>'
				        : '<?php echo esc_js( __( 'Absen Sekarang', 'absensi-sekolah' ) ); ?>'"></span>
			</button>

			<?php // Petunjuk kenapa tombol nonaktif: selfie wajib (foto belum diambil). ?>
			<p class="kiosk-submit-hint" x-show="!photoBlob && !submitting" x-cloak>
				<?php esc_html_e( 'Ambil foto selfie dulu untuk bisa absen.', 'absensi-sekolah' ); ?>
			</p>

			<?php // Area hasil: kartu feedback warna peta status (hijau hadir / kuning telat / info pulang / merah error). ?>
			<div x-show="result" x-cloak
			     class="kiosk-result" :class="resultClass"
			     role="status" aria-live="assertive">
				<span class="kiosk-result__icon" x-html="$icon(resultIcon, 44)" aria-hidden="true"></span>
				<h2 class="kiosk-result__title" x-text="resultTitle"></h2>
				<p class="kiosk-result__msg" x-text="result && result.message"></p>

				<div x-show="result && result.ok" class="kiosk-result__meta">
					<span x-show="result && result.jam">
						<?php esc_html_e( 'Jam', 'absensi-sekolah' ); ?>:
						<strong x-text="result && result.jam"></strong>
					</span>
					<span x-show="result && result.jarak !== null">
						<?php esc_html_e( 'Jarak', 'absensi-sekolah' ); ?>:
						<strong x-text="(result && result.jarak) + ' m'"></strong>
					</span>
				</div>

				<button type="button" class="btn btn--outline btn--block" @click="reset()">
					<span x-html="$icon('rotate-ccw', 18)" aria-hidden="true"></span>
					<span class="btn__label"><?php esc_html_e( 'Absen Lagi', 'absensi-sekolah' ); ?></span>
				</button>
			</div>

			<?php // Widget Cek Status Hari Ini: input nomor induk → GET /absen/status → tampil sudah_absen/nama/rekap. ?>
			<div class="kiosk-status">
				<h2 class="kiosk-status__head"><?php esc_html_e( 'Cek Status Hari Ini', 'absensi-sekolah' ); ?></h2>

				<div class="field">
					<div class="input-group">
						<span class="input-group__icon" x-html="$icon('id-card')" aria-hidden="true"></span>
						<input type="text" inputmode="numeric" class="input" maxlength="30"
						       x-model.trim="statusNomor"
						       autocomplete="off"
						       @keydown.enter.prevent="checkStatus()"
						       aria-label="<?php esc_attr_e( 'Nomor induk untuk cek status', 'absensi-sekolah' ); ?>"
						       placeholder="<?php esc_attr_e( 'Nomor induk…', 'absensi-sekolah' ); ?>">
					</div>
				</div>

				<button type="button" class="btn btn--outline btn--block"
				        :class="statusLoading ? 'is-loading' : ''"
				        :disabled="statusLoading || !statusNomor"
				        @click="checkStatus()">
					<span x-show="statusLoading" class="btn__spin" aria-hidden="true"></span>
					<span x-show="!statusLoading" x-html="$icon('search', 18)" aria-hidden="true"></span>
					<span class="btn__label"><?php esc_html_e( 'Cek Status', 'absensi-sekolah' ); ?></span>
				</button>

				<div x-show="statusError" x-cloak class="alert alert--danger" role="alert" aria-live="assertive">
					<span class="alert__icon" x-html="$icon('x-circle', 18)" aria-hidden="true"></span>
					<span x-text="statusError"></span>
				</div>

				<template x-if="statusResult">
					<div class="kiosk-status__result" role="status" aria-live="polite">
						<div class="kiosk-status__nama" x-text="statusResult.nama"></div>

						<template x-if="!statusResult.sudah_absen">
							<p class="kiosk-status__empty"><?php esc_html_e( 'Belum absen hari ini.', 'absensi-sekolah' ); ?></p>
						</template>

						<template x-if="statusResult.sudah_absen && statusResult.rekap">
							<div class="kiosk-status__rekap">
								<span class="badge" :class="'badge--' + statusResult.rekap.status" x-text="statusResult.rekap.status"></span>
								<div class="kiosk-status__times">
									<span><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?>: <strong x-text="jamHM(statusResult.rekap.waktu_masuk)"></strong></span>
									<span><?php esc_html_e( 'Keluar', 'absensi-sekolah' ); ?>: <strong x-text="jamHM(statusResult.rekap.waktu_keluar)"></strong></span>
								</div>
							</div>
						</template>
					</div>
				</template>
			</div>
		</div>
	</div>
</div>
