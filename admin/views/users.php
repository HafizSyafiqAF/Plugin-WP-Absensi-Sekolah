<?php
/**
 * Admin view — Users.  (design.md §5)
 *
 * Kelola master orang yang diabsen (siswa/guru/staff, satu tabel — dibedakan via
 * tipe group). Konten DI DALAM wp-admin: bungkus `.absensi-app`, tanpa sidebar/topbar
 * plugin (design.md §3). Endpoint: /users CRUD, /users/{id}/rfid, /users/import.
 *
 * Dibangun bertahap per item TODO-FE. Item ini = header halaman (judul + aksi).
 * Filter bar, tabel, modal (form/bind/import), hapus, state = item berikutnya.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="usersManager">
    <div class="absensi-page">

      <!-- Header: judul + subjudul (kiri) · Add Bulk User + Tambah User (kanan) -->
      <div class="absensi-page__head">
        <div class="page-title-wrap">
          <h1 class="absensi-page__title"><?php esc_html_e( 'Users', 'absensi-sekolah' ); ?></h1>
          <p class="page-subtitle"><?php esc_html_e( 'Kelola siswa, guru, dan staf sekolah.', 'absensi-sekolah' ); ?></p>
        </div>
        <div class="absensi-page__actions">
          <!-- Export: dua tujuan (data user / akun guru) — unduh Excel. Endpoint stream file
               butuh nonce → ditangani fetch blob di exportUsers()/exportGuru(). -->
          <div class="dropdown" @click.outside="exportMenuOpen = false">
            <button type="button" class="btn btn--outline" @click="exportMenuOpen = ! exportMenuOpen"
                    :aria-expanded="exportMenuOpen ? 'true' : 'false'" aria-haspopup="true">
              <span x-html="$icon( 'download', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Export', 'absensi-sekolah' ); ?>
              <span x-html="$icon( 'chevron-down', 16 )" aria-hidden="true"></span>
            </button>
            <div class="dropdown__menu" x-show="exportMenuOpen" x-cloak>
              <button type="button" class="dropdown__item" @click="exportMenuOpen = false; exportUsers()">
                <span x-html="$icon( 'file-spreadsheet', 16 )" aria-hidden="true"></span>
                <?php esc_html_e( 'Data User', 'absensi-sekolah' ); ?>
              </button>
              <button type="button" class="dropdown__item" @click="exportMenuOpen = false; exportGuru()">
                <span x-html="$icon( 'user-plus', 16 )" aria-hidden="true"></span>
                <?php esc_html_e( 'Akun Guru', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </div>
          <!-- Add Bulk User: satu tombol, dua tujuan (data user / akun guru) — endpoint beda
               (/users/import vs /guru/import) jadi tak bisa disatukan jadi satu aksi. -->
          <div class="dropdown" @click.outside="importMenuOpen = false">
            <button type="button" class="btn btn--outline" @click="importMenuOpen = ! importMenuOpen"
                    :aria-expanded="importMenuOpen ? 'true' : 'false'" aria-haspopup="true">
              <span x-html="$icon( 'upload', 18 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Add Bulk User', 'absensi-sekolah' ); ?>
              <span x-html="$icon( 'chevron-down', 16 )" aria-hidden="true"></span>
            </button>
            <div class="dropdown__menu" x-show="importMenuOpen" x-cloak>
              <button type="button" class="dropdown__item" @click="importMenuOpen = false; openImport()">
                <span x-html="$icon( 'file-spreadsheet', 16 )" aria-hidden="true"></span>
                <?php esc_html_e( 'Data User', 'absensi-sekolah' ); ?>
              </button>
              <button type="button" class="dropdown__item" @click="importMenuOpen = false; openImportGuru()">
                <span x-html="$icon( 'user-plus', 16 )" aria-hidden="true"></span>
                <?php esc_html_e( 'Akun Guru', 'absensi-sekolah' ); ?>
              </button>
            </div>
          </div>
          <button type="button" class="btn btn--primary" @click="openCreate()">
            <span x-html="$icon( 'plus', 18 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Tambah User', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>

      <!-- Toolbar: Search (client, debounce) · Select Grup (server) · Filter Tipe -->
      <div class="toolbar">
        <div class="input-group toolbar__search">
          <span class="input-group__icon" x-html="$icon( 'search', 18 )" aria-hidden="true"></span>
          <input type="search" class="input" x-model.trim="search"
                 @input.debounce.300ms="page = 1"
                 placeholder="<?php esc_attr_e( 'Cari NIS/NIP atau nama…', 'absensi-sekolah' ); ?>"
                 aria-label="<?php esc_attr_e( 'Cari user', 'absensi-sekolah' ); ?>">
          <button type="button" class="input-group__clear" x-show="search" x-cloak
                  @click="search = ''; page = 1"
                  aria-label="<?php esc_attr_e( 'Bersihkan pencarian', 'absensi-sekolah' ); ?>">
            <span x-html="$icon( 'x', 16 )"></span>
          </button>
        </div>

        <!-- Grup: opsinya ikut menyempit sesuai filter Tipe (groupsForFilter). Nama grup boleh kembar
             ("5B" tipe Siswa DAN "5B" tipe Guru) → dibedakan lewat penyempitan Tipe, BUKAN dengan
             menempelkan tipe ke label (label sengaja nama polos). -->
        <select class="select" x-model="groupId" @change="page = 1; loadUsers()"
                aria-label="<?php esc_attr_e( 'Filter grup', 'absensi-sekolah' ); ?>">
          <option value=""><?php esc_html_e( 'Semua Grup', 'absensi-sekolah' ); ?></option>
          <template x-for="g in groupsForFilter" :key="g.id">
            <option :value="g.id" x-text="g.nama"></option>
          </template>
        </select>

        <!-- Tipe = milik GROUP (absensi_group.tipe), bukan kolom di absensi_users. Opsi dibangun
             dari data termuat (tipe = string bebas) → tipe kustom ikut muncul tanpa ubah kode.
             Penyaringan client-side: seluruh user memang sudah ada di browser. -->
        <select class="select" x-model="tipeFilter" @change="gantiTipeFilter()"
                aria-label="<?php esc_attr_e( 'Filter tipe', 'absensi-sekolah' ); ?>">
          <option value=""><?php esc_html_e( 'Semua Tipe', 'absensi-sekolah' ); ?></option>
          <template x-for="t in tipeOptions" :key="t.value">
            <option :value="t.value" x-text="t.label"></option>
          </template>
        </select>

        <button type="button" class="btn btn--ghost btn--sm" x-show="search || groupId || tipeFilter" x-cloak
                @click="resetFilter()">
          <span x-html="$icon( 'rotate-ccw', 16 )" aria-hidden="true"></span>
          <?php esc_html_e( 'Reset', 'absensi-sekolah' ); ?>
        </button>
      </div>

      <!-- Bulk bar: muncul saat ada baris tercentang. Aksi massal (kini: hapus). -->
      <div class="bulkbar" x-show="selectedCount > 0" x-cloak role="region"
           aria-label="<?php esc_attr_e( 'Aksi untuk user terpilih', 'absensi-sekolah' ); ?>">
        <span class="bulkbar__info">
          <strong class="u-num" x-text="selectedCount"></strong>
          <?php esc_html_e( 'user dipilih', 'absensi-sekolah' ); ?>
        </span>
        <!-- Centang header hanya mengenai halaman aktif → sediakan jalan pintas ke seluruh hasil filter. -->
        <button type="button" class="btn btn--ghost btn--sm"
                x-show="selectedCount < totalFiltered" @click="selectAllFiltered()">
          <?php esc_html_e( 'Pilih semua', 'absensi-sekolah' ); ?>
          <strong class="u-num" x-text="totalFiltered"></strong>
        </button>
        <button type="button" class="btn btn--ghost btn--sm" @click="clearSelection()">
          <?php esc_html_e( 'Batal pilih', 'absensi-sekolah' ); ?>
        </button>
        <button type="button" class="btn btn--danger btn--sm bulkbar__act" @click="confirmBulkDelete()">
          <span x-html="$icon( 'trash-2', 16 )" aria-hidden="true"></span>
          <?php esc_html_e( 'Hapus terpilih', 'absensi-sekolah' ); ?>
        </button>
      </div>

      <!-- Tabel Users (design.md §5): skeleton · error · tabel · empty · pagination -->
      <div class="table-card">

        <!-- Skeleton loading (10 baris: avatar bulat + balok teks) -->
        <div x-show="loading" x-cloak class="table-scroll" aria-hidden="true">
          <table class="table users-table">
            <thead>
              <tr>
                <th class="col-check"></th>
                <th><?php esc_html_e( 'User', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Group', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Tipe', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'RFID', 'absensi-sekolah' ); ?></th>
                <th class="col-actions"><?php esc_html_e( 'Aksi', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="n in 8" :key="n">
                <tr>
                  <td class="col-check"><span class="skeleton" style="width:16px;height:16px;border-radius:4px"></span></td>
                  <td><div class="table__user"><span class="skeleton" style="width:32px;height:32px;border-radius:50%"></span><span class="skeleton skeleton--text" style="width:120px"></span></div></td>
                  <td><span class="skeleton skeleton--text" style="width:80px"></span></td>
                  <td><span class="skeleton skeleton--text" style="width:56px"></span></td>
                  <td><span class="skeleton skeleton--text" style="width:70px"></span></td>
                  <td class="col-actions"><span class="skeleton skeleton--text" style="width:76px;margin-left:auto"></span></td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Error state + Coba Lagi -->
        <div x-show="! loading && error" x-cloak class="error-state">
          <div class="error-state__icon" x-html="$icon( 'alert-triangle', 32 )" aria-hidden="true"></div>
          <p class="error-state__title"><?php esc_html_e( 'Gagal memuat data', 'absensi-sekolah' ); ?></p>
          <p class="error-state__desc"><?php esc_html_e( 'Tidak dapat mengambil daftar user. Periksa koneksi lalu coba lagi.', 'absensi-sekolah' ); ?></p>
          <button type="button" class="btn btn--outline" @click="loadUsers()">
            <span x-html="$icon( 'refresh-cw', 16 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Coba Lagi', 'absensi-sekolah' ); ?>
          </button>
        </div>

        <!-- Tabel (ada data) -->
        <div x-show="! loading && ! error && totalFiltered > 0" x-cloak class="table-scroll">
          <table class="table users-table">
            <thead>
              <tr>
                <!-- Centang semua = HALAMAN AKTIF saja (indeterminate bila sebagian). Seluruh hasil
                     filter dicentang lewat tombol "Pilih semua N" di bulk bar. -->
                <th class="col-check">
                  <input type="checkbox" class="check" :checked="allPageSelected"
                         :indeterminate="somePageSelected" @change="toggleSelectPage()"
                         aria-label="<?php esc_attr_e( 'Pilih semua user di halaman ini', 'absensi-sekolah' ); ?>">
                </th>
                <th><?php esc_html_e( 'User', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Group', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'Tipe', 'absensi-sekolah' ); ?></th>
                <th><?php esc_html_e( 'RFID', 'absensi-sekolah' ); ?></th>
                <th class="col-actions"><?php esc_html_e( 'Actions', 'absensi-sekolah' ); ?></th>
              </tr>
            </thead>
            <tbody>
              <template x-for="u in pagedUsers" :key="u.id">
                <tr :class="isSelected(u.id) ? 'is-selected' : ''">
                  <!-- Checklist baris -->
                  <td class="col-check" data-label="">
                    <input type="checkbox" class="check" :checked="isSelected(u.id)"
                           @change="toggleSelect(u.id)"
                           :aria-label="'<?php echo esc_js( __( 'Pilih', 'absensi-sekolah' ) ); ?> ' + u.nama">
                  </td>
                  <!-- User: avatar (warna deterministik dari nama) + nama + NIS/NIP -->
                  <td data-label="<?php esc_attr_e( 'User', 'absensi-sekolah' ); ?>">
                    <div class="table__user">
                      <span class="table__avatar" :class="avatarTone(u.nama)" x-text="inisial(u.nama)" aria-hidden="true"></span>
                      <div>
                        <div class="table__user-name" x-text="u.nama"></div>
                        <div class="table__user-id">
                          <span x-text="nomorLabel()"></span>:
                          <span class="u-num" x-text="u.nomor_induk"></span>
                        </div>
                      </div>
                    </div>
                  </td>
                  <!-- Group -->
                  <td data-label="<?php esc_attr_e( 'Group', 'absensi-sekolah' ); ?>"><span x-text="u.nama_group || '—'"></span></td>
                  <!-- Tipe (badge) -->
                  <td data-label="<?php esc_attr_e( 'Tipe', 'absensi-sekolah' ); ?>">
                    <span x-show="u.tipe_group" class="badge" :class="tipeBadge(u.tipe_group)" x-text="tipeLabel(u.tipe_group)"></span>
                    <span x-show="! u.tipe_group" class="u-muted">—</span>
                  </td>
                  <!-- RFID: UID ditampilkan PENUH (tanpa mask) sebagai chip; belum ada kartu = "–".
                       Pemasangan kartu tetap lewat menu "…" → Bind RFID. -->
                  <td data-label="<?php esc_attr_e( 'RFID', 'absensi-sekolah' ); ?>">
                    <span x-show="u.rfid_uid" class="rfid-chip-uid" x-text="u.rfid_uid"></span>
                    <span x-show="! u.rfid_uid" class="rfid-empty" aria-label="<?php esc_attr_e( 'Belum ada kartu', 'absensi-sekolah' ); ?>">–</span>
                  </td>
                  <!-- Actions: 3 ikon langsung — bind kartu · edit · hapus -->
                  <td class="col-actions" data-label="<?php esc_attr_e( 'Actions', 'absensi-sekolah' ); ?>">
                    <div class="row-actions-ic">
                      <button type="button" class="act-ic" @click="openBind(u)"
                              :aria-label="'<?php echo esc_js( __( 'Bind kartu RFID untuk', 'absensi-sekolah' ) ); ?> ' + u.nama"
                              :title="'<?php echo esc_js( __( 'Bind kartu RFID', 'absensi-sekolah' ) ); ?>'">
                        <span x-html="$icon( 'credit-card', 16 )"></span>
                      </button>
                      <button type="button" class="act-ic" @click="openEdit(u)"
                              :aria-label="'<?php echo esc_js( __( 'Edit user', 'absensi-sekolah' ) ); ?> ' + u.nama"
                              :title="'<?php echo esc_js( __( 'Edit user', 'absensi-sekolah' ) ); ?>'">
                        <span x-html="$icon( 'square-pen', 16 )"></span>
                      </button>
                      <button type="button" class="act-ic act-ic--danger" @click="confirmDelete(u)"
                              :aria-label="'<?php echo esc_js( __( 'Hapus user', 'absensi-sekolah' ) ); ?> ' + u.nama"
                              :title="'<?php echo esc_js( __( 'Hapus user', 'absensi-sekolah' ) ); ?>'">
                        <span x-html="$icon( 'trash-2', 16 )"></span>
                      </button>
                    </div>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Empty (belum ada user / hasil filter kosong) -->
        <div x-show="! loading && ! error && totalFiltered === 0" x-cloak class="empty">
          <div class="empty__icon" x-html="$icon( 'users', 32 )" aria-hidden="true"></div>
          <p class="empty__title"><?php esc_html_e( 'Belum ada user', 'absensi-sekolah' ); ?></p>
          <p class="empty__desc" x-text="(search || groupId)
            ? '<?php echo esc_js( __( 'Tak ada user cocok dengan filter.', 'absensi-sekolah' ) ); ?>'
            : '<?php echo esc_js( __( 'Tambah user baru atau import dari Excel.', 'absensi-sekolah' ) ); ?>'"></p>
        </div>

        <!-- Footer: info jumlah (selalu tampil bila ada data) + pagination (bila >1 halaman) -->
        <div x-show="! loading && ! error && totalFiltered > 0" x-cloak class="table-foot">
          <span class="table-foot__info">
            <?php esc_html_e( 'Menampilkan', 'absensi-sekolah' ); ?>
            <strong class="u-num" x-text="pageStart"></strong>–<strong class="u-num" x-text="pageEnd"></strong>
            <?php esc_html_e( 'dari', 'absensi-sekolah' ); ?>
            <strong class="u-num" x-text="totalFiltered"></strong> <?php esc_html_e( 'user', 'absensi-sekolah' ); ?>
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
      </div>

      <!-- Modal Form (tambah/edit user) — design.md §5. Focus-trap penuh = item State. -->
      <div x-show="modalOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeModal()" @click.self="closeModal()">
        <div class="modal modal--plain" role="dialog" aria-modal="true" aria-labelledby="uf-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <h2 class="modal__title" id="uf-title"
                x-text="editing
                  ? '<?php echo esc_js( __( 'Edit User', 'absensi-sekolah' ) ); ?>'
                  : '<?php echo esc_js( __( 'Tambah User Baru', 'absensi-sekolah' ) ); ?>'"></h2>
            <button type="button" class="modal__close" @click="closeModal()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>

          <form @submit.prevent="save()">
            <div class="modal__body">
              <!-- Error tingkat form (409 duplikat / lainnya) -->
              <div x-show="formError" x-cloak class="alert alert--danger">
                <span class="alert__icon" x-html="$icon( 'alert-circle', 18 )" aria-hidden="true"></span>
                <span x-text="formError"></span>
              </div>

              <!-- Nama Lengkap (wajib, ≤150) -->
              <div class="field">
                <label class="field__label" for="uf-nama"><?php esc_html_e( 'Nama Lengkap', 'absensi-sekolah' ); ?></label>
                <input id="uf-nama" type="text" class="input" :class="fieldErr.nama ? 'input--error' : ''"
                       x-model.trim="form.nama" maxlength="150" required
                       @input="fieldErr.nama = false"
                       placeholder="<?php esc_attr_e( 'Contoh: Ahmad Santoso', 'absensi-sekolah' ); ?>">
              </div>

              <!-- Nomor Induk (wajib, ≤30) -->
              <div class="field">
                <label class="field__label" for="uf-nomor"><?php esc_html_e( 'Nomor Induk (NIS/NIP)', 'absensi-sekolah' ); ?></label>
                <input id="uf-nomor" type="text" class="input" :class="fieldErr.nomor_induk ? 'input--error' : ''"
                       x-model.trim="form.nomor_induk" maxlength="30" required
                       @input="fieldErr.nomor_induk = false"
                       placeholder="<?php esc_attr_e( 'Contoh: 2023001', 'absensi-sekolah' ); ?>">
              </div>

              <!-- Tipe: BUKAN kolom user — penyaring daftar grup (tipe user ikut grupnya).
                   Pilih tipe DULU → dropdown Grup di bawah cuma menampilkan grup bertipe itu.
                   Dropdown modern (bukan radio) supaya rapi walau tipe banyak. Opsinya dari tipe
                   group yang benar-benar ada di data (tanpa preset). -->
              <div class="field">
                <label class="field__label"><?php esc_html_e( 'Tipe', 'absensi-sekolah' ); ?></label>
                <div class="dd-select" @click.outside="formTipeMenuOpen = false"
                     @keydown.escape.window="formTipeMenuOpen = false">
                  <button type="button" class="dd-select__btn" :class="formTipe ? 'is-filled' : ''"
                          @click="formTipeMenuOpen = ! formTipeMenuOpen"
                          :aria-expanded="formTipeMenuOpen ? 'true' : 'false'" aria-haspopup="listbox"
                          aria-label="<?php esc_attr_e( 'Pilih tipe', 'absensi-sekolah' ); ?>">
                    <span class="dd-select__ic" x-html="$icon( 'filter', 15 )" aria-hidden="true"></span>
                    <span class="dd-select__val" x-text="formTipeLabel"></span>
                    <span class="dd-select__chev" :class="formTipeMenuOpen ? 'is-open' : ''"
                          x-html="$icon( 'chevron-down', 16 )" aria-hidden="true"></span>
                  </button>

                  <div class="dd-select__menu" x-show="formTipeMenuOpen" x-cloak role="listbox"
                       aria-label="<?php esc_attr_e( 'Pilih tipe', 'absensi-sekolah' ); ?>">
                    <button type="button" class="dd-select__opt" role="option"
                            :class="! formTipe ? 'is-active' : ''" :aria-selected="! formTipe"
                            @click="gantiTipe(''); formTipeMenuOpen = false">
                      <span class="dd-select__check" x-html="! formTipe ? $icon( 'check', 15 ) : ''" aria-hidden="true"></span>
                      <span class="dd-select__opt-label"><?php esc_html_e( 'Semua Tipe', 'absensi-sekolah' ); ?></span>
                    </button>
                    <template x-for="t in tipeGroupOptions" :key="t">
                      <button type="button" class="dd-select__opt" role="option"
                              :class="formTipe === t ? 'is-active' : ''" :aria-selected="formTipe === t"
                              @click="gantiTipe(t); formTipeMenuOpen = false">
                        <span class="dd-select__check" x-html="formTipe === t ? $icon( 'check', 15 ) : ''" aria-hidden="true"></span>
                        <span class="dd-select__opt-label" x-text="tipeLabel(t)"></span>
                      </button>
                    </template>
                  </div>
                </div>
                <p class="field__hint" x-show="tipeGroupOptions.length === 0" x-cloak>
                  <?php esc_html_e( 'Belum ada tipe — buat group dulu di menu Group.', 'absensi-sekolah' ); ?>
                </p>
              </div>

              <!-- Grup: opsinya disaring oleh Tipe di atas (groupsByTipe). -->
              <div class="field">
                <label class="field__label" for="uf-group"><?php esc_html_e( 'Grup', 'absensi-sekolah' ); ?></label>
                <select id="uf-group" class="select" x-model="form.group_id">
                  <option value=""><?php esc_html_e( 'Pilih grup…', 'absensi-sekolah' ); ?></option>
                  <template x-for="g in groupsByTipe" :key="g.id">
                    <option :value="g.id" x-text="g.nama"></option>
                  </template>
                </select>
                <p class="field__hint" x-show="groupsByTipe.length === 0" x-cloak>
                  <?php esc_html_e( 'Belum ada grup bertipe ini — buat dulu di halaman Group.', 'absensi-sekolah' ); ?>
                </p>
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

      <!-- Modal Bind RFID (design.md §5). Input UID auto-focus; guard "Ganti" client-side. -->
      <div x-show="bindOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeBind()" @click.self="closeBind()">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bind-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <div class="modal__head-ic">
              <span class="card-chip card-chip--purple" x-html="$icon( 'credit-card', 18 )" aria-hidden="true"></span>
              <h2 class="modal__title" id="bind-title"
                  x-text="'<?php echo esc_js( __( 'Bind Kartu RFID —', 'absensi-sekolah' ) ); ?> ' + (bindUser ? bindUser.nama : '')"></h2>
            </div>
            <button type="button" class="modal__close" @click="closeBind()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>

          <form @submit.prevent="saveBind()">
            <div class="modal__body">
              <!-- Error (409 kartu_terpakai / 422) -->
              <div x-show="bindError" x-cloak class="alert alert--danger">
                <span class="alert__icon" x-html="$icon( 'alert-circle', 18 )" aria-hidden="true"></span>
                <span x-text="bindError"></span>
              </div>

              <!-- Input UID (auto-focus, scanner mengisi) -->
              <div class="field">
                <label class="field__label" for="bind-uid"><?php esc_html_e( 'UID Kartu', 'absensi-sekolah' ); ?></label>
                <div class="input-group">
                  <span class="input-group__icon" x-html="$icon( 'credit-card', 18 )" aria-hidden="true"></span>
                  <input id="bind-uid" x-ref="binduid" type="text" class="input" x-model.trim="bindUid"
                         maxlength="50" autocomplete="off" spellcheck="false"
                         @input="bindError = ''" aria-describedby="bind-hint"
                         placeholder="<?php esc_attr_e( 'Tap kartu…', 'absensi-sekolah' ); ?>">
                </div>
                <p id="bind-hint" class="field__label"><?php esc_html_e( 'Tap kartu pada scanner — UID terisi otomatis.', 'absensi-sekolah' ); ?></p>
              </div>

              <!-- Ganti kartu lama (muncul bila user sudah punya kartu) -->
              <label x-show="bindHasCard" x-cloak class="bind-replace">
                <input type="checkbox" x-model="bindReplace">
                <span>
                  <?php esc_html_e( 'Ganti kartu lama', 'absensi-sekolah' ); ?>
                  (<span class="u-num" x-text="maskRfid(bindUser ? bindUser.rfid_uid : '')"></span>)
                </span>
              </label>
            </div>

            <div class="modal__footer">
              <button type="button" class="btn btn--outline" @click="closeBind()"><?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?></button>
              <button type="submit" class="btn btn--primary" :class="binding ? 'is-loading' : ''" :disabled="! canBind">
                <span class="btn__spin" x-show="binding" x-cloak aria-hidden="true"></span>
                <span class="btn__label"><?php esc_html_e( 'Simpan Kartu', 'absensi-sekolah' ); ?></span>
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Import Excel (design.md §5) → POST /users/import (base64). -->
      <div x-show="importOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeImport()" @click.self="closeImport()">
        <div class="modal modal--lg" role="dialog" aria-modal="true" aria-labelledby="import-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <div class="modal__head-ic">
              <span class="card-chip card-chip--primary" x-html="$icon( 'upload', 18 )" aria-hidden="true"></span>
              <h2 class="modal__title" id="import-title"><?php esc_html_e( 'Import User dari Excel', 'absensi-sekolah' ); ?></h2>
            </div>
            <button type="button" class="modal__close" @click="closeImport()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>

          <div class="modal__body">
            <!-- Cara import (tutorial kontekstual) -->
            <div class="import-help">
              <div class="import-help__title">
                <span x-html="$icon( 'info', 16 )" aria-hidden="true"></span>
                <?php esc_html_e( 'Cara import data user', 'absensi-sekolah' ); ?>
              </div>
              <ol class="import-help__steps">
                <li><?php esc_html_e( 'Unduh template di bawah, buka dengan Excel.', 'absensi-sekolah' ); ?></li>
                <li>
                  <?php esc_html_e( 'Isi kolom — wajib:', 'absensi-sekolah' ); ?> <strong>nama</strong>, <strong>nomor_induk</strong>.
                  <?php esc_html_e( 'Opsional:', 'absensi-sekolah' ); ?> <strong>group</strong>, <strong>tipe</strong>.
                  <?php esc_html_e( 'Group belum ada → dibuat otomatis dengan tipe itu; tanpa tipe, group harus sudah ada. Maks 2000 baris.', 'absensi-sekolah' ); ?>
                  <br><span class="import-help__eg"><?php esc_html_e( 'Contoh: Andi Pratama · 2024001 · 7A · Siswa', 'absensi-sekolah' ); ?></span>
                </li>
                <li><?php esc_html_e( 'Simpan file, lalu unggah (.xlsx) dan klik Import.', 'absensi-sekolah' ); ?></li>
                <li><?php esc_html_e( 'Cek hasil: jumlah berhasil/gagal + daftar error per baris.', 'absensi-sekolah' ); ?></li>
              </ol>
            </div>

            <!-- Unduh template contoh -->
            <a class="import-tpl" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/templates/template-import-users.xlsx' ); ?>" download>
              <span x-html="$icon( 'download', 16 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Unduh template Excel (.xlsx)', 'absensi-sekolah' ); ?>
            </a>

            <!-- Area upload -->
            <label class="import-drop">
              <span class="import-drop__icon" x-html="$icon( 'file-spreadsheet', 32 )" aria-hidden="true"></span>
              <span x-show="! importFileName"><?php esc_html_e( 'Pilih file .xlsx', 'absensi-sekolah' ); ?></span>
              <span x-show="importFileName" x-cloak class="import-drop__file" x-text="importFileName"></span>
              <input type="file" class="import-drop__input"
                     accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                     @change="onImportFile($event)">
            </label>

            <!-- Error tingkat (503 vendor absen / 422 header/baris) -->
            <div x-show="importError" x-cloak class="alert alert--danger">
              <span class="alert__icon" x-html="$icon( 'alert-circle', 18 )" aria-hidden="true"></span>
              <span x-text="importError"></span>
            </div>

            <!-- Hasil impor -->
            <template x-if="importResult">
              <div class="import-result">
                <div class="import-summary">
                  <span class="import-stat import-stat--ok">
                    <span x-html="$icon( 'check-circle-2', 16 )" aria-hidden="true"></span>
                    <span x-text="importResult.imported"></span> <?php esc_html_e( 'berhasil', 'absensi-sekolah' ); ?>
                  </span>
                  <span class="import-stat import-stat--err" x-show="importResult.gagal > 0">
                    <span x-html="$icon( 'x-circle', 16 )" aria-hidden="true"></span>
                    <span x-text="importResult.gagal"></span> <?php esc_html_e( 'gagal', 'absensi-sekolah' ); ?>
                  </span>
                </div>
                <!-- Tabel error per baris -->
                <div class="import-errors" x-show="importResult.errors && importResult.errors.length > 0">
                  <table class="table">
                    <thead>
                      <tr>
                        <th style="width:80px;"><?php esc_html_e( 'Baris', 'absensi-sekolah' ); ?></th>
                        <th><?php esc_html_e( 'Pesan', 'absensi-sekolah' ); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <template x-for="(er, i) in importResult.errors" :key="i">
                        <tr>
                          <td class="u-num" x-text="er.baris"></td>
                          <td x-text="er.pesan"></td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>
          </div>

          <div class="modal__footer">
            <button type="button" class="btn btn--outline" @click="closeImport()"><?php esc_html_e( 'Tutup', 'absensi-sekolah' ); ?></button>
            <button type="button" class="btn btn--primary" @click="runImport()"
                    :class="importing ? 'is-loading' : ''" :disabled="! importFile || importing">
              <span class="btn__spin" x-show="importing" x-cloak aria-hidden="true"></span>
              <span class="btn__label"><?php esc_html_e( 'Import', 'absensi-sekolah' ); ?></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Import Akun Guru → POST /guru/import (base64). Buat WP user role `guru`. -->
      <div x-show="importGuruOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeImportGuru()" @click.self="closeImportGuru()">
        <div class="modal modal--lg" role="dialog" aria-modal="true" aria-labelledby="import-guru-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <div class="modal__head-ic">
              <span class="card-chip card-chip--primary" x-html="$icon( 'user-plus', 18 )" aria-hidden="true"></span>
              <h2 class="modal__title" id="import-guru-title"><?php esc_html_e( 'Import Akun Guru', 'absensi-sekolah' ); ?></h2>
            </div>
            <button type="button" class="modal__close" @click="closeImportGuru()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>

          <div class="modal__body">
            <!-- Cara import akun guru (tutorial kontekstual) -->
            <div class="import-help">
              <div class="import-help__title">
                <span x-html="$icon( 'info', 16 )" aria-hidden="true"></span>
                <?php esc_html_e( 'Cara import akun guru', 'absensi-sekolah' ); ?>
              </div>
              <ol class="import-help__steps">
                <li><?php esc_html_e( 'Unduh template di bawah, buka dengan Excel.', 'absensi-sekolah' ); ?></li>
                <li>
                  <?php esc_html_e( 'Isi kolom — wajib:', 'absensi-sekolah' ); ?> <strong>username</strong>.
                  <?php esc_html_e( 'Opsional:', 'absensi-sekolah' ); ?> <strong>nama</strong>, <strong>password</strong>, <strong>email</strong>.
                  <?php esc_html_e( 'Password kosong → dibuat otomatis; bila diisi minimal 6 karakter. Email harus valid & unik bila diisi.', 'absensi-sekolah' ); ?>
                  <br><span class="import-help__eg"><?php esc_html_e( 'Contoh: budi.guru · Budi Santoso · (kosong = auto) · budi@sekolah.sch.id', 'absensi-sekolah' ); ?></span>
                </li>
                <li><?php esc_html_e( 'Unggah (.xlsx) dan klik Import Guru. Akun dibuat dengan role Guru (akses kiosk RFID).', 'absensi-sekolah' ); ?></li>
                <li><strong><?php esc_html_e( 'Penting:', 'absensi-sekolah' ); ?></strong> <?php esc_html_e( 'setelah import, password tiap akun tampil SEKALI. Klik "Unduh daftar (.csv)" dan simpan sebelum menutup — tak bisa dilihat lagi.', 'absensi-sekolah' ); ?></li>
              </ol>
            </div>

            <!-- Unduh template contoh -->
            <a class="import-tpl" href="<?php echo esc_url( ABSENSI_PLUGIN_URL . 'assets/templates/template-import-akun-guru.xlsx' ); ?>" download>
              <span x-html="$icon( 'download', 16 )" aria-hidden="true"></span>
              <?php esc_html_e( 'Unduh template Excel (.xlsx)', 'absensi-sekolah' ); ?>
            </a>

            <!-- Area upload -->
            <label class="import-drop">
              <span class="import-drop__icon" x-html="$icon( 'file-spreadsheet', 32 )" aria-hidden="true"></span>
              <span x-show="! importGuruFileName"><?php esc_html_e( 'Pilih file .xlsx', 'absensi-sekolah' ); ?></span>
              <span x-show="importGuruFileName" x-cloak class="import-drop__file" x-text="importGuruFileName"></span>
              <input type="file" class="import-drop__input"
                     accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                     @change="onImportGuruFile($event)">
            </label>

            <!-- Error tingkat (503 vendor absen / 422 header/baris) -->
            <div x-show="importGuruError" x-cloak class="alert alert--danger">
              <span class="alert__icon" x-html="$icon( 'alert-circle', 18 )" aria-hidden="true"></span>
              <span x-text="importGuruError"></span>
            </div>

            <!-- Hasil impor -->
            <template x-if="importGuruResult">
              <div class="import-result">
                <div class="import-summary">
                  <span class="import-stat import-stat--ok">
                    <span x-html="$icon( 'check-circle-2', 16 )" aria-hidden="true"></span>
                    <span x-text="importGuruResult.imported"></span> <?php esc_html_e( 'berhasil', 'absensi-sekolah' ); ?>
                  </span>
                  <span class="import-stat import-stat--err" x-show="importGuruResult.gagal > 0">
                    <span x-html="$icon( 'x-circle', 16 )" aria-hidden="true"></span>
                    <span x-text="importGuruResult.gagal"></span> <?php esc_html_e( 'gagal', 'absensi-sekolah' ); ?>
                  </span>
                </div>

                <!-- Kredensial akun baru: tampil SEKALI (password tak tersimpan plaintext di DB) -->
                <template x-if="importGuruResult.kredensial && importGuruResult.kredensial.length > 0">
                  <div class="import-cred">
                    <div class="alert alert--warning">
                      <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
                      <span><?php esc_html_e( 'Simpan password sekarang — hanya ditampilkan sekali ini. Setelah modal ditutup tak bisa dilihat lagi (yang tersimpan hanya hash).', 'absensi-sekolah' ); ?></span>
                    </div>
                    <div class="import-cred__bar">
                      <strong><?php esc_html_e( 'Akun & password baru', 'absensi-sekolah' ); ?></strong>
                      <button type="button" class="btn btn--outline btn--sm" @click="downloadGuruCredentials()">
                        <span x-html="$icon( 'download', 16 )" aria-hidden="true"></span>
                        <?php esc_html_e( 'Unduh daftar (.csv)', 'absensi-sekolah' ); ?>
                      </button>
                    </div>
                    <table class="table">
                      <thead>
                        <tr>
                          <th><?php esc_html_e( 'Username', 'absensi-sekolah' ); ?></th>
                          <th><?php esc_html_e( 'Password', 'absensi-sekolah' ); ?></th>
                          <th><?php esc_html_e( 'Nama', 'absensi-sekolah' ); ?></th>
                        </tr>
                      </thead>
                      <tbody>
                        <template x-for="(k, i) in importGuruResult.kredensial" :key="i">
                          <tr>
                            <td x-text="k.username"></td>
                            <td><code class="import-cred__pass" x-text="k.password"></code></td>
                            <td x-text="k.nama"></td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                </template>

                <!-- Tabel error per baris -->
                <div class="import-errors" x-show="importGuruResult.errors && importGuruResult.errors.length > 0">
                  <table class="table">
                    <thead>
                      <tr>
                        <th style="width:80px;"><?php esc_html_e( 'Baris', 'absensi-sekolah' ); ?></th>
                        <th><?php esc_html_e( 'Pesan', 'absensi-sekolah' ); ?></th>
                      </tr>
                    </thead>
                    <tbody>
                      <template x-for="(er, i) in importGuruResult.errors" :key="i">
                        <tr>
                          <td class="u-num" x-text="er.baris"></td>
                          <td x-text="er.pesan"></td>
                        </tr>
                      </template>
                    </tbody>
                  </table>
                </div>
              </div>
            </template>
          </div>

          <div class="modal__footer">
            <button type="button" class="btn btn--outline" @click="closeImportGuru()"><?php esc_html_e( 'Tutup', 'absensi-sekolah' ); ?></button>
            <button type="button" class="btn btn--primary" @click="runImportGuru()"
                    :class="importGuruBusy ? 'is-loading' : ''" :disabled="! importGuruFile || importGuruBusy">
              <span class="btn__spin" x-show="importGuruBusy" x-cloak aria-hidden="true"></span>
              <span class="btn__label"><?php esc_html_e( 'Import Guru', 'absensi-sekolah' ); ?></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Konfirmasi Hapus (design.md §5) → DELETE /users/{id} -->
      <div x-show="delOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeDelete()" @click.self="closeDelete()">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="del-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <div class="modal__head-ic">
              <span class="card-chip card-chip--danger" x-html="$icon( 'trash-2', 18 )" aria-hidden="true"></span>
              <h2 class="modal__title" id="del-title"><?php esc_html_e( 'Hapus User', 'absensi-sekolah' ); ?></h2>
            </div>
            <button type="button" class="modal__close" @click="closeDelete()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>
          <div class="modal__body">
            <div class="alert alert--danger">
              <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
              <span>
                <?php esc_html_e( 'Yakin hapus', 'absensi-sekolah' ); ?>
                <strong x-text="delUser ? delUser.nama : ''"></strong>?
                <?php esc_html_e( 'Seluruh riwayat absensinya ikut terhapus — laporan bulan lalu akan ikut berubah. Tindakan ini tidak bisa dibatalkan.', 'absensi-sekolah' ); ?>
              </span>
            </div>
          </div>
          <div class="modal__footer">
            <button type="button" class="btn btn--outline" @click="closeDelete()"><?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?></button>
            <button type="button" class="btn btn--danger" @click="runDelete()"
                    :class="deleting ? 'is-loading' : ''" :disabled="deleting">
              <span class="btn__spin" x-show="deleting" x-cloak aria-hidden="true"></span>
              <span class="btn__label"><?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?></span>
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Konfirmasi Hapus MASSAL (checklist) → POST /users/bulk-delete { ids } -->
      <div x-show="bulkDelOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeBulkDelete()" @click.self="closeBulkDelete()">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="bulkdel-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <div class="modal__head-ic">
              <span class="card-chip card-chip--danger" x-html="$icon( 'trash-2', 18 )" aria-hidden="true"></span>
              <h2 class="modal__title" id="bulkdel-title"><?php esc_html_e( 'Hapus User Terpilih', 'absensi-sekolah' ); ?></h2>
            </div>
            <button type="button" class="modal__close" @click="closeBulkDelete()"
                    aria-label="<?php esc_attr_e( 'Tutup', 'absensi-sekolah' ); ?>">
              <span x-html="$icon( 'x', 18 )"></span>
            </button>
          </div>
          <div class="modal__body">
            <div class="alert alert--danger">
              <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
              <span>
                <?php esc_html_e( 'Yakin hapus', 'absensi-sekolah' ); ?>
                <strong class="u-num" x-text="selectedCount"></strong> <?php esc_html_e( 'user terpilih?', 'absensi-sekolah' ); ?>
                <?php esc_html_e( 'Seluruh riwayat absensi mereka ikut terhapus — laporan bulan lalu akan ikut berubah. Tindakan ini tidak bisa dibatalkan.', 'absensi-sekolah' ); ?>
              </span>
            </div>
            <!-- Pratinjau nama (maks 10) → biar tak salah hapus. -->
            <ul class="del-preview">
              <template x-for="u in filteredUsers.filter((x) => isSelected(x.id)).slice(0, 10)" :key="u.id">
                <li><span x-text="u.nama"></span> <span class="u-muted u-num" x-text="u.nomor_induk"></span></li>
              </template>
            </ul>
            <p class="u-muted" x-show="selectedCount > 10" x-cloak>
              <?php esc_html_e( '…dan', 'absensi-sekolah' ); ?>
              <span class="u-num" x-text="selectedCount - 10"></span> <?php esc_html_e( 'user lainnya.', 'absensi-sekolah' ); ?>
            </p>
          </div>
          <div class="modal__footer">
            <button type="button" class="btn btn--outline" @click="closeBulkDelete()"><?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?></button>
            <button type="button" class="btn btn--danger" @click="runBulkDelete()"
                    :class="bulkDeleting ? 'is-loading' : ''" :disabled="bulkDeleting">
              <span class="btn__spin" x-show="bulkDeleting" x-cloak aria-hidden="true"></span>
              <span class="btn__label">
                <?php esc_html_e( 'Hapus', 'absensi-sekolah' ); ?> <span class="u-num" x-text="selectedCount"></span>
              </span>
            </button>
          </div>
        </div>
      </div>

      <?php // State lengkap (skeleton/error/responsive/a11y) = item berikutnya. ?>

    </div>
  </div>
</div>
