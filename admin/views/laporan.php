<?php
/**
 * Admin view — Laporan Kehadiran.  (design.md §8)
 *
 * Rekap kehadiran per rentang + ringkasan + grafik mingguan + export (CSV/XLSX/PDF).
 * Konten DI DALAM wp-admin: bungkus `.absensi-app` (design.md §3).
 * Endpoint: /laporan, /laporan/summary, /laporan/export — semuanya sudah ada.
 *
 * Susunan (acuan desain): header + Export · 6 KPI · Grafik mingguan · Filter
 * (rentang + grup + pill status) · Tabel + Detail · Pagination.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="laporanManager">
    <div class="absensi-page">

      <!-- Header: judul + subjudul (kiri) · Export Data (kanan) -->
      <div class="absensi-page__head">
        <div class="page-title-wrap">
          <h1 class="absensi-page__title"><?php esc_html_e( 'Laporan Kehadiran', 'absensi-sekolah' ); ?></h1>
          <p class="page-subtitle"><?php esc_html_e( 'Ringkasan dan detail data kehadiran seluruh grup.', 'absensi-sekolah' ); ?></p>
        </div>
        <div class="absensi-page__actions">
          <div class="dropdown" @keydown.escape="exportOpen = false" @click.outside="exportOpen = false">
            <button type="button" class="btn btn--primary" @click="exportOpen = ! exportOpen"
                    :disabled="total === 0"
                    :aria-expanded="exportOpen ? 'true' : 'false'" aria-haspopup="menu">
              <span x-html="$icon( 'download', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Export Data', 'absensi-sekolah' ); ?>
              <span x-html="$icon( 'chevron-down', 16 )" aria-hidden="true"></span>
            </button>
            <div class="dropdown__menu" x-show="exportOpen" x-cloak role="menu">
              <template x-for="f in exportFormats" :key="f.key">
                <button type="button" class="dropdown__item" role="menuitem"
                        @click="exportOpen = false; doExport(f.key)" x-text="f.label"></button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- 6 KPI (GET /laporan/summary, ikut filter): Total + 5 status + % dari total -->
      <div class="kpi-grid">
        <template x-for="c in sumCards" :key="c.key">
          <div class="kpi" :class="'kpi--' + c.tone">
            <div class="kpi__top">
              <span class="kpi__label" x-text="c.label"></span>
              <span class="kpi__chip" x-html="$icon(c.icon, 18)" aria-hidden="true"></span>
            </div>
            <span x-show="summaryLoading" class="skeleton skeleton--text" style="width:64px;height:28px;"></span>
            <span x-show="! summaryLoading" class="kpi__value" :class="c.share ? 'kpi__value--tone' : ''"
                  x-text="summaryError ? '–' : fmtNum(summary[c.key])"
                  :aria-label="c.label + ': ' + (summaryError ? '-' : (summary[c.key] ?? 0))"></span>
            <span class="kpi__delta" x-show="c.share && ! summaryLoading && ! summaryError && summary.total > 0" x-cloak>
              <span class="u-num" x-text="sharePct(c.key) + '%'"></span>
              <?php esc_html_e( 'dari total', 'absensi-sekolah' ); ?>
            </span>
          </div>
        </template>
      </div>

      <!-- Grafik Kehadiran Mingguan (Sen–Jum, seri Hadir). Ikut filter grup;
           minggunya mengikuti tanggal "Sampai" (atau minggu ini bila kosong). -->
      <div class="card dash-chart">
        <div class="trend-head">
          <div class="card-title-wrap">
            <h2 class="card__title"><?php esc_html_e( 'Grafik Kehadiran Mingguan', 'absensi-sekolah' ); ?></h2>
            <p class="card__sub"><?php esc_html_e( 'Jumlah hadir per hari', 'absensi-sekolah' ); ?></p>
          </div>
        </div>

        <div x-show="trendLoading" x-cloak style="padding:8px 0;">
          <span class="skeleton" style="display:block;height:190px;border-radius:var(--r-md);"></span>
        </div>

        <div x-show="! trendLoading && trendError" x-cloak class="empty" style="padding:28px 16px;">
          <div class="empty__icon" x-html="$icon( 'alert-triangle', 26 )" aria-hidden="true"></div>
          <p class="empty__desc"><?php esc_html_e( 'Gagal memuat grafik.', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- CATATAN: jangan pakai <template x-for> di dalam <svg> (Alpine clone di
             namespace HTML → elemen SVG tak tergambar). Elemen ditulis eksplisit. -->
        <svg x-show="! trendLoading && ! trendError" x-cloak class="trend-chart"
             viewBox="0 0 1200 200" role="img" :aria-label="trendAria">
          <defs>
            <linearGradient id="absensiLaporanFill" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#22C55E" stop-opacity=".18"/>
              <stop offset="100%" stop-color="#22C55E" stop-opacity="0"/>
            </linearGradient>
          </defs>

          <?php for ( $t = 0; $t < 5; $t++ ) : ?>
            <line class="trend-grid" x1="50" x2="1180"
                  :y1="trendTicks[<?php echo (int) $t; ?>].y" :y2="trendTicks[<?php echo (int) $t; ?>].y"></line>
            <text class="trend-axis trend-axis--y" x="40"
                  :y="trendTicks[<?php echo (int) $t; ?>].y + 4"
                  x-text="fmtY(trendTicks[<?php echo (int) $t; ?>].v)"></text>
          <?php endfor; ?>

          <path class="trend-fill trend-fill--hijau" :d="trendArea" x-show="hasTrend"></path>
          <path class="trend-line trend-line--hadir-hijau" :d="trendLine" x-show="hasTrend"></path>

          <?php for ( $d = 0; $d < 5; $d++ ) : $i = (int) $d; ?>
            <text class="trend-axis trend-axis--x" y="184"
                  :x="pt(<?php echo $i; ?>).x" x-text="pt(<?php echo $i; ?>).label"></text>
            <circle class="trend-dot trend-dot--hijau" r="3.5" x-show="hasTrend"
                    :cx="pt(<?php echo $i; ?>).x" :cy="pt(<?php echo $i; ?>).y">
              <title x-text="pt(<?php echo $i; ?>).label + ' ' + pt(<?php echo $i; ?>).tanggal + ' — ' + pt(<?php echo $i; ?>).value + ' hadir'"></title>
            </circle>
          <?php endfor; ?>

          <text x="615" y="92" class="trend-axis trend-axis--x" x-show="! hasTrend">
            <?php esc_html_e( 'Belum ada catatan kehadiran pada minggu ini.', 'absensi-sekolah' ); ?>
          </text>
        </svg>
      </div>

      <!-- Tabel + filter -->
      <div class="table-card">

        <!-- Filter: rentang tanggal + grup (server) · pill status (client) -->
        <div class="report-bar">
          <div class="report-bar__left">
            <div class="date-range">
              <span class="date-range__icon" x-html="$icon( 'calendar', 16 )" aria-hidden="true"></span>
              <label class="u-hidden" for="lf-dari"><?php esc_html_e( 'Dari', 'absensi-sekolah' ); ?></label>
              <input id="lf-dari" type="date" class="date-range__input" x-model="filter.dari"
                     @change="onDateChange(); applyFilter()">
              <span class="date-range__sep">–</span>
              <label class="u-hidden" for="lf-sampai"><?php esc_html_e( 'Sampai', 'absensi-sekolah' ); ?></label>
              <input id="lf-sampai" type="date" class="date-range__input" x-model="filter.sampai"
                     @change="onDateChange(); applyFilter()">
            </div>

            <!-- Tipe: opsinya dari tipe group yang ada di data (teks bebas, tanpa preset).
                 Server-side (param `tipe`) → summary, tabel, DAN export ikut tersaring. -->
            <select class="select select--sm" x-model="filter.tipe" @change="onTipeChange(); applyFilter()"
                    aria-label="<?php esc_attr_e( 'Filter tipe', 'absensi-sekolah' ); ?>">
              <option value=""><?php esc_html_e( 'Semua Tipe', 'absensi-sekolah' ); ?></option>
              <template x-for="t in tipeOptions" :key="t.value">
                <option :value="t.value" x-text="t.label"></option>
              </template>
            </select>

            <!-- Grup: opsinya ikut menyempit sesuai Tipe (nama grup boleh kembar antar tipe). -->
            <select class="select select--sm" x-model="filter.group_id" @change="applyFilter()"
                    aria-label="<?php esc_attr_e( 'Filter grup', 'absensi-sekolah' ); ?>">
              <option value=""><?php esc_html_e( 'Semua Grup', 'absensi-sekolah' ); ?></option>
              <template x-for="g in groupsForFilter" :key="g.id">
                <option :value="g.id" x-text="g.nama"></option>
              </template>
            </select>

            <button type="button" class="btn btn--ghost btn--sm" x-show="filter.dari || filter.sampai || filter.group_id || filter.tipe" x-cloak
                    @click="resetFilter()">
              <span x-html="$icon( 'rotate-ccw', 16 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Reset', 'absensi-sekolah' ); ?>
            </button>
          </div>

          <!-- Pill status: menyaring baris yang SUDAH termuat (BE tak punya param status) -->
          <div class="pill-tabs" role="group" aria-label="<?php esc_attr_e( 'Filter status kehadiran', 'absensi-sekolah' ); ?>">
            <template x-for="s in statusPills" :key="s.key">
              <button type="button" class="pill pill--dark" :class="statusFilter === s.key ? 'is-active' : ''"
                      :aria-pressed="statusFilter === s.key ? 'true' : 'false'"
                      @click="statusFilter = s.key" x-text="s.label"></button>
            </template>
          </div>
        </div>

        <!-- Skeleton -->
        <div x-show="laporanLoading" x-cloak style="padding:12px 16px;">
          <template x-for="n in 6" :key="n">
            <div class="skeleton skeleton--row"></div>
          </template>
        </div>

        <!-- Error -->
        <div x-show="! laporanLoading && laporanError" x-cloak class="error-state">
          <div class="error-state__icon" x-html="$icon( 'alert-triangle', 32 )" aria-hidden="true"></div>
          <p class="error-state__title"><?php esc_html_e( 'Gagal memuat laporan', 'absensi-sekolah' ); ?></p>
          <button type="button" class="btn btn--outline" @click="loadLaporan()">
            <span x-html="$icon( 'refresh-cw', 16 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?>
          </button>
        </div>

        <!-- Empty -->
        <div x-show="! laporanLoading && ! laporanError && filteredRows.length === 0" x-cloak class="empty">
          <div class="empty__icon" x-html="$icon( 'clipboard-check', 32 )" aria-hidden="true"></div>
          <p class="empty__title"><?php esc_html_e( 'Tidak ada data', 'absensi-sekolah' ); ?></p>
          <p class="empty__desc"><?php esc_html_e( 'Ubah rentang tanggal, grup, atau status.', 'absensi-sekolah' ); ?></p>
        </div>

        <!-- Tabel -->
        <div x-show="! laporanLoading && ! laporanError && filteredRows.length > 0" x-cloak class="table-scroll">
          <table class="table report-table">
            <thead>
              <tr>
                <th scope="col"><?php esc_html_e( 'Tanggal', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Nama / NIS', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Grup', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Jarak', 'absensi-sekolah' ); ?></th>
                <th scope="col" class="col-actions"><?php esc_html_e( 'Aksi', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(r, i) in filteredRows" :key="i">
                <tr>
                  <td data-label="<?php esc_attr_e( 'Tanggal', 'absensi-sekolah' ); ?>">
                    <span x-text="tglPendek(r.tanggal)"></span>
                  </td>
                  <!-- Nama + nomor induk (avatar seperti halaman Users) -->
                  <td data-label="<?php esc_attr_e( 'Nama / NIS', 'absensi-sekolah' ); ?>">
                    <div class="table__user">
                      <span class="table__avatar" :class="avatarTone(r.nama)" x-text="inisial(r.nama)" aria-hidden="true"></span>
                      <div>
                        <div class="table__user-name" x-text="r.nama || '—'"></div>
                        <div class="table__user-id">
                          <?php esc_html_e( 'NIS:', 'absensi-sekolah' ); ?>
                          <span class="u-num" x-text="r.nomor_induk || '—'"></span>
                        </div>
                      </div>
                    </div>
                  </td>
                  <td data-label="<?php esc_attr_e( 'Grup', 'absensi-sekolah' ); ?>"><span x-text="r.nama_group || '—'"></span></td>
                  <td data-label="<?php esc_attr_e( 'Status', 'absensi-sekolah' ); ?>">
                    <span class="badge" :class="statusBadge(r.status)" x-text="statusLabel(r.status)"></span>
                  </td>
                  <!-- Jam masuk + selisih menit telat (dihitung dari jam masuk di Pengaturan) -->
                  <td data-label="<?php esc_attr_e( 'Jam Masuk', 'absensi-sekolah' ); ?>">
                    <span class="jam" x-text="jamHM(r.waktu_masuk)"></span>
                    <span class="jam-telat" x-show="menitTelat(r) !== null" x-cloak
                          x-text="'(+' + menitTelat(r) + 'm)'"></span>
                  </td>
                  <td data-label="<?php esc_attr_e( 'Jam Keluar', 'absensi-sekolah' ); ?>">
                    <span class="jam" x-text="jamHM(r.waktu_keluar)"></span>
                  </td>
                  <td data-label="<?php esc_attr_e( 'Jarak', 'absensi-sekolah' ); ?>">
                    <span class="u-num" x-text="jarakTeks(r)"></span>
                  </td>
                  <td class="col-actions" data-label="<?php esc_attr_e( 'Aksi', 'absensi-sekolah' ); ?>">
                    <button type="button" class="link-btn" @click="openDetail(r)"
                            :aria-label="'<?php echo esc_js( __( 'Detail absensi', 'absensi-sekolah' ) ); ?> ' + (r.nama || '')">
                      <?php esc_html_e( 'Detail', 'absensi-sekolah' ); ?>
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Footer: info + pagination (server-side) -->
        <div x-show="! laporanLoading && ! laporanError && total > 0" x-cloak class="table-foot">
          <span class="table-foot__info">
            <?php esc_html_e( 'Menampilkan', 'absensi-sekolah' ); ?>
            <strong class="u-num" x-text="pageStart"></strong>–<strong class="u-num" x-text="pageEnd"></strong>
            <?php esc_html_e( 'dari', 'absensi-sekolah' ); ?>
            <strong class="u-num" x-text="fmtNum(total)"></strong> <?php esc_html_e( 'data', 'absensi-sekolah' ); ?>
          </span>
          <div class="pagination__pages" x-show="totalPage > 1">
            <button type="button" class="page-btn" :disabled="page <= 1" @click="goPage(page - 1)"
                    aria-label="<?php esc_attr_e( 'Halaman sebelumnya', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'chevron-left', 16 )"></span>
            </button>
            <template x-for="(p, i) in pageWindow" :key="i">
              <button type="button" class="page-btn" :class="p === page ? 'is-active' : ''"
                      :disabled="p === '…'" @click="goPage(p)" x-text="p"></button>
            </template>
            <button type="button" class="page-btn" :disabled="page >= totalPage" @click="goPage(page + 1)"
                    aria-label="<?php esc_attr_e( 'Halaman berikutnya', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'chevron-right', 16 )"></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Detail: dari data baris yang sudah dimuat (tak ada endpoint detail) -->
      <div x-show="detailOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeDetail()" @click.self="closeDetail()">
        <div class="modal modal--plain" role="dialog" aria-modal="true" aria-labelledby="ld-title">
          <div class="modal__head">
            <div>
              <h2 class="modal__title" id="ld-title"><?php esc_html_e( 'Detail Absensi', 'absensi-sekolah' ); ?></h2>
              <p class="card__sub" x-text="detailRow ? (detailRow.nama || '—') + ' · ' + tglPendek(detailRow.tanggal) : ''"></p>
            </div>
            <button type="button" class="modal__close" @click="closeDetail()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>

          <div class="modal__body" x-show="detailRow">
            <dl class="detail-list">
              <div class="detail-row">
                <dt><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></dt>
                <dd><span class="badge" :class="statusBadge(detailRow?.status)" x-text="statusLabel(detailRow?.status)"></span></dd>
              </div>
              <div class="detail-row">
                <dt><?php esc_html_e( 'Nomor Induk', 'absensi-sekolah' ); ?></dt>
                <dd class="u-num" x-text="detailRow?.nomor_induk || '—'"></dd>
              </div>
              <div class="detail-row">
                <dt><?php esc_html_e( 'Grup', 'absensi-sekolah' ); ?></dt>
                <dd x-text="detailRow?.nama_group || '—'"></dd>
              </div>
              <div class="detail-row">
                <dt><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></dt>
                <dd>
                  <span class="jam" x-text="jamHM(detailRow?.waktu_masuk)"></span>
                  <span class="u-muted" x-text="' · ' + metodeLabel(detailRow?.metode_masuk)"></span>
                </dd>
              </div>
              <div class="detail-row">
                <dt><?php esc_html_e( 'Jam Keluar', 'absensi-sekolah' ); ?></dt>
                <dd>
                  <span class="jam" x-text="jamHM(detailRow?.waktu_keluar)"></span>
                  <span class="u-muted" x-text="' · ' + metodeLabel(detailRow?.metode_keluar)"></span>
                </dd>
              </div>
              <div class="detail-row">
                <dt><?php esc_html_e( 'Jarak dari sekolah', 'absensi-sekolah' ); ?></dt>
                <dd class="u-num" x-text="jarakTeks(detailRow)"></dd>
              </div>
              <div class="detail-row" x-show="detailRow?.catatan">
                <dt><?php esc_html_e( 'Catatan', 'absensi-sekolah' ); ?></dt>
                <dd x-text="detailRow?.catatan"></dd>
              </div>
              <div class="detail-row" x-show="detailRow?.bukti_url">
                <dt><?php esc_html_e( 'Bukti', 'absensi-sekolah' ); ?></dt>
                <dd><a :href="detailRow?.bukti_url" target="_blank" rel="noopener"><?php esc_html_e( 'Lihat berkas', 'absensi-sekolah' ); ?></a></dd>
              </div>
            </dl>
          </div>

          <div class="modal__footer">
            <button type="button" class="btn btn--outline" @click="closeDetail()"><?php esc_html_e( 'Tutup', 'absensi-sekolah' ); ?></button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
