# Rincian Elemen per Halaman — Plugin Absensi Sekolah (v2 kiosk)

> Rincian **elemen fungsional** tiap halaman yang dibuat plugin: input, output, tombol, state yang harus ditangani — semua diturunkan dari kontrak REST backend.
> Markup, layout, styling, warna = keputusan FE. Dokumen ini **apa yang harus ada**, bukan bagaimana bentuknya.
> Base REST: `/wp-json/absensi/v1/`. Config JS: `AbsensiConfig` (public) / `AbsensiAdmin` (admin).

Ada **2 halaman publik (kiosk, tanpa login)** + **5 submenu admin (wp-admin, login administrator)**.

---

# A. HALAMAN PUBLIK (kiosk, tanpa login)

Dibuat otomatis saat aktivasi plugin. Alamat & isi:

| Slug | Judul | Shortcode | Fungsi |
|---|---|---|---|
| `/absensi-siswa` | Absensi Siswa | `[absensi_siswa]` | absen mandiri by nomor induk + selfie + GPS |
| `/absensi-guru` | Absensi Guru | `[absensi_guru]` | kiosk tap kartu RFID |

Config tersedia di `AbsensiConfig`: `restUrl`, `nonce`, `rfidDebounce` (detik), `akurasiMax` (meter).
⚠️ **HTTPS wajib** — kamera & GPS browser cuma jalan di HTTPS; endpoint absen juga tolak non-HTTPS (403 `butuh_https`).

---

## A.1 — Halaman `/absensi-siswa` (selfie + GPS)

**Alur:** ketik nomor induk → ambil lokasi GPS → ambil selfie → kirim → lihat hasil.
**Endpoint:** `POST /absen/selfie` body `{ nomor_induk, lat, lng, accuracy?, foto?, sesi? }`.

### Elemen yang harus ada

| Elemen | Detail | Terkait |
|---|---|---|
| **Judul halaman** | "Absensi Siswa" (dari judul page WP) | — |
| **Input nomor induk** | text, wajib, maxLength 30. NIS/NIP. | `nomor_induk` |
| **Status GPS** | Panggil `navigator.geolocation.getCurrentPosition` → dapat `lat`, `lng`, `accuracy` (meter). Tampilkan state: "mencari lokasi…", "lokasi didapat", nilai akurasi. | `lat`, `lng`, `accuracy` |
| **Indikator akurasi** | Bandingkan `accuracy` vs `AbsensiConfig.akurasiMax` (default 100 m). Kalau lebih besar → warn user cari sinyal lebih baik SEBELUM kirim (server juga tolak 422 `akurasi_rendah`). | `akurasiMax` |
| **Kamera selfie** | `getUserMedia` → tampilkan preview → tombol **Ambil Foto** → capture frame jadi **base64** (JPEG/PNG). Tombol **Ulang Foto**. Foto **opsional** (boleh kirim tanpa foto). | `foto` (base64) |
| **(Opsional) pilih sesi** | Toggle **Masuk / Pulang**. Kalau tak dikirim → server auto (belum ada baris hari ini = masuk; sudah masuk belum pulang = pulang). | `sesi` |
| **Tombol Kirim / Absen** | Submit POST. **Disable** selama GPS belum siap / sedang loading. | — |
| **Area hasil / feedback** | Tampilkan respons (lihat tabel state di bawah). | — |
| **(Opsional) Cek status hari ini** | Input nomor induk → `GET /absen/status?nomor_induk=` → tampil `sudah_absen`, `nama`, `rekap{status, waktu_masuk, waktu_keluar}`. Bisa jadi widget "Cek Kehadiran Saya". | endpoint status |

### State respons yang WAJIB ditangani

| HTTP | code | Arti / pesan untuk user |
|---|---|---|
| 201 | — | Absen **masuk** OK. Respons `{sesi:'masuk', status:'hadir'|'telat', jarak, message}`. Bedakan tampilan hadir vs telat. |
| 200 | — | Absen **pulang** OK. `{sesi:'pulang', jarak, message}`. |
| 404 | `nomor_tidak_terdaftar` | Nomor induk tak ada. |
| 403 | `diluar_radius` | Di luar radius sekolah. `message` memuat jarak & batas (mis. "Lokasi Anda 320 m dari sekolah (batas 100 m)."). |
| 403 | `butuh_https` | Koneksi bukan HTTPS. |
| 422 | `nomor_kosong` / `koordinat_invalid` / `akurasi_rendah` | Input invalid — `message` jelaskan. |
| 409 | `sudah_absen` / `sudah_absen_keluar` / `belum_absen_masuk` | Duplikat / urutan salah. |
| 429 | `terlalu_cepat` | Rate-limit (submit terlalu cepat, default jeda 5 dtk). Minta tunggu. |
| 503 | `sekolah_belum_diatur` | Admin belum set koordinat sekolah. |

> **Catatan radius:** koordinat & radius sekolah **TIDAK** dikirim ke browser (rahasia server). FE **tak bisa** gambar peta "dalam/luar radius" sebelum kirim — cuma tahu hasilnya dari respons (403 + jarak). Yang bisa dicek client-side cuma **akurasi** (via `akurasiMax`).

---

## A.2 — Halaman `/absensi-guru` (kiosk RFID)

**Alur:** perangkat di meja, scanner RFID nancep (mode HID keyboard). Tap kartu → scanner "ketik" UID + Enter → kirim → tampil nama+status → siap tap berikut.
**Endpoint:** `POST /absen/rfid` body `{ rfid_uid }`.

### Elemen yang harus ada

| Elemen | Detail | Terkait |
|---|---|---|
| **Judul halaman** | "Absensi Guru" (nama historis — sebetulnya kiosk RFID untuk siapa pun yang punya kartu). | — |
| **Input UID (auto-focus)** | Text field yang **selalu fokus**. Scanner mengetik UID lalu Enter. FE: dengar event Enter → submit → **kosongkan field** → **fokus ulang** untuk tap berikutnya. Idealnya field tak terlihat/menonjol (kiosk otomatis). | `rfid_uid` |
| **Feedback besar** | Tampilan hasil yang **terbaca dari jarak** (kiosk): nama + status. | respons |
| **Loop tap** | Setelah feedback muncul, otomatis reset + siap tap kartu berikutnya. | — |

Tidak ada GPS/kamera di halaman ini.

### State respons yang WAJIB ditangani

| HTTP | field/code | Tampilan |
|---|---|---|
| 201 | `{action:'masuk', status:'hadir'|'telat', siswa, message}` | "Selamat datang, {siswa}!" + tanda masuk (bedakan telat). |
| 200 | `{action:'keluar', siswa, message}` | "Selamat siang, {siswa}! Waktu keluar dicatat." |
| 404 | `uid_tidak_terdaftar` | "Kartu tidak terdaftar." |
| 429 | `double_tap` | "Kartu baru saja di-tap, tunggu sebentar." (debounce default 3 dtk, dari `AbsensiConfig.rfidDebounce`) |
| 409 | `sudah_absen` | "{siswa} sudah absen masuk & keluar hari ini." |

---

# B. HALAMAN ADMIN (wp-admin — menu "Absensi", login administrator)

Semua submenu butuh cap `manage_options` (cuma administrator lihat menu). Nonce & URL dari `AbsensiAdmin.{restUrl, nonce}` (header `X-WP-Nonce` di tiap request). 5 submenu:

| Submenu | URL | View file | Status view |
|---|---|---|---|
| Dashboard | `?page=absensi-dashboard` | `admin/views/dashboard.php` | **belum ada (FE bikin)** |
| Users | `?page=absensi-users` | `admin/views/users.php` | **belum ada (FE bikin)** |
| Group | `?page=absensi-group` | `admin/views/group.php` | **belum ada (FE bikin)** |
| Laporan | `?page=absensi-laporan` | `admin/views/laporan.php` | **belum ada (FE bikin)** |
| Pengaturan | `?page=absensi-settings` | `admin/views/settings.php` | sudah ada |

> Menu ke view yang belum ada → plugin tampilkan placeholder "View belum tersedia" (bukan error).

---

## B.1 — Users (`?page=absensi-users`)

Master orang yang diabsen (siswa/guru/staff jadi satu tabel; dibedakan lewat group.tipe).
**Endpoint:** `GET/POST /users`, `GET/PUT/DELETE /users/{id}`, `POST /users/{id}/rfid`, `POST /users/import`.

### Elemen

| Elemen | Detail |
|---|---|
| **Tabel users** | Kolom: `nomor_induk`, `nama`, `nama_group` (+ `tipe_group`), `rfid_uid` (boleh di-mask), tombol aksi. Sumber: `GET /users` (list bawa join nama_group/tipe_group). |
| **Filter & pencarian** | Dropdown group (`group_id`), kotak cari (`search`), paging. Param dikirim ke `GET /users`. |
| **Form tambah/edit** | Field: `nomor_induk`* (≤30), `nama`* (≤150), `group_id` (dropdown dari `GET /group`), `rfid_uid` (opsional). Submit `POST /users` (baru) atau `PUT /users/{id}` (edit, boleh parsial). |
| **Tombol Bind RFID (popup)** | Pilih user → popup input UID (tap scanner) → `POST /users/{id}/rfid` body `{rfid_uid, replace?}`. Tangani 409 `kartu_terpakai` (UID milik user lain) & `sudah_punya_kartu` (kirim ulang `replace=true` untuk ganti). |
| **Tombol Import Excel** | Upload `.xlsx` → `POST /users/import`. Kolom header wajib: `nama`, `nomor_induk`, `group` (nama group — tak ada → auto-create). Cap 2000 baris. Tampilkan hasil `{imported, gagal, errors:[{baris, pesan}]}` — daftar baris gagal + alasannya. 503 = vendor PhpSpreadsheet belum di-install. |
| **Tombol Hapus** | Konfirmasi → `DELETE /users/{id}`. |

**Error umum:** 409 nomor_induk/rfid_uid duplikat saat create/update; 404 user tak ada.

---

## B.2 — Group (`?page=absensi-group`)

Kelompok absen (kelas/guru/staff).
**Endpoint:** `GET/POST /group`, `GET/PUT/DELETE /group/{id}`.

### Elemen

| Elemen | Detail |
|---|---|
| **Tabel group** | Kolom: `nama`, `tipe` (kelas/guru/staff), `jumlah_user` (jumlah user di group itu), aksi. Sumber `GET /group`. |
| **Form tambah/edit** | `nama`* (≤100), `tipe` dropdown (`kelas` default / `guru` / `staff`). `POST /group` / `PUT /group/{id}`. |
| **Tombol Hapus** | `DELETE /group/{id}`. Tangani **409 `group_ada_user`** — group masih punya user, tak bisa dihapus (minta pindah/hapus user dulu). |

---

> **Submenu "Absen RFID" (admin) DIBUANG.** Bind kartu RFID dilakukan di halaman **Users** (popup Bind RFID → `POST /users/{id}/rfid`). Tap-absen RFID dilakukan di **kiosk publik** `/absensi-guru` (`POST /absen/rfid`). Endpoint lama `/absen/rfid/enroll` & `/absen/rfid/resolve` sudah 404.

---

## B.4 — Laporan (`?page=absensi-laporan`)

Rekap kehadiran + export.
**Endpoint:** `GET /laporan`, `GET /laporan/summary`, `GET /laporan/export`.

### Elemen

| Elemen | Detail |
|---|---|
| **Filter rentang** | `dari`, `sampai` (YYYY-MM-DD), atau `preset` (mis. harian/mingguan/bulanan), `group_id`, paging (`per_page`, `page`). |
| **Kartu ringkasan** | `GET /laporan/summary` → `{hadir, telat, izin, sakit, alpha, total}` per rentang. Tampilkan sebagai angka/kartu. Field pakai `group_id` (bukan kelas_id). |
| **Tabel rekap** | `GET /laporan` → tiap baris: `nama`, `nomor_induk`, `nama_group`, `tanggal`, `status`, `waktu_masuk`, `waktu_keluar`, `metode_masuk`/`metode_keluar`, `jarak_meter`, `bukti_url` (bila ada — izin/sakit, saat ini dormant). Respons `{data[], total, page, per_page, total_page}`. |
| **Tombol Export** | `GET /laporan/export?format=csv|xlsx|pdf` + filter → unduh file. Tangani **503 `export_unavailable`** untuk xlsx/pdf bila vendor absen (CSV selalu bisa). |

Label: pakai **"Group"** (bukan Kelas) & **"Nomor Induk"** (bukan NIS) di kolom + header export.

---

## B.5 — Dashboard (`?page=absensi-dashboard`)

Ringkasan cepat. Sumber data: `GET /laporan/summary` (mis. rentang hari ini / bulan ini). Tampilkan statistik hadir/telat/izin/sakit/alpha + total. Detail visual = FE.

---

## B.6 — Pengaturan (`?page=absensi-settings`)

View `settings.php` **sudah ada**. **Endpoint:** `GET /settings` (prefill), `PUT /settings` (simpan). Prefill juga tersedia di `AbsensiAdmin.settings`.

### Field pengaturan

| Field | Isi | Catatan |
|---|---|---|
| `absensi_lat`, `absensi_lng` | koordinat sekolah | idealnya map picker |
| `absensi_radius` | radius valid (m), default 100 | server clamp maks 500 |
| `absensi_jam_masuk` / `absensi_jam_keluar` | `07:00` / `15:00` | jam default (jadwal per-group menimpa) |
| `absensi_telat_menit` | toleransi telat (menit), 15 | |
| `absensi_akurasi_max` | akurasi GPS maks diterima (m), 100 | |
| `absensi_rfid_debounce` | anti double-tap (detik), 3 | |
| `absensi_retensi_hari` | retensi foto selfie (hari), 90 | |
| `absensi_wa_gateway`, `absensi_wa_token` | gateway WA | **notif WA luar MVP** — field boleh disembunyikan; `wa_token` **tak** di-prefill (sensitif) |

---

# C. Yang TIDAK dibuat (luar MVP)

- UI izin/sakit (pengajuan + approve) — endpoint sudah dihapus.
- UI notifikasi WA / nomor HP ortu.
- Halaman/role guru-login, siswa-login, ortu — model tanpa akun/role.

---

*Kontrak REST detail (request/response/error tiap endpoint) ada di UC per fitur: `tests/manual/UC-pivot-*.md`.*
