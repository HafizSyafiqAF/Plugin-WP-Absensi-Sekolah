# CLAUDE.md — Plugin WP Absensi Sekolah

Panduan untuk Claude Code saat bekerja di plugin ini. **Kode = sumber kebenaran.** Dokumen `plans/` dan `README.md` bersifat aspiratif dan sebagian menyimpang dari implementasi nyata (lihat §Divergensi).

---

## Peran & Batasan

Pekerjaan saat ini: **Frontend Developer**. Backend dikerjakan oleh developer terpisah.

**Claude DILARANG menyentuh file Backend**, termasuk:

- `absensi-sekolah.php` (entry point plugin)
- `includes/` — semua file PHP di dalamnya:
  - `includes/Plugin.php`
  - `includes/Installer.php`
  - `includes/api/` (REST endpoint)
  - `includes/class/Shortcodes.php`
  - `includes/helpers/`
  - `includes/Admin/Menu.php`

Jika permintaan membutuhkan perubahan Backend → **tolak dan sampaikan ke Backend developer**.

**Lingkup file yang boleh disentuh:**

| Folder / File | Keterangan |
|---|---|
| `assets/src/` | Source JS & CSS sebelum build |
| `assets/dist/` | Output Vite (ter-generate otomatis, jangan edit manual) |
| `admin/views/` | View PHP halaman admin (render HTML template saja, tanpa logika DB) |
| `public/views/` | View PHP publik / shortcode output |
| `vite.config.js` | Konfigurasi Vite |
| `package.json` | Dependensi npm (Alpine, Tailwind, Vite, Leaflet) |
| `tailwind.config.js` | Konfigurasi Tailwind |
| `postcss.config.js` | Konfigurasi PostCSS |

> View PHP di `admin/views/` dan `public/views/` boleh disentuh karena fungsinya **hanya render HTML/template** — bukan logika bisnis atau query DB.

---

## Ringkasan

Plugin WordPress untuk absensi sekolah, MVP. Dua mode absen:
1. **Selfie + GPS** — siswa absen mandiri via browser HP (validasi radius haversine).
2. **RFID USB scanner** — guru tap kartu siswa (scanner = HID keyboard, "ketik" UID + Enter).

Plus dashboard admin (WP admin) + laporan rekap. Stack: PHP 8.0+, WordPress 6.0+, custom table `$wpdb` (bukan CPT), REST API, **Alpine.js + Tailwind + Vite** (dibangun sebagai FE terpisah di `assets/`).

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

### Tech Stack

| Item | Pilihan |
|---|---|
| Framework JS | **Alpine.js** ^3 — bundled via Vite, BUKAN CDN |
| Styling | **Tailwind CSS** — build-time (purge), BUKAN CDN |
| Build tool | **Vite** |
| HTTP | `fetch` native + wrapper `apiClient.js` (inject nonce otomatis) |
| Ikon | **Lucide** (outline), **Leaflet** (map picker settings) |

### Struktur Asset

```
assets/
├── src/
│   ├── siswa.js        # Alpine: kamera, GPS, pilih sesi, submit selfie
│   ├── guru.js         # Alpine: RFID listener, resolve, submit, toggle ENROLL
│   ├── admin.js        # filter tanggal/kelas/sesi, export, enroll, settingsMap
│   ├── ortu.js         # Alpine: view-only absensi anak
│   ├── rfid.js         # shared HID buffer/parser (dipakai guru.js & admin enroll)
│   ├── apiClient.js    # fetch + X-WP-Nonce wrapper + retry nonce
│   └── app.css         # Tailwind entry + design tokens + Leaflet CSS
└── dist/               # output Vite (enqueue di PHP oleh Backend)
```

### Empat Surface

| Surface | Pengguna | Konteks | Shortcode / Halaman |
|---|---|---|---|
| **Siswa** | Siswa | HP, selfie+GPS | `[absensi_siswa]` (alias `[absensi_selfie]`) |
| **Guru** | Guru | Laptop, RFID scanner | `[absensi_guru]` |
| **Admin** | Admin | WP admin dashboard | WP admin menu |
| **Orang tua** | Ortu | HP, view-only | `[absensi_ortu]` |

### Design Tokens (ikuti saat menulis CSS/Tailwind)

```css
:root {
  --c-primary:        #2563EB;  /* blue-600  tombol utama */
  --c-primary-hover:  #1D4ED8;  /* blue-700 */
  --c-primary-soft:   #DBEAFE;  /* blue-100 */

  --c-success:        #16A34A;  /* hadir / valid */
  --c-success-soft:   #DCFCE7;
  --c-warning:        #D97706;  /* telat */
  --c-warning-soft:   #FEF3C7;
  --c-danger:         #DC2626;  /* ditolak / luar radius / UID tak dikenal */
  --c-danger-soft:    #FEE2E2;
  --c-info:           #0891B2;  /* izin / sakit / sesi pulang */

  --c-bg:             #F8FAFC;
  --c-surface:        #FFFFFF;
  --c-border:         #E2E8F0;
  --c-text:           #0F172A;
  --c-text-muted:     #64748B;
}
```

Pemetaan status → warna:
| Status | Token |
|---|---|
| Hadir | success (hijau) |
| Telat | warning (oranye) |
| Alpha / ditolak | danger (merah) |
| Izin / Sakit | info (cyan) |
| Sesi Masuk | primary (biru) |
| Sesi Pulang | info (cyan) |

### Komponen per Surface

**Siswa (HP)**
- `SesiSwitcher` — segmented Masuk/Pulang (auto-suggest by jam, bisa override)
- `CameraView` — `<video>` live + frame guide oval wajah
- `GpsStatusChip` — chip kecil akurasi GPS (hijau/oranye/merah)
- `CapturePreview` — foto + tombol Ulangi/Kirim
- `ResultCard` — hasil absen: ikon ✓/✗ + sesi + status + jam + jarak

**Guru (laptop)**
- `KelasSelector` — dropdown kelas (sticky atas)
- `SesiSwitcher` — Masuk/Pulang
- `ModeSwitcher` — toggle "Absen | Daftar Kartu"
- `RfidInputPad` — area tap kartu, input hidden auto-focus, pulse animasi
- `EnrollPad` — search siswa + "Tap kartu untuk [Nama]..." + hasil
- `ScanResultToast` — toast tiap tap (auto-dismiss 3s)
- `TodayList` — daftar scan hari ini + counter hadir

**Admin (WP admin)**
- `FilterBar` — date range + kelas + preset periode + tombol Terapkan
- `RekapTable` — tabel absensi, kolom Masuk (jam+metode) / Pulang (jam+metode), badge status
- `SummaryCards` — 4 kartu: Hadir / Telat / Izin-Sakit / Alpha
- `ExportMenu` — dropdown: Excel · PDF · CSV (server-side) · Cetak
- `EnrollPanel` — search siswa → tap / input UID manual → status
- `SettingsForm` — koordinat (map picker Leaflet), radius slider, jam masuk/keluar, window double-tap

**Orang tua (HP, view-only)**
- `ChildSelector` — pilih anak (kalau >1), inisial nama
- `AbsensiTimeline` — daftar per tanggal: chip masuk/pulang + status badge + jam
- `MonthSummary` — ringkasan bulan: hadir/telat/izin-sakit/alpha

### Aturan Storage (data sensitif)

| Data | Storage | Alasan |
|---|---|---|
| Foto selfie | **Memory** saja | Privasi, buang setelah submit |
| Koordinat GPS | **Memory** saja | Sensitif, kirim sekali saat submit |
| Nonce REST | **Memory** (via `wp_localize_script`) | Per page-load |
| Buffer RFID (UID) | **Memory** | Transient, clear tiap Enter |
| Draft guru (kelas/sesi/mode) | **sessionStorage** | Hilang saat tab tutup |
| Preferensi filter admin | **localStorage** (`absensi_admin_filter`) | Non-sensitif |

**Aturan wajib:**
1. Data sensitif (foto, GPS, nonce, UID) → **JANGAN localStorage / cookie non-HttpOnly**. Memory only.
2. Selalu `try/catch` saat akses storage (mode privat browser bisa throw).

---

## Konvensi

### Backend (PHP)
- `defined( 'ABSPATH' ) || exit;` di baris atas tiap file PHP.
- **Semua** query lewat `$wpdb->prepare()`. Untuk WHERE dinamis: rakit dari fragmen yang sudah di-`prepare` (lihat pola `$where_parts` di LaporanEndpoint), jangan concat raw input.
- Sanitasi lewat `SanitizeHelper` sebelum DB; output escape `esc_html/esc_attr/esc_url`.
- String UI lewat i18n `__()/esc_html__()` text domain `absensi-sekolah`.
- Waktu pakai `current_time()` / `wp_timezone()`, bukan `time()` server langsung untuk tanggal.
- PHP 8: typed properties, `str_contains/str_starts_with`, named args, union return (`string|\WP_Error`).
- Komentar & identifier domain dalam Bahasa Indonesia (ikuti gaya existing).

### Frontend (JS/CSS/HTML)
- **Alpine.js** untuk reaktivitas; gunakan `x-data`, `x-bind`, `x-on`, `@click`, dll.
- **Tailwind** untuk styling; class utility langsung di HTML; jangan tulis CSS custom kecuali untuk design tokens.
- **`rfid.js`** adalah shared module untuk parsing HID — jangan duplikat logika RFID di file lain.
- **`apiClient.js`** wajib dipakai untuk semua REST call (bukan raw `fetch`) agar nonce di-inject otomatis.
- Config REST diambil dari `AbsensiConfig` (publik) dan `AbsensiAdmin` (admin) — di-inject oleh Backend via `wp_localize_script`.
- Semua string UI lewat `__()` / `esc_html__()` text domain `absensi-sekolah` (siap i18n).
- Tap target minimal **44px**, kontras teks minimal **4.5:1 (AA)**.
- Status dan sesi WAJIB ditunjukkan dengan **ikon + label + warna** (jangan andalkan warna saja).
- `aria-live` untuk hasil scan, hasil absen, hasil enroll (screen reader).
- Tombol submit WAJIB `disabled` saat `submitting` (cegah double submit).
- Foto selfie: resize max 1280px sisi panjang, JPEG q0.7 via `<canvas>` sebelum upload.
- RFID: jangan hardcode panjang UID; gunakan Enter sebagai terminator; anti double-tap window (default 3s).

---

## Build / Run / Test

> ⚠️ **WAJIB: setiap selesai mengerjakan fitur apa pun, Claude harus mengetesnya dulu sebelum melapor selesai.** Jangan klaim "selesai" tanpa bukti jalan. Minimal: PHP lint (`php -l`) file yang diubah + `npm run build` bersih, lalu uji perilaku nyata. Sertakan output sebagai bukti.

```bash
npm run build   # output ke assets/dist/ — wajib dijalankan setelah edit assets/src/
npm run dev     # watch mode (development)
```

- Lingkungan: Laragon di `c:\laragon\www\absensi-sekolah\`.
- Aktivasi plugin men-trigger `Installer::activate()` (buat tabel + seed options). Setelah ubah skema DB → deactivate + activate ulang.
- HTTPS wajib di produksi (Geolocation API + kamera). Local biasanya jalan via domain `.local`.
- Tailwind `content` harus scan semua `.php` dan `.js` agar purge benar.

**Alur kerja FE:**
1. Identifikasi surface mana yang dikerjakan (Siswa / Guru / Admin / Ortu).
2. Kerjakan hanya pada file di `assets/src/`, `admin/views/`, atau `public/views/`.
3. Jalankan `npm run build` setelah selesai, verifikasi di browser.
4. Jika fitur butuh perubahan Backend (endpoint baru, skema DB, enqueue baru) → **sampaikan ke Backend developer**, jangan ubah sendiri.

---

## Error & Edge Case yang Harus Ditangani (FE)

| Kasus | Handling UI |
|---|---|
| Browser tolak izin kamera/GPS | Banner instruksi aktifkan izin (persistent) |
| Bukan HTTPS | Blok form, tampilkan pesan "Absen butuh koneksi aman (HTTPS)" |
| GPS tidak akurat | Tahan submit, "Tunggu sinyal lebih akurat..." |
| Nonce expired (403) | Auto refresh nonce → retry sekali; gagal → minta reload |
| Offline saat submit | Pesan jelas, jangan auto-queue foto (privasi) |
| RFID double-tap | Abaikan dalam window 3s (client) |
| Scanner kirim karakter aneh | `rfid.js` normalisasi; UID kosong/invalid → tolak, tetap focus |
| Enroll: kartu sudah dipakai (409) | Toast merah "Kartu milik [Nama]" |
| Enroll: siswa sudah punya kartu | Konfirmasi dialog replace sebelum POST |
| Sudah absen sesi ini (409) | "Sudah absen [masuk/pulang] hari ini" |
| Di luar radius (422) | ResultCard ✗ merah + jarak meter, izinkan ulang |

---

## Gap & TODO yang diketahui (jangan asumsikan sudah ada)

**Backend (perlu dikerjakan BE):**
- **Export Excel/PDF belum ada** — tidak ada PhpSpreadsheet/Dompdf/`vendor/`. `/laporan` hanya balas JSON; endpoint `/laporan/export?format=xlsx|pdf|csv` belum diimplementasi.
- **Shortcode belum lengkap:** `[absensi_siswa]`, `[absensi_guru]`, `[absensi_ortu]` belum didaftarkan di `Shortcodes.php`.
- **Enqueue lama:** `Plugin.php` masih enqueue `public.js`/`admin.js` lama, belum membaca manifest Vite (`assets/dist/`).
- **Role belum di-seed:** `guru`, `absensi_admin`, `orang_tua` dirujuk di permission check tapi Installer tidak membuatnya.
- **Endpoint RFID enroll/resolve belum ada:** `POST /absen/rfid/enroll` + `GET /absen/rfid/resolve?uid=` (R1/K8).
- **Tidak ada uninstall.php** — drop table belum ditangani; `deactivate()` cuma `flush_rewrite_rules()`.
- **Tidak ada:** capability granular, WaliLinker (`absensi_wali` tabel + endpoint), `JadwalEndpoint` per kelas.
- **Inject config:** `rfidDebounce`, `akurasi_max`, `retensi_hari` belum di-inject ke `AbsensiConfig`/`AbsensiAdmin`.
- **SiswaEndpoint bug:** `create_siswa()` tidak cek `$wpdb->last_error` → tetap balas 201 walau insert duplikat NIS gagal.

**Frontend (sudah dikerjakan):**
- ✅ Semua view admin + public sudah ada dan di-redesign.
- ✅ `apiClient.js`, `siswa.js`, `guru.js`, `admin.js`, `ortu.js`, `rfid.js` sudah implementasi penuh.
- ✅ Enroll endpoint sudah pakai `POST absen/rfid/enroll` (menunggu BE implement).
- ✅ FilterBar preset periode (Hari Ini/Minggu/Bulan).
- ✅ ExportMenu mengarah ke server `/laporan/export` (menunggu BE implement).

---

## Divergensi: kode vs plans/README

`plugins/includes/plans/` memuat `01_BACKEND_PLAN.md`, `02_FRONTEND_PLAN.md`, `03_UIUX_PLAN.md` — desain target yang **lebih ambisius** dari yang dibangun. Perbedaan utama:

| Aspek | Plan | Kode nyata |
|---|---|---|
| Tabel absensi | `absensi_log`, 2 baris/sesi (masuk+pulang), UNIQUE `(siswa,tanggal,sesi)` | `absensi_rekap`, 1 baris/hari, kolom `waktu_masuk`+`waktu_keluar` |
| Relasi ortu | tabel `absensi_wali`, endpoint `/child/logs` | belum ada |
| Settings | 1 option serialized `absensi_settings` | option individual `absensi_*` |
| Endpoint | `/checkin/*`, `/rfid/resolve`, `/rfid/enroll`, `/wali`, `/settings` | `/absen/*`, `/siswa/*`, `/laporan/*` |
| Auth | capability granular (`absensi_submit_self` dll) | role check `array_intersect` |
| Autoload | Composer PSR-4 + `vendor/` bundled | `spl_autoload` manual, no vendor |
| Frontend | Alpine.js + Tailwind + Vite | **Alpine.js + Tailwind + Vite** ✅ (sudah diimplementasi) |
| Arsitektur | Controller→Service→Repository | query `$wpdb` langsung di endpoint |

**Saat menambah fitur:** ikuti pola kode yang ADA sekarang, bukan plan — kecuali user eksplisit minta refactor ke arah plan.
