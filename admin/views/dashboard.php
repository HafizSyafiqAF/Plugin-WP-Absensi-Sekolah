<?php
/**
 * Admin view — Dashboard.  (design.md §4)
 *
 * Ringkasan cepat kondisi absensi hari ini + populasi. Konten DI DALAM wp-admin:
 * bungkus `.absensi-app`, tanpa sidebar/topbar plugin (design.md §3).
 * Sumber: /laporan/summary, /users, /group, /laporan (terbaru).
 *
 * Dibangun bertahap per item TODO-FE. Item ini = header (Refresh) + Quick Stats (6).
 * Grafik, quick action, absensi terbaru, state = item berikutnya.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="dashboardManager">
    <div class="absensi-page">

      <!-- Header halaman (design.md §3.1 / §4): judul + Refresh -->
      <div class="absensi-page__head">
        <h1 class="absensi-page__title"><?php esc_html_e( 'Dashboard', 'absensi-sekolah' ); ?></h1>
        <div class="absensi-page__actions">
          <button type="button" class="btn btn--outline" @click="refresh()"
                  :class="loading ? 'is-loading' : ''" :disabled="loading">
            <span class="btn__spin" x-show="loading" x-cloak aria-hidden="true"></span>
            <span class="btn__label" style="display:inline-flex;align-items:center;gap:8px;">
              <span x-html="$icon( 'refresh-cw', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Refresh', 'absensi-sekolah' ); ?>
            </span>
          </button>
        </div>
      </div>

      <!-- Error state + Coba Lagi (design.md §4) -->
      <div x-show="error" x-cloak class="alert alert--danger" style="align-items:center;">
        <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
        <span style="flex:1;"><?php esc_html_e( 'Gagal memuat data dashboard.', 'absensi-sekolah' ); ?></span>
        <button type="button" class="btn btn--outline btn--sm" @click="refresh()"><?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?></button>
      </div>

      <!-- Quick Stats (design.md §4): 2 grup ber-eyebrow — Populasi + Kehadiran.
           Kartu ber-aksen tone (bar atas + chip ikon filled + glow). Kehadiran:
           angka berwarna tone + badge share %. Populasi: badge label statis. -->
      <p class="dash-eyebrow"><?php esc_html_e( 'Populasi', 'absensi-sekolah' ); ?></p>
      <div class="dash-stats dash-stats--pop">
        <template x-for="c in statCards.filter(s => s.group === 'pop')" :key="c.key">
          <div class="card stat" :class="'stat--' + c.tone">
            <div class="stat__top">
              <span class="stat__chip" x-html="$icon(c.icon, 20)" aria-hidden="true"></span>
              <span class="stat__badge" x-text="c.chip"></span>
            </div>
            <span x-show="loading" class="skeleton skeleton--text" style="width:56px;height:34px;"></span>
            <span x-show="! loading" class="stat__value u-num" x-text="error ? '–' : (stats[c.key] ?? 0)"
                  :aria-label="c.label + ': ' + (error ? '-' : (stats[c.key] ?? 0))"></span>
            <span class="stat__label" x-text="c.label"></span>
          </div>
        </template>
      </div>

      <p class="dash-eyebrow"><?php esc_html_e( 'Kehadiran Hari Ini', 'absensi-sekolah' ); ?></p>
      <div class="dash-stats dash-stats--keh">
        <template x-for="c in statCards.filter(s => s.group === 'keh')" :key="c.key">
          <div class="card stat" :class="'stat--' + c.tone">
            <div class="stat__top">
              <span class="stat__chip" x-html="$icon(c.icon, 20)" aria-hidden="true"></span>
              <span class="stat__badge" x-show="! loading && ! error && chartTotal > 0" x-cloak
                    x-text="sharePct(c.key) + '%'"></span>
            </div>
            <span x-show="loading" class="skeleton skeleton--text" style="width:56px;height:34px;"></span>
            <span x-show="! loading" class="stat__value stat__value--tone u-num" x-text="error ? '–' : (stats[c.key] ?? 0)"
                  :aria-label="c.label + ': ' + (error ? '-' : (stats[c.key] ?? 0))"></span>
            <span class="stat__label" x-text="c.label"></span>
          </div>
        </template>
      </div>

      <!-- Grafik (2/3) + Quick Action (1/3) — design.md §4 -->
      <div class="dash-main">

      <!-- Grafik kehadiran (design.md §4): distribusi status hari ini (bar, peta warna) -->
      <div class="card dash-chart">
        <div class="card__head">
          <h2 class="card__title"><?php esc_html_e( 'Distribusi Kehadiran Hari Ini', 'absensi-sekolah' ); ?></h2>
        </div>

        <!-- Loading skeleton (5 bar) -->
        <div x-show="loading" x-cloak class="chart-bars" aria-hidden="true">
          <template x-for="n in 5" :key="n">
            <div class="chart-row">
              <span class="skeleton skeleton--text" style="width:56px"></span>
              <span class="skeleton" style="height:14px;border-radius:999px"></span>
              <span class="skeleton skeleton--text" style="width:24px;margin-left:auto"></span>
            </div>
          </template>
        </div>

        <!-- Empty: belum ada data hari ini -->
        <div x-show="! loading && chartTotal === 0" x-cloak class="empty" style="padding:32px 16px;">
          <div class="empty__icon" x-html="$icon( 'bar-chart-3', 28 )" aria-hidden="true"></div>
          <p class="empty__desc"><?php esc_html_e( 'Belum ada data kehadiran hari ini.', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- Bar chart horizontal (aria: tiap baris label + nilai) -->
        <div class="chart-bars" x-show="! loading && chartTotal > 0" x-cloak role="img"
             :aria-label="'<?php echo esc_js( __( 'Distribusi kehadiran hari ini', 'absensi-sekolah' ) ); ?>'">
          <template x-for="s in chartStatuses" :key="s.key">
            <div class="chart-row">
              <span class="chart-row__label" x-text="s.label"></span>
              <div class="chart-row__track">
                <div class="chart-row__bar" :class="'chart-row__bar--' + s.tone"
                     :style="'width:' + barPct(stats[s.key]) + '%'"></div>
              </div>
              <span class="chart-row__val u-num" x-text="stats[s.key] ?? 0"
                    :aria-label="s.label + ': ' + (stats[s.key] ?? 0)"></span>
            </div>
          </template>
        </div>
      </div>

      <!-- Quick Action (design.md §4): tombol pintas ke halaman lain -->
      <div class="card dash-actions">
        <div class="card__head">
          <h2 class="card__title"><?php esc_html_e( 'Aksi Cepat', 'absensi-sekolah' ); ?></h2>
        </div>
        <div class="quick-actions">
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-users' ) ); ?>" class="btn btn--primary btn--block">
            <span x-html="$icon( 'plus', 18 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Tambah User', 'absensi-sekolah' ); ?>
          </a>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-group' ) ); ?>" class="btn btn--outline btn--block">
            <span x-html="$icon( 'plus', 18 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Tambah Group', 'absensi-sekolah' ); ?>
          </a>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-laporan' ) ); ?>" class="btn btn--ghost btn--block">
            <span x-html="$icon( 'bar-chart-3', 18 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Lihat Laporan', 'absensi-sekolah' ); ?>
          </a>
          <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-laporan' ) ); ?>" class="btn btn--outline btn--block">
            <span x-html="$icon( 'download', 18 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Export Laporan', 'absensi-sekolah' ); ?>
          </a>
        </div>
      </div>

      </div><!-- /.dash-main -->

      <!-- Absensi Terbaru (design.md §4): tabel ringkas GET /laporan terbaru -->
      <div class="card dash-recent" style="padding:0;">
        <div class="card__head" style="padding:16px 20px;margin:0;border-bottom:1px solid var(--c-border);">
          <h2 class="card__title"><?php esc_html_e( 'Absensi Terbaru', 'absensi-sekolah' ); ?></h2>
        </div>

        <!-- Loading skeleton (5 baris) -->
        <div x-show="loading" x-cloak style="padding:8px 20px 16px;">
          <template x-for="n in 5" :key="n">
            <div class="skeleton skeleton--row"></div>
          </template>
        </div>

        <!-- Empty -->
        <div x-show="! loading && recent.length === 0" x-cloak class="empty" style="padding:32px 16px;">
          <div class="empty__icon" x-html="$icon( 'clipboard-check', 28 )" aria-hidden="true"></div>
          <p class="empty__desc"><?php esc_html_e( 'Belum ada absensi terbaru.', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- Tabel ringkas -->
        <div x-show="! loading && recent.length > 0" x-cloak class="table-scroll">
          <table class="table">
            <thead>
              <tr>
                <th scope="col"><?php esc_html_e( 'Nama', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Group', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(r, i) in recent" :key="i">
                <tr>
                  <td>
                    <div class="table__user">
                      <span class="table__avatar" x-text="inisial(r.nama)" aria-hidden="true"></span>
                      <span class="t-body-strong" x-text="r.nama || '—'"></span>
                    </div>
                  </td>
                  <td><span x-text="r.nama_group || '—'"></span></td>
                  <td><span class="badge" :class="statusBadge(r.status)" x-text="statusLabel(r.status)"></span></td>
                  <td><span class="u-num" x-text="jamHM(r.waktu_masuk)"></span></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <?php // State lengkap (skeleton/error) = item berikutnya. ?>

    </div>
  </div>
</div>
