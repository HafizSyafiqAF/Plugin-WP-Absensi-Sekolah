# CLAUDE.md — Plugin WP Absensi Sekolah

Panduan untuk Claude Code saat bekerja di plugin ini. **Kode = sumber kebenaran.** Dokumen `plans/` dan `README.md` bersifat aspiratif dan sebagian menyimpang dari implementasi nyata (lihat §Divergensi).

---

## Ringkasan

Plugin WordPress untuk absensi sekolah, MVP. Dua mode absen:
1. **Selfie + GPS** — siswa absen mandiri via browser HP (validasi radius haversine).
2. **RFID USB scanner** — guru tap kartu siswa (scanner = HID keyboard, "ketik" UID + Enter).

Plus dashboard admin (WP admin) + laporan rekap. Stack: PHP 8.0+, WordPress 6.0+, custom table `$wpdb` (bukan CPT), REST API, **Alpine.js + Tailwind CSS (via CDN)** (tanpa Vite/build tool).

---

## Arsitektur

**Entry point:** [absensi-sekolah.php](absensi-sekolah.php) — header plugin, konstanta (`ABSENSI_*`), autoloader, register activation/deactivation, boot di `plugins_loaded`.

**Autoload:** `spl_autoload_register` sederhana (BUKAN Composer/PSR-4, tidak ada `vendor/`). Namespace `Absensi\` → `includes/`. Segmen namespace = nama folder **case-sensitive**:

| Namespace | Folder |
|---|---|
| `Absensi\Plugin`, `Absensi\Installer` | `includes/` |
| `Absensi\api\*` | `includes/api/` |
| `Absensi\class\*` | `includes/class/` |
| `Absensi\helpers\*` | `includes/helpers/` |
| `Absensi\Admin\*` | `includes/Admin/` |

> ⚠️ `class` adalah reserved word PHP tapi dipakai sebagai segmen namespace (`Absensi\class\Shortcodes`) — legal dalam konteks namespace, tapi tak biasa. Pertahankan saat menambah file di folder itu.

**Bootstrap:** [includes/Plugin.php](includes/Plugin.php) — singleton. `boot()` register: PostTypes, 3 REST endpoint (`rest_api_init`), Admin Menu (`if is_admin()`), Shortcodes, enqueue asset public + admin.

**Pola REST:** Controller endpoint langsung query `$wpdb` di dalam handler (TIDAK ada layer Service/Repository terpisah — plan menyebut pola itu tapi belum ada). Sanitasi lewat `SanitizeHelper` sebelum insert/update.

---

## Database

Tabel custom dibuat di [includes/Installer.php](includes/Installer.php) via `dbDelta` saat aktivasi. Prefix `{$wpdb->prefix}absensi_`:

| Tabel | Isi | Index penting |
|---|---|---|
| `absensi_siswa` | master siswa + `rfid_uid` + `user_id` (WP) | UNIQUE `nis`, UNIQUE `rfid_uid` |
| `absensi_kelas` | kelas + `guru_id` (WP user wali) | PK |
| `absensi_jadwal` | jam masuk/keluar per kelas per `hari` (1=Senin) | KEY `kelas_id` |
| `absensi_rekap` | **1 baris per siswa per tanggal** | UNIQUE `(siswa_id, tanggal)`, KEY `tanggal`, `kelas_id` |

**Model rekap (penting):** satu hari = satu baris. Kolom `waktu_masuk` + `waktu_keluar` di baris yang sama. RFID tap pertama → insert (`waktu_masuk`), tap kedua → `UPDATE` set `waktu_keluar`. **Bukan** dua baris masuk/pulang terpisah. `status` ENUM(`hadir,telat,izin,sakit,alpha`), `mode` ENUM(`selfie,rfid,manual`).

**Versi skema:** `Installer::DB_VERSION` + option `absensi_db_version`. Tidak ada migration runner — `dbDelta` hanya jalan saat aktivasi. Ubah skema → naikkan `DB_VERSION` DAN re-activate plugin (atau tambah runner).

**Settings = wp_options individual** (BUKAN satu blob serialized): `absensi_lat`, `absensi_lng`, `absensi_radius` (default 100m), `absensi_jam_masuk` (`07:00`), `absensi_jam_keluar` (`15:00`), `absensi_telat_menit` (15), `absensi_wa_gateway`, `absensi_wa_token`. Di-seed di `Installer::seed_default_options()`.

---

## REST API

Namespace `absensi/v1` (`/wp-json/absensi/v1/`). Konstanta `NAMESPACE` diulang di tiap kelas endpoint.

| Method | Endpoint | Permission | File |
|---|---|---|---|
| POST | `/absen/selfie` | login | [AbsensiEndpoint.php](includes/api/AbsensiEndpoint.php) |
| POST | `/absen/rfid` | guru/admin | AbsensiEndpoint |
| GET | `/absen/status` | login | AbsensiEndpoint |
| GET/POST | `/siswa` | manage (admin/guru) | [SiswaEndpoint.php](includes/api/SiswaEndpoint.php) |
| GET/PUT/DELETE | `/siswa/{id}` | manage | SiswaEndpoint |
| POST | `/siswa/{id}/rfid` | manage | SiswaEndpoint |
| GET | `/laporan` | view | [LaporanEndpoint.php](includes/api/LaporanEndpoint.php) |
| GET | `/laporan/summary` | view | LaporanEndpoint |

**Auth model (sederhana, role-based — belum granular caps):**
- Cookie WP + nonce `wp_rest` (header `X-WP-Nonce`), di-inject via `wp_localize_script` → `AbsensiConfig`/`AbsensiAdmin` `{ restUrl, nonce }`.
- `is_logged_in()` untuk selfie/status.
- `is_guru_or_admin()` / `can_manage()` = `array_intersect($user->roles, ['administrator','absensi_admin','guru'])`.
- `can_view()` (laporan) menambah role `orang_tua`.

**Format response:** sukses `WP_REST_Response([...], 2xx)`. Error via helper `error($code,$msg,$status)` → `['code','message']`. Status: 403 (luar radius / cap kurang), 404 (siswa/UID tak ada), 409 (sudah absen / UID bentrok).

---

## Helpers (`includes/helpers/`)

- **[SanitizeHelper.php](includes/helpers/SanitizeHelper.php)** — WAJIB sebelum tiap `$wpdb->insert/update`. `::siswa()`, `::rekap()` (whitelist `status`/`mode`), `::rfid_uid()` (strip non-hex, uppercase, trim CR/LF dari HID).
- **[GeoHelper.php](includes/helpers/GeoHelper.php)** — `::haversine($lat1,$lng1,$lat2,$lng2)` → meter. `::is_valid()` range cek.
- **[FileHelper.php](includes/helpers/FileHelper.php)** — `::save_selfie($base64,$siswa_id)` → simpan ke `uploads/absensi-selfie/Y/m/`, validasi magic bytes (JPEG `ffd8ff` / PNG `89504e47`), cap 5MB, return path relatif. `::selfie_url()` konversi ke URL publik.

---

## Frontend

**Alpine.js + Tailwind CSS via CDN — TANPA Vite/build step.** (Plan menyebut Vite — TIDAK dipakai; Alpine & Tailwind dimuat dari CDN, bukan di-bundle.)

- Public: [public/js/public.js](public/js/public.js) + `public/css/public.css`, enqueue global di `wp_enqueue_scripts`, config var `AbsensiConfig`. Alpine + Tailwind dari CDN.
- Admin: `admin/js/admin.js` + `admin/css/admin.css`, enqueue hanya di halaman plugin (`str_contains($hook,'absensi')`), config var `AbsensiAdmin`.
- **Catatan migrasi:** sebagian file JS lama masih bergaya vanilla/jQuery; arah resmi = Alpine.js (CDN). Tambah/ubah interaksi baru pakai Alpine, jangan jQuery.
- Shortcode ([includes/class/Shortcodes.php](includes/class/Shortcodes.php)): `[absensi_selfie]`, `[absensi_status]`. Render via `ob_start()` + `include` view, gate `is_user_logged_in()`.
- Admin menu ([includes/Admin/Menu.php](includes/Admin/Menu.php)): menu "Absensi" + 6 submenu (Dashboard, Siswa, Kelas, Absen RFID, Laporan, Pengaturan). Render `admin/views/{slug}.php`, fallback "View belum tersedia" jika file tak ada.

---

## Konvensi (ikuti saat menulis kode)

- `defined( 'ABSPATH' ) || exit;` di baris atas tiap file PHP.
- **Semua** query lewat `$wpdb->prepare()`. Untuk WHERE dinamis: rakit dari fragmen yang sudah di-`prepare` (lihat pola `$where_parts` di LaporanEndpoint), jangan concat raw input.
- Sanitasi lewat `SanitizeHelper` sebelum DB; output escape `esc_html/esc_attr/esc_url`.
- String UI lewat i18n `__()/esc_html__()` text domain `absensi-sekolah`.
- Waktu pakai `current_time()` / `wp_timezone()`, bukan `time()` server langsung untuk tanggal (perhatikan: kode existing campur `time()` + `current_time()` saat hitung telat).
- PHP 8: typed properties, `str_contains/str_starts_with`, named args, union return (`string|\WP_Error`).
- Komentar & identifier domain dalam Bahasa Indonesia (ikuti gaya existing).

---

## Build / Run / Test

> ⚠️ **WAJIB: setiap selesai mengerjakan fitur apa pun, Claude harus mengetesnya dulu sebelum melapor selesai.** Jangan klaim "selesai" tanpa bukti jalan. Minimal: PHP lint (`php -l`) file yang diubah, lalu uji perilaku nyata sesuai fitur — panggil endpoint REST (`curl`/`wp eval`), buka halaman shortcode/admin, atau jalankan query untuk verifikasi data tersimpan. Sertakan output/hasil sebagai bukti. Jika tak bisa dites di lingkungan ini, sebutkan eksplisit apa yang belum terverifikasi.

- **Tidak ada build step (no Vite/npm).** Edit PHP/JS/CSS langsung, refresh. Alpine.js + Tailwind dimuat dari CDN di view/enqueue.
- Lingkungan: Local (Flywheel) di `c:\Users\hafiz\Local Sites\absensi-sekolah\`.
- Aktivasi plugin men-trigger `Installer::activate()` (buat tabel + seed options). Setelah ubah skema DB → deactivate + activate ulang.
- HTTPS wajib di produksi (Geolocation API + kamera). Local biasanya jalan via domain `.local`.
- **Composer + PHPUnit sudah terpasang** (`composer.json`, `vendor/`, `phpunit.xml.dist`, `tests/unit/` Brain Monkey). Belum ada CI. **Tidak ada `package.json`/npm** (Alpine+Tailwind via CDN, bukan build).

---

## Gap & TODO yang diketahui (jangan asumsikan sudah ada)

- **Export Excel/PDF belum ada** — tidak ada PhpSpreadsheet/Dompdf/`vendor/`. `/laporan` hanya balas JSON.
- **View hilang:** admin `dashboard.php`, `siswa.php`, `kelas.php`, `laporan.php` belum ada (hanya `rfid.php` + `settings.php`). Public `status.php` belum ada → shortcode `[absensi_status]` akan `include` file tak ada (warning).
- **Role belum di-seed:** `guru`, `absensi_admin`, `orang_tua` dirujuk di permission check tapi Installer tidak membuatnya. Buat role/cap saat aktivasi bila diperlukan.
- **Tidak ada uninstall.php** — drop table belum ditangani; `deactivate()` cuma `flush_rewrite_rules()`.
- **Tidak ada:** capability granular, anti double-tap RFID (window), cek akurasi GPS, enforce `is_ssl()` di endpoint, tabel relasi ortu→anak, endpoint linking ortu.
- Param `foto` di `/absen/selfie` `required => false` (absen tanpa foto diperbolehkan saat ini).

---

## Divergensi: kode vs plans/README

`plugins/includes/plans/` (di luar folder plugin) memuat `01_BACKEND_PLAN.md`, `02_FRONTEND_PLAN.md`, `03_UIUX_PLAN.md` — desain target yang **lebih ambisius** dari yang dibangun. Perbedaan utama:

### Divergensi inti yang TETAP (sengaja beda dari plan, ikuti kode)

| Aspek | Plan | Kode nyata |
|---|---|---|
| Tabel absensi | `absensi_log`, 2 baris/sesi (masuk+pulang), UNIQUE `(siswa,tanggal,sesi)` | `absensi_rekap`, **1 baris/hari**, kolom `waktu_masuk`+`waktu_keluar` |
| Settings | 1 option serialized `absensi_settings` | option **individual** `absensi_*` |
| Arsitektur | Controller→Service→Repository | query `$wpdb` **langsung** di endpoint (no Service/Repo) |
| Frontend | Alpine.js + Tailwind + **Vite** | Alpine.js + Tailwind **via CDN, no Vite/build** |

### Konvergensi sejak brief (plan SUDAH tercapai — jangan bikin ulang)

Per 2026-06-08, fitur ini sudah dibangun (dulu tercatat "belum ada"):

- **Relasi ortu** — tabel `absensi_wali` + endpoint `/wali`, `/child/logs` **ada**. Lihat [WaliEndpoint](includes/api/WaliEndpoint.php), [ChildEndpoint](includes/api/ChildEndpoint.php).
- **Endpoint resolve/enroll/wali/settings/kelas/jadwal/export** — semua **ada**. Penamaan pakai prefix `/absen/*` (mis. `/absen/rfid/resolve`, `/absen/rfid/enroll`), bukan `/checkin/*` / `/rfid/*` ala plan.
- **Capability** — CAPS (`absensi_submit_self` dll) **di-seed** saat aktivasi (`Installer::seed_roles`). Auth **hybrid**: sebagian endpoint pakai `current_user_can(cap)` (enroll/export/child), sisanya masih role-check `array_intersect`.
- **Composer/vendor** — `composer.json` + `vendor/` **ada** (PhpSpreadsheet/Dompdf untuk export + PHPUnit/Brain Monkey dev). `spl_autoload` manual tetap **autoloader utama**; vendor dimuat kondisional bila ada.

**Saat menambah fitur:** ikuti pola kode yang ADA sekarang, bukan plan — kecuali user eksplisit minta refactor ke arah plan. Plan berguna sebagai referensi niat/edge-case, bukan kontrak struktur.
