<?php
/**
 * View kiosk publik — Absensi Siswa (satu layar, kartu terang).
 *
 * Di-include Shortcodes::render_siswa() via [absensi_siswa]. TANPA login
 * (keamanan absen di endpoint: nomor_induk + rate-limit). Semua style di bawah
 * `.absensi-kiosk.kiosk-siswa` (scoped). Alpine + helper ($icon, api, absensiToast)
 * dari public.js, komponen `kioskSiswa`.
 *
 * ALUR (satu layar — bukan wizard):
 *   NIS (numpad) → nama siswa dicek ke GET /absen/status → ambil selfie →
 *   status GPS → "Absen Sekarang" (POST /absen/selfie). Hasil ditampilkan
 *   sebagai kartu overlay (berhasil/ditolak) + "Absensi Berikutnya".
 *
 * Sesi (masuk/pulang) DITENTUKAN SERVER dari jam — kiosk tak punya toggle.
 * Selfie WAJIB untuk sesi masuk (kebijakan BE: kosong = 422 foto_wajib).
 *
 * CATATAN DATA: endpoint publik hanya memberi `nama` (tak ada kelas/grup).
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="absensi-kiosk kiosk-siswa" x-data="kioskSiswa">
	<div class="ksv-card">

		<!-- Command bar: brand (kiri) + jam berjalan (kanan) — bahasa desain dashboard -->
		<div class="ksv-top">
			<div class="ksv-brand">
				<span class="ksv-brand__ico" x-html="$icon( 'id-card', 20 )" aria-hidden="true"></span>
				<span class="ksv-brand__txt">
					<span class="ksv-brand__name"><?php esc_html_e( 'Absensi', 'absensi-sekolah' ); ?></span>
									</span>
			</div>
			<div class="ksv-clock" role="timer">
				<span class="ksv-clock__dot" aria-hidden="true"></span>
				<span class="ksv-clock__time u-num" x-text="jam"></span>
				<span class="ksv-clock__tz"><?php esc_html_e( 'WIB', 'absensi-sekolah' ); ?></span>
			</div>
		</div>

		<!-- ══ FORM (disembunyikan saat kartu hasil tampil) ══ -->
		<div class="ksv-body" x-show="! result" x-cloak>

			<!-- Input NIS via keyboard: ketik langsung (keyboard fisik) atau tap field
			     di HP → keyboard angka muncul (inputmode="numeric"). Enter = submit. -->
			<span class="ksv-label"><?php esc_html_e( 'Nomor Induk', 'absensi-sekolah' ); ?></span>
			<div class="ksv-nis" :class="lookupError ? 'ksv-nis--error' : ''">
				<span class="ksv-nis__ico" x-html="$icon( 'id-card', 20 )" aria-hidden="true"></span>
				<label class="u-sr" for="ks-nis"><?php esc_html_e( 'Nomor induk siswa', 'absensi-sekolah' ); ?></label>
				<input id="ks-nis" type="text" inputmode="numeric" autocomplete="off" enterkeyhint="done"
				       class="ksv-nis__input" x-model.trim="nomorInduk"
				       @input.debounce.400ms="cekNis()"
				       @keydown.enter.prevent="canSubmit && submit()"
				       placeholder="<?php esc_attr_e( 'Masukkan NIS…', 'absensi-sekolah' ); ?>">
				<span class="ksv-nis__spin" x-show="lookupBusy" x-cloak aria-hidden="true"></span>
			</div>

			<!-- Nama siswa ditemukan / nomor tak terdaftar -->
			<div class="ksv-found" x-show="siswaNama" x-cloak>
				<span class="ksv-found__avatar" x-text="inisial(siswaNama)" aria-hidden="true"></span>
				<span class="ksv-found__main">
					<span class="ksv-found__name" x-text="siswaNama"></span>
					<span class="ksv-found__meta" x-text="sudahAbsen
						? '<?php echo esc_js( __( 'Sudah tercatat hari ini', 'absensi-sekolah' ) ); ?>'
						: '<?php echo esc_js( __( 'Belum absen hari ini', 'absensi-sekolah' ) ); ?>'"></span>
				</span>
				<span class="ksv-found__ok" x-html="$icon( 'check-circle-2', 18 )" aria-hidden="true"></span>
			</div>
			<p class="ksv-err" x-show="lookupError" x-cloak role="alert" x-text="lookupError"></p>

			<!-- Area kamera selfie -->
			<span class="ksv-label"><?php esc_html_e( 'Foto Selfie', 'absensi-sekolah' ); ?></span>
			<div class="ksv-cam">
				<!-- Belum aktif: pandu isi NIS dulu (kamera dikunci sampai siswa ditemukan) -->
				<div class="ksv-cam__view" x-show="cam === 'off'">
					<span class="ksv-cam__face" x-html="$icon( 'user-check', 40 )" aria-hidden="true"></span>
					<span class="ksv-cam__pill" x-show="! camDenied && ! siswaNama"><?php esc_html_e( 'Masukkan NIS dulu', 'absensi-sekolah' ); ?></span>
					<span class="ksv-cam__pill" x-show="! camDenied && siswaNama" x-cloak><?php esc_html_e( 'Siap ambil foto', 'absensi-sekolah' ); ?></span>
					<span class="ksv-cam__pill ksv-cam__pill--warn" x-show="camDenied" x-cloak role="alert">
						<?php esc_html_e( 'Izin kamera ditolak', 'absensi-sekolah' ); ?>
					</span>
				</div>
				<!-- Kamera hidup -->
				<div class="ksv-cam__view" x-show="cam === 'live'" x-cloak>
					<video x-ref="video" class="ksv-cam__media" autoplay playsinline muted
					       x-effect="if (cam === 'live' && stream) $refs.video.srcObject = stream"></video>
					<span class="ksv-cam__pill"><?php esc_html_e( 'Posisikan wajah, lalu Jepret', 'absensi-sekolah' ); ?></span>
				</div>
				<!-- Foto diambil -->
				<div class="ksv-cam__view" x-show="cam === 'preview'" x-cloak>
					<img :src="photoUrl" class="ksv-cam__media" alt="<?php esc_attr_e( 'Foto selfie', 'absensi-sekolah' ); ?>">
					<span class="ksv-cam__pill ksv-cam__pill--ok">
						<span x-html="$icon( 'check-circle-2', 15 )" aria-hidden="true"></span>
						<?php esc_html_e( 'Foto siap', 'absensi-sekolah' ); ?>
					</span>
				</div>
				<canvas x-ref="canvas" class="u-sr"></canvas>
			</div>

			<!-- Aksi kamera: Retake (aktif setelah ada foto) + Ambil/Jepret Foto -->
			<div class="ksv-cam-actions">
				<button type="button" class="ksv-cam-btn ksv-cam-btn--ghost"
				        @click="retakePhoto()" :disabled="cam !== 'preview'">
					<span x-html="$icon( 'rotate-ccw', 16 )" aria-hidden="true"></span>
					<?php esc_html_e( 'Retake', 'absensi-sekolah' ); ?>
				</button>
				<!-- Kamera dikunci sampai NIS valid (siswa ditemukan) → fokus isi NIS dulu -->
				<button type="button" class="ksv-cam-btn ksv-cam-btn--primary"
				        @click="cam === 'live' ? capturePhoto() : startCamera()"
				        :disabled="! siswaNama || cam === 'preview' || camDenied">
					<span x-html="$icon( 'camera', 16 )" aria-hidden="true"></span>
					<span x-text="cam === 'live'
						? '<?php echo esc_js( __( 'Ambil Foto', 'absensi-sekolah' ) ); ?>'
						: '<?php echo esc_js( __( 'Buka Kamera', 'absensi-sekolah' ) ); ?>'"></span>
				</button>
			</div>

			<!-- Status GPS -->
			<div class="ksv-gps" :class="'ksv-gps--' + gpsStatus" role="status" aria-live="polite">
				<span class="ksv-gps__ico">
					<span x-show="gpsStatus === 'waiting'" class="ksv-gps__spin" aria-hidden="true"></span>
					<span x-show="gpsStatus === 'ok'"    x-html="$icon( 'map-pin', 16 )" aria-hidden="true"></span>
					<span x-show="gpsStatus === 'weak'"  x-html="$icon( 'alert-triangle', 16 )" aria-hidden="true"></span>
					<span x-show="gpsStatus === 'error'" x-html="$icon( 'x-circle', 16 )" aria-hidden="true"></span>
				</span>
				<span class="ksv-gps__txt" x-show="gpsStatus === 'waiting'"><?php esc_html_e( 'Mencari lokasi…', 'absensi-sekolah' ); ?></span>
				<span class="ksv-gps__txt" x-show="gpsStatus === 'ok'"
				      x-text="'<?php echo esc_js( __( 'Lokasi Sesuai', 'absensi-sekolah' ) ); ?> (' + gpsAccuracyLabel + ')'"></span>
				<span class="ksv-gps__txt" x-show="gpsStatus === 'weak'"
				      x-text="'<?php echo esc_js( __( 'Akurasi rendah', 'absensi-sekolah' ) ); ?> (' + gpsAccuracyLabel + ')'"></span>
				<span class="ksv-gps__txt" x-show="gpsStatus === 'error'" x-text="gpsError"></span>
				<button type="button" class="ksv-gps__retry" x-show="gpsStatus === 'error'" x-cloak @click="startGps()">
					<?php esc_html_e( 'Coba lagi', 'absensi-sekolah' ); ?>
				</button>
			</div>

			<!-- Submit -->
			<button type="button" class="ksv-submit" @click="submit()" :disabled="! canSubmit">
				<span class="ksv-submit__spin" x-show="submitting" x-cloak aria-hidden="true"></span>
				<span class="ksv-submit__lbl">
					<span x-html="$icon( 'fingerprint', 20 )" aria-hidden="true"></span>
					<span x-text="submitting
						? '<?php echo esc_js( __( 'Mengirim…', 'absensi-sekolah' ) ); ?>'
						: '<?php echo esc_js( __( 'Absen Sekarang', 'absensi-sekolah' ) ); ?>'"></span>
				</span>
			</button>

		</div><!-- /.ksv-body -->

		<!-- ══ HASIL (overlay dalam kartu) ══ -->
		<div class="ksv-result" x-show="result" x-cloak aria-live="polite">
			<span class="ksv-result__ico" :class="hasilBerhasil ? 'is-ok' : 'is-err'"
			      x-html="$icon( hasilBerhasil ? 'check-circle-2' : 'x-circle', 34 )" aria-hidden="true"></span>

			<h2 class="ksv-result__title" :class="hasilBerhasil ? 'is-ok' : 'is-err'"
			    x-text="hasilBerhasil
			      ? '<?php echo esc_js( __( 'Berhasil!', 'absensi-sekolah' ) ); ?>'
			      : '<?php echo esc_js( __( 'Absen Ditolak', 'absensi-sekolah' ) ); ?>'"></h2>

			<!-- Guard `result &&` WAJIB: <template x-if> tetap dievaluasi walau x-show=false. -->
			<template x-if="result && hasilBerhasil">
				<div class="ksv-result__body">
					<p class="ksv-result__sub"><span x-text="siswaNama"></span> · <span x-text="result.jam"></span></p>
					<span class="ksv-badge" :class="statusBadgeClass" x-text="statusBadgeLabel"></span>
				</div>
			</template>
			<template x-if="result && ! hasilBerhasil">
				<p class="ksv-result__sub" x-text="result.message"></p>
			</template>

			<button type="button" class="ksv-submit" @click="reset()">
				<span class="ksv-submit__lbl">
					<span x-html="$icon( 'refresh-cw', 18 )" aria-hidden="true"></span>
					<?php esc_html_e( 'Absensi Berikutnya', 'absensi-sekolah' ); ?>
				</span>
			</button>
		</div>

	</div>
</div>
