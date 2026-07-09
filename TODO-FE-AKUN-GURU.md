# TODO FE — Akun Guru + Gate Login Kiosk RFID

> Fitur: page RFID (`/absensi/guru`) hidden dari publik & butuh login (role `guru`/admin).
> Login pakai **wp-login bawaan** (redirect otomatis) → **tak perlu bikin form login**.
> BE handle role, gate, endpoint, skema (lihat [TODO-BE-AKUN-GURU.md](TODO-BE-AKUN-GURU.md)).
>
> Scope FE (markup/JS/CSS). **Tes browser** tiap item, console bersih, sebelum centang.

## Ringkas dampak ke FE

Kebanyakan gate = server-side (BE). FE cuma 3 titik: nonce di request RFID, tombol import guru,
sembunyikan item guru di navbar (kalau block theme). URL page guru berubah → `/absensi/guru/`.

---

## 1. RFID request kirim nonce (WAJIB — endpoint kini auth)
`POST /absen/rfid` sekarang **wajib login + nonce**. Pastikan `public/js/public.js` (kioskGuru submit)
kirim header `X-WP-Nonce: AbsensiConfig.nonce`.
- [ ] Cek fetch/axios ke `/absen/rfid`: ada header `X-WP-Nonce`? Kalau belum → tambah.
      (`AbsensiConfig.nonce` sudah di-inject BE via `wp_localize_script`; untuk user login = nonce valid.)
- [ ] Tes: login sebagai guru, buka `/absensi/guru`, tap kartu → sukses (bukan 401). Cek Network tab header terkirim.
- [ ] (Endpoint `/absen/selfie` & `/absen/status` tetap publik — page siswa tak berubah.)

## 2. Tombol Import Akun Guru (admin)
Admin daftarkan banyak guru sekaligus via Excel. BE sediakan `POST /guru/import`.
- [ ] Admin view (mis. `admin/views/users.php` section baru, atau view khusus) + JS: tombol **Import Guru**,
      upload `.xlsx` → `POST /guru/import` (kolom file: `nama, username, password`, opsional `email`).
      Kirim header `X-WP-Nonce`. Ikut pola tombol Import Users yang sudah ada.
- [ ] Tampil hasil: `imported` (jumlah sukses), `gagal`, `errors[]` (`{baris, pesan}`) — tabel/list error.
- [ ] Handle 503 (vendor PhpSpreadsheet absen) → pesan "fitur import butuh library".
- [ ] Tes: upload file 3 baris → 3 akun guru; file dengan 1 username dobel → error baris tampil, sisanya masuk.

## 3. Sembunyikan item "Guru" dari navbar publik (kalau perlu)
BE coba exclude via filter `get_pages`. **Kalau navbar = block theme** (`core/page-list` / `core/navigation`),
filter BE mungkin tak ngefek → FE sembunyikan manual.
- [ ] Verifikasi: buka situs sbagai anon → apakah "Absensi Guru"/child masih muncul di nav?
      Kalau BE filter sudah cukup (tak muncul) → **skip item ini**.
- [ ] Kalau masih muncul: edit navigation block / template (Site Editor atau template FSE) buang item page guru.
      Sisakan cuma "Absensi" (siswa). Konfirmasi ke BE dulu apakah filter memang tak jalan.
- [ ] Tes: anon lihat nav → cuma "Absensi". Login guru boleh tetap tak lihat (akses via link langsung `/absensi/guru`).

## 4. (Info) URL page guru berubah
Slug lama `/absensi-guru/` → **`/absensi/guru/`** (child dari page Absensi). Kalau ada hardcode link
`/absensi-guru` di view/JS → update. (Grep BE: tak ada di kode; cek ulang FE.)
- [ ] Grep `absensi-guru` di `public/**`, `admin/**` (non-view kalau ada) → update ke `/absensi/guru` bila ketemu.

---

## Kontrak REST (dari BE)
- `POST /absen/rfid` `{ rfid_uid, sesi? }` — **wajib login** cap `absensi_rfid` + `X-WP-Nonce`. Anon → 401.
  Sukses 201/200 (format lama). 404 `uid_tidak_terdaftar`, 429 `double_tap`, 403 gate jam.
- `POST /guru/import` — cap `manage_options` + `X-WP-Nonce`. File xlsx (format `/users/import`).
  Resp `{ imported, gagal, errors:[{baris,pesan}] }`. 503 kalau vendor absen.
- Login = wp-login bawaan, redirect otomatis (`auth_redirect`) → **nol kode form login**.
