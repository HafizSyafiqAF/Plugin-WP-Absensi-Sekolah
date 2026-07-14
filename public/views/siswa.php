<?php
/**
 * View kiosk publik — Absensi Siswa (fullscreen, latar biru gelap).
 *
 * Di-include Shortcodes::render_siswa() via [absensi_siswa]. TANPA login
 * (keamanan absen di endpoint: nomor_induk + rate-limit). Semua style di bawah
 * `.absensi-kiosk` (scoped). Alpine + helper ($icon, api, absensiToast) dari public.js.
 *
 * ALUR (wizard 4 langkah — komponen `kioskSiswa`):
 *   1. nis        → ketik nomor induk, nama siswa dicek ke GET /absen/status
 *   2. verifikasi → status GPS + kamera selfie (ambil / ulang)
 *   3. konfirmasi → ringkasan data sebelum kirim
 *   4. hasil      → kartu berhasil/ditolak + "Absensi Berikutnya"
 * Endpoint: GET /absen/status, POST /absen/selfie. Config: AbsensiConfig.
 *
 * CATATAN DATA: endpoint publik hanya memberi `nama` (tak ada kelas/grup) —
 * karena itu ringkasan menampilkan Sesi & Lokasi, bukan "Kelas".
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="absensi-kiosk kiosk-siswa" x-data="kioskSiswa">

	<!-- Header: lencana kiosk + tanggal & jam berjalan -->
	<header class="kiosk-top">
		<div class="kiosk-badge-brand">
			<span class="kiosk-brand__ico" x-html="$icon( 'clipboard-check', 16 )" aria-hidden="true"></span>
			<span class="kiosk-brand__txt"><?php esc_html_e( 'Kiosk Absensi', 'absensi-sekolah' ); ?></span>
		</div>
		<p class="kiosk-top__meta">
			<span x-text="tanggal"></span> · <span x-text="jam"></span>
		</p>
	</header>

	<!-- ── Langkah 1: Masukkan NIS/NIP ── -->
	<section class="kiosk-panel" x-show="step === 'nis'" x-cloak>
		<span class="kiosk-panel__ico" x-html="$icon( 'user-check', 22 )" aria-hidden="true"></span>
		<h2 class="kiosk-panel__title"><?php esc_html_e( 'Masukkan NIS/NIP', 'absensi-sekolah' ); ?></h2>
		<p class="kiosk-panel__sub"><?php esc_html_e( 'NIS/NIP', 'absensi-sekolah' ); ?></p>

		<label class="u-sr" for="ks-nis"><?php esc_html_e( 'Nomor induk (NIS/NIP)', 'absensi-sekolah' ); ?></label>
		<input id="ks-nis" type="text" inputmode="numeric" autocomplete="off"
		       class="kiosk-nis" :class="lookupError ? 'kiosk-nis--error' : ''"
		       x-model.trim="nomorInduk"
		       @input.debounce.400ms="cekNis()"
		       @keydown.enter.prevent="lanjutKeVerifikasi()"
		       placeholder="2024001">

		<!-- Nama siswa (dari GET /absen/status) -->
		<div class="kiosk-found" x-show="siswaNama" x-cloak>
			<span class="kiosk-found__avatar" x-text="inisial(siswaNama)" aria-hidden="true"></span>
			<span class="kiosk-found__main">
				<span class="kiosk-found__name" x-text="siswaNama"></span>
				<span class="kiosk-found__meta" x-text="sudahAbsen
					? '<?php echo esc_js( __( 'Sudah tercatat hari ini', 'absensi-sekolah' ) ); ?>'
					: '<?php echo esc_js( __( 'Belum absen hari ini', 'absensi-sekolah' ) ); ?>'"></span>
			</span>
			<span class="kiosk-found__ok" x-html="$icon( 'check-circle-2', 18 )" aria-hidden="true"></span>
		</div>

		<!-- Nomor tak terdaftar / error -->
		<p class="kiosk-inline-err" x-show="lookupError" x-cloak role="alert" x-text="lookupError"></p>

		<button type="button" class="kiosk-btn kiosk-btn--primary" @click="lanjutKeVerifikasi()"
		        :disabled="! bisaLanjutNis || ! siswaNama">
			<span x-show="! lookupBusy"><?php esc_html_e( 'Lanjutkan', 'absensi-sekolah' ); ?> &rarr;</span>
			<span x-show="lookupBusy" x-cloak><?php esc_html_e( 'Memeriksa…', 'absensi-sekolah' ); ?></span>
		</button>
	</section>

	<!-- ── Langkah 2: Verifikasi (GPS + selfie) ── -->
	<section class="kiosk-panel" x-show="step === 'verifikasi'" x-cloak>
		<h2 class="kiosk-panel__title"><?php esc_html_e( 'Verifikasi', 'absensi-sekolah' ); ?></h2>
		<p class="kiosk-panel__sub"><?php esc_html_e( 'GPS & Foto Selfie', 'absensi-sekolah' ); ?></p>

		<!-- Status GPS -->
		<div class="kiosk-gps" :class="'kiosk-gps--' + gpsStatus" role="status" aria-live="polite">
			<span class="kiosk-gps__ico">
				<span x-show="gpsStatus === 'waiting'" class="kiosk-spin" aria-hidden="true"></span>
				<span x-show="gpsStatus === 'ok'" x-html="$icon( 'check-circle-2', 18 )" aria-hidden="true"></span>
				<span x-show="gpsStatus === 'weak'" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
				<span x-show="gpsStatus === 'error'" x-html="$icon( 'x-circle', 18 )" aria-hidden="true"></span>
			</span>
			<span class="kiosk-gps__body">
				<span class="kiosk-gps__title"><?php esc_html_e( 'GPS', 'absensi-sekolah' ); ?></span>
				<span class="kiosk-gps__desc" x-show="gpsStatus === 'waiting'"><?php esc_html_e( 'Mencari lokasi…', 'absensi-sekolah' ); ?></span>
				<span class="kiosk-gps__desc" x-show="gpsStatus === 'ok'"><?php esc_html_e( 'Lokasi terverifikasi ✓', 'absensi-sekolah' ); ?></span>
				<span class="kiosk-gps__desc" x-show="gpsStatus === 'weak'"
				      x-text="'<?php echo esc_js( __( 'Akurasi rendah', 'absensi-sekolah' ) ); ?> · ' + gpsAccuracyLabel"></span>
				<span class="kiosk-gps__desc" x-show="gpsStatus === 'error'" x-text="gpsError"></span>
			</span>
			<button type="button" class="kiosk-gps__retry" x-show="gpsStatus === 'error'" x-cloak @click="startGps()">
				<?php esc_html_e( 'Coba lagi', 'absensi-sekolah' ); ?>
			</button>
		</div>

		<!-- Area kamera -->
		<div class="kiosk-cam">
			<!-- Kamera belum aktif / izin ditolak -->
			<div class="kiosk-cam__box" x-show="cam === 'off'">
				<span class="kiosk-cam__ico" x-html="$icon( 'camera', 28 )" aria-hidden="true"></span>
				<span class="kiosk-cam__txt" x-show="! camDenied"><?php esc_html_e( 'Kamera aktif', 'absensi-sekolah' ); ?></span>
				<span class="kiosk-cam__txt kiosk-cam__txt--warn" x-show="camDenied" x-cloak role="alert">
					<?php esc_html_e( 'Izin kamera ditolak', 'absensi-sekolah' ); ?>
				</span>
			</div>

			<!-- Kamera hidup -->
			<div class="kiosk-cam__box kiosk-cam__box--live" x-show="cam === 'live'" x-cloak>
				<video x-ref="video" class="kiosk-cam__video" autoplay playsinline muted
				       x-effect="if (cam === 'live' && stream) $refs.video.srcObject = stream"></video>
			</div>

			<!-- Foto sudah diambil -->
			<div class="kiosk-cam__box kiosk-cam__box--shot" x-show="cam === 'preview'" x-cloak>
				<img :src="photoUrl" class="kiosk-cam__img" alt="<?php esc_attr_e( 'Foto selfie', 'absensi-sekolah' ); ?>">
				<span class="kiosk-cam__done">
					<span x-html="$icon( 'check-circle-2', 26 )" aria-hidden="true"></span>
					<span><?php esc_html_e( 'Foto diambil', 'absensi-sekolah' ); ?></span>
				</span>
			</div>
			<canvas x-ref="canvas" class="u-hidden"></canvas>
		</div>

		<!-- Aksi: sebelum foto = Ambil Foto; sesudah = Ulang + Lanjut -->
		<button type="button" class="kiosk-btn kiosk-btn--dark" x-show="cam !== 'preview'"
		        @click="cam === 'live' ? capturePhoto() : startCamera()" :disabled="camDenied">
			<span x-html="$icon( 'camera', 16 )" aria-hidden="true"></span>
			<?php esc_html_e( 'Ambil Foto', 'absensi-sekolah' ); ?>
		</button>

		<div class="kiosk-actions" x-show="cam === 'preview'" x-cloak>
			<button type="button" class="kiosk-btn kiosk-btn--ghost" @click="retakePhoto()">
				<span x-html="$icon( 'rotate-ccw', 15 )" aria-hidden="true"></span>
				<?php esc_html_e( 'Ulang', 'absensi-sekolah' ); ?>
			</button>
			<button type="button" class="kiosk-btn kiosk-btn--primary" @click="lanjutKeKonfirmasi()">
				<?php esc_html_e( 'Lanjut', 'absensi-sekolah' ); ?>
			</button>
		</div>
	</section>

	<!-- ── Langkah 3: Konfirmasi ── -->
	<section class="kiosk-panel" x-show="step === 'konfirmasi'" x-cloak>
		<h2 class="kiosk-panel__title"><?php esc_html_e( 'Konfirmasi', 'absensi-sekolah' ); ?></h2>
		<p class="kiosk-panel__sub"><?php esc_html_e( 'Cek data sebelum submit', 'absensi-sekolah' ); ?></p>

		<dl class="kiosk-summary">
			<div class="kiosk-summary__row">
				<dt><?php esc_html_e( 'Nama', 'absensi-sekolah' ); ?></dt>
				<dd x-text="siswaNama"></dd>
			</div>
			<div class="kiosk-summary__row">
				<dt><?php esc_html_e( 'NIS/NIP', 'absensi-sekolah' ); ?></dt>
				<dd x-text="nomorInduk"></dd>
			</div>
			<div class="kiosk-summary__row">
				<dt><?php esc_html_e( 'Waktu', 'absensi-sekolah' ); ?></dt>
				<dd x-text="jam"></dd>
			</div>
			<div class="kiosk-summary__row">
				<dt><?php esc_html_e( 'Lokasi', 'absensi-sekolah' ); ?></dt>
				<dd :class="gpsStatus === 'ok' ? 'is-ok' : 'is-warn'" x-text="lokasiLabel"></dd>
			</div>
		</dl>

		<button type="button" class="kiosk-btn kiosk-btn--primary" @click="kirimAbsensi()"
		        :disabled="! canSubmit">
			<span x-show="! submitting"><?php esc_html_e( 'Submit Absensi', 'absensi-sekolah' ); ?></span>
			<span x-show="submitting" x-cloak><?php esc_html_e( 'Mengirim…', 'absensi-sekolah' ); ?></span>
		</button>
		<button type="button" class="kiosk-back" @click="kembali()"><?php esc_html_e( 'Kembali', 'absensi-sekolah' ); ?></button>
	</section>

	<!-- ── Langkah 4: Hasil ── -->
	<section class="kiosk-panel" x-show="step === 'hasil' && result" x-cloak aria-live="polite">
		<span class="kiosk-result__ico" :class="hasilBerhasil ? 'is-ok' : 'is-err'"
		      x-html="$icon( hasilBerhasil ? 'check-circle-2' : 'x-circle', 30 )" aria-hidden="true"></span>

		<h2 class="kiosk-panel__title" :class="hasilBerhasil ? 'is-ok' : 'is-err'"
		    x-text="hasilBerhasil
		      ? '<?php echo esc_js( __( 'Berhasil!', 'absensi-sekolah' ) ); ?>'
		      : '<?php echo esc_js( __( 'Absen Ditolak', 'absensi-sekolah' ) ); ?>'"></h2>

		<!-- Berhasil: nama · jam + badge status.
		     Guard `result &&` WAJIB: <template x-if> tetap dievaluasi walau section-nya
		     x-show=false, jadi tanpa guard `result.message` meledak saat result null. -->
		<template x-if="result && hasilBerhasil">
			<div class="kiosk-result__body">
				<p class="kiosk-panel__sub">
					<span x-text="siswaNama"></span> · <span x-text="result.jam"></span>
				</p>
				<span class="kiosk-badge" :class="statusBadgeClass" x-text="statusBadgeLabel"></span>
			</div>
		</template>

		<!-- Ditolak: pesan asli dari server (mis. di luar radius) -->
		<template x-if="result && ! hasilBerhasil">
			<p class="kiosk-panel__sub" x-text="result.message"></p>
		</template>

		<button type="button" class="kiosk-btn kiosk-btn--primary" @click="reset()">
			<?php esc_html_e( 'Absensi Berikutnya', 'absensi-sekolah' ); ?>
		</button>
	</section>

</div>
