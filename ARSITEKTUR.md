# Arsitektur — Plugin WP Absensi Sekolah

Dokumentasi teknis internal plugin. **Kode adalah sumber kebenaran**; dokumen ini menjelaskan
alasan di balik keputusan yang tidak terbaca langsung dari kode.

Untuk gambaran umum, instalasi, dan daftar fitur → [README.md](README.md).
Untuk spesifikasi tampilan (design system, layout tiap halaman) → [design.md](design.md).

---

## Ringkasan model

Plugin absensi sekolah dengan **model kiosk**: siswa/guru/staf yang diabsen adalah **data**
(baris di tabel `absensi_users`), **bukan** user WordPress. Yang punya akun WP hanya:

| Peran | Akun WP | Akses |
|---|---|---|
| Administrator | ya | wp-admin penuh (`manage_options`) |
| Guru | ya (role `guru`) | hanya kiosk RFID (`absensi_rfid`) — **tidak bisa** masuk wp-admin |
| Siswa / staf | **tidak** | tak perlu login, cukup nomor induk atau kartu RFID |

Dua mode absen:

1. **Selfie + GPS** — **publik, tanpa login**. Orang mengetik **nomor induk (NIS/NIP)** di kiosk
   `/absensi`, mengambil selfie, lalu mengirim. Server memvalidasi radius (haversine) dan akurasi GPS.
2. **RFID USB scanner** — **login-gated (guru/admin)**. Guru membuka kiosk `/absensi/guru` dari
   perangkat masing-masing, lalu menempelkan kartu **siswa**. Scanner berperilaku sebagai HID
   keyboard: ia "mengetik" UID lalu Enter. Login guru hanya gerbang akses perangkat — identitas
   kehadiran tetap berasal dari `rfid_uid` **siswa**.

Karena `/absen/selfie` dan `/absen/status` terbuka tanpa autentikasi, keduanya wajib memiliki
anti-abuse: rate-limit per nomor induk + gerbang anti-enumerasi per-IP (lihat §REST API).

Stack: PHP 8.0+ · WordPress 6.0+ · tabel custom `$wpdb` (bukan CPT) · REST API ·
Alpine.js + Tailwind CSS via CDN (**tanpa build step**).

---

## Struktur & bootstrap

**Entry point** — [absensi-sekolah.php](absensi-sekolah.php): header plugin, konstanta `ABSENSI_*`,
autoloader, registrasi activation/deactivation hook, boot pada `plugins_loaded`.

**Autoload — dua lapis:**

1. **Composer** `vendor/autoload.php` dimuat **bila ada** (opsional; dipakai PhpSpreadsheet dan
   Dompdf untuk export, serta PHPUnit saat pengembangan). `vendor/` tidak di-commit.
2. **`spl_autoload_register` manual** — autoloader utama, namespace `Absensi\` → `includes/`.
   Segmen namespace = nama folder, **case-sensitive**:

| Namespace | Folder |
|---|---|
| `Absensi\Plugin`, `Absensi\Installer`, `Absensi\Retensi` | `includes/` |
| `Absensi\api\*` | `includes/api/` |
| `Absensi\class\*` | `includes/class/` |
| `Absensi\helpers\*` | `includes/helpers/` |
| `Absensi\Admin\*` | `includes/Admin/` |

> `class` adalah reserved word PHP, tetapi legal sebagai segmen namespace
> (`Absensi\class\Shortcodes`). Tidak lazim — pertahankan pola ini saat menambah file di folder itu.

**Bootstrap** — [includes/Plugin.php](includes/Plugin.php), singleton. `boot()` dijalankan pada
`plugins_loaded` dan mengerjakan: `Installer::maybe_upgrade()` (migration runner) · `Retensi::init()` ·
registrasi PostTypes · **8 kelas endpoint REST** pada `rest_api_init` · Admin Menu (`if is_admin()`) ·
Shortcodes · enqueue aset publik/admin dan CDN Alpine + Tailwind.

**Pola REST:** controller endpoint melakukan query `$wpdb` langsung di dalam handler — tidak ada
lapisan Service/Repository terpisah. Sanitasi selalu lewat `SanitizeHelper` sebelum insert/update.

---

## Database

Tabel dibuat di [includes/Installer.php](includes/Installer.php) via `dbDelta`,
prefix `{$wpdb->prefix}absensi_`:

| Tabel | Isi | Index penting |
|---|---|---|
| `absensi_users` | master orang yang diabsen (siswa/guru/staf): `nomor_induk`, `rfid_uid`, `group_id`, `foto_path`. Tanpa akun WP. | UNIQUE `nomor_induk`, UNIQUE `rfid_uid` |
| `absensi_group` | kelompok absen: `nama` + `tipe` VARCHAR(50) bebas (default `kelas`) | PK |
| `absensi_jadwal` | jam masuk/keluar per `group_id` per `hari` (1 = Senin) | KEY `group_id` |
| `absensi_libur` | hari libur sebagai **rentang** (`tanggal_mulai`..`tanggal_selesai` + `keterangan`); libur sehari → mulai = selesai. Tanggal libur tidak dihitung alpha. Overlap diperbolehkan. | KEY `tanggal_mulai`, `tanggal_selesai` |
| `absensi_rekap` | **satu baris per user per tanggal** | UNIQUE `(user_id, tanggal)`, KEY `tanggal`, `group_id` |

### Model rekap

Satu hari = satu baris. Kolom `waktu_masuk` dan `waktu_keluar` berada di baris yang sama:
tap/selfie pertama melakukan INSERT (`waktu_masuk`), yang kedua melakukan UPDATE
(`waktu_keluar`) — **bukan** dua baris terpisah.

- `status` ENUM(`hadir`, `telat`, `izin`, `sakit`, `alpha`)
- `mode` / `metode_masuk` / `metode_keluar` ENUM(`selfie`, `rfid`, `manual`)
- `izin_tipe`, `bukti_status`, `bukti_path` **ada tapi dorman** (fitur izin/sakit di luar lingkup MVP)

### Versi skema & migration runner

`Installer::DB_VERSION` (saat ini `2.4.0`) dipasangkan dengan option `absensi_db_version`.
`maybe_upgrade()` berjalan setiap `plugins_loaded`: bila versi tersimpan lebih rendah, ia
menjalankan migrasi per-versi, lalu `create_tables()` (dbDelta), lalu re-seed options.
**Tidak perlu deactivate/activate** untuk menyinkronkan skema.

Migrasi yang tidak bisa ditangani dbDelta (rename tabel/kolom, ubah tipe kolom) ditulis manual dan
dipanggil **sebelum** `create_tables()`:

| Migrasi | Untuk versi < | Kerjanya |
|---|---|---|
| `migrate_to_v2()` | 2.0.0 | RENAME TABLE + ALTER (idempotent) |
| `migrate_to_v2_1()` | 2.1.0 | ALTER MODIFY `group.tipe` ENUM → VARCHAR (guard `column_is_enum`) |
| `bersihkan_yatim()` | 2.3.0 | menyapu rekap tanpa user & jadwal tanpa group (dipanggil **setelah** `create_tables()` karena butuh tabelnya ada) |
| `bersihkan_role_warisan()` | 2.4.0 | membuang role/cap pra-pivot; user yang hanya memegang role warisan dipindah ke `subscriber` |

Semua idempotent lewat pemeriksaan `table_exists` / `column_exists` / `column_is_enum` / `index_exists`.

### Cascade hapus

Skema tidak memakai foreign key — cascade dikerjakan di endpoint:

- `DELETE /users/{id}` dan `POST /users/bulk-delete` ikut menghapus **rekap** milik user tersebut
  (respons memuat `rekap_dihapus`).
- `DELETE /group/{id}` ikut menghapus **jadwal** group tersebut (respons memuat `jadwal_dihapus`).
  Guard 409 `group_ada_user` tetap berlaku; saat permintaan ditolak, jadwal tidak tersentuh.

Alasannya bukan sekadar kerapian data:

1. `/laporan/summary` menghitung `COUNT(*)` dari rekap **tanpa JOIN users** — baris yatim akan
   menaikkan angka Hadir/Telat.
2. Di list dan export (LEFT JOIN) baris yatim muncul tanpa nama.
3. `rekap.user_id` hanya berupa angka. MariaDB/MySQL 5.7 me-reset `AUTO_INCREMENT` ke `MAX(id)+1`
   setiap restart, sehingga **id bisa dipakai ulang** dan riwayat orang lama menempel ke user baru.

Konsekuensi yang disengaja: **menghapus user berarti riwayat absensinya hilang dari laporan lama.**
Antarmuka menyebutkan hal ini pada dialog konfirmasi. Bila kelak riwayat harus tetap utuh,
solusinya arsip/soft-delete — bukan membiarkan baris yatim.

### Settings

Disimpan sebagai **wp_options individual**, bukan satu blob serialized. Di-seed oleh
`Installer::seed_default_options()` (idempotent, ikut dijalankan ulang saat `maybe_upgrade`):

`absensi_lat` · `absensi_lng` · `absensi_radius` (100) · `absensi_jam_masuk` (`07:00`) ·
`absensi_jam_keluar` (`15:00`) · `absensi_telat_menit` (15) · `absensi_akurasi_max` (100) ·
`absensi_rfid_debounce` (3) · `absensi_retensi_hari` (90)

Runtime juga membaca `absensi_selfie_rl_detik` (rate-limit selfie, default 5 detik).

---

## REST API

Namespace `absensi/v1` → `/wp-json/absensi/v1/`.

| Method | Endpoint | Permission | Berkas |
|---|---|---|---|
| POST | `/absen/selfie` | **publik** + gerbang anti-enumerasi per-IP | [AbsensiEndpoint.php](includes/api/AbsensiEndpoint.php) |
| POST | `/absen/rfid` | login + cap `absensi_rfid` | AbsensiEndpoint |
| GET | `/absen/status` | **publik** (by `nomor_induk`) + gerbang anti-enumerasi | AbsensiEndpoint |
| GET/POST | `/users` | `manage_options` | [UsersEndpoint.php](includes/api/UsersEndpoint.php) |
| GET/PUT/DELETE | `/users/{id}` | `manage_options` | UsersEndpoint |
| POST | `/users/{id}/rfid` | `manage_options` | UsersEndpoint — bind kartu ke user |
| POST | `/users/bulk-delete` | `manage_options` | UsersEndpoint — `{ids:[int]}` → `{deleted:N}`, satu query `DELETE … IN`, batas 500 |
| POST | `/users/import` | `manage_options` | UsersEndpoint — xlsx via PhpSpreadsheet; kolom `nama`, `nomor_induk`, opsional `group`, `tipe` |
| POST | `/guru/import` | `manage_options` | UsersEndpoint — bulk membuat WP user role `guru` dari xlsx |
| GET/POST | `/group` | `manage_options` | [GroupEndpoint.php](includes/api/GroupEndpoint.php) |
| GET/PUT/DELETE | `/group/{id}` | `manage_options` | GroupEndpoint |
| GET/POST/PUT/DELETE | `/jadwal`, `/jadwal/{id}` | `manage_options` | [JadwalEndpoint.php](includes/api/JadwalEndpoint.php) |
| GET/POST/PUT/DELETE | `/libur`, `/libur/{id}` | `manage_options` | [LiburEndpoint.php](includes/api/LiburEndpoint.php) |
| POST | `/rekap/status` | `manage_options` | [RekapEndpoint.php](includes/api/RekapEndpoint.php) — koreksi status |
| DELETE | `/rekap/{id}` | `manage_options` | RekapEndpoint — batalkan penyesuaian |
| GET | `/laporan`, `/laporan/summary`, `/laporan/export` | `manage_options` | [LaporanEndpoint.php](includes/api/LaporanEndpoint.php) |
| GET/PUT | `/settings` | `manage_options` | [SettingsEndpoint.php](includes/api/SettingsEndpoint.php) |

### Tiga kelas autentikasi

**1. Kiosk publik siswa** (`/absen/selfie`, `/absen/status`) — `permission_callback => __return_true`.
Identitas berasal dari `nomor_induk`, bukan sesi WordPress. Dua lapis anti-abuse **wajib
dipertahankan**:

- **Rate-limit transient per nomor induk** (khusus selfie, `absensi_selfie_rl_detik`).
- **Gerbang anti-enumerasi per-IP** — `gerbang_publik()` + `tandai_nomor_asing()`.
  Nomor induk umumnya berurutan, dan endpoint membedakan 404 (nomor asing) dari 200/403 (nomor ada).
  Perbedaan itu adalah *oracle*: sebuah skrip bisa memanen daftar nama sekaligus mengetahui siapa
  yang hari ini tidak masuk. Karena itu dipasang dua lapis: `RL_MAX` hit per jendela per IP (longgar,
  sebab satu kiosk dipakai seluruh sekolah) **dan** penguncian setelah `MISS_MAX` nomor asing
  **beruntun** selama `MISS_LOCK` detik — nomor yang benar me-reset hitungan, sehingga kiosk asli
  tak pernah terkena.
  Semua dapat difilter: `absensi_publik_rl_max`, `absensi_publik_miss_max`, `absensi_publik_lock_detik`,
  serta `absensi_client_ip` untuk deployment di belakang proxy. Header `X-Forwarded-For`
  **sengaja tidak dibaca secara default** karena mudah dipalsukan.

**2. Kiosk RFID (login-gated)** (`/absen/rfid`) — `can_absen_rfid()` =
`is_user_logged_in() && current_user_can('absensi_rfid')`, dengan nonce `wp_rest`.
Debounce anti double-tap wajib dipertahankan. Identitas kehadiran tetap dari `rfid_uid` siswa.

**3. Admin** (users, group, jadwal, libur, rekap, laporan, settings) —
`current_user_can('manage_options')`, cookie WordPress + nonce `wp_rest` pada header `X-WP-Nonce`.
Nonce diinjeksikan lewat `wp_localize_script` ke variabel `AbsensiConfig` (publik) dan
`AbsensiAdmin` (admin).

### Format respons

Sukses: `WP_REST_Response([...], 2xx)`.
Error lewat helper `error($code, $msg, $status)` → `['code', 'message', 'data' => ['status']]`.

| Status | Arti umum |
|---|---|
| 403 | di luar radius, atau koneksi bukan HTTPS |
| 404 | nomor induk / UID / entitas tidak ditemukan |
| 409 | sudah absen, UID/nomor bentrok, group masih berisi user |
| 422 | input tidak valid |
| 429 | kena rate-limit atau double-tap |
| 503 | pustaka export tidak terpasang, atau koordinat sekolah belum diatur |

---

## Helpers (`includes/helpers/`)

**[SanitizeHelper.php](includes/helpers/SanitizeHelper.php)** — wajib dipanggil sebelum setiap
`$wpdb->insert/update`.

- `::users()` — `nomor_induk` ≤ 30, `nama` ≤ 150, `group_id` absint, `rfid_uid`
- `::group()` — `nama` ≤ 100, `tipe` string bebas ≤ 50 (bukan whitelist; `GroupEndpoint` menolak
  tipe kosong dengan 422 `tipe_wajib`)
- `::rekap()` — whitelist `status`/`mode`, mengunci `user_id`/`group_id`
- `::jadwal()` — `group_id`, normalisasi jam
- `::libur()` — tanggal lewat `::normalize_date()` yang memakai `checkdate`, sehingga `2026-02-31` ditolak
- `::rfid_uid()` — buang karakter non-hex, uppercase, buang CR/LF bawaan HID

**[KehadiranHelper.php](includes/helpers/KehadiranHelper.php)** — mesin **alpha**. Baris rekap hanya
lahir ketika seseorang tap atau selfie, jadi yang bolos tidak punya baris sama sekali. Helper ini
menghitung alpha **saat laporan dibuka** — tanpa cron, tanpa menulis baris ke database:
`::alpha_rows($dari, $sampai, $group_id, $tipe)` dan `::hitung_alpha(...)`.

Aturannya:

- Hari aktif mengikuti `absensi_jadwal` per group; group tanpa jadwal dianggap Senin–Jumat dengan
  `absensi_jam_keluar` global.
- Tanggal yang tercatat di `absensi_libur` bukan hari aktif.
- User dihitung sejak `users.created_at`.
- Alpha baru sah **setelah jam pulang hari itu terlewat** — hari berjalan berstatus "belum absen",
  dan tanggal di masa depan tidak pernah alpha.
- User yang sudah punya rekap tentu bukan alpha.

Baris alpha bersifat **virtual** (`id = null`, `virtual = true`), sehingga mengubah statusnya berarti
INSERT, bukan UPDATE. Dipakai oleh `/laporan`, `/laporan/summary`, dan export.

**[GeoHelper.php](includes/helpers/GeoHelper.php)** — `::haversine($lat1, $lng1, $lat2, $lng2)`
mengembalikan jarak dalam meter; `::is_valid()` memeriksa rentang koordinat.

**[FileHelper.php](includes/helpers/FileHelper.php)** — `::save_selfie()` menyimpan ke
`uploads/absensi-selfie/Y/m/` dengan validasi magic bytes (JPEG `ffd8ff`, PNG `89504e47`), batas 5 MB,
dan nama berakhiran 8 hex acak. Folder upload dilindungi `.htaccess` + `index.php`.
Pola nama: `selfie_{NIS}_{DD-MM-YYYY}-{Masuk|Keluar}_{8hex}.{ext}`.

---

## Frontend

**Alpine.js + Tailwind CSS lewat CDN — tanpa Vite, npm, atau build step.** Edit PHP/JS/CSS lalu
segarkan peramban.

- **Publik** — [public/js/public.js](public/js/public.js) + `public/css/public.css`, di-enqueue pada
  `wp_enqueue_scripts`. Variabel konfigurasi `AbsensiConfig` berisi `restUrl`, `nonce`,
  `rfidDebounce`, `akurasiMax`. Alpine dan Tailwind dimuat dari CDN lewat `enqueue_frontend_cdn`
  (filterable, memakai `defer` via `script_loader_tag`).
- **Admin** — `admin/js/admin.js` + `admin/css/admin.css`, di-enqueue hanya pada halaman plugin
  (`str_contains($hook, 'absensi')`). Variabel `AbsensiAdmin` berisi `restUrl`, `nonce`,
  `rfidDebounce`, dan `settings`. Preflight Tailwind dimatikan agar tidak merusak tampilan wp-admin.
- **Shortcode** ([includes/class/Shortcodes.php](includes/class/Shortcodes.php)) —
  `[absensi_siswa]` dan `[absensi_guru]`. Markup `[absensi_guru]` sendiri tidak memeriksa cap, tetapi
  halamannya di-gate pada level `template_redirect` dan endpointnya tetap memeriksa autentikasi.
  Render memakai `ob_start()` + `include public/views/{view}.php`.
- **Auto-page** — `Installer::seed_pages()` (dipanggil dari `activate()`, bukan `maybe_upgrade()`)
  membuat dua halaman saat aktivasi: `/absensi` (siswa, publik) dan `/absensi/guru` (guru,
  **child page** lewat `post_parent`, login-gated). ID disimpan di option `absensi_pages`
  `{siswa, guru}`; halaman yang benar-benar dibuat plugin dicatat di `absensi_pages_created`.
  Idempotent — halaman milik user dengan slug sama akan diadopsi, bukan diduplikasi.
  Saat deactivate, `remove_pages()` hanya menghapus halaman yang ada di daftar created **dan**
  kontennya masih memuat shortcode terkait; halaman yang diadopsi atau sudah diubah user dibiarkan.
- **Admin menu** ([includes/Admin/Menu.php](includes/Admin/Menu.php)) — menu "Absensi" dengan enam
  submenu: Dashboard, Users, Group, Jadwal (dua tab: jadwal per group + hari libur), Laporan,
  Pengaturan. Semuanya `manage_options`. Render dari `admin/views/{slug}.php`.

---

## Akun Guru (kiosk RFID login-gated)

RFID dipakai untuk mengabsen **siswa**, dioperasikan oleh **guru**. Tiap guru memakai perangkat
sendiri dengan USB RFID reader, login lebih dulu, lalu menempelkan kartu siswa. Login berfungsi
sebagai gerbang akses perangkat, bukan identitas kehadiran.

- **Role & cap** — `Installer::seed_roles()` mendaftarkan role `guru` dengan cap `read` + `absensi_rfid`
  saja (tanpa cap admin lain). Cap `absensi_rfid` juga ditambahkan ke `administrator`.
  Konstanta `Installer::CAP_RFID`. `uninstall.php` mencabut cap dan menghapus role; **deactivate tidak**
  menghapus role agar akun guru tetap utuh.
- **Gate halaman** — `Plugin::gate_kiosk_guru` pada hook `template_redirect`. Belum login →
  `auth_redirect()` (dialihkan ke wp-login lalu kembali otomatis). Sudah login tapi tak berhak →
  `wp_die(403)`. Halaman siswa tidak di-gate.
- **Blokir wp-admin untuk guru** — `Plugin::block_admin_for_guru` dipasang pada **dua** hook:
  `admin_init` dan `admin_page_access_denied`. Hook kedua wajib ada karena `menu.php` memanggil
  `wp_die` untuk cap tinggi **sebelum** `admin_init` sempat berjalan. AJAX dan cron dikecualikan.
- **Admin bar disembunyikan** — filter `show_admin_bar` via `Plugin::hide_admin_bar_for_guru`, supaya
  tampilan kiosk bersih. Administrator tetap memperoleh admin bar.
- **Identitas operator di kiosk** — [public/views/guru.php](public/views/guru.php) menampilkan chip
  "Login sebagai: [Nama]" dan tombol Keluar (`wp_logout_url(get_permalink())`). Dirender server-side
  karena halaman sudah login-gated. Sesi dibiarkan persisten — tidak ada auto-logout.
- **Halaman guru = child page** `/absensi/guru`, disembunyikan dari navigasi publik lewat filter
  `get_pages` (`Plugin::hide_guru_page_public`). Block theme yang memakai `core/page-list`
  menghormati filter ini; bila navigasi diganti `core/navigation` dengan tautan hardcoded, item
  tersebut harus disembunyikan manual.

---

## Konvensi kode

- `defined( 'ABSPATH' ) || exit;` pada baris atas setiap berkas PHP.
- **Semua** query melewati `$wpdb->prepare()`. Untuk WHERE dinamis, rakit dari fragmen yang sudah
  di-`prepare` (lihat pola `$where_parts` di `LaporanEndpoint`) — jangan menyambung input mentah.
- Sanitasi lewat `SanitizeHelper` sebelum menyentuh database; escape keluaran dengan
  `esc_html` / `esc_attr` / `esc_url`.
- String antarmuka melalui i18n `__()` / `esc_html__()` dengan text domain `absensi-sekolah`.
- Waktu memakai `current_time()` / `wp_timezone()` — lihat `tentukan_status_masuk` di
  `AbsensiEndpoint` sebagai acuan perhitungan telat yang benar secara zona waktu.
- Manfaatkan fitur PHP 8: typed property, `str_contains` / `str_starts_with`, named argument,
  union return type (`string|\WP_Error`).
- Endpoint admin baru memakai `current_user_can('manage_options')` — bukan pemeriksaan role.
  Endpoint publik baru **wajib** punya anti-abuse (rate-limit atau debounce).
- Komentar dan identifier domain ditulis dalam Bahasa Indonesia, mengikuti gaya berkas yang ada.
- Scoping CSS wajib: selector admin diawali `.absensi-app`, kiosk diawali `.absensi-kiosk`
  (kiosk guru memakai co-class `.kiosk-guru`) agar gaya tidak bocor ke wp-admin maupun tema.

---

## Menjalankan & menguji

- **Tidak ada build step.** Edit berkas lalu segarkan peramban.
- **Skema database menyinkron sendiri** lewat `maybe_upgrade()` pada `plugins_loaded`. Aktivasi
  penuh (`activate()`) tetap mengerjakan: create tables, seed options, seed dua halaman, dan
  menjadwalkan tugas retensi.
- **HTTPS wajib di produksi** — Geolocation API dan akses kamera hanya bekerja di secure context.
  Endpoint absen memeriksa `is_ssl()`; untuk pengembangan lokal dapat dimatikan lewat filter
  `absensi_enforce_ssl`.
- **Export** (XLSX/PDF) dan **import** Excel membutuhkan `composer install` lebih dulu. Tanpa
  `vendor/`, endpoint terkait menjawab **503** secara sengaja, bukan fatal error.
- Setiap perubahan sebaiknya diuji dengan `php -l` pada berkas yang disentuh, lalu diverifikasi
  perilakunya secara nyata (memanggil endpoint, memeriksa data, atau menjalankan test terkait).

---

## Batasan yang diketahui

- **Izin dan sakit di luar lingkup MVP.** Endpoint pengajuan dan persetujuan sudah dihapus. Kolom
  `izin_tipe`, `bukti_status`, `bukti_path` beserta `FileHelper::save_bukti()` sengaja dibiarkan
  dorman agar fitur ini mudah dihidupkan kembali.
- **Notifikasi WhatsApp dicabut.** Action `absensi_absen_masuk` dan `absensi_absen_keluar` tetap
  di-fire oleh endpoint sebagai titik integrasi bila kelak dibutuhkan.
- **Belum ada continuous integration.**
- **Cap masih kasar** — seluruh fungsi admin memakai `manage_options`, belum dipecah per-fitur.

### Endpoint yang sudah dihapus

Jangan dibuat ulang; semuanya kini menjawab 404:
`/siswa*` · `/kelas*` · `/wali*` · `/child*` · `/absen/izin` · `POST /absen/status` ·
`/absen/rfid/enroll` · `/absen/rfid/resolve`.
