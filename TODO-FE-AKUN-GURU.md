# TODO FE — Akun Guru + Gate Login Kiosk RFID

> Fitur: page RFID (`/absensi/guru`) hidden dari publik & butuh login (role `guru`/admin).
> Login pakai **wp-login bawaan** (redirect otomatis) → **tak perlu bikin form login**.
> BE handle role, gate, blok wp-admin, admin bar, endpoint, skema, page (lihat [TODO-BE-AKUN-GURU.md](TODO-BE-AKUN-GURU.md)).
> **Status BE: SELESAI & teruji** (item 1-8 + admin bar). FE tinggal 4 titik di bawah.
>
> Scope FE (markup/JS/CSS). **Tes browser** tiap item, console bersih, sebelum centang.

## Ringkas dampak ke FE (prioritas)

| # | Item | Prioritas | Kenapa |
|---|---|---|---|
| 1 | Nonce `X-WP-Nonce` di request RFID | **WAJIB** | Tanpa ini guru login pun tap → 401 |
| 5 | Handle 401 di kiosk guru (sesi habis) | **Disarankan** | Endpoint kini auth; sesi bisa kedaluwarsa |
| 2 | Tombol Import Guru (admin) | Opsional | Endpoint siap; sementara admin bisa Users bawaan |
| 4 | Cek hardcode URL `/absensi-guru` & `/absensi-siswa` | Cek | Slug berubah |
| 3 | Sembunyikan guru dari navbar | **Kemungkinan SKIP** | Filter BE sudah cukup di theme sekarang |

Gate/blok/adminbar/hide-nav = server-side (BE). Login = wp-login bawaan (nol form).

---

## 1. RFID request kirim nonce (WAJIB — endpoint kini auth)
`POST /absen/rfid` sekarang **wajib login + nonce**. `public/js/public.js` (kioskGuru submit) harus
kirim header `X-WP-Nonce: AbsensiConfig.nonce`.
- [ ] Cek fetch/axios ke `/absen/rfid`: ada header `X-WP-Nonce`? Kalau belum → tambah.
      (`AbsensiConfig.nonce` sudah di-inject BE via `wp_localize_script`; untuk user login = nonce valid.)
- [ ] Tes: login guru, buka `/absensi/guru`, tap kartu → sukses (bukan 401). Cek Network tab header terkirim.
- [ ] (Endpoint `/absen/selfie` & `/absen/status` tetap publik — page siswa `/absensi` tak berubah.)

## 2. Tombol Import Akun Guru (admin) — endpoint SIAP
Admin daftarkan banyak guru sekaligus via Excel. BE sediakan `POST /guru/import` (SUDAH JADI & teruji).
- [ ] Admin view (mis. `admin/views/users.php` section baru / view khusus) + JS: tombol **Import Guru**,
      upload `.xlsx` → `POST /guru/import`. Kirim header `X-WP-Nonce`. Ikut pola tombol Import Users.
- [ ] **Kolom file (header baris-1):** `username` **WAJIB** + `nama`/`password`/`email` **opsional**.
      (password kosong → BE auto-generate; password diisi harus ≥ 6 char; email harus valid+unik bila diisi.)
      Beri contoh template / hint kolom ke admin.
- [ ] Tampil hasil: `imported` (sukses), `gagal`, `errors[]` (`{baris, pesan}`) — tabel/list error per baris.
- [ ] Handle `503` (vendor PhpSpreadsheet absen) → pesan "fitur import butuh library".
- [ ] Tes: file 3 baris valid → 3 akun; 1 username dobel → error baris tampil, sisanya masuk.
> Sementara belum ada tombol: admin bisa buat guru manual di **wp-admin → Users → Add New → Role: Guru**.

## 3. Sembunyikan item "Guru" dari navbar publik — KEMUNGKINAN SKIP
BE sudah exclude via filter `get_pages` (`hide_guru_page_public`). **Terverifikasi**: theme sekarang
**twentytwentyfive (block theme)** pakai blok **Page List** yang menghormati `get_pages` → guru **tak muncul**
di nav anon.
- [ ] Cek ulang: anon buka homepage → "Absensi Guru" muncul di nav? Kalau TIDAK → **SKIP item ini** (sudah beres BE).
- [ ] HANYA kalau FE nanti ganti nav ke blok **Navigation** dengan link `/absensi/guru` **hardcoded** (tak lewat
      `get_pages`) → filter BE tak ngefek → buang item guru manual via Site Editor.

## 4. Cek hardcode URL yang berubah
Slug berubah: `/absensi-guru/` → **`/absensi/guru/`**, dan (perubahan sebelumnya) `/absensi-siswa/` → **`/absensi/`**.
- [ ] Grep `absensi-guru` dan `absensi-siswa` di `public/**`, `admin/**` → update ke `/absensi/guru` & `/absensi`
      bila ketemu. (Grep BE: tak ada di kode PHP; cek JS/CSS/markup FE.)

## 5. Handle 401 di kiosk guru (sesi kedaluwarsa) — DISARANKAN
Endpoint RFID kini auth. Kalau sesi WP guru habis / nonce kedaluwarsa saat kiosk sudah lama terbuka,
`POST /absen/rfid` bisa balas **401**.
- [ ] kioskGuru: tangani status `401`/`403` → tampilkan pesan mis. "Sesi habis, silakan login ulang"
      + tombol/redirect ke `wp-login.php?redirect_to=<url kiosk>`. Jangan diam / tampil error mentah.
- [ ] (Opsional) auto-reload / refresh nonce bila memungkinkan.

## (Info) Admin bar & login — tak perlu aksi FE
- **Admin bar WP disembunyikan** untuk guru (BE filter `show_admin_bar`) → kiosk guru **tanpa** bar hitam,
  layout bisa asumsikan mulai dari `top:0` (tak ada offset 32px). Nol aksi FE.
- **Login** = wp-login bawaan; setelah sukses guru **otomatis diarahkan** ke `/absensi/guru` (BE). Nol form login FE.

---

## Kontrak REST (dari BE — final)
- `POST /absen/rfid` `{ rfid_uid, sesi? }` — **wajib login** cap `absensi_rfid` + header `X-WP-Nonce`. Anon → **401** `rest_forbidden`.
  Sukses 201/200 (format lama). 404 `uid_tidak_terdaftar`, 429 `double_tap`, 403 gate jam (`belum_waktu_masuk`/`belum_waktu_pulang`).
- `POST /guru/import` — cap `manage_options` + `X-WP-Nonce`. File xlsx (multipart `file` atau base64 `file`, pola `/users/import`).
  Kolom: `username` wajib; `nama`/`password`/`email` opsional. Resp `{ imported, gagal, errors:[{baris,pesan}] }`. 503 bila vendor absen.
- `/absen/selfie` & `/absen/status` — **tetap publik** (siswa), tak berubah.
- Login = wp-login bawaan, redirect otomatis (`auth_redirect`) → **nol kode form login**.
- URL: kiosk siswa `/absensi/`, kiosk guru `/absensi/guru/` (child, login-gated).
