# CLAUDE.md — Plugin WP Absensi Sekolah

Panduan untuk Claude Code saat bekerja di plugin ini. **Kode = sumber kebenaran.** Dokumen `plans/` dan `README.md` bersifat aspiratif dan menyimpang jauh dari implementasi (lihat §Divergensi). Plugin sudah **pivot ke model kiosk v2** (tanpa login/role publik) — riwayat & rasional pivot ada di `TODO-BE-PIVOT.md` + `tests/manual/UC-pivot-*.md`.

> ⚠️ **Update fitur "Akun Guru" (2026-07-10) — sebagian membalik pivot.** Kiosk **RFID** kini **login-gated**: hanya user ber-cap `absensi_rfid` (role **`guru`** yang di-*re-add*, + administrator) yang boleh buka page `/absensi/guru` & hit `POST /absen/rfid`. Kiosk **selfie siswa (`/absensi`) TETAP publik tanpa login**. Detail: **§Akun Guru** + `TODO-BE-AKUN-GURU.md` + `tests/manual/UC-akun-guru-*.md`.

---

## Ringkasan

Plugin WordPress untuk absensi sekolah, MVP. **Model kiosk:** siswa/staff = **data** (baris di `absensi_users`), **bukan** user WP. Yang punya akun WP: **administrator** (`manage_options`) + sejak fitur Akun Guru, **guru** (role `guru`, cap `absensi_rfid`, akses terbatas ke kiosk RFID — TIDAK bisa wp-admin).

Dua mode absen:
1. **Selfie + GPS** (**publik, tanpa login**) — orang ketik **nomor induk (NIS/NIP)** di kiosk `/absensi` (page siswa), ambil selfie, kirim (validasi radius haversine + akurasi GPS).
2. **RFID USB scanner** (**login-gated: guru/admin**) — guru buka kiosk `/absensi/guru` dari device masing-masing (login dulu), tap kartu **siswa** (scanner = HID keyboard, "ketik" UID + Enter). Login = gerbang akses device; identitas absen tetap dari `rfid_uid` **siswa** (rekap tak punya guru_id).

Anti-abuse endpoint publik: rate-limit transient per nomor_induk (selfie) + debounce anti double-tap (RFID).

Admin (wp-admin, `manage_options`): Dashboard · **Users** · **Group** · Absen RFID · Laporan (rekap + export CSV/XLSX/PDF) · Pengaturan. Stack: PHP 8.0+, WordPress 6.0+, custom table `$wpdb` (bukan CPT), REST API, **Alpine.js + Tailwind CSS via CDN** (tanpa Vite/build tool).

---

## Arsitektur

**Entry point:** [absensi-sekolah.php](absensi-sekolah.php) — header plugin, konstanta (`ABSENSI_*`), autoloader, register activation/deactivation, boot di `plugins_loaded`.

**Autoload:** DUA lapis (lihat [absensi-sekolah.php](absensi-sekolah.php)):
1. **Composer** `vendor/autoload.php` dimuat **bila ada** (opsional; untuk PhpSpreadsheet/Dompdf export + PHPUnit/Brain Monkey dev). `vendor/` TIDAK di-commit.
2. **`spl_autoload_register` manual** (autoloader UTAMA namespace `Absensi\` → `includes/`). Segmen namespace = nama folder **case-sensitive**:

| Namespace | Folder |
|---|---|
| `Absensi\Plugin`, `Absensi\Installer`, `Absensi\Retensi` | `includes/` |
| `Absensi\api\*` | `includes/api/` |
| `Absensi\class\*` | `includes/class/` |
| `Absensi\helpers\*` | `includes/helpers/` |
| `Absensi\Admin\*` | `includes/Admin/` |

> ⚠️ `class` adalah reserved word PHP tapi dipakai sebagai segmen namespace (`Absensi\class\Shortcodes`) — legal dalam konteks namespace, tapi tak biasa. Pertahankan saat menambah file di folder itu.

**Bootstrap:** [includes/Plugin.php](includes/Plugin.php) — singleton. `boot()` (di `plugins_loaded`) menjalankan: `Installer::maybe_upgrade()` (migration runner), `Retensi::init()`, register PostTypes, **6 REST endpoint** (`rest_api_init`), Admin Menu (`if is_admin()`), Shortcodes, enqueue asset public + admin + Alpine/Tailwind CDN.

**Pola REST:** Controller endpoint langsung query `$wpdb` di dalam handler (TIDAK ada layer Service/Repository terpisah). Sanitasi lewat `SanitizeHelper` sebelum insert/update.

---

## Database

Tabel custom dibuat di [includes/Installer.php](includes/Installer.php) via `dbDelta`. Prefix `{$wpdb->prefix}absensi_`:

| Tabel | Isi | Index penting |
|---|---|---|
| `absensi_users` | master orang yang diabsen (siswa/guru/staff), `nomor_induk`, `rfid_uid`, `group_id`, `foto_path`. **Tanpa akun WP.** | UNIQUE `nomor_induk`, UNIQUE `rfid_uid` |
| `absensi_group` | kelompok absen: `nama` + `tipe` **VARCHAR(50) kustom bebas** (v2.1.0; default `kelas`, eks-ENUM) | PK |
| `absensi_jadwal` | jam masuk/keluar per `group_id` per `hari` (1=Senin) | KEY `group_id` |
| `absensi_libur` | **(v2.2.0)** hari libur sbg **rentang** (`tanggal_mulai`..`tanggal_selesai` + `keterangan`); libur sehari → mulai == selesai. Tanggal libur TIDAK dihitung alpha. Overlap dibolehkan. | KEY `tanggal_mulai`, `tanggal_selesai` |
| `absensi_rekap` | **1 baris per user per tanggal** | UNIQUE `(user_id, tanggal)`, KEY `tanggal`, `group_id` |

**Model rekap (penting):** satu hari = satu baris. Kolom `waktu_masuk` + `waktu_keluar` di baris sama. Tap/selfie pertama → insert (`waktu_masuk`), kedua → `UPDATE` set `waktu_keluar`. **Bukan** dua baris terpisah. `status` ENUM(`hadir,telat,izin,sakit,alpha`), `mode`/`metode_masuk`/`metode_keluar` ENUM(`selfie,rfid,manual`). Kolom `izin_tipe`/`bukti_status`/`bukti_path` **ADA tapi dormant** (izin/sakit luar MVP — lihat §Gap).

**Versi skema + migration runner (ADA):** `Installer::DB_VERSION` (`2.2.0`) + option `absensi_db_version`. `maybe_upgrade()` jalan tiap `plugins_loaded`: bila versi tersimpan < `DB_VERSION` → jalankan migrasi per-versi + `create_tables()` (dbDelta) + re-seed options. **Tak perlu deactivate/activate** untuk sinkron skema. Migrasi breaking (rename tabel/kolom / ubah tipe kolom) yang dbDelta tak bisa: `migrate_to_v2()` (RENAME TABLE + ALTER, idempotent) untuk <2.0.0, `migrate_to_v2_1()` (ALTER MODIFY `group.tipe` ENUM→VARCHAR, guard `column_is_enum`) untuk <2.1.0 — dipanggil SEBELUM `create_tables()`. Idempotent via cek `table_exists`/`column_exists`/`column_is_enum`/`index_exists`.

**Settings = wp_options individual** (BUKAN blob serialized), di-seed `Installer::seed_default_options()` (idempotent, ikut re-seed di `maybe_upgrade`): `absensi_lat`, `absensi_lng`, `absensi_radius` (100), `absensi_jam_masuk` (`07:00`), `absensi_jam_keluar` (`15:00`), `absensi_telat_menit` (15), `absensi_akurasi_max` (100), `absensi_rfid_debounce` (3), `absensi_retensi_hari` (90), `absensi_wa_gateway`, `absensi_wa_token`. Runtime juga pakai `absensi_selfie_rl_detik` (rate-limit selfie, default 5). `absensi_wa_*` masih di-seed tapi **notifikasi WA dicabut** (§Gap).

---

## REST API

Namespace `absensi/v1` (`/wp-json/absensi/v1/`). Konstanta `NAMESPACE` diulang di tiap kelas endpoint. 6 endpoint didaftar di `Plugin::boot()`.

| Method | Endpoint | Permission | File |
|---|---|---|---|
| POST | `/absen/selfie` | **publik** (`__return_true`) | [AbsensiEndpoint.php](includes/api/AbsensiEndpoint.php) |
| POST | `/absen/rfid` | **login + cap `absensi_rfid`** (guru/admin) — `can_absen_rfid()` | AbsensiEndpoint |
| GET | `/absen/status` | **publik** (by `nomor_induk`) | AbsensiEndpoint |
| GET/POST | `/users` | `manage_options` | [UsersEndpoint.php](includes/api/UsersEndpoint.php) |
| GET/PUT/DELETE | `/users/{id}` | `manage_options` | UsersEndpoint |
| POST | `/users/{id}/rfid` | `manage_options` | UsersEndpoint (bind kartu; ganti enroll lama) |
| POST | `/users/bulk-delete` | `manage_options` | UsersEndpoint (`{ids:[int]}` → `{deleted:N}`; hapus massal dari checklist tabel, 1 query `DELETE ... IN`, cap 500) |
| POST | `/users/import` | `manage_options` | UsersEndpoint (xlsx, PhpSpreadsheet; kolom `nama`,`nomor_induk` + opsional `group`,`tipe` — group resolve by nama+tipe, auto-create pakai tipe dari file; tanpa `tipe` group wajib sudah ada) |
| POST | `/guru/import` | `manage_options` | UsersEndpoint (`import_guru`: bulk WP user role `guru` dari xlsx) |
| GET/POST | `/group` | `manage_options` | [GroupEndpoint.php](includes/api/GroupEndpoint.php) |
| GET/PUT/DELETE | `/group/{id}` | `manage_options` | GroupEndpoint |
| GET/POST/PUT/DELETE | `/jadwal`, `/jadwal/{id}` | `manage_options` | [JadwalEndpoint.php](includes/api/JadwalEndpoint.php) |
| POST | `/rekap/status` | `manage_options` | [RekapEndpoint.php](includes/api/RekapEndpoint.php) — koreksi status admin (Alpha→Izin/Sakit dst). **UPSERT by (`user_id`,`tanggal`)**, BUKAN by id: baris alpha itu virtual (tak ada di DB) → belum ada = INSERT `mode=manual` (201), sudah ada = UPDATE **status+catatan saja** (200; jam masuk/keluar & bukti selfie tak diutak-atik). FE selalu kirim user_id+tanggal, tak perlu bercabang. |
| DELETE | `/rekap/{id}` | `manage_options` | RekapEndpoint — batalkan penyesuaian → baris kembali dihitung otomatis (alpha lagi). **Hanya `mode=manual`**; rekap selfie/RFID → **409 `bukan_manual`** (bukti kehadiran, bukan penyesuaian). |
| GET/POST/PUT/DELETE | `/libur`, `/libur/{id}` | `manage_options` | [LiburEndpoint.php](includes/api/LiburEndpoint.php) — hari libur (v2.2.0). `GET` filter `dari`/`sampai` = **bersinggungan** (libur semester tetap muncul saat lihat 1 bulan di tengahnya). `tanggal_selesai` kosong → libur sehari. 422 `tanggal_invalid` / `rentang_terbalik` |
| GET | `/laporan`, `/laporan/summary`, `/laporan/export` | `manage_options` | [LaporanEndpoint.php](includes/api/LaporanEndpoint.php) — **hasilnya = rekap nyata + baris ALPHA virtual** (KehadiranHelper). Rekap diambil TANPA `LIMIT` lalu digabung+diurut+dipaginasi di PHP (kalau `LIMIT` di SQL, halaman 2 melewatkan alpha). Export ikut memuat alpha. |
| GET/PUT | `/settings` | `manage_options` | [SettingsEndpoint.php](includes/api/SettingsEndpoint.php) |

**Auth model (tiga kelas):**
- **Kiosk publik siswa** (selfie/status): `permission_callback => __return_true`. Identitas dari **`nomor_induk`**, bukan sesi WP. Anti-abuse: rate-limit transient per nomor_induk — **wajib pertahankan** (endpoint tanpa auth).
- **Kiosk RFID (login-gated)** (`/absen/rfid`): `can_absen_rfid()` = `is_user_logged_in() && current_user_can('absensi_rfid')` (role `guru`/admin). Nonce `wp_rest` (cookie auth REST). Debounce anti double-tap **wajib pertahankan**. Identitas absen dari **`rfid_uid` siswa** (login guru = gerbang akses, tak masuk rekap).
- **Admin** (users/group/guru-import/jadwal/laporan/settings): `current_user_can('manage_options')`. Cookie WP + nonce `wp_rest` (header `X-WP-Nonce`), di-inject via `wp_localize_script` → `AbsensiConfig` (public) / `AbsensiAdmin` (admin) `{ restUrl, nonce, ... }`.
- **Role `guru` AKTIF kembali** (fitur Akun Guru): di-seed `Installer::seed_roles()` (caps `read` + `absensi_rfid`, tanpa cap admin lain). Cap `absensi_rfid` juga di administrator. Gate/blok terkait di §Akun Guru. (Role pra-pivot lain — `orang_tua`/`absensi_admin`/`absensi_siswa` — mungkin masih nyangkut di DB tapi tak ada gate yang membacanya; dihapus saat uninstall.)

**Format response:** sukses `WP_REST_Response([...], 2xx)`. Error via helper `error($code,$msg,$status)` → `['code','message','data'=>['status']]`. Status umum: 403 (luar radius / HTTPS), 404 (nomor/UID/entitas tak ada), 409 (sudah absen / UID/nomor bentrok / group masih ada user), 422 (input invalid), 429 (rate-limit / double-tap), 503 (vendor export absen / sekolah belum diatur).

**Endpoint yang SUDAH DIHAPUS (jangan bikin ulang, sudah 404):** `/siswa*`, `/kelas*`, `/wali*`, `/child*`, `/absen/izin`, `POST /absen/status` (set-status guru), `/absen/rfid/enroll`, `/absen/rfid/resolve`.

---

## Helpers (`includes/helpers/`)

- **[SanitizeHelper.php](includes/helpers/SanitizeHelper.php)** — WAJIB sebelum tiap `$wpdb->insert/update`. `::users()` (nomor_induk≤30, nama≤150, group_id absint, rfid_uid), `::group()` (nama≤100, **tipe string bebas ≤50** — bukan whitelist; `GroupEndpoint` menolak tipe kosong → 422 `tipe_wajib`, tak ada preset/default), `::rekap()` (whitelist `status`/`mode`, kunci `user_id`/`group_id`), `::jadwal()` (group_id, normalize jam), `::libur()` (tanggal via `::normalize_date()` — tolak 2026-02-31 pakai `checkdate`; keterangan ≤150), `::rfid_uid()` (strip non-hex, uppercase, trim CR/LF dari HID). (`::siswa()`/`::kelas()` lama sudah dibuang.)
- **[KehadiranHelper.php](includes/helpers/KehadiranHelper.php)** — **mesin ALPHA (v2.2.0)**. Rekap hanya lahir saat orang tap/selfie → yang bolos TAK punya baris. Helper ini menghitung alpha **saat laporan dibuka** (tanpa cron, tanpa nulis baris): `::alpha_rows($dari,$sampai,$group_id,$tipe)` / `::hitung_alpha(...)`. Aturan: hari aktif ikut **`absensi_jadwal` per group** (group tanpa jadwal → Sen–Jum + `absensi_jam_keluar` global) · tanggal di `absensi_libur` → bukan hari aktif · user dihitung **sejak `users.created_at`** · alpha baru sah **setelah jam pulang hari itu lewat** (hari berjalan = "belum absen", masa depan tak pernah alpha) · sudah punya rekap → bukan alpha. Baris alpha **virtual** (`id=null`, `virtual=true`) — ubah status = INSERT, bukan UPDATE. Dipakai `/laporan`, `/laporan/summary`, DAN export.
- **[GeoHelper.php](includes/helpers/GeoHelper.php)** — `::haversine($lat1,$lng1,$lat2,$lng2)` → meter. `::is_valid()` range cek.
- **[FileHelper.php](includes/helpers/FileHelper.php)** — `::save_selfie($base64,$user_id)` → `uploads/absensi-selfie/Y/m/`, validasi magic bytes (JPEG `ffd8ff`/PNG `89504e47`), cap 5MB, nama random. `::save_bukti()` (izin/sakit, terima JPG/PNG/PDF — dormant tapi ada). `::selfie_url()`/`::file_url()` → URL publik. Guard `.htaccess`+`index.php` di folder upload.

---

## Frontend

**Alpine.js + Tailwind CSS via CDN — TANPA Vite/build step.** (Plan menyebut Vite — TIDAK dipakai.)

- Public: [public/js/public.js](public/js/public.js) + `public/css/public.css`, enqueue global di `wp_enqueue_scripts`, config var `AbsensiConfig` (`restUrl, nonce, rfidDebounce, akurasiMax`). Alpine + Tailwind dari CDN (`enqueue_frontend_cdn`, filterable, `defer` via `script_loader_tag`).
- Admin: `admin/js/admin.js` + `admin/css/admin.css`, enqueue hanya di halaman plugin (`str_contains($hook,'absensi')`), config var `AbsensiAdmin` (`restUrl, nonce, rfidDebounce, settings{...}`). **`absensi_wa_token` TIDAK di-inject** (sensitif). Tailwind admin: preflight dimatikan agar tak merusak wp-admin.
- **Shortcode** ([includes/class/Shortcodes.php](includes/class/Shortcodes.php)): `[absensi_siswa]` + `[absensi_guru]` = surface kiosk. `[absensi_siswa]` publik; `[absensi_guru]` markup-nya tanpa cek cap, **tapi page-nya login-gated di level `template_redirect`** (`Plugin::gate_kiosk_guru`, cap `absensi_rfid`) + endpoint `/absen/rfid` juga auth. `[absensi_selfie]`/`[absensi_status]` = shortcode lama (masih gate login — sisa model lama, tak dipakai kiosk). Render `ob_start()` + `include public/views/{view}.php`; view belum ada → placeholder (bukan error). **`[absensi_ortu]` sudah dihapus.**
- **Auto-page:** `Installer::seed_pages()` (dipanggil di `activate()`, BUKAN maybe_upgrade) buat **2 page** saat aktivasi: **`/absensi`** (siswa, publik) + **`/absensi/guru`** (guru, **child** dari page siswa via `post_parent`, **login-gated**). Isi shortcode masing-masing. ID di option `absensi_pages` `{siswa,guru}`; dibuat plugin dicatat di `absensi_pages_created`. Idempotent; page user ber-slug sama diadopsi. **Deactivate: `remove_pages()`** hapus page buatan plugin (syarat: ada di created-list DAN konten masih memuat shortcode-nya); page adopsi/repurpose user dibiarkan. Guru disembunyikan dari nav publik via filter `get_pages` (`Plugin::hide_guru_page_public`).
- **Admin menu** ([includes/Admin/Menu.php](includes/Admin/Menu.php)): menu "Absensi" + **6 submenu** (Dashboard, **Users** `absensi-users`, **Group** `absensi-group`, **Jadwal** `absensi-jadwal` (v2.2.0 — 2 tab: jadwal per group + hari libur), Laporan, Pengaturan). **Semua cap `manage_options`** (admin-only). Render `admin/views/{slug}.php`, fallback "View belum tersedia" jika file tak ada. (Submenu "Absen RFID" dibuang — bind kartu di Users via `/users/{id}/rfid`, tap absen di kiosk login-gated `/absensi/guru`.)

> **Boundary FE/BE:** file `*/views/*.php` (markup) + `*/js/**` + `*/css/**` = tanggung jawab FE. Kontrak REST untuk FE ada di [HANDOFF-FE.md](HANDOFF-FE.md).

---

## Akun Guru (kiosk RFID login-gated)

Fitur 2026-07-10, **sebagian membalik pivot** (role masuk lagi). RFID = absen **siswa** oleh **guru**; tiap guru pakai device sendiri + USB RFID reader, login dulu, tap kartu **siswa**. Login = gerbang akses device (bukan identitas absen). Rincian & tes: `TODO-BE-AKUN-GURU.md`, `tests/_pivot_akun_guru_*_test.php`, `tests/manual/UC-akun-guru-*.md`.

- **Role `guru` + cap** ([Installer.php](includes/Installer.php)): `seed_roles()` (di `activate()`) daftarkan role `guru` (caps `read` + `absensi_rfid`, **tanpa** cap admin lain); cap `absensi_rfid` juga di `administrator`. Const `Installer::CAP_RFID = 'absensi_rfid'`. `uninstall.php` mencabut cap + hapus role. **Deactivate TIDAK hapus role** (jaga akun guru). Guru = **per-individu** (didaftarkan admin).
- **Gate page** ([Plugin.php](includes/Plugin.php) `gate_kiosk_guru`, hook `template_redirect`): page `absensi_pages['guru']` butuh cap `absensi_rfid` → belum login `auth_redirect()` (redirect wp-login, balik otomatis); login tapi tak berhak → `wp_die(403)`. Page siswa TIDAK di-gate.
- **Blok wp-admin utk guru** ([Plugin.php](includes/Plugin.php) `block_admin_for_guru`): guru buka wp-admin → `wp_safe_redirect` ke kiosk. DUA hook: `admin_init` + `admin_page_access_denied` (yang kedua wajib — `menu.php` `wp_die` cap tinggi jalan sebelum `admin_init`). AJAX/cron dikecualikan. Admin bebas.
- **Admin bar disembunyikan utk guru** ([Plugin.php](includes/Plugin.php) `hide_admin_bar_for_guru`, filter `show_admin_bar`): garis hitam WP tak muncul di kiosk guru (kiosk bersih). Administrator tetap punya admin bar.
- **Endpoint `/absen/rfid`** ([AbsensiEndpoint.php](includes/api/AbsensiEndpoint.php) `can_absen_rfid`): wajib login + cap `absensi_rfid` + nonce. `/absen/selfie` & `/absen/status` **tetap publik**.
- **`POST /guru/import`** ([UsersEndpoint.php](includes/api/UsersEndpoint.php) `import_guru`): bulk buat WP user role `guru` dari xlsx (kolom `username` wajib + `nama`/`password`/`email` opsional; validasi username unik/valid, password ≥6, email valid+unik; password kosong → auto-generate). Cap `manage_options`. Respons `{ imported, gagal, errors:[{baris,pesan}] }`. Reuse pola `/users/import`.
- **Page guru = child** `/absensi/guru` (slug `guru`, `post_parent` = page siswa) + disembunyikan dari nav publik (`hide_guru_page_public` filter `get_pages`). Fresh install lewat `seed_pages()`; install existing via script one-time. Nav site (block theme twentytwentyfive pakai `core/page-list`) menghormati filter → guru tak muncul di nav anon. Kalau FE ganti ke `core/navigation` hardcoded → perlu sembunyikan manual (FE).
- **FE menyusul** (koordinasi, lihat `TODO-FE-AKUN-GURU.md`): kiosk guru JS kirim header `X-WP-Nonce`; tombol Import Guru (upload xlsx → `/guru/import`); login = wp-login bawaan (nol form).

---

## Konvensi (ikuti saat menulis kode)

- `defined( 'ABSPATH' ) || exit;` di baris atas tiap file PHP.
- **Semua** query lewat `$wpdb->prepare()`. Untuk WHERE dinamis: rakit dari fragmen yang sudah di-`prepare` (lihat pola `$where_parts` di LaporanEndpoint), jangan concat raw input.
- Sanitasi lewat `SanitizeHelper` sebelum DB; output escape `esc_html/esc_attr/esc_url`.
- String UI lewat i18n `__()/esc_html__()` text domain `absensi-sekolah`.
- Waktu pakai `current_time()` / `wp_timezone()` (lihat `tentukan_status_masuk` di AbsensiEndpoint untuk pola hitung telat yang benar timezone-nya).
- PHP 8: typed properties, `str_contains/str_starts_with`, named args, union return (`string|\WP_Error`).
- Endpoint admin baru → `current_user_can('manage_options')` (jangan role-check). Endpoint publik baru → anti-abuse wajib (rate-limit/debounce).
- Komentar & identifier domain dalam Bahasa Indonesia (ikuti gaya existing).

---

## Build / Run / Test

> ⚠️ **WAJIB: setiap selesai mengerjakan fitur apa pun, tes dulu sebelum melapor selesai.** Minimal: `php -l` file yang diubah, lalu uji perilaku nyata — panggil endpoint/handler, query verifikasi data, atau jalankan test terkait. Sertakan output sebagai bukti. Bila tak bisa dites, sebutkan eksplisit apa yang belum terverifikasi.

- **Tidak ada build step (no Vite/npm/package.json).** Edit PHP/JS/CSS langsung, refresh. Alpine+Tailwind via CDN.
- Lingkungan: Local (Flywheel) di `c:\Users\hafiz\Local Sites\absensi-sekolah\`. Site MySQL harus running (kalau tidak: "Error establishing a database connection").
- **Skema DB auto-sync** via `maybe_upgrade()` saat `plugins_loaded` (tak perlu re-activate). Aktivasi penuh (`activate()`) tetap: create tables + seed options + seed 2 page + schedule retensi.
- HTTPS wajib di produksi (Geolocation API + kamera). Endpoint absen enforce `is_ssl()` (bisa dimatikan dev via filter `absensi_enforce_ssl`).
- **Menjalankan test** (WP-bootstrap, butuh binary + ini Local — bukan `php`/`wp` di PATH): lihat memory `php-cli-test-recipe`. Pola: `& $php -c $ini script.php 2>$null`.
  - **Regresi v2:** `tests/run-all.php` = **agregator** yang spawn tiap `_migrate_v2_test.php` + `_pivot_*_test.php` sebagai proses terpisah, jumlahkan PASS/FAIL. Target hijau. File `_pivot_x_test.php` baru otomatis ikut.
  - Tiap fitur punya UC manual di `tests/manual/UC-pivot-*.md`.
  - **PHPUnit + Brain Monkey** (`tests/unit/`, tanpa DB): `& $php -c $ini vendor\phpunit\phpunit\phpunit`. (`tests/` & `vendor/` gitignored.)
- **Composer** (tak ada `php` di PATH): `& $php -c $ini "C:\ProgramData\ComposerSetup\bin\composer.phar" <cmd>`.

---

## Gap & TODO yang diketahui (jangan asumsikan sudah ada)

- **Hapus group TIDAK menghapus jadwalnya** (jadwal yatim) — sama polanya dengan hapus user yang meninggalkan rekap yatim (baris rekap tetap ada, `nama`/`nomor_induk` jadi null di laporan). Keduanya belum ditangani.
- **View = tugas FE.** `admin/views/` berisi view lama pra-pivot (dashboard/laporan/settings + orphan siswa/kelas/jadwal) yang perlu di-rework ke skema v2 oleh FE; menu butuh `users.php`/`group.php`. `public/views/siswa.php`/`guru.php` (kiosk) belum ada. (Submenu + view `rfid.php` sudah dibuang.)
- **Izin/sakit = LUAR MVP.** Endpoint pengajuan + approve guru sudah **dihapus** (git history menyimpan). Kolom `izin_tipe`/`bukti_status`/`bukti_path` di rekap + `FileHelper::save_bukti()` **dibiarkan** (dormant). Saat masuk roadmap: pengajuan kiosk by nomor_induk + bukti, approve wp-admin `manage_options`.
- **Notifikasi WA = LUAR MVP, dicabut.** `includes/Notifikasi.php` dihapus, `Notifikasi::init()` dilepas dari boot. Action `absensi_absen_masuk`/`absensi_absen_keluar` **tetap di-fire** endpoint (titik colok). Resep hidupkan lagi tanpa role: kolom `no_wa` di `absensi_users` + `recipients()` = `SELECT no_wa`.
- **Role `guru` AKTIF** (fitur Akun Guru): di-seed `seed_roles()`, dibaca gate `absensi_rfid` (§Akun Guru). Role pra-pivot LAIN (`absensi_admin`/`absensi_siswa`/`orang_tua`) mungkin masih nyangkut di DB tapi **tak ada gate yang membacanya** — tak berbahaya, kehapus saat DELETE plugin (`uninstall.php`).
- **uninstall.php ADA** (drop tabel absensi_* + hapus option saat plugin dihapus). `deactivate()` = `remove_pages()` + unschedule retensi + flush.
- Param `foto` di `/absen/selfie`: arg `required => false` (agar sesi **pulang** tak wajib foto), TAPI sesi **masuk** di-enforce handler → foto **WAJIB**, kosong = `422 foto_wajib` (kebijakan kiosk: selfie = bukti hadir). Nama file: `selfie_{NIS}_{DD-MM-YYYY}-{Masuk|Keluar}_{8hex}.{ext}` (`FileHelper::save_selfie( $b64, $id, $nomor_induk, $sesi )`).
- Belum ada: CI, cek relasi ortu (dibuang), granular caps (semua `manage_options`).

---

## Divergensi: kode vs plans/README

`plugins/includes/plans/` (`01_BACKEND_PLAN.md` dll) = desain target yang **jauh lebih ambisius & sebagian usang** (pra-pivot). **Jangan** pakai sebagai kontrak struktur — ikuti kode.

| Aspek | Plan | Kode nyata (v2) |
|---|---|---|
| Model auth | login siswa + role guru/ortu | selfie siswa **publik tanpa login**; RFID **login-gated** (role `guru`/admin, cap `absensi_rfid`); data admin `manage_options` |
| Tabel master | `absensi_siswa` + `absensi_kelas` + `absensi_wali` | `absensi_users` + `absensi_group` (wali dibuang) |
| Identitas absen | user WP login | **`nomor_induk`** / `rfid_uid` (data, bukan akun) |
| Tabel absensi | `absensi_log`, 2 baris/sesi | `absensi_rekap`, **1 baris/hari** (`waktu_masuk`+`waktu_keluar`) |
| Settings | 1 option serialized | option **individual** `absensi_*` |
| Arsitektur | Controller→Service→Repository | query `$wpdb` **langsung** di endpoint |
| Frontend | Alpine + Tailwind + **Vite** | Alpine + Tailwind **via CDN, no build** |
| Notif/izin/ortu | fitur inti | **luar MVP** (dicabut/dihapus) |

**Saat menambah fitur:** ikuti pola kode yang ADA (v2), bukan plan — kecuali user eksplisit minta refactor. Plan berguna sebagai referensi niat/edge-case, bukan kontrak.
