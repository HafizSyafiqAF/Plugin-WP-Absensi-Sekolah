# TODO FE — Pivot Kiosk Tanpa Login (Users/Group)

> Scope **frontend** (markup/JS/CSS + view PHP). Konsumsi kontrak REST dari BE (lihat TODO-BE-PIVOT.md). Model baru: tanpa akun/role publik; absen kiosk tanpa login.
> ⚠️ Buang UI lama: surface ortu, login per-role, nav role-based. Relabel "kelas"→"group", "NIS"→"Nomor Induk (NIS/NIP)".

---

## Urutan Pengerjaan (terkoordinasi BE↔FE)
Kontrak REST sudah fix → **markup/skeleton bisa mulai sekarang** (belum live). **Tes live** nunggu endpoint BE nyala.

| Ronde | FE | BE (paralel) | Gate |
|---|---|---|---|
| 0 | scaffold markup 2 page publik (siswa.php, guru.php) | Fase 1 skema v2 + migrasi | — |
| 1 | skeleton admin views (users tabel+form, group) | Fase 2 buang mati + SanitizeHelper | — |
| 2 | wire + **tes live** 2 page publik | **Fase 3** absen publik (selfie+rfid) | nunggu BE Fase 3 |
| 3 | wire live users CRUD + popup bind RFID + import Excel | **Fase 4** /users + /group + /users/import | nunggu BE Fase 4 |
| 4 | laporan relabel group, dashboard, bersih UI ortu/login | Fase 5 (Menu, regresi) | tes integrasi gabungan |

**FE bisa mulai Ronde 0 sekarang** (markup nembak kontrak). Yang nahan cuma tes live (butuh endpoint BE).

---

## Public — 2 halaman kiosk (tanpa login)
- [ ] `public/views/siswa.php` — form **input nomor_induk** + kamera selfie + ambil GPS → `POST /absen/selfie` `{nomor_induk,lat,lng,accuracy,foto}`. Tampilkan hasil (hadir/telat) + error: 404 nomor tak terdaftar, 403 di luar radius, 422 akurasi/koordinat, 429 terlalu cepat.
- [ ] `public/views/guru.php` — **kiosk RFID**: input UID (scanner HID ketik UID+Enter) → `POST /absen/rfid` `{rfid_uid}`. Feedback nama + status, lalu siap tap berikutnya. (loop)

## Admin views (di dalam wp-admin, operator login)
- [ ] `admin/views/users.php` — tabel users; **form satuan** (nama, nomor_induk, group dropdown); **popup bind RFID** (`POST /users/{id}/rfid`); **tombol Import Excel** (upload `.xlsx` → `POST /users/import`, tampil `imported`/`gagal`/`errors`).
- [ ] `admin/views/group.php` — CRUD group (nama + tipe: kelas/guru/staff) via `/group`.
- [ ] `admin/views/dashboard.php` — ringkasan (pakai `/laporan/summary`).
- [ ] `admin/views/laporan.php` — relabel "Kelas"→"Group", filter `group_id`; konsumsi `/laporan` + export.
- [ ] `admin/views/settings.php` — tetap (tak berubah).

## Pembersihan UI
- [ ] Hapus markup/JS surface ortu, link login per-role, nav role-based, halaman ortu.
- [ ] Relabel label "Kelas"→"Group" di seluruh UI; field "NIS"→"Nomor Induk (NIS/NIP)".

---

## Kontrak REST (dari BE)
- `POST /absen/selfie` `{nomor_induk,lat,lng,accuracy?,foto?,sesi?}` → 201/200; 404 `nomor_tidak_terdaftar`, 403 `diluar_radius`, 422, 429.
- `POST /absen/rfid` `{rfid_uid}` → 201/200; 404 `uid_tidak_terdaftar`, 429 `double_tap`.
- `/users` (GET/POST), `/users/{id}` (GET/PUT/DELETE), `/users/{id}/rfid` (POST), `/users/import` (POST xlsx).
- `/group` (GET/POST), `/group/{id}` (GET/PUT/DELETE).
- `/laporan*` param `group_id` (eks `kelas_id`).

> Field localize `AbsensiConfig`/`AbsensiAdmin` (restUrl, nonce) tak berubah. Endpoint absen kini public (nonce opsional untuk absen, wajib untuk admin CRUD).
