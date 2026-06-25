<?php
defined( 'ABSPATH' ) || exit;
global $wpdb;
$kelas_list = $wpdb->get_results( "SELECT id, nama_kelas FROM {$wpdb->prefix}absensi_kelas ORDER BY nama_kelas" );
$kelas_json = wp_json_encode( array_map( fn($k) => [ 'id' => $k->id, 'nama_kelas' => $k->nama_kelas ], $kelas_list ) );
?>
<div x-data="absensiGuru" :data-kelas-list="<?php echo esc_attr( $kelas_json ); ?>" x-cloak class="gab-wrap">

  <!-- Blob lokal — position:absolute agar backdrop-filter punya konten berwarna di belakang card -->
  <div class="gab-blob gab-blob--1" aria-hidden="true"></div>
  <div class="gab-blob gab-blob--2" aria-hidden="true"></div>
  <div class="gab-blob gab-blob--3" aria-hidden="true"></div>

  <!-- RFID input tersembunyi (HID keyboard emulation) -->
  <input type="text" x-ref="rfidInput" x-init="focusInput()" autocomplete="off" tabindex="-1"
         style="position:fixed;left:-9999px;opacity:0;width:1px;height:1px;" aria-hidden="true">

  <!-- ══ SINGLE GLASS CARD ══ -->
  <div class="gab-card">

    <!-- ── Header ── -->
    <div class="gab-hdr">
      <div class="gab-hdr__orb gab-hdr__orb--a" aria-hidden="true"></div>
      <div class="gab-hdr__orb gab-hdr__orb--b" aria-hidden="true"></div>

      <!-- Title row -->
      <div class="gab-hdr__row">
        <div class="gab-hdr__icon" aria-hidden="true">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
        </div>
        <div class="gab-hdr__meta">
          <h1 class="gab-hdr__title"><?php esc_html_e( 'Absensi RFID', 'absensi-sekolah' ); ?></h1>
          <p class="gab-hdr__sub"><?php esc_html_e( 'Tempelkan kartu siswa ke scanner USB', 'absensi-sekolah' ); ?></p>
        </div>
        <div :style="mode === 'absen' ? '' : 'visibility:hidden'" class="gab-hdr__counter" aria-live="polite">
          <span class="gab-hdr__counter-lbl"><?php esc_html_e( 'Hadir', 'absensi-sekolah' ); ?></span>
          <span class="gab-hdr__counter-val" x-text="hadirCount">0</span>
        </div>
      </div>

      <!-- Mode toggle row -->
      <div class="gab-ctrl-row">
        <div class="gab-seg" role="group" aria-label="<?php esc_attr_e( 'Pilih mode', 'absensi-sekolah' ); ?>">
          <button type="button" class="gab-seg__btn"
                  :class="mode === 'absen' ? 'gab-seg__btn--on' : ''"
                  @click="mode = 'absen'; saveDraft()"
                  :aria-pressed="mode === 'absen'">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
            <?php esc_html_e( 'Absen', 'absensi-sekolah' ); ?>
          </button>
          <button type="button" class="gab-seg__btn"
                  :class="mode === 'enroll' ? 'gab-seg__btn--on gab-seg__btn--enroll' : ''"
                  @click="mode = 'enroll'; saveDraft()"
                  :aria-pressed="mode === 'enroll'">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
            <?php esc_html_e( 'Daftar Kartu', 'absensi-sekolah' ); ?>
          </button>
          <button type="button" class="gab-seg__btn"
                  :class="mode === 'papan' ? 'gab-seg__btn--on gab-seg__btn--papan' : ''"
                  @click="mode = 'papan'; saveDraft()"
                  :aria-pressed="mode === 'papan'">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125"/></svg>
            <?php esc_html_e( 'Papan', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>
    </div><!-- /.gab-hdr -->

    <!-- ── Body ── -->
    <div class="gab-body">

      <!-- ══ Mode: Absen ══ -->
      <div x-show="mode === 'absen'" class="gab-absen">

        <!-- Scanner pad -->
        <div class="gab-scanner">

          <!-- Area pindai -->
          <div class="gab-scan-zone" @click="$refs.rfidInput.focus()" role="button" tabindex="0"
               @keydown.enter="$refs.rfidInput.focus()"
               :aria-label="'<?php echo esc_js( __( 'Area pindai kartu RFID — klik untuk aktifkan scanner', 'absensi-sekolah' ) ); ?>'">
            <div class="gab-scanner__ring gab-scanner__ring--1" aria-hidden="true"></div>
            <div class="gab-scanner__ring gab-scanner__ring--2" aria-hidden="true"></div>
            <div class="gab-scanner__pulse" aria-hidden="true"></div>
            <div class="gab-scanner__core" aria-hidden="true">
              <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
            </div>
            <p class="gab-scanner__title"><?php esc_html_e( 'Tempelkan kartu ke scanner', 'absensi-sekolah' ); ?></p>
            <p class="gab-scanner__hint"><?php esc_html_e( 'Klik area ini jika scanner tidak merespons', 'absensi-sekolah' ); ?></p>
          </div>

          <!-- Scanner field (Manual / Autofocus input) -->
          <div class="gab-scanner-field">
            <label class="gab-scanner-label">
              <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/></svg>
              <?php esc_html_e( 'Input Scanner', 'absensi-sekolah' ); ?>
              <span class="gab-scanner-label__hint"><?php esc_html_e( 'selalu aktif', 'absensi-sekolah' ); ?></span>
            </label>
            <div class="gab-scanner-input-wrap">
              <input type="text" x-ref="rfidInput" autocomplete="off" spellcheck="false"
                     class="gab-scanner-input"
                     placeholder="<?php esc_attr_e( 'Menunggu scan...', 'absensi-sekolah' ); ?>">
              <button type="button" @click="$refs.rfidInput.focus()" class="gab-scanner-action">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <?php esc_html_e( 'Fokus', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </div>

        </div>

        <!-- Log pindaian hari ini -->
        <div class="gab-log">
          <!-- Toast notifikasi -->
          <div aria-live="assertive" class="gab-toasts">
            <template x-for="t in toasts" :key="t.id">
              <div class="gab-toast" :class="t.ok ? 'gab-toast--ok' : 'gab-toast--err'">
                <span class="gab-toast__dot" :class="t.ok ? 'gab-toast__dot--ok' : 'gab-toast__dot--err'" aria-hidden="true"></span>
                <span x-text="t.message" style="font-weight:600;font-size:13px;"></span>
              </div>
            </template>
          </div>

          <!-- Header log -->
          <div class="gab-log__head">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <?php esc_html_e( 'Log Pindaian Hari Ini', 'absensi-sekolah' ); ?>
          </div>

          <!-- Daftar scan -->
          <div class="gab-log__list">
            <template x-for="(r,i) in todayList" :key="i">
              <div class="gab-log__row">
                <div class="gab-log__avatar" x-text="r.nama ? r.nama.charAt(0).toUpperCase() : '?'" aria-hidden="true"></div>
                <div class="gab-log__info">
                  <p class="gab-log__name" x-text="r.nama"></p>
                  <p class="gab-log__jam" x-text="r.jam"></p>
                </div>
                <div class="gab-log__badges">
                  <span class="gab-badge" :class="r.sesi === 'masuk' ? 'gab-badge--blue' : 'gab-badge--cyan'"
                        x-text="r.sesi === 'masuk' ? '<?php echo esc_js( __( 'Masuk', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Pulang', 'absensi-sekolah' ) ); ?>'"></span>
                  <span class="gab-badge"
                        :class="r.status === 'hadir' ? 'gab-badge--green' : (r.status === 'telat' ? 'gab-badge--amber' : 'gab-badge--red')"
                        x-text="r.status"></span>
                </div>
              </div>
            </template>

            <!-- Empty state -->
            <div x-show="todayList.length === 0" class="gab-log__empty">
              <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
              <p><?php esc_html_e( 'Belum ada data pindaian.', 'absensi-sekolah' ); ?></p>
            </div>
          </div>
        </div><!-- /.gab-log -->

      </div><!-- /.gab-absen -->

      <!-- ══ Mode: Enroll ══ -->
      <div x-show="mode === 'enroll'" class="gab-enroll">

        <!-- ── Kiri: Cari Siswa ── -->
        <div class="gab-ep gab-ep--search">
          <div class="gab-ep__hdr">
            <div class="gab-ep__hdr-icon" aria-hidden="true">
              <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
            </div>
            <div>
              <p class="gab-ep__title"><?php esc_html_e( 'Daftarkan Kartu', 'absensi-sekolah' ); ?></p>
              <p class="gab-ep__sub"><?php esc_html_e( 'Cari siswa yang akan didaftarkan', 'absensi-sekolah' ); ?></p>
            </div>
          </div>
          <div class="gab-ep__body">
            <div class="gab-search-wrap">
              <svg class="gab-search-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
              <input type="search" x-model="enrollSearch" @input.debounce.350ms="searchSiswa()"
                     placeholder="<?php esc_attr_e( 'Ketik nama atau NIS siswa…', 'absensi-sekolah' ); ?>"
                     class="gab-search-input"
                     aria-label="<?php esc_attr_e( 'Cari siswa untuk daftar kartu', 'absensi-sekolah' ); ?>">
            </div>

            <!-- Spinner -->
            <div x-show="enrollSearching" class="gab-enroll__state-row">
              <svg class="gab-spin" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <span><?php esc_html_e( 'Mencari siswa…', 'absensi-sekolah' ); ?></span>
            </div>

            <?php // Hindari karakter > / < di nilai atribut Alpine. ?>
            <!-- Empty state: belum ketik -->
            <div x-show="!enrollSearching && (enrollSearch.length === 0 || enrollSearch.length === 1)" class="gab-search-empty">
              <div class="gab-search-empty__icon" aria-hidden="true">
                <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
              </div>
              <p class="gab-search-empty__text"><?php esc_html_e( 'Ketik nama atau NIS untuk mencari siswa', 'absensi-sekolah' ); ?></p>
            </div>

            <!-- No result -->
            <div x-show="!enrollSearching && enrollSearch.length !== 0 && enrollSearch.length !== 1 && enrollResults.length === 0" class="gab-enroll__noresult">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
              <?php esc_html_e( 'Siswa tidak ditemukan atau semua sudah punya kartu.', 'absensi-sekolah' ); ?>
            </div>

            <!-- Results -->
            <div x-show="enrollResults.length !== 0" class="gab-enroll__results">
              <template x-for="s in enrollResults" :key="s.id">
                <div class="gab-enroll__item">
                  <div class="gab-enroll__avatar"
                       :style="`background:hsl(${(s.id*61)%360},50%,88%);color:hsl(${(s.id*61)%360},45%,35%)`"
                       x-text="s.nama ? s.nama.charAt(0).toUpperCase() : '?'" aria-hidden="true"></div>
                  <div class="gab-enroll__info">
                    <p class="gab-enroll__name" x-text="s.nama"></p>
                    <p class="gab-enroll__nis">
                      <span x-text="s.nis"></span>
                      <template x-if="s.nama_kelas"><span class="gab-enroll__sep" aria-hidden="true">·</span><span x-text="s.nama_kelas"></span></template>
                    </p>
                  </div>
                  <button type="button" @click="selectEnrollTarget(s)" class="gab-btn-pick">
                    <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                    <?php esc_html_e( 'Pilih', 'absensi-sekolah' ); ?>
                  </button>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- ── Kanan: Tap Zone / Target ── -->
        <div class="gab-ep gab-ep--tap">
          <!-- Progress stepper -->
          <div class="gab-ep__hdr gab-ep__hdr--center">
            <div class="gab-progress" aria-label="<?php esc_attr_e( 'Langkah pendaftaran kartu', 'absensi-sekolah' ); ?>">
              <div class="gab-progress__step" :class="!enrollTarget ? 'gab-progress__step--active' : 'gab-progress__step--done'">
                <div class="gab-progress__num">
                  <span x-show="!enrollTarget">1</span>
                  <svg x-show="enrollTarget" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                </div>
                <span><?php esc_html_e( 'Pilih Siswa', 'absensi-sekolah' ); ?></span>
              </div>
              <div class="gab-progress__line" :class="enrollTarget ? 'gab-progress__line--done' : ''"></div>
              <div class="gab-progress__step" :class="enrollTarget ? 'gab-progress__step--active' : ''">
                <div class="gab-progress__num">2</div>
                <span><?php esc_html_e( 'Tap Kartu', 'absensi-sekolah' ); ?></span>
              </div>
            </div>
          </div>

          <div class="gab-ep__body gab-ep__body--tap">

            <!-- Belum pilih siswa -->
            <div x-show="!enrollTarget" class="gab-ep__empty">
              <div class="gab-ep__empty-icon" aria-hidden="true">
                <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
              </div>
              <p class="gab-ep__empty-text"><?php esc_html_e( 'Pilih siswa di panel kiri untuk mulai mendaftarkan kartu RFID', 'absensi-sekolah' ); ?></p>
            </div>

            <!-- Sudah pilih siswa -->
            <div x-show="enrollTarget" class="gab-ep__tap-wrap">

              <!-- Target card -->
              <div class="gab-target-card">
                <div class="gab-target-card__avatar"
                     :style="enrollTarget ? `background:hsl(${(enrollTarget.id*61)%360},50%,88%);color:hsl(${(enrollTarget.id*61)%360},45%,35%)` : ''"
                     x-text="enrollTarget ? enrollTarget.nama.charAt(0).toUpperCase() : ''" aria-hidden="true"></div>
                <div class="gab-target-card__info">
                  <p class="gab-target-card__lbl"><?php esc_html_e( 'Siswa dipilih', 'absensi-sekolah' ); ?></p>
                  <p class="gab-target-card__name" x-text="enrollTarget ? enrollTarget.nama : ''"></p>
                  <p class="gab-target-card__meta" x-text="enrollTarget ? enrollTarget.nis : ''"></p>
                </div>
                <button type="button" @click="enrollTarget = null" class="gab-btn-ghost" style="flex-shrink:0;">
                  <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                  <?php esc_html_e( 'Ganti', 'absensi-sekolah' ); ?>
                </button>
              </div>

              <!-- Tap zone -->
              <div class="gab-tap-zone" aria-live="polite">
                <div class="gab-tap-ring gab-tap-ring--1" aria-hidden="true"></div>
                <div class="gab-tap-ring gab-tap-ring--2" aria-hidden="true"></div>
                <div class="gab-scanner__pulse" aria-hidden="true"></div>
                <div class="gab-scanner__core" aria-hidden="true">
                  <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
                </div>
                <p class="gab-tap-zone__title">
                  <?php esc_html_e( 'Tempelkan kartu untuk', 'absensi-sekolah' ); ?>
                  <strong x-text="enrollTarget ? enrollTarget.nama : ''"></strong>…
                </p>
                <p class="gab-tap-zone__hint"><?php esc_html_e( 'Scanner akan otomatis mendaftarkan UID kartu', 'absensi-sekolah' ); ?></p>
              </div>

              <!-- Enroll status -->
              <div x-show="enrollStatus" class="gab-enroll__status"
                   :class="enrollStatus ? (enrollStatus.ok ? 'gab-enroll__status--ok' : 'gab-enroll__status--err') : ''"
                   aria-live="polite">
                <svg x-show="enrollStatus && enrollStatus.ok" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <svg x-show="enrollStatus && !enrollStatus.ok" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="enrollStatus ? enrollStatus.message : ''"></span>
              </div>
            </div>

          </div>
        </div>

      </div><!-- /.gab-enroll -->

      <!-- ══ Mode: Papan Kehadiran ══ -->
      <div x-show="mode === 'papan'" x-cloak class="gab-papan">

        <!-- Toolbar -->
        <div class="gab-papan__toolbar">
          <div class="gab-papan__field-grp">
            <label class="gab-papan__lbl">
              <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
              <?php esc_html_e( 'Tanggal', 'absensi-sekolah' ); ?>
            </label>
            <input type="date" x-model="papanDate" class="gab-papan__input"
                   aria-label="<?php esc_attr_e( 'Tanggal papan kehadiran', 'absensi-sekolah' ); ?>">
          </div>
          <div class="gab-papan__field-grp gab-papan__field-grp--grow">
            <label class="gab-papan__lbl">
              <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
              <?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?>
            </label>
            <div style="position:relative;">
              <button type="button" @click="papanCsOpen=!papanCsOpen" @keydown.escape="papanCsOpen=false"
                      class="gab-papan__csel" :class="papanCsOpen ? 'gab-papan__csel--open' : ''"
                      :aria-expanded="papanCsOpen"
                      aria-label="<?php esc_attr_e( 'Pilih kelas', 'absensi-sekolah' ); ?>">
                <span x-text="papanKelasId ? papanKelasNama : '<?php echo esc_js( __( '— Pilih Kelas —', 'absensi-sekolah' ) ); ?>'"></span>
                <svg class="gab-papan__csel-chev" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
              </button>
              <div x-show="papanCsOpen" x-cloak @click.outside="papanCsOpen=false" class="gab-papan__csel-drop">
                <button type="button" @click="papanKelasId='';papanKelasNama='';papanCsOpen=false"
                        class="gab-papan__csel-opt" :class="papanKelasId==='' ? 'gab-papan__csel-opt--on':''">
                  <?php esc_html_e( '— Pilih Kelas —', 'absensi-sekolah' ); ?>
                </button>
                <?php foreach ( $kelas_list as $k ) : ?>
                <button type="button"
                        @click="papanKelasId='<?php echo esc_js( (string) $k->id ); ?>';papanKelasNama='<?php echo esc_js( $k->nama_kelas ); ?>';papanCsOpen=false"
                        class="gab-papan__csel-opt"
                        :class="papanKelasId==='<?php echo esc_js( (string) $k->id ); ?>' ? 'gab-papan__csel-opt--on':''">
                  <?php echo esc_html( $k->nama_kelas ); ?>
                </button>
                <?php endforeach; ?>
              </div>
            </div>
          </div>
          <div class="gab-papan__field-grp">
            <span class="gab-papan__lbl" aria-hidden="true" style="visibility:hidden;">_</span>
            <button type="button" @click="loadPapan()" :disabled="papanLoading || !papanKelasId"
                    class="gab-papan__btn-muat">
              <svg x-show="!papanLoading" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
              <svg x-show="papanLoading" class="gab-spin" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <?php esc_html_e( 'Terapkan', 'absensi-sekolah' ); ?>
            </button>
          </div>
        </div>

        <!-- Error -->
        <div x-show="papanError" x-cloak class="gab-papan__notice gab-papan__notice--err" aria-live="polite">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
          <span x-text="papanError"></span>
        </div>

        <!-- Summary chips -->
        <div x-show="papanRoster.length > 0" class="gab-papan__summary">
          <span class="gab-papan__chip gab-papan__chip--hadir"
                x-text="papanRoster.filter(function(s){return s.status === 'hadir' || s.status === 'telat';}).length + ' <?php echo esc_js( __( 'Hadir', 'absensi-sekolah' ) ); ?>'"></span>
          <span class="gab-papan__chip gab-papan__chip--izin"
                x-text="papanRoster.filter(function(s){return s.status === 'izin' || s.status === 'sakit';}).length + ' <?php echo esc_js( __( 'Izin/Sakit', 'absensi-sekolah' ) ); ?>'"></span>
          <span class="gab-papan__chip gab-papan__chip--alpha"
                x-text="papanRoster.filter(function(s){return s.status === 'alpha' || s.status === null;}).length + ' <?php echo esc_js( __( 'Alpha/Belum', 'absensi-sekolah' ) ); ?>'"></span>
          <span class="gab-papan__chip gab-papan__chip--total"
                x-text="papanRoster.length + ' <?php echo esc_js( __( 'Total', 'absensi-sekolah' ) ); ?>'"></span>
        </div>

        <!-- Empty state -->
        <div x-show="!papanLoading && papanRoster.length === 0 && !papanError" class="gab-papan__empty">
          <svg width="30" height="30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5"/></svg>
          <p><?php esc_html_e( 'Pilih kelas dan klik Terapkan untuk melihat papan kehadiran.', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- Student list -->
        <div class="gab-papan__list" aria-live="polite" :style="papanRoster.length === 0 ? 'visibility:hidden' : ''">
          <table class="gab-papan__table">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Siswa', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Pulang', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Ubah', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="s in papanRoster" :key="s.id">
                <tr class="gab-papan__tr">

                  <!-- Siswa -->
                  <td>
                    <div class="gab-papan__cell-siswa">
                      <div class="gab-papan__cell-av"
                           :style="`background:hsl(${(s.id*61)%360},50%,88%);color:hsl(${(s.id*61)%360},45%,35%)`"
                           x-text="s.nama ? s.nama.charAt(0).toUpperCase() : '?'" aria-hidden="true"></div>
                      <div>
                        <span class="gab-papan__cell-nama" x-text="s.nama"></span>
                        <span class="gab-papan__cell-nis" x-text="s.nis"></span>
                      </div>
                    </div>
                  </td>

                  <!-- Masuk -->
                  <td>
                    <span class="gab-papan__cell-time" x-text="s.waktu_masuk ? s.waktu_masuk.substr(11,5) : '—'"></span>
                  </td>

                  <!-- Pulang -->
                  <td>
                    <span class="gab-papan__cell-time gab-papan__cell-time--muted" x-text="s.waktu_keluar ? s.waktu_keluar.substr(11,5) : '—'"></span>
                  </td>

                  <!-- Status badge -->
                  <td>
                    <span x-show="s.status === 'hadir'"  class="gab-papan__sbadge gab-papan__sbadge--hadir"><?php esc_html_e( 'Hadir',  'absensi-sekolah' ); ?></span>
                    <span x-show="s.status === 'telat'"  class="gab-papan__sbadge gab-papan__sbadge--telat"><?php esc_html_e( 'Telat',  'absensi-sekolah' ); ?></span>
                    <span x-show="s.status === 'alpha'"  class="gab-papan__sbadge gab-papan__sbadge--alpha"><?php esc_html_e( 'Alpha',  'absensi-sekolah' ); ?></span>
                    <span x-show="s.status === 'izin'"   class="gab-papan__sbadge gab-papan__sbadge--izin"><?php esc_html_e(  'Izin',   'absensi-sekolah' ); ?></span>
                    <span x-show="s.status === 'sakit'"  class="gab-papan__sbadge gab-papan__sbadge--sakit"><?php esc_html_e( 'Sakit',  'absensi-sekolah' ); ?></span>
                    <span x-show="s.status === null"     class="gab-papan__sbadge gab-papan__sbadge--belum"><?php esc_html_e( 'Belum',  'absensi-sekolah' ); ?></span>
                  </td>

                  <!-- Ubah status + Bukti -->
                  <td>
                    <div class="gab-papan__ubah-col">
                      <div class="gab-papan__row-ctrl">
                        <select class="gab-papan__status-sel" :disabled="s._stLoading"
                                @change="setStatus(s, $event.target.value)"
                                aria-label="<?php esc_attr_e( 'Ubah status kehadiran', 'absensi-sekolah' ); ?>">
                          <option value=""><?php esc_html_e( 'Ubah…', 'absensi-sekolah' ); ?></option>
                          <option value="hadir"><?php esc_html_e( 'Hadir', 'absensi-sekolah' ); ?></option>
                          <option value="telat"><?php esc_html_e( 'Telat', 'absensi-sekolah' ); ?></option>
                          <option value="izin"><?php esc_html_e( 'Izin', 'absensi-sekolah' ); ?></option>
                          <option value="sakit"><?php esc_html_e( 'Sakit', 'absensi-sekolah' ); ?></option>
                          <option value="alpha"><?php esc_html_e( 'Alpha', 'absensi-sekolah' ); ?></option>
                        </select>
                        <svg x-show="s._stLoading" class="gab-spin" width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:#64748B;" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                      </div>
                      <p x-show="s._stErr" x-text="s._stErr" class="gab-papan__row-err" aria-live="polite"></p>
                      <div x-show="s.bukti_status === 'menunggu'" class="gab-papan__bukti">
                        <a :href="s.bukti_url || '#'" target="_blank" rel="noopener"
                           class="gab-papan__bukti-link" @click.prevent="if(s.bukti_url) window.open(s.bukti_url,'_blank')">
                          <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                          <?php esc_html_e( 'Bukti', 'absensi-sekolah' ); ?>
                        </a>
                        <button type="button" class="gab-papan__bukti-btn gab-papan__bukti-btn--ok"
                                :disabled="s._bkLoading" @click="confirmBukti(s, 'setuju')"
                                aria-label="<?php esc_attr_e( 'Setujui izin/sakit', 'absensi-sekolah' ); ?>">
                          <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                          <?php esc_html_e( 'Setuju', 'absensi-sekolah' ); ?>
                        </button>
                        <button type="button" class="gab-papan__bukti-btn gab-papan__bukti-btn--no"
                                :disabled="s._bkLoading" @click="confirmBukti(s, 'tolak')"
                                aria-label="<?php esc_attr_e( 'Tolak izin/sakit', 'absensi-sekolah' ); ?>">
                          <svg width="10" height="10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                          <?php esc_html_e( 'Tolak', 'absensi-sekolah' ); ?>
                        </button>
                      </div>
                      <span x-show="s.bukti_status === 'setuju'" class="gab-badge gab-badge--green" style="font-size:9.5px;"><?php esc_html_e( 'Disetujui', 'absensi-sekolah' ); ?></span>
                      <span x-show="s.bukti_status === 'tolak'"  class="gab-badge gab-badge--red"   style="font-size:9.5px;"><?php esc_html_e( 'Ditolak',   'absensi-sekolah' ); ?></span>
                    </div>
                  </td>

                </tr>
              </template>
            </tbody>
          </table>
        </div><!-- /.gab-papan__list -->

      </div><!-- /.gab-papan -->

    </div><!-- /.gab-body -->
  </div><!-- /.gab-card -->
</div><!-- /.gab-wrap -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

[x-cloak]{display:none!important;}
html,body{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed !important;min-height:100vh;}
#page,#main,#primary,#content,.site-content,.entry-content,.wp-block-group{background:transparent !important;}

/* ── Wrap ── */
.gab-wrap *,.gab-wrap *::before,.gab-wrap *::after{box-sizing:border-box;}
.gab-wrap{
  font-family:'Plus Jakarta Sans',-apple-system,sans-serif;
  max-width:680px;
  margin:0 auto;
  padding:16px 0 40px;
  position:relative;
}

/* ── Blob lokal ── */
.gab-blob{position:absolute;border-radius:50%;filter:blur(90px);pointer-events:none;z-index:0;}
.gab-blob--1{width:420px;height:420px;top:-150px;left:-180px;background:radial-gradient(circle,rgba(129,140,248,.55) 0%,rgba(99,102,241,.25) 65%,transparent 100%);}
.gab-blob--2{width:380px;height:380px;bottom:-120px;right:-150px;background:radial-gradient(circle,rgba(244,114,182,.50) 0%,rgba(219,39,119,.22) 65%,transparent 100%);}
.gab-blob--3{width:320px;height:320px;top:40%;right:-80px;background:radial-gradient(circle,rgba(103,232,249,.50) 0%,rgba(6,182,212,.22) 65%,transparent 100%);}
.gab-card{position:relative;z-index:1;}

/* ── Card (glassmorphism) ── */
.gab-card{
  background:rgba(255,255,255,.55);
  backdrop-filter:blur(32px) saturate(180%);
  -webkit-backdrop-filter:blur(32px) saturate(180%);
  border:1px solid rgba(255,255,255,.75);
  border-radius:24px;
  overflow:hidden;
  box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);
}

/* ── Header ── */
.gab-hdr{padding:22px 20px 18px;position:relative;background:rgba(255,255,255,.22);border-bottom:1px solid rgba(0,0,0,.05);}
.gab-hdr__orb{position:absolute;border-radius:50%;pointer-events:none;}
.gab-hdr__orb--a{width:220px;height:220px;top:-80px;right:-50px;background:radial-gradient(circle,rgba(37,99,235,.10) 0%,transparent 70%);filter:blur(38px);}
.gab-hdr__orb--b{width:130px;height:130px;bottom:-55px;left:-25px;background:radial-gradient(circle,rgba(124,58,237,.09) 0%,transparent 70%);filter:blur(30px);}

.gab-hdr__row{display:flex;align-items:center;gap:11px;margin-bottom:20px;position:relative;}
.gab-hdr__icon{width:40px;height:40px;flex-shrink:0;background:#DBEAFE;color:#2563EB;border-radius:12px;display:flex;align-items:center;justify-content:center;}
.gab-hdr__meta{flex:1;min-width:0;}
.gab-hdr__title{font-size:16px;font-weight:800;color:#1E293B;margin:0 0 1px;letter-spacing:-.2px;}
.gab-hdr__sub{font-size:11px;color:#64748B;margin:0;font-weight:500;}
.gab-hdr__counter{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:1px;background:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.7);border-radius:12px;padding:8px 18px;}
.gab-hdr__counter-lbl{font-size:10px;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:.05em;}
.gab-hdr__counter-val{font-size:22px;font-weight:800;color:#2563EB;line-height:1;}

/* Baris mode toggle (di bawah title row) */
.gab-ctrl-row{margin-top:14px;display:flex;justify-content:flex-end;}

/* Segmented control */
.gab-seg{display:flex;gap:4px;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.8);box-shadow:inset 0 1px 1px rgba(255,255,255,.7);border-radius:10px;padding:3px;}
.gab-seg__btn{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:8px 10px;border-radius:8px;border:none;font-family:inherit;font-size:12px;font-weight:700;color:#64748B;background:transparent;cursor:pointer;min-height:38px;white-space:nowrap;transition:all .18s;}
.gab-seg__btn:hover:not(.gab-seg__btn--on){color:#1E293B;background:rgba(255,255,255,.6);}
.gab-seg__btn--on{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:white;box-shadow:3px 3px 8px rgba(37,99,235,.3),-1px -1px 4px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.2);}
.gab-seg__btn--pulang.gab-seg__btn--on{background:linear-gradient(145deg,#3b82f6,#1d4ed8);}
.gab-seg__btn--enroll.gab-seg__btn--on{background:linear-gradient(145deg,#3b82f6,#1d4ed8);}

/* ── Body ── */
.gab-body{padding:20px;}

/* ── Mode Absen — 2 kolom ── */
.gab-absen{display:grid;grid-template-columns:1fr 1fr;gap:14px;height:400px;}

/* Scanner pad */
.gab-scanner{display:flex;flex-direction:column;background:rgba(255,255,255,.5);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.7);border-radius:16px;overflow:hidden;}
.gab-scan-zone{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:16px 18px;background:linear-gradient(145deg,rgba(238,242,255,.50),rgba(224,231,255,.35));cursor:pointer;transition:all .22s;position:relative;overflow:hidden;backdrop-filter:blur(6px);}
.gab-scan-zone:hover{background:linear-gradient(145deg,rgba(238,242,255,.75),rgba(224,231,255,.65));}
.gab-scanner__ring{position:absolute;border-radius:50%;border:1px solid rgba(99,102,241,.12);animation:gab-ring-expand 2.8s ease-out infinite;}
.gab-scanner__ring--1{width:110px;height:110px;}
.gab-scanner__ring--2{width:150px;height:150px;animation-delay:.5s;}
.gab-scanner__pulse{position:absolute;width:86px;height:86px;border-radius:50%;background:rgba(99,102,241,.18);opacity:0;animation:gab-rfid-pulse 2.2s cubic-bezier(.4,0,.6,1) infinite;}
.gab-scanner__core{position:relative;width:64px;height:64px;border-radius:11px;background:linear-gradient(145deg,#6366F1,#4F46E5);display:flex;align-items:center;justify-content:center;color:white;z-index:2;margin-bottom:12px;box-shadow:4px 4px 16px rgba(79,70,229,.40),-2px -2px 8px rgba(255,255,255,.55),inset 0 1px 1px rgba(255,255,255,.28);}
.gab-scanner__title{font-size:13px;font-weight:700;color:#4338CA;margin:0 0 4px;z-index:1;position:relative;}
.gab-scanner__hint{font-size:11.5px;color:#94A3B8;margin:0;z-index:1;position:relative;}

/* Scanner field (Mode Absen) */
.gab-scanner-field{background:rgba(255,255,255,.55);border-top:1px solid rgba(255,255,255,.8);padding:10px 14px 12px;display:flex;flex-direction:column;gap:6px;}
.gab-scanner-label{display:flex;align-items:center;gap:5px;font-size:10.5px;font-weight:800;color:#1E293B;text-transform:uppercase;letter-spacing:.04em;}
.gab-scanner-label__hint{font-weight:500;color:#64748B;text-transform:none;letter-spacing:0;}
.gab-scanner-input-wrap{position:relative;display:flex;}
.gab-scanner-input{flex:1;min-width:0;padding:8px 10px;border:1.5px solid rgba(0,0,0,.08);border-radius:8px;font-size:12px;font-family:monospace;font-weight:700;color:#0F172A;background:rgba(255,255,255,.8);outline:none;transition:all .2s;}
.gab-scanner-input:focus{border-color:rgba(37,99,235,.4);box-shadow:0 0 0 3px rgba(37,99,235,.15);background:#fff;}
.gab-scanner-action{position:absolute;right:3px;top:3px;bottom:3px;padding:0 10px;background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:#fff;border:none;border-radius:6px;font-size:10.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:4px;transition:opacity .15s;}
.gab-scanner-action:hover{opacity:.9;}

/* Log panel */
.gab-log{display:flex;flex-direction:column;background:rgba(255,255,255,.4);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.6);border-radius:16px;overflow:hidden;}
.gab-toasts{padding:10px 14px 0;display:flex;flex-direction:column;gap:6px;}
.gab-toast{display:flex;align-items:center;gap:9px;padding:10px 12px;border-radius:8px;font-size:13px;background:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.8);animation:gab-toast-in .25s ease;}
.gab-toast--ok{border-left:3px solid #16A34A;}
.gab-toast--err{border-left:3px solid #DC2626;}
.gab-toast__dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;}
.gab-toast__dot--ok{background:#16A34A;}
.gab-toast__dot--err{background:#DC2626;}
.gab-log__head{display:flex;align-items:center;gap:7px;padding:12px 16px;border-bottom:1px solid rgba(0,0,0,.06);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#64748B;}
.gab-log__list{flex:1;overflow-y:auto;padding:4px 14px 14px;}
.gab-log__row{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(0,0,0,.05);}
.gab-log__row:last-child{border-bottom:none;}
.gab-log__avatar{width:32px;height:32px;border-radius:50%;flex-shrink:0;background:linear-gradient(145deg,#2563EB,#1D4ED8);color:white;font-size:13px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.gab-log__info{flex:1;min-width:0;}
.gab-log__name{font-size:13px;font-weight:700;color:#0F172A;margin:0 0 1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.gab-log__jam{font-size:11px;color:#94A3B8;margin:0;font-family:monospace;}
.gab-log__badges{display:flex;gap:4px;flex-shrink:0;}
.gab-log__empty{text-align:center;padding:40px 16px;color:#94A3B8;}
.gab-log__empty svg{margin:0 auto 10px;display:block;}
.gab-log__empty p{font-size:13px;margin:0;}

/* Badges */
.gab-badge{display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
.gab-badge--blue{background:#DBEAFE;color:#2563EB;}
.gab-badge--cyan{background:#CFFAFE;color:#0891B2;}
.gab-badge--green{background:#DCFCE7;color:#16A34A;}
.gab-badge--amber{background:#FEF3C7;color:#D97706;}
.gab-badge--red{background:#FEE2E2;color:#DC2626;}
.gab-badge--neutral{background:rgba(0,0,0,.07);color:#64748B;}

/* Tombol ghost */
.gab-btn-ghost{display:inline-flex;align-items:center;justify-content:center;padding:9px 14px;border-radius:10px;font-size:12.5px;font-weight:600;border:1.5px solid rgba(255,255,255,.6);background:rgba(255,255,255,.45);backdrop-filter:blur(8px);color:#475569;cursor:pointer;font-family:inherit;min-height:40px;transition:background .12s;}
.gab-btn-ghost:hover{background:rgba(255,255,255,.65);color:#1E293B;}

/* ── Mode Enroll — 2 kolom (konsisten dengan mode absen) ── */
.gab-enroll{display:grid;grid-template-columns:1fr 1fr;gap:14px;height:400px;}

/* Panel base */
.gab-ep{display:flex;flex-direction:column;background:rgba(255,255,255,.4);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.6);border-radius:16px;overflow:hidden;}

/* Panel header */
.gab-ep__hdr{display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid rgba(0,0,0,.06);flex-shrink:0;}
.gab-ep__hdr--center{justify-content:center;}
.gab-ep__hdr-icon{width:34px;height:34px;border-radius:9px;background:#DBEAFE;color:#2563EB;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.gab-ep__title{font-size:12.5px;font-weight:800;color:#0F172A;margin:0 0 1px;}
.gab-ep__sub{font-size:10.5px;color:#64748B;margin:0;}

/* Panel body */
.gab-ep__body{flex:1;overflow-y:auto;padding:12px 14px;display:flex;flex-direction:column;gap:10px;min-height:0;}
.gab-ep__body--tap{overflow:hidden;}

/* Empty state (panel kanan belum pilih siswa) */
.gab-ep__empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:10px;padding:20px;}
.gab-ep__empty-icon{width:52px;height:52px;border-radius:12px;background:rgba(219,234,254,.4);color:#93C5FD;display:flex;align-items:center;justify-content:center;margin:0 auto;}
.gab-ep__empty-text{font-size:12px;color:#94A3B8;margin:0;line-height:1.6;}

/* Tap wrap */
.gab-ep__tap-wrap{flex:1;display:flex;flex-direction:column;gap:10px;min-height:0;overflow-y:auto;}

/* Enroll status */
.gab-enroll__status{display:flex;align-items:center;gap:8px;padding:10px 13px;border-radius:8px;font-size:12.5px;font-weight:600;flex-shrink:0;}
.gab-enroll__status--ok{background:rgba(240,253,244,.9);color:#16A34A;border:1px solid rgba(187,247,208,.7);}
.gab-enroll__status--err{background:rgba(254,242,242,.9);color:#DC2626;border:1px solid rgba(254,202,202,.7);}

/* Search */
.gab-search-wrap{position:relative;flex-shrink:0;}
.gab-search-icon{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94A3B8;pointer-events:none;}
.gab-search-input{width:100%;padding:9px 10px 9px 34px;border:1.5px solid rgba(0,0,0,.08);border-radius:10px;font-size:13px;font-family:inherit;outline:none;background:rgba(255,255,255,.7);transition:border-color .15s,box-shadow .15s;}
.gab-search-input:focus{border-color:#2563EB;box-shadow:0 0 0 3px rgba(37,99,235,.08);background:rgba(255,255,255,.9);}

/* States */
.gab-enroll__state-row{display:flex;align-items:center;gap:8px;padding:8px 0;font-size:13px;color:#64748B;}
.gab-search-empty{text-align:center;padding:20px 10px;}
.gab-search-empty__icon{width:48px;height:48px;border-radius:50%;background:rgba(219,234,254,.4);display:flex;align-items:center;justify-content:center;margin:0 auto 10px;color:#93C5FD;}
.gab-search-empty__text{font-size:12px;color:#94A3B8;margin:0;line-height:1.5;}
.gab-enroll__noresult{display:flex;align-items:center;gap:7px;padding:9px 12px;background:rgba(255,251,235,.9);color:#D97706;border:1px solid rgba(253,230,138,.5);border-radius:8px;font-size:12px;font-weight:600;}

/* Results list */
.gab-enroll__results{border:1px solid rgba(0,0,0,.08);border-radius:12px;overflow-y:auto;background:rgba(255,255,255,.5);}
.gab-enroll__item{display:flex;align-items:center;gap:10px;width:100%;padding:10px 12px;border-bottom:1px solid rgba(0,0,0,.05);}
.gab-enroll__item:last-child{border-bottom:none;}
.gab-enroll__avatar{width:32px;height:32px;border-radius:50%;flex-shrink:0;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.gab-enroll__info{flex:1;min-width:0;}
.gab-enroll__name{font-size:13px;font-weight:700;color:#0F172A;margin:0 0 1px;}
.gab-enroll__nis{font-size:11px;color:#64748B;margin:0;font-family:monospace;display:flex;align-items:center;gap:4px;}
.gab-enroll__sep{color:#CBD5E1;}
.gab-btn-pick{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;border:none;background:linear-gradient(145deg,#2563EB,#1D4ED8);color:white;font-size:11.5px;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:3px 3px 8px rgba(37,99,235,.3);transition:all .15s;flex-shrink:0;}
.gab-btn-pick:hover{transform:translateY(-1px);box-shadow:4px 4px 12px rgba(37,99,235,.4);}

/* Progress stepper */
.gab-progress{display:flex;align-items:center;gap:6px;}
.gab-progress__step{display:flex;flex-direction:column;align-items:center;gap:4px;min-width:56px;}
.gab-progress__step span:last-child{font-size:9.5px;font-weight:700;color:#94A3B8;text-align:center;text-transform:uppercase;letter-spacing:.04em;}
.gab-progress__num{width:24px;height:24px;border-radius:50%;border:2px solid #CBD5E1;background:transparent;color:#94A3B8;font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;transition:all .25s;}
.gab-progress__step--active .gab-progress__num{border-color:#2563EB;background:#2563EB;color:white;}
.gab-progress__step--active span:last-child{color:#2563EB;}
.gab-progress__step--done .gab-progress__num{border-color:#16A34A;background:#16A34A;color:white;}
.gab-progress__step--done span:last-child{color:#16A34A;}
.gab-progress__line{width:28px;height:2px;background:#E2E8F0;border-radius:2px;flex-shrink:0;margin-bottom:16px;transition:background .25s;}
.gab-progress__line--done{background:#16A34A;}

/* Target card */
.gab-target-card{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.85);border-radius:12px;padding:11px 13px;flex-shrink:0;}
.gab-target-card__avatar{width:36px;height:36px;border-radius:50%;flex-shrink:0;font-size:14px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.gab-target-card__info{flex:1;min-width:0;}
.gab-target-card__lbl{font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#94A3B8;margin:0 0 1px;}
.gab-target-card__name{font-size:13.5px;font-weight:800;color:#0F172A;margin:0 0 1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.gab-target-card__meta{font-size:11px;color:#64748B;margin:0;font-family:monospace;}

/* Tap zone */
.gab-tap-zone{display:flex;flex-direction:column;align-items:center;text-align:center;padding:18px 16px 16px;border:2px dashed rgba(37,99,235,.18);background:rgba(239,246,255,.5);border-radius:14px;position:relative;overflow:hidden;flex:1;}
.gab-tap-ring{position:absolute;border-radius:50%;border:2px solid rgba(37,99,235,.15);}
.gab-tap-ring--1{width:100px;height:100px;animation:gab-tap-ring 2s ease-out infinite;}
.gab-tap-ring--2{width:136px;height:136px;animation:gab-tap-ring 2s ease-out infinite .5s;}
.gab-tap-zone__title{font-size:12.5px;font-weight:700;color:#1E293B;margin:10px 0 3px;position:relative;z-index:1;}
.gab-tap-zone__hint{font-size:11px;color:#64748B;margin:0;position:relative;z-index:1;}

/* Spin util */
.gab-spin{animation:gab-spin-anim .75s linear infinite;}

/* ── Mode Papan ── */
.gab-seg__btn--papan.gab-seg__btn--on{background:linear-gradient(145deg,#3b82f6,#1d4ed8);color:white;box-shadow:3px 3px 8px rgba(37,99,235,.3),-1px -1px 4px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.2);}
.gab-papan{display:flex;flex-direction:column;gap:10px;height:400px;}

/* Toolbar */
.gab-papan__toolbar{display:flex;gap:10px;flex-shrink:0;align-items:flex-end;background:rgba(255,255,255,.45);border:1px solid rgba(255,255,255,.78);border-radius:14px;padding:12px 14px;position:relative;z-index:10;}
.gab-papan__field-grp{display:flex;flex-direction:column;gap:5px;flex:0 0 auto;}
.gab-papan__field-grp--grow{flex:1;}
.gab-papan__lbl{display:flex;align-items:center;gap:5px;font-size:10.5px;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.05em;}
.gab-papan__input{width:100%;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.88);border-radius:11px;padding:8px 11px;font-size:12.5px;min-height:40px;font-family:inherit;color:#1E293B;outline:none;transition:border-color .15s,box-shadow .15s;box-shadow:inset 3px 3px 8px rgba(163,177,198,.28),inset -3px -3px 8px rgba(255,255,255,.78);appearance:none;-webkit-appearance:none;}
.gab-papan__input:focus{border-color:rgba(37,99,235,.35);box-shadow:0 0 0 3px rgba(37,99,235,.07),inset 2px 2px 6px rgba(163,177,198,.2),inset -2px -2px 6px rgba(255,255,255,.78);}
.gab-papan__btn-muat{display:inline-flex;align-items:center;gap:5px;padding:8px 18px;border-radius:999px;border:none;font-size:12.5px;font-weight:700;font-family:inherit;background:linear-gradient(145deg,#2563EB,#1D4ED8);color:white;cursor:pointer;white-space:nowrap;flex-shrink:0;min-height:40px;transition:all .15s;box-shadow:4px 4px 12px rgba(37,99,235,.28),-1px -1px 5px rgba(255,255,255,.4),inset 0 1px 1px rgba(255,255,255,.2);}
.gab-papan__btn-muat:disabled{opacity:.45;cursor:not-allowed;}
.gab-papan__btn-muat:hover:not(:disabled){transform:translateY(-1px);box-shadow:5px 5px 16px rgba(37,99,235,.35);}

/* Notice */
.gab-papan__notice{display:flex;align-items:center;gap:7px;padding:9px 12px;border-radius:9px;font-size:12px;font-weight:600;flex-shrink:0;}
.gab-papan__notice--err{background:rgba(254,242,242,.9);color:#DC2626;border:1px solid rgba(254,202,202,.6);}

/* Summary chips */
.gab-papan__summary{display:flex;gap:6px;flex-wrap:wrap;flex-shrink:0;}
.gab-papan__chip{display:inline-flex;align-items:center;padding:3px 10px;border-radius:999px;font-size:10.5px;font-weight:700;letter-spacing:.03em;}
.gab-papan__chip--hadir{background:#DCFCE7;color:#16A34A;}
.gab-papan__chip--izin{background:#CFFAFE;color:#0891B2;}
.gab-papan__chip--alpha{background:#FEE2E2;color:#DC2626;}
.gab-papan__chip--total{background:rgba(0,0,0,.06);color:#64748B;}

/* Empty */
.gab-papan__empty{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:8px;color:#94A3B8;}
.gab-papan__empty svg{opacity:.3;}
.gab-papan__empty p{font-size:12px;margin:0;}

/* List */
.gab-papan__list{flex:1;overflow-y:auto;overflow-x:auto;min-height:0;border:1px solid rgba(0,0,0,.07);border-radius:12px;background:rgba(255,255,255,.4);backdrop-filter:blur(10px);}

/* Table */
.gab-papan__table{width:100%;border-collapse:collapse;font-size:12.5px;}
.gab-papan__table thead tr{background:rgba(219,234,254,.5);border-bottom:1.5px solid rgba(147,197,253,.3);}
.gab-papan__table th{text-align:left;padding:10px 14px;color:#2563EB;font-weight:700;font-size:10px;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;}
.gab-papan__table td{padding:10px 12px;border-bottom:1px solid rgba(0,0,0,.05);vertical-align:middle;}
.gab-papan__tr:last-child td{border-bottom:none;}
.gab-papan__tr{transition:background .12s;}
.gab-papan__tr:hover td{background:rgba(255,255,255,.5);}
.gab-papan__cell-siswa{display:flex;align-items:center;gap:9px;}
.gab-papan__cell-av{width:30px;height:30px;border-radius:9px;flex-shrink:0;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;}
.gab-papan__cell-nama{display:block;font-weight:700;color:#1E293B;font-size:12.5px;white-space:nowrap;}
.gab-papan__cell-nis{display:block;font-size:10.5px;color:#94A3B8;font-family:monospace;}
.gab-papan__cell-time{font-family:monospace;font-size:12.5px;font-weight:700;color:#0F172A;letter-spacing:.02em;}
.gab-papan__cell-time--muted{color:#94A3B8;font-weight:500;}
.gab-papan__sbadge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:10.5px;font-weight:600;letter-spacing:.02em;}
.gab-papan__sbadge--hadir{background:rgba(220,252,231,.9);color:#16A34A;}
.gab-papan__sbadge--telat{background:rgba(254,243,199,.9);color:#D97706;}
.gab-papan__sbadge--alpha{background:rgba(254,226,226,.9);color:#DC2626;}
.gab-papan__sbadge--izin,.gab-papan__sbadge--sakit{background:rgba(207,250,254,.9);color:#0891B2;}
.gab-papan__sbadge--belum{background:rgba(0,0,0,.07);color:#64748B;}
.gab-papan__ubah-col{display:flex;flex-direction:column;gap:4px;align-items:flex-start;}
.gab-papan__row-ctrl{display:flex;align-items:center;gap:5px;}
.gab-papan__status-sel{padding:4px 7px;border:1.5px solid rgba(0,0,0,.1);border-radius:7px;font-size:11px;font-family:inherit;background:rgba(255,255,255,.8);color:#0F172A;outline:none;cursor:pointer;transition:border-color .15s;}
.gab-papan__status-sel:focus{border-color:#2563EB;}
.gab-papan__status-sel:disabled{opacity:.5;}
.gab-papan__row-err{font-size:10px;color:#DC2626;margin:0;}

/* Custom kelas select */
.gab-papan__csel{width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.88);border-radius:11px;padding:8px 11px;font-size:12.5px;min-height:40px;font-family:inherit;color:#1E293B;outline:none;cursor:pointer;text-align:left;transition:border-color .15s,box-shadow .15s;box-shadow:inset 3px 3px 8px rgba(163,177,198,.28),inset -3px -3px 8px rgba(255,255,255,.78);}
.gab-papan__csel:hover,.gab-papan__csel--open{background:rgba(255,255,255,.85);border-color:rgba(37,99,235,.3);box-shadow:0 0 0 3px rgba(37,99,235,.06),inset 2px 2px 6px rgba(163,177,198,.2),inset -2px -2px 6px rgba(255,255,255,.78);}
.gab-papan__csel-chev{flex-shrink:0;color:#94A3B8;transition:transform .18s;}
.gab-papan__csel--open .gab-papan__csel-chev{transform:rotate(180deg);}
.gab-papan__csel-drop{position:absolute;left:0;right:0;top:calc(100% + 5px);background:rgba(255,255,255,.92);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid rgba(255,255,255,.9);border-radius:12px;box-shadow:6px 6px 20px rgba(163,177,198,.2),-3px -3px 10px rgba(255,255,255,.7);z-index:999;overflow:hidden;max-height:200px;overflow-y:auto;}
.gab-papan__csel-opt{display:block;width:100%;padding:9px 13px;border:none;background:none;cursor:pointer;font-size:12.5px;font-family:inherit;text-align:left;color:#1E293B;transition:background .1s;font-weight:500;}
.gab-papan__csel-opt:hover{background:rgba(238,242,255,.8);}
.gab-papan__csel-opt--on{background:#DBEAFE;color:#2563EB;font-weight:700;}

/* Bukti */
.gab-papan__bukti{display:flex;align-items:center;gap:4px;flex-wrap:wrap;justify-content:flex-end;}
.gab-papan__bukti-link{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:6px;font-size:10.5px;font-weight:700;color:#0891B2;background:rgba(207,250,254,.4);border:1px solid rgba(8,145,178,.2);text-decoration:none;cursor:pointer;}
.gab-papan__bukti-link:hover{background:rgba(207,250,254,.7);}
.gab-papan__bukti-btn{display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:6px;font-size:10.5px;font-weight:700;border:none;cursor:pointer;transition:opacity .15s;}
.gab-papan__bukti-btn:disabled{opacity:.45;cursor:not-allowed;}
.gab-papan__bukti-btn--ok{background:#DCFCE7;color:#16A34A;}
.gab-papan__bukti-btn--ok:hover:not(:disabled){background:#BBF7D0;}
.gab-papan__bukti-btn--no{background:#FEE2E2;color:#DC2626;}
.gab-papan__bukti-btn--no:hover:not(:disabled){background:#FECACA;}

/* ── Responsive ── */
@media(max-width:540px){
  .gab-absen,.gab-enroll,.gab-papan{grid-template-columns:1fr;height:auto;}
  .gab-ep{min-height:240px;}
  .gab-wrap{padding:12px 0 32px;}
  .gab-papan{min-height:360px;}
  .gab-papan__toolbar{flex-wrap:wrap;}
  .gab-papan__field-grp{flex:1;min-width:120px;}
}

/* ── Animations ── */
@keyframes gab-rfid-pulse{0%,100%{transform:scale(.8);opacity:.3}50%{transform:scale(2.1);opacity:0}}
@keyframes gab-ring-expand{0%{transform:scale(.5);opacity:.5}100%{transform:scale(1.6);opacity:0}}
@keyframes gab-toast-in{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
@keyframes gab-tap-ring{0%{transform:scale(.5);opacity:.6}100%{transform:scale(1.8);opacity:0}}
@keyframes gab-spin-anim{to{transform:rotate(360deg)}}
</style>

<script>
/* ── Patch absensiGuru: tambah mode Papan Kehadiran ── */
(function () {
  'use strict';
  function todayLocal() {
    var d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
  }

  document.addEventListener('alpine:init', function () {
    var origData = Alpine.data.bind(Alpine);
    Alpine.data = function (name, factory) {
      if (name === 'absensiGuru') {
        Alpine.data = origData;
        origData(name, function () {
          var c = factory();

          /* Papan state */
          c.papanDate      = todayLocal();
          c.papanKelasId   = '';
          c.papanKelasNama = '';
          c.papanCsOpen    = false;
          c.papanLoading   = false;
          c.papanRoster    = [];
          c.papanError     = null;

          c.loadPapan = async function () {
            if (!this.papanKelasId) { this.papanError = 'Pilih kelas terlebih dahulu.'; return; }
            this.papanLoading = true; this.papanError = null; this.papanRoster = [];
            try {
              var kid = encodeURIComponent(this.papanKelasId);
              var d   = encodeURIComponent(this.papanDate);
              var results = await Promise.all([
                window.api.get('siswa?kelas_id=' + kid),
                window.api.get('laporan?dari=' + d + '&sampai=' + d + '&kelas_id=' + kid),
              ]);
              var sr = results[0]; var lr = results[1];
              var roster  = Array.isArray(sr) ? sr : ((sr && sr.data) ? sr.data : []);
              var laporan = Array.isArray(lr) ? lr : ((lr && lr.data) ? lr.data : ((lr && lr.rekap) ? lr.rekap : []));
              var map = {};
              laporan.forEach(function (r) { map[r.siswa_id] = r; });
              this.papanRoster = roster.map(function (s) {
                var r = map[s.id] || null;
                return {
                  id: s.id, nama: s.nama, nis: s.nis,
                  status:        r ? r.status        : null,
                  waktu_masuk:   r ? r.waktu_masuk   : null,
                  waktu_keluar:  r ? r.waktu_keluar  : null,
                  izin_tipe:     r ? r.izin_tipe     : null,
                  bukti_status:  r ? r.bukti_status  : null,
                  bukti_url:     r ? r.bukti_url     : null,
                  _stLoading: false, _bkLoading: false, _stErr: null,
                };
              });
            } catch (err) {
              this.papanError = err.message || 'Gagal memuat data.';
            } finally {
              this.papanLoading = false;
            }
          };

          c.setStatus = async function (s, newStatus) {
            if (!newStatus) return;
            s._stLoading = true; s._stErr = null;
            try {
              await window.api.post('absen/status', { siswa_id: s.id, tanggal: this.papanDate, status: newStatus });
              s.status = newStatus;
            } catch (err) {
              s._stErr = err.message || 'Gagal mengubah status.';
            } finally {
              s._stLoading = false;
            }
          };

          c.confirmBukti = async function (s, buktiStatus) {
            s._bkLoading = true; s._stErr = null;
            try {
              var data = await window.api.post('absen/status', {
                siswa_id: s.id, tanggal: this.papanDate,
                status: s.status, bukti_status: buktiStatus,
              });
              s.bukti_status = buktiStatus;
              if (data && data.status) s.status = data.status;
            } catch (err) {
              s._stErr = err.message || 'Gagal mengonfirmasi bukti.';
            } finally {
              s._bkLoading = false;
            }
          };

          return c;
        });
      } else {
        origData(name, factory);
      }
    };
  });
}());
</script>
