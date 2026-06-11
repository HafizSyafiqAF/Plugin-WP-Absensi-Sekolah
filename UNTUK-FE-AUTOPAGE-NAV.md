# Untuk FE — Auto-Page Surface + Nav Visibility per Role

> Dari: BE · Tanggal: 2026-06-11
> Ringkas: BE menambah **auto-buat 3 page surface saat aktivasi** + **filter nav per role**. Tak ada perubahan kontrak API / localize. FE aman, cuma perlu tahu 2 hal di bawah.

---

## TL;DR (yang wajib FE tahu)

1. **Page surface dibuat otomatis** saat plugin diaktifkan — FE tak perlu bikin page manual.
2. **Link surface di navbar disaring per role oleh BE** — FE **tidak perlu** bikin logika sembunyikan menu per role.
3. **Kontrak API & var localize TIDAK berubah** — `AbsensiConfig` / `AbsensiAdmin`, endpoint, field semua sama. Kerjaan FE existing tidak terdampak.

---

## 1. Auto-Page Surface (saat aktivasi)

Saat plugin diaktifkan, BE otomatis buat 3 page WP (status **publish**):

| Page | Permalink | Isi konten |
|---|---|---|
| Absensi Siswa | `/absensi-siswa` | `[absensi_siswa]` |
| Absensi Guru | `/absensi-guru` | `[absensi_guru]` |
| Absensi Orang Tua | `/absensi-ortu` | `[absensi_ortu]` |

- ID page disimpan di option `absensi_pages` = `{ siswa, guru, ortu }`.
- **Idempotent:** aktivasi ulang tak buat dobel; kalau user sudah punya page ber-slug sama, dipakai (tak ditimpa).
- Deactivate / uninstall: **page dibiarkan** (tak dihapus).

### Yang FE kerjakan
Cukup sediakan **markup view** (shortcode sudah di-wire BE):

```
public/views/siswa.php   ← dipanggil shortcode [absensi_siswa]
public/views/guru.php    ← [absensi_guru]
public/views/ortu.php    ← [absensi_ortu]
```

- Shortcode sudah meng-`include` file ini via `ob_start()`. Kalau view belum ada → BE tampilkan placeholder (bukan error).
- Di dalam view, data sudah tersedia lewat `AbsensiConfig` (lihat skema localize — tak berubah).
- Gate sudah ditangani shortcode (login + cap). View tak perlu cek login lagi (boleh, tapi tak wajib).

---

## 2. Nav Visibility per Role (ditangani BE)

Link surface di navigasi **otomatis disembunyikan** sesuai role user. FE **tidak perlu** menulis logika ini.

| Login sebagai | Link yang tampil di navbar |
|---|---|
| role `absensi_siswa` | **Absensi Siswa** saja |
| role `guru` | **Absensi Guru** saja |
| role `orang_tua` | **Absensi Orang Tua** saja |
| `administrator` | ketiganya |
| guest (belum login) | tak ada link surface |

### Cara kerja (BE)
Filter server-side, menjangkau semua jenis menu WP:
- `wp_list_pages_excludes` → tema klasik (`wp_page_menu`).
- `wp_nav_menu_objects` → menu custom (Appearance → Menus).
- `get_pages` → **tema blok / FSE** (mis. Twenty Twenty-Five — Navigation block render lewat `core/page-list`).

Map cap (strict per-role):
| Page | Cap wajib |
|---|---|
| Absensi Siswa | `absensi_submit_self` |
| Absensi Guru | `absensi_submit_rfid` |
| Absensi Orang Tua | `absensi_view_child` |

### Catatan untuk FE
- **Cosmetic only.** Ini menyembunyikan *link* di navbar. Gate isi page tetap di shortcode (login + cap). Buka URL langsung tetap dicek.
- Kalau FE/tema render menu via cara **non-standar** (hardcode `<a href>` di template, bukan `wp_nav_menu`/`core/navigation`), filter ini **tak menjangkau** → koordinasi ke BE biar disesuaikan.
- Kalau FE mau bikin nav sendiri yang role-aware, bisa pakai data role/cap user dari WP biasa; tapi disarankan biarkan BE yang saring (sudah jalan & ketes).

---

## 3. Kontrak API & Localize — TIDAK berubah

- `AbsensiConfig` (siswa/guru/ortu) dan `AbsensiAdmin` (admin): field sama persis seperti sebelumnya. Tak ada hapus/rename.
- Endpoint REST `absensi/v1/*`: tak ada perubahan.
- Jadi: tak ada yang perlu FE ubah di sisi konsumsi data.

---

## Status tes (BE)

| Aspek | Tes | Hasil |
|---|---|---|
| Auto-page | `tests/_seed_pages_test.php` | 22/22 |
| Nav visibility per role | `tests/_nav_visibility_test.php` | 26/26 |
| Fresh install (incl. page) | `tests/_fresh_install_test.php` | 38/38 |
| Regresi keseluruhan | `tests/run-all.php` | 62/62 |

Render nyata `core/page-list` per role (Twenty Twenty-Five) sudah diverifikasi cocok dengan tabel di §2.

---

## Pertanyaan untuk FE (kalau ada)

1. Tema final yang dipakai apa? (kalau bukan block theme / pakai nav custom non-standar → kabari BE).
2. View `siswa.php` / `guru.php` / `ortu.php` mau kapan disiapkan? (shortcode sudah siap menunggu).
