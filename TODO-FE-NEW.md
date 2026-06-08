
# TODO Frontend — Plugin WP Absensi Sekolah (NEW, selaras kontrak BE↔FE)

> **Legenda:** ✅ Selesai · ⬜ Belum · 🔧 Perlu Backend · ⚠️ Perbaikan (jalan, belum sesuai brief) · 🤝 Selaraskan kontrak BE↔FE
> Sumber: `Brief_P4_Plugin_WP_Absensi_Sekolah.pdf` + `02_FRONTEND_PLAN.md` + `03_UIUX_PLAN.md`, direkonsiliasi dgn `TODO_BACKEND.md` + `01_BACKEND_PLAN.md`.
> **Pasangan:** `TODO-BE-NEW.md` (kontrak §0 IDENTIK di kedua file).

---

## 0. 🤝 KONTRAK BERSAMA BE↔FE (SUMBER KEBENARAN)

Backend sudah terimplementasi + lulus tes sebagian → **keputusan backend = kanonik**; frontend menyesuaikan. Poin plan lama yang bertentangan **di-override** tabel ini.

| # | Topik | Plan lama | KANONIK (dipakai) | Dampak FE |
|---|---|---|---|---|
| K1 | Namespace REST | `absensi/v1` | **`absensi/v1`** | tetap |
| K2 | Prefix endpoint absen | FE plan `/checkin/*` | **`/absen/*`** | `apiClient`: `/checkin/selfie`→`/absen/selfie`, `/checkin/rfid`→`/absen/rfid` |
| K3 | Model data absensi | `absensi_log` 2 baris/sesi | **`absensi_rekap` 1 baris/hari** (`waktu_masuk`,`waktu_keluar`,`metode_masuk`,`metode_keluar`) | RekapTable = kolom **Masuk + Pulang** (+ metode per sesi, R2); timeline ortu cocok |
| K4 | Filter sesi di list | `/logs?...&sesi=` | **tak ada `?sesi=`** | jangan kirim `?sesi=`; "sesi" = 2 kolom waktu |
| K5 | Settings store | serialized `absensi_settings` | **`wp_options` individual `absensi_*`** | konsumsi via `AbsensiConfig`/`AbsensiAdmin`; BE internal |
| K6 | Export laporan | `/report?range=&format=` | **`/laporan/export?format=xlsx\|csv\|pdf&dari=&sampai=&kelas=`** stream | ExportMenu arahkan XLSX+PDF ke endpoint ini; PDF resmi = Dompdf server (bukan `window.print()`) |
| K7 | Param rentang | `range=` | **`dari=` & `sampai=`** | sudah match (bugfix §10) |
| K8 | Resolve/Enroll RFID | FE `/rfid/*` | **`GET /absen/rfid/resolve?uid=`** + **`POST /absen/rfid/enroll`** `{siswa_id,rfid_uid,replace}` | final (R1); `rfid_uid`, UID di-mask di response |
| K9 | CRUD kelas | REST belum ada | **`/kelas` (BE P1, belum)** | sampai siap, FE tetap PHP form |
| K10 | Arsitektur kode BE | Domain/Service/Repository | **$wpdb langsung di controller** | BE internal, tak ada dampak FE |

**Aturan anti-konflik:** ubah endpoint/param/field **lewat kontrak ini dulu**, baru kode. BE jangan rename/hapus `AbsensiConfig`/`AbsensiAdmin` tanpa kabar. Status absensi: `hadir|telat|alpha|izin|sakit` (telat **hanya sesi masuk**). HTTP: 422 radius/akurasi/koordinat · 404 UID/siswa · 409 sudah absen/kartu bentrok · 429 double-tap · 403 cap/SSL.

---

## 0b. ⚠️ PERBAIKAN — sudah jalan, tapi belum sesuai kontrak/brief (apa + cara)

> Komponen di bawah **sudah ada & berfungsi**, tapi perlu disesuaikan. Tiap baris: **Apa** salahnya · **Cara** fix konkret.

- [x] ✅ **P-FE1 · apiClient path** (K2/K6/K8) — path sudah benar: `absen/selfie`, `absen/rfid`, `absen/rfid/enroll`, `laporan/export`. Enroll body `{siswa_id,rfid_uid,replace}` (R1).
- [x] ✅ **P-FE2 · RekapTable kolom** (K3) — kolom: **Nama · Kelas · Tanggal · Masuk (jam + metode) · Pulang (jam + metode) · Status**. Baca `waktu_masuk`/`waktu_keluar` + `metode_masuk`/`metode_keluar`. Tidak ada kolom/filter "Sesi".
- [x] ✅ **P-FE3 · FilterBar** (K4 + brief §5) — tidak ada filter Sesi. Preset **Hari Ini / Minggu Ini / Bulan Ini** sudah ada. Simpan `localStorage`.
- [x] ✅ **P-FE4 · ExportMenu PDF + Excel** (K6, brief §7/§11) — menu dropdown Excel/CSV/PDF sudah arahkan ke `/laporan/export?format=xlsx|pdf|csv`. Tombol Print (`window.print`) terpisah. 🔧 Eksekusi tunggu endpoint server BE.
- [x] ✅ **P-FE5 · SettingsForm Lokasi/Radius** (UIUX §3.2) — map picker Leaflet sudah ada (`settingsMap` Alpine), klik peta isi lat/lng, radius slider live. 🐛 Bug split tile peta masih intermittent (ResizeObserver sudah ditambah, menunggu konfirmasi).
- [x] ✅ **P-FE6 · "Jadwal" per kelas** (brief §5) — `admin/views/jadwal.php` sudah ada: notice "menunggu backend", preview tabel disabled. 🔧 Fungsional tunggu `JadwalEndpoint /jadwal` dari BE.
- [x] ✅ **P-FE7 · siswa.js submit** (K2/K3) — submit ke `POST absen/selfie` `{ foto, lat, lng, accuracy, sesi }`. SesiSwitcher auto-suggest + override. Handle 422/409 dari server.

---

## 0c. ✅ KEPUTUSAN TERKUNCI (resolusi R1–R6)

Disepakati — tutup titik koordinasi terbuka. Item 🤝 sudah final mengacu sini.

| Ref | Keputusan | Detail |
|---|---|---|
| R1 | Enroll RFID | `POST absensi/v1/absen/rfid/enroll` body `{ siswa_id, rfid_uid, replace:false }` → `{ siswa, uid_masked }`. 409 bentrok (sebut pemilik); overwrite hanya `replace:true`. |
| R2 | Metode rekap | Kolom **`metode_masuk` + `metode_keluar`** di `absensi_rekap` (DB_VERSION++); tampil per sesi. |
| R3 | Skema localize | Lihat tabel skema di bawah. Tambah field = boleh; hapus/rename → naikkan `schemaVersion` + kabar. |
| R4 | WaliLinker | UI assign ortu↔anak **di `admin/views/siswa.php`** (field Wali = WP user, 1 ortu : N anak), konsumsi `/wali` (BE P2). |
| R5 | Auth export | Cookie auth + cap `absensi_view_reports` di `permission_callback`; header `Content-Disposition: attachment`. Tanpa nonce di URL. |
| R6 | CSV | **Server-only** `/laporan/export?format=csv`. CSV client-side dipensiun. |
| K9 | Kelas/Jadwal REST | FE PHP form sampai `KelasEndpoint` + `JadwalEndpoint` (P1) lulus tes → BE kabari → FE migrasi REST. |

**Skema field localize (R3) — terkunci:**

| Var | Field |
|---|---|
| `AbsensiConfig` (siswa/guru/ortu) | `restUrl, nonce, schemaVersion, lat, lng, radius, jam_masuk, jam_keluar, akurasi_max, rfidDebounce, anakList[]` |
| `AbsensiAdmin` (admin) | semua `AbsensiConfig` + `retensi_hari, doubletap_detik, kelasList[], capabilities[]` |

---

## 1. Build System

- [x] `package.json` — Alpine.js ^3, Lucide, Tailwind CSS, Vite
- [x] `vite.config.js` — 5 entry points (`siswa`, `guru`, `admin`, `ortu`, `app`) → `assets/dist/`
- [x] `tailwind.config.js` — design tokens, scan `admin/views/**`, `public/views/**`, `assets/src/**`
- [x] `postcss.config.js` — Tailwind + Autoprefixer
- [x] `npm run build` berjalan bersih tanpa error/warning
- [x] `npm run dev` (watch mode tersedia)

---

## 2. Assets Source (`assets/src/`)

- [x] `app.css` — Tailwind entry + CSS variables (design tokens) + component classes (`.btn`, `.card`, `.badge`, `.status-*`)
- [x] `apiClient.js` — fetch wrapper, auto-inject `X-WP-Nonce`, auto-retry saat nonce expired (403)
  - 🤝 **Selaraskan path (K2/K6/K8):** absen pakai `/absen/*`, export `/laporan/export`, resolve `/absen/rfid/resolve` — bukan `/checkin/*` / `/report`
- [x] `rfid.js` — HID buffer/parser, normalisasi UID (strip non-hex, uppercase), anti double-tap configurable via `AbsensiAdmin.rfidDebounce`
- [x] `siswa.js` — Alpine `absensiSiswa`: kamera, GPS watchPosition, capture + resize 1280px JPEG q0.7, submit selfie, inject sesi+jam ke result
- [x] `guru.js` — Alpine `absensiGuru`: RFID listener (param `rfid_uid` benar), absen tap, enroll kartu, toast 3s, sessionStorage draft kelas/sesi/mode
- [x] `admin.js` — Alpine `filterBar` (localStorage), `rekapTable` (auto-load, export CSV, print, summary computed dari rows), `enrollPanel` (param `rfid_uid` benar)
- [x] `ortu.js` — Alpine `absensiOrtu`: pilih anak, timeline per tanggal, navigasi bulan, summary bulan

---

## 3. Surface Siswa — `[absensi_siswa]`

- [x] `public/views/selfie.php` — shortcode `[absensi_selfie]` (nama lama, lihat catatan)
- [x] Komponen `SesiSwitcher` — segmented Masuk/Pulang, auto-suggest by jam
- [x] Komponen `CameraView` — `<video>` live + frame guide oval wajah
- [x] Komponen `GpsStatusChip` — chip akurasi GPS (hijau/oranye/merah)
- [x] Komponen `CapturePreview` — preview foto + tombol Ulangi/Kirim
- [x] Komponen `ResultCard` — ikon ✓/✗ + sesi + status badge + jam + jarak meter (tolak luar radius/akurasi 422, brief §3/§11)
  - 🤝 submit ke **`POST /absen/selfie`** `{ foto, lat, lng, accuracy, sesi }` (K2); sesi pulang = isi `waktu_keluar` (K3)
- [ ] 🔧 Shortcode `[absensi_siswa]` — Backend daftarkan alias/rename dari `[absensi_selfie]`

---

## 4. Surface Guru — `[absensi_guru]`

- [x] `public/views/guru.php` — view siap, menunggu shortcode dari Backend
- [x] `admin/views/rfid.php` — halaman WP admin (sudah aktif via menu)
- [x] Komponen `KelasSelector` — dropdown kelas dari PHP (sticky toolbar)
- [x] Komponen `SesiSwitcher` — Masuk/Pulang
- [x] Komponen `ModeSwitcher` — toggle Absen / Daftar Kartu
- [x] Komponen `RfidInputPad` — input hidden auto-focus, pulse animasi, klik untuk refocus
  - 🤝 alur: `GET /absen/rfid/resolve?uid=` (feedback) → `POST /absen/rfid` `{ rfid_uid, kelas_id, sesi }` (K2/K8)
- [x] Komponen `EnrollPad` — search siswa + tap kartu + konfirmasi replace + status
  - ⚠️ **Perbaikan (R1/K8):** enroll → **`POST /absen/rfid/enroll`** `{siswa_id,rfid_uid,replace}`, UID di-mask di response (dulu nempel `SiswaEndpoint::set_rfid`)
- [x] Komponen `ScanResultToast` — toast per tap, auto-dismiss 3s, aria-live
- [x] Komponen `TodayList` — daftar scan hari ini + counter hadir
- [ ] 🔧 Shortcode `[absensi_guru]` — Backend daftarkan di `Shortcodes.php` → `public/views/guru.php`

---

## 5. Surface Admin — WP Admin Menu

- [x] `admin/views/dashboard.php` — summary cards PHP (Total Siswa, Hadir, Telat, Izin/Sakit, Alpha) + tabel aktivitas 10 terbaru
- [x] `admin/views/siswa.php` — CRUD siswa, client-side search & filter kelas, avatar inisial, modal add/edit, empty state
- [ ] 🔧 Komponen **WaliLinker** (di `admin/views/siswa.php`) — field **Wali** (WP user) per siswa, 1 ortu : N anak; konsumsi `/wali` (BE P2, R4)
- [x] `admin/views/kelas.php` — CRUD kelas via PHP form + nonce (tanpa REST), modal, pesan sukses/hapus
  - 🤝 sementara PHP form; pindah ke REST `/kelas` saat BE P1 siap (K9)
- [x] ✅ `admin/views/jadwal.php` — view ada: notice "menunggu BE" + preview tabel disabled. 🔧 Fungsional CRUD tunggu `JadwalEndpoint` dari BE
- [x] `admin/views/laporan.php` — `filterBar` + `rekapTable` auto-load, summary cards dari rows
- [x] `admin/views/settings.php` — UI per-card (Lokasi, Jadwal, RFID, WhatsApp), semua field pengaturan
  - ⚠️ **Perbaikan**: card "Jadwal" = jam masuk/keluar **global**; brief §5 minta **jadwal per kelas** → `jadwal.php` (BE `JadwalEndpoint`)
  - 🤝 settings dibaca dari `wp_options` individual via localize (K5)
- [x] Komponen `FilterBar` — filter **tanggal dari/sampai + kelas** (localStorage)
  - ✅ K4: tidak ada filter "Sesi" — model rekap 1 baris/hari
  - ✅ Preset periode **Hari Ini / Minggu Ini / Bulan Ini** sudah ada
- [x] Komponen `RekapTable` — tabel absensi, badge status, zebra rows, sticky header, auto-load hari ini
  - ✅ Kolom: **Nama · Kelas · Tanggal · Masuk (jam + metode) · Pulang (jam + metode) · Status** (K3/R2)
- [x] Komponen `SummaryCards` — 4 kartu Hadir/Telat/Izin-Sakit/Alpha, dihitung dari rows aktual
- [x] Komponen `ExportMenu` — dropdown Excel/CSV/PDF arahkan ke `/laporan/export?format=xlsx|csv|pdf` + tombol Cetak (`window.print()`) terpisah
  - ✅ Menu & redirect sudah benar (K6/R6). 🔧 Eksekusi download tunggu endpoint server BE
- [x] Komponen `EnrollPanel` — search siswa + input UID manual + submit enroll kartu RFID
- [x] Komponen `SettingsForm` — koordinat GPS, radius, jam masuk/keluar, toleransi telat, **window double-tap RFID**, akurasi GPS, **retensi foto**, WA gateway

---

## 6. Surface Orang Tua — `[absensi_ortu]`

- [x] `public/views/ortu.php` — view siap, menunggu shortcode dari Backend
- [x] Komponen `ChildSelector` — pilih anak (multi-anak), avatar inisial
- [x] Komponen `AbsensiTimeline` — daftar per tanggal: chip masuk/pulang + status badge + jam (cocok model rekap K3)
- [x] Komponen `MonthSummary` — 4 kartu ringkasan bulan (Hadir/Telat/Izin-Sakit/Alpha)
- [x] Navigasi bulan ← → dengan batas bulan saat ini
- [ ] 🔧 Shortcode `[absensi_ortu]` — Backend daftarkan di `Shortcodes.php` → `public/views/ortu.php`
- [ ] 🔧 Data anak via `GET /child/logs?range=` + inject `anakList` — hanya anak ter-link (`absensi_wali`, BE P2)

> Catatan brief: ortu = **opsional / view-only** (brief §4). Dibangun penuh = melebihi MVP, diizinkan. IDOR di-guard server (BE P0/P2).

---

## 7. Error & Edge Case

| # | Kasus | Status |
|---|---|---|
| 1 | Browser tolak izin kamera | ✅ Banner persistent di `selfie.php` |
| 2 | Browser tolak izin GPS | ✅ Banner + chip merah di `selfie.php` |
| 3 | Bukan HTTPS | ✅ Blok form + pesan (BE juga enforce `is_ssl()` → 403) |
| 4 | GPS tidak akurat (accuracy > ambang) | ✅ Chip oranye + tahan submit (BE: 422 by `absensi_akurasi_max`) |
| 5 | Nonce expired (403) | ✅ `apiClient.js` auto-refresh + retry sekali |
| 6 | **Offline saat submit** | ✅ Deteksi `navigator.onLine` sebelum submit di `siswa.js`, pesan jelas, tidak queue foto |
| 7 | RFID double-tap | ✅ `rfid.js` window configurable (default 3s); BE enforce 429 |
| 8 | Scanner kirim karakter aneh / UID invalid | ✅ `rfid.js` normalisasi, tolak + tetap fokus |
| 9 | Enroll: kartu sudah dipakai siswa lain (409) | ✅ Toast/pesan merah; BE 409 sebut pemilik |
| 10 | Enroll: siswa sudah punya kartu | ✅ Konfirmasi dialog replace sebelum POST (BE overwrite) |
| 11 | Sudah absen sesi ini (409) | ✅ Pesan error dari server ditampilkan |
| 12 | Di luar radius sekolah (422) | ✅ `ResultCard` ✗ merah + jarak meter, izinkan ulang |

---

## 8. Konvensi Frontend

- [x] Alpine.js untuk reaktivitas — `x-data`, `x-bind`, `@click`
- [x] Tailwind CSS — utility di HTML, **BUKAN CDN**, build-time purge
- [x] `rfid.js` shared module — tak ada duplikasi logika RFID
- [x] `apiClient.js` wajib untuk semua REST call — nonce auto-inject
- [x] Config REST dari `AbsensiConfig` (publik) / `AbsensiAdmin` (admin)
- [x] String UI via `__()`/`esc_html__()` text domain `absensi-sekolah` (i18n-ready)
- [x] Tap target minimal **44px**
- [x] Status & sesi: **ikon + label + warna** (tak hanya warna)
- [x] `aria-live` untuk hasil scan/absen/enroll
- [x] Tombol submit `disabled` saat proses pengiriman
- [x] Foto selfie: resize max 1280px, JPEG q0.7 via `<canvas>`
- [x] RFID: panjang UID tak di-hardcode, Enter terminator (brief §11: dukung Mifare/EM4100 min 3 kartu)

---

## 9. Aturan Storage

| Data | Storage | Status |
|---|---|---|
| Foto selfie | Memory only (revoke setelah submit) | ✅ |
| Koordinat GPS | Memory only (null setelah submit) | ✅ |
| Nonce REST | Memory (`wp_localize_script`) | ✅ |
| Buffer RFID (UID) | Memory (clear tiap Enter) | ✅ |
| Draft guru kelas/sesi/mode | `sessionStorage` | ✅ |
| Preferensi filter admin | `localStorage` (`absensi_admin_filter`) | ✅ |
| `try/catch` di semua akses storage | mode privat bisa throw | ✅ |

---

## 10. Bug Fixes Selesai

- [x] `guru.js` — param RFID: `uid` → `rfid_uid` (sesuai backend)
- [x] `guru.js` — response RFID: `data.nama` → `data.siswa`
- [x] `guru.js` + `admin.js` — enroll: `uid` → `rfid_uid`
- [x] `admin.js` — param laporan: `tanggal_dari/sampai` → `dari/sampai` (sesuai K7)
- [x] `admin.js` — `rekapTable` tidak auto-load saat halaman dibuka
- [x] `admin.js` — summary API abaikan filter → computed dari `rows`
- [x] `siswa.php` — komentar `"` di atribut HTML → Alpine gagal parse
- [x] `siswa.php` — `save()` kirim `user_id: null` → "Invalid parameter"
- [x] `rfid.js` — `handleBlur` rebut fokus elemen interaktif
- [x] `kelas.php` — REST `/kelas` belum ada → redesign ke PHP form

---

## 11. Menunggu Backend (kontrak handoff)

- [ ] 🔧 Shortcode `[absensi_siswa]` / `[absensi_guru]` / `[absensi_ortu]` di `Shortcodes.php` → view masing-masing
- [ ] 🔧 Update enqueue `Plugin.php` → `assets/dist/` (baca manifest Vite), ganti `public.js`/`admin.js` lama
- [ ] 🔧 Inject `rfidDebounce` ke `AbsensiAdmin` (`get_option('absensi_doubletap_detik',3)`) + seed option
- [ ] 🔧 Inject `akurasi_max`, `retensi_hari` ke config (FE settings form butuh nilai awal)
- [ ] 🔧 REST `/kelas` CRUD (K9) — FE pindah dari PHP form saat siap
- [ ] 🔧 `JadwalEndpoint` `/jadwal` CRUD per kelas — untuk `admin/views/jadwal.php` (brief §5)
- [ ] 🔧 Param `?search=` di `GET /siswa` (sekarang search client-side)
- [ ] 🔧 Export server `/laporan/export?format=xlsx|csv|pdf&dari=&sampai=&kelas=` (K6, brief §5/§7/§11) — XLSX PhpSpreadsheet, PDF Dompdf
- [ ] 🔧 Preset laporan harian/mingguan/bulanan (param `dari/sampai` siap-pakai, brief §5)
- [ ] 🔧 Inject `anakList` + `GET /child/logs?range=` (ortu) + tabel `absensi_wali` linking (BE P2)
- [ ] 🔧 **Enroll RFID** `POST /absen/rfid/enroll` `{siswa_id,rfid_uid,replace}` + **resolve** `GET /absen/rfid/resolve?uid=` (path final R1/K8 — BE implement)

---

## 12. Status Akhir Frontend

- [x] ✅ **Selaraskan `apiClient` ke kontrak §0** — path `/absen/*`, `/laporan/export`, `/absen/rfid/enroll` sudah benar (K2/K6/K8)
- [x] ✅ **RekapTable: kolom Masuk + Pulang** (K3) — kolom `waktu_masuk`+`metode_masuk` / `waktu_keluar`+`metode_keluar`, tanpa filter Sesi
- [x] ✅ **Offline detection** di `siswa.js` — `navigator.onLine` sebelum submit, tidak queue foto
- [x] ✅ **Preset periode** harian/mingguan/bulanan di `FilterBar` — Hari Ini / Minggu Ini / Bulan Ini
- [x] ✅ **`admin/views/jadwal.php`** — view ada, notice "menunggu BE", preview tabel disabled. 🔧 Fungsional tunggu `JadwalEndpoint`
- [ ] 🔧 **Export Excel + PDF server** — menu & redirect sudah ada, tunggu endpoint `/laporan/export` dari BE (K6)
- [ ] 🐛 **Bug split tile peta settings** — ResizeObserver sudah ditambah, belum terkonfirmasi fix

---

## Catatan Penting

- File FE **hanya** di: `assets/src/`, `admin/views/`, `public/views/`, config root. `includes/**` (backend) **tak disentuh**.
- Workaround: `<script type="module">` di-inject dari view hingga BE update enqueue.
- **Anti-konflik BE↔FE:** semua endpoint/param/field mengikuti **§0 Kontrak**. Perubahan kontrak = update §0 di `TODO-FE-NEW.md` **dan** `TODO-BE-NEW.md` bersamaan, baru ubah kode.
- **Konflik plan yang sudah diselesaikan:** `/checkin/*`→`/absen/*` (K2), `absensi_log`→`absensi_rekap` (K3), filter `sesi` dibuang (K4), export `/report`→`/laporan/export` (K6).
- **Deviasi sadar brief §7:** Tailwind di-bundle (Vite), BUKAN CDN — alasan koneksi sekolah lemah.
- **Di luar MVP (brief §6):** liveness, anti-spoof GPS, multi-cabang, upload surat izin/sakit. WhatsApp = opsional (field settings).
