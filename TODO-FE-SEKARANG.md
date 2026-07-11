# TODO FE — Sisa yang belum (audit 2026-07-09)

> Scope frontend saja (view/JS/CSS). Deliverable pivot & kiosk **sudah selesai**.
> File ini KHUSUS item FE yang belum. Kosongkan/hapus bila semua dicentang.
>
> **STATUS 2026-07-11: SEMUA ITEM FE SELESAI ✅.** Sisa 1 **handoff ke BE** (§2: cabut registrasi
> shortcode lama `absensi_selfie`/`absensi_status` di `Shortcodes.php` — view-nya sudah dihapus FE,
> jadi registrasi menganggur ini bisa memicu PHP warning bila shortcode dipakai di page).
> File dipertahankan sebagai catatan handoff sampai BE menindaklanjuti.

---

## 1. Group: input tipe kustom (BE sudah siap)

BE v2.1.0 sudah dukung **tipe group bebas** (`group.tipe` VARCHAR, bukan lagi ENUM kelas/guru/staff).
`POST/PUT /group` kini terima `tipe` string bebas (≤50). FE tinggal ganti input dropdown → teks bebas.

- [x] [admin/views/group.php](admin/views/group.php) modal form: ganti `<select id="gf-tipe">` (3 opsi statis)
      → **input teks** `x-model="form.tipe"` + `<datalist>` saran (Kelas/Guru/Staff + tipe yang sudah
      dipakai di `groups`). Admin boleh ketik tipe baru (mis. "Ekskul", "Panitia", "Tata Usaha").
- [x] [admin/js/admin.js](admin/js/admin.js) `groupManager`: `tipeLabel()` sudah fallback tampil apa adanya;
      `tipeBadge()` default `badge--kelas` untuk tipe kustom — pertimbangkan badge netral (mis. `badge--neutral`)
      biar tipe kustom tak selalu warna "kelas". `form.tipe` default tetap `'kelas'`.
- [x] (Opsional) datalist di-isi dinamis dari `[...new Set(groups.map(g => g.tipe))]` biar saran ikut data nyata.
- [x] Tes browser: buat group tipe "Ekskul" via UI → tersimpan & tampil; edit tipe → berubah. Console bersih.

---

## 2. Verifikasi/cleanup (opsional — konfirmasi dulu)

Di luar kontrak, temuan audit. **Jangan hapus sebelum pastikan tak dipakai.**

- [x] `admin/views/jadwal.php` — orphan? Menu admin cuma 5 (tak ada submenu Jadwal). Konfirmasi ke BE,
      bila tak dirender → boleh dihapus FE.
      **2026-07-11:** confirmed orphan — `includes/Admin/Menu.php` hanya render
      `dashboard/users/group/laporan/settings`, tak pernah include `jadwal.php`. **Dihapus.**
- [x] `public/views/selfie.php`, `public/views/status.php` — view shortcode LAMA (`[absensi_selfie]`/
      `[absensi_status]`, di luar model kiosk v2). Biarkan atau hapus setelah pastikan tak ada page yang pakai.
      **RESOLVED 2026-07-11 (keputusan user: DIHAPUS).** Syarat "tak dipakai" terpenuhi: DB `wp_posts` =
      **nol page** pakai keduanya. Kedua view **DIHAPUS**. `public/views/` kini hanya `guru.php` + `siswa.php` (kiosk v2).
      → **Handoff BE (masih relevan):** cabut registrasi `add_shortcode('absensi_selfie'/'absensi_status')` di
      `Shortcodes.php` — handler-nya `include` view yang kini tak ada (tanpa `file_exists`), jadi kalau shortcode
      itu dipakai di suatu page akan muncul PHP warning. Registrasi menganggur → sebaiknya dicabut BE.

---

## Sudah selesai (sesi 2026-07-09, arsip singkat)

- ✅ Gate jam jadwal — label "Belum Waktunya" (guru `_errLabel`, selfie `resultTitle`).
- ✅ Kiosk RFID pilih sesi Masuk/Pulang — toggle guru + kirim `sesi`.
- ✅ Foto selfie WAJIB (masuk) — `canSubmit` butuh foto + hint; label kamera "wajib".
- ✅ Toggle sesi siswa tanpa "(opsional)" — default Masuk, wajib terpilih.
- ✅ Navbar kiosk → "Absensi".
- ✅ Admin Group — expand daftar user per group (klik baris).
