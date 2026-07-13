<?php
/**
 * Admin view — Jadwal (v2.2.0). Dua tab: Jadwal per Group + Hari Libur.
 *
 * Keduanya = masukan mesin ALPHA (KehadiranHelper):
 *   - Jadwal per group menentukan HARI AKTIF group itu (tanpa jadwal → Sen–Jum + Jadwal Default
 *     dari Pengaturan).
 *   - Hari libur = tanggal yang tak dihitung sama sekali (tak ada alpha, tak menuduh bolos).
 *
 * Endpoint: /jadwal (CRUD), /libur (CRUD), /group (opsi). Admin-only.
 */
defined( 'ABSPATH' ) || exit;
?>
<div class="wrap">
  <div class="absensi-app" x-data="jadwalManager">
    <div class="absensi-page">

      <!-- Header -->
      <div class="absensi-page__head">
        <div class="page-title-wrap">
          <h1 class="absensi-page__title"><?php esc_html_e( 'Jadwal', 'absensi-sekolah' ); ?></h1>
          <p class="page-subtitle"><?php esc_html_e( 'Atur hari & jam absensi tiap grup, serta tanggal libur.', 'absensi-sekolah' ); ?></p>
        </div>
      </div>

      <!-- Tab -->
      <div class="pill-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Bagian jadwal', 'absensi-sekolah' ); ?>">
        <button type="button" class="pill" role="tab" :class="tab === 'jadwal' ? 'is-active' : ''"
                :aria-selected="tab === 'jadwal'" @click="tab = 'jadwal'">
          <?php esc_html_e( 'Jadwal per Group', 'absensi-sekolah' ); ?>
        </button>
        <button type="button" class="pill" role="tab" :class="tab === 'libur' ? 'is-active' : ''"
                :aria-selected="tab === 'libur'" @click="tab = 'libur'">
          <?php esc_html_e( 'Hari Libur', 'absensi-sekolah' ); ?>
        </button>
      </div>

      <!-- ══════════ TAB 1 — JADWAL PER GROUP ══════════ -->
      <div x-show="tab === 'jadwal'" x-cloak>
        <div class="alert alert--info">
          <span class="alert__icon" x-html="$icon( 'info', 18 )" aria-hidden="true"></span>
          <span>
            <?php esc_html_e( 'Hari yang tidak diaktifkan = bukan hari absensi (tidak dihitung Alpha). Grup yang belum punya jadwal otomatis memakai Jadwal Default dari Pengaturan (Senin–Jumat). Toleransi telat tetap dari Pengaturan, dihitung dari jam masuk grup ini.', 'absensi-sekolah' ); ?>
          </span>
        </div>

        <div class="table-card">
          <div class="group-bar">
            <select class="select" x-model="groupId" @change="gantiGroup()"
                    aria-label="<?php esc_attr_e( 'Pilih grup', 'absensi-sekolah' ); ?>">
              <option value=""><?php esc_html_e( 'Pilih grup…', 'absensi-sekolah' ); ?></option>
              <template x-for="g in groups" :key="g.id">
                <option :value="g.id" x-text="g.nama"></option>
              </template>
            </select>
            <span class="u-muted" x-show="groupId && ! punyaJadwal(groupId)" x-cloak>
              <?php esc_html_e( 'Grup ini belum punya jadwal → memakai Jadwal Default.', 'absensi-sekolah' ); ?>
            </span>
          </div>

          <!-- Belum pilih grup -->
          <div x-show="! groupId" x-cloak class="empty">
            <div class="empty__icon" x-html="$icon( 'calendar', 32 )" aria-hidden="true"></div>
            <p class="empty__title"><?php esc_html_e( 'Pilih grup dulu', 'absensi-sekolah' ); ?></p>
            <p class="empty__desc"><?php esc_html_e( 'Jadwal diatur per grup: hari mana saja masuk, jam berapa masuk & pulang.', 'absensi-sekolah' ); ?></p>
          </div>

          <!-- Error simpan (409 duplikat / 422 jam) -->
          <div class="alert alert--danger" x-show="jadwalError" x-cloak style="margin:12px 16px;">
            <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
            <span x-text="jadwalError"></span>
          </div>

          <!-- 7 hari -->
          <div x-show="groupId" x-cloak class="table-scroll">
            <table class="table jadwal-table">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Hari', 'absensi-sekolah' ); ?></th>
                  <th><?php esc_html_e( 'Aktif', 'absensi-sekolah' ); ?></th>
                  <th><?php esc_html_e( 'Jam Masuk', 'absensi-sekolah' ); ?></th>
                  <th><?php esc_html_e( 'Jam Pulang', 'absensi-sekolah' ); ?></th>
                  <th class="col-actions"><?php esc_html_e( 'Aksi', 'absensi-sekolah' ); ?></th>
                </tr>
              </thead>
              <tbody>
                <template x-for="h in [1,2,3,4,5,6,7]" :key="h">
                  <tr :class="baris[h] && baris[h].aktif ? '' : 'is-off'">
                    <td data-label="<?php esc_attr_e( 'Hari', 'absensi-sekolah' ); ?>">
                      <strong x-text="HARI[h]"></strong>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Aktif', 'absensi-sekolah' ); ?>">
                      <input type="checkbox" class="check" x-model="baris[h].aktif"
                             :aria-label="'<?php echo esc_js( __( 'Aktifkan hari', 'absensi-sekolah' ) ); ?> ' + HARI[h]">
                    </td>
                    <td data-label="<?php esc_attr_e( 'Jam Masuk', 'absensi-sekolah' ); ?>">
                      <input type="time" class="input input--sm" x-model="baris[h].jam_masuk"
                             :disabled="! baris[h].aktif"
                             :aria-label="'<?php echo esc_js( __( 'Jam masuk', 'absensi-sekolah' ) ); ?> ' + HARI[h]">
                    </td>
                    <td data-label="<?php esc_attr_e( 'Jam Pulang', 'absensi-sekolah' ); ?>">
                      <input type="time" class="input input--sm" x-model="baris[h].jam_keluar"
                             :disabled="! baris[h].aktif"
                             :aria-label="'<?php echo esc_js( __( 'Jam pulang', 'absensi-sekolah' ) ); ?> ' + HARI[h]">
                    </td>
                    <td class="col-actions" data-label="<?php esc_attr_e( 'Aksi', 'absensi-sekolah' ); ?>">
                      <button type="button" class="btn btn--outline btn--sm"
                              @click="simpanBaris(h)" :disabled="simpanHari === h"
                              :class="simpanHari === h ? 'is-loading' : ''">
                        <span class="btn__spin" x-show="simpanHari === h" x-cloak aria-hidden="true"></span>
                        <span class="btn__label"><?php esc_html_e( 'Simpan', 'absensi-sekolah' ); ?></span>
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ══════════ TAB 2 — HARI LIBUR ══════════ -->
      <div x-show="tab === 'libur'" x-cloak>
        <div class="alert alert--info">
          <span class="alert__icon" x-html="$icon( 'info', 18 )" aria-hidden="true"></span>
          <span><?php esc_html_e( 'Tanggal libur tidak dihitung kehadiran — tak ada yang ditandai Alpha. Isi tanggal selesai untuk libur beberapa hari (mis. libur semester).', 'absensi-sekolah' ); ?></span>
        </div>

        <div class="table-card">
          <!-- Form tambah -->
          <form class="group-bar" @submit.prevent="tambahLibur()">
            <div class="field field--inline">
              <label class="field__label" for="lb-mulai"><?php esc_html_e( 'Tanggal', 'absensi-sekolah' ); ?></label>
              <input id="lb-mulai" type="date" class="input input--sm" x-model="formLibur.tanggal_mulai" required>
            </div>
            <div class="field field--inline">
              <label class="field__label" for="lb-selesai"><?php esc_html_e( 'Sampai (opsional)', 'absensi-sekolah' ); ?></label>
              <input id="lb-selesai" type="date" class="input input--sm" x-model="formLibur.tanggal_selesai"
                     :min="formLibur.tanggal_mulai">
            </div>
            <div class="field field--inline field--grow">
              <label class="field__label" for="lb-ket"><?php esc_html_e( 'Keterangan', 'absensi-sekolah' ); ?></label>
              <input id="lb-ket" type="text" class="input input--sm" x-model.trim="formLibur.keterangan" maxlength="150"
                     placeholder="<?php esc_attr_e( 'mis. Idul Fitri, Libur Semester', 'absensi-sekolah' ); ?>">
            </div>
            <button type="submit" class="btn btn--primary btn--sm" :disabled="! liburValid || liburBusy"
                    :class="liburBusy ? 'is-loading' : ''">
              <span class="btn__spin" x-show="liburBusy" x-cloak aria-hidden="true"></span>
              <span class="btn__label"><?php esc_html_e( 'Tambah', 'absensi-sekolah' ); ?></span>
            </button>
          </form>

          <div class="alert alert--danger" x-show="liburError" x-cloak style="margin:12px 16px;">
            <span class="alert__icon" x-html="$icon( 'alert-triangle', 18 )" aria-hidden="true"></span>
            <span x-text="liburError"></span>
          </div>

          <!-- Daftar libur -->
          <div x-show="libur.length > 0" x-cloak class="table-scroll">
            <table class="table">
              <thead>
                <tr>
                  <th><?php esc_html_e( 'Tanggal', 'absensi-sekolah' ); ?></th>
                  <th><?php esc_html_e( 'Lama', 'absensi-sekolah' ); ?></th>
                  <th><?php esc_html_e( 'Keterangan', 'absensi-sekolah' ); ?></th>
                  <th class="col-actions"><?php esc_html_e( 'Aksi', 'absensi-sekolah' ); ?></th>
                </tr>
              </thead>
              <tbody>
                <template x-for="l in libur" :key="l.id">
                  <tr>
                    <td data-label="<?php esc_attr_e( 'Tanggal', 'absensi-sekolah' ); ?>" x-text="rentangTeks(l)"></td>
                    <td data-label="<?php esc_attr_e( 'Lama', 'absensi-sekolah' ); ?>">
                      <span class="u-num" x-text="jumlahHari(l)"></span> <?php esc_html_e( 'hari', 'absensi-sekolah' ); ?>
                    </td>
                    <td data-label="<?php esc_attr_e( 'Keterangan', 'absensi-sekolah' ); ?>" x-text="l.keterangan || '—'"></td>
                    <td class="col-actions" data-label="<?php esc_attr_e( 'Aksi', 'absensi-sekolah' ); ?>">
                      <button type="button" class="act-ic act-ic--danger" @click="hapusLibur(l)"
                              :disabled="hapusLiburId === l.id"
                              :aria-label="'<?php echo esc_js( __( 'Hapus libur', 'absensi-sekolah' ) ); ?> ' + (l.keterangan || '')">
                        <span x-html="$icon( 'trash-2', 16 )"></span>
                      </button>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Kosong -->
          <div x-show="libur.length === 0" x-cloak class="empty">
            <div class="empty__icon" x-html="$icon( 'calendar', 32 )" aria-hidden="true"></div>
            <p class="empty__title"><?php esc_html_e( 'Belum ada hari libur', 'absensi-sekolah' ); ?></p>
            <p class="empty__desc"><?php esc_html_e( 'Tambahkan tanggal merah & libur semester agar tak dihitung Alpha.', 'absensi-sekolah' ); ?></p>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>
