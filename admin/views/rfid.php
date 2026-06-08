<?php
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'ABSENSI_ADMIN_ASSETS' ) ) :
    define( 'ABSENSI_ADMIN_ASSETS', true ); ?>
<link rel="stylesheet" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/app.css' ); ?>">
<script type="module" src="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/dist/admin.js' ); ?>"></script>
<?php endif; ?>
<script>
if (typeof AbsensiAdmin === 'undefined') {
    window.AbsensiAdmin = {
        restUrl: <?php echo wp_json_encode( rest_url( 'absensi/v1/' ) ); ?>,
        nonce:   <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>
    };
}
</script>

<div class="wrap absensi-rfid-wrap" x-data="adminRfid" x-cloak id="absensi-rfid-app">
  <hr class="wp-header-end" style="margin:0;">

  <!-- ── Page Header ── -->
  <div class="rfid-page-header">
    <div>
      <h1 class="rfid-page-title"><?php esc_html_e( 'Absen RFID', 'absensi-sekolah' ); ?></h1>
      <p class="rfid-page-sub"><?php esc_html_e( 'Pastikan scanner RFID USB sudah terpasang sebelum memulai.', 'absensi-sekolah' ); ?></p>
    </div>

    <!-- Mode Tabs -->
    <div class="rfid-mode-tabs">
      <button type="button" @click="mode='absen'" :class="mode==='absen' ? 'rfid-tab rfid-tab--active' : 'rfid-tab'">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
        <?php esc_html_e( 'Absen', 'absensi-sekolah' ); ?>
      </button>
      <button type="button" @click="mode='enroll'" :class="mode==='enroll' ? 'rfid-tab rfid-tab--active' : 'rfid-tab'">
        <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
        <?php esc_html_e( 'Daftar Kartu', 'absensi-sekolah' ); ?>
      </button>
    </div>
  </div>

  <!-- ══════════════════ MODE ABSEN ══════════════════ -->
  <div x-show="mode === 'absen'" class="rfid-absen-grid">

    <!-- Kolom kiri: Scanner Panel -->
    <div class="rfid-card rfid-scanner-card">

      <!-- Area Pindai -->
      <div class="rfid-scan-area" :class="sesi==='pulang' ? 'rfid-scan-area--pulang' : ''" @click="refocus()">
        <div class="rfid-pulse" :class="sesi==='pulang' ? 'rfid-pulse--pulang' : ''"></div>
        <div class="rfid-core" :class="sesi==='pulang' ? 'rfid-core--pulang' : ''">
          <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
        </div>
        <p class="rfid-scan-hint" x-text="sesi==='masuk' ? '<?php echo esc_js( __( 'Tempelkan kartu untuk absen masuk', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Tempelkan kartu untuk absen pulang', 'absensi-sekolah' ) ); ?>'"></p>
      </div>

      <!-- Status Result -->
      <div class="rfid-result-box"
           :class="{ 'rfid-result--ok': absenStatus.type==='ok', 'rfid-result--err': absenStatus.type==='err', 'rfid-result--loading': absenStatus.type==='loading' }"
           aria-live="polite">
        <span x-show="absenStatus.type === 'idle'" class="rfid-result-icon">⏳</span>
        <span x-show="absenStatus.type === 'loading'" class="rfid-result-icon">⟳</span>
        <span x-show="absenStatus.type === 'ok'" class="rfid-result-icon">✓</span>
        <span x-show="absenStatus.type === 'err'" class="rfid-result-icon">✕</span>
        <span x-text="absenStatus.msg"></span>
      </div>

      <!-- Field Scanner -->
      <div class="rfid-field-wrap">
        <div style="position:relative;">
          <svg style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;pointer-events:none;" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 6.75h.75v.75h-.75v-.75zM6.75 16.5h.75v.75h-.75v-.75zM16.5 6.75h.75v.75h-.75v-.75zM13.5 13.5h.75v.75h-.75v-.75zM13.5 19.5h.75v.75h-.75v-.75zM19.5 13.5h.75v.75h-.75v-.75zM19.5 19.5h.75v.75h-.75v-.75zM16.5 16.5h.75v.75h-.75v-.75z"/></svg>
          <input type="text" x-ref="scanner" autocomplete="off" spellcheck="false"
                 @keydown="onScanKey($event)"
                 class="rfid-scanner-input"
                 placeholder="<?php esc_attr_e( 'Tempelkan kartu ke scanner…', 'absensi-sekolah' ); ?>">
        </div>
        <div class="rfid-field-footer">
          <span><?php esc_html_e( 'Field ini harus selalu aktif', 'absensi-sekolah' ); ?></span>
          <button type="button" @click="refocus()" class="rfid-refocus-btn">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            <?php esc_html_e( 'Refocus', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>

    </div>

    <!-- Kolom kanan: Log -->
    <div class="rfid-card rfid-log-card">
      <div class="rfid-log-header">
        <div>
          <h2 class="rfid-log-title"><?php esc_html_e( 'Log Hari Ini', 'absensi-sekolah' ); ?></h2>
          <p class="rfid-log-date"><?php echo esc_html( wp_date( 'l, j F Y' ) ); ?></p>
        </div>
        <div class="rfid-log-counter" x-show="absenLog.length > 0">
          <span x-text="absenLog.length"></span>
          <small><?php esc_html_e( 'scan', 'absensi-sekolah' ); ?></small>
        </div>
      </div>

      <div class="rfid-log-body" aria-live="polite">
        <!-- Empty state -->
        <div x-show="absenLog.length === 0" class="rfid-log-empty">
          <div class="rfid-log-empty-icon">
            <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
          </div>
          <p><?php esc_html_e( 'Belum ada scan hari ini', 'absensi-sekolah' ); ?></p>
          <small><?php esc_html_e( 'Hasil scan akan muncul di sini', 'absensi-sekolah' ); ?></small>
        </div>

        <!-- Log items -->
        <template x-for="(item, i) in absenLog" :key="i">
          <div class="rfid-log-item">
            <div class="rfid-log-avatar" :class="item.action==='masuk' ? 'rfid-avatar--masuk' : 'rfid-avatar--pulang'">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" x-show="item.action==='masuk'"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75"/></svg>
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" x-show="item.action!=='masuk'"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 9V5.25A2.25 2.25 0 0110.5 3h6a2.25 2.25 0 012.25 2.25v13.5A2.25 2.25 0 0116.5 21h-6a2.25 2.25 0 01-2.25-2.25V15m-3 0l-3-3m0 0l3-3m-3 3H15"/></svg>
            </div>
            <div class="rfid-log-info">
              <p class="rfid-log-name" x-text="item.nama"></p>
              <p class="rfid-log-meta" x-text="item.jam + ' · ' + item.uid"></p>
            </div>
            <div class="rfid-log-badges">
              <span :class="item.status==='telat' ? 'rfid-badge rfid-badge--warn' : 'rfid-badge rfid-badge--ok'"
                    x-text="item.status"></span>
            </div>
          </div>
        </template>
      </div>
    </div>

  </div>

  <!-- ══════════════════ MODE ENROLL ══════════════════ -->
  <div x-show="mode === 'enroll'" class="rfid-enroll-layout">

    <div class="rfid-card rfid-enroll-card">

      <!-- Judul -->
      <div class="rfid-enroll-header">
        <div class="rfid-enroll-icon">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM3 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 019.374 21c-2.331 0-4.512-.645-6.374-1.766z"/></svg>
        </div>
        <div>
          <h2 class="rfid-enroll-title"><?php esc_html_e( 'Daftarkan Kartu RFID', 'absensi-sekolah' ); ?></h2>
          <p class="rfid-enroll-sub"><?php esc_html_e( 'Cari siswa → pilih → tempelkan kartu ke scanner', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- Step indicator -->
      <div class="rfid-steps">
        <div class="rfid-step" :class="!enrollTarget ? 'rfid-step--active' : 'rfid-step--done'">
          <div class="rfid-step-num" x-text="enrollTarget ? '✓' : '1'"></div>
          <span><?php esc_html_e( 'Pilih Siswa', 'absensi-sekolah' ); ?></span>
        </div>
        <div class="rfid-step-line" :class="enrollTarget ? 'rfid-step-line--done' : ''"></div>
        <div class="rfid-step" :class="enrollTarget ? 'rfid-step--active' : ''">
          <div class="rfid-step-num">2</div>
          <span><?php esc_html_e( 'Tap Kartu', 'absensi-sekolah' ); ?></span>
        </div>
      </div>

      <div class="rfid-enroll-divider"></div>

      <!-- ── STEP 1: Cari Siswa ── -->
      <div x-show="!enrollTarget">
        <div class="rfid-search-wrap">
          <svg class="rfid-search-icon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          <input type="search" x-model="enrollSearch" @input.debounce.350ms="searchEnroll()"
                 class="rfid-search-input"
                 placeholder="<?php esc_attr_e( 'Ketik nama atau NIS siswa…', 'absensi-sekolah' ); ?>">
        </div>

        <!-- Searching indicator -->
        <div x-show="enrollSearching" class="rfid-searching">
          <svg class="rfid-spin" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
          <?php esc_html_e( 'Mencari…', 'absensi-sekolah' ); ?>
        </div>

        <!-- Tidak ditemukan -->
        <p x-show="!enrollSearching && enrollSearch.length >= 2 && enrollResults.length === 0"
           class="rfid-no-result"><?php esc_html_e( 'Siswa tidak ditemukan.', 'absensi-sekolah' ); ?></p>

        <!-- Hasil pencarian -->
        <div x-show="enrollResults.length > 0" class="rfid-results">
          <template x-for="s in enrollResults" :key="s.id">
            <div class="rfid-result-item">
              <div class="rfid-result-avatar">
                <span x-text="s.nama?.charAt(0)?.toUpperCase()"></span>
              </div>
              <div class="rfid-result-info">
                <p class="rfid-result-name" x-text="s.nama"></p>
                <p class="rfid-result-meta" x-text="s.nis + (s.nama_kelas ? ' · ' + s.nama_kelas : '')"></p>
              </div>
              <div style="display:flex;align-items:center;gap:8px;">
                <span x-show="s.rfid_uid" class="rfid-badge rfid-badge--warn"><?php esc_html_e( 'Ada kartu', 'absensi-sekolah' ); ?></span>
                <button type="button" @click="selectTarget(s)" class="rfid-btn-select">
                  <?php esc_html_e( 'Pilih', 'absensi-sekolah' ); ?>
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- Placeholder kosong -->
        <div x-show="enrollSearch.length < 2 && enrollResults.length === 0" class="rfid-search-placeholder">
          <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.2" style="color:#d1d5db;margin-bottom:10px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/></svg>
          <p><?php esc_html_e( 'Ketik minimal 2 karakter untuk mencari siswa', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- ── STEP 2: Tap Kartu ── -->
      <div x-show="enrollTarget" x-cloak>

        <!-- Siswa terpilih -->
        <div class="rfid-target-card">
          <div class="rfid-target-avatar">
            <span x-text="enrollTarget?.nama?.charAt(0)?.toUpperCase()"></span>
          </div>
          <div class="rfid-target-info">
            <p class="rfid-target-label"><?php esc_html_e( 'Siswa dipilih', 'absensi-sekolah' ); ?></p>
            <p class="rfid-target-name" x-text="enrollTarget?.nama"></p>
            <p class="rfid-target-meta" x-text="(enrollTarget?.nis ?? '') + (enrollTarget?.nama_kelas ? ' · ' + enrollTarget?.nama_kelas : '')"></p>
          </div>
          <button type="button" @click="clearTarget()" class="rfid-btn-change">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            <?php esc_html_e( 'Ganti', 'absensi-sekolah' ); ?>
          </button>
        </div>

        <!-- Tap area -->
        <div class="rfid-tap-area">
          <div class="rfid-tap-pulse"></div>
          <div class="rfid-tap-icon">
            <svg width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15.59 14.37a6 6 0 01-5.84 7.38v-4.8m5.84-2.58a14.98 14.98 0 006.16-12.12A14.98 14.98 0 009.631 8.41m5.96 5.96a14.926 14.926 0 01-5.841 2.58m-.119-8.54a6 6 0 00-7.381 5.84h4.8m2.581-5.84a14.927 14.927 0 00-2.58 5.84m2.699 2.7c-.103.021-.207.041-.311.06a15.09 15.09 0 01-2.448-2.448 14.9 14.9 0 01.06-.312m-2.24 2.39a4.493 4.493 0 00-1.757 4.306 4.493 4.493 0 004.306-1.758M16.5 9a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/></svg>
          </div>
          <p class="rfid-tap-label" x-text="'<?php echo esc_js( __( 'Tempelkan kartu untuk', 'absensi-sekolah' ) ); ?> ' + (enrollTarget?.nama ?? '') + '…'"></p>
          <p class="rfid-tap-hint"><?php esc_html_e( 'Atau masukkan UID secara manual di bawah', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- Scanner field (enroll) -->
        <div class="rfid-field-wrap" style="margin-bottom:14px;">
          <input type="text" x-ref="scanner" autocomplete="off" spellcheck="false"
                 @keydown="onScanKey($event)"
                 class="rfid-scanner-input"
                 placeholder="<?php esc_attr_e( 'Tempelkan kartu ke scanner…', 'absensi-sekolah' ); ?>">
          <div class="rfid-field-footer">
            <span><?php esc_html_e( 'Field ini harus selalu aktif', 'absensi-sekolah' ); ?></span>
            <button type="button" @click="refocus()" class="rfid-refocus-btn">
              <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <?php esc_html_e( 'Refocus', 'absensi-sekolah' ); ?>
            </button>
          </div>
        </div>

        <!-- Input UID manual -->
        <div class="rfid-manual-row">
          <input type="text" x-model="enrollUid"
                 placeholder="<?php esc_attr_e( 'UID manual, mis. A1B2C3D4', 'absensi-sekolah' ); ?>"
                 class="rfid-uid-input"
                 @focus="pauseRefocus(); $el.style.borderColor='#2563EB'"
                 @blur="resumeRefocus(); $el.style.borderColor='#d1d5db'">
          <button type="button" @click="submitEnroll()"
                  :disabled="enrollLoading || !enrollUid.trim()"
                  class="rfid-btn-submit"
                  :class="(enrollLoading || !enrollUid.trim()) ? 'rfid-btn-submit--disabled' : ''">
            <span x-text="enrollLoading ? '<?php echo esc_js( __( 'Mendaftar…', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'Daftarkan', 'absensi-sekolah' ) ); ?>'"></span>
          </button>
        </div>

        <!-- Status enroll -->
        <div x-show="enrollStatus" x-cloak class="rfid-enroll-status"
             :class="enrollStatus?.ok ? 'rfid-enroll-status--ok' : 'rfid-enroll-status--err'"
             aria-live="polite">
          <span x-text="enrollStatus?.ok ? '✓' : '✕'" style="font-size:15px;"></span>
          <span x-text="enrollStatus?.message"></span>
        </div>

      </div>
    </div>

  </div>

</div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');

/* ── Base ── */
.absensi-rfid-wrap, .absensi-rfid-wrap * { box-sizing: border-box; }
.absensi-rfid-wrap { font-family: 'Plus Jakarta Sans', sans-serif !important; background: #F5F7FB; min-height: 100vh; padding-bottom: 40px; }
[x-cloak] { display: none !important; }

/* ── Page Header ── */
.rfid-page-header { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin: 14px 0 28px; padding-bottom: 18px; border-bottom: 2px solid #e5e7eb; }
.rfid-page-title  { font-size: 20px; font-weight: 800; color: #0F172A; margin: 0 0 3px; letter-spacing: -.3px; }
.rfid-page-sub    { font-size: 13px; color: #64748B; margin: 0; }

/* ── Mode Tabs ── */
.rfid-mode-tabs   { display: flex; background: #F1F5F9; border-radius: 10px; padding: 4px; gap: 3px; }
.rfid-tab         { display: flex; align-items: center; gap: 7px; padding: 8px 18px; border-radius: 8px; border: none; font-size: 13.5px; font-weight: 600; color: #64748B; background: transparent; cursor: pointer; font-family: inherit; transition: all .15s; min-height: 40px; }
.rfid-tab--active { background: white; color: #2563EB; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.rfid-tab:hover:not(.rfid-tab--active) { color: #374151; }

/* ── Card ── */
.rfid-card        { background: white; border: 1px solid #E2E8F0; border-radius: 14px; overflow: hidden; }

/* ── Absen Grid ── */
.rfid-absen-grid  { display: grid; grid-template-columns: 380px 1fr; gap: 20px; }
@media (max-width: 900px) { .rfid-absen-grid { grid-template-columns: 1fr; } }

/* ── Scanner Card ── */
.rfid-scanner-card { padding: 24px; display: flex; flex-direction: column; gap: 16px; }

/* ── Sesi Switcher ── */
.rfid-sesi-switcher { display: flex; background: #F1F5F9; border-radius: 10px; padding: 4px; gap: 3px; }
.sesi-btn           { flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 9px 12px; border-radius: 8px; border: none; font-size: 13.5px; font-weight: 600; color: #64748B; background: transparent; cursor: pointer; font-family: inherit; transition: all .2s; min-height: 42px; }
.sesi-btn--active   { background: white; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
.sesi-btn--masuk.sesi-btn--active  { color: #2563EB; }
.sesi-btn--pulang.sesi-btn--active { color: #0891B2; }

/* ── Scan Area ── */
.rfid-scan-area       { position: relative; height: 160px; background: #F8FAFC; border: 2px dashed #CBD5E1; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; overflow: hidden; transition: all .2s; }
.rfid-scan-area:hover { border-color: #2563EB; background: #EFF6FF; }
.rfid-scan-area--pulang       { border-color: #A5F3FC; }
.rfid-scan-area--pulang:hover { border-color: #0891B2; background: #ECFEFF; }
.rfid-pulse         { position: absolute; width: 90px; height: 90px; background: #2563EB; border-radius: 50%; opacity: 0; animation: rfid-pulse 2.2s cubic-bezier(.4,0,.6,1) infinite; }
.rfid-pulse--pulang { background: #0891B2; }
.rfid-core          { position: relative; width: 64px; height: 64px; background: #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; z-index: 2; box-shadow: 0 4px 12px rgba(37,99,235,.3); }
.rfid-core--pulang  { background: #0891B2; box-shadow: 0 4px 12px rgba(8,145,178,.3); }
.rfid-scan-hint     { position: absolute; bottom: 12px; font-size: 11.5px; color: #94A3B8; font-weight: 500; margin: 0; }

/* ── Result Box ── */
.rfid-result-box         { display: flex; align-items: center; gap: 10px; padding: 13px 15px; border-radius: 10px; font-size: 13.5px; font-weight: 600; background: #F1F5F9; color: #64748B; transition: all .25s; }
.rfid-result--ok         { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
.rfid-result--err        { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
.rfid-result--loading    { background: #EFF6FF; color: #2563EB; border: 1px solid #BFDBFE; }
.rfid-result-icon        { font-size: 16px; flex-shrink: 0; }

/* ── Scanner Field ── */
.rfid-field-wrap      { background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 10px; padding: 12px 14px; }
.rfid-scanner-input   { width: 100%; border: 2px solid #2563EB; border-radius: 8px; padding: 9px 12px 9px 38px; font-size: 14px; font-family: monospace; letter-spacing: .06em; min-height: 44px; outline: none; background: #EFF6FF; color: #1E3A8A; transition: box-shadow .15s; }
.rfid-scanner-input:focus { box-shadow: 0 0 0 3px rgba(37,99,235,.15); }
.rfid-field-footer    { display: flex; align-items: center; justify-content: space-between; margin-top: 7px; }
.rfid-field-footer span { font-size: 11.5px; color: #94A3B8; }
.rfid-refocus-btn     { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 6px; background: white; border: 1px solid #CBD5E1; font-size: 12px; font-weight: 600; color: #374151; cursor: pointer; font-family: inherit; }
.rfid-refocus-btn:hover { background: #F8FAFC; }

/* ── Log Card ── */
.rfid-log-card    { display: flex; flex-direction: column; }
.rfid-log-header  { display: flex; align-items: center; justify-content: space-between; padding: 18px 22px; border-bottom: 1px solid #F1F5F9; }
.rfid-log-title   { font-size: 14px; font-weight: 700; color: #0F172A; margin: 0 0 2px; }
.rfid-log-date    { font-size: 12px; color: #94A3B8; margin: 0; }
.rfid-log-counter { display: flex; flex-direction: column; align-items: center; background: #EFF6FF; border-radius: 10px; padding: 8px 14px; min-width: 56px; text-align: center; }
.rfid-log-counter span { font-size: 22px; font-weight: 800; color: #2563EB; line-height: 1; }
.rfid-log-counter small { font-size: 10px; color: #93C5FD; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; }
.rfid-log-body    { flex: 1; overflow-y: auto; max-height: 500px; padding: 8px 0; }

.rfid-log-empty       { text-align: center; padding: 56px 24px; color: #94A3B8; }
.rfid-log-empty-icon  { width: 52px; height: 52px; background: #F1F5F9; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 12px; color: #CBD5E1; }
.rfid-log-empty p     { font-size: 14px; font-weight: 600; color: #64748B; margin: 0 0 4px; }
.rfid-log-empty small { font-size: 12px; color: #94A3B8; }

.rfid-log-item    { display: flex; align-items: center; gap: 12px; padding: 12px 22px; border-bottom: 1px solid #F8FAFC; animation: slide-in .2s ease; }
.rfid-log-item:last-child { border-bottom: none; }
.rfid-log-avatar  { width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rfid-avatar--masuk  { background: #EFF6FF; color: #2563EB; }
.rfid-avatar--pulang { background: #ECFEFF; color: #0891B2; }
.rfid-log-info    { flex: 1; min-width: 0; }
.rfid-log-name    { font-size: 13.5px; font-weight: 700; color: #0F172A; margin: 0 0 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rfid-log-meta    { font-size: 11px; color: #94A3B8; margin: 0; font-family: monospace; }
.rfid-log-badges  { flex-shrink: 0; }

/* ── Badges ── */
.rfid-badge          { display: inline-flex; align-items: center; padding: 3px 9px; border-radius: 999px; font-size: 11px; font-weight: 700; letter-spacing: .02em; }
.rfid-badge--ok      { background: #DCFCE7; color: #16A34A; }
.rfid-badge--warn    { background: #FEF3C7; color: #D97706; }
.rfid-badge--masuk   { background: #EFF6FF; color: #2563EB; }
.rfid-badge--pulang  { background: #ECFEFF; color: #0891B2; }

/* ── Enroll Layout ── */
.rfid-enroll-layout { max-width: 620px; }
.rfid-enroll-card   { padding: 28px; }
.rfid-enroll-header { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }
.rfid-enroll-icon   { width: 44px; height: 44px; background: #EFF6FF; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #2563EB; flex-shrink: 0; }
.rfid-enroll-title  { font-size: 16px; font-weight: 800; color: #0F172A; margin: 0 0 3px; }
.rfid-enroll-sub    { font-size: 13px; color: #64748B; margin: 0; }

/* ── Steps ── */
.rfid-steps         { display: flex; align-items: center; gap: 0; margin-bottom: 20px; }
.rfid-step          { display: flex; align-items: center; gap: 8px; }
.rfid-step-num      { width: 26px; height: 26px; border-radius: 50%; background: #E2E8F0; color: #94A3B8; font-size: 12px; font-weight: 800; display: flex; align-items: center; justify-content: center; transition: all .2s; }
.rfid-step--active .rfid-step-num { background: #2563EB; color: white; }
.rfid-step--done   .rfid-step-num { background: #16A34A; color: white; }
.rfid-step span     { font-size: 12.5px; font-weight: 600; color: #94A3B8; transition: color .2s; }
.rfid-step--active span { color: #0F172A; }
.rfid-step--done   span { color: #16A34A; }
.rfid-step-line     { flex: 1; height: 2px; background: #E2E8F0; margin: 0 12px; }
.rfid-step-line--done { background: #16A34A; }
.rfid-enroll-divider { height: 1px; background: #F1F5F9; margin-bottom: 22px; }

/* ── Enroll Search ── */
.rfid-search-wrap   { position: relative; margin-bottom: 10px; }
.rfid-search-icon   { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9ca3af; pointer-events: none; }
.rfid-search-input  { width: 100%; border: 1.5px solid #E2E8F0; border-radius: 10px; padding: 10px 12px 10px 36px; font-size: 14px; min-height: 46px; font-family: inherit; outline: none; background: #F8FAFC; transition: all .15s; }
.rfid-search-input:focus { border-color: #2563EB; background: white; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.rfid-searching     { display: flex; align-items: center; gap: 7px; font-size: 12.5px; color: #64748B; padding: 6px 2px; }
.rfid-no-result     { font-size: 13px; color: #94A3B8; text-align: center; padding: 16px; margin: 0; }
.rfid-spin          { animation: spin .8s linear infinite; }

.rfid-results       { border: 1px solid #E2E8F0; border-radius: 10px; overflow: hidden; }
.rfid-result-item   { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-bottom: 1px solid #F8FAFC; transition: background .1s; }
.rfid-result-item:last-child { border-bottom: none; }
.rfid-result-item:hover { background: #F8FAFC; }
.rfid-result-avatar { width: 36px; height: 36px; border-radius: 50%; background: #EFF6FF; color: #2563EB; font-size: 14px; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rfid-result-info   { flex: 1; min-width: 0; }
.rfid-result-name   { font-size: 13.5px; font-weight: 600; color: #0F172A; margin: 0 0 2px; }
.rfid-result-meta   { font-size: 11.5px; color: #94A3B8; margin: 0; }
.rfid-btn-select    { padding: 6px 14px; border-radius: 7px; background: #2563EB; color: white; border: none; font-size: 12.5px; font-weight: 700; cursor: pointer; font-family: inherit; min-height: 34px; white-space: nowrap; }
.rfid-btn-select:hover { background: #1D4ED8; }

.rfid-search-placeholder { text-align: center; padding: 36px 20px; color: #94A3B8; display: flex; flex-direction: column; align-items: center; font-size: 13px; }

/* ── Enroll Target Card ── */
.rfid-target-card   { display: flex; align-items: center; gap: 14px; background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 12px; padding: 14px 16px; margin-bottom: 18px; }
.rfid-target-avatar { width: 46px; height: 46px; border-radius: 50%; background: #2563EB; color: white; font-size: 18px; font-weight: 800; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.rfid-target-info   { flex: 1; min-width: 0; }
.rfid-target-label  { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #0284C7; margin: 0 0 3px; }
.rfid-target-name   { font-size: 15px; font-weight: 800; color: #0C4A6E; margin: 0 0 2px; }
.rfid-target-meta   { font-size: 12px; color: #38BDF8; margin: 0; }
.rfid-btn-change    { display: flex; align-items: center; gap: 5px; padding: 6px 12px; border-radius: 7px; background: white; border: 1px solid #CBD5E1; font-size: 12px; font-weight: 600; color: #374151; cursor: pointer; font-family: inherit; white-space: nowrap; flex-shrink: 0; }
.rfid-btn-change:hover { background: #F8FAFC; }

/* ── Tap Area ── */
.rfid-tap-area      { position: relative; text-align: center; padding: 28px 20px; border: 2px dashed #BAE6FD; border-radius: 14px; background: #F0F9FF; margin-bottom: 16px; overflow: hidden; }
.rfid-tap-pulse     { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 80px; height: 80px; background: #2563EB; border-radius: 50%; opacity: 0; animation: rfid-pulse 2.2s cubic-bezier(.4,0,.6,1) infinite; pointer-events: none; }
.rfid-tap-icon      { position: relative; width: 62px; height: 62px; background: #2563EB; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; margin: 0 auto 14px; box-shadow: 0 4px 14px rgba(37,99,235,.3); z-index: 1; }
.rfid-tap-label     { font-size: 14px; font-weight: 700; color: #0369A1; margin: 0 0 5px; }
.rfid-tap-hint      { font-size: 12px; color: #64748B; margin: 0; }

/* ── Manual UID ── */
.rfid-manual-row    { display: flex; gap: 8px; margin-bottom: 14px; }
.rfid-uid-input     { flex: 1; border: 1.5px solid #E2E8F0; border-radius: 8px; padding: 9px 12px; font-size: 13.5px; font-family: monospace; min-height: 44px; outline: none; text-transform: uppercase; background: #F8FAFC; transition: all .15s; }
.rfid-uid-input:focus { background: white; box-shadow: 0 0 0 3px rgba(37,99,235,.1); }
.rfid-btn-submit         { padding: 9px 20px; border-radius: 8px; background: #2563EB; color: white; border: none; font-size: 13.5px; font-weight: 700; cursor: pointer; min-height: 44px; font-family: inherit; white-space: nowrap; transition: background .15s; }
.rfid-btn-submit:hover   { background: #1D4ED8; }
.rfid-btn-submit--disabled { opacity: .5; cursor: not-allowed !important; }

/* ── Enroll Status ── */
.rfid-enroll-status      { display: flex; align-items: center; gap: 10px; padding: 12px 15px; border-radius: 10px; font-size: 13.5px; font-weight: 600; }
.rfid-enroll-status--ok  { background: #F0FDF4; color: #16A34A; border: 1px solid #BBF7D0; }
.rfid-enroll-status--err { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }

/* ── Animations ── */
@keyframes rfid-pulse { 0%,100%{transform:scale(.8);opacity:.25} 50%{transform:scale(2);opacity:0} }
@keyframes slide-in   { from{opacity:0;transform:translateY(-6px)} to{opacity:1;transform:translateY(0)} }
@keyframes spin       { to{transform:rotate(360deg)} }
</style>
