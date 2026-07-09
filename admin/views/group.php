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

      <!-- Header halaman (design.md §3.1 / §6): judul kiri + aksi utama kanan -->
      <div class="absensi-page__head">
        <h1 class="absensi-page__title"><?php esc_html_e( 'Group', 'absensi-sekolah' ); ?></h1>
        <div class="absensi-page__actions">
          <button type="button" class="btn btn--primary" @click="openCreate()">
            <span x-html="$icon( 'plus', 18 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Tambah Group', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>

      <!-- Tabel Group (design.md §6): skeleton · error · tabel · empty -->
      <div class="table-card">

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
        <div x-show="! loading && ! error && groups.length > 0" x-cloak class="table-scroll">
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
              <template x-for="g in groups" :key="g.id">
                <tr>
                  <!-- Nama -->
                  <td data-label="<?php esc_attr_e( 'Nama Group', 'absensi-sekolah' ); ?>">
                    <span class="t-body-strong" x-text="g.nama"></span>
                  </td>
                  <!-- Tipe (badge) -->
                  <td data-label="<?php esc_attr_e( 'Tipe', 'absensi-sekolah' ); ?>">
                    <span class="badge" :class="tipeBadge(g.tipe)" x-text="tipeLabel(g.tipe)"></span>
                  </td>
                  <!-- Jumlah User -->
                  <td data-label="<?php esc_attr_e( 'Jumlah User', 'absensi-sekolah' ); ?>">
                    <span class="group-count">
                      <span class="group-count__icon" x-html="$icon( 'users', 15 )" aria-hidden="true"></span>
                      <span class="u-num" x-text="g.jumlah_user || 0"></span>
                    </span>
                  </td>
                  <!-- Aksi: edit / hapus -->
                  <td class="col-actions" data-label="<?php esc_attr_e( 'Aksi', 'absensi-sekolah' ); ?>">
                    <button type="button" class="btn btn--ghost btn--icon btn--sm" @click="openEdit(g)"
                            :aria-label="'<?php echo esc_js( __( 'Edit group', 'absensi-sekolah' ) ); ?> ' + g.nama">
                      <span x-html="$icon( 'square-pen', 16 )"></span>
                    </button>
                    <button type="button" class="btn btn--ghost btn--icon btn--sm act-del" @click="confirmDelete(g)"
                            :aria-label="'<?php echo esc_js( __( 'Hapus group', 'absensi-sekolah' ) ); ?> ' + g.nama">
                      <span x-html="$icon( 'trash-2', 16 )"></span>
                    </button>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Empty (belum ada group) -->
        <div x-show="! loading && ! error && groups.length === 0" x-cloak class="empty">
          <div class="empty__icon" x-html="$icon( 'layers', 32 )" aria-hidden="true"></div>
          <p class="empty__title"><?php esc_html_e( 'Belum ada group', 'absensi-sekolah' ); ?></p>
          <p class="empty__desc"><?php esc_html_e( 'Buat group pertama (kelas/guru/staff) untuk mengelompokkan user.', 'absensi-sekolah' ); ?></p>
          <button type="button" class="btn btn--primary" @click="openCreate()">
            <span x-html="$icon( 'plus', 16 )" aria-hidden="true"></span>
            <?php esc_html_e( 'Tambah Group', 'absensi-sekolah' ); ?>
          </button>
        </div>
      </div>

      <!-- Modal Form (tambah/edit group) — design.md §6 -->
      <div x-show="modalOpen" x-cloak class="modal-overlay"
           @keydown.escape.window="closeModal()" @click.self="closeModal()">
        <div class="modal" role="dialog" aria-modal="true" aria-labelledby="gf-title" @keydown.tab="trapFocus($event)">
          <div class="modal__head">
            <div class="modal__head-ic">
              <span class="card-chip card-chip--primary" x-html="$icon( 'layers', 18 )" aria-hidden="true"></span>
              <h2 class="modal__title" id="gf-title"
                  x-text="editing
                    ? '<?php echo esc_js( __( 'Edit Group', 'absensi-sekolah' ) ); ?>'
                    : '<?php echo esc_js( __( 'Tambah Group', 'absensi-sekolah' ) ); ?>'"></h2>
            </div>
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

              <!-- Nama Group (wajib, ≤100) -->
              <div class="field">
                <label class="field__label" for="gf-nama"><?php esc_html_e( 'Nama Group *', 'absensi-sekolah' ); ?></label>
                <input id="gf-nama" type="text" class="input" :class="fieldErr.nama ? 'input--error' : ''"
                       x-model.trim="form.nama" maxlength="100" required @input="fieldErr.nama = false">
              </div>

              <!-- Tipe (kelas default / guru / staff) -->
              <div class="field">
                <label class="field__label" for="gf-tipe"><?php esc_html_e( 'Tipe', 'absensi-sekolah' ); ?></label>
                <select id="gf-tipe" class="select" x-model="form.tipe">
                  <option value="kelas"><?php esc_html_e( 'Kelas', 'absensi-sekolah' ); ?></option>
                  <option value="guru"><?php esc_html_e( 'Guru', 'absensi-sekolah' ); ?></option>
                  <option value="staff"><?php esc_html_e( 'Staff', 'absensi-sekolah' ); ?></option>
                </select>
              </div>
            </div>

            <div class="modal__footer">
              <button type="button" class="btn btn--outline" @click="closeModal()"><?php esc_html_e( 'Batal', 'absensi-sekolah' ); ?></button>
              <button type="submit" class="btn btn--primary" :class="saving ? 'is-loading' : ''" :disabled="! canSave">
                <span class="btn__spin" x-show="saving" x-cloak aria-hidden="true"></span>
                <span class="btn__label" x-text="editing
                  ? '<?php echo esc_js( __( 'Simpan Perubahan', 'absensi-sekolah' ) ); ?>'
                  : '<?php echo esc_js( __( 'Simpan', 'absensi-sekolah' ) ); ?>'"></span>
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
