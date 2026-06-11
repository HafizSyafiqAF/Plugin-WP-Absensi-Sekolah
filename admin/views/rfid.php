<?php
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap rf-wrap" x-data="adminRfid" x-cloak id="absensi-rfid-app">

  <div class="rf-bg" aria-hidden="true">
    <div class="rf-blob rf-blob--1"></div>
    <div class="rf-blob rf-blob--2"></div>
    <div class="rf-blob rf-blob--3"></div>
  </div>

  <hr class="wp-header-end" style="margin:0;">

  <!-- ══ HERO CARD ══ -->
  <div class="rf-hero-card">
    <div class="rf-hero-card__dot-grid" aria-hidden="true"></div>
    <div class="rf-hero-card__body">
      <div class="rf-hero-card__left">
        <div class="rf-eyebrow">
          <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
          <?php esc_html_e( 'Absensi RFID', 'absensi-sekolah' ); ?>
        </div>
        <h1 class="rf-hero-card__title">
          <?php esc_html_e( 'Scanner', 'absensi-sekolah' ); ?>
          <span class="rf-gradient-text"><?php esc_html_e( 'RFID', 'absensi-sekolah' ); ?></span>
        </h1>
        <p class="rf-hero-card__sub"><?php esc_html_e( 'Pastikan scanner RFID USB sudah terpasang. Sistem otomatis menentukan sesi masuk atau pulang.', 'absensi-sekolah' ); ?></p>
        <div class="rf-hero-card__chips">
          <span class="rf-chip rf-chip--glass">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 9v7.5"/></svg>
            <?php echo esc_html( wp_date( 'l, j F Y' ) ); ?>
          </span>
          <span class="rf-chip rf-chip--indigo">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
            <?php esc_html_e( 'Otomatis Masuk / Pulang', 'absensi-sekolah' ); ?>
          </span>
          <span class="rf-chip rf-chip--green" x-show="absenLog.length > 0">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span x-text="absenLog.length + ' scan hari ini'"></span>
          </span>
        </div>
      </div>
      <div class="rf-hero-card__right">
        <div class="rf-mode-tabs">
          <button type="button" @click="mode='absen'" class="rf-mode-tab" :class="mode==='absen' ? 'rf-mode-tab--active' : ''">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
            <?php esc_html_e( 'Absen', 'absensi-sekolah' ); ?>
          </button>
          <button type="button" @click="mode='enroll'" class="rf-mode-tab" :class="mode==='enroll' ? 'rf-mode-tab--active' : ''">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
            <?php esc_html_e( 'Daftar Kartu', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ MODE ABSEN ══ -->
  <div x-show="mode === 'absen'" class="rf-absen-grid">

    <!-- Kiri: Scanner Panel -->
    <div class="rf-panel rf-scanner-panel">
      <div class="rf-panel__head">
        <div class="rf-panel__head-icon">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/></svg>
        </div>
        <div class="rf-panel__head-text">
          <p class="rf-panel__title"><?php esc_html_e( 'Area Pindai', 'absensi-sekolah' ); ?></p>
          <p class="rf-panel__sub"><?php esc_html_e( 'Tap 1 = Masuk · Tap 2 = Pulang (otomatis)', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <div class="rf-scan-zone" @click="refocus()">
        <div class="rf-scan-ring rf-scan-ring--1"></div>
        <div class="rf-scan-ring rf-scan-ring--2"></div>
        <div class="rf-pulse"></div>
        <div class="rf-core">
          <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
        </div>
        <p class="rf-scan-label"><?php esc_html_e( 'Tempelkan kartu ke scanner', 'absensi-sekolah' ); ?></p>
        <p class="rf-scan-sub"><?php esc_html_e( 'Klik area ini jika scanner tidak merespons', 'absensi-sekolah' ); ?></p>
      </div>

      <div class="rf-status-strip"
           :class="{ 'rf-status--ok': absenStatus.type==='ok', 'rf-status--err': absenStatus.type==='err', 'rf-status--loading': absenStatus.type==='loading', 'rf-status--idle': absenStatus.type==='idle' }"
           aria-live="polite">
        <span class="rf-status-icon">
          <svg x-show="absenStatus.type==='idle'" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <svg x-show="absenStatus.type==='loading'" class="rf-spin" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <svg x-show="absenStatus.type==='ok'" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          <svg x-show="absenStatus.type==='err'" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </span>
        <span class="rf-status-msg" x-text="absenStatus.msg"></span>
      </div>

      <div class="rf-scanner-field">
        <label class="rf-scanner-label">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/></svg>
          <?php esc_html_e( 'Input scanner', 'absensi-sekolah' ); ?>
          <span class="rf-scanner-label__hint"><?php esc_html_e( 'harus selalu aktif', 'absensi-sekolah' ); ?></span>
        </label>
        <div class="rf-scanner-input-wrap">
          <input type="text" x-ref="scanner" autocomplete="off" spellcheck="false"
                 @keydown="onScanKey($event)"
                 class="rf-scanner-input"
                 placeholder="<?php esc_attr_e( 'Menunggu scan kartu…', 'absensi-sekolah' ); ?>">
          <button type="button" @click="refocus()" class="rf-scanner-action">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <?php esc_html_e( 'Refocus', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>
    </div>

    <!-- Kanan: Log Panel -->
    <div class="rf-panel rf-log-panel">
      <div class="rf-log-header">
        <div class="rf-log-header__left">
          <div class="rf-log-header-icon">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          </div>
          <div>
            <p class="rf-log-title"><?php esc_html_e( 'Log Hari Ini', 'absensi-sekolah' ); ?></p>
            <p class="rf-log-date"><?php echo esc_html( wp_date( 'l, j F Y' ) ); ?></p>
          </div>
        </div>
        <div class="rf-log-stats">
          <div class="rf-log-stat" x-show="absenLog.filter(i=>i.action==='masuk').length > 0">
            <span class="rf-log-stat__num rf-log-stat__num--blue" x-text="absenLog.filter(i=>i.action==='masuk').length"></span>
            <span class="rf-log-stat__lbl"><?php esc_html_e( 'masuk', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="rf-log-stat-sep" x-show="absenLog.filter(i=>i.action==='masuk').length > 0 && absenLog.filter(i=>i.action!=='masuk').length > 0"></div>
          <div class="rf-log-stat" x-show="absenLog.filter(i=>i.action!=='masuk').length > 0">
            <span class="rf-log-stat__num rf-log-stat__num--cyan" x-text="absenLog.filter(i=>i.action!=='masuk').length"></span>
            <span class="rf-log-stat__lbl"><?php esc_html_e( 'pulang', 'absensi-sekolah' ); ?></span>
          </div>
        </div>
      </div>

      <div class="rf-log-body" aria-live="polite">
        <div x-show="absenLog.length === 0" class="rf-log-empty">
          <div class="rf-log-empty-ico">
            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.3"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          </div>
          <p class="rf-log-empty__title"><?php esc_html_e( 'Belum ada scan hari ini', 'absensi-sekolah' ); ?></p>
          <p class="rf-log-empty__sub"><?php esc_html_e( 'Hasil scan akan muncul di sini secara real-time', 'absensi-sekolah' ); ?></p>
        </div>
        <template x-for="(item, i) in absenLog" :key="i">
          <div class="rf-log-item" :class="item.action==='masuk' ? 'rf-log-item--masuk' : 'rf-log-item--pulang'">
            <div class="rf-log-avatar" :class="item.action==='masuk' ? 'rf-avatar--masuk' : 'rf-avatar--pulang'">
              <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" x-show="item.action==='masuk'"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
              <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" x-show="item.action!=='masuk'"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15"/></svg>
            </div>
            <div class="rf-log-info">
              <p class="rf-log-name" x-text="item.nama"></p>
              <p class="rf-log-meta"><span x-text="item.jam"></span><span class="rf-log-dot">·</span><span class="rf-log-uid" x-text="item.uid"></span></p>
            </div>
            <span class="rf-badge" :class="item.status==='telat' ? 'rf-badge--warn' : (item.status==='alpha' ? 'rf-badge--err' : 'rf-badge--ok')" x-text="item.status"></span>
          </div>
        </template>
      </div>
    </div>

  </div><!-- /mode absen -->

  <!-- ══ MODE ENROLL ══ -->
  <div x-show="mode === 'enroll'" class="rf-enroll-wrap">

    <div class="rf-panel rf-enroll-panel">

      <div class="rf-enroll-header">
        <div class="rf-enroll-header__icon">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
        </div>
        <div class="rf-enroll-header__text">
          <h2 class="rf-enroll-header__title"><?php esc_html_e( 'Daftarkan Kartu RFID', 'absensi-sekolah' ); ?></h2>
          <p class="rf-enroll-header__sub"><?php esc_html_e( 'Cari dan pilih siswa, lalu tempelkan kartu ke scanner', 'absensi-sekolah' ); ?></p>
        </div>
        <div class="rf-enroll-progress">
          <div class="rf-progress-step" :class="!enrollTarget ? 'is-active' : 'is-done'">
            <div class="rf-progress-step__num">
              <span x-show="!enrollTarget">1</span>
              <svg x-show="enrollTarget" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            </div>
            <span><?php esc_html_e( 'Pilih Siswa', 'absensi-sekolah' ); ?></span>
          </div>
          <div class="rf-progress-line" :class="enrollTarget ? 'is-done' : ''"></div>
          <div class="rf-progress-step" :class="enrollTarget ? 'is-active' : ''">
            <div class="rf-progress-step__num">2</div>
            <span><?php esc_html_e( 'Tap Kartu', 'absensi-sekolah' ); ?></span>
          </div>
        </div>
      </div>

      <!-- Step 1: Cari Siswa -->
      <div x-show="!enrollTarget" class="rf-enroll-step">
        <div class="rf-search-wrap">
          <svg class="rf-search-icon" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" x-model="enrollSearch" @input.debounce.350ms="searchEnroll()"
                 class="rf-search-input"
                 placeholder="<?php esc_attr_e( 'Ketik nama atau NIS siswa…', 'absensi-sekolah' ); ?>">
        </div>

        <div x-show="enrollSearching" class="rf-state-row">
          <svg class="rf-spin" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <span><?php esc_html_e( 'Mencari siswa…', 'absensi-sekolah' ); ?></span>
        </div>

        <div x-show="enrollSearch.length < 2 && !enrollSearching" class="rf-search-empty">
          <div class="rf-search-empty__icon">
            <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
          </div>
          <p class="rf-search-empty__text"><?php esc_html_e( 'Ketik nama atau NIS untuk mencari siswa yang belum punya kartu', 'absensi-sekolah' ); ?></p>
        </div>

        <p x-show="!enrollSearching && enrollSearch.length >= 2 && enrollResults.length === 0" class="rf-no-result">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/></svg>
          <?php esc_html_e( 'Siswa tidak ditemukan atau semua sudah punya kartu.', 'absensi-sekolah' ); ?>
        </p>

        <div x-show="enrollResults.length > 0" class="rf-results-list">
          <template x-for="s in enrollResults" :key="s.id">
            <div class="rf-result-item">
              <div class="rf-result-avatar" :style="`background:hsl(${(s.id*61)%360},50%,88%);color:hsl(${(s.id*61)%360},45%,35%)`">
                <span x-text="s.nama?.charAt(0)?.toUpperCase()"></span>
              </div>
              <div class="rf-result-info">
                <p class="rf-result-name" x-text="s.nama"></p>
                <p class="rf-result-meta">
                  <span x-text="s.nis"></span>
                  <template x-if="s.nama_kelas"><span class="rf-result-sep">·</span><span x-text="s.nama_kelas"></span></template>
                </p>
              </div>
              <button type="button" @click="selectTarget(s)" class="rf-btn rf-btn--primary rf-btn--sm">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <?php esc_html_e( 'Pilih', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- Step 2: Tap Kartu -->
      <div x-show="enrollTarget" x-cloak class="rf-enroll-step">
        <div class="rf-enroll-step2-grid">

          <!-- Kiri: Info siswa + tap area -->
          <div class="rf-enroll-left">
            <div class="rf-target-card">
              <div class="rf-target-avatar">
                <span x-text="enrollTarget?.nama?.charAt(0)?.toUpperCase()"></span>
              </div>
              <div class="rf-target-info">
                <p class="rf-target-label"><?php esc_html_e( 'Siswa dipilih', 'absensi-sekolah' ); ?></p>
                <p class="rf-target-name" x-text="enrollTarget?.nama"></p>
                <p class="rf-target-meta" x-text="(enrollTarget?.nis ?? '') + (enrollTarget?.nama_kelas ? ' · ' + enrollTarget?.nama_kelas : '')"></p>
              </div>
              <button type="button" @click="clearTarget()" class="rf-btn rf-btn--ghost rf-btn--sm">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                <?php esc_html_e( 'Ganti', 'absensi-sekolah' ); ?>
              </button>
            </div>

            <div class="rf-tap-zone">
              <div class="rf-tap-rings">
                <div class="rf-tap-ring rf-tap-ring--1"></div>
                <div class="rf-tap-ring rf-tap-ring--2"></div>
              </div>
              <div class="rf-pulse"></div>
              <div class="rf-core rf-core--lg">
                <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
              </div>
              <p class="rf-tap-title" x-text="'<?php echo esc_js( __('Tempelkan kartu untuk', 'absensi-sekolah') ); ?> ' + (enrollTarget?.nama ?? '') + '…'"></p>
              <p class="rf-tap-hint"><?php esc_html_e( 'Scanner akan otomatis mendaftarkan UID', 'absensi-sekolah' ); ?></p>
            </div>
          </div>

          <!-- Kanan: Input scanner + UID manual -->
          <div class="rf-enroll-right">
            <div class="rf-enroll-right__section">
              <p class="rf-enroll-right__label">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/></svg>
                <?php esc_html_e( 'Input scanner', 'absensi-sekolah' ); ?>
                <span class="rf-lbl-hint"><?php esc_html_e( 'harus aktif', 'absensi-sekolah' ); ?></span>
              </p>
              <div class="rf-scanner-input-wrap">
                <input type="text" x-ref="scanner" autocomplete="off" spellcheck="false"
                       @keydown="onScanKey($event)"
                       class="rf-scanner-input"
                       placeholder="<?php esc_attr_e( 'Menunggu scan kartu…', 'absensi-sekolah' ); ?>">
                <button type="button" @click="refocus()" class="rf-scanner-action">
                  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                  <?php esc_html_e( 'Refocus', 'absensi-sekolah' ); ?>
                </button>
              </div>
            </div>

            <div class="rf-enroll-divider">
              <span><?php esc_html_e( 'atau masukkan UID secara manual', 'absensi-sekolah' ); ?></span>
            </div>

            <div class="rf-enroll-right__section">
              <p class="rf-enroll-right__label">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/></svg>
                <?php esc_html_e( 'UID manual', 'absensi-sekolah' ); ?>
              </p>
              <input type="text" x-model="enrollUid"
                     placeholder="<?php esc_attr_e( 'Mis. A1B2C3D4…', 'absensi-sekolah' ); ?>"
                     class="rf-uid-input"
                     @focus="pauseRefocus()"
                     @blur="resumeRefocus()">
              <button type="button" @click="submitEnroll()"
                      :disabled="enrollLoading || !enrollUid.trim()"
                      class="rf-btn rf-btn--primary" style="width:100%;margin-top:10px;">
                <span x-show="enrollLoading" class="rf-spin-sm"></span>
                <svg x-show="!enrollLoading" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span x-text="enrollLoading ? '<?php echo esc_js(__('Mendaftar…','absensi-sekolah')); ?>' : '<?php echo esc_js(__('Daftarkan Kartu','absensi-sekolah')); ?>'"></span>
              </button>
            </div>

            <div x-show="enrollStatus" x-cloak class="rf-enroll-status"
                 :class="enrollStatus?.ok ? 'rf-enroll-status--ok' : 'rf-enroll-status--err'"
                 aria-live="polite">
              <span x-show="enrollStatus?.ok"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
              <span x-show="!enrollStatus?.ok"><svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
              <span x-text="enrollStatus?.message"></span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div><!-- /mode enroll -->

</div><!-- /rf-wrap -->

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap');

body.wp-admin{background:#EAF0F6!important;}
#wpcontent,#wpbody-content,#wpbody{background:linear-gradient(135deg,#F5F7FB 0%,#E2E8F0 100%) fixed!important;}

.rf-wrap *,.rf-wrap *::before,.rf-wrap *::after{box-sizing:border-box;}
[x-cloak]{display:none!important;}
.rf-wrap{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,sans-serif!important;min-height:100vh;padding-bottom:56px;position:relative;z-index:0;}

/* ── Blobs ── */
.rf-bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden;}
.rf-blob{position:absolute;border-radius:50%;filter:blur(140px);opacity:1;}
.rf-blob--1{width:750px;height:750px;top:-180px;left:-120px;background:radial-gradient(circle,rgba(129,140,248,.55) 0%,rgba(99,102,241,.25) 65%,transparent 100%);}
.rf-blob--2{width:700px;height:700px;bottom:-150px;right:-80px;background:radial-gradient(circle,rgba(244,114,182,.50) 0%,rgba(219,39,119,.22) 65%,transparent 100%);}
.rf-blob--3{width:600px;height:600px;top:25%;right:10%;background:radial-gradient(circle,rgba(103,232,249,.52) 0%,rgba(6,182,212,.22) 65%,transparent 100%);}

/* ── Glass base ── */
.rf-glass{background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border:1px solid rgba(255,255,255,.75);box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);}

/* ── Hero Card ── */
.rf-hero-card{position:relative;z-index:1;background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border:1px solid rgba(255,255,255,.75);border-radius:24px;margin:14px 0 20px;overflow:hidden;box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);}
.rf-hero-card__dot-grid{position:absolute;inset:0;background-image:radial-gradient(circle,rgba(79,70,229,.012) 1px,transparent 1px);background-size:22px 22px;pointer-events:none;}
.rf-hero-card__body{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:28px 32px;position:relative;z-index:1;flex-wrap:wrap;}
.rf-hero-card__left{flex:1;min-width:240px;}
.rf-hero-card__right{display:flex;flex-direction:column;align-items:flex-end;gap:12px;flex-shrink:0;}
.rf-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:10.5px;font-weight:700;color:#2563EB;background:#DBEAFE;padding:5px 11px;border-radius:8px;letter-spacing:.02em;text-transform:uppercase;margin:0 0 12px;border:1px solid rgba(37,99,235,.1);}
.rf-hero-card__title{font-size:clamp(22px,2.6vw,30px);font-weight:800;color:#1E293B;margin:0 0 8px;letter-spacing:-.5px;line-height:1.15;}
.rf-gradient-text{background:linear-gradient(135deg,#2563EB,#7C3AED);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
.rf-hero-card__sub{font-size:13.5px;color:#64748B;margin:0 0 14px;line-height:1.55;max-width:480px;}
.rf-hero-card__chips{display:flex;flex-wrap:wrap;gap:7px;}

.rf-chip{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:11.5px;font-weight:600;}
.rf-chip--glass{background:rgba(255,255,255,.6);color:#334155;border:1px solid rgba(255,255,255,.8);}
.rf-chip--indigo{background:rgba(238,242,255,.85);color:#4F46E5;border:1px solid rgba(165,180,252,.35);}
.rf-chip--green{background:rgba(240,253,244,.85);color:#16A34A;border:1px solid rgba(134,239,172,.35);}

/* ── Mode tabs ── */
.rf-mode-tabs{display:flex;background:rgba(255,255,255,.48);backdrop-filter:blur(16px) saturate(140%);-webkit-backdrop-filter:blur(16px) saturate(140%);border:1.5px solid rgba(255,255,255,.82);border-radius:14px;padding:4px;gap:4px;box-shadow:4px 4px 12px rgba(163,177,198,.18),-2px -2px 8px rgba(255,255,255,.72);}
.rf-mode-tab{display:flex;align-items:center;gap:7px;padding:9px 20px;border-radius:10px;border:none;font-size:13px;font-weight:600;color:#64748B;background:transparent;cursor:pointer;font-family:inherit;transition:all .18s;min-height:40px;letter-spacing:.01em;}
.rf-mode-tab:hover:not(.rf-mode-tab--active){color:#334155;background:rgba(255,255,255,.55);}
.rf-mode-tab--active{background:linear-gradient(145deg,#2563EB,#1D4ED8);color:white;box-shadow:3px 3px 10px rgba(37,99,235,.3),inset 0 1px 0 rgba(255,255,255,.2);}

/* ── Panel base ── */
.rf-panel{position:relative;z-index:1;background:rgba(255,255,255,.55);backdrop-filter:blur(32px) saturate(180%);-webkit-backdrop-filter:blur(32px) saturate(180%);border-radius:24px;border:1px solid rgba(255,255,255,.75);box-shadow:6px 6px 20px rgba(163,177,198,.25),-6px -6px 20px rgba(255,255,255,.8),inset 0 1px 1px rgba(255,255,255,.7);}

/* ── Absen grid ── */
.rf-absen-grid{position:relative;z-index:1;display:grid;grid-template-columns:420px 1fr;gap:18px;align-items:start;}
@media(max-width:1000px){.rf-absen-grid{grid-template-columns:1fr;}}

/* ── Scanner Panel ── */
.rf-scanner-panel{display:flex;flex-direction:column;overflow:hidden;}
.rf-panel__head{display:flex;align-items:center;gap:11px;padding:16px 20px;border-bottom:1px solid rgba(0,0,0,.05);background:rgba(255,255,255,.18);}
.rf-panel__head-icon{width:36px;height:36px;border-radius:11px;background:#DBEAFE;display:flex;align-items:center;justify-content:center;color:#2563EB;flex-shrink:0;}
.rf-panel__head-text{flex:1;}
.rf-panel__title{font-size:13.5px;font-weight:700;color:#1E293B;margin:0 0 2px;}
.rf-panel__sub{font-size:11.5px;color:#94A3B8;margin:0;}

/* ── Scan zone ── */
.rf-scan-zone{position:relative;margin:18px 20px 0;height:200px;background:linear-gradient(145deg,rgba(238,242,255,.50),rgba(224,231,255,.35));border:2px dashed rgba(165,180,252,.45);border-radius:18px;display:flex;flex-direction:column;align-items:center;justify-content:center;cursor:pointer;overflow:hidden;transition:all .22s;backdrop-filter:blur(6px);}
.rf-scan-zone:hover{border-color:rgba(99,102,241,.55);background:linear-gradient(145deg,rgba(238,242,255,.75),rgba(224,231,255,.65));}
.rf-scan-ring{position:absolute;border-radius:50%;border:1px solid rgba(99,102,241,.12);animation:rf-ring-expand 2.8s ease-out infinite;}
.rf-scan-ring--1{width:110px;height:110px;}
.rf-scan-ring--2{width:150px;height:150px;animation-delay:.5s;}
.rf-pulse{position:absolute;width:86px;height:86px;background:rgba(99,102,241,.18);border-radius:50%;opacity:0;animation:rf-pulse 2.2s cubic-bezier(.4,0,.6,1) infinite;}
.rf-core{position:relative;width:68px;height:68px;background:linear-gradient(145deg,#6366F1,#4F46E5);border-radius:11px;display:flex;align-items:center;justify-content:center;color:white;z-index:2;box-shadow:4px 4px 16px rgba(79,70,229,.40),-2px -2px 8px rgba(255,255,255,.55),inset 0 1px 1px rgba(255,255,255,.28);}
.rf-scan-label{font-size:13px;font-weight:700;color:#4338CA;margin:12px 0 3px;z-index:1;position:relative;}
.rf-scan-sub{font-size:11px;color:#94A3B8;margin:0;z-index:1;position:relative;}

/* ── Status strip ── */
.rf-status-strip{display:flex;align-items:center;gap:10px;margin:14px 20px 0;padding:11px 16px;border-radius:12px;font-size:13px;font-weight:600;transition:all .25s;background:rgba(255,255,255,.55);color:#64748B;border:1px solid rgba(255,255,255,.8);box-shadow:inset 1px 1px 3px rgba(163,177,198,.1);}
.rf-status--ok{background:rgba(240,253,244,.85)!important;color:#16A34A!important;border-color:rgba(134,239,172,.3)!important;}
.rf-status--err{background:rgba(254,242,242,.85)!important;color:#DC2626!important;border-color:rgba(252,165,165,.3)!important;}
.rf-status--loading{background:rgba(238,242,255,.85)!important;color:#4F46E5!important;border-color:rgba(165,180,252,.3)!important;}
.rf-status-icon{display:flex;align-items:center;flex-shrink:0;}
.rf-status-msg{flex:1;}

/* ── Scanner field ── */
.rf-scanner-field{margin:14px 20px 20px;}
.rf-scanner-label{display:flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;color:#475569;margin-bottom:6px;text-transform:uppercase;letter-spacing:.04em;}
.rf-scanner-label__hint,.rf-lbl-hint{font-size:10.5px;font-weight:600;color:#16A34A;background:rgba(240,253,244,.8);padding:2px 7px;border-radius:5px;border:1px solid rgba(134,239,172,.3);text-transform:none;letter-spacing:0;}
.rf-scanner-input-wrap{position:relative;display:flex;align-items:center;gap:8px;}
#absensi-rfid-app .rf-scanner-input{flex:1;background:rgba(255,255,255,.55)!important;border:1px solid rgba(255,255,255,.88)!important;border-radius:12px;padding:9px 12px;font-size:13px;font-family:'JetBrains Mono',monospace;letter-spacing:.06em;min-height:42px;outline:none!important;color:#1E293B;transition:border-color .15s,box-shadow .15s,background .15s;box-shadow:inset 4px 4px 10px rgba(163,177,198,.35),inset -4px -4px 10px rgba(255,255,255,.85)!important;width:auto;}
#absensi-rfid-app .rf-scanner-input:focus{border-color:rgba(79,70,229,.35)!important;box-shadow:0 0 0 3px rgba(79,70,229,.07),inset 3px 3px 7px rgba(163,177,198,.25),inset -3px -3px 7px rgba(255,255,255,.85)!important;background:rgba(255,255,255,.88)!important;}
.rf-scanner-action{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;border-radius:10px;background:rgba(255,255,255,.65);border:1px solid rgba(255,255,255,.85);font-size:12px;font-weight:600;color:#475569;cursor:pointer;font-family:inherit;transition:all .14s;box-shadow:2px 2px 6px rgba(163,177,198,.18),-1px -1px 4px rgba(255,255,255,.7);white-space:nowrap;flex-shrink:0;min-height:42px;}
.rf-scanner-action:hover{background:rgba(255,255,255,.92);color:#1E293B;}

/* ── Log Panel ── */
.rf-log-panel{display:flex;flex-direction:column;overflow:hidden;min-height:480px;}
.rf-log-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px;border-bottom:1px solid rgba(0,0,0,.05);background:rgba(255,255,255,.18);}
.rf-log-header__left{display:flex;align-items:center;gap:12px;}
.rf-log-header-icon{width:36px;height:36px;border-radius:11px;background:#DCFCE7;display:flex;align-items:center;justify-content:center;color:#16A34A;flex-shrink:0;}
.rf-log-title{font-size:13.5px;font-weight:700;color:#1E293B;margin:0 0 2px;}
.rf-log-date{font-size:11.5px;color:#94A3B8;margin:0;}
.rf-log-stats{display:flex;align-items:center;gap:8px;}
.rf-log-stat{display:flex;flex-direction:column;align-items:center;background:rgba(255,255,255,.55);border:1px solid rgba(255,255,255,.8);border-radius:10px;padding:6px 12px;min-width:46px;}
.rf-log-stat__num{font-size:20px;font-weight:800;line-height:1;}
.rf-log-stat__num--blue{color:#2563EB;}
.rf-log-stat__num--cyan{color:#0891B2;}
.rf-log-stat__lbl{font-size:9.5px;font-weight:700;color:#94A3B8;text-transform:uppercase;letter-spacing:.05em;margin-top:1px;}
.rf-log-stat-sep{width:1px;height:30px;background:rgba(163,177,198,.2);border-radius:1px;}
.rf-log-body{flex:1;overflow-y:auto;max-height:560px;scrollbar-width:thin;scrollbar-color:rgba(0,0,0,.07) transparent;}
.rf-log-body::-webkit-scrollbar{width:4px;}
.rf-log-body::-webkit-scrollbar-thumb{background:rgba(0,0,0,.07);border-radius:99px;}
.rf-log-empty{display:flex;flex-direction:column;align-items:center;gap:10px;text-align:center;padding:60px 24px;}
.rf-log-empty-ico{width:56px;height:56px;background:rgba(255,255,255,.6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#CBD5E1;border:1.5px dashed rgba(203,213,225,.45);}
.rf-log-empty__title{font-size:14px;font-weight:700;color:#475569;margin:0;}
.rf-log-empty__sub{font-size:12px;color:#94A3B8;margin:0;line-height:1.5;}
.rf-log-item{display:flex;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid rgba(0,0,0,.05);animation:rf-slide-in .22s ease;transition:background .12s,transform .15s ease;}
.rf-log-item:last-child{border-bottom:none;}
.rf-log-item:hover{background:rgba(255,255,255,.5);transform:translateY(-0.5px);}
.rf-log-item--masuk{border-left:3px solid rgba(37,99,235,.4);}
.rf-log-item--pulang{border-left:3px solid rgba(8,145,178,.4);}
.rf-log-avatar{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:2px 2px 5px rgba(163,177,198,.14),-1px -1px 3px rgba(255,255,255,.8);}
.rf-avatar--masuk{background:rgba(238,242,255,.9);color:#2563EB;}
.rf-avatar--pulang{background:rgba(236,254,255,.9);color:#0891B2;}
.rf-log-info{flex:1;min-width:0;}
.rf-log-name{font-size:13.5px;font-weight:700;color:#1E293B;margin:0 0 3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.rf-log-meta{font-size:11px;color:#94A3B8;margin:0;display:flex;align-items:center;gap:5px;}
.rf-log-dot{color:#CBD5E1;}
.rf-log-uid{font-family:'JetBrains Mono',monospace;letter-spacing:.04em;}
.rf-badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:.02em;flex-shrink:0;}
.rf-badge--ok{background:rgba(220,252,231,.9);color:#16A34A;}
.rf-badge--warn{background:rgba(254,243,199,.9);color:#D97706;}
.rf-badge--err{background:rgba(254,226,226,.9);color:#DC2626;}

/* ── Enroll ── */
.rf-enroll-wrap{position:relative;z-index:1;}
.rf-enroll-panel{overflow:hidden;}
.rf-enroll-header{display:flex;align-items:center;gap:16px;padding:22px 28px;border-bottom:1px solid rgba(0,0,0,.05);background:rgba(255,255,255,.18);flex-wrap:wrap;}
.rf-enroll-header__icon{width:46px;height:46px;background:#DBEAFE;border-radius:14px;display:flex;align-items:center;justify-content:center;color:#2563EB;flex-shrink:0;}
.rf-enroll-header__text{flex:1;min-width:160px;}
.rf-enroll-header__title{font-size:16px;font-weight:800;color:#1E293B;margin:0 0 3px;}
.rf-enroll-header__sub{font-size:13px;color:#64748B;margin:0;}
.rf-enroll-progress{display:flex;align-items:center;gap:0;flex-shrink:0;margin-left:auto;}
.rf-progress-step{display:flex;align-items:center;gap:8px;padding:0 4px;}
.rf-progress-step__num{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.6);border:2px solid rgba(203,213,225,.5);color:#94A3B8;font-size:12px;font-weight:800;display:flex;align-items:center;justify-content:center;transition:all .22s;box-shadow:2px 2px 5px rgba(163,177,198,.14),-1px -1px 3px rgba(255,255,255,.8);}
.rf-progress-step.is-active .rf-progress-step__num{background:linear-gradient(145deg,#2563EB,#1D4ED8);color:white;border-color:transparent;box-shadow:2px 2px 8px rgba(37,99,235,.35);}
.rf-progress-step.is-done .rf-progress-step__num{background:linear-gradient(145deg,#22c55e,#16a34a);color:white;border-color:transparent;box-shadow:2px 2px 8px rgba(22,163,74,.3);}
.rf-progress-step span{font-size:12.5px;font-weight:600;color:#94A3B8;}
.rf-progress-step.is-active span,.rf-progress-step.is-done span{color:#1E293B;}
.rf-progress-line{width:60px;height:2px;background:rgba(203,213,225,.4);border-radius:999px;margin:0 2px;}
.rf-progress-line.is-done{background:linear-gradient(90deg,#22c55e,#86efac);}

.rf-enroll-step{padding:24px 28px;}
.rf-enroll-step2-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;}
@media(max-width:760px){.rf-enroll-step2-grid{grid-template-columns:1fr;}}

/* Enroll step 1 */
.rf-search-wrap{position:relative;margin-bottom:16px;}
.rf-search-icon{position:absolute;left:15px;top:50%;transform:translateY(-50%);color:#94A3B8;pointer-events:none;z-index:1;}
#absensi-rfid-app .rf-search-input{width:100%;background:rgba(255,255,255,.55)!important;border:1px solid rgba(255,255,255,.88)!important;border-radius:999px;padding:11px 18px 11px 44px;font-size:13.5px;min-height:46px;font-family:inherit;outline:none!important;color:#1E293B;transition:border-color .15s,box-shadow .15s,background .15s;box-shadow:inset 4px 4px 10px rgba(163,177,198,.32),inset -4px -4px 10px rgba(255,255,255,.82)!important;}
#absensi-rfid-app .rf-search-input:focus{background:rgba(255,255,255,.80)!important;border-color:rgba(79,70,229,.22)!important;box-shadow:inset 3px 3px 7px rgba(163,177,198,.22),inset -3px -3px 7px rgba(255,255,255,.80),0 0 0 3px rgba(79,70,229,.06)!important;}
.rf-state-row{display:flex;align-items:center;gap:8px;font-size:12.5px;color:#64748B;padding:10px 4px;}
.rf-search-empty{display:flex;align-items:center;gap:16px;background:rgba(255,255,255,.35);border:1px solid rgba(255,255,255,.7);border-radius:14px;padding:18px 20px;}
.rf-search-empty__icon{width:48px;height:48px;background:rgba(255,255,255,.6);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#CBD5E1;flex-shrink:0;}
.rf-search-empty__text{font-size:13px;color:#64748B;line-height:1.55;margin:0;}
.rf-no-result{display:flex;align-items:center;gap:7px;font-size:13px;color:#94A3B8;padding:12px 4px;margin:0;}
.rf-results-list{border:1px solid rgba(255,255,255,.72);border-radius:16px;overflow:hidden;background:rgba(255,255,255,.32);box-shadow:6px 6px 20px rgba(163,177,198,.15);}
.rf-result-item{display:flex;align-items:center;gap:13px;padding:12px 16px;border-bottom:1px solid rgba(163,177,198,.08);transition:background .12s,transform .15s ease;}
.rf-result-item:last-child{border-bottom:none;}
.rf-result-item:hover{background:rgba(255,255,255,.55);transform:translateY(-0.5px);}
.rf-result-avatar{width:38px;height:38px;border-radius:11px;font-size:14px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:2px 2px 5px rgba(163,177,198,.14);}
.rf-result-info{flex:1;min-width:0;}
.rf-result-name{font-size:13.5px;font-weight:700;color:#1E293B;margin:0 0 2px;}
.rf-result-meta{font-size:11.5px;color:#94A3B8;margin:0;display:flex;align-items:center;gap:5px;}
.rf-result-sep{color:#CBD5E1;}

/* Enroll step 2 – left */
.rf-target-card{display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,rgba(238,242,255,.8),rgba(224,231,255,.55));border:1.5px solid rgba(165,180,252,.35);border-radius:16px;padding:16px;margin-bottom:18px;backdrop-filter:blur(8px);}
.rf-target-avatar{width:50px;height:50px;border-radius:11px;background:linear-gradient(145deg,#6366F1,#4F46E5);color:white;font-size:18px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:3px 3px 10px rgba(79,70,229,.35);}
.rf-target-info{flex:1;min-width:0;}
.rf-target-label{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2563EB;margin:0 0 3px;}
.rf-target-name{font-size:15px;font-weight:800;color:#1E1B4B;margin:0 0 2px;}
.rf-target-meta{font-size:12px;color:#818CF8;margin:0;}
.rf-tap-zone{position:relative;text-align:center;padding:32px 20px 24px;border:2px dashed rgba(99,102,241,.3);border-radius:18px;background:linear-gradient(145deg,rgba(238,242,255,.45),rgba(224,231,255,.30));overflow:hidden;backdrop-filter:blur(6px);}
.rf-tap-rings{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;}
.rf-tap-ring{position:absolute;border-radius:50%;border:1px solid rgba(99,102,241,.1);}
.rf-tap-ring--1{width:120px;height:120px;animation:rf-ring-expand 3s ease-out infinite;}
.rf-tap-ring--2{width:180px;height:180px;animation:rf-ring-expand 3s ease-out infinite;animation-delay:.7s;}
.rf-core--lg{width:72px;height:72px;margin:0 auto;border-radius:11px;}
.rf-tap-title{font-size:13.5px;font-weight:700;color:#4338CA;margin:14px 0 5px;position:relative;z-index:1;}
.rf-tap-hint{font-size:12px;color:#6366F1;opacity:.7;margin:0;position:relative;z-index:1;}

/* Enroll step 2 – right */
.rf-enroll-right{display:flex;flex-direction:column;gap:0;}
.rf-enroll-right__section{background:rgba(255,255,255,.35);border:1px solid rgba(255,255,255,.72);border-radius:16px;padding:16px;margin-bottom:14px;}
.rf-enroll-right__label{display:flex;align-items:center;gap:6px;font-size:11.5px;font-weight:700;color:#475569;margin:0 0 8px;text-transform:uppercase;letter-spacing:.04em;}
.rf-enroll-divider{display:flex;align-items:center;gap:10px;margin:4px 0 14px;color:#94A3B8;font-size:12px;font-weight:600;}
.rf-enroll-divider::before,.rf-enroll-divider::after{content:'';flex:1;height:1px;background:rgba(163,177,198,.25);}
#absensi-rfid-app .rf-uid-input{width:100%;background:rgba(255,255,255,.55)!important;border:1px solid rgba(255,255,255,.88)!important;border-radius:12px;padding:10px 14px;font-size:13px;font-family:'JetBrains Mono',monospace;min-height:42px;outline:none!important;text-transform:uppercase;letter-spacing:.08em;color:#1E293B;transition:border-color .15s,box-shadow .15s,background .15s;box-shadow:inset 4px 4px 10px rgba(163,177,198,.35),inset -4px -4px 10px rgba(255,255,255,.85)!important;}
#absensi-rfid-app .rf-uid-input:focus{border-color:rgba(79,70,229,.35)!important;box-shadow:0 0 0 3px rgba(79,70,229,.07),inset 3px 3px 7px rgba(163,177,198,.25),inset -3px -3px 7px rgba(255,255,255,.85)!important;background:rgba(255,255,255,.88)!important;}

/* ── Buttons ── */
.rf-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:10px 22px;border-radius:999px;font-size:13.5px;font-weight:600;cursor:pointer;border:none;min-height:44px;font-family:inherit;transition:all .15s;white-space:nowrap;letter-spacing:.01em;}
.rf-btn--primary{background:linear-gradient(145deg,#2563EB,#1D4ED8);color:white;box-shadow:4px 4px 14px rgba(37,99,235,.32),-2px -2px 8px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.22);}
.rf-btn--primary:hover:not(:disabled){transform:translateY(-2px);box-shadow:6px 6px 20px rgba(37,99,235,.40),-2px -2px 8px rgba(255,255,255,.5),inset 0 1px 1px rgba(255,255,255,.22);}
.rf-btn--primary:active:not(:disabled){transform:translateY(0);box-shadow:inset 4px 4px 10px rgba(30,27,75,.2),-1px -1px 5px rgba(255,255,255,.5);}
.rf-btn--primary:disabled{opacity:.42;cursor:not-allowed;transform:none;box-shadow:none;}
.rf-btn--ghost{background:rgba(255,255,255,.65);backdrop-filter:blur(12px);border:1.5px solid rgba(255,255,255,.88);color:#475569;box-shadow:3px 3px 8px rgba(163,177,198,.2),-2px -2px 6px rgba(255,255,255,.8);}
.rf-btn--ghost:hover{background:rgba(255,255,255,.92);color:#1E293B;transform:translateY(-1px);}
.rf-btn--sm{padding:7px 15px;font-size:12.5px;min-height:36px;border-radius:10px;}

/* ── Enroll status ── */
.rf-enroll-status{display:flex;align-items:center;gap:10px;padding:12px 15px;border-radius:12px;font-size:13px;font-weight:600;margin-top:4px;}
.rf-enroll-status--ok{background:rgba(240,253,244,.88);color:#16A34A;border:1px solid rgba(134,239,172,.3);}
.rf-enroll-status--err{background:rgba(254,242,242,.88);color:#DC2626;border:1px solid rgba(252,165,165,.3);}

/* ── Spin / utils ── */
.rf-spin{animation:rf-spin .8s linear infinite;display:flex;}
.rf-spin-sm{width:14px;height:14px;border:2px solid rgba(255,255,255,.35);border-top-color:white;border-radius:50%;animation:rf-spin .7s linear infinite;flex-shrink:0;}

@keyframes rf-pulse{0%,100%{transform:scale(.75);opacity:.15} 50%{transform:scale(2.4);opacity:0}}
@keyframes rf-ring-expand{0%{transform:scale(.6);opacity:.5} 100%{transform:scale(1.6);opacity:0}}
@keyframes rf-slide-in{from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)}}
@keyframes rf-spin{to{transform:rotate(360deg)}}
</style>
