<?php
/**
 * Admin view — Group.  (design.md §6)
 *
 * Kelola kelompok absen (kelas/guru/staff). Konten DI DALAM wp-admin: bungkus
 * `.absensi-app`, tanpa sidebar/topbar plugin (design.md §3). Endpoint: /group CRUD.
 *
 * Dibangun bertahap per item TODO-FE. Item ini = header halaman (judul + aksi).
 * Tabel, modal form, hapus, state = item berikutnya.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="groupManager">
    <div class="absensi-page">

      <!-- Header: judul + subjudul (kiri) · Buat Grup (kanan) -->
      <div class="absensi-page__head">
        <div class="page-title-wrap">
          <h1 class="absensi-page__title"><?php esc_html_e( 'Group Management', 'absensi-sekolah' ); ?></h1>
          <p class="page-subtitle"><?php esc_html_e( 'Atur pengguna ke dalam kelas, departemen, dan peran.', 'absensi-sekolah' ); ?></p>
        </div>
        <div class="absensi-page__actions">
          <button type="button" class="btn btn--primary" @click="openCreate()">
            <span x-html="$icon( 'plus', 18 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Buat Grup', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>

      <!-- Layout: ringkasan (kiri) + daftar group (kanan) -->
      <div class="group-layout">

        <!-- Kolom kiri: Total Grup + Distribusi (dihitung dari daftar group) -->
        <aside class="group-side">
          <div class="card side-card">
            <p class="side-card__eyebrow"><?php esc_html_e( 'Total Grup', 'absensi-sekolah' ); ?></p>
            <span x-show="loading" class="skeleton skeleton--text" style="width:56px;height:32px;"></span>
            <p x-show="! loading" class="side-card__value u-num" x-text="error ? '–' : totalGroup"></p>
            <p class="side-card__foot" x-show="! loading && ! error" x-cloak>
              <span x-html="$icon( 'users', 14 )" aria-hidden="true"></span>
              <span class="u-num" x-text="totalMember"></span> <?php esc_html_e( 'user terdaftar', 'absensi-sekolah' ); ?>
            </p>
          </div>

          <div class="card side-card">
            <p class="side-card__eyebrow"><?php esc_html_e( 'Distribusi', 'absensi-sekolah' ); ?></p>
            <div x-show="loading" x-cloak>
              <template x-for="n in 3" :key="n">
                <span class="skeleton skeleton--text" style="width:100%;height:14px;"></span>
              </template>
            </div>
            <div x-show="! loading && ! error && distribusi.length === 0" x-cloak class="side-card__foot">
              <?php esc_html_e( 'Belum ada group.', 'absensi-sekolah' ); ?>
            </div>
            <ul class="dist" x-show="! loading && ! error && distribusi.length > 0" x-cloak>
              <template x-for="d in distribusi" :key="d.tipe">
                <li class="dist__row">
                  <div class="dist__head">
                    <span class="dist__label" x-text="d.label"></span>
                    <span class="dist__val u-num" x-text="d.jumlah"></span>
                  </div>
                  <div class="dist__track">
                    <div class="dist__bar" :class="'dist__bar--' + d.tipe" :style="'width:' + d.pct + '%'"></div>
                  </div>
                </li>
              </template>
            </ul>
          </div>
        </aside>

      <!-- Kolom kanan: tab tipe + search + tabel -->
      <div class="table-card">

        <!-- Toolbar kartu: tab tipe (kiri) + cari group (kanan) -->
        <div class="group-bar">
          <div class="pill-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter tipe', 'absensi-sekolah' ); ?>">
            <template x-for="t in tipeTabs" :key="t.tipe">
              <button type="button" class="pill" role="tab" :class="tipeFilter === t.tipe ? 'is-active' : ''"
                      :aria-selected="tipeFilter === t.tipe" @click="pilihTipe(t.tipe)" x-text="t.label"></button>
            </template>
          </div>
          <div class="input-group group-bar__search">
            <span class="input-group__icon" x-html="$icon( 'search', 16 )" aria-hidden="true"></span>
            <input type="search" class="input input--sm" x-model.trim="search" @input.debounce.300ms="page = 1"
                   placeholder="<?php esc_attr_e( 'Cari grup…', 'absensi-sekolah' ); ?>"
                   aria-label="<?php esc_attr_e( 'Cari grup', 'absensi-sekolah' ); ?>">
          </div>
        </div>


        <!-- Skeleton loading -->
        <div x-show="loading" x-cloak class="table-scroll" aria-hidden="true">
          <table class="table group-table">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Nama Group', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Tipe', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Jumlah User', 'absensi-sekolah' ); ?></th>
                <th class="col-actions"><?php esc_html_e( 'Aksi', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="n in 6" :key="n">
                <tr>
                  <td><span class="skeleton skeleton--text" style="width:140px"></span></td>
                  <td><span class="skeleton skeleton--text" style="width:56px"></span></td>
                  <td><span class="skeleton skeleton--text" style="width:40px"></span></td>
                  <td class="col-actions"><span class="skeleton skeleton--text" style="width:56px;margin-left:auto"></span></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Error state + Coba Lagi -->
        <div x-show="! loading && error" x-cloak class="error-state">
          <div class="error-state__icon" x-html="$icon( 'alert-triangle', 32 )" aria-hidden="true"></div>
          <p class="error-state__title"><?php esc_html_e( 'Gagal memuat data', 'absensi-sekolah' ); ?></p>
          <p class="error-state__desc"><?php esc_html_e( 'Tidak dapat mengambil daftar group. Periksa koneksi lalu coba lagi.', 'absensi-sekolah' ); ?></p>
          <button type="button" class="btn btn--outline" @click="loadGroups()">
            <span x-html="$icon( 'refresh-cw', 16 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?>
          </button>
        </div>

        <!-- Tabel (ada data) -->
        <div x-show="! loading && ! error && totalFiltered > 0" x-cloak class="table-scroll">
          <table class="table group-table">
            <thead>
              <tr>
                <th><?php esc_html_e( 'Group Name', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Tipe', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Members', 'absensi-sekolah' ); ?></th>
                <th class="col-actions"><?php esc_html_e( 'Actions', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <!-- Baris group: klik (baris / angka Members) → modal Anggota Grup.
                   Dulu expand inline — diganti karena satu kelas bisa puluhan murid. -->
              <template x-for="g in pagedGroups" :key="g.id">
                <tr class="group-row" @click="openAnggota(g)" role="button" tabindex="0"
                    @keydown.enter.prevent="openAnggota(g)" @keydown.space.prevent="openAnggota(g)"
                    :aria-label="'<?php echo esc_js( __( 'Lihat anggota grup', 'absensi-sekolah' ) ); ?> ' + g.nama">
                  <!-- Nama -->
                  <td data-label="<?php esc_attr_e( 'Group Name', 'absensi-sekolah' ); ?>">
                    <span class="group-row__name" x-text="g.nama"></span>
                  </td>
                  <!-- Tipe (badge) -->
                  <td data-label="<?php esc_attr_e( 'Tipe', 'absensi-sekolah' ); ?>">
                    <span class="badge" :class="tipeBadge(g.tipe)" x-text="tipeLabel(g.tipe)"></span>
                  </td>
                  <!-- Members: tombol → modal anggota -->
                  <td data-label="<?php esc_attr_e( 'Members', 'absensi-sekolah' ); ?>">
                    <button type="button" class="member-count" @click.stop="openAnggota(g)"
                            :aria-label="'<?php echo esc_js( __( 'Lihat anggota grup', 'absensi-sekolah' ) ); ?> ' + g.nama">
                      <span class="member-count__icon" x-html="$icon( 'users', 15 )" aria-hidden="true"></span>
                      <span class="u-num" x-text="g.jumlah_user || 0"></span>
                    </button>
                  </td>
                  <!-- Actions: edit / hapus — ikon langsung, sama dengan halaman Users.
                       @click.stop → klik ikon tak ikut membuka modal anggota. -->
                  <td class="col-actions" data-label="<?php esc_attr_e( 'Actions', 'absensi-sekolah' ); ?>">
                    <div class="row-actions-ic">
                      <button type="button" class="act-ic" @click.stop="openEdit(g)"
                              :aria-label="'<?php echo esc_js( __( 'Edit grup', 'absensi-sekolah' ) ); ?> ' + g.nama"
                              :title="'<?php echo esc_js( __( 'Edit grup', 'absensi-sekolah' ) ); ?>'">
                        <span x-html="$icon( 'square-pen', 16 )"></span>
                      </button>
                      <button type="button" class="act-ic act-ic--danger" @click.stop="confirmDelete(g)"
                              :aria-label="'<?php echo esc_js( __( 'Hapus grup', 'absensi-sekolah' ) ); ?> ' + g.nama"
                              :title="'<?php echo esc_js( __( 'Hapus grup', 'absensi-sekolah' ) ); ?>'">
                        <span x-html="$icon( 'trash-2', 16 )"></span>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Empty: belum ada group SAMA SEKALI vs hasil filter kosong -->
        <div x-show="! loading && ! error && totalFiltered === 0" x-cloak class="empty">
          <div class="empty__icon" x-html="$icon( 'layers', 32 )" aria-hidden="true"></div>
          <p class="empty__title" x-text="groups.length === 0
            ? '<?php echo esc_js( __( 'Belum ada grup', 'absensi-sekolah' ) ); ?>'
            : '<?php echo esc_js( __( 'Tak ada grup cocok', 'absensi-sekolah' ) ); ?>'"></p>
          <p class="empty__desc" x-text="groups.length === 0
            ? '<?php echo esc_js( __( 'Buat grup pertama (kelas/guru/staff) untuk mengelompokkan user.', 'absensi-sekolah' ) ); ?>'
            : '<?php echo esc_js( __( 'Ubah kata kunci atau pilih tipe lain.', 'absensi-sekolah' ) ); ?>'"></p>
          <button type="button" class="btn btn--primary" x-show="groups.length === 0" @click="openCreate()">
            <span x-html="$icon( 'plus', 16 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Buat Grup', 'absensi-sekolah' ); ?>
          </button>
        </div>

        <!-- Footer: info jumlah + pagination (bila >1 halaman) -->
        <div x-show="! loading && ! error && totalFiltered > 0" x-cloak class="table-foot">
          <span class="table-foot__info">
            <?php esc_html_e( 'Menampilkan', 'absensi-sekolah' ); ?>
            <strong class="u-num" x-text="pageStart"></strong>–<strong class="u-num" x-text="pageEnd"></strong>
            <?php esc_html_e( 'dari', 'absensi-sekolah' ); ?>
            <strong class="u-num" x-text="totalFiltered"></strong> <?php esc_html_e( 'grup', 'absensi-sekolah' ); ?>
          </span>
          <div class="pagination__pages" x-show="totalPages > 1">
            <button type="button" class="page-btn" :disabled="page <= 1" @click="goPage(page - 1)"
                    aria-label="<?php esc_attr_e( 'Halaman sebelumnya', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'chevron-left', 16 )"></span>
            </button>
            <template x-for="(p, i) in pageWindow" :key="i">
              <button type="button" class="page-btn" :class="p === page ? 'is-active' : ''"
                      :disabled="p === '…'" @click="goPage(p)" x-text="p"></button>
            </template>
            <button type="button" class="page-btn" :disabled="page >= totalPages" @click="goPage(page + 1)"
                    aria-label="<?php esc_attr_e( 'Halaman berikutnya', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'chevron-right', 16 )"></span>
            </button>
          </div>
        </div>
      </div><!-- /.table-card -->
      </div><!-- /.group-layout -->

      <!-- Modal Anggota Grup: daftar user di grup (GET /users?group_id) + cari + pagination.
           Pengganti expand inline — kelas bisa berisi puluhan murid. -->
      <div x-show="anggotaOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeAnggota()" @click.self="closeAnggota()">
        <div class="modal modal--lg modal--plain" role="dialog" aria-modal="true" aria-labelledby="ga-title">
          <div class="modal__head">
            <div>
              <h2 class="modal__title" id="ga-title"
                  x-text="'<?php echo esc_js( __( 'Anggota —', 'absensi-sekolah' ) ); ?> ' + (anggotaGroup ? anggotaGroup.nama : '')"></h2>
              <p class="card__sub">
                <span class="u-num" x-text="anggotaGroup ? (anggotaGroup.jumlah_user || 0) : 0"></span>
                <?php esc_html_e( 'anggota terdaftar', 'absensi-sekolah' ); ?>
              </p>
            </div>
            <button type="button" class="modal__close" @click="closeAnggota()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>

          <div class="modal__body">
            <!-- Cari anggota (client) -->
            <div class="input-group" x-show="anggotaSemua.length > 0">
              <span class="input-group__icon" x-html="$icon( 'search', 16 )" aria-hidden="true"></span>
              <input type="search" class="input" x-model.trim="anggotaSearch" @input="anggotaPage = 1"
                     placeholder="<?php esc_attr_e( 'Cari nama atau nomor induk…', 'absensi-sekolah' ); ?>"
                     aria-label="<?php esc_attr_e( 'Cari anggota', 'absensi-sekolah' ); ?>">
            </div>

            <!-- Loading -->
            <div x-show="anggotaLoading" x-cloak>
              <template x-for="n in 4" :key="n">
                <div class="skeleton skeleton--row"></div>
              </template>
            </div>

            <!-- Error -->
            <div x-show="! anggotaLoading && anggotaError" x-cloak class="alert alert--danger">
              <span class="alert__icon" x-html="$icon( 'alert-circle', 18 )" aria-hidden="true"></span>
              <span style="flex:1;"><?php esc_html_e( 'Gagal memuat anggota.', 'absensi-sekolah' ); ?></span>
              <button type="button" class="btn btn--outline btn--sm" @click="loadGroupUsers(anggotaId)">
                <?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?>
              </button>
            </div>

            <!-- Kosong -->
            <div x-show="! anggotaLoading && ! anggotaError && anggotaTotal === 0" x-cloak class="empty" style="padding:28px 16px;">
              <div class="empty__icon" x-html="$icon( 'users', 28 )" aria-hidden="true"></div>
              <p class="empty__desc" x-text="anggotaSemua.length === 0
                ? '<?php echo esc_js( __( 'Belum ada anggota di grup ini.', 'absensi-sekolah' ) ); ?>'
                : '<?php echo esc_js( __( 'Tak ada anggota cocok dengan pencarian.', 'absensi-sekolah' ) ); ?>'"></p>
            </div>

            <!-- Daftar anggota (halaman aktif) -->
            <ul class="anggota-list" x-show="! anggotaLoading && ! anggotaError && anggotaTotal > 0" x-cloak>
              <template x-for="u in anggotaPaged" :key="u.id">
                <li class="anggota-item">
                  <span class="table__avatar" :class="avatarTone(u.nama)" x-text="inisial(u.nama)" aria-hidden="true"></span>
                  <span class="anggota-item__main">
                    <span class="anggota-item__name" x-text="u.nama"></span>
                    <span class="anggota-item__nis u-num" x-text="u.nomor_induk"></span>
                  </span>
                  <!-- UID penuh dalam chip abu — sama persis dengan kolom RFID di halaman Users -->
                  <span class="rfid-chip-uid" x-show="u.rfid_uid" x-text="u.rfid_uid"></span>
                  <span class="rfid-empty" x-show="! u.rfid_uid" aria-label="<?php esc_attr_e( 'Belum ada kartu', 'absensi-sekolah' ); ?>">–</span>
                </li>
              </template>
            </ul>

            <!-- Footer daftar: info + pagination -->
            <div class="anggota-foot" x-show="! anggotaLoading && ! anggotaError && anggotaTotal > 0" x-cloak>
              <span class="table-foot__info">
                <?php esc_html_e( 'Menampilkan', 'absensi-sekolah' ); ?>
                <strong class="u-num" x-text="anggotaStart"></strong>–<strong class="u-num" x-text="anggotaEnd"></strong>
                <?php esc_html_e( 'dari', 'absensi-sekolah' ); ?>
                <strong class="u-num" x-text="anggotaTotal"></strong>
              </span>
              <div class="pagination__pages" x-show="anggotaTotalPages > 1">
                <button type="button" class="page-btn" :disabled="anggotaPage <= 1" @click="goAnggotaPage(anggotaPage - 1)"
                        aria-label="<?php esc_attr_e( 'Halaman sebelumnya', 'absensi-sekolah' ); ?>">
                  <span x-html="$icon( 'chevron-left', 16 )"></span>
                </button>
                <span class="pagination__info">
                  <span class="u-num" x-text="Math.min(anggotaPage, anggotaTotalPages)"></span> /
                  <span class="u-num" x-text="anggotaTotalPages"></span>
                </span>
                <button type="button" class="page-btn" :disabled="anggotaPage >= anggotaTotalPages" @click="goAnggotaPage(anggotaPage + 1)"
                        aria-label="<?php esc_attr_e( 'Halaman berikutnya', 'absensi-sekolah' ); ?>">
                  <span x-html="$icon( 'chevron-right', 16 )"></span>
                </button>
              </div>
            </div>
          </div>

          <div class="modal__footer">
            <button type="button" class="btn btn--outline" @click="closeAnggota()"><?php esc_html_e( 'Tutup', 'absensi-sekolah' ); ?></button>
          </div>
        </div>
      </div>

      <!-- Modal Form (tambah/edit group) — design.md §6 -->
      <div x-show="modalOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeModal()" @click.self="closeModal()">
        <div class="modal modal--plain" role="dialog" aria-modal="true" aria-labelledby="gf-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <h2 class="modal__title" id="gf-title"
                x-text="editing
                  ? '<?php echo esc_js( __( 'Edit Grup', 'absensi-sekolah' ) ); ?>'
                  : '<?php echo esc_js( __( 'Buat Grup Baru', 'absensi-sekolah' ) ); ?>'"></h2>
            <button type="button" class="modal__close" @click="closeModal()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>

          <form @submit.prevent="save()">
            <div class="modal__body">
              <!-- Error tingkat form -->
              <div x-show="formError" x-cloak class="alert alert--danger">
                <span class="alert__icon" x-html="$icon( 'alert-circle', 18 )" aria-hidden="true"></span>
                <span x-text="formError"></span>
              </div>

              <!-- Nama Grup (wajib, ≤100) -->
              <div class="field">
                <label class="field__label" for="gf-nama"><?php esc_html_e( 'Nama Grup', 'absensi-sekolah' ); ?></label>
                <input id="gf-nama" type="text" class="input" :class="fieldErr.nama ? 'input--error' : ''"
                       x-model.trim="form.nama" maxlength="100" required @input="fieldErr.nama = false"
                       placeholder="<?php esc_attr_e( 'Contoh: XII MIPA 1', 'absensi-sekolah' ); ?>">
              </div>

              <!-- Tipe: 3 bawaan + "Lainnya" (BE simpan VARCHAR bebas sejak v2.1.0,
                   jadi tipe kustom tetap bisa dibuat lewat opsi Lainnya) -->
              <div class="field">
                <label class="field__label" for="gf-tipe"><?php esc_html_e( 'Tipe', 'absensi-sekolah' ); ?></label>
                <select id="gf-tipe" class="select" @change="gantiTipePilihan($event.target.value)">
                  <option value="kelas" :selected="tipePilihan === 'kelas'"><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></option>
                  <option value="guru" :selected="tipePilihan === 'guru'"><?php esc_html_e( 'Guru', 'absensi-sekolah' ); ?></option>
                  <option value="staff" :selected="tipePilihan === 'staff'"><?php esc_html_e( 'Staff', 'absensi-sekolah' ); ?></option>
                  <option value="lainnya" :selected="tipePilihan === 'lainnya'"><?php esc_html_e( 'Lainnya…', 'absensi-sekolah' ); ?></option>
                </select>
              </div>

              <!-- Tipe kustom (muncul hanya bila pilih "Lainnya") -->
              <div class="field" x-show="tipePilihan === 'lainnya'" x-cloak>
                <label class="field__label" for="gf-tipe-lain"><?php esc_html_e( 'Nama Tipe', 'absensi-sekolah' ); ?></label>
                <input id="gf-tipe-lain" type="text" class="input" x-model.trim="form.tipe" maxlength="50"
                       placeholder="<?php esc_attr_e( 'Contoh: Ekstrakurikuler', 'absensi-sekolah' ); ?>">
              </div>
            </div>

            <div class="modal__footer">
              <button type="button" class="btn btn--outline" @click="closeModal()"><?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?></button>
              <button type="submit" class="btn btn--primary" :class="saving ? 'is-loading' : ''" :disabled="! canSave">
                <span class="btn__spin" x-show="saving" x-cloak aria-hidden="true"></span>
                <span class="btn__label"><?php esc_html_e( 'Simpan', 'absensi-sekolah' ); ?></span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Konfirmasi Hapus (design.md §6) → DELETE /group/{id} (409 group_ada_user) -->
      <div x-show="delOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeDelete()" @click.self="closeDelete()">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="gdel-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <div class="modal__head-ic">
              <span class="card-chip card-chip--danger" x-html="$icon( 'trash-2', 18 )" aria-hidden="true"></span>
              <h2 class="modal__title" id="gdel-title"><?php esc_html_e( 'Hapus Group', 'absensi-sekolah' ); ?></h2>
            </div>
            <button type="button" class="modal__close" @click="closeDelete()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>
          <div class="modal__body">
            <!-- Proaktif: group masih punya user → cegah hapus -->
            <div x-show="delHasUsers" x-cloak class="alert alert--warning">
              <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
              <span>
                <?php esc_html_e( 'Group masih memiliki', 'absensi-sekolah' ); ?>
                <strong><span class="u-num" x-text="delGroup ? delGroup.jumlah_user : 0"></span> <?php esc_html_e( 'user', 'absensi-sekolah' ); ?></strong>.
                <?php esc_html_e( 'Pindahkan user ke group lain dulu sebelum menghapus.', 'absensi-sekolah' ); ?>
              </span>
            </div>

            <!-- Konfirmasi normal (tanpa user) -->
            <div x-show="! delHasUsers" x-cloak class="alert alert--danger">
              <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
              <span>
                <?php esc_html_e( 'Yakin hapus', 'absensi-sekolah' ); ?>
                <strong x-text="delGroup ? delGroup.nama : ''"></strong>?
                <?php esc_html_e( 'Tindakan ini tidak bisa dibatalkan.', 'absensi-sekolah' ); ?>
              </span>
            </div>

            <!-- Error server (mis. 409 jika terjadi race) -->
            <div x-show="delError" x-cloak class="alert alert--danger">
              <span class="alert__icon" x-html="$icon( 'alert-circle', 18 )" aria-hidden="true"></span>
              <span x-text="delError"></span>
            </div>
          </div>
          <div class="modal__footer">
            <button type="button" class="btn btn--outline" @click="closeDelete()"><?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?></button>
            <button type="button" class="btn btn--danger" @click="runDelete()"
                    :class="deleting ? 'is-loading' : ''" :disabled="deleting || delHasUsers"
                    :title="delHasUsers ? '<?php echo esc_js( __( 'Pindahkan user dulu', 'absensi-sekolah' ) ); ?>' : ''">
              <span class="btn__spin" x-show="deleting" x-cloak aria-hidden="true"></span>
              <span class="btn__label"><?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?></span>
            </button>
          </div>
        </div>
      </div>

      <?php // State lengkap (skeleton/error/empty/responsive) = item berikutnya. ?>

    </div>
  </div>
</div>
