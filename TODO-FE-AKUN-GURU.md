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
- [x] Cek fetch/axios ke `/absen/rfid`: ada header `X-WP-Nonce`? Kalau belum → tambah.
      (`AbsensiConfig.nonce` sudah di-inject BE via `wp_localize_script`; untuk user login = nonce valid.)
      **2026-07-11:** SUDAH — `kioskGuru.submit` pakai `window.api.post('absen/rfid', …)`, dan helper
      `request()` inject `X-WP-Nonce: getNonce()` + retry refresh-nonce sekali pada 403. Tak perlu ubah.
- [x] Tes: login guru, buka `/absensi/guru`, tap kartu → sukses (bukan 401). Cek Network tab header terkirim.
      **2026-07-11:** verifikasi browser — request `/absen/rfid` bawa `x-wp-nonce: b0cbce680a`. Auth lolos
      (admin ber-cap → bukan 401); 403 yang muncul di localhost = gate SSL (`butuh_https`), normal tanpa HTTPS.
- [x] (Endpoint `/absen/selfie` & `/absen/status` tetap publik — page siswa `/absensi` tak berubah.)

## 2. Tombol Import Akun Guru (admin) — endpoint SIAP
Admin daftarkan banyak guru sekaligus via Excel. BE sediakan `POST /guru/import` (SUDAH JADI & teruji).
- [x] Admin view (mis. `admin/views/users.php` section baru / view khusus) + JS: tombol **Import Guru**,
      upload `.xlsx` → `POST /guru/import`. Kirim header `X-WP-Nonce`. Ikut pola tombol Import Users.
      **2026-07-11:** tombol "Import Guru" (ikon user-plus) di header Users + modal baru; `usersManager`
      method `openImportGuru/onImportGuruFile/runImportGuru` (base64 via `window.api` → nonce otomatis).
- [x] **Kolom file (header baris-1):** `username` **WAJIB** + `nama`/`password`/`email` **opsional**.
      (password kosong → BE auto-generate; password diisi harus ≥ 6 char; email harus valid+unik bila diisi.)
      Beri contoh template / hint kolom ke admin. **2026-07-11:** alert info di modal jelaskan kolom + aturan.
- [x] Tampil hasil: `imported` (sukses), `gagal`, `errors[]` (`{baris, pesan}`) — tabel/list error per baris.
      **2026-07-11:** blok `import-result` (summary berhasil/gagal + tabel error per baris) — sama pola Import Users.
- [x] Handle `503` (vendor PhpSpreadsheet absen) → pesan "fitur import butuh library".
      **2026-07-11:** diuji nyata di env ini (vendor tak terpasang) → modal tampil pesan 503 merah, konsol
      hanya log HTTP 503 (di-handle FE, tak crash). Screenshot diverifikasi.
- [~] Tes: file 3 baris valid → 3 akun; 1 username dobel → error baris tampil, sisanya masuk.
      **BELUM TERVERIFIKASI PENUH:** jalur sukses butuh PhpSpreadsheet (`vendor/`), yang **tidak terpasang**
      di env lokal ini → hanya jalur 503 yang bisa dites. Header nonce + wiring endpoint sudah terverifikasi.
      Perlu `composer install` untuk uji sukses 3-baris/dobel-username end-to-end.
> Sementara belum ada tombol: admin bisa buat guru manual di **wp-admin → Users → Add New → Role: Guru**.

## 3. Sembunyikan item "Guru" dari navbar publik — KEMUNGKINAN SKIP
BE sudah exclude via filter `get_pages` (`hide_guru_page_public`). **Terverifikasi**: theme sekarang
**twentytwentyfive (block theme)** pakai blok **Page List** yang menghormati `get_pages` → guru **tak muncul**
di nav anon.
- [x] Cek ulang: anon buka homepage → "Absensi Guru" muncul di nav? Kalau TIDAK → **SKIP item ini** (sudah beres BE).
      **2026-07-11:** fetch homepage anon (tanpa cookie) → "Absensi Guru" **TIDAK** muncul; "Absensi Siswa" &
      "Absensi Orang Tua" muncul. Filter BE `hide_guru_page_public` bekerja → **SKIP**, nol aksi FE.
- [x] HANYA kalau FE nanti ganti nav ke blok **Navigation** dengan link `/absensi/guru` **hardcoded** (tak lewat
      `get_pages`) → filter BE tak ngefek → buang item guru manual via Site Editor. **N/A** (nav masih Page List).

## 4. Cek hardcode URL yang berubah
Slug berubah: `/absensi-guru/` → **`/absensi/guru/`**, dan (perubahan sebelumnya) `/absensi-siswa/` → **`/absensi/`**.
- [x] Grep `absensi-guru` dan `absensi-siswa` di `public/**`, `admin/**` → update ke `/absensi/guru` & `/absensi`
      bila ketemu. (Grep BE: tak ada di kode PHP; cek JS/CSS/markup FE.)
      **2026-07-11:** grep `public/**` & `admin/**` → **nol** hit hardcode URL (hanya di `design.md`/TODO docs).
      Nol aksi FE. (Catatan: slug page guru aktual = `/absensi-guru/`, bukan `/absensi/guru/`, tapi FE tak
      pernah hardcode URL-nya — semua navigasi lewat WP page/permalink.)

## 5. Handle 401 di kiosk guru (sesi kedaluwarsa) — DISARANKAN
Endpoint RFID kini auth. Kalau sesi WP guru habis / nonce kedaluwarsa saat kiosk sudah lama terbuka,
`POST /absen/rfid` bisa balas **401**.
- [x] kioskGuru: tangani status `401`/`403` → tampilkan pesan mis. "Sesi habis, silakan login ulang"
      + tombol/redirect ke `wp-login.php?redirect_to=<url kiosk>`. Jangan diam / tampil error mentah.
      **2026-07-11:** `submit` deteksi auth-fail via **kode** (`rest_forbidden`/`rest_cookie_invalid_nonce`)
      atau status 401 → set `needLogin`, panggung "Sesi Habis" + tombol "Login Ulang" (`loginUrl` = wp-login +
      redirect balik). Panggung menetap (tak auto-reset), tap diabaikan sampai login.
      ⚠️ **Penting:** deteksi TIDAK boleh `status===403` mentah — 403 juga dipakai gate SSL (`butuh_https`) &
      gate jam (`belum_waktu_*`); itu bug yang sempat terjadi & sudah diperbaiki. Diuji 4 kasus (SSL/gate-jam →
      bukan sesi habis; 401/nonce-403 → sesi habis) via `Alpine.$data` + screenshot. Ikon `log-in` ditambah ke registry.
- [x] (Opsional) auto-reload / refresh nonce bila memungkinkan.
      **2026-07-11:** refresh-nonce sekali sudah otomatis di helper `request()` pada 403; bila tetap gagal →
      panggung login. Auto-reload tak dipakai (biar guru sadar & login manual; lebih aman utk kiosk).

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
