<?php
/**
 * Admin view — Dashboard.  (design.md §4)
 *
 * Ringkasan kondisi absensi hari ini + populasi. Konten DI DALAM wp-admin:
 * bungkus `.absensi-app`, tanpa sidebar/topbar plugin (design.md §3).
 *
 * Susunan: breadcrumb + Refresh · 6 KPI (delta vs pekan lalu) · Tren kehadiran
 * mingguan + Aksi Cepat · Absensi Terbaru.
 * Sumber data (endpoint yang sudah ada): /laporan/summary (hari ini, hari yang
 * sama pekan lalu, dan per hari Sen–Jum untuk tren), /users, /group, /laporan.
 */
defined( 'ABSPATH' ) || exit;

// URL kiosk RFID guru (page dibuat Installer::seed_pages, id di option absensi_pages).
$absensi_pages = get_option( 'absensi_pages' );
$absensi_guru  = is_array( $absensi_pages ) ? absint( $absensi_pages['guru'] ?? 0 ) : 0;
$kiosk_url     = $absensi_guru ? get_permalink( $absensi_guru ) : home_url( '/absensi/guru' );
?>
<div class="wrap">
  <div class="absensi-app" x-data="dashboardManager">
    <div class="absensi-page">

      <!-- Header halaman: judul + tanggal hari ini (kiri) + Refresh (kanan) -->
      <div class="absensi-page__head">
        <div class="page-title-wrap">
          <h1 class="absensi-page__title"><?php esc_html_e( 'Dashboard', 'absensi-sekolah' ); ?></h1>
          <p class="page-subtitle" x-text="tanggalHariIni"></p>
        </div>
        <div class="absensi-page__actions">
          <button type="button" class="btn btn--outline btn--sm" @click="refresh()"
                  :class="loading ? 'is-loading' : ''" :disabled="loading">
            <span class="btn__spin" x-show="loading" x-cloak aria-hidden="true"></span>
            <span class="btn__label" style="display:inline-flex;align-items:center;gap:8px;">
              <span x-html="$icon( 'refresh-cw', 16 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Refresh', 'absensi-sekolah' ); ?>
            </span>
          </button>
        </div>
      </div>

      <!-- Error state + Coba Lagi -->
      <div x-show="error" x-cloak class="alert alert--danger" style="align-items:center;">
        <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
        <span style="flex:1;"><?php esc_html_e( 'Gagal memuat data dashboard.', 'absensi-sekolah' ); ?></span>
        <button type="button" class="btn btn--outline btn--sm" @click="refresh()"><?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?></button>
      </div>

      <!-- KPI: 2 populasi + 4 status hari ini. Kartu status membawa delta vs
           hari yang sama pekan lalu (warna delta ikut makna, bukan tanda). -->
      <div class="kpi-grid">
        <template x-for="c in statCards" :key="c.key">
          <div class="kpi" :class="'kpi--' + c.tone">
            <div class="kpi__top">
              <span class="kpi__label" x-text="c.label"></span>
              <span class="kpi__chip" x-html="$icon(c.icon, 18)" aria-hidden="true"></span>
            </div>

            <span x-show="loading" class="skeleton skeleton--text" style="width:64px;height:28px;"></span>
            <span x-show="! loading" class="kpi__value" x-text="error ? '–' : fmtNum(stats[c.key])"
                  :aria-label="c.label + ': ' + (error ? '-' : (stats[c.key] ?? 0))"></span>

            <!-- Populasi: tak punya histori → sub-label statis, bukan delta palsu -->
            <span class="kpi__delta" x-show="c.sub" x-text="c.sub"></span>

            <!-- Status: delta nyata vs minggu lalu. Panah = arah; warna = makna
                 (telat/izin/alpha naik = merah, turun = hijau). -->
            <span class="kpi__delta" x-show="! c.sub" x-cloak :class="deltaTone(c.key)">
              <span class="kpi__pct">
                <span x-html="$icon(deltaIcon(c.key), 13)" aria-hidden="true"></span>
                <span x-text="deltaText(c.key)"></span>
              </span>
              <span><?php esc_html_e( 'vs minggu lalu', 'absensi-sekolah' ); ?></span>
            </span>
          </div>
        </template>
      </div>

      <!-- Tren kehadiran (2/3) + Aksi Cepat (1/3) -->
      <div class="dash-main">

        <!-- Grafik kehadiran mingguan: DUA seri (hadir & telat) per hari (Sen–Jum) -->
        <div class="card dash-chart">
          <div class="trend-head">
            <div class="card-title-wrap">
              <h2 class="card__title"><?php esc_html_e( 'Grafik Kehadiran', 'absensi-sekolah' ); ?></h2>
              <p class="card__sub"><?php esc_html_e( 'Tren kehadiran mingguan', 'absensi-sekolah' ); ?></p>
            </div>
            <label class="u-hidden" for="absensi-trend-week"><?php esc_html_e( 'Pilih minggu', 'absensi-sekolah' ); ?></label>
            <select id="absensi-trend-week" class="trend-select" @change="gantiMinggu($event.target.value)">
              <option value="ini"><?php esc_html_e( 'Minggu Ini', 'absensi-sekolah' ); ?></option>
              <option value="lalu"><?php esc_html_e( 'Minggu Lalu', 'absensi-sekolah' ); ?></option>
            </select>
          </div>

          <!-- Loading -->
          <div x-show="trendLoading" x-cloak style="padding:8px 0;">
            <span class="skeleton" style="display:block;height:200px;border-radius:var(--r-md);"></span>
          </div>

          <!-- Error -->
          <div x-show="! trendLoading && trendError" x-cloak class="empty" style="padding:32px 16px;">
            <div class="empty__icon" x-html="$icon( 'alert-triangle', 28 )" aria-hidden="true"></div>
            <p class="empty__desc"><?php esc_html_e( 'Gagal memuat tren kehadiran.', 'absensi-sekolah' ); ?></p>
          </div>

          <!-- Grafik garis (area + titik). Nilai = hadir + telat per hari.
               CATATAN: JANGAN pakai <template x-for> di dalam <svg> — Alpine meng-clone
               isi template di namespace HTML, jadi <line>/<circle> tak pernah tergambar
               (dan bindingnya error). Jumlah elemen tetap (3 tick, 5 hari Sen–Jum),
               jadi ditulis eksplisit lewat loop PHP + binding by index. -->
          <svg x-show="! trendLoading && ! trendError" x-cloak class="trend-chart"
               viewBox="0 0 600 200" role="img" :aria-label="trendAria">
            <defs>
              <linearGradient id="absensiTrendFill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="#2563EB" stop-opacity=".18"/>
                <stop offset="100%" stop-color="#2563EB" stop-opacity="0"/>
              </linearGradient>
            </defs>

            <!-- Sumbu Y: 5 garis bantu putus-putus + label (0 · 25% · 50% · 75% · maks) -->
            <?php for ( $t = 0; $t < 5; $t++ ) : ?>
              <line class="trend-grid" x1="46" x2="588"
                    :y1="trendTicks[<?php echo (int) $t; ?>].y" :y2="trendTicks[<?php echo (int) $t; ?>].y"></line>
              <text class="trend-axis trend-axis--y" x="36"
                    :y="trendTicks[<?php echo (int) $t; ?>].y + 4"
                    x-text="fmtY(trendTicks[<?php echo (int) $t; ?>].v)"></text>
            <?php endfor; ?>

            <!-- Area + dua garis: hadir (biru, seri utama) & telat (oranye) -->
            <path class="trend-fill" :d="areaHadir" x-show="hasTrend"></path>
            <path class="trend-line trend-line--hadir" :d="line('hadir')" x-show="hasTrend"></path>
            <path class="trend-line trend-line--telat" :d="line('telat')" x-show="hasTrend"></path>

            <!-- Titik tiap seri + label hari (Sen–Min, seminggu penuh) -->
            <?php for ( $d = 0; $d < 7; $d++ ) : $i = (int) $d; ?>
              <text class="trend-axis trend-axis--x" y="188"
                    :x="pt('hadir', <?php echo $i; ?>).x" x-text="pt('hadir', <?php echo $i; ?>).label"></text>
              <circle class="trend-dot trend-dot--hadir" r="3.5" x-show="hasTrend"
                      :cx="pt('hadir', <?php echo $i; ?>).x" :cy="pt('hadir', <?php echo $i; ?>).y">
                <title x-text="'Hadir ' + pt('hadir', <?php echo $i; ?>).label + ': ' + pt('hadir', <?php echo $i; ?>).value"></title>
              </circle>
              <circle class="trend-dot trend-dot--telat" r="3.5" x-show="hasTrend"
                      :cx="pt('telat', <?php echo $i; ?>).x" :cy="pt('telat', <?php echo $i; ?>).y">
                <title x-text="'Telat ' + pt('telat', <?php echo $i; ?>).label + ': ' + pt('telat', <?php echo $i; ?>).value"></title>
              </circle>
            <?php endfor; ?>

            <!-- Empty: minggu terpilih belum ada catatan (teks ikut minggu yang dipilih) -->
            <text x="317" y="95" class="trend-axis trend-axis--x" x-show="! hasTrend"
                  x-text="'<?php echo esc_js( __( 'Belum ada catatan kehadiran', 'absensi-sekolah' ) ); ?> ' + (week === 'lalu' ? '<?php echo esc_js( __( 'minggu lalu', 'absensi-sekolah' ) ); ?>' : '<?php echo esc_js( __( 'minggu ini', 'absensi-sekolah' ) ); ?>') + '.'"></text>
          </svg>

          <!-- Legenda dua seri -->
          <div class="trend-legend" x-show="! trendLoading && ! trendError" x-cloak>
            <span class="trend-legend__item">
              <span class="trend-legend__dot trend-legend__dot--hadir" aria-hidden="true"></span>
              <?php esc_html_e( 'Hadir', 'absensi-sekolah' ); ?>
            </span>
            <span class="trend-legend__item">
              <span class="trend-legend__dot trend-legend__dot--telat" aria-hidden="true"></span>
              <?php esc_html_e( 'Telat', 'absensi-sekolah' ); ?>
            </span>
          </div>
        </div>

        <!-- Aksi Cepat + ringkasan Tingkat Kehadiran hari ini -->
        <div class="card dash-actions">
          <div class="card__head">
            <div class="card-title-wrap">
              <h2 class="card__title"><?php esc_html_e( 'Aksi Cepat', 'absensi-sekolah' ); ?></h2>
              <p class="card__sub"><?php esc_html_e( 'Pintasan tugas harian', 'absensi-sekolah' ); ?></p>
            </div>
          </div>
          <div class="quick-actions">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-users' ) ); ?>" class="btn btn--primary btn--block">
              <span x-html="$icon( 'user-plus', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Tambah User', 'absensi-sekolah' ); ?>
            </a>
            <a href="<?php echo esc_url( $kiosk_url ); ?>" class="btn btn--outline-primary btn--block" target="_blank" rel="noopener">
              <span x-html="$icon( 'scan-line', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Buka Kiosk RFID', 'absensi-sekolah' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-laporan' ) ); ?>" class="btn btn--outline btn--block">
              <span x-html="$icon( 'file-text', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Lihat Laporan', 'absensi-sekolah' ); ?>
            </a>
          </div>

          <!-- HARI INI: % hadir dari seluruh absensi yang tercatat hari ini -->
          <div class="today-rate">
            <p class="today-rate__eyebrow"><?php esc_html_e( 'Hari Ini', 'absensi-sekolah' ); ?></p>
            <div class="today-rate__row">
              <span class="today-rate__label"><?php esc_html_e( 'Tingkat Kehadiran', 'absensi-sekolah' ); ?></span>
              <span class="today-rate__pct u-num" x-text="rateHadir + '%'"></span>
            </div>
            <div class="today-rate__track">
              <div class="today-rate__bar" :style="'width:' + rateHadir + '%'"></div>
            </div>
            <div class="today-rate__row today-rate__row--foot">
              <span><span class="u-num" x-text="fmtNum(stats.hadir)"></span> <?php esc_html_e( 'hadir', 'absensi-sekolah' ); ?></span>
              <span><span class="u-num" x-text="fmtNum(totalHariIni)"></span> <?php esc_html_e( 'tercatat', 'absensi-sekolah' ); ?></span>
            </div>
          </div>
        </div>

      </div><!-- /.dash-main -->

      <!-- Absensi Terbaru -->
      <div class="card dash-recent" style="padding:0;">
        <div class="card__head">
          <div class="card-title-wrap">
            <h2 class="card__title"><?php esc_html_e( 'Absensi Terbaru', 'absensi-sekolah' ); ?></h2>
            <p class="card__sub"><?php esc_html_e( 'Absensi terakhir hari ini', 'absensi-sekolah' ); ?></p>
          </div>
          <a class="link-all" href="<?php echo esc_url( admin_url( 'admin.php?page=absensi-laporan' ) ); ?>"><?php esc_html_e( 'Lihat Semua', 'absensi-sekolah' ); ?></a>
        </div>

        <!-- Loading skeleton -->
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
                <th scope="col"><?php esc_html_e( 'User', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Group', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Waktu', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(r, i) in recent" :key="i">
                <tr>
                  <td>
                    <div class="recent-user">
                      <span class="table__avatar" x-text="inisial(r.nama)" aria-hidden="true"></span>
                      <span>
                        <span class="recent-user__name" x-text="r.nama || '—'"></span><br>
                        <span class="recent-user__id"><?php esc_html_e( 'ID:', 'absensi-sekolah' ); ?> <span x-text="r.nomor_induk || '—'"></span></span>
                      </span>
                    </div>
                  </td>
                  <td><span x-text="r.nama_group || '—'"></span></td>
                  <td><span class="badge" :class="statusBadge(r.status)" x-text="statusLabel(r.status)"></span></td>
                  <td>
                    <span class="u-num" x-text="jamHM(r.waktu_masuk)"></span>
                    <span class="u-muted t-caption" x-show="r.waktu_masuk"><?php esc_html_e( 'WIB', 'absensi-sekolah' ); ?></span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</div>
