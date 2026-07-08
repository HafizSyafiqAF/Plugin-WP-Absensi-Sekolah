<?php
/**
 * Admin view — Laporan.  (design.md §8)
 *
 * Rekap kehadiran per rentang + summary + export (CSV/XLSX/PDF). Konten DI DALAM
 * wp-admin: bungkus `.absensi-app`, tanpa sidebar/topbar plugin (design.md §3).
 * Endpoint: /laporan, /laporan/summary, /laporan/export. Label "Group" & "Nomor Induk".
 *
 * Dibangun bertahap per item TODO-FE. Item ini = header (judul + Export dropdown).
 * Summary cards, filter, tabel, export, state = item berikutnya.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="laporanManager">
    <div class="absensi-page">

      <!-- Header halaman (design.md §3.1 / §8): judul kiri + Export kanan -->
      <div class="absensi-page__head">
        <h1 class="absensi-page__title"><?php esc_html_e( 'Laporan', 'absensi-sekolah' ); ?></h1>
        <div class="absensi-page__actions">
          <!-- Export dropdown (CSV/XLSX/PDF) -->
          <div class="dropdown" @keydown.escape="exportOpen = false" @click.outside="exportOpen = false">
            <button type="button" class="btn btn--outline" @click="exportOpen = ! exportOpen"
                    :disabled="total === 0"
                    :aria-expanded="exportOpen ? 'true' : 'false'" aria-haspopup="menu">
              <span x-html="$icon( 'download', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Export', 'absensi-sekolah' ); ?>
              <span x-html="$icon( 'chevron-down', 16 )" aria-hidden="true"></span>
            </button>
            <div class="dropdown__menu" x-show="exportOpen" x-cloak role="menu"
                 x-transition.opacity.duration.120ms>
              <template x-for="f in exportFormats" :key="f.key">
                <button type="button" class="dropdown__item" role="menuitem"
                        @click="doExport(f.key)" x-text="f.label"></button>
              </template>
            </div>
          </div>
        </div>
      </div>

      <!-- Summary cards (6) — design.md §8: peta warna status (GET /laporan/summary) -->
      <div class="summary-grid">
        <template x-for="c in sumCards" :key="c.key">
          <div class="sum-card" :class="'sum-card--' + c.tone">
            <span class="sum-card__label" x-text="c.label"></span>
            <span class="sum-card__value u-num" x-text="summary[c.key] ?? 0"></span>
          </div>
        </template>
      </div>

      <!-- Filter server (design.md §8): Dari/Sampai + Preset + Group + Terapkan/Reset -->
      <div class="report-filter">
        <div class="field report-filter__field">
          <label class="field__label" for="lf-dari"><?php esc_html_e( 'Dari', 'absensi-sekolah' ); ?></label>
          <input id="lf-dari" type="date" class="input" x-model="filter.dari" @change="onDateChange()">
        </div>
        <div class="field report-filter__field">
          <label class="field__label" for="lf-sampai"><?php esc_html_e( 'Sampai', 'absensi-sekolah' ); ?></label>
          <input id="lf-sampai" type="date" class="input" x-model="filter.sampai" @change="onDateChange()">
        </div>
        <div class="field report-filter__field">
          <label class="field__label" for="lf-preset"><?php esc_html_e( 'Preset', 'absensi-sekolah' ); ?></label>
          <select id="lf-preset" class="select" x-model="filter.preset" @change="onPresetChange()">
            <option value=""><?php esc_html_e( 'Kustom', 'absensi-sekolah' ); ?></option>
            <option value="harian"><?php esc_html_e( 'Hari ini', 'absensi-sekolah' ); ?></option>
            <option value="mingguan"><?php esc_html_e( 'Minggu ini', 'absensi-sekolah' ); ?></option>
            <option value="bulanan"><?php esc_html_e( 'Bulan ini', 'absensi-sekolah' ); ?></option>
          </select>
        </div>
        <div class="field report-filter__field">
          <label class="field__label" for="lf-group"><?php esc_html_e( 'Group', 'absensi-sekolah' ); ?></label>
          <select id="lf-group" class="select" x-model="filter.group_id">
            <option value=""><?php esc_html_e( 'Semua Group', 'absensi-sekolah' ); ?></option>
            <template x-for="g in groups" :key="g.id">
              <option :value="g.id" x-text="g.nama"></option>
            </template>
          </select>
        </div>
        <div class="report-filter__actions">
          <button type="button" class="btn btn--primary" @click="applyFilter()"><?php esc_html_e( 'Terapkan', 'absensi-sekolah' ); ?></button>
          <button type="button" class="btn btn--ghost" @click="resetFilter()"><?php esc_html_e( 'Reset', 'absensi-sekolah' ); ?></button>
        </div>
      </div>

      <!-- Filter status (client-side, design.md §8): saring baris termuat. BE tak punya param status. -->
      <div class="pill-tabs report-status" role="group" aria-label="<?php esc_attr_e( 'Filter status kehadiran', 'absensi-sekolah' ); ?>">
        <template x-for="s in statusPills" :key="s.key">
          <button type="button" class="pill" :class="statusFilter === s.key ? 'is-active' : ''"
                  :aria-pressed="statusFilter === s.key ? 'true' : 'false'"
                  @click="statusFilter = s.key" x-text="s.label"></button>
        </template>
      </div>

      <!-- Tabel Rekap (design.md §8). Skeleton/empty/error/responsive = item State. -->
      <div class="table-card">
        <div class="table-scroll">
          <table class="table report-table">
            <thead>
              <tr>
                <th scope="col"><?php esc_html_e( 'Tanggal', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Nama', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Nomor Induk', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Group', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Status', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Masuk', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Keluar', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Metode', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Jarak', 'absensi-sekolah' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Bukti', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="(r, i) in filteredRows" :key="i">
                <tr>
                  <td data-label="<?php esc_attr_e( 'Tanggal', 'absensi-sekolah' ); ?>"><span class="u-num" x-text="r.tanggal"></span></td>
                  <td data-label="<?php esc_attr_e( 'Nama', 'absensi-sekolah' ); ?>"><span class="t-body-strong" x-text="r.nama || '—'"></span></td>
                  <td data-label="<?php esc_attr_e( 'Nomor Induk', 'absensi-sekolah' ); ?>"><span class="u-num" x-text="r.nomor_induk || '—'"></span></td>
                  <td data-label="<?php esc_attr_e( 'Group', 'absensi-sekolah' ); ?>"><span x-text="r.nama_group || '—'"></span></td>
                  <td data-label="<?php esc_attr_e( 'Status', 'absensi-sekolah' ); ?>">
                    <span class="badge" :class="statusBadge(r.status)" x-text="statusLabel(r.status)"></span>
                  </td>
                  <td data-label="<?php esc_attr_e( 'Masuk', 'absensi-sekolah' ); ?>"><span class="u-num" x-text="jamHM(r.waktu_masuk)"></span></td>
                  <td data-label="<?php esc_attr_e( 'Keluar', 'absensi-sekolah' ); ?>"><span class="u-num" x-text="jamHM(r.waktu_keluar)"></span></td>
                  <td data-label="<?php esc_attr_e( 'Metode', 'absensi-sekolah' ); ?>">
                    <span class="u-muted" x-text="(r.metode_masuk || '—') + (r.metode_keluar ? ' / ' + r.metode_keluar : '')"></span>
                  </td>
                  <td data-label="<?php esc_attr_e( 'Jarak', 'absensi-sekolah' ); ?>">
                    <span class="u-num" x-text="(r.jarak_meter != null && r.jarak_meter !== '') ? (r.jarak_meter + ' m') : '—'"></span>
                  </td>
                  <td data-label="<?php esc_attr_e( 'Bukti', 'absensi-sekolah' ); ?>">
                    <template x-if="r.bukti_url">
                      <a :href="r.bukti_url" target="_blank" rel="noopener"><?php esc_html_e( 'Lihat', 'absensi-sekolah' ); ?></a>
                    </template>
                    <span x-show="! r.bukti_url" class="u-muted">—</span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination (server-side) -->
        <div x-show="totalPage > 1" x-cloak class="pagination">
          <span class="pagination__info">
            <?php esc_html_e( 'Menampilkan', 'absensi-sekolah' ); ?>
            <span class="u-num" x-text="pageStart"></span>–<span class="u-num" x-text="pageEnd"></span>
            <?php esc_html_e( 'dari', 'absensi-sekolah' ); ?> <span class="u-num" x-text="total"></span>
          </span>
          <div class="pagination__pages">
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

      <?php // State lengkap (skeleton/empty/error/responsive) = item berikutnya. ?>

    </div>
  </div>
</div>
