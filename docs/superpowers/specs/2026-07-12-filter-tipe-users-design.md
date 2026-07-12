# Filter Tipe di halaman Admin → Users

**Tanggal:** 2026-07-12 · **Scope:** Frontend saja (tanpa perubahan backend/DB)

## Masalah

Halaman **Absensi → Users** sudah punya filter **Group** (dropdown) dan kotak pencarian, tapi belum bisa
menyaring berdasarkan **tipe** orang (Kelas / Guru / Staff / tipe kustom). Kolom "Tipe" sudah tampil
sebagai badge di tabel, tapi tidak bisa dipakai sebagai filter.

## Klarifikasi penting

Tabel `absensi_users` **tidak punya kolom role**. Orang yang diabsen bukan user WordPress. Yang paling
mendekati konsep "role" adalah **`absensi_group.tipe`** — string bebas (≤50 char) milik group tempat
orang itu berada. `GET /users` sudah mengirimkannya sebagai kolom **`tipe_group`** (hasil LEFT JOIN),
jadi datanya **sudah tersedia di browser** tanpa request tambahan.

## Keputusan desain

| Keputusan | Alasan |
|---|---|
| **Dropdown `<select>`**, bukan pill tabs | Konsisten dengan filter Group yang sudah ada. Tipe = string bebas, jumlahnya bisa tumbuh tak terbatas → deretan pill akan meluber. |
| **Filter di sisi klien** | Halaman ini memang sudah memuat *seluruh* user sekali lalu search/paginasi di klien (`GET /users` tak punya pagination maupun param search). Filter tipe cukup operasi in-memory → instan, nol request. |
| **Opsi dibangun dinamis dari data** | Tipe bebas diisi admin ("Ekskul Basket", "Panitia"). Hardcode daftar akan menyembunyikan tipe kustom. Pola yang sama dipakai `groupManager.tipeSuggestions`. |
| **Ada opsi "Tanpa group"** | User dengan `group_id = 0` punya `tipe_group = null`. Tanpa opsi ini mereka hilang dari semua filter dan terlihat seperti data rusak. |
| **Tanpa perubahan backend** | Semua data yang dibutuhkan sudah dikirim. Menambah param `tipe` di `/users` baru relevan bila endpoint itu kelak dipagination di server. |

## Perubahan

Hanya dua file:

**`admin/views/users.php`** — filter bar
- Tambah `<select>` berlabel **Tipe** di antara dropdown Group dan tombol Reset.
- Opsi: "Semua tipe" + `tipeOptions` (dinamis) + "Tanpa group" (bila ada).
- Kondisi tampil tombol Reset ditambah `tipeFilter`.

**`admin/js/admin.js`** — `usersManager`
- State baru: `tipeFilter` (default `''` = semua).
- Getter `tipeOptions`: tipe unik dari `users` yang termuat, urut alfabetis; sisipkan sentinel
  `__none__` ("Tanpa group") bila ada user tanpa group.
- Getter `filteredUsers`: sisipkan penyaringan tipe agar **bersusun** dengan filter Group + pencarian
  (bukan menggantikan).
- Ganti pilihan → `page = 1` (cegah nyangkut di halaman kosong).
- `resetFilter()`: ikut mengosongkan `tipeFilter`.

## Kriteria selesai

- Pilih tipe "Guru" → hanya user yang group-nya bertipe guru yang tampil; jumlah & pagination ikut menyesuaikan.
- Filter Tipe + filter Group + pencarian bisa dipakai **bersamaan** (saling menyaring).
- Pilih "Tanpa group" → menampilkan user tanpa group (mis. Gilang Ramadhan / 2026007).
- Tipe kustom hasil import (mis. "Ekskul Basket") muncul di dropdown tanpa perlu ubah kode.
- Tombol Reset mengembalikan semua filter ke kondisi awal.
- Konsol browser bersih.
